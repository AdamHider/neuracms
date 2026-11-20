<?php

declare(strict_types=1);

namespace FlatCms\Tests\Unit;

use FlatCms\Admin\UserRepository;
use FlatCms\Core\Request;
use FlatCms\Modules\Builder\Block;
use FlatCms\Modules\Builder\BuilderModule;
use FlatCms\Modules\Builder\Column;
use FlatCms\Modules\Builder\GlobalLibrary;
use FlatCms\Modules\Builder\Layout;
use FlatCms\Modules\Builder\LayoutRenderer;
use FlatCms\Modules\Builder\Row;
use FlatCms\Modules\Builder\Section;
use FlatCms\Modules\Builder\TypeRegistry;
use FlatCms\Tests\FlatCmsTestCase;

final class BuilderTest extends FlatCmsTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->copyFromProject('modules/builder');
        $this->copyFromProject('src/Admin');
        $this->copyFromProject('themes/default');
    }

    private function appWithBuilder(bool $withAdmin = false): \FlatCms\App
    {
        $modules = "modules:\n  builder: { enabled: true }\n";
        $admin = 'admin: { enabled: ' . ($withAdmin ? 'true' : 'false') . " }\n";
        $this->write('config/site.yaml', "site:\n  title: Test\n  theme: default\n" . $admin . $modules);

        return $this->app();
    }

    private function builder(\FlatCms\App $app): BuilderModule
    {
        $module = $app->module('builder');
        self::assertInstanceOf(BuilderModule::class, $module);

        return $module;
    }

    public function testCoreTypesAreRegistered(): void
    {
        $app = $this->appWithBuilder();
        $types = $this->builder($app)->types();

        self::assertInstanceOf(TypeRegistry::class, $types);
        foreach (['heading', 'text', 'image', 'button', 'quote', 'elements', 'media', 'divider', 'spacer', 'html', 'menu'] as $name) {
            self::assertTrue($types->has($name), "Missing block type: {$name}");
        }
    }

    public function testMediaBlockRendersImage(): void
    {
        $app = $this->appWithBuilder();
        $renderer = $this->builder($app)->renderer();
        self::assertNotNull($renderer);

        $html = $renderer->renderBlock(new Block('media', ['src' => '/media/index/logo.svg', 'alt' => 'Лого']));

        self::assertStringContainsString('block--media', $html);
        self::assertStringContainsString('src="/media/index/logo.svg"', $html);
        self::assertStringContainsString('alt="Лого"', $html);
    }

    public function testMediaBlockWithoutSourceRendersNothing(): void
    {
        $app = $this->appWithBuilder();
        $renderer = $this->builder($app)->renderer();
        self::assertNotNull($renderer);

        self::assertSame('', trim($renderer->renderBlock(new Block('media', []))));
    }

    public function testElementsBlockListsElementsWithPropertyMapping(): void
    {
        $this->write('config/schemas/service.yaml', "type: service\nlabel: Услуга\nfields:\n  description: { type: text }\n  price: { type: float }\n  link: { type: string }\nindexed: [price]\n");
        $this->write('content/services/site/item.yaml', "type: service\ntitle: Сайт\ndescription: Описание\nprice: 30000\nlink: /contact\norder: 1\n");
        $this->write('content/services/support/item.yaml', "type: service\ntitle: Поддержка\nprice: 5000\nlink: /contact\norder: 2\n");
        $app = $this->appWithBuilder();
        $renderer = $this->builder($app)->renderer();
        self::assertNotNull($renderer);

        $html = $renderer->renderBlock(new Block('elements', [
            'element_type' => 'service',
            'map_title' => 'title',
            'map_description' => 'description',
            'map_price' => 'price',
            'map_link' => 'link',
        ]));

        self::assertStringContainsString('element-card', $html);
        self::assertStringContainsString('Сайт', $html);
        self::assertStringContainsString('Описание', $html);
        self::assertStringContainsString('30000', $html);
        self::assertStringContainsString('href="/contact"', $html);
    }

    public function testElementsBlockWithoutTypeRendersNothing(): void
    {
        $app = $this->appWithBuilder();
        $renderer = $this->builder($app)->renderer();
        self::assertNotNull($renderer);

        self::assertSame('', trim($renderer->renderBlock(new Block('elements', []))));
    }

    public function testEditorRejectsElementItems(): void
    {
        $app = $this->appWithBuilder(true);
        (new UserRepository($app->fs))->create('admin', 'secret-password', 'admin');
        $app->handle(new Request('POST', '/admin/login', [], ['login' => 'admin', 'password' => 'secret-password'], [], [], []));

        // products/teapot is a "product" type, i.e. an element, not a page.
        $response = $app->handle(new Request('GET', '/admin/builder', ['id' => 'products/teapot'], [], [], [], []));

        self::assertSame(303, $response->status());
        self::assertStringStartsWith('/admin/content/edit', (string) $response->headers()['Location']);
    }

    public function testBuilderMediaUploadRequiresAdmin(): void
    {
        $app = $this->appWithBuilder(true);

        $response = $app->handle(new Request('POST', '/admin/builder/media', [], ['id' => 'about'], [], [], []));

        self::assertSame(403, $response->status());
    }

    public function testBuilderMediaUploadRejectsNonPages(): void
    {
        $app = $this->appWithBuilder(true);
        (new UserRepository($app->fs))->create('admin', 'secret-password', 'admin');
        $app->handle(new Request('POST', '/admin/login', [], ['login' => 'admin', 'password' => 'secret-password'], [], [], []));

        $response = $app->handle(new Request('POST', '/admin/builder/media', [], ['id' => 'products/teapot'], [], [], []));

        self::assertSame(404, $response->status());
    }

    public function testMediaLibraryListsAndDeletesUploads(): void
    {
        $library = new \FlatCms\Admin\MediaLibrary($this->app()->fs);
        $this->write('content/about/logo.svg', '<svg/>');

        self::assertContains('logo.svg', $library->list('about'));
        self::assertTrue($library->delete('about', 'logo.svg'));
        self::assertNotContains('logo.svg', $library->list('about'));
    }

    public function testLegacyGridUpgradesToSections(): void
    {
        $layout = Layout::fromValue([
            'grid' => [[6, 6]],
            'blocks' => [
                ['type' => 'heading', 'settings' => ['text' => 'Hi']],
                ['type' => 'text', 'settings' => ['body' => 'Body']],
            ],
        ]);

        self::assertCount(2, $layout->blocks());
        self::assertTrue($layout->hasSections());
        // One section per grid row, one column per block.
        self::assertCount(1, $layout->sections());
        self::assertSame([6, 6], $layout->sections()[0]->widths());
    }

    public function testLayoutAcceptsAPlainBlockList(): void
    {
        $layout = Layout::fromValue([['type' => 'heading', 'settings' => ['text' => 'Only']]]);

        self::assertCount(1, $layout->blocks());
        // Each bare block becomes its own full-width section.
        self::assertCount(1, $layout->sections());
        self::assertSame('heading', $layout->blocks()[0]->type);
    }

    public function testSectionsAndColumnsParse(): void
    {
        $layout = Layout::fromValue([
            'sections' => [
                [
                    'id' => 'hero',
                    'settings' => ['variant' => 'muted', 'width' => 'full'],
                    'columns' => [
                        ['width' => 8, 'blocks' => [['type' => 'heading', 'settings' => ['text' => 'Left']]]],
                        ['width' => 4, 'blocks' => [['type' => 'button', 'settings' => ['label' => 'Go']]]],
                    ],
                ],
            ],
        ]);

        self::assertCount(1, $layout->sections());
        $section = $layout->sections()[0];
        self::assertSame('muted', $section->get('variant'));
        self::assertSame([8, 4], $section->widths());
        self::assertCount(2, $section->blocks());
        self::assertSame('hero', $section->id());
    }

    public function testLegacySectionBlocksBecomeOneColumn(): void
    {
        $layout = Layout::fromValue([
            'sections' => [
                ['blocks' => [['type' => 'text', 'settings' => ['body' => 'A']]]],
            ],
        ]);

        self::assertCount(1, $layout->sections());
        self::assertCount(1, $layout->sections()[0]->columns());
        self::assertCount(1, $layout->blocks());
    }

    public function testLegacyBlocksAreKeptNextToExplicitSections(): void
    {
        $layout = Layout::fromValue([
            'sections' => [
                ['settings' => ['role' => 'header'], 'columns' => [['width' => 12, 'blocks' => [['type' => 'menu']]]]],
            ],
            'blocks' => [['type' => 'text', 'settings' => ['body' => 'Hand-written body']]],
        ]);

        self::assertCount(2, $layout->sections());
        self::assertCount(2, $layout->blocks());
        self::assertSame('Hand-written body', $layout->blocks()[1]->get('body'));
    }

    public function testBlockDefaultsAreApplied(): void
    {
        $app = $this->appWithBuilder();
        $type = $this->builder($app)->types()?->get('heading');
        self::assertNotNull($type);

        $settings = $type->withDefaults([]);

        self::assertSame('Заголовок', $settings['text']);
        self::assertSame('h2', $settings['level']);
    }

    public function testRendererRendersBlocksIntoHtml(): void
    {
        $app = $this->appWithBuilder();
        $renderer = $this->builder($app)->renderer();
        self::assertInstanceOf(LayoutRenderer::class, $renderer);

        $html = $renderer->renderBlock(new Block('heading', ['text' => 'Привет', 'level' => 'h1']));
        self::assertStringContainsString('<h1 class="block block--heading">Привет</h1>', $html);

        $text = $renderer->renderBlock(new Block('text', ['body' => '**жирный**']));
        self::assertStringContainsString('<strong>жирный</strong>', $text);
    }

    public function testUnknownBlockTypeIsSkipped(): void
    {
        $app = $this->appWithBuilder();
        $renderer = $this->builder($app)->renderer();
        self::assertNotNull($renderer);

        $html = $renderer->renderBlock(new Block('does-not-exist', []));

        self::assertStringContainsString('unknown block', $html);
    }

    public function testSectionsRenderColumnsWithSpans(): void
    {
        $app = $this->appWithBuilder();
        $renderer = $this->builder($app)->renderer();
        self::assertNotNull($renderer);

        $layout = Layout::fromValue([
            'sections' => [
                ['columns' => [
                    ['width' => 6, 'blocks' => [['type' => 'text', 'settings' => ['body' => 'A']]]],
                    ['width' => 6, 'blocks' => [['type' => 'text', 'settings' => ['body' => 'B']]]],
                ]],
                ['columns' => [
                    ['width' => 4, 'blocks' => [['type' => 'text', 'settings' => ['body' => 'C']]]],
                    ['width' => 8, 'blocks' => [['type' => 'text', 'settings' => ['body' => 'D']]]],
                ]],
            ],
        ]);

        $html = $renderer->renderLayout($layout);

        self::assertSame(2, substr_count($html, 'class="g-section"'));
        self::assertSame(2, substr_count($html, '<div class="g-row">'));
        self::assertStringContainsString('--g-span:6', $html);
        self::assertStringContainsString('--g-span:4', $html);
        self::assertStringContainsString('--g-span:8', $html);
    }

    public function testSectionSettingsRenderVariantAndContainer(): void
    {
        $app = $this->appWithBuilder();
        $renderer = $this->builder($app)->renderer();
        self::assertNotNull($renderer);

        $layout = Layout::fromValue([
            'sections' => [
                [
                    'settings' => ['variant' => 'dark', 'width' => 'full'],
                    'columns' => [['width' => 12, 'blocks' => [['type' => 'text', 'settings' => ['body' => 'X']]]]],
                ],
            ],
        ]);

        $html = $renderer->renderLayout($layout);

        self::assertStringContainsString('g-section--dark', $html);
        self::assertStringContainsString('g-container--full', $html);
    }

    public function testLegacyGridRendersAsSections(): void
    {
        $app = $this->appWithBuilder();
        $renderer = $this->builder($app)->renderer();
        self::assertNotNull($renderer);

        $layout = Layout::fromValue([
            'grid' => [[6, 6]],
            'blocks' => [
                ['type' => 'text', 'settings' => ['body' => 'A']],
                ['type' => 'text', 'settings' => ['body' => 'B']],
            ],
        ]);

        $html = $renderer->renderLayout($layout);

        self::assertStringContainsString('g-row', $html);
        self::assertStringContainsString('--g-span:6', $html);
    }

    /**
     * The columns particle is gone: rows and columns are structure, so the same
     * 8/4 split is expressed as a row with two columns.
     */
    public function testColumnsParticleIsReplacedByRows(): void
    {
        $app = $this->appWithBuilder();
        $renderer = $this->builder($app)->renderer();
        self::assertNotNull($renderer);

        $section = Section::fromArray([
            'rows' => [[
                'columns' => [
                    ['width' => 8, 'blocks' => [['type' => 'heading', 'settings' => ['text' => 'Внутри']]]],
                    ['width' => 4, 'blocks' => [['type' => 'text', 'settings' => ['body' => 'Текст']]]],
                ],
            ]],
        ], 12);

        $html = $renderer->renderSection($section);

        self::assertStringContainsString('--g-span:8', $html);
        self::assertStringContainsString('--g-span:4', $html);
        self::assertStringContainsString('Внутри', $html);
        self::assertStringContainsString('Текст', $html);
    }

    /**
     * The products particle is gone: an element list over the "product" type
     * shows the same cards, and it works for any element type.
     */
    public function testElementListRendersProducts(): void
    {
        $app = $this->appWithBuilder();
        $renderer = $this->builder($app)->renderer();
        self::assertNotNull($renderer);

        $html = $renderer->renderBlock(new Block('elements', [
            'element_type' => 'product',
            'limit' => 5,
            'map_price' => 'price',
        ]));

        self::assertStringContainsString('Teapot', $html);
        self::assertStringContainsString('1200', $html);
    }

    /**
     * A section can hold several rows, each with its own column split: that is
     * what replaced the old multi-column particle.
     */
    public function testSectionHoldsSeveralRowsWithOwnSplits(): void
    {
        $app = $this->appWithBuilder();
        $renderer = $this->builder($app)->renderer();
        self::assertNotNull($renderer);

        $section = Section::fromArray([
            'rows' => [
                ['columns' => [
                    ['width' => 12, 'blocks' => [['type' => 'heading', 'settings' => ['text' => 'Заголовок']]]],
                ]],
                ['columns' => [
                    ['width' => 6, 'blocks' => [['type' => 'text', 'settings' => ['body' => 'Слева']]]],
                    ['width' => 6, 'blocks' => [['type' => 'text', 'settings' => ['body' => 'Справа']]]],
                ]],
            ],
        ], 12);

        self::assertCount(2, $section->rows());
        self::assertCount(3, $section->columns());

        $html = $renderer->renderSection($section);
        self::assertSame(2, substr_count($html, 'class="g-row"'));
        self::assertStringContainsString('Слева', $html);
        self::assertStringContainsString('Справа', $html);
    }

    public function testRowAndColumnCarryTheirOwnTitleAndClass(): void
    {
        $app = $this->appWithBuilder();
        $renderer = $this->builder($app)->renderer();
        self::assertNotNull($renderer);

        $section = Section::fromArray([
            'title' => 'Герой',
            'class' => 'hero-band',
            'rows' => [[
                'title' => 'Верхний ряд',
                'class' => 'hero-row',
                'columns' => [[
                    'width' => 12,
                    'class' => 'hero-col',
                    'blocks' => [['type' => 'heading', 'settings' => ['text' => 'Привет']]],
                ]],
            ]],
        ], 12);

        self::assertSame('Герой', $section->title());
        self::assertSame('hero-band', $section->cssClass());
        self::assertSame('Верхний ряд', $section->rows()[0]->title());
        self::assertSame('hero-col', $section->columns()[0]->cssClass());

        $html = $renderer->renderSection($section);
        self::assertStringContainsString('hero-band', $html);
        self::assertStringContainsString('hero-row', $html);
        self::assertStringContainsString('hero-col', $html);
    }

    public function testBlockTitleAndClassRoundTripThroughSave(): void
    {
        $app = $this->appWithBuilder(true);
        $this->write('content/landing/item.yaml', "type: page\ntitle: Landing\n");
        $app->build();

        (new UserRepository($app->fs))->create('admin', 'secret-password', 'admin');
        $app->handle(new Request('POST', '/admin/login', [], ['login' => 'admin', 'password' => 'secret-password'], [], [], []));

        $layout = [
            'sections' => [[
                'title' => 'Герой',
                'rows' => [[
                    'columns' => [[
                        'width' => 12,
                        'blocks' => [[
                            'type' => 'heading',
                            'title' => 'Главный заголовок',
                            'class' => 'hero-title',
                            'settings' => ['text' => 'Привет'],
                        ]],
                    ]],
                ]],
            ]],
        ];

        $response = $app->handle(new Request('POST', '/admin/builder/save', [], [
            'id' => 'landing',
            'layout_json' => json_encode($layout),
        ], [], [], []));
        self::assertSame(303, $response->status());

        $saved = $app->storage->load('landing');
        self::assertNotNull($saved);
        $section = $saved->get('layout')['sections'][0];
        self::assertSame('Герой', $section['title']);
        self::assertSame('hero-title', $section['rows'][0]['columns'][0]['blocks'][0]['class']);
        self::assertSame('Главный заголовок', $section['rows'][0]['columns'][0]['blocks'][0]['title']);
    }

    public function testColumnAlignmentAndJustifyReachTheMarkup(): void
    {
        $app = $this->appWithBuilder();
        $renderer = $this->builder($app)->renderer();
        self::assertNotNull($renderer);

        $section = Section::fromArray([
            'rows' => [[
                'settings' => ['justify' => 'between', 'align' => 'center'],
                'columns' => [[
                    'width' => 6,
                    'settings' => ['justify' => 'end', 'align' => 'center'],
                    'blocks' => [['type' => 'text', 'settings' => ['body' => 'Текст']]],
                ]],
            ]],
        ], 12);

        $html = $renderer->renderSection($section);

        self::assertStringContainsString('--g-justify:space-between', $html);
        self::assertStringContainsString('--g-align:center', $html);
        self::assertStringContainsString('--g-justify:flex-end', $html);
    }

    /**
     * A section that only points at a global has no rows of its own. Reading it
     * back must not invent an empty row, or every save would grow the file.
     */
    public function testGlobalSectionReferenceKeepsNoRows(): void
    {
        $layout = Layout::fromValue([
            'sections' => [['settings' => ['global' => 'site-header']]],
        ], 12);

        $section = $layout->sections()[0];
        self::assertSame([], $section->rows());
        self::assertSame('site-header', $section->get('global'));
        self::assertArrayNotHasKey('rows', $section->toArray());
    }

    public function testNodeIdsAreUniqueAcrossLevels(): void
    {
        $section = Section::fromArray([
            'rows' => [['columns' => [['width' => 12, 'blocks' => []]]]],
        ], 12);

        $ids = [$section->id(), $section->rows()[0]->id(), $section->columns()[0]->id()];
        self::assertSame($ids, array_unique($ids));
    }

    public function testEmptySettingsAreNotPersisted(): void
    {
        $section = Section::fromArray(['rows' => [['columns' => [['width' => 12, 'blocks' => []]]]]], 12);
        $array = $section->toArray();

        self::assertArrayNotHasKey('settings', $array);
        self::assertArrayNotHasKey('settings', $array['rows'][0]);
        self::assertArrayNotHasKey('settings', $array['rows'][0]['columns'][0]);
    }

    public function testPageLayoutRendersThroughTemplate(): void
    {
        $app = $this->appWithBuilder();
        $this->write('content/landing/item.yaml', "type: page\ntitle: Landing\nlayout:\n  blocks:\n    - type: heading\n      settings: {text: Блок на странице, level: h2}\n");
        $this->write('content/landing/content.md', 'Fallback body');
        $app->build();

        $response = $app->handle(new Request('GET', '/landing', [], [], [], [], []));

        self::assertSame(200, $response->status());
        self::assertStringContainsString('Блок на странице', $response->body());
        self::assertStringNotContainsString('Fallback body', $response->body());
    }

    public function testPageWithoutLayoutRendersEmptyArticle(): void
    {
        $app = $this->appWithBuilder();
        $this->write('content/landing/item.yaml', "type: page\ntitle: Landing\n");
        $this->write('content/landing/content.md', 'Body that must not leak');
        $app->build();

        $response = $app->handle(new Request('GET', '/landing', [], [], [], [], []));

        self::assertSame(200, $response->status());
        self::assertStringContainsString('Landing', $response->body());
        self::assertStringNotContainsString('Body that must not leak', $response->body());
    }

    public function testEditorRequiresAdmin(): void
    {
        $app = $this->appWithBuilder(true);

        $response = $app->handle(new Request('GET', '/admin/builder', ['id' => 'about'], [], [], [], []));

        self::assertSame(302, $response->status());
        self::assertSame('/admin/login', $response->headers()['Location']);
    }

    public function testEditorPayloadSerializesTypesAsAList(): void
    {
        $app = $this->appWithBuilder(true);
        (new UserRepository($app->fs))->create('admin', 'secret-password', 'admin');
        $app->handle(new Request('POST', '/admin/login', [], ['login' => 'admin', 'password' => 'secret-password'], [], [], []));

        $body = $app->handle(new Request('GET', '/admin/builder', ['id' => 'about'], [], [], [], []))->body();

        self::assertSame(1, preg_match("/data-builder='(.*?)'/s", $body, $m), 'Editor must embed a data-builder payload');
        $payload = json_decode(html_entity_decode($m[1], ENT_QUOTES), true, 512, JSON_THROW_ON_ERROR);

        // The editor JS iterates types with forEach, so this must be a JSON list.
        self::assertArrayHasKey('types', $payload);
        self::assertTrue(array_is_list($payload['types']), 'types must serialize as a JSON array');
        self::assertNotEmpty($payload['types']);

        // The shared icon map lets the editor render semantic icon names.
        self::assertArrayHasKey('icons', $payload);
        self::assertSame('trash', $payload['icons']['delete'] ?? null);
    }

    public function testSaveSanitizesUnknownTypesAndPersists(): void
    {
        $app = $this->appWithBuilder(true);
        (new UserRepository($app->fs))->create('admin', 'secret-password', 'admin');
        $app->handle(new Request('POST', '/admin/login', [], ['login' => 'admin', 'password' => 'secret-password'], [], [], []));

        $json = json_encode(['blocks' => [
            ['type' => 'heading', 'settings' => ['text' => 'Из редактора', 'level' => 'h2']],
            ['type' => 'not-a-type', 'settings' => ['x' => '1']],
            ['type' => 'button', 'settings' => ['text' => 'Купить', 'url' => '/products']],
        ]], JSON_UNESCAPED_UNICODE);

        $response = $app->handle(new Request('POST', '/admin/builder/save', [], [
            'id' => 'about',
            'layout_json' => $json,
        ], [], [], []));

        self::assertSame(303, $response->status());

        $yaml = (string) file_get_contents($this->root . '/content/about/item.yaml');
        self::assertStringContainsString('Из редактора', $yaml);
        self::assertStringNotContainsString('not-a-type', $yaml);

        $page = $app->handle(new Request('GET', '/about', [], [], [], [], []));
        self::assertStringContainsString('Из редактора', $page->body());
        self::assertStringContainsString('/products', $page->body());
    }

    public function testSaveRejectsAnonymous(): void
    {
        $app = $this->appWithBuilder(true);

        $response = $app->handle(new Request('POST', '/admin/builder/save', [], [
            'id' => 'about',
            'layout_json' => '{"blocks":[]}',
        ], [], [], []));

        self::assertSame(302, $response->status());
        self::assertSame('/admin/login', $response->headers()['Location']);
        self::assertStringNotContainsString('layout', (string) file_get_contents($this->root . '/content/about/item.yaml'));
    }

    public function testNewBlockTypesRender(): void
    {
        $app = $this->appWithBuilder();
        $renderer = $this->builder($app)->renderer();
        self::assertNotNull($renderer);

        self::assertStringContainsString(
            'block--spacer-lg',
            $renderer->renderBlock(new Block('spacer', ['size' => 'lg']))
        );
        self::assertStringContainsString(
            'block--divider-dashed',
            $renderer->renderBlock(new Block('divider', ['style' => 'dashed']))
        );
        $quote = $renderer->renderBlock(new Block('quote', ['text' => 'Мысль', 'author' => 'Автор']));
        self::assertStringContainsString('Мысль', $quote);
        self::assertStringContainsString('Автор', $quote);
    }

    public function testSpacerRejectsUnknownSize(): void
    {
        $app = $this->appWithBuilder();
        $renderer = $this->builder($app)->renderer();
        self::assertNotNull($renderer);

        $html = $renderer->renderBlock(new Block('spacer', ['size' => '<script>']));

        self::assertStringContainsString('block--spacer-md', $html);
        self::assertStringNotContainsString('<script>', $html);
    }

    public function testSaveSanitizesSectionsAndPersists(): void
    {
        $app = $this->appWithBuilder(true);
        (new UserRepository($app->fs))->create('admin', 'secret-password', 'admin');
        $app->handle(new Request('POST', '/admin/login', [], ['login' => 'admin', 'password' => 'secret-password'], [], [], []));

        $json = json_encode(['sections' => [
            [
                'id' => 'hero',
                'settings' => ['variant' => 'muted', 'width' => 'full'],
                'columns' => [
                    ['width' => 6, 'blocks' => [['type' => 'heading', 'settings' => ['text' => 'Секция', 'level' => 'h1']]]],
                    ['width' => 6, 'blocks' => [
                        ['type' => 'text', 'settings' => ['body' => 'Тело']],
                        ['type' => 'not-a-type', 'settings' => ['x' => '1']],
                    ]],
                ],
            ],
        ]], JSON_UNESCAPED_UNICODE);

        $response = $app->handle(new Request('POST', '/admin/builder/save', [], [
            'id' => 'about',
            'layout_json' => $json,
        ], [], [], []));

        self::assertSame(303, $response->status());

        $yaml = (string) file_get_contents($this->root . '/content/about/item.yaml');
        self::assertStringContainsString('sections:', $yaml);
        self::assertStringContainsString('variant: muted', $yaml);
        self::assertStringNotContainsString('not-a-type', $yaml);

        $page = $app->handle(new Request('GET', '/about', [], [], [], [], []));
        self::assertStringContainsString('g-section--muted', $page->body());
        self::assertStringContainsString('g-container--full', $page->body());
        self::assertStringContainsString('--g-span:6', $page->body());
        self::assertStringContainsString('Секция', $page->body());
    }

    public function testPresetsAreBodyLayoutsOnly(): void
    {
        $app = $this->appWithBuilder(true);
        (new UserRepository($app->fs))->create('admin', 'secret-password', 'admin');
        $app->handle(new Request('POST', '/admin/login', [], ['login' => 'admin', 'password' => 'secret-password'], [], [], []));

        $body = $app->handle(new Request('GET', '/admin/builder', ['id' => 'about'], [], [], [], []))->body();

        self::assertSame(1, preg_match("/data-builder='(.*?)'/s", $body, $m));
        $payload = json_decode(html_entity_decode($m[1], ENT_QUOTES), true, 512, JSON_THROW_ON_ERROR);

        $presets = [];
        foreach ($payload['presets'] as $preset) {
            $presets[$preset['name']] = $preset;
        }

        self::assertSame([12], $presets['one']['widths']);
        self::assertSame([9, 3], $presets['sidebar']['widths']);
        // Site chrome is a global section, offered from the same menu separately,
        // so it must not appear here as a preset.
        self::assertArrayNotHasKey('header', $presets);
        self::assertArrayNotHasKey('footer', $presets);
    }

    public function testGlobalSectionsAreOfferedInTheAddSectionMenuNotTheLibrary(): void
    {
        $app = $this->appWithBuilder(true);
        $library = $this->builder($app)->globals();
        self::assertNotNull($library);
        $library->save('section', 'site-header', 'Шапка сайта', [
            'section' => (new Section([
                new Row([new Column([new Block('text', ['body' => 'ШАПКА'])], 12)]),
            ], ['role' => 'header']))->toArray(),
        ]);

        (new UserRepository($app->fs))->create('admin', 'secret-password', 'admin');
        $app->handle(new Request('POST', '/admin/login', [], ['login' => 'admin', 'password' => 'secret-password'], [], [], []));

        $body = $app->handle(new Request('GET', '/admin/builder', ['id' => 'about'], [], [], [], []))->body();

        // The section lives in the "Добавить секцию" dropdown...
        self::assertStringContainsString('data-global-section-add="site-header"', $body);
        // ...and the payload carries no separate copy of its structure: a global
        // is expanded into the page layout itself, where it is edited in place.
        self::assertSame(1, preg_match("/data-builder='(.*?)'/s", $body, $m));
        $payload = json_decode(html_entity_decode($m[1], ENT_QUOTES), true, 512, JSON_THROW_ON_ERROR);
        self::assertArrayNotHasKey('globalStructures', $payload);
        self::assertArrayHasKey('site-header', $payload['globals']['section']);
    }

    public function testGlobalBlockStructuresAreShippedForTheCanvas(): void
    {
        $app = $this->appWithBuilder(true);
        $this->builder($app)->globals()?->save('block', 'cta', 'Кнопка CTA', [
            'block' => (new Block('button', ['text' => 'Связаться', 'url' => '/contact']))->toArray(),
        ]);

        (new UserRepository($app->fs))->create('admin', 'secret-password', 'admin');
        $app->handle(new Request('POST', '/admin/login', [], ['login' => 'admin', 'password' => 'secret-password'], [], [], []));

        $body = $app->handle(new Request('GET', '/admin/builder', ['id' => 'about'], [], [], [], []))->body();

        self::assertSame(1, preg_match("/data-builder='(.*?)'/s", $body, $m));
        $payload = json_decode(html_entity_decode($m[1]), true, 512, JSON_THROW_ON_ERROR);

        // The catalogue still ships so the library can list what is available,
        // but the block's own structure is not duplicated in the payload.
        self::assertArrayHasKey('cta', $payload['globals']['block']);
        self::assertArrayNotHasKey('globalStructures', $payload);
    }

    /**
     * A module-provided block must survive promotion intact. The editor button
     * that starts the flow emitted "data-block-promote" while the click handler
     * listened for "data-promote-block", so the control did nothing at all.
     */
    public function testModuleBlockPromotesWithItsSettingsIntact(): void
    {
        $app = $this->appWithBuilder(true);
        (new UserRepository($app->fs))->create('admin', 'secret-password', 'admin');
        $app->handle(new Request('POST', '/admin/login', [], ['login' => 'admin', 'password' => 'secret-password'], [], [], []));

        $layout = [
            'sections' => [
                [
                    'rows' => [['columns' => [[
                        'width' => 12,
                        'blocks' => [[
                            'type' => 'menu',
                            'settings' => [
                                'source' => 'pages',
                                'class' => 'site-nav',
                                'orientation' => 'horizontal',
                                'filters' => [['field' => 'show_in_menu', 'op' => '=', 'value' => '1']],
                            ],
                        ]],
                    ]]]],
                ],
            ],
        ];

        $response = $app->handle(new Request('POST', '/admin/builder/global', [], [
            'action' => 'promote',
            'kind' => 'block',
            'title' => 'Меню шапки',
            'block_index' => '0:0:0:0',
            'layout_json' => json_encode($layout),
        ], [], [], []));

        self::assertSame(200, $response->status());
        $block = (new GlobalLibrary($app->fs))->block('menyu-shapki');
        self::assertNotNull($block);
        self::assertSame('menu', $block->type);
        self::assertSame('site-nav', $block->get('class'));
        self::assertSame([['field' => 'show_in_menu', 'op' => '=', 'value' => '1']], $block->get('filters'));
    }

    public function testPromoteButtonAttributeMatchesTheClickHandler(): void
    {
        $js = (string) file_get_contents(__DIR__ . '/../../modules/builder/assets/js/builder.js');

        // Whatever the card emits, the delegated handler has to listen for it.
        $emitted = preg_match_all('/data-(promote-block|block-promote)=/', $js, $m) ? $m[1] : [];
        self::assertNotEmpty($emitted, 'the block card must render a promote control');
        $attribute = 'data-' . $emitted[0] . '=';

        self::assertStringContainsString("closest('[" . rtrim($attribute, '=') . "]')", $js);
    }

    public function testHeaderAndFooterPresetsRenderAsSections(): void
    {
        $app = $this->appWithBuilder();
        $renderer = $this->builder($app)->renderer();
        self::assertNotNull($renderer);

        $layout = Layout::fromValue(['sections' => [
            ['columns' => [
                ['width' => 4, 'blocks' => [['type' => 'heading', 'settings' => ['text' => 'Мой сайт', 'level' => 'h2']]]],
                ['width' => 8, 'blocks' => [['type' => 'menu', 'settings' => ['source' => 'pages', 'class' => 'site-nav']]]],
            ]],
        ]], 12);

        $html = $renderer->renderLayout($layout);
        self::assertStringContainsString('Мой сайт', $html);
        self::assertStringContainsString('site-nav', $html);
    }

    public function testMenuParticleFiltersPagesByCustomProperty(): void
    {
        // The user adds a custom bool property to the page type and uses it to
        // decide which pages appear in the menu.
        $this->write('config/schemas/page.yaml', "type: page\nlabel: Страница\nbuiltin: true\nfields:\n  title: { type: string, required: true }\n  show_in_menu: { type: bool, default: true }\n");
        $this->write('content/about/item.yaml', "type: page\ntitle: О нас\nshow_in_menu: true\norder: 1\n");
        $this->write('content/private/item.yaml', "type: page\ntitle: Скрытая\nshow_in_menu: false\norder: 2\n");

        $app = $this->appWithBuilder();
        $renderer = $this->builder($app)->renderer();
        self::assertNotNull($renderer);

        $html = $renderer->renderBlock(new Block('menu', [
            'source' => 'pages',
            'content_type' => 'page',
            'filters' => [['field' => 'show_in_menu', 'op' => '=', 'value' => '1']],
        ]));

        self::assertStringContainsString('О нас', $html);
        self::assertStringNotContainsString('Скрытая', $html);
    }

    public function testMenuParticleSupportsMultipleFilters(): void
    {
        $this->write('config/schemas/page.yaml', "type: page\nlabel: Страница\nbuiltin: true\nfields:\n  title: { type: string, required: true }\n  show_in_menu: { type: bool, default: true }\n  group: { type: string }\n");
        $this->write('content/about/item.yaml', "type: page\ntitle: О нас\nshow_in_menu: true\ngroup: main\norder: 1\n");
        $this->write('content/team/item.yaml', "type: page\ntitle: Команда\nshow_in_menu: true\ngroup: secondary\norder: 2\n");
        $this->write('content/private/item.yaml', "type: page\ntitle: Скрытая\nshow_in_menu: false\ngroup: main\norder: 3\n");

        $app = $this->appWithBuilder();
        $renderer = $this->builder($app)->renderer();
        self::assertNotNull($renderer);

        $html = $renderer->renderBlock(new Block('menu', [
            'source' => 'pages',
            'content_type' => 'page',
            'filters' => [
                ['field' => 'show_in_menu', 'op' => '=', 'value' => '1'],
                ['field' => 'group', 'op' => '=', 'value' => 'main'],
            ],
        ]));

        self::assertStringContainsString('О нас', $html);
        self::assertStringNotContainsString('Команда', $html);
        self::assertStringNotContainsString('Скрытая', $html);
    }

    public function testMenuFiltersSurviveSaveAndRender(): void
    {
        $this->write('config/schemas/page.yaml', "type: page\nlabel: Страница\nbuiltin: true\nfields:\n  title: { type: string, required: true }\n  show_in_menu: { type: bool, default: true }\n");
        $this->write('content/about/item.yaml', "type: page\ntitle: About\nshow_in_menu: true\norder: 1\n");
        $this->write('content/index/item.yaml', "type: page\ntitle: Home\norder: 0\n");

        $app = $this->appWithBuilder(true);
        (new UserRepository($app->fs))->create('admin', 'secret-password', 'admin');
        $app->handle(new Request('POST', '/admin/login', [], ['login' => 'admin', 'password' => 'secret-password'], [], [], []));

        $json = json_encode(['sections' => [[
            'columns' => [[
                'width' => 12,
                'blocks' => [[
                    'type' => 'menu',
                    'settings' => [
                        'source' => 'pages',
                        'content_type' => 'page',
                        'filters' => [['field' => 'show_in_menu', 'op' => '=', 'value' => '1']],
                    ],
                ]],
            ]],
        ]]], JSON_UNESCAPED_UNICODE);

        $response = $app->handle(new Request('POST', '/admin/builder/save', [], [
            'id' => 'about',
            'layout_json' => $json,
        ], [], [], []));
        self::assertSame(303, $response->status());

        $yaml = (string) file_get_contents($this->root . '/content/about/item.yaml');
        self::assertStringContainsString('filters:', $yaml);
        self::assertStringContainsString('show_in_menu', $yaml);

        $page = $app->handle(new Request('GET', '/about', [], [], [], [], []));
        self::assertStringContainsString('block--menu', $page->body());
        self::assertStringContainsString('>About<', $page->body());
    }
}
