<?php

declare(strict_types=1);

namespace FlatCms\Admin;

use FlatCms\App;
use FlatCms\Content\Item;
use FlatCms\Content\Schema;
use FlatCms\Content\TypeRepository;
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

    private ?TypeRepository $types = null;

    public function boot(App $app): void
    {
        $this->app = $app;

        $this->users = new UserRepository($app->fs);
        $this->auth = new Auth($this->users, $app->session());
        $this->writer = new ContentWriter($app->fs);
        $this->media = new MediaLibrary($app->fs);
        $this->types = new TypeRepository($app->fs, $app->schema);

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

        $router->add('GET', '/admin/types', fn (Request $r) => $this->typesList($r));
        $router->add('GET', '/admin/types/new', fn (Request $r) => $this->typesNew($r));
        $router->add('POST', '/admin/types/new', fn (Request $r) => $this->typesCreate($r));
        $router->add('GET', '/admin/types/edit', fn (Request $r) => $this->typesEdit($r));
        $router->add('POST', '/admin/types/edit', fn (Request $r) => $this->typesSave($r));
        $router->add('POST', '/admin/types/delete', fn (Request $r) => $this->typesDelete($r));

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

    public function types(): ?TypeRepository
    {
        return $this->types;
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

        $type = (string) $request->query('type', 'page');

        return $this->page('admin/content-edit', 'Новый элемент', [
            'item' => null,
            'id' => '',
            'types' => $this->typeOptions(),
            'type' => $type,
            'fields' => $this->fieldMeta($type),
            'relations' => $this->relationChoices($type),
            'errors' => [],
        ]);
    }

    private function contentCreate(Request $request): Response
    {
        if (!$this->requireAuth()) {
            return Response::redirect('/admin/login', 302);
        }

        $id = trim((string) $request->input('id', ''));
        $type = trim((string) $request->input('type', 'page'));
        $writer = $this->writer;

        if ($writer === null || $id === '') {
            return $this->page('admin/content-edit', 'Новый элемент', $this->editData(null, $id, $type, ['Укажите путь элемента.']))
                ->setStatus(422);
        }

        [$data, $body, $errors] = $this->collect($request);
        if ($errors !== []) {
            return $this->page('admin/content-edit', 'Новый элемент', $this->editData(null, $id, $type, $errors, $data, $body))
                ->setStatus(422);
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

        return $this->page('admin/content-edit', 'Редактирование: ' . $item->title(), $this->editData(
            $item,
            $id,
            $item->type(),
            [],
            $item->all(),
            $item->body(),
            [
                'saved' => $request->query('saved') === '1',
                'media' => $this->media?->list($id) ?? [],
                'media_error' => (string) $request->query('media_error', ''),
            ],
        ));
    }

    private function contentSave(Request $request): Response
    {
        if (!$this->requireAuth()) {
            return Response::redirect('/admin/login', 302);
        }

        $id = (string) $request->input('id', '');
        $type = trim((string) $request->input('type', 'page'));
        [$data, $body, $errors] = $this->collect($request);

        if ($errors !== []) {
            $item = new Item($id, $data, $body);

            return $this->page('admin/content-edit', 'Редактирование: ' . $item->title(), $this->editData(
                $item,
                $id,
                $type,
                $errors,
                $data,
                $body,
                ['media' => $this->media?->list($id) ?? []],
            ))->setStatus(422);
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
     * Read the posted form into item data. Values described by the type's schema
     * are cast to their field type; unknown keys stay free-form properties.
     *
     * @return array{0: array<string, mixed>, 1: string, 2: list<string>}
     */
    private function collect(Request $request): array
    {
        $app = $this->requireApp();
        $type = trim((string) $request->input('type', 'page'));
        $fields = $app->schema->get($type)['fields'];

        $data = [
            'type' => $type,
            'title' => trim((string) $request->input('title', '')),
            'order' => (int) $request->input('order', 0),
            'published' => (bool) $request->input('published', false),
        ];

        $slug = trim((string) $request->input('slug', ''));
        if ($slug !== '') {
            $data['slug'] = $slug;
        }

        $posted = $request->input('fields', []);
        $posted = is_array($posted) ? $posted : [];

        foreach ($fields as $name => $field) {
            if (in_array($name, Item::RESERVED, true)) {
                continue;
            }
            $data[$name] = $this->castField($field, $posted[$name] ?? null);
        }

        $props = $request->input('props', []);
        if (is_array($props)) {
            foreach ($props as $pair) {
                if (!is_array($pair)) {
                    continue;
                }
                $key = trim((string) ($pair['key'] ?? ''));
                if ($key === '' || in_array($key, Item::RESERVED, true) || array_key_exists($key, $fields)) {
                    continue;
                }
                $value = $pair['value'] ?? '';
                $data[$key] = is_string($value) ? trim($value) : $value;
            }
        }

        $body = (string) $request->input('body', '');

        $item = new Item('', $data, $body);
        $errors = $app->schema->validate($item);
        if ($data['title'] === '') {
            $errors[] = 'Укажите заголовок.';
        }

        return [$data, $body, $errors];
    }

    /**
     * @param array<string, mixed> $field
     */
    private function castField(array $field, mixed $value): mixed
    {
        $type = (string) ($field['type'] ?? 'string');

        return match ($type) {
            'int' => $this->isBlank($value) ? null : (int) (is_string($value) ? trim($value) : $value),
            'float' => $this->isBlank($value) ? null : (float) (is_string($value) ? trim($value) : $value),
            'bool' => (bool) $value,
            'list' => $this->toList($value),
            'relation' => !empty($field['multiple'])
                ? array_values(array_filter($this->toList($value), static fn (string $id): bool => $id !== ''))
                : ($this->isBlank($value) ? null : trim((string) $value)),
            default => $this->isBlank($value) ? null : (is_string($value) ? trim($value) : $value),
        };
    }

    /**
     * @return list<string>
     */
    private function toList(mixed $value): array
    {
        if (is_array($value)) {
            $items = array_map(static fn (mixed $v): string => trim((string) $v), $value);

            return array_values(array_filter($items, static fn (string $v): bool => $v !== ''));
        }

        if (!is_string($value) || trim($value) === '') {
            return [];
        }

        return array_values(array_filter(array_map('trim', explode(',', $value)), static fn (string $v): bool => $v !== ''));
    }

    private function isBlank(mixed $value): bool
    {
        return $value === null || $value === '' || $value === [];
    }

    /**
     * Shared payload for the content editor template.
     *
     * @param array<string, mixed> $data
     * @param list<string>         $errors
     * @param array<string, mixed> $extra
     *
     * @return array<string, mixed>
     */
    private function editData(
        ?Item $item,
        string $id,
        string $type,
        array $errors,
        ?array $data = null,
        ?string $body = null,
        array $extra = [],
    ): array {
        $type = $type !== '' ? $type : 'page';

        return array_merge([
            'item' => $item,
            'id' => $id,
            'types' => $this->typeOptions(),
            'type' => $type,
            'data' => $data ?? $item?->all() ?? [],
            'body' => $body ?? $item?->body() ?? '',
            'fields' => $this->requireApp()->schema->get($type)['fields'],
            'relations' => $this->relationChoices($type),
            'errors' => $errors,
            'saved' => false,
            'media' => [],
            'media_error' => '',
        ], $extra);
    }

    /**
     * Type name => human label, for the type picker.
     *
     * @return array<string, string>
     */
    private function typeOptions(): array
    {
        $options = [];
        foreach ($this->requireApp()->schema->all() as $name => $schema) {
            $options[$name] = $schema['label'];
        }

        return $options !== [] ? $options : ['page' => 'Page'];
    }

    /**
     * Candidate items for each relation field of a type.
     *
     * @return array<string, list<array{id: string, title: string, type: string}>>
     */
    private function relationChoices(string $type): array
    {
        $app = $this->requireApp();
        $choices = [];

        foreach ($app->schema->relations($type) as $name => $relation) {
            $query = $app->query()->withUnpublished();
            if ($relation['to'] !== '') {
                $query = $query->type($relation['to']);
            }

            $list = [];
            foreach ($query->sort('title')->find() as $candidate) {
                $list[] = [
                    'id' => $candidate->id(),
                    'title' => $candidate->title(),
                    'type' => $candidate->type(),
                ];
            }
            $choices[$name] = $list;
        }

        return $choices;
    }

    private function typesList(Request $request): Response
    {
        if (!$this->requireAuth()) {
            return Response::redirect('/admin/login', 302);
        }

        $app = $this->requireApp();
        $types = [];
        foreach ($app->schema->all() as $name => $schema) {
            $types[$name] = [
                'label' => $schema['label'],
                'builtin' => $schema['builtin'],
                'fields' => $schema['fields'],
                'indexed' => $schema['indexed'],
                'relations' => $app->schema->relations($name),
                'referenced_by' => $app->schema->referencedBy($name),
                'count' => $app->query()->withUnpublished()->type($name)->count(),
            ];
        }

        return $this->page('admin/types-list', 'Типы контента', [
            'types' => $types,
            'created' => (string) $request->query('created', ''),
            'deleted' => (string) $request->query('deleted', ''),
            'error' => (string) $request->query('error', ''),
        ]);
    }

    private function typesNew(Request $request): Response
    {
        if (!$this->requireAuth()) {
            return Response::redirect('/admin/login', 302);
        }

        return $this->page('admin/types-edit', 'Новый тип', [
            'name' => '',
            'label' => '',
            'fields' => [],
            'indexed' => [],
            'builtin' => false,
            'isNew' => true,
            'typeNames' => $this->typeOptions(),
            'errors' => [],
            'referenced_by' => [],
        ]);
    }

    private function typesCreate(Request $request): Response
    {
        if (!$this->requireAuth()) {
            return Response::redirect('/admin/login', 302);
        }

        $repository = $this->types;
        $name = $repository?->normalizeName((string) $request->input('name', ''));
        $label = trim((string) $request->input('label', ''));

        if ($repository === null || $name === null) {
            return $this->typesFormError('', $label, $request, ['Недопустимое имя типа. Используйте латиницу, цифры, дефис.']);
        }
        if ($repository->exists($name)) {
            return $this->typesFormError($name, $label, $request, ['Тип с таким именем уже существует.']);
        }

        $parsed = $repository->parseRows($this->fieldRows($request));
        if ($parsed['errors'] !== []) {
            return $this->typesFormError($name, $label, $request, $parsed['errors']);
        }

        $repository->save($name, $parsed['fields'], $parsed['indexed'], $label);
        $this->requireApp()->build();

        return Response::redirect('/admin/types?created=' . rawurlencode($name), 303);
    }

    private function typesEdit(Request $request): Response
    {
        if (!$this->requireAuth()) {
            return Response::redirect('/admin/login', 302);
        }

        $name = (string) $request->query('name', '');
        $loaded = $this->types?->load($name);
        if ($loaded === null) {
            return $this->notFound();
        }

        return $this->page('admin/types-edit', 'Тип: ' . $loaded['label'], [
            'name' => $name,
            'label' => $loaded['label'],
            'fields' => $loaded['fields'],
            'indexed' => $loaded['indexed'],
            'builtin' => $this->types?->isBuiltin($name) ?? false,
            'isNew' => false,
            'typeNames' => $this->typeOptions(),
            'errors' => [],
            'referenced_by' => $this->requireApp()->schema->referencedBy($name),
        ]);
    }

    private function typesSave(Request $request): Response
    {
        if (!$this->requireAuth()) {
            return Response::redirect('/admin/login', 302);
        }

        $repository = $this->types;
        $name = (string) $request->input('name', '');
        $label = trim((string) $request->input('label', ''));

        if ($repository === null || !$repository->exists($name)) {
            return $this->notFound();
        }

        $parsed = $repository->parseRows($this->fieldRows($request));
        if ($parsed['errors'] !== []) {
            return $this->typesFormError($name, $label, $request, $parsed['errors']);
        }

        $repository->save($name, $parsed['fields'], $parsed['indexed'], $label);
        $this->requireApp()->build();

        return Response::redirect('/admin/types?created=' . rawurlencode($name), 303);
    }

    private function typesDelete(Request $request): Response
    {
        if (!$this->requireAdmin()) {
            return Response::redirect('/admin', 302);
        }

        $name = (string) $request->input('name', '');
        $repository = $this->types;

        if ($repository === null || !$repository->exists($name)) {
            return $this->notFound();
        }
        if ($repository->isBuiltin($name)) {
            return Response::redirect('/admin/types?error=' . rawurlencode('Встроенный тип нельзя удалить.'), 303);
        }

        $repository->delete($name);
        $this->requireApp()->build();

        return Response::redirect('/admin/types?deleted=' . rawurlencode($name), 303);
    }

    /**
     * Re-render the type form with errors and the submitted rows kept.
     *
     * @param list<string> $errors
     */
    private function typesFormError(string $name, string $label, Request $request, array $errors): Response
    {
        return $this->page('admin/types-edit', $name === '' ? 'Новый тип' : 'Тип: ' . $name, [
            'name' => $name,
            'label' => $label,
            'fields' => $this->submittedFields($request),
            'indexed' => $this->submittedIndexed($request),
            'builtin' => $name !== '' && ($this->types?->isBuiltin($name) ?? false),
            'isNew' => $name === '' || !($this->types?->exists($name) ?? false),
            'typeNames' => $this->typeOptions(),
            'errors' => $errors,
            'referenced_by' => $name !== '' ? $this->requireApp()->schema->referencedBy($name) : [],
        ])->setStatus(422);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fieldRows(Request $request): array
    {
        $rows = $request->input('fields', []);

        return is_array($rows) ? $rows : [];
    }

    /**
     * Field rows as a field map, so the form can render them back on error.
     *
     * @return array<string, array<string, mixed>>
     */
    private function submittedFields(Request $request): array
    {
        $fields = [];
        foreach ($this->fieldRows($request) as $row) {
            if (!is_array($row)) {
                continue;
            }
            $name = strtolower(trim((string) ($row['name'] ?? '')));
            if ($name === '') {
                continue;
            }
            $field = ['type' => (string) ($row['type'] ?? 'string')];
            foreach (['label', 'default', 'to', 'options'] as $key) {
                if (($row[$key] ?? '') !== '') {
                    $field[$key] = $row[$key];
                }
            }
            if (!empty($row['required'])) {
                $field['required'] = true;
            }
            if (!empty($row['multiple'])) {
                $field['multiple'] = true;
            }
            $fields[$name] = $field;
        }

        return $fields;
    }

    /**
     * @return list<string>
     */
    private function submittedIndexed(Request $request): array
    {
        $indexed = [];
        foreach ($this->fieldRows($request) as $row) {
            if (is_array($row) && !empty($row['indexed']) && ($row['name'] ?? '') !== '') {
                $indexed[] = strtolower(trim((string) $row['name']));
            }
        }

        return $indexed;
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
