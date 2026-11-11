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
        foreach (['heading', 'text', 'image', 'button', 'columns', 'products', 'html'] as $name) {
            self::assertTrue($types->has($name), "Missing block type: {$name}");
        }
    }

    public function testLayoutParsesBlocksAndGrid(): void
    {
        $layout = Layout::fromValue([
            'grid' => [[6, 6]],
            'blocks' => [
                ['type' => 'heading', 'settings' => ['text' => 'Hi']],
                ['type' => 'text', 'settings' => ['body' => 'Body']],
            ],
        ]);

        self::assertCount(2, $layout->blocks());
        self::assertTrue($layout->hasGrid());
        self::assertSame([6, 6], $layout->rowFor(0));
        self::assertSame([6, 6], $layout->rowFor(1));
        self::assertNull($layout->rowFor(5));
    }

    public function testLayoutAcceptsAPlainBlockList(): void
    {
        $layout = Layout::fromValue([['type' => 'heading', 'settings' => ['text' => 'Only']]]);

        self::assertCount(1, $layout->blocks());
        self::assertFalse($layout->hasGrid());
        self::assertSame('heading', $layout->blocks()[0]->type);
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

    public function testGridRendersColumnsWithSpans(): void
    {
        $app = $this->appWithBuilder();
        $renderer = $this->builder($app)->renderer();
        self::assertNotNull($renderer);

        $layout = Layout::fromValue([
            'grid' => [[6, 6], [4, 8]],
            'blocks' => [
                ['type' => 'text', 'settings' => ['body' => 'A']],
                ['type' => 'text', 'settings' => ['body' => 'B']],
                ['type' => 'text', 'settings' => ['body' => 'C']],
                ['type' => 'text', 'settings' => ['body' => 'D']],
            ],
        ]);

        $html = $renderer->renderLayout($layout);

        self::assertSame(2, substr_count($html, '<div class="grid">'));
        self::assertStringContainsString('--span:6', $html);
        self::assertStringContainsString('--span:4', $html);
        self::assertStringContainsString('--span:8', $html);
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
}
