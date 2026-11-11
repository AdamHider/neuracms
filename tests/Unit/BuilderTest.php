<?php

declare(strict_types=1);

namespace FlatCms\Tests\Unit;

use FlatCms\Admin\UserRepository;
use FlatCms\Core\Request;
use FlatCms\Modules\Builder\Block;
use FlatCms\Modules\Builder\BuilderModule;
use FlatCms\Modules\Builder\Layout;
use FlatCms\Modules\Builder\LayoutRenderer;
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
        foreach (['heading', 'text', 'image', 'button', 'quote', 'columns', 'products', 'divider', 'spacer', 'html'] as $name) {
            self::assertTrue($types->has($name), "Missing block type: {$name}");
        }
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

    public function testNestedColumnsBlockRendersChildren(): void
    {
        $app = $this->appWithBuilder();
        $renderer = $this->builder($app)->renderer();
        self::assertNotNull($renderer);

        $html = $renderer->renderBlock(new Block('columns', [
            'ratio' => '8-4',
            'left' => [['type' => 'heading', 'settings' => ['text' => 'Внутри']]],
            'right' => [['type' => 'text', 'settings' => ['body' => 'Текст']]],
        ]));

        self::assertStringContainsString('--left:8;--right:4', $html);
        self::assertStringContainsString('Внутри', $html);
        self::assertStringContainsString('Текст', $html);
    }

    public function testProductsBlockListsProducts(): void
    {
        $app = $this->appWithBuilder();
        $renderer = $this->builder($app)->renderer();
        self::assertNotNull($renderer);

        $html = $renderer->renderBlock(new Block('products', ['limit' => 5]));

        self::assertStringContainsString('Teapot', $html);
        self::assertStringContainsString('1 200 ₽', $html);
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

    public function testPageWithoutBlocksFallsBackToMarkdown(): void
    {
        $app = $this->appWithBuilder();

        $response = $app->handle(new Request('GET', '/about', [], [], [], [], []));

        self::assertStringContainsString('About', $response->body());
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
}
