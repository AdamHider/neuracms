<?php

declare(strict_types=1);

namespace FlatCms\Tests\Unit;

use FlatCms\Admin\UserRepository;
use FlatCms\Core\Request;
use FlatCms\Modules\Builder\Block;
use FlatCms\Modules\Builder\Column;
use FlatCms\Modules\Builder\GlobalLibrary;
use FlatCms\Modules\Builder\Layout;
use FlatCms\Modules\Builder\Row;
use FlatCms\Modules\Builder\Section;
use FlatCms\Tests\FlatCmsTestCase;

/**
 * The flat page model: header and footer bands are ordinary top-level sections
 * with a structural role, and any section or block can be promoted to a global
 * that every page references.
 */
final class GlobalsTest extends FlatCmsTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->copyFromProject('modules/builder');
        $this->copyFromProject('src/Admin');
        $this->copyFromProject('themes/default');
    }

    private function appWithBuilder(): \FlatCms\App
    {
        $this->write('config/site.yaml', "site:\n  title: Test\n  theme: default\nadmin: { enabled: true }\nmodules:\n  builder: { enabled: true }\n");

        return $this->app();
    }

    public function testGlobalSectionRoundTripsThroughLibrary(): void
    {
        $app = $this->appWithBuilder();
        $library = new GlobalLibrary($app->fs);

        $section = new Section([new Row([new Column([new Block('heading', ['text' => 'Меню', 'level' => 'h2'])], 12)])], ['role' => 'header']);
        self::assertTrue($library->save(GlobalLibrary::SECTION, 'Шапка сайта', 'Шапка сайта', ['section' => $section->toArray()]));

        $names = array_keys($library->all(GlobalLibrary::SECTION));
        self::assertSame(['shapka-sayta'], $names);

        $loaded = $library->section('shapka-sayta');
        self::assertNotNull($loaded);
        self::assertSame('header', $loaded->role());
        self::assertCount(1, $loaded->blocks());
        self::assertSame('Меню', $loaded->blocks()[0]->get('text'));
    }

    public function testReferenceResolvesToSharedContentAndRole(): void
    {
        $app = $this->appWithBuilder();
        $library = new GlobalLibrary($app->fs);
        $shared = new Section(
            [new Row([new Column([new Block('heading', ['text' => 'Глобальный заголовок', 'level' => 'h1'])], 12)])],
            ['role' => 'header']
        );
        $library->save(GlobalLibrary::SECTION, 'header-global', 'Глобальная шапка', ['section' => $shared->toArray()]);

        $layout = new Layout([
            new Section([], ['global' => 'header-global']),
            new Section([new Row([new Column([new Block('text', ['body' => 'Страница'])], 12)])]),
        ]);
        $this->write('content/about/item.yaml', "type: page\ntitle: About\norder: 10\nlayout:\n" . $this->yamlLayout($layout));

        $renderer = $app->module('builder')->renderer();
        self::assertNotNull($renderer);

        $about = $app->storage->load('about');
        self::assertNotNull($about);

        $html = $renderer->renderPage($about);
        self::assertStringContainsString('Глобальный заголовок', $html);
        self::assertStringContainsString('Страница', $html);
    }

    public function testPromoteSectionCopiesItIntoTheLibrary(): void
    {
        $app = $this->appWithBuilder();
        (new UserRepository($app->fs))->create('admin', 'secret-password', 'admin');
        $app->handle(new Request('POST', '/admin/login', [], ['login' => 'admin', 'password' => 'secret-password'], [], [], []));

        $layout = [
            'sections' => [
                [
                    'id' => 's1',
                    'settings' => ['role' => 'header'],
                    'columns' => [[
                        'width' => 12,
                        'blocks' => [['type' => 'heading', 'settings' => ['text' => 'Логотип', 'level' => 'h2']]],
                    ]],
                ],
            ],
        ];

        $response = $app->handle(new Request('POST', '/admin/builder/global', [], [
            'action' => 'promote',
            'kind' => 'section',
            'title' => 'Шапка',
            'index' => '0',
            'layout_json' => json_encode($layout),
        ], [], [], []));

        self::assertSame(200, $response->status());
        $result = json_decode($response->body(), true);
        self::assertSame('shapka', $result['name']);

        $library = new GlobalLibrary($app->fs);
        $stored = $library->section('shapka');
        self::assertNotNull($stored);
        self::assertSame('header', $stored->role());
        self::assertSame('Логотип', $stored->blocks()[0]->get('text'));
    }

    public function testPromoteBlockCopiesItIntoTheLibrary(): void
    {
        $app = $this->appWithBuilder();
        (new UserRepository($app->fs))->create('admin', 'secret-password', 'admin');
        $app->handle(new Request('POST', '/admin/login', [], ['login' => 'admin', 'password' => 'secret-password'], [], [], []));

        $layout = [
            'sections' => [
                [
                    'columns' => [[
                        'width' => 12,
                        'blocks' => [
                            ['type' => 'heading', 'settings' => ['text' => 'Первый']],
                            ['type' => 'button', 'settings' => ['text' => 'Купить', 'url' => '/shop']],
                        ],
                    ]],
                ],
            ],
        ];

        $response = $app->handle(new Request('POST', '/admin/builder/global', [], [
            'action' => 'promote',
            'kind' => 'block',
            'title' => 'Кнопка «Купить»',
            'block_index' => '0:0:1',
            'layout_json' => json_encode($layout),
        ], [], [], []));

        self::assertSame(200, $response->status());
        $result = json_decode($response->body(), true);
        self::assertSame('knopka-kupit', $result['name']);

        $block = (new GlobalLibrary($app->fs))->block('knopka-kupit');
        self::assertNotNull($block);
        self::assertSame('button', $block->type);
        self::assertSame('/shop', $block->get('url'));
    }

    public function testPromoteAcceptsTheFullSectionRowColumnBlockPath(): void
    {
        $app = $this->appWithBuilder();
        (new UserRepository($app->fs))->create('admin', 'secret-password', 'admin');
        $app->handle(new Request('POST', '/admin/login', [], ['login' => 'admin', 'password' => 'secret-password'], [], [], []));

        // A header section sits at index 1, so a path that drops the section index
        // would hit the wrong block (or none at all).
        $layout = [
            'sections' => [
                ['rows' => [['columns' => [['width' => 12, 'blocks' => [['type' => 'text', 'settings' => ['body' => 'ТЕЛО']]]]]]]],
                [
                    'settings' => ['role' => 'header'],
                    'rows' => [['columns' => [[
                        'width' => 12,
                        'blocks' => [['type' => 'button', 'settings' => ['text' => 'Войти', 'url' => '/login']]],
                    ]]]],
                ],
            ],
        ];

        $response = $app->handle(new Request('POST', '/admin/builder/global', [], [
            'action' => 'promote',
            'kind' => 'block',
            'title' => 'Кнопка входа',
            'block_index' => '1:0:0:0',
            'layout_json' => json_encode($layout),
        ], [], [], []));

        self::assertSame(200, $response->status());
        $block = (new GlobalLibrary($app->fs))->block('knopka-vhoda');
        self::assertNotNull($block);
        self::assertSame('/login', $block->get('url'));
    }

    public function testPageReferencingAGlobalSectionRendersItsContent(): void
    {
        $app = $this->appWithBuilder();
        $library = new GlobalLibrary($app->fs);
        $library->save(GlobalLibrary::SECTION, 'site-header', 'Шапка сайта', [
            'section' => (new Section([
                new Row([new Column([new Block('text', ['body' => 'ОБЩАЯ-ШАПКА'])], 12)]),
            ], ['role' => 'header']))->toArray(),
        ]);

        $this->write('content/about/item.yaml', "type: page\ntitle: О нас\nlayout:\n"
            . "  sections:\n"
            . "    - settings: {global: site-header}\n"
            . "    - rows:\n"
            . "        - columns:\n"
            . "            - width: 12\n"
            . "              blocks:\n"
            . "                - type: text\n"
            . "                  settings: { body: ТЕЛО }\n");
        $app->build();

        $html = $app->handle(new Request('GET', '/about', [], [], [], [], []))->body();

        self::assertStringContainsString('ОБЩАЯ-ШАПКА', $html);
        self::assertStringContainsString('ТЕЛО', $html);
    }

    public function testGlobalBlockReferenceRendersSharedContent(): void
    {
        $app = $this->appWithBuilder();
        $library = new GlobalLibrary($app->fs);
        $library->save(GlobalLibrary::BLOCK, 'cta', 'Кнопка CTA', [
            'block' => ['type' => 'button', 'settings' => ['text' => 'Связаться', 'url' => '/contact']],
        ]);

        $renderer = $app->module('builder')->renderer();
        self::assertNotNull($renderer);

        $html = $renderer->renderBlock(new Block('global', ['name' => 'cta']));
        self::assertStringContainsString('Связаться', $html);
        self::assertStringContainsString('/contact', $html);
    }

    public function testMissingGlobalRendersNothingInsteadOfBreaking(): void
    {
        $app = $this->appWithBuilder();
        $renderer = $app->module('builder')->renderer();
        self::assertNotNull($renderer);

        self::assertSame('', trim($renderer->renderBlock(new Block('global', ['name' => 'nope']))));
    }

    /**
     * Indent a layout array as YAML under a "layout:" key of item.yaml.
     */
    private function yamlLayout(Layout $layout): string
    {
        $yaml = \Symfony\Component\Yaml\Yaml::dump($layout->toArray(), 8, 2);

        return '  ' . str_replace("\n", "\n  ", rtrim($yaml)) . "\n";
    }

    public function testHeaderAndFooterSectionsOrderAroundBody(): void
    {
        $app = $this->appWithBuilder();
        $library = new GlobalLibrary($app->fs);

        $layout = new Layout([
            new Section([new Row([new Column([new Block('text', ['body' => 'ТЕЛО-СТРАНИЦЫ'])], 12)])]),
            new Section([new Row([new Column([new Block('text', ['body' => 'ПОДВАЛ'])], 12)])], ['role' => 'footer']),
            new Section([new Row([new Column([new Block('text', ['body' => 'ШАПКА'])], 12)])], ['role' => 'header']),
        ]);

        $ordered = [];
        foreach ($layout->ordered() as $section) {
            $ordered[] = $section->blocks()[0]->get('body');
        }

        self::assertSame(['ШАПКА', 'ТЕЛО-СТРАНИЦЫ', 'ПОДВАЛ'], $ordered);
    }

    public function testPageWithoutItemBorrowsChromeFromTheHomePage(): void
    {
        $app = $this->appWithBuilder();
        $renderer = $app->module('builder')->renderer();
        self::assertNotNull($renderer);

        $layout = new Layout([
            new Section([new Row([new Column([new Block('text', ['body' => 'ШАПКА'])], 12)])], ['role' => 'header']),
        ]);
        $this->write('content/index/item.yaml', "type: page\ntitle: Home\norder: 0\nlayout:\n" . $this->yamlLayout($layout));
        $app->build();

        $html = $app->renderer->page(null, '<p>Тело</p>');

        self::assertStringContainsString('site-header', $html);
        self::assertStringContainsString('<p>Тело</p>', $html);
        self::assertLessThan(strpos($html, '<p>Тело</p>'), strpos($html, 'site-header'));
    }

    /**
     * The header is a section, not a theme fallback: a page whose layout has no
     * header section and no home page to borrow from renders without one.
     */
    public function testPageWithoutHeaderSectionRendersNoHeader(): void
    {
        $app = $this->appWithBuilder();
        $renderer = $app->module('builder')->renderer();
        self::assertNotNull($renderer);

        $layout = new Layout([
            new Section([new Row([new Column([new Block('text', ['body' => 'Только тело'])], 12)])]),
        ]);

        $html = $renderer->renderLayout($layout);
        self::assertStringNotContainsString('site-header', $html);

        $item = new \FlatCms\Content\Item('about', ['type' => 'page', 'title' => 'About', 'layout' => $layout->toArray()]);
        $page = $renderer->pageShell($item, '<p>Тело</p>');

        self::assertStringNotContainsString('site-header', $page);
        self::assertStringContainsString('<p>Тело</p>', $page);
    }

    /**
     * The separate globals page is gone: shared content is edited on whatever
     * page shows it, so its URL must not exist any more.
     */
    public function testGlobalsPageIsGone(): void
    {
        $app = $this->appWithBuilder();
        (new UserRepository($app->fs))->create('admin', 'secret-password', 'admin');
        $app->handle(new Request('POST', '/admin/login', [], ['login' => 'admin', 'password' => 'secret-password'], [], [], []));

        $response = $app->handle(new Request('GET', '/admin/globals', [], [], [], [], []));

        self::assertSame(404, $response->status());
    }

    /**
     * A shared section opens expanded on the page that references it, so its
     * blocks can be edited right there instead of on a separate screen.
     */
    public function testGlobalSectionOpensExpandedInThePageEditor(): void
    {
        $app = $this->appWithBuilder();
        (new UserRepository($app->fs))->create('admin', 'secret-password', 'admin');
        $app->handle(new Request('POST', '/admin/login', [], ['login' => 'admin', 'password' => 'secret-password'], [], [], []));

        $library = new GlobalLibrary($app->fs);
        $library->save(GlobalLibrary::SECTION, 'site-header', 'Шапка сайта', [
            'section' => (new Section([
                new Row([new Column([new Block('heading', ['text' => 'ШАПКА', 'level' => 'h2'])], 12)]),
            ], ['role' => 'header']))->toArray(),
        ]);
        $this->write('content/about/item.yaml', "type: page\ntitle: About\nlayout:\n  sections:\n    - settings: {global: site-header}\n");

        $body = $app->handle(new Request('GET', '/admin/builder', ['id' => 'about'], [], [], [], []))->body();

        self::assertSame(1, preg_match("/data-builder='(.*?)'/s", $body, $m));
        $payload = json_decode(html_entity_decode($m[1], ENT_QUOTES), true, 512, JSON_THROW_ON_ERROR);

        $section = $payload['layout']['sections'][0];
        // The reference is still marked as a reference...
        self::assertSame('site-header', $section['settings']['global']);
        // ...but its rows are the shared ones, not an empty list.
        self::assertSame('ШАПКА', $section['rows'][0]['columns'][0]['blocks'][0]['settings']['text']);
    }

    /**
     * Editing a shared band on one page writes the new content to the library
     * and leaves a reference on the page, so every other page picks it up.
     */
    public function testEditingAGlobalInPlaceUpdatesTheLibraryAndKeepsAReference(): void
    {
        $app = $this->appWithBuilder();
        (new UserRepository($app->fs))->create('admin', 'secret-password', 'admin');
        $app->handle(new Request('POST', '/admin/login', [], ['login' => 'admin', 'password' => 'secret-password'], [], [], []));

        $library = new GlobalLibrary($app->fs);
        $library->save(GlobalLibrary::SECTION, 'site-header', 'Шапка сайта', [
            'section' => (new Section([
                new Row([new Column([new Block('heading', ['text' => 'СТАРОЕ', 'level' => 'h2'])], 12)]),
            ], ['role' => 'header']))->toArray(),
        ]);
        $this->write('content/about/item.yaml', "type: page\ntitle: About\nlayout:\n  sections:\n    - settings: {global: site-header}\n");

        // What the editor posts back: the expanded band, edited.
        $posted = ['sections' => [[
            'settings' => ['global' => 'site-header', 'role' => 'header'],
            'rows' => [['columns' => [[
                'width' => 12,
                'blocks' => [['type' => 'heading', 'settings' => ['text' => 'НОВОЕ', 'level' => 'h2']]],
            ]]]],
        ]]];

        $app->handle(new Request('POST', '/admin/builder/save', [], [
            'id' => 'about',
            'layout_json' => json_encode($posted),
        ], [], [], []));

        $shared = (new GlobalLibrary($app->fs))->section('site-header');
        self::assertNotNull($shared);
        self::assertSame('НОВОЕ', $shared->blocks()[0]->get('text'));
        // The human title and the library order sit next to the payload, so an
        // in-place edit must not overwrite them with the slug or drop them.
        $stored = (new GlobalLibrary($app->fs))->read(GlobalLibrary::SECTION, 'site-header');
        self::assertSame('Шапка сайта', $stored['title'] ?? null);

        $page = $app->storage->load('about');
        self::assertNotNull($page);
        $saved = $page->get('layout');
        self::assertSame('site-header', $saved['sections'][0]['settings']['global']);
        // The page must not carry a duplicate copy of the shared rows.
        self::assertArrayNotHasKey('rows', $saved['sections'][0]);
    }

    /**
     * A global may carry an explicit order for the library listing. Saving a
     * page that shows it must leave that key untouched.
     */
    public function testEditingAGlobalInPlaceKeepsItsLibraryOrder(): void
    {
        $app = $this->appWithBuilder();
        (new UserRepository($app->fs))->create('admin', 'secret-password', 'admin');
        $app->handle(new Request('POST', '/admin/login', [], ['login' => 'admin', 'password' => 'secret-password'], [], [], []));

        $this->write('content/_global/section/site-header/item.yaml', <<<'YAML'
            title: Шапка сайта
            kind: section
            order: 7
            section:
              settings: {role: header}
              rows:
                - columns:
                    - width: 12
                      blocks:
                        - {type: heading, settings: {text: СТАРОЕ, level: h2}}
            YAML);
        $this->write('content/about/item.yaml', "type: page\ntitle: About\nlayout:\n  sections:\n    - settings: {global: site-header}\n");

        $app->handle(new Request('POST', '/admin/builder/save', [], [
            'id' => 'about',
            'layout_json' => json_encode(['sections' => [[
                'settings' => ['global' => 'site-header', 'role' => 'header'],
                'rows' => [['columns' => [[
                    'width' => 12,
                    'blocks' => [['type' => 'heading', 'settings' => ['text' => 'НОВОЕ', 'level' => 'h2']]],
                ]]]],
            ]]]),
        ], [], [], []));

        $stored = (new GlobalLibrary($app->fs))->read(GlobalLibrary::SECTION, 'site-header');
        self::assertSame(7, $stored['order'] ?? null);
        self::assertSame('Шапка сайта', $stored['title'] ?? null);
    }

    /**
     * Promotion used to post the whole canvas and then reload the page, so the
     * editor threw away everything the user had typed and came back from the
     * server. The request must not reload, and it must not drag the rest of the
     * canvas along either.
     */
    public function testPromotingASectionDoesNotReloadOrPostTheWholeCanvas(): void
    {
        $app = $this->appWithBuilder();
        (new UserRepository($app->fs))->create('admin', 'secret-password', 'admin');
        $app->handle(new Request('POST', '/admin/login', [], ['login' => 'admin', 'password' => 'secret-password'], [], [], []));

        $js = (string) file_get_contents(__DIR__ . '/../../modules/builder/assets/js/builder.js');
        $promote = $this->functionBody($js, 'function promote(');

        self::assertStringNotContainsString('form.submit()', $promote, 'promotion must not reload the page');
        self::assertStringContainsString('X-Requested-With', $promote);
    }

    /**
     * The library copy has to carry the section's content. Promotion used to
     * store a section that the sanitiser had already dropped for having no
     * rows, which is why the band came back empty.
     */
    public function testPromoteSectionKeepsTheRowsItCopies(): void
    {
        $app = $this->appWithBuilder();
        (new UserRepository($app->fs))->create('admin', 'secret-password', 'admin');
        $app->handle(new Request('POST', '/admin/login', [], ['login' => 'admin', 'password' => 'secret-password'], [], [], []));

        $layout = ['sections' => [[
            'id' => 's1',
            'settings' => ['role' => 'header'],
            'rows' => [['columns' => [[
                'width' => 12,
                'blocks' => [
                    ['type' => 'heading', 'settings' => ['text' => 'Логотип', 'level' => 'h2']],
                    ['type' => 'menu', 'settings' => ['source' => 'pages', 'class' => 'site-nav']],
                ],
            ]]]],
        ]]];

        $response = $app->handle(new Request('POST', '/admin/builder/global', [], [
            'action' => 'promote',
            'kind' => 'section',
            'title' => 'Шапка',
            'index' => '0',
            'layout_json' => json_encode($layout),
        ], [], [], []));

        self::assertSame(200, $response->status());
        $result = json_decode($response->body(), true);
        self::assertSame('shapka', $result['name']);

        $stored = (new GlobalLibrary($app->fs))->section('shapka');
        self::assertNotNull($stored);
        self::assertCount(1, $stored->rows(), 'promotion must keep the rows it copies');
        $blocks = $stored->rows()[0]->columns()[0]->blocks();
        self::assertCount(2, $blocks);
        self::assertSame('Логотип', $blocks[0]->get('text'));
        self::assertSame('site-nav', $blocks[1]->get('class'));

        // ...and the answer must carry them too, so the editor can expand the
        // new reference without a second round trip.
        self::assertSame('Логотип', $result['structure']['rows'][0]['columns'][0]['blocks'][0]['settings']['text'] ?? null);
    }

    /**
     * A page can be saved while a global reference has not been expanded yet
     * (a failed fetch, or a save that races the editor). An empty row list then
     * means "nothing loaded", never "the shared band is empty", so the stored
     * content has to survive.
     */
    public function testSavingAnUnexpandedReferenceKeepsTheSharedContent(): void
    {
        $app = $this->appWithBuilder();
        (new UserRepository($app->fs))->create('admin', 'secret-password', 'admin');
        $app->handle(new Request('POST', '/admin/login', [], ['login' => 'admin', 'password' => 'secret-password'], [], [], []));

        $library = new GlobalLibrary($app->fs);
        $library->save(GlobalLibrary::SECTION, 'site-header', 'Шапка сайта', [
            'section' => (new Section([
                new Row([new Column([new Block('heading', ['text' => 'Логотип', 'level' => 'h2'])], 12)]),
            ], ['role' => 'header']))->toArray(),
        ]);
        $this->write('content/about/item.yaml', "type: page
title: About
layout:
  sections:
    - settings: {global: site-header}
");

        $app->handle(new Request('POST', '/admin/builder/save', [], [
            'id' => 'about',
            'layout_json' => json_encode(['sections' => [[
                'settings' => ['global' => 'site-header', 'role' => 'header'],
                'rows' => [],
            ]]]),
        ], [], [], []));

        $stored = (new GlobalLibrary($app->fs))->section('site-header');
        self::assertNotNull($stored);
        self::assertCount(1, $stored->rows(), 'saving must not empty a global it could not expand');
        self::assertSame('Логотип', $stored->rows()[0]->columns()[0]->blocks()[0]->get('text'));
        // Settings the editor never showed must survive too: losing "role"
        // would silently turn the site header into an ordinary band.
        self::assertSame('header', $stored->role());
    }

    /**
     * The editor posts the node on its own, so its position on the canvas is
     * irrelevant. Reading it through the index used to work only for the first
     * section and for a block at 0:0:0:0.
     */
    public function testPromoteFromASingleNodeIgnoresTheCanvasPosition(): void
    {
        $app = $this->appWithBuilder();
        (new UserRepository($app->fs))->create('admin', 'secret-password', 'admin');
        $app->handle(new Request('POST', '/admin/login', [], ['login' => 'admin', 'password' => 'secret-password'], [], [], []));

        $section = [
            'settings' => ['role' => 'footer'],
            'rows' => [['columns' => [[
                'width' => 12,
                'blocks' => [['type' => 'text', 'settings' => ['body' => 'Подвал']]],
            ]]]],
        ];

        $response = $app->handle(new Request('POST', '/admin/builder/global', [], [
            'action' => 'promote',
            'kind' => 'section',
            'title' => 'Подвал',
            'index' => '3',
            'payload' => json_encode($section),
        ], [], [], []));

        self::assertSame(200, $response->status());
        $stored = (new GlobalLibrary($app->fs))->section('podval');
        self::assertNotNull($stored);
        self::assertSame('footer', $stored->role());
        self::assertSame('Подвал', $stored->rows()[0]->columns()[0]->blocks()[0]->get('body'));
    }

    public function testPromoteBlockFromASingleNodeIgnoresTheCanvasPosition(): void
    {
        $app = $this->appWithBuilder();
        (new UserRepository($app->fs))->create('admin', 'secret-password', 'admin');
        $app->handle(new Request('POST', '/admin/login', [], ['login' => 'admin', 'password' => 'secret-password'], [], [], []));

        $response = $app->handle(new Request('POST', '/admin/builder/global', [], [
            'action' => 'promote',
            'kind' => 'block',
            'title' => 'Кнопка CTA',
            'block_index' => '2:1:0:3',
            'payload' => json_encode([
                'type' => 'menu',
                'settings' => ['source' => 'pages', 'class' => 'site-nav'],
            ]),
        ], [], [], []));

        self::assertSame(200, $response->status());
        $block = (new GlobalLibrary($app->fs))->block('knopka-cta');
        self::assertNotNull($block);
        self::assertSame('menu', $block->type);
        self::assertSame('site-nav', $block->get('class'));
    }

    /**
     * Rows and columns are promoted the same way sections and blocks are: the
     * single node is posted, stored under its own kind, and answered with the
     * stored copy so the editor can expand the reference in place.
     */
    public function testPromoteRowStoresTheRowAndAnswersWithItsColumns(): void
    {
        $app = $this->appWithBuilder();
        (new UserRepository($app->fs))->create('admin', 'secret-password', 'admin');
        $app->handle(new Request('POST', '/admin/login', [], ['login' => 'admin', 'password' => 'secret-password'], [], [], []));

        $response = $app->handle(new Request('POST', '/admin/builder/global', [], [
            'action' => 'promote',
            'kind' => 'row',
            'title' => 'Полоса преимуществ',
            'payload' => json_encode([
                'settings' => ['justify' => 'center'],
                'columns' => [
                    ['width' => 6, 'blocks' => [['type' => 'heading', 'settings' => ['text' => 'Быстро']]]],
                    ['width' => 6, 'blocks' => [['type' => 'heading', 'settings' => ['text' => 'Надёжно']]]],
                ],
            ]),
        ], [], [], []));

        self::assertSame(200, $response->status());
        $result = json_decode($response->body(), true);
        self::assertSame('row', $result['kind']);
        self::assertSame('polosa-preimuschestv', $result['name']);

        $stored = (new GlobalLibrary($app->fs))->row('polosa-preimuschestv');
        self::assertNotNull($stored);
        self::assertCount(2, $stored->columns());
        self::assertSame('Быстро', $stored->columns()[0]->blocks()[0]->get('text'));
        self::assertSame(6, $stored->columns()[1]->width());

        self::assertSame('center', $result['structure']['settings']['justify'] ?? null);
        self::assertCount(2, $result['structure']['columns'] ?? []);
    }

    public function testPromoteColumnStoresTheColumnAndAnswersWithItsBlocks(): void
    {
        $app = $this->appWithBuilder();
        (new UserRepository($app->fs))->create('admin', 'secret-password', 'admin');
        $app->handle(new Request('POST', '/admin/login', [], ['login' => 'admin', 'password' => 'secret-password'], [], [], []));

        $response = $app->handle(new Request('POST', '/admin/builder/global', [], [
            'action' => 'promote',
            'kind' => 'column',
            'title' => 'Боковая колонка',
            'payload' => json_encode([
                'width' => 4,
                'settings' => ['align' => 'center'],
                'blocks' => [['type' => 'text', 'settings' => ['body' => 'Подпись']]],
            ]),
        ], [], [], []));

        self::assertSame(200, $response->status());
        $result = json_decode($response->body(), true);
        self::assertSame('column', $result['kind']);

        $stored = (new GlobalLibrary($app->fs))->column('bokovaya-kolonka');
        self::assertNotNull($stored);
        self::assertCount(1, $stored->blocks());
        self::assertSame('Подпись', $stored->blocks()[0]->get('body'));
        self::assertSame(1, count($result['structure']['blocks'] ?? []));
    }

    /**
     * A page that references a shared row renders its columns, and saving the
     * page writes the edit back to the library instead of duplicating it.
     */
    public function testSharedRowRendersAndRoundTripsThroughThePage(): void
    {
        $app = $this->appWithBuilder();
        (new UserRepository($app->fs))->create('admin', 'secret-password', 'admin');
        $app->handle(new Request('POST', '/admin/login', [], ['login' => 'admin', 'password' => 'secret-password'], [], [], []));
        $library = new GlobalLibrary($app->fs);
        $library->save(GlobalLibrary::ROW, 'features', 'Преимущества', ['row' => [
            'columns' => [
                ['width' => 6, 'blocks' => [['type' => 'heading', 'settings' => ['text' => 'Оригинал']]]],
            ],
        ]]);

        $this->write('content/rowpage/item.yaml', "type: page\ntitle: Rows\nlayout:\n  sections:\n    - rows:\n        - settings: {global: features}\n");

        $item = $app->storage->load('rowpage');
        self::assertNotNull($item);
        $html = $app->module('builder')->renderer()->render($item);
        self::assertStringContainsString('Оригинал', $html);

        // The editor posts the expanded row back, edited.
        $app->handle(new Request('POST', '/admin/builder/save', [], [
            'id' => 'rowpage',
            'layout_json' => json_encode(['sections' => [[
                'rows' => [[
                    'settings' => ['global' => 'features'],
                    'columns' => [
                        ['width' => 6, 'blocks' => [['type' => 'heading', 'settings' => ['text' => 'Изменено']]]],
                    ],
                ]],
            ]]]),
        ], [], [], []));

        $shared = (new GlobalLibrary($app->fs))->row('features');
        self::assertNotNull($shared);
        self::assertSame('Изменено', $shared->columns()[0]->blocks()[0]->get('text'));

        // ...and the page itself keeps only the reference.
        $saved = $app->storage->load('rowpage');
        $layout = $saved?->get('layout');
        $row = $layout['sections'][0]['rows'][0] ?? [];
        self::assertSame('features', $row['settings']['global'] ?? null);
        self::assertSame([], $row['columns'] ?? null, 'the page must not store a copy of the shared row');
    }

    /**
     * A shared column keeps the width of the row it sits in, so the same shared
     * column can be narrow on one page and wide on another.
     */
    public function testSharedColumnKeepsTheWidthOfItsRow(): void
    {
        $app = $this->appWithBuilder();
        (new UserRepository($app->fs))->create('admin', 'secret-password', 'admin');
        $app->handle(new Request('POST', '/admin/login', [], ['login' => 'admin', 'password' => 'secret-password'], [], [], []));
        $library = new GlobalLibrary($app->fs);
        $library->save(GlobalLibrary::COLUMN, 'aside', 'Боковая', ['column' => [
            'blocks' => [['type' => 'text', 'settings' => ['body' => 'Общий текст']]],
        ]]);

        $this->write('content/colpage/item.yaml', "type: page\ntitle: Cols\nlayout:\n  sections:\n    - rows:\n        - columns:\n            - settings: {global: aside}\n              width: 4\n");

        $item = $app->storage->load('colpage');
        $html = $app->module('builder')->renderer()->render($item);
        self::assertStringContainsString('Общий текст', $html);
        self::assertStringContainsString('--g-span:4', $html);

        $app->handle(new Request('POST', '/admin/builder/save', [], [
            'id' => 'colpage',
            'layout_json' => json_encode(['sections' => [[
                'rows' => [[
                    'columns' => [[
                        'width' => 4,
                        'settings' => ['global' => 'aside'],
                        'blocks' => [['type' => 'text', 'settings' => ['body' => 'Правка']]],
                    ]],
                ]],
            ]]]),
        ], [], [], []));

        $shared = (new GlobalLibrary($app->fs))->column('aside');
        self::assertNotNull($shared);
        self::assertSame('Правка', $shared->blocks()[0]->get('body'));

        $saved = $app->storage->load('colpage');
        $column = $saved?->get('layout')['sections'][0]['rows'][0]['columns'][0] ?? [];
        self::assertSame('aside', $column['settings']['global'] ?? null);
        self::assertSame(4, $column['width'] ?? null, 'the width belongs to the reference, not the shared column');
        self::assertSame([], $column['blocks'] ?? null);
    }

    /**
     * Adding a column to a row that already fills the grid has to shrink the
     * existing columns instead of wrapping the new one onto a second line.
     */
    public function testAddingAColumnShrinksAFullRowInsteadOfWrapping(): void
    {
        $js = (string) file_get_contents(__DIR__ . '/../../modules/builder/assets/js/builder.js');
        $fit = $this->functionBody($js, 'function fitColumns(');

        self::assertStringContainsString('budget', $fit);

        // The editor gives the newcomer a slot before fitting, so the fit sees
        // an overflow and resolves it.
        $addColumn = $this->functionBody($js, 'function addColumn(');
        self::assertStringContainsString('fitColumns(', $addColumn);
    }

    public function testColumnResizeCannotOverflowTheRow(): void
    {
        $js = (string) file_get_contents(__DIR__ . '/../../modules/builder/assets/js/builder.js');
        $resize = $this->functionBody($js, 'function resizeColumn(');

        self::assertStringContainsString('ceiling', $resize);
        self::assertStringContainsString('grid - siblings', $resize);
    }

    /**
     * Undo/redo has to cover every structural change, and the stack is capped so
     * a long session cannot grow without bound. History is recorded on render,
     * which every mutation ends with, so it cannot miss an edit.
     */
    public function testHistoryIsRecordedOnEveryRenderAndCapped(): void
    {
        $js = (string) file_get_contents(__DIR__ . '/../../modules/builder/assets/js/builder.js');

        self::assertStringContainsString('limit: 20', $js);
        self::assertStringContainsString('function commitHistory()', $js);
        self::assertStringContainsString('function undo()', $js);
        self::assertStringContainsString('function redo()', $js);

        $render = $this->functionBody($js, 'function render(');
        self::assertStringContainsString('commitHistory()', $render, 'render must record the state it produced');

        // A snapshot taken before the change would make the top of the stack the
        // pre-edit state, which is why undo appeared to do nothing.
        self::assertStringNotContainsString('checkpoint()', $js);

        // Undo restores a snapshot, so it must not record that restore as a new
        // edit or redo would be impossible.
        $restore = $this->functionBody($js, 'function restore(');
        self::assertStringContainsString('history.quiet', $restore);
    }

    /**
     * Settings are edited field by field; one snapshot per keystroke would fill
     * the stack with noise, so text fields commit once the user pauses.
     */
    public function testSettingsEditsAreRecordedWithoutOneSnapshotPerKeystroke(): void
    {
        $js = (string) file_get_contents(__DIR__ . '/../../modules/builder/assets/js/builder.js');

        $soon = $this->functionBody($js, 'function commitHistorySoon(');
        self::assertStringContainsString('setTimeout', $soon);
        self::assertStringContainsString('SELECT', $soon);

        $setting = $this->functionBody($js, 'function onSettingChange(');
        self::assertStringContainsString('commitHistorySoon(', $setting);
    }

    /**
     * The settings panel names the element instead of saying "Настройки", and
     * the delete action lives in the panel menu rather than as a button.
     */
    public function testSettingsPanelNamesTheNodeAndMovesDeleteIntoAMenu(): void
    {
        $js = (string) file_get_contents(__DIR__ . '/../../modules/builder/assets/js/builder.js');
        $template = (string) file_get_contents(__DIR__ . '/../../modules/builder/templates/admin/builder.php');

        self::assertStringNotContainsString('Настройки</h2>', $template);
        self::assertStringContainsString('builder-settings-head', $template);
        self::assertStringContainsString('builder-settings-tabs', $template);

        $head = $this->functionBody($js, 'function settingsHeadHtml(');
        self::assertStringContainsString('data-delete-node', $head);
        self::assertStringContainsString('data-duplicate-node', $head);
        self::assertStringContainsString('data-promote-node', $head);

        // The old bottom-of-form delete buttons are gone.
        self::assertStringNotContainsString('builder__settings-actions', $js);
    }

    private function functionBody(string $js, string $signature): string
    {
        $start = strpos($js, $signature);
        self::assertNotFalse($start, 'missing ' . $signature);
        $open = strpos($js, '{', $start);
        $depth = 0;
        for ($i = $open, $len = strlen($js); $i < $len; $i++) {
            if ($js[$i] === '{') {
                $depth++;
            } elseif ($js[$i] === '}') {
                $depth--;
                if ($depth === 0) {
                    return substr($js, $open, $i - $open + 1);
                }
            }
        }

        return substr($js, $open);
    }

    /**
     * A global block is edited as the block it really is: its own type and
     * settings, not a list of the reference's properties.
     */
    public function testGlobalBlockOpensAsItsRealBlockType(): void
    {
        $app = $this->appWithBuilder();
        (new UserRepository($app->fs))->create('admin', 'secret-password', 'admin');
        $app->handle(new Request('POST', '/admin/login', [], ['login' => 'admin', 'password' => 'secret-password'], [], [], []));

        $library = new GlobalLibrary($app->fs);
        $library->save(GlobalLibrary::BLOCK, 'cta', 'Кнопка CTA', [
            'block' => (new Block('button', ['text' => 'Связаться', 'url' => '/contact']))->toArray(),
        ]);
        $this->write('content/about/item.yaml', "type: page\ntitle: About\nlayout:\n  sections:\n    - rows:\n        - columns:\n            - width: 12\n              blocks:\n                - type: global\n                  settings: {name: cta}\n");

        $body = $app->handle(new Request('GET', '/admin/builder', ['id' => 'about'], [], [], [], []))->body();

        self::assertSame(1, preg_match("/data-builder='(.*?)'/s", $body, $m));
        $payload = json_decode(html_entity_decode($m[1], ENT_QUOTES), true, 512, JSON_THROW_ON_ERROR);

        $block = $payload['layout']['sections'][0]['rows'][0]['columns'][0]['blocks'][0];
        self::assertSame('button', $block['type']);
        self::assertSame('Связаться', $block['settings']['text']);
        self::assertSame('/contact', $block['settings']['url']);
        self::assertSame('cta', $block['settings']['global']);
    }

    /**
     * Saving the expanded block collapses it back into a reference, and the
     * edit lands in the library.
     */
    public function testEditingAGlobalBlockInPlaceUpdatesTheLibrary(): void
    {
        $app = $this->appWithBuilder();
        (new UserRepository($app->fs))->create('admin', 'secret-password', 'admin');
        $app->handle(new Request('POST', '/admin/login', [], ['login' => 'admin', 'password' => 'secret-password'], [], [], []));

        $library = new GlobalLibrary($app->fs);
        $library->save(GlobalLibrary::BLOCK, 'cta', 'Кнопка CTA', [
            'block' => (new Block('button', ['text' => 'СТАРАЯ', 'url' => '/old']))->toArray(),
        ]);
        $this->write('content/about/item.yaml', "type: page\ntitle: About\nlayout:\n  sections:\n    - rows:\n        - columns:\n            - width: 12\n              blocks:\n                - type: global\n                  settings: {name: cta}\n");

        $posted = ['sections' => [[
            'rows' => [['columns' => [[
                'width' => 12,
                'blocks' => [['type' => 'button', 'settings' => ['text' => 'НОВАЯ', 'url' => '/new', 'global' => 'cta']]],
            ]]]],
        ]]];

        $app->handle(new Request('POST', '/admin/builder/save', [], [
            'id' => 'about',
            'layout_json' => json_encode($posted),
        ], [], [], []));

        $shared = (new GlobalLibrary($app->fs))->block('cta');
        self::assertNotNull($shared);
        self::assertSame('button', $shared->type);
        self::assertSame('НОВАЯ', $shared->get('text'));

        $page = $app->storage->load('about');
        self::assertNotNull($page);
        $saved = $page->get('layout');
        $block = $saved['sections'][0]['rows'][0]['columns'][0]['blocks'][0];
        self::assertSame('global', $block['type']);
        self::assertSame('cta', $block['settings']['name']);
    }
}
