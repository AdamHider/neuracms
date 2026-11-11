<?php

declare(strict_types=1);

namespace FlatCms\Tests\Unit;

use FlatCms\Admin\UserRepository;
use FlatCms\Content\Regions;
use FlatCms\Core\Request;
use FlatCms\Modules\Builder\Block;
use FlatCms\Modules\Builder\BuilderModule;
use FlatCms\Tests\FlatCmsTestCase;

/**
 * Header/footer as editable theme regions, the Menu particle that drives them,
 * and the documentation viewer in the admin panel.
 */
final class RegionsTest extends FlatCmsTestCase
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

    private function login(\FlatCms\App $app): void
    {
        (new UserRepository($app->fs))->create('admin', 'secret-password', 'admin');
        $app->handle(new Request('POST', '/admin/login', [], ['login' => 'admin', 'password' => 'secret-password'], [], [], []));
    }

    public function testBuiltinRegionsAreAvailable(): void
    {
        $regions = new Regions($this->app()->fs);

        self::assertArrayHasKey('header', $regions->all());
        self::assertArrayHasKey('footer', $regions->all());
        self::assertTrue($regions->has('header'));
        self::assertFalse($regions->has('../evil'));
    }

    public function testRegionLayoutRoundTripsThroughDisk(): void
    {
        $regions = new Regions($this->app()->fs);
        self::assertSame([], $regions->layout('header'));

        $layout = ['sections' => [['columns' => [['width' => 12, 'blocks' => [
            ['type' => 'heading', 'settings' => ['text' => 'Привет']],
        ]]]]]];
        self::assertTrue($regions->save('header', $layout));
        self::assertSame($layout, $regions->layout('header'));
    }

    public function testMenuBlockRendersMenuItemsWithNesting(): void
    {
        $app = $this->appWithBuilder();
        $renderer = $this->builder($app)->renderer();
        self::assertNotNull($renderer);

        $this->write('config/schemas/menu-item.yaml', "type: menu-item\nkind: element\nfields:\n  link: { type: string }\n  parent: { type: string }\n  order: { type: int }\nindexed: [link]\n");
        $this->write('content/menu/home/item.yaml', "type: menu-item\ntitle: Главная\nlink: /\norder: 0\n");
        $this->write('content/menu/services/item.yaml', "type: menu-item\ntitle: Услуги\nlink: /services\norder: 1\n");
        $this->write('content/menu/sites/item.yaml', "type: menu-item\ntitle: Сайты\nlink: /services/sites\nparent: services\norder: 0\n");
        $app->build();

        $html = $renderer->renderBlock(new Block('menu', ['source' => 'menu', 'depth' => 2, 'class' => 'nav']));

        self::assertStringContainsString('class="nav"', $html);
        self::assertStringContainsString('href="/"', $html);
        self::assertStringContainsString('>Главная<', $html);
        self::assertStringContainsString('nav--sub', $html);
        self::assertStringContainsString('>Сайты<', $html);
    }

    public function testMenuBlockCanRenderPagesAndCustomLinks(): void
    {
        $app = $this->appWithBuilder();
        $renderer = $this->builder($app)->renderer();
        self::assertNotNull($renderer);

        $pages = $renderer->renderBlock(new Block('menu', ['source' => 'pages']));
        self::assertStringContainsString('>Home<', $pages);
        self::assertStringContainsString('>About<', $pages);

        $custom = $renderer->renderBlock(new Block('menu', ['source' => 'custom', 'items' => "Внешний | https://example.com"]));
        self::assertStringContainsString('href="https://example.com"', $custom);
        self::assertStringContainsString('>Внешний<', $custom);
    }

    public function testRegionEditorRendersAndSavesThroughHttp(): void
    {
        $app = $this->appWithBuilder(true);
        $this->login($app);

        $editor = $app->handle(new Request('GET', '/admin/builder', ['region' => 'header'], [], [], [], []));
        self::assertSame(200, $editor->status());
        self::assertStringContainsString('Область сайта', $editor->body());

        $json = json_encode(['sections' => [['columns' => [['width' => 12, 'blocks' => [
            ['type' => 'heading', 'settings' => ['text' => 'Шапка']],
        ]]]]]], JSON_UNESCAPED_UNICODE);
        $save = $app->handle(new Request('POST', '/admin/builder/save', [], ['region' => 'header', 'layout_json' => $json], [], [], []));

        self::assertSame(303, $save->status());
        self::assertStringContainsString('region=header', (string) $save->headers()['Location']);
        self::assertFileExists($this->root . '/config/regions/header.yaml');

        $page = $app->handle(new Request('GET', '/', [], [], [], [], []));
        self::assertStringContainsString('Шапка', $page->body());
    }

    public function testRegionEditorRejectsUnknownRegion(): void
    {
        $app = $this->appWithBuilder(true);
        $this->login($app);

        $response = $app->handle(new Request('GET', '/admin/builder', ['region' => 'nope'], [], [], [], []));

        self::assertSame(404, $response->status());
    }

    public function testRegionsPageListsRegions(): void
    {
        $app = $this->appWithBuilder(true);
        $this->login($app);

        $body = $app->handle(new Request('GET', '/admin/regions', [], [], [], [], []))->body();

        self::assertStringContainsString('Области сайта', $body);
        self::assertStringContainsString('Шапка сайта', $body);
        self::assertStringContainsString('region=header', $body);
    }

    public function testRegionsPageRequiresLogin(): void
    {
        $app = $this->appWithBuilder(true);

        $response = $app->handle(new Request('GET', '/admin/regions', [], [], [], [], []));

        self::assertSame(302, $response->status());
    }

    public function testDocsPageRendersProjectDocumentation(): void
    {
        $app = $this->appWithBuilder(true);
        $this->login($app);
        $this->copyFromProject('docs');

        $list = $app->handle(new Request('GET', '/admin/docs', [], [], [], [], []));
        self::assertSame(200, $list->status());
        self::assertStringContainsString('Документация', $list->body());

        $doc = $app->handle(new Request('GET', '/admin/docs', ['page' => 'architecture'], [], [], [], []));
        self::assertSame(200, $doc->status());
        self::assertStringContainsString('docs__prose', $doc->body());
        // Markdown must be rendered, not dumped raw.
        self::assertStringContainsString('<h1', $doc->body());
    }

    public function testDocsPageIgnoresUnknownDoc(): void
    {
        $app = $this->appWithBuilder(true);
        $this->login($app);
        $this->copyFromProject('docs');

        $body = $app->handle(new Request('GET', '/admin/docs', ['page' => '../../etc/passwd'], [], [], [], []))->body();

        self::assertStringContainsString('Выберите раздел слева', $body);
    }

    private function builder(\FlatCms\App $app): BuilderModule
    {
        $module = $app->module('builder');
        self::assertInstanceOf(BuilderModule::class, $module);

        return $module;
    }
}
