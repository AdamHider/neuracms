<?php

declare(strict_types=1);

namespace FlatCms\Admin;

use FlatCms\App;
use FlatCms\Content\Item;
use FlatCms\Contracts\Subsystem;
use FlatCms\Core\Request;
use FlatCms\Core\Response;

/**
 * The admin panel. Part of the core rather than a module: login, content CRUD,
 * media uploads, revision history and a small tools page, all under /admin.
 * It can be switched off with "admin.enabled: false" in config/site.yaml.
 */
final class AdminPanel implements Subsystem
{
    private ?App $app = null;

    private ?Auth $auth = null;

    private ?ContentWriter $writer = null;

    private ?MediaLibrary $media = null;

    private ?UserRepository $users = null;

    public function boot(App $app): void
    {
        $this->app = $app;

        $this->users = new UserRepository($app->fs);
        $this->auth = new Auth($this->users, $app->session());
        $this->writer = new ContentWriter($app->fs);
        $this->media = new MediaLibrary($app->fs);

        // The panel owns its templates, partials and CSS; they live in the core
        // tree and are served at /assets/admin/... .
        $app->addCoreTemplates('src/Admin');
        $app->addCoreAssets('admin', 'src/Admin/assets');

        if (!$this->isEnabled()) {
            return;
        }

        $router = $app->router;
        $router->add('GET', '/admin', fn (Request $r) => $this->dashboard($r));
        $router->add('GET', '/admin/login', fn (Request $r) => $this->loginForm($r));
        $router->add('POST', '/admin/login', fn (Request $r) => $this->loginSubmit($r));
        $router->add('POST', '/admin/logout', fn (Request $r) => $this->logout($r));

        $router->add('GET', '/admin/content', fn (Request $r) => $this->contentList($r));
        $router->add('GET', '/admin/content/new', fn (Request $r) => $this->contentNew($r));
        $router->add('POST', '/admin/content/new', fn (Request $r) => $this->contentCreate($r));
        $router->add('GET', '/admin/content/edit', fn (Request $r) => $this->contentEdit($r));
        $router->add('POST', '/admin/content/edit', fn (Request $r) => $this->contentSave($r));
        $router->add('POST', '/admin/content/delete', fn (Request $r) => $this->contentDelete($r));
        $router->add('POST', '/admin/content/move', fn (Request $r) => $this->contentMove($r));
        $router->add('GET', '/admin/content/history', fn (Request $r) => $this->contentHistory($r));

        $router->add('POST', '/admin/media/upload', fn (Request $r) => $this->mediaUpload($r));
        $router->add('POST', '/admin/media/delete', fn (Request $r) => $this->mediaDelete($r));

        $router->add('GET', '/admin/users', fn (Request $r) => $this->usersPage($r));
        $router->add('POST', '/admin/users/create', fn (Request $r) => $this->usersCreate($r));

        $router->add('GET', '/admin/tools', fn (Request $r) => $this->toolsPage($r));
        $router->add('POST', '/admin/tools/rebuild', fn (Request $r) => $this->toolsRebuild($r));
        $router->add('POST', '/admin/tools/clear-cache', fn (Request $r) => $this->toolsClearCache($r));
    }

    public function build(): void
    {
    }

    public function auth(): ?Auth
    {
        return $this->auth;
    }

    public function users(): ?UserRepository
    {
        return $this->users;
    }

    public function contentWriter(): ?ContentWriter
    {
        return $this->writer;
    }

    public function media(): ?MediaLibrary
    {
        return $this->media;
    }

    private function dashboard(Request $request): Response
    {
        if (!$this->requireAuth()) {
            return Response::redirect('/admin/login', 302);
        }

        $app = $this->requireApp();
        $items = $app->query()->withUnpublished()->find();
        $orders = $app->module('shop')?->orders()?->all(5) ?? [];

        return $this->page('admin/dashboard', 'Панель', [
            'counts' => $this->typeCounts($items),
            'recent' => array_slice($items, 0, 10),
            'orders' => $orders,
        ]);
    }

    private function loginForm(Request $request, string $error = ''): Response
    {
        if ($this->auth?->check()) {
            return Response::redirect('/admin', 302);
        }

        return $this->page('admin/login', 'Вход', ['error' => $error, 'layout' => false]);
    }

    private function loginSubmit(Request $request): Response
    {
        $login = (string) $request->input('login', '');
        $password = (string) $request->input('password', '');

        if ($this->auth?->attempt($login, $password)) {
            return Response::redirect('/admin', 302);
        }

        return $this->loginForm($request, 'Неверный логин или пароль.')->setStatus(401);
    }

    private function logout(Request $request): Response
    {
        $this->auth?->logout();

        return Response::redirect('/admin/login', 302);
    }

    private function contentList(Request $request): Response
    {
        if (!$this->requireAuth()) {
            return Response::redirect('/admin/login', 302);
        }

        $app = $this->requireApp();
        $type = trim((string) $request->query('type', ''));

        $query = $app->query()->withUnpublished();
        if ($type !== '') {
            $query = $query->type($type);
        }

        return $this->page('admin/content-list', 'Контент', [
            'items' => $query->sort('id')->find(),
            'type' => $type,
        ]);
    }

    private function contentNew(Request $request): Response
    {
        if (!$this->requireAuth()) {
            return Response::redirect('/admin/login', 302);
        }

        return $this->page('admin/content-edit', 'Новый элемент', [
            'item' => null,
            'id' => '',
            'types' => $this->types(),
            'errors' => [],
        ]);
    }

    private function contentCreate(Request $request): Response
    {
        if (!$this->requireAuth()) {
            return Response::redirect('/admin/login', 302);
        }

        $id = trim((string) $request->input('id', ''));
        $writer = $this->writer;

        if ($writer === null || $id === '') {
            return $this->page('admin/content-edit', 'Новый элемент', [
                'item' => null,
                'id' => $id,
                'types' => $this->types(),
                'errors' => ['Укажите путь элемента.'],
            ])->setStatus(422);
        }

        [$data, $body, $errors] = $this->collect($request);
        if ($errors !== []) {
            return $this->page('admin/content-edit', 'Новый элемент', [
                'item' => null,
                'id' => $id,
                'types' => $this->types(),
                'errors' => $errors,
            ])->setStatus(422);
        }

        $writer->save($id, $data, $body);
        $this->requireApp()->build();

        return Response::redirect('/admin/content/edit?id=' . rawurlencode($id), 303);
    }

    private function contentEdit(Request $request): Response
    {
        if (!$this->requireAuth()) {
            return Response::redirect('/admin/login', 302);
        }

        $id = (string) $request->query('id', '');
        $item = $this->writer?->load($id);
        if ($item === null) {
            return $this->notFound();
        }

        return $this->page('admin/content-edit', 'Редактирование: ' . $item->title(), [
            'item' => $item,
            'id' => $id,
            'types' => $this->types(),
            'errors' => [],
            'saved' => $request->query('saved') === '1',
            'media' => $this->media?->list($id) ?? [],
            'media_error' => (string) $request->query('media_error', ''),
        ]);
    }

    private function contentSave(Request $request): Response
    {
        if (!$this->requireAuth()) {
            return Response::redirect('/admin/login', 302);
        }

        $id = (string) $request->input('id', '');
        [$data, $body, $errors] = $this->collect($request);

        if ($errors !== []) {
            $item = new Item($id, $data, $body);

            return $this->page('admin/content-edit', 'Редактирование: ' . $item->title(), [
                'item' => $item,
                'id' => $id,
                'types' => $this->types(),
                'errors' => $errors,
                'media' => $this->media?->list($id) ?? [],
            ])->setStatus(422);
        }

        $this->writer?->save($id, $data, $body);
        $this->requireApp()->build();

        return Response::redirect('/admin/content/edit?id=' . rawurlencode($id) . '&saved=1', 303);
    }

    private function contentDelete(Request $request): Response
    {
        if (!$this->requireAuth()) {
            return Response::redirect('/admin/login', 302);
        }

        $this->writer?->delete((string) $request->input('id', ''));
        $this->requireApp()->build();

        return Response::redirect('/admin/content', 303);
    }

    private function contentMove(Request $request): Response
    {
        if (!$this->requireAuth()) {
            return Response::redirect('/admin/login', 302);
        }

        $from = (string) $request->input('id', '');
        $to = (string) $request->input('to', '');
        $this->writer?->move($from, $to);
        $this->requireApp()->build();

        return Response::redirect('/admin/content/edit?id=' . rawurlencode($to !== '' ? $to : $from), 303);
    }

    private function contentHistory(Request $request): Response
    {
        if (!$this->requireAuth()) {
            return Response::redirect('/admin/login', 302);
        }

        $id = (string) $request->query('id', '');

        return $this->page('admin/content-history', 'История: ' . $id, [
            'id' => $id,
            'entries' => $this->writer?->history($id) ?? [],
        ]);
    }

    private function mediaUpload(Request $request): Response
    {
        if (!$this->requireAuth()) {
            return Response::redirect('/admin/login', 302);
        }

        $id = (string) $request->input('id', '');
        $file = $request->file('file') ?? [];
        $error = $this->media?->upload($id, $file) ?? 'Модуль не загружен.';

        $url = '/admin/content/edit?id=' . rawurlencode($id) . '&saved=1';
        if ($error !== '') {
            $url .= '&media_error=' . rawurlencode($error);
        }

        return Response::redirect($url, 303);
    }

    private function mediaDelete(Request $request): Response
    {
        if (!$this->requireAuth()) {
            return Response::redirect('/admin/login', 302);
        }

        $id = (string) $request->input('id', '');
        $this->media?->delete($id, (string) $request->input('name', ''));

        return Response::redirect('/admin/content/edit?id=' . rawurlencode($id) . '&saved=1', 303);
    }

    private function usersPage(Request $request): Response
    {
        if (!$this->requireAdmin()) {
            return Response::redirect('/admin', 302);
        }

        return $this->page('admin/users', 'Пользователи', [
            'users' => $this->users?->all() ?? [],
            'created' => $request->query('created') === '1',
            'error' => (string) $request->query('error', ''),
        ]);
    }

    private function usersCreate(Request $request): Response
    {
        if (!$this->requireAdmin()) {
            return Response::redirect('/admin', 302);
        }

        $login = (string) $request->input('login', '');
        $password = (string) $request->input('password', '');
        $role = (string) $request->input('role', 'editor');

        if (mb_strlen($password) < 8) {
            return Response::redirect('/admin/users?error=' . rawurlencode('Пароль короче 8 символов.'), 303);
        }

        $ok = $this->users?->create($login, $password, in_array($role, ['admin', 'editor'], true) ? $role : 'editor');

        return Response::redirect('/admin/users?' . ($ok ? 'created=1' : 'error=' . rawurlencode('Не удалось создать пользователя.')), 303);
    }

    private function toolsPage(Request $request): Response
    {
        if (!$this->requireAdmin()) {
            return Response::redirect('/admin', 302);
        }

        $app = $this->requireApp();

        return $this->page('admin/tools', 'Инструменты', [
            'stamp' => $app->index->stamp(),
            'cache_size' => $this->dirSize('var/cache'),
            'history_size' => $this->dirSize('var/history'),
            'done' => (string) $request->query('done', ''),
        ]);
    }

    private function toolsRebuild(Request $request): Response
    {
        if (!$this->requireAdmin()) {
            return Response::redirect('/admin', 302);
        }

        $this->requireApp()->build();

        return Response::redirect('/admin/tools?done=rebuild', 303);
    }

    private function toolsClearCache(Request $request): Response
    {
        if (!$this->requireAdmin()) {
            return Response::redirect('/admin', 302);
        }

        $app = $this->requireApp();
        $app->fs->delete('var/cache/index.php');
        $app->build();

        return Response::redirect('/admin/tools?done=cache', 303);
    }

    /**
     * @return array{0: array<string, mixed>, 1: string, 2: list<string>}
     */
    private function collect(Request $request): array
    {
        $data = [
            'type' => trim((string) $request->input('type', 'page')),
            'title' => trim((string) $request->input('title', '')),
            'order' => (int) $request->input('order', 0),
            'published' => (bool) $request->input('published', false),
        ];

        $slug = trim((string) $request->input('slug', ''));
        if ($slug !== '') {
            $data['slug'] = $slug;
        }

        $props = $request->input('props', []);
        if (is_array($props)) {
            foreach ($props as $pair) {
                if (!is_array($pair)) {
                    continue;
                }
                $key = trim((string) ($pair['key'] ?? ''));
                if ($key === '' || in_array($key, Item::RESERVED, true)) {
                    continue;
                }
                $value = $pair['value'] ?? '';
                $data[$key] = is_string($value) ? trim($value) : $value;
            }
        }

        $body = (string) $request->input('body', '');

        $item = new Item('', $data, $body);
        $errors = $this->requireApp()->schema->validate($item);
        if ($data['title'] === '') {
            $errors[] = 'Укажите заголовок.';
        }

        return [$data, $body, $errors];
    }

    /**
     * @param list<Item> $items
     *
     * @return array<string, int>
     */
    private function typeCounts(array $items): array
    {
        $counts = [];
        foreach ($items as $item) {
            $counts[$item->type()] = ($counts[$item->type()] ?? 0) + 1;
        }

        return $counts;
    }

    /**
     * @return list<string>
     */
    private function types(): array
    {
        $dir = $this->requireApp()->fs->path('config/schemas');
        $types = [];
        foreach ($this->requireApp()->fs->listDir('config/schemas') as $name) {
            if (str_ends_with($name, '.yaml')) {
                $types[] = substr($name, 0, -5);
            }
        }

        if ($types === []) {
            $types = ['page'];
        }

        return $types;
    }

    private function dirSize(string $path): int
    {
        $fs = $this->requireApp()->fs;
        $real = $fs->path($path);
        if (!is_dir($real)) {
            return 0;
        }

        $size = 0;
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($real, \FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $size += $file->getSize();
            }
        }

        return $size;
    }

    private function page(string $template, string $title, array $data): Response
    {
        $app = $this->requireApp();
        $data['title'] = $title;
        $data['admin_user'] = $this->auth?->user();

        return Response::html($app->renderer->renderTemplate($template, $data));
    }

    private function notFound(): Response
    {
        return Response::html($this->requireApp()->renderer->renderTemplate('admin/404', ['title' => 'Не найдено']), 404);
    }

    private function requireAuth(): bool
    {
        return $this->auth?->check() ?? false;
    }

    private function requireAdmin(): bool
    {
        return $this->auth?->isAdmin() ?? false;
    }

    private function requireApp(): App
    {
        if ($this->app === null) {
            throw new \RuntimeException('Admin panel is not booted');
        }

        return $this->app;
    }

    private function isEnabled(): bool
    {
        return (bool) ($this->app?->config->get('admin.enabled', true) ?? true);
    }
}
