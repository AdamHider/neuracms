<?php

declare(strict_types=1);

namespace FlatCms\Modules\Builder;

use FlatCms\App;
use FlatCms\Content\Item;
use FlatCms\Content\Schema;
use FlatCms\Contracts\Module;
use FlatCms\Core\Request;
use FlatCms\Core\Response;
use FlatCms\Modules\Builder\Types\ButtonType;
use FlatCms\Modules\Builder\Types\ColumnsType;
use FlatCms\Modules\Builder\Types\DividerType;
use FlatCms\Modules\Builder\Types\ElementsType;
use FlatCms\Modules\Builder\Types\GlobalBlockType;
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

    private ?GlobalLibrary $globals = null;

    public function boot(App $app): void
    {
        $this->app = $app;
        $app->config->mergeDefaults('modules/builder/config/config.yaml');

        $this->types = new TypeRegistry($app->events);
        $this->registerCoreTypes();
        $this->globals = new GlobalLibrary($app->fs);
        $this->renderer = new LayoutRenderer($app, $this->types, $app->events, $this->globals);

        // Templates call $this->layout($item); the resolver renders the blocks.
        $app->renderer->setLayoutResolver(function (mixed $value) use ($app): string {
            return $this->renderer?->renderValue($value) ?? '';
        });

        // $this->page($item, $body) wraps a body in the header/body/footer bands.
        $app->renderer->setPageResolver(function (mixed $item, string $body = ''): string {
            // Pages without an item (cart, contact form) still get the chrome,
            // taken from the home page.
            return $this->renderer?->pageShell($item instanceof Item ? $item : null, $body) ?? $body;
        });

        $router = $app->router;
        $router->add('GET', '/admin/builder', fn (Request $r) => $this->editor($r));
        $router->add('POST', '/admin/builder/save', fn (Request $r) => $this->save($r));
        $router->add('POST', '/admin/builder/media', fn (Request $r) => $this->uploadMedia($r));
        $router->add('GET', '/admin/builder/types', fn (Request $r) => $this->typesApi($r));
        $router->add('POST', '/admin/builder/global', fn (Request $r) => $this->globalAction($r));
        $router->add('GET', '/admin/globals', fn (Request $r) => $this->globalsPage($r));
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

    public function globals(): ?GlobalLibrary
    {
        return $this->globals;
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
            new GlobalBlockType(),
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

        // A global is edited on the same canvas but saved to the library, not to
        // a page. ?global=section:main-header or ?global=block:cta.
        $global = (string) $request->query('global', '');
        if ($global !== '') {
            return $this->globalEditor($global, $request);
        }

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
            'globals' => $this->globals?->catalog() ?? [],
            'media' => $app->admin->media()?->list($id) ?? [],
            'columns' => (int) $app->config->get('builder.grid_columns', 12),
            'saved' => $request->query('saved') === '1',
            'error' => (string) $request->query('error', ''),
        ]));
    }

    /**
     * Editor for one global, either a shared section or a shared block. A section
     * global is edited as a one-section layout so the canvas and the settings
     * panel work exactly as they do for a page.
     */
    private function globalEditor(string $spec, Request $request): Response
    {
        $app = $this->requireApp();
        [$kind, $name] = $this->splitGlobal($spec);
        $library = $this->globals;
        if ($library === null || $name === '') {
            return Response::redirect('/admin/globals', 303);
        }

        if ($kind === GlobalLibrary::SECTION) {
            $stored = $library->read(GlobalLibrary::SECTION, $name);
            if ($stored === null || !is_array($stored['section'] ?? null)) {
                return Response::html($app->renderer->renderTemplate('admin/404', ['title' => 'Не найдено']), 404);
            }
            $layout = new Layout([Section::fromArray($stored['section'])], $this->gridColumns());
        } else {
            $block = $library->block($name);
            if ($block === null) {
                return Response::html($app->renderer->renderTemplate('admin/404', ['title' => 'Не найдено']), 404);
            }
            $layout = new Layout([
                new Section([new Column([$block], $this->gridColumns())]),
            ], $this->gridColumns());
        }

        $data = $library->read($kind, $name) ?? [];

        return Response::html($app->renderer->renderTemplate('admin/builder', [
            'item' => null,
            'id' => '',
            'global' => $kind . ':' . $name,
            'globalTitle' => (string) ($data['title'] ?? $name),
            'globalKind' => $kind,
            'globalName' => $name,
            'layout' => $layout,
            'types' => $this->types?->all() ?? [],
            'categories' => $this->categories(),
            'presets' => $this->presets(),
            'elements' => $this->elementTypes(),
            'menu' => $this->menuSources(),
            'globals' => $library->catalog(),
            'media' => [],
            'columns' => $this->gridColumns(),
            'saved' => $request->query('saved') === '1',
            'error' => (string) $request->query('error', ''),
        ]));
    }

    /**
     * The list of reusable sections and blocks, so an author can see what exists
     * and jump straight into editing one.
     */
    private function globalsPage(Request $request): Response
    {
        if (!$this->isAdmin()) {
            return Response::redirect('/admin/login', 302);
        }

        $app = $this->requireApp();
        $catalog = $this->globals?->catalog() ?? [GlobalLibrary::SECTION => [], GlobalLibrary::BLOCK => []];

        // Usage counts: how many pages reference each global.
        $usage = [];
        foreach ($app->storage->scan() as $id) {
            $item = $app->storage->load($id);
            if ($item === null) {
                continue;
            }
            $layout = Layout::fromValue($item->get('layout'), $this->gridColumns());
            foreach ($layout->sections() as $section) {
                $ref = trim((string) $section->get('global', ''));
                if ($ref !== '') {
                    $usage[GlobalLibrary::SECTION . ':' . $ref] = ($usage[GlobalLibrary::SECTION . ':' . $ref] ?? 0) + 1;
                }
            }
            foreach ($layout->blocks() as $block) {
                if ($block->type !== GlobalBlockType::NAME) {
                    continue;
                }
                $ref = trim((string) $block->get('name', ''));
                if ($ref !== '') {
                    $usage[GlobalLibrary::BLOCK . ':' . $ref] = ($usage[GlobalLibrary::BLOCK . ':' . $ref] ?? 0) + 1;
                }
            }
        }

        return Response::html($app->renderer->renderTemplate('admin/globals', [
            'globals' => $catalog,
            'usage' => $usage,
            'saved' => $request->query('saved') === '1',
            'error' => (string) $request->query('error', ''),
        ]));
    }

    /**
     * Promote, update or drop a global. The editor posts the whole canvas, so the
     * same action covers "make this section global" and "save the global".
     */
    private function globalAction(Request $request): Response
    {
        if (!$this->isAdmin()) {
            return Response::redirect('/admin/login', 302);
        }

        $app = $this->requireApp();
        $library = $this->globals;
        if ($library === null) {
            return Response::json(['error' => 'Модуль не загружен.'], 500);
        }

        $action = (string) $request->input('action', 'save');
        [$kind, $name] = $this->splitGlobal((string) $request->input('global', ''));
        $back = (string) $request->input('back', '');

        if ($action === 'delete') {
            $library->delete($kind, $name);

            return $this->globalRedirect($back, $request, 'deleted=1');
        }

        if ($action === 'promote') {
            return $this->promote($request, $library);
        }

        $title = trim((string) $request->input('title', ''));
        $layout = $this->sanitize($this->readLayout($request), $this->gridColumns());

        if ($kind === GlobalLibrary::SECTION) {
            $section = $layout->sections()[0] ?? null;
            if ($section === null) {
                return $this->globalRedirect($back, $request, 'error=' . rawurlencode('Пустая секция.'));
            }
            if ($name === '') {
                $name = $library->uniqueName(GlobalLibrary::SECTION, $title !== '' ? $title : $section->get('title', 'section'));
            }
            $payload = ['section' => $section->toArray()];
        } else {
            $block = $layout->blocks()[0] ?? null;
            if ($block === null) {
                return $this->globalRedirect($back, $request, 'error=' . rawurlencode('Пустой элемент.'));
            }
            if ($name === '') {
                $name = $library->uniqueName(GlobalLibrary::BLOCK, $title !== '' ? $title : $block->type);
            }
            $payload = ['block' => $block->toArray()];
        }

        $library->save($kind, $name, $title !== '' ? $title : $name, $payload);

        return $this->globalRedirect($back, $request, 'saved=1');
    }

    /**
     * Copy one section or block out of a page layout into the library and answer
     * with the name the editor should reference. The editor then replaces its
     * local copy with that reference, so the page stops carrying a duplicate.
     */
    private function promote(Request $request, GlobalLibrary $library): Response
    {
        $kind = (string) $request->input('kind', GlobalLibrary::SECTION) === GlobalLibrary::BLOCK
            ? GlobalLibrary::BLOCK
            : GlobalLibrary::SECTION;
        $title = trim((string) $request->input('title', ''));
        $layout = $this->sanitize($this->readLayout($request), $this->gridColumns());

        if ($kind === GlobalLibrary::SECTION) {
            $index = (int) $request->input('index', -1);
            $section = $layout->sections()[$index] ?? null;
            if ($section === null) {
                return Response::json(['error' => 'Секция не найдена.'], 422);
            }
            $payload = ['section' => $section->toArray()];
            $fallback = (string) $section->get('title', 'section');
        } else {
            $path = explode(':', (string) $request->input('block_index', '0:0'));
            $si = (int) ($path[0] ?? 0);
            $ci = (int) ($path[1] ?? 0);
            $bi = (int) ($path[2] ?? 0);
            $columns = $layout->sections()[$si]?->columns() ?? [];
            $block = $columns[$ci]?->blocks()[$bi] ?? null;
            if ($block === null) {
                return Response::json(['error' => 'Элемент не найден.'], 422);
            }
            $payload = ['block' => $block->toArray()];
            $fallback = $block->type;
        }

        $name = $library->uniqueName($kind, $title !== '' ? $title : $fallback);
        $library->save($kind, $name, $title !== '' ? $title : $name, $payload);

        return Response::json(['name' => $name, 'kind' => $kind, 'title' => $title !== '' ? $title : $name]);
    }

    private function globalRedirect(string $back, Request $request, string $query): Response
    {
        $separator = str_contains($back, '?') ? '&' : '?';
        if ($back !== '') {
            return Response::redirect($back . $separator . $query, 303);
        }

        return Response::redirect('/admin/globals?' . $query, 303);
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function splitGlobal(string $spec): array
    {
        $spec = trim($spec);
        if ($spec === '') {
            return [GlobalLibrary::SECTION, ''];
        }
        $parts = explode(':', $spec, 2);
        $kind = $parts[0] === GlobalLibrary::BLOCK ? GlobalLibrary::BLOCK : GlobalLibrary::SECTION;
        $name = trim($parts[1] ?? '');

        return [$kind, $this->globals?->slug($name) ?? $name];
    }

    private function gridColumns(): int
    {
        return (int) $this->requireApp()->config->get('builder.grid_columns', 12);
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
                'role' => 'header',
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
                'role' => 'footer',
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

        // Saving from a global editor writes to the library, not to a page.
        $global = (string) $request->input('global', '');
        if ($global !== '') {
            return $this->globalAction($request);
        }

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

        $settings = $this->sanitizeSettings($raw['settings'] ?? []);
        $id = isset($raw['id']) && is_string($raw['id']) ? $raw['id'] : null;

        // A section that only points at a global keeps no columns of its own.
        $isGlobalRef = trim((string) ($settings['global'] ?? '')) !== '';
        if ($columnsData === [] && !$isGlobalRef) {
            return null;
        }

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
