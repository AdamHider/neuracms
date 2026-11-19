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

    public function testGlobalsPageListsPromotedItems(): void
    {
        $app = $this->appWithBuilder();
        (new UserRepository($app->fs))->create('admin', 'secret-password', 'admin');
        $app->handle(new Request('POST', '/admin/login', [], ['login' => 'admin', 'password' => 'secret-password'], [], [], []));

        $library = new GlobalLibrary($app->fs);
        $library->save(GlobalLibrary::SECTION, 'site-header', 'Шапка сайта', [
            'section' => ['settings' => ['role' => 'header'], 'columns' => [['width' => 12, 'blocks' => []]]],
        ]);

        $response = $app->handle(new Request('GET', '/admin/globals', [], [], [], [], []));

        self::assertSame(200, $response->status());
        self::assertStringContainsString('Шапка сайта', $response->body());
        self::assertStringContainsString('site-header', $response->body());
    }

    public function testDeleteGlobalRemovesItFromTheLibrary(): void
    {
        $app = $this->appWithBuilder();
        (new UserRepository($app->fs))->create('admin', 'secret-password', 'admin');
        $app->handle(new Request('POST', '/admin/login', [], ['login' => 'admin', 'password' => 'secret-password'], [], [], []));

        $library = new GlobalLibrary($app->fs);
        $library->save(GlobalLibrary::SECTION, 'temp', 'Временная', [
            'section' => ['settings' => [], 'columns' => [['width' => 12, 'blocks' => []]]],
        ]);
        self::assertTrue($library->exists(GlobalLibrary::SECTION, 'temp'));

        $app->handle(new Request('POST', '/admin/builder/global', [], [
            'action' => 'delete',
            'global' => 'section:temp',
        ], [], [], []));

        self::assertFalse($library->exists(GlobalLibrary::SECTION, 'temp'));
    }
}
