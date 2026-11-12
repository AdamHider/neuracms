<?php

declare(strict_types=1);

namespace FlatCms\Modules\Builder;

use FlatCms\App;
use FlatCms\Content\Schema;
use FlatCms\Contracts\Module;
use FlatCms\Core\Request;
use FlatCms\Core\Response;
use FlatCms\Modules\Builder\Types\ButtonType;
use FlatCms\Modules\Builder\Types\ColumnsType;
use FlatCms\Modules\Builder\Types\DividerType;
use FlatCms\Modules\Builder\Types\ElementsType;
use FlatCms\Modules\Builder\Types\HeadingType;
use FlatCms\Modules\Builder\Types\HtmlType;
use FlatCms\Modules\Builder\Types\ImageType;
use FlatCms\Modules\Builder\Types\MediaType;
use FlatCms\Modules\Builder\Types\MenuType;
use FlatCms\Modules\Builder\Types\ProductsType;
use FlatCms\Modules\Builder\Types\QuoteType;
use FlatCms\Modules\Builder\Types\SpacerType;
use FlatCms\Modules\Builder\Types\TextType;

/**
 * Page builder module. Registers block types, renders the "layout" property of an
 * item and exposes a JSON API plus an editor for arranging blocks.
 */
final class BuilderModule implements Module
{
    private ?App $app = null;

    private ?TypeRegistry $types = null;

    private ?LayoutRenderer $renderer = null;

    public function boot(App $app): void
    {
        $this->app = $app;
        $app->config->mergeFile('modules/builder/config/config.yaml');

        $this->types = new TypeRegistry($app->events);
        $this->registerCoreTypes();
        $this->renderer = new LayoutRenderer($app, $this->types, $app->events);

        // Templates call $this->layout($item); the resolver renders the blocks.
        $app->renderer->setLayoutResolver(function (mixed $value) use ($app): string {
            return $this->renderer?->renderValue($value) ?? '';
        });

        $router = $app->router;
        $router->add('GET', '/admin/builder', fn (Request $r) => $this->editor($r));
        $router->add('POST', '/admin/builder/save', fn (Request $r) => $this->save($r));
        $router->add('POST', '/admin/builder/media', fn (Request $r) => $this->uploadMedia($r));
        $router->add('GET', '/admin/builder/types', fn (Request $r) => $this->typesApi($r));
    }

    public function build(): void
    {
    }

    public function types(): ?TypeRegistry
    {
        return $this->types;
    }

    public function renderer(): ?LayoutRenderer
    {
        return $this->renderer;
    }

    private function registerCoreTypes(): void
    {
        $registry = $this->types;
        if ($registry === null) {
            return;
        }

        foreach ([
            new HeadingType(),
            new TextType(),
            new ImageType(),
            new MediaType(),
            new ButtonType(),
            new QuoteType(),
            new ColumnsType(),
            new ProductsType(),
            new ElementsType(),
            new MenuType(),
            new DividerType(),
            new SpacerType(),
            new HtmlType(),
        ] as $type) {
            $registry->register($type);
        }
    }

    /**
     * Types API: everything the editor needs to build a settings form.
     */
    private function typesApi(Request $request): Response
    {
        if (!$this->isAdmin()) {
            return Response::json(['error' => 'forbidden'], 403);
        }

        $types = [];
        foreach ($this->types?->all() ?? [] as $name => $type) {
            $types[] = $type->toArray();
        }

        return Response::json([
            'types' => $types,
            'categories' => $this->categories(),
            'presets' => $this->presets(),
            'elements' => $this->elementTypes(),
            'columns' => (int) $this->requireApp()->config->get('builder.grid_columns', 12),
        ]);
    }

    /**
     * Element types (custom content) with their field names, so the element-list
     * particle can offer a property picker.
     *
     * @return array<string, array{label: string, fields: list<string>}>
     */
    private function elementTypes(): array
    {
        $out = [];
        foreach ($this->requireApp()->schema->elements() as $name => $schema) {
            $out[$name] = [
                'label' => $schema['label'],
                'fields' => array_values(array_unique(array_merge(
                    ['title'],
                    array_keys($schema['fields']),
                ))),
            ];
        }

        return $out;
    }

    private function editor(Request $request): Response
    {
        if (!$this->isAdmin()) {
            return Response::redirect('/admin/login', 302);
        }

        $app = $this->requireApp();
        $id = (string) $request->query('id', '');
        if ($id === '') {
            return Response::redirect('/admin/pages', 302);
        }

        $item = $app->storage->load($id);
        if ($item === null) {
            return Response::html($app->renderer->renderTemplate('admin/404', ['title' => 'Не найдено']), 404);
        }

        // The builder defines the look of pages only. Elements are data and are
        // edited as properties, so send them back to the normal editor.
        if (!$app->schema->isPage($item->type())) {
            return Response::redirect('/admin/content/edit?id=' . rawurlencode($id), 303);
        }

        $layout = Layout::fromValue($item->get('layout'), (int) $app->config->get('builder.grid_columns', 12));

        return Response::html($app->renderer->renderTemplate('admin/builder', [
            'item' => $item,
            'id' => $id,
            'layout' => $layout,
            'types' => $this->types?->all() ?? [],
            'categories' => $this->categories(),
            'presets' => $this->presets(),
            'elements' => $this->elementTypes(),
            'menu' => $this->menuSources(),
            'media' => $app->admin->media()?->list($id) ?? [],
            'columns' => (int) $app->config->get('builder.grid_columns', 12),
            'saved' => $request->query('saved') === '1',
            'error' => (string) $request->query('error', ''),
        ]));
    }

    /**
     * Content types and their properties, so the menu particle can offer a type
     * picker and a property filter builder. Each property carries a label and
     * type so the filter value control matches the field.
     *
     * @return array<string, array{label: string, kind: string, properties: list<array{name: string, label: string, type: string}>}>
     */
    private function menuSources(): array
    {
        $app = $this->requireApp();
        $out = [];
        foreach ($app->schema->all() as $name => $schema) {
            $properties = [
                ['name' => 'title', 'label' => 'Заголовок', 'type' => 'string'],
                ['name' => 'published', 'label' => 'Опубликовано', 'type' => 'bool'],
                ['name' => 'order', 'label' => 'Порядок', 'type' => 'int'],
                ['name' => 'date', 'label' => 'Дата', 'type' => 'date'],
            ];
            foreach ($schema['fields'] as $fieldName => $field) {
                if (in_array($fieldName, ['title', 'published', 'order', 'date'], true)) {
                    continue;
                }
                $properties[] = [
                    'name' => (string) $fieldName,
                    'label' => (string) ($field['label'] ?? $fieldName),
                    'type' => (string) ($field['type'] ?? 'string'),
                ];
            }
            $out[$name] = [
                'label' => $schema['label'],
                'kind' => $schema['kind'],
                'properties' => $properties,
            ];
        }

        return $out;
    }

    /**
     * Block types grouped by category, for the particle picker.
     *
     * @return array<string, list<array>>
     */
    private function categories(): array
    {
        $grouped = [];
        foreach ($this->types?->all() ?? [] as $type) {
            $grouped[$type->category()][] = $type->toArray();
        }

        return $grouped;
    }

    /**
     * Ready-made sections ("presets"). Each carries a name, a title and the
     * column widths; some also carry ready-made blocks, so the "Добавить секцию"
     * menu can drop in a header or a footer in one click.
     *
     * @return list<array{name: string, title: string, widths: list<int>, blocks?: array<int, list<array>>, icon?: string, position?: string}>
     */
    private function presets(): array
    {
        return [
            ['name' => 'one', 'title' => 'Одна колонка', 'widths' => [12]],
            ['name' => 'two', 'title' => 'Две колонки', 'widths' => [6, 6]],
            ['name' => 'two-wide', 'title' => 'Две колонки (широкая слева)', 'widths' => [8, 4]],
            ['name' => 'three', 'title' => 'Три колонки', 'widths' => [4, 4, 4]],
            ['name' => 'four', 'title' => 'Четыре колонки', 'widths' => [3, 3, 3, 3]],
            ['name' => 'sidebar', 'title' => 'Контент + сайдбар', 'widths' => [9, 3]],
            [
                'name' => 'header',
                'title' => 'Шапка сайта',
                'icon' => 'menu',
                'position' => 'start',
                'widths' => [4, 8],
                'blocks' => [
                    [
                        ['type' => 'heading', 'settings' => ['text' => 'Название сайта', 'level' => 'h2']],
                    ],
                    [
                        ['type' => 'menu', 'settings' => ['source' => 'pages', 'depth' => 2, 'class' => 'site-nav']],
                    ],
                ],
            ],
            [
                'name' => 'footer',
                'title' => 'Подвал сайта',
                'icon' => 'menu',
                'widths' => [6, 6],
                'blocks' => [
                    [
                        ['type' => 'heading', 'settings' => ['text' => 'Название сайта', 'level' => 'h3']],
                        ['type' => 'text', 'settings' => ['body' => 'Коротко о сайте.']],
                    ],
                    [
                        ['type' => 'menu', 'settings' => ['source' => 'pages', 'depth' => 1, 'class' => 'site-nav--footer']],
                        ['type' => 'text', 'settings' => ['body' => '© ' . date('Y') . ' Все права защищены.']],
                    ],
                ],
            ],
        ];
    }

    private function save(Request $request): Response
    {
        if (!$this->isAdmin()) {
            return Response::redirect('/admin/login', 302);
        }

        $app = $this->requireApp();
        $id = (string) $request->input('id', '');
        $item = $app->storage->load($id);
        if ($item === null) {
            return Response::redirect('/admin/builder?error=' . rawurlencode('Элемент не найден.'), 303);
        }
        if (!$app->schema->isPage($item->type())) {
            return Response::redirect('/admin/content/edit?id=' . rawurlencode($id), 303);
        }

        $layout = $this->sanitize($this->readLayout($request), (int) $app->config->get('builder.grid_columns', 12));

        $safe = $app->fs->safePath('content', $id);
        if ($safe === null) {
            return Response::redirect('/admin/builder?error=' . rawurlencode('Неверный путь.'), 303);
        }

        $data = $item->all();
        $data['layout'] = $layout->toArray();
        $app->fs->writeYaml('content/' . $safe . '/item.yaml', $data);
        $app->build();

        return Response::redirect('/admin/builder?id=' . rawurlencode($id) . '&saved=1', 303);
    }

    /**
     * The layout posted by the editor: either the JSON textarea or the nested form.
     */
    private function readLayout(Request $request): array
    {
        $raw = $request->input('layout_json', null);
        if (is_string($raw) && $raw !== '') {
            $decoded = json_decode($raw, true);

            return is_array($decoded) ? $decoded : [];
        }

        $raw = $request->input('layout', []);

        return is_array($raw) ? $raw : [];
    }

    /**
     * Upload a file from the builder (media particle) into the page's folder and
     * return its /media URL as JSON, so the editor can drop it into a setting.
     */
    private function uploadMedia(Request $request): Response
    {
        if (!$this->isAdmin()) {
            return Response::json(['error' => 'forbidden'], 403);
        }

        $app = $this->requireApp();
        $id = (string) $request->input('id', '');
        $item = $app->storage->load($id);
        if ($item === null || !$app->schema->isPage($item->type())) {
            return Response::json(['error' => 'Элемент не найден.'], 404);
        }

        $file = $request->file('file') ?? [];
        $library = $app->admin->media();
        $error = $library?->upload($id, $file) ?? 'Модуль не загружен.';
        if ($error !== '') {
            return Response::json(['error' => $error], 422);
        }

        $name = basename((string) ($file['name'] ?? ''));

        return Response::json([
            'name' => $name,
            'url' => '/media/' . $id . '/' . $name,
            'media' => $library?->list($id) ?? [],
        ]);
    }

    /**
     * Rebuild a Layout from request data, dropping unknown block types and
     * normalising settings to scalars so nothing unsafe reaches disk. Accepts
     * the current {"sections":[...]} shape and the legacy flat {"blocks":[...]}.
     */
    private function sanitize(mixed $raw, int $columns): Layout
    {
        if (!is_array($raw)) {
            return new Layout([], $columns);
        }

        $sections = [];
        if (isset($raw['sections']) && is_array($raw['sections'])) {
            foreach ($raw['sections'] as $entry) {
                if (!is_array($entry)) {
                    continue;
                }
                $section = $this->sanitizeSection($entry, $columns);
                if ($section !== null) {
                    $sections[] = $section;
                }
            }

            return new Layout($sections, $columns);
        }

        // Legacy flat block list: upgrade through Layout so it lands as sections.
        $blocks = $raw['blocks'] ?? $raw;
        $clean = [];
        if (is_array($blocks)) {
            foreach ($blocks as $entry) {
                if (!is_array($entry)) {
                    continue;
                }
                $block = $this->sanitizeBlock($entry);
                if ($block !== null) {
                    $clean[] = $block;
                }
            }
        }

        return Layout::fromValue(
            ['blocks' => array_map(static fn (Block $b): array => $b->toArray(), $clean)],
            $columns
        );
    }

    private function sanitizeSection(mixed $raw, int $columns): ?Section
    {
        if (!is_array($raw)) {
            return null;
        }

        $columnsData = [];
        foreach (is_array($raw['columns'] ?? null) ? $raw['columns'] : [] as $column) {
            if (!is_array($column)) {
                continue;
            }
            $blocks = [];
            foreach (is_array($column['blocks'] ?? null) ? $column['blocks'] : [] as $entry) {
                if (is_array($entry)) {
                    $block = $this->sanitizeBlock($entry);
                    if ($block !== null) {
                        $blocks[] = $block;
                    }
                }
            }
            $width = isset($column['width']) ? max(0, min($columns, (int) $column['width'])) : 0;
            $columnsData[] = new Column($blocks, $width);
        }

        // A section given as a flat block list keeps working.
        if ($columnsData === [] && isset($raw['blocks']) && is_array($raw['blocks'])) {
            $blocks = [];
            foreach ($raw['blocks'] as $entry) {
                if (is_array($entry)) {
                    $block = $this->sanitizeBlock($entry);
                    if ($block !== null) {
                        $blocks[] = $block;
                    }
                }
            }
            $columnsData[] = new Column($blocks, $columns);
        }

        if ($columnsData === []) {
            return null;
        }

        $settings = $this->sanitizeSettings($raw['settings'] ?? []);
        $id = isset($raw['id']) && is_string($raw['id']) ? $raw['id'] : null;

        return new Section($columnsData, $settings, $id);
    }

    private function sanitizeBlock(mixed $entry): ?Block
    {
        if (!is_array($entry)) {
            return null;
        }

        $type = (string) ($entry['type'] ?? '');
        if ($type === '' || !($this->types?->has($type) ?? false)) {
            return null;
        }

        $settings = $this->sanitizeSettings($entry['settings'] ?? []);
        $id = isset($entry['id']) && is_string($entry['id']) ? $entry['id'] : null;

        return new Block($type, $settings, $id);
    }

    /**
     * @return array<string, mixed>
     */
    private function sanitizeSettings(mixed $settings): array
    {
        if (!is_array($settings)) {
            return [];
        }

        $clean = [];
        foreach ($settings as $key => $value) {
            $key = (string) $key;
            if ($key === '' || !preg_match('/^[a-z0-9_]+$/i', $key)) {
                continue;
            }
            if (is_array($value)) {
                // Nested blocks (columns) recurse one level.
                $clean[$key] = $this->sanitizeSettings($value);
            } elseif (is_bool($value) || is_int($value) || is_float($value) || $value === null) {
                $clean[$key] = $value;
            } else {
                $clean[$key] = (string) $value;
            }
        }

        return $clean;
    }

    private function isAdmin(): bool
    {
        return $this->app?->admin->auth()?->isAdmin() ?? false;
    }

    private function requireApp(): App
    {
        if ($this->app === null) {
            throw new \RuntimeException('Builder module is not booted');
        }

        return $this->app;
    }
}
