<?php

declare(strict_types=1);

namespace FlatCms\Tests\Unit;

use FlatCms\Admin\UserRepository;
use FlatCms\Content\Item;
use FlatCms\Content\Schema;
use FlatCms\Content\TypeRepository;
use FlatCms\Core\Request;
use FlatCms\Support\Fs;
use FlatCms\Tests\FlatCmsTestCase;

final class TypeTest extends FlatCmsTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->copyFromProject('src/Admin');
        $this->copyFromProject('themes/default');
        $this->copyFromProject('config/schemas');
    }

    private function appWithAdmin(): \FlatCms\App
    {
        $this->write('config/site.yaml', "site:\n  title: Test\n  theme: default\nadmin:\n  enabled: true\n");

        return $this->app();
    }

    private function login(\FlatCms\App $app): void
    {
        (new UserRepository($app->fs))->create('admin', 'secret-password', 'admin');
        $app->handle(new Request('POST', '/admin/login', [], ['login' => 'admin', 'password' => 'secret-password'], [], [], []));
    }

    public function testSchemaReadsLabelBuiltinAndRelations(): void
    {
        $schema = new Schema(new Fs($this->root));

        self::assertSame('Товар', $schema->label('product'));
        self::assertFalse($schema->isBuiltin('product'));
        self::assertTrue($schema->isBuiltin('page'));

        $relations = $schema->relations('product');
        self::assertArrayHasKey('category', $relations);
        self::assertSame('category', $relations['category']['to']);
        self::assertFalse($relations['category']['multiple']);
        self::assertSame('product', $relations['related']['to']);
        self::assertTrue($relations['related']['multiple']);
    }

    public function testSchemaSeparatesPagesFromElements(): void
    {
        $schema = new Schema(new Fs($this->root));

        // Core page-like types are pages; custom data types are elements.
        self::assertSame(Schema::KIND_PAGE, $schema->kind('page'));
        self::assertSame(Schema::KIND_PAGE, $schema->kind('section'));
        // Menu items are data the Menu particle renders, not pages of their own.
        self::assertSame(Schema::KIND_ELEMENT, $schema->kind('menu-item'));
        self::assertSame(Schema::KIND_ELEMENT, $schema->kind('product'));
        self::assertTrue($schema->isPage('page'));
        self::assertFalse($schema->isPage('product'));
        self::assertTrue($schema->isElement('product'));

        self::assertArrayHasKey('page', $schema->pages());
        self::assertArrayNotHasKey('product', $schema->pages());
        self::assertArrayHasKey('product', $schema->elements());
        self::assertArrayNotHasKey('page', $schema->elements());
    }

    public function testSchemaHonoursExplicitKind(): void
    {
        $this->write('config/schemas/landing.yaml', "type: landing\nkind: page\nlabel: Лендинг\n");
        $this->write('config/schemas/note.yaml', "type: note\nkind: element\nlabel: Заметка\n");
        $schema = new Schema(new Fs($this->root));

        self::assertTrue($schema->isPage('landing'));
        self::assertTrue($schema->isElement('note'));
    }

    public function testQueryFiltersByKind(): void
    {
        $this->write('content/services/site/item.yaml', "type: service\ntitle: Сайт\n");
        $app = $this->app();

        $pages = $app->query()->kind(Schema::KIND_PAGE)->withUnpublished()->find();
        $elements = $app->query()->kind(Schema::KIND_ELEMENT)->withUnpublished()->find();

        $pageIds = array_map(static fn ($i) => $i->id(), $pages);
        $elementIds = array_map(static fn ($i) => $i->id(), $elements);

        self::assertContains('index', $pageIds);
        self::assertContains('products/teapot', $elementIds);
        self::assertNotContains('products/teapot', $pageIds);
        self::assertNotContains('index', $elementIds);
    }

    public function testSchemaReferencedByListsDependents(): void
    {
        $schema = new Schema(new Fs($this->root));

        self::assertContains('product', $schema->referencedBy('category'));
    }

    public function testIndexStoresRelationFields(): void
    {
        $this->write('content/categories/tea/item.yaml', "type: category\ntitle: Чай\n");
        $this->write('content/products/teapot/item.yaml', "type: product\ntitle: Teapot\nprice: 1200\ncategory: categories/tea\n");

        $app = $this->app();

        $entry = $app->index->get('products/teapot');
        self::assertSame('categories/tea', $entry['indexed']['category']);

        $found = $app->query()->type('product')->where('category', '=', 'categories/tea')->find();
        self::assertCount(1, $found);
        self::assertSame('products/teapot', $found[0]->id());
    }

    public function testTypeRepositoryCreatesAndLoadsType(): void
    {
        $app = $this->app();
        $repository = new TypeRepository($app->fs, $app->schema);

        self::assertTrue($repository->save('news', [
            'headline' => ['type' => 'string', 'required' => true, 'label' => 'Заголовок'],
            'topic' => ['type' => 'relation', 'to' => 'category', 'multiple' => false],
        ], ['headline'], 'Новость'));

        self::assertTrue($repository->exists('news'));
        $loaded = $repository->load('news');
        self::assertSame('Новость', $loaded['label']);
        self::assertSame('relation', $loaded['fields']['topic']['type']);
        self::assertSame(['headline'], $loaded['indexed']);
    }

    public function testTypeRepositoryRejectsBadNames(): void
    {
        $app = $this->app();
        $repository = new TypeRepository($app->fs, $app->schema);

        self::assertNull($repository->normalizeName('Bad Name'));
        self::assertNull($repository->normalizeName('../evil'));
        self::assertFalse($repository->save('Bad Name', [], []));
        self::assertSame('product-2', $repository->normalizeName('Product-2'));
    }

    public function testBuiltinTypeCannotBeDeleted(): void
    {
        $app = $this->app();
        $repository = new TypeRepository($app->fs, $app->schema);

        self::assertTrue($repository->isBuiltin('page'));
        self::assertFalse($repository->delete('page'));
        self::assertTrue($repository->exists('page'));
    }

    public function testParseRowsBuildsFieldsAndFlagsErrors(): void
    {
        $app = $this->app();
        $repository = new TypeRepository($app->fs, $app->schema);

        $parsed = $repository->parseRows([
            ['name' => 'price', 'type' => 'float', 'label' => 'Цена', 'required' => '1', 'indexed' => '1'],
            ['name' => 'tags', 'type' => 'list', 'label' => 'Теги'],
            ['name' => 'category', 'type' => 'relation', 'to' => 'category', 'multiple' => '1'],
            ['name' => 'broken', 'type' => 'nonsense'],
        ]);

        self::assertArrayHasKey('price', $parsed['fields']);
        self::assertTrue($parsed['fields']['price']['required']);
        self::assertContains('price', $parsed['indexed']);
        self::assertTrue($parsed['fields']['category']['multiple']);
        self::assertNotEmpty($parsed['errors']);
        self::assertArrayNotHasKey('broken', $parsed['fields']);
    }

    public function testTypeValidationRejectsMissingRelationTarget(): void
    {
        $app = $this->app();

        $item = new Item('products/x', ['type' => 'product', 'title' => 'X', 'price' => 10, 'category' => 'categories/none']);
        $errors = $app->schema->validate($item);

        self::assertNotEmpty($errors);
        self::assertStringContainsString('category', $errors[0]);
    }

    public function testTypeValidationRejectsWrongRelationType(): void
    {
        $app = $this->app();

        $item = new Item('products/x', ['type' => 'product', 'title' => 'X', 'price' => 10, 'category' => 'index']);
        $errors = $app->schema->validate($item);

        self::assertNotEmpty($errors);
        self::assertStringContainsString('category', $errors[0]);
    }

    public function testTypeValidationAcceptsValidRelation(): void
    {
        $this->write('content/categories/tea/item.yaml', "type: category\ntitle: Чай\n");
        $app = $this->app();

        $item = new Item('products/x', ['type' => 'product', 'title' => 'X', 'price' => 10, 'category' => 'categories/tea']);

        self::assertSame([], $app->schema->validate($item));
    }

    public function testAdminTypesListRequiresLogin(): void
    {
        $app = $this->appWithAdmin();

        $response = $app->handle(new Request('GET', '/admin/types', [], [], [], [], []));

        self::assertSame(302, $response->status());
        self::assertSame('/admin/login', $response->headers()['Location']);
    }

    public function testAdminTypesListShowsTypes(): void
    {
        $app = $this->appWithAdmin();
        $this->login($app);

        $response = $app->handle(new Request('GET', '/admin/types', [], [], [], [], []));

        self::assertSame(200, $response->status());
        self::assertStringContainsString('product', $response->body());
        self::assertStringContainsString('Типы контента', $response->body());
        self::assertStringContainsString('встроенный', $response->body());
    }

    public function testAdminCreatesTypeThroughHttp(): void
    {
        $app = $this->appWithAdmin();
        $this->login($app);

        $response = $app->handle(new Request('POST', '/admin/types/new', [], [
            'name' => 'news',
            'label' => 'Новость',
            'fields' => [
                ['name' => 'headline', 'type' => 'string', 'label' => 'Заголовок', 'required' => '1'],
                ['name' => 'topic', 'type' => 'relation', 'to' => 'category'],
            ],
        ], [], [], []));

        self::assertSame(303, $response->status());
        self::assertFileExists($this->root . '/config/schemas/news.yaml');

        $yaml = (string) file_get_contents($this->root . '/config/schemas/news.yaml');
        self::assertStringContainsString('headline', $yaml);
        self::assertStringContainsString('relation', $yaml);
        self::assertStringContainsString('category', $yaml);
    }

    public function testAdminTypeCreateRejectsDuplicate(): void
    {
        $app = $this->appWithAdmin();
        $this->login($app);

        $response = $app->handle(new Request('POST', '/admin/types/new', [], [
            'name' => 'product',
            'label' => 'Дубликат',
        ], [], [], []));

        self::assertSame(422, $response->status());
    }

    public function testAdminCannotDeleteBuiltinType(): void
    {
        $app = $this->appWithAdmin();
        $this->login($app);

        $response = $app->handle(new Request('POST', '/admin/types/delete', [], ['name' => 'page'], [], [], []));

        self::assertSame(303, $response->status());
        self::assertStringContainsString('error=', (string) $response->headers()['Location']);
        self::assertFileExists($this->root . '/config/schemas/page.yaml');
    }

    public function testAdminDeletesCustomType(): void
    {
        $app = $this->appWithAdmin();
        $this->login($app);
        $app->handle(new Request('POST', '/admin/types/new', [], ['name' => 'news', 'label' => 'Новость'], [], [], []));

        $response = $app->handle(new Request('POST', '/admin/types/delete', [], ['name' => 'news'], [], [], []));

        self::assertSame(303, $response->status());
        self::assertFileDoesNotExist($this->root . '/config/schemas/news.yaml');
    }

    public function testNewContentFormRenders(): void
    {
        $app = $this->appWithAdmin();
        $this->login($app);

        $response = $app->handle(new Request('GET', '/admin/content/new', [], [], [], [], []));
        self::assertSame(200, $response->status());
        self::assertStringContainsString('Новая страница', $response->body());

        $typed = $app->handle(new Request('GET', '/admin/content/new', ['type' => 'product'], [], [], [], [], []));
        self::assertSame(200, $typed->status());
        self::assertStringContainsString('Новый элемент', $typed->body());
        self::assertStringContainsString('fields[price]', $typed->body());
    }

    public function testContentEditorRendersTypedFields(): void
    {
        $app = $this->appWithAdmin();
        $this->login($app);

        $response = $app->handle(new Request('GET', '/admin/content/edit', ['id' => 'products/teapot'], [], [], [], [], []));

        self::assertSame(200, $response->status());
        self::assertStringContainsString('fields[price]', $response->body());
        self::assertStringContainsString('fields[category]', $response->body());
        self::assertStringContainsString('Категория', $response->body());
    }

    public function testContentSaveCastsTypedValues(): void
    {
        $app = $this->appWithAdmin();
        $this->login($app);
        $this->write('content/categories/tea/item.yaml', "type: category\ntitle: Чай\n");
        $app->build();

        $response = $app->handle(new Request('POST', '/admin/content/edit', [], [
            'id' => 'products/teapot',
            'type' => 'product',
            'title' => 'Чайник',
            'published' => '1',
            'fields' => [
                'price' => '1500',
                'stock' => '3',
                'category' => 'categories/tea',
                'tags' => 'tea, kitchen',
            ],
        ], [], [], []));

        self::assertSame(303, $response->status());

        $item = $app->storage->load('products/teapot');
        self::assertSame(1500.0, (float) $item->get('price'));
        self::assertSame(3, $item->get('stock'));
        self::assertSame('categories/tea', $item->get('category'));
        self::assertSame(['tea', 'kitchen'], $item->get('tags'));
    }

    public function testContentSaveRejectsDanglingRelation(): void
    {
        $app = $this->appWithAdmin();
        $this->login($app);

        $response = $app->handle(new Request('POST', '/admin/content/edit', [], [
            'id' => 'products/teapot',
            'type' => 'product',
            'title' => 'Чайник',
            'fields' => ['price' => '1500', 'category' => 'categories/missing'],
        ], [], [], []));

        self::assertSame(422, $response->status());
    }

    public function testSchemaReadsAdminMenuSettings(): void
    {
        $this->write('config/schemas/article.yaml', "type: article\nkind: element\nlabel: Статья\nadmin:\n  menu: true\n  label: Статьи\n  icon: docs\n  order: 5\nfields:\n  headline: { type: string }\n");

        $schema = new Schema(new Fs($this->root));

        $admin = $schema->get('article')['admin'];
        self::assertTrue($admin['menu']);
        self::assertSame('Статьи', $admin['label']);
        self::assertSame('docs', $admin['icon']);
        self::assertSame(5, $admin['order']);

        // A type without the block defaults to no menu entry.
        self::assertFalse($schema->get('product')['admin']['menu']);

        $menu = $schema->adminMenu();
        self::assertCount(1, $menu);
        self::assertSame('article', $menu[0]['type']);
        self::assertSame('Статьи', $menu[0]['label']);
        self::assertSame('docs', $menu[0]['icon']);
    }

    public function testTypeRepositoryPersistsAdminMenuSettings(): void
    {
        $app = $this->app();
        $repository = new TypeRepository($app->fs, $app->schema);

        self::assertTrue($repository->save('article', ['headline' => ['type' => 'string']], [], 'Статья', 'element', [
            'menu' => true,
            'label' => 'Статьи',
            'icon' => 'docs',
            'order' => 5,
        ]));

        $yaml = (string) file_get_contents($this->root . '/config/schemas/article.yaml');
        self::assertStringContainsString('menu: true', $yaml);
        self::assertStringContainsString('icon: docs', $yaml);

        $loaded = $repository->load('article');
        self::assertTrue($loaded['admin']['menu']);
        self::assertSame('Статьи', $loaded['admin']['label']);

        // Turning the entry off drops the "admin" block again.
        self::assertTrue($repository->save('article', ['headline' => ['type' => 'string']], [], 'Статья', 'element', ['menu' => false]));
        self::assertStringNotContainsString('admin:', (string) file_get_contents($this->root . '/config/schemas/article.yaml'));
    }

    public function testTypeWithAdminMenuGetsItsOwnSidebarEntry(): void
    {
        $app = $this->appWithAdmin();
        $this->login($app);

        $app->handle(new Request('POST', '/admin/types/new', [], [
            'name' => 'article',
            'label' => 'Статья',
            'kind' => 'element',
            'admin_menu' => '1',
            'admin_label' => 'Статьи',
            'admin_icon' => 'docs',
            'admin_order' => '5',
        ], [], [], []));

        $this->write('content/articles/first/item.yaml', "type: article\ntitle: Первая\n");
        $app->build();

        $dashboard = $app->handle(new Request('GET', '/admin', [], [], [], [], []))->body();
        self::assertStringContainsString('/admin/elements?type=article', $dashboard);
        self::assertStringContainsString('Статьи', $dashboard);

        // The entry opens the element list filtered by the type and highlights it.
        $list = $app->handle(new Request('GET', '/admin/elements', ['type' => 'article'], [], [], [], []));
        self::assertSame(200, $list->status());
        self::assertStringContainsString('is-active', $list->body());
        self::assertStringContainsString('Первая', $list->body());
    }
}
