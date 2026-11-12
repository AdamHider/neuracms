<?php

declare(strict_types=1);

namespace FlatCms\Tests\Unit;

use FlatCms\Admin\Auth;
use FlatCms\Admin\ContentWriter;
use FlatCms\Admin\UserRepository;
use FlatCms\Core\Request;
use FlatCms\Support\Fs;
use FlatCms\Tests\FakeSession;
use FlatCms\Tests\FlatCmsTestCase;

final class AdminTest extends FlatCmsTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->copyFromProject('src/Admin');
        $this->copyFromProject('themes/default');
    }

    private function appWithAdmin(): \FlatCms\App
    {
        $this->write('config/site.yaml', "site:\n  title: Test\n  theme: default\nadmin:\n  enabled: true\n");

        return $this->app();
    }

    public function testAdminIsCoreAndNeedsNoModuleEntry(): void
    {
        $app = $this->appWithAdmin();

        self::assertSame([], array_filter(array_keys($app->modules()), static fn (string $name): bool => $name === 'admin'));
        self::assertSame(302, $app->handle(new Request('GET', '/admin', [], [], [], [], []))->status());
        self::assertSame(200, $app->handle(new Request('GET', '/admin/login', [], [], [], [], []))->status());
    }

    public function testAdminCanBeDisabled(): void
    {
        $this->write('config/site.yaml', "site:\n  title: Test\n  theme: default\nadmin:\n  enabled: false\n");
        $app = $this->app();

        self::assertSame(404, $app->handle(new Request('GET', '/admin', [], [], [], [], []))->status());
        self::assertSame(404, $app->handle(new Request('GET', '/admin/login', [], [], [], [], []))->status());
    }

    public function testAdminCssIsServedFromCore(): void
    {
        $app = $this->appWithAdmin();

        $response = $app->handle(new Request('GET', '/assets/admin/css/admin.css', [], [], [], [], []));

        self::assertSame(200, $response->status());
        self::assertStringContainsString('text/css', $response->headers()['Content-Type']);
    }

    public function testUserRepositoryHashesPasswordAndVerifies(): void
    {
        $users = new UserRepository(new Fs($this->root));

        self::assertTrue($users->create('Admin', 'secret-password', 'admin'));
        $user = $users->find('admin');
        self::assertNotNull($user);
        self::assertNotSame('secret-password', $user['password']);
        self::assertNotNull($users->verify('admin', 'secret-password'));
        self::assertNull($users->verify('admin', 'wrong'));
    }

    public function testUserRepositoryRejectsDuplicateAndBadLogin(): void
    {
        $users = new UserRepository(new Fs($this->root));

        self::assertTrue($users->create('editor', 'secret-password'));
        self::assertFalse($users->create('editor', 'other-password'));
        self::assertFalse($users->create('a b', 'secret-password'));
    }

    public function testAuthStoresLoginAndRoleInSession(): void
    {
        $session = new FakeSession();
        $users = new UserRepository(new Fs($this->root));
        $users->create('admin', 'secret-password', 'admin');
        $auth = new Auth($users, $session);

        self::assertFalse($auth->check());
        self::assertTrue($auth->attempt('admin', 'secret-password'));
        self::assertTrue($auth->isAdmin());
        self::assertSame('admin', $auth->user()['login'] ?? null);

        $auth->logout();
        self::assertFalse($auth->check());
    }

    public function testAuthRejectsWrongPassword(): void
    {
        $users = new UserRepository(new Fs($this->root));
        $users->create('admin', 'secret-password', 'admin');
        $auth = new Auth($users, new FakeSession());

        self::assertFalse($auth->attempt('admin', 'nope'));
        self::assertFalse($auth->check());
    }

    public function testContentWriterSavesAndSnapshotsHistory(): void
    {
        $writer = new ContentWriter(new Fs($this->root));
        self::assertTrue($writer->save('blog/post', ['type' => 'page', 'title' => 'First'], 'Body one'));
        self::assertTrue($writer->save('blog/post', ['type' => 'page', 'title' => 'Second'], 'Body two'));

        $item = $writer->load('blog/post');
        self::assertNotNull($item);
        self::assertSame('Second', $item->title());
        self::assertSame('Body two', $item->body());
        self::assertNotEmpty($writer->history('blog/post'));
    }

    public function testContentWriterRefusesPathTraversal(): void
    {
        $writer = new ContentWriter(new Fs($this->root));

        self::assertFalse($writer->save('../escape', ['title' => 'x'], ''));
        self::assertFalse($writer->delete('../escape'));
        self::assertFalse($writer->move('../../etc', 'blog/x'));
    }

    public function testContentWriterMovesItem(): void
    {
        $writer = new ContentWriter(new Fs($this->root));
        $writer->save('blog/old', ['type' => 'page', 'title' => 'Post'], '');

        self::assertTrue($writer->move('blog/old', 'blog/new'));
        self::assertNull($writer->load('blog/old'));
        self::assertNotNull($writer->load('blog/new'));
    }

    public function testAdminRequiresLogin(): void
    {
        $app = $this->appWithAdmin();

        $response = $app->handle(new Request('GET', '/admin', [], [], [], [], []));

        self::assertSame(302, $response->status());
        self::assertSame('/admin/login', $response->headers()['Location']);
    }

    public function testAdminLoginThenDashboard(): void
    {
        $app = $this->appWithAdmin();
        (new UserRepository($app->fs))->create('admin', 'secret-password', 'admin');

        $login = $app->handle(new Request('POST', '/admin/login', [], ['login' => 'admin', 'password' => 'secret-password'], [], [], []));
        self::assertSame(302, $login->status());

        $dashboard = $app->handle(new Request('GET', '/admin', [], [], [], [], []));
        self::assertSame(200, $dashboard->status());
        self::assertStringContainsString('Панель', $dashboard->body());
    }

    public function testEditorCannotOpenUsersPage(): void
    {
        $app = $this->appWithAdmin();
        $users = new UserRepository($app->fs);
        $users->create('editor', 'secret-password', 'editor');
        $app->handle(new Request('POST', '/admin/login', [], ['login' => 'editor', 'password' => 'secret-password'], [], [], []));

        $response = $app->handle(new Request('GET', '/admin/users', [], [], [], [], []));

        self::assertSame(302, $response->status());
        self::assertSame('/admin', $response->headers()['Location']);
    }

    public function testContentSaveThroughHttpWritesFiles(): void
    {
        $app = $this->appWithAdmin();
        (new UserRepository($app->fs))->create('admin', 'secret-password', 'admin');
        $app->handle(new Request('POST', '/admin/login', [], ['login' => 'admin', 'password' => 'secret-password'], [], [], []));

        $post = [
            'id' => 'blog/created',
            'type' => 'product',
            'title' => 'Created',
            'order' => '3',
            'published' => '1',
            'body' => '# Hi',
            'fields' => ['price' => '42.5'],
        ];
        $response = $app->handle(new Request('POST', '/admin/content/new', [], $post, [], [], []));

        self::assertSame(303, $response->status());
        self::assertFileExists($this->root . '/content/blog/created/item.yaml');
        self::assertStringContainsString('price: 42.5', (string) file_get_contents($this->root . '/content/blog/created/item.yaml'));
    }

    public function testContentSavePreservesPropertiesMissingFromTheType(): void
    {
        $app = $this->appWithAdmin();
        (new UserRepository($app->fs))->create('admin', 'secret-password', 'admin');
        $app->handle(new Request('POST', '/admin/login', [], ['login' => 'admin', 'password' => 'secret-password'], [], [], []));

        // A custom property the type does not describe must survive a save, so
        // menu filters that rely on it keep working.
        $this->write('content/about/item.yaml', "type: page\ntitle: About\nshow_in_menu: true\norder: 10\n");

        $post = [
            'id' => 'about',
            'type' => 'page',
            'title' => 'About',
            'order' => '10',
            'published' => '1',
            'fields' => [],
        ];
        $response = $app->handle(new Request('POST', '/admin/content/edit', [], $post, [], [], []));

        self::assertSame(303, $response->status());
        self::assertStringContainsString('show_in_menu: true', (string) file_get_contents($this->root . '/content/about/item.yaml'));
    }

    public function testContentValidationFailureReturns422(): void
    {
        $app = $this->appWithAdmin();
        (new UserRepository($app->fs))->create('admin', 'secret-password', 'admin');
        $app->handle(new Request('POST', '/admin/login', [], ['login' => 'admin', 'password' => 'secret-password'], [], [], []));

        $response = $app->handle(new Request('POST', '/admin/content/new', [], ['id' => 'blog/bad', 'type' => 'page', 'title' => ''], [], [], []));

        self::assertSame(422, $response->status());
        self::assertFileDoesNotExist($this->root . '/content/blog/bad/item.yaml');
    }

    public function testNewContentFormKeepsTheRequestedType(): void
    {
        $app = $this->appWithAdmin();
        (new UserRepository($app->fs))->create('admin', 'secret-password', 'admin');
        $app->handle(new Request('POST', '/admin/login', [], ['login' => 'admin', 'password' => 'secret-password'], [], [], []));

        // A product (an element type) opens the product form, not a generic one.
        $product = $app->handle(new Request('GET', '/admin/content/new', ['kind' => 'element', 'type' => 'product'], [], [], [], [], []))->body();
        self::assertStringContainsString('name="type" value="product"', $product);
        self::assertStringContainsString('name="fields[price]"', $product);
    }

    public function testPagesListShowsOnlyPages(): void
    {
        $app = $this->appWithAdmin();
        (new UserRepository($app->fs))->create('admin', 'secret-password', 'admin');
        $app->handle(new Request('POST', '/admin/login', [], ['login' => 'admin', 'password' => 'secret-password'], [], [], []));

        $body = $app->handle(new Request('GET', '/admin/pages', [], [], [], [], []))->body();

        self::assertStringContainsString('Страницы', $body);
        self::assertStringContainsString('<code>about</code>', $body);
        self::assertStringNotContainsString('<code>products/teapot</code>', $body);
    }

    public function testElementsListShowsOnlyElements(): void
    {
        $app = $this->appWithAdmin();
        (new UserRepository($app->fs))->create('admin', 'secret-password', 'admin');
        $app->handle(new Request('POST', '/admin/login', [], ['login' => 'admin', 'password' => 'secret-password'], [], [], []));

        $body = $app->handle(new Request('GET', '/admin/elements', [], [], [], [], []))->body();

        self::assertStringContainsString('Элементы', $body);
        self::assertStringContainsString('<code>products/teapot</code>', $body);
        self::assertStringNotContainsString('<code>about</code>', $body);
    }

    public function testDeletingAPageReturnsToPagesList(): void
    {
        $app = $this->appWithAdmin();
        (new UserRepository($app->fs))->create('admin', 'secret-password', 'admin');
        $app->handle(new Request('POST', '/admin/login', [], ['login' => 'admin', 'password' => 'secret-password'], [], [], []));

        $response = $app->handle(new Request('POST', '/admin/content/delete', [], ['id' => 'about'], [], [], []));

        self::assertSame(303, $response->status());
        self::assertSame('/admin/pages', $response->headers()['Location']);
    }

    public function testDeletingAnElementReturnsToElementsList(): void
    {
        $app = $this->appWithAdmin();
        (new UserRepository($app->fs))->create('admin', 'secret-password', 'admin');
        $app->handle(new Request('POST', '/admin/login', [], ['login' => 'admin', 'password' => 'secret-password'], [], [], []));

        $response = $app->handle(new Request('POST', '/admin/content/delete', [], ['id' => 'products/teapot'], [], [], []));

        self::assertSame(303, $response->status());
        self::assertSame('/admin/elements', $response->headers()['Location']);
    }
}
