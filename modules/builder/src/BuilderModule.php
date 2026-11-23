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
use FlatCms\Modules\Builder\Types\DividerType;
use FlatCms\Modules\Builder\Types\ElementsType;
use FlatCms\Modules\Builder\Types\GlobalBlockType;
use FlatCms\Modules\Builder\Types\HeadingType;
use FlatCms\Modules\Builder\Types\HtmlType;
use FlatCms\Modules\Builder\Types\ImageType;
use FlatCms\Modules\Builder\Types\MediaType;
use FlatCms\Modules\Builder\Types\MenuType;
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

        // A brand-new page starts with a header section, so every page has a
        // header by default. It is an ordinary section: deleting it in the
        // builder removes the header from that page.
        $app->events->on('item.created', function (array $data): array {
            $type = (string) ($data['type'] ?? 'page');
            if (!isset($data['layout']) && $this->requireApp()->schema->isPage($type)) {
                $data['layout'] = $this->defaultLayout($type);
            }

            return $data;
        });

        $router = $app->router;
        $router->add('GET', '/admin/builder', fn (Request $r) => $this->editor($r));
        $router->add('POST', '/admin/builder/save', fn (Request $r) => $this->save($r));
        $router->add('POST', '/admin/builder/media', fn (Request $r) => $this->uploadMedia($r));
        $router->add('GET', '/admin/builder/types', fn (Request $r) => $this->typesApi($r));
        $router->add('POST', '/admin/builder/global', fn (Request $r) => $this->globalAction($r));
        $router->add('GET', '/admin/builder/global', fn (Request $r) => $this->globalApi($r));
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

        $columns = (int) $app->config->get('builder.grid_columns', 12);
        $layout = Layout::fromValue($item->get('layout'), $columns);

        return Response::html($app->renderer->renderTemplate('admin/builder', [
            'item' => $item,
            'id' => $id,
            // Global references are expanded here, so every shared band and
            // block is edited in place on whatever page shows it. The save path
            // collapses them back into references.
            'layout' => $this->hydrate($layout),
            'types' => $this->types?->all() ?? [],
            'categories' => $this->categories(),
            'presets' => $this->presets(),
            'elements' => $this->elementTypes(),
            'menu' => $this->menuSources(),
            'globals' => $this->globals?->catalog() ?? [],
            'media' => $app->admin->media()?->list($id) ?? [],
            'columns' => $columns,
            'saved' => $request->query('saved') === '1',
            'error' => (string) $request->query('error', ''),
        ]));
    }

    /**
     * Expand every global reference into the content it points at, tagging the
     * result so the save path knows what to collapse again. A section becomes
     * its shared rows; a global block becomes the shared block's own type and
     * settings, so it edits exactly like the block it came from.
     *
     * Works on the array form: the editor only needs plain data, and round
     * tripping through the models would drop fields the editor should keep.
     *
     * @return array<string, mixed>
     */
    private function hydrate(Layout $layout): array
    {
        $data = $layout->toArray();
        $library = $this->globals;
        if ($library === null) {
            return $data;
        }

        $sections = [];
        foreach (is_array($data['sections'] ?? null) ? $data['sections'] : [] as $section) {
            if (!is_array($section)) {
                continue;
            }
            $sections[] = $this->hydrateSection($section, $library);
        }
        $data['sections'] = $sections;

        return $data;
    }

    /**
     * @param array<string, mixed> $section
     *
     * @return array<string, mixed>
     */
    private function hydrateSection(array $section, GlobalLibrary $library): array
    {
        $name = trim((string) ($section['settings']['global'] ?? ''));

        if ($name === '') {
            $section['rows'] = $this->hydrateRows($section['rows'] ?? [], $library);

            return $section;
        }

        $shared = $library->section($name);
        if ($shared === null) {
            return $section;
        }

        $sharedData = $shared->toArray();
        $settings = is_array($sharedData['settings'] ?? null) ? $sharedData['settings'] : [];

        // Local overrides win, matching how the renderer resolves a band.
        foreach (is_array($section['settings'] ?? null) ? $section['settings'] : [] as $key => $value) {
            if ($key !== 'global' && $value !== '' && $value !== null) {
                $settings[$key] = $value;
            }
        }
        if (($settings['role'] ?? '') === '') {
            $settings['role'] = $shared->get('role', '');
        }
        // The marker is what tells the save path to collapse this back into a
        // reference instead of storing the shared rows on the page.
        $settings['global'] = $name;

        $merged = $section;
        $merged['settings'] = $settings;
        $merged['rows'] = $this->hydrateRows($sharedData['rows'] ?? [], $library);
        if (($merged['title'] ?? '') === '' && ($sharedData['title'] ?? '') !== '') {
            $merged['title'] = $sharedData['title'];
        }
        $class = Structure::classes((string) ($sharedData['class'] ?? ''), (string) ($section['class'] ?? ''));
        if ($class !== '') {
            $merged['class'] = $class;
        } else {
            unset($merged['class']);
        }

        return $merged;
    }

    /**
     * @param mixed $rows
     *
     * @return list<array<string, mixed>>
     */
    private function hydrateRows(mixed $rows, GlobalLibrary $library): array
    {
        $out = [];
        foreach (is_array($rows) ? $rows : [] as $row) {
            if (!is_array($row)) {
                continue;
            }

            $rowName = trim((string) ($row['settings']['global'] ?? ''));
            if ($rowName !== '') {
                $sharedRow = $library->row($rowName);
                if ($sharedRow !== null) {
                    $row = $this->hydrateRow($row, $sharedRow, $rowName);
                }
            }

            foreach (is_array($row['columns'] ?? null) ? $row['columns'] : [] as $ci => $column) {
                if (!is_array($column)) {
                    continue;
                }

                $columnName = trim((string) ($column['settings']['global'] ?? ''));
                if ($columnName !== '') {
                    $sharedColumn = $library->column($columnName);
                    if ($sharedColumn !== null) {
                        $column = $this->hydrateColumn($column, $sharedColumn, $columnName);
                        $row['columns'][$ci] = $column;
                    }
                }

                foreach (is_array($column['blocks'] ?? null) ? $column['blocks'] : [] as $bi => $block) {
                    if (!is_array($block) || ($block['type'] ?? '') !== GlobalBlockType::NAME) {
                        continue;
                    }
                    $replacement = $this->hydrateBlock($block, $library);
                    if ($replacement !== null) {
                        $row['columns'][$ci]['blocks'][$bi] = $replacement;
                    }
                }
            }
            $out[] = $row;
        }

        return $out;
    }

    /**
     * A shared row wearing the reference's own id/title/class, marked with the
     * name so the save path can collapse it back.
     *
     * @param array<string, mixed> $row
     *
     * @return array<string, mixed>
     */
    private function hydrateRow(array $row, Row $shared, string $name): array
    {
        $sharedData = $shared->toArray();
        $settings = is_array($sharedData['settings'] ?? null) ? $sharedData['settings'] : [];
        foreach (is_array($row['settings'] ?? null) ? $row['settings'] : [] as $key => $value) {
            if ($key !== 'global' && $value !== '' && $value !== null) {
                $settings[$key] = $value;
            }
        }
        $settings['global'] = $name;

        $merged = $sharedData;
        $merged['settings'] = $settings;
        if (isset($row['id'])) {
            $merged['id'] = $row['id'];
        }
        if (($row['title'] ?? '') !== '') {
            $merged['title'] = $row['title'];
        }
        $class = Structure::classes((string) ($sharedData['class'] ?? ''), (string) ($row['class'] ?? ''));
        if ($class !== '') {
            $merged['class'] = $class;
        } else {
            unset($merged['class']);
        }

        return $merged;
    }

    /**
     * A shared column wearing the reference's own id/title/class. The width is
     * the reference's, so one shared column can sit at a different share of
     * different rows.
     *
     * @param array<string, mixed> $column
     *
     * @return array<string, mixed>
     */
    private function hydrateColumn(array $column, Column $shared, string $name): array
    {
        $sharedData = $shared->toArray();
        $settings = is_array($sharedData['settings'] ?? null) ? $sharedData['settings'] : [];
        foreach (is_array($column['settings'] ?? null) ? $column['settings'] : [] as $key => $value) {
            if ($key !== 'global' && $value !== '' && $value !== null) {
                $settings[$key] = $value;
            }
        }
        $settings['global'] = $name;

        $merged = $sharedData;
        $merged['settings'] = $settings;
        if (isset($column['width'])) {
            $merged['width'] = (int) $column['width'];
        }
        if (isset($column['id'])) {
            $merged['id'] = $column['id'];
        }
        if (($column['title'] ?? '') !== '') {
            $merged['title'] = $column['title'];
        }
        $class = Structure::classes((string) ($sharedData['class'] ?? ''), (string) ($column['class'] ?? ''));
        if ($class !== '') {
            $merged['class'] = $class;
        } else {
            unset($merged['class']);
        }

        return $merged;
    }

    /**
     * The shared block, wearing the reference's own id/title/class and carrying
     * the reference name so saving can put the reference back.
     *
     * @param array<string, mixed> $block
     *
     * @return array<string, mixed>|null
     */
    private function hydrateBlock(array $block, GlobalLibrary $library): ?array
    {
        $name = trim((string) ($block['settings']['name'] ?? ''));
        $shared = $name === '' ? null : $library->block($name);
        if ($shared === null) {
            return null;
        }

        $sharedData = $shared->toArray();
        $settings = is_array($sharedData['settings'] ?? null) ? $sharedData['settings'] : [];
        $settings['global'] = $name;

        $merged = $sharedData;
        $merged['settings'] = $settings;
        if (isset($block['id'])) {
            $merged['id'] = $block['id'];
        }
        if (($block['title'] ?? '') !== '') {
            $merged['title'] = $block['title'];
        }
        $class = Structure::classes((string) ($sharedData['class'] ?? ''), (string) ($block['class'] ?? ''));
        if ($class !== '') {
            $merged['class'] = $class;
        } else {
            unset($merged['class']);
        }

        return $merged;
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

        $library = $this->globals;
        if ($library === null) {
            return Response::json(['error' => 'Модуль не загружен.'], 500);
        }

        // Promotion is the only global action the editor needs: shared content is
        // edited in place and written back by the normal save path, so there is
        // no separate "save the global" request.
        return $this->promote($request, $library);
    }

    /**
     * One global's stored structure, so a section dropped onto the canvas can be
     * expanded on the spot instead of waiting for a page reload.
     */
    private function globalApi(Request $request): Response
    {
        if (!$this->isAdmin()) {
            return Response::json(['error' => 'Требуется вход.'], 401);
        }

        $kind = $this->promotionKind($request);
        $name = (string) $request->query('name', '');
        $library = $this->globals;
        if ($library === null) {
            return Response::json(['error' => 'Модуль не загружен.'], 500);
        }

        $structure = $library->structure($kind, $name);
        if ($structure === null) {
            return Response::json(['error' => 'Глобальный элемент не найден.'], 404);
        }

        return Response::json(['kind' => $kind, 'name' => $name, 'structure' => $structure]);
    }

    /**
     * Copy one section or block out of a page layout into the library and answer
     * with the name the editor should reference. The editor then replaces its
     * local copy with that reference, so the page stops carrying a duplicate.
     */
    private function promote(Request $request, GlobalLibrary $library): Response
    {
        $kind = $this->promotionKind($request);
        $title = trim((string) $request->input('title', ''));
        $node = $this->promotionNode($request);

        $payload = $node !== null
            ? $this->promotionPayloadFromNode($kind, $node)
            : $this->promotionPayloadFromLayout($request, $kind);
        if ($payload instanceof Response) {
            return $payload;
        }
        [$body, $fallback] = $payload;

        $name = $library->uniqueName($kind, $title !== '' ? $title : $fallback);
        $library->save($kind, $name, $title !== '' ? $title : $name, [$kind => $body]);

        // Answer with what was stored, in the same shape as the read endpoint.
        // The editor swaps its local copy for a reference right away, and a
        // reference without content would read as an empty global on the next
        // save.
        return Response::json([
            'name' => $name,
            'kind' => $kind,
            'title' => $title !== '' ? $title : $name,
            'structure' => $library->structure($kind, $name) ?? [],
        ]);
    }

    private function promotionKind(Request $request): string
    {
        $kind = (string) $request->input('kind', GlobalLibrary::SECTION);

        return in_array($kind, [GlobalLibrary::SECTION, GlobalLibrary::ROW, GlobalLibrary::COLUMN, GlobalLibrary::BLOCK], true)
            ? $kind
            : GlobalLibrary::SECTION;
    }

    /**
     * Store the node the editor sent. The node stands alone, so the indexes in
     * the request name a position in the full canvas and are meaningless here.
     *
     * @param array<string, mixed> $node
     *
     * @return array{0: array<string, mixed>, 1: string}|Response
     */
    private function promotionPayloadFromNode(string $kind, array $node): array|Response
    {
        $grid = $this->gridColumns();

        if ($kind === GlobalLibrary::SECTION) {
            $section = $this->sanitizeSection($node, $grid);
            if ($section === null) {
                return Response::json(['error' => 'Секция не найдена.'], 422);
            }

            return [$section->toArray(), (string) $section->get('title', 'section')];
        }

        if ($kind === GlobalLibrary::ROW) {
            $row = $this->sanitizeRow($node, $grid);
            if ($row === null) {
                return Response::json(['error' => 'Ряд не найден.'], 422);
            }

            return [$row->toArray(), $row->title() !== '' ? $row->title() : 'row'];
        }

        if ($kind === GlobalLibrary::COLUMN) {
            $column = $this->sanitizeColumn($node, $grid);
            if ($column === null) {
                return Response::json(['error' => 'Колонка не найдена.'], 422);
            }

            return [$column->toArray(), $column->title() !== '' ? $column->title() : 'column'];
        }

        $block = $this->sanitizeBlock($node);
        if ($block === null) {
            return Response::json(['error' => 'Элемент не найден.'], 422);
        }

        return [$block->toArray(), $block->type];
    }

    /**
     * Older clients post the whole canvas plus an index or a path instead of the
     * node itself. Resolve it the same way, from the layout.
     *
     * @return array{0: array<string, mixed>, 1: string}|Response
     */
    private function promotionPayloadFromLayout(Request $request, string $kind): array|Response
    {
        $layout = $this->sanitize($this->readLayout($request), $this->gridColumns());

        if ($kind === GlobalLibrary::SECTION) {
            $section = $layout->sections()[(int) $request->input('index', -1)] ?? null;
            if ($section === null) {
                return Response::json(['error' => 'Секция не найдена.'], 422);
            }

            return [$section->toArray(), (string) $section->get('title', 'section')];
        }

        // Current path is section:row:column:block. A three-part path comes from
        // layouts saved before rows existed, so it names row 0.
        $path = array_map('intval', explode(':', (string) $request->input('block_index', '0:0:0:0')));
        if (count($path) >= 4) {
            [$si, $ri, $ci, $bi] = $path;
        } else {
            $si = $path[0] ?? 0;
            $ri = 0;
            $ci = $path[1] ?? 0;
            $bi = $path[2] ?? 0;
        }

        $row = $layout->sections()[$si]?->rows()[$ri] ?? null;
        if ($row === null) {
            return Response::json(['error' => 'Ряд не найден.'], 422);
        }

        if ($kind === GlobalLibrary::ROW) {
            return [$row->toArray(), $row->title() !== '' ? $row->title() : 'row'];
        }

        $column = $row->columns()[$ci] ?? null;
        if ($column === null) {
            return Response::json(['error' => 'Колонка не найдена.'], 422);
        }

        if ($kind === GlobalLibrary::COLUMN) {
            return [$column->toArray(), $column->title() !== '' ? $column->title() : 'column'];
        }

        $block = $column->blocks()[$bi] ?? null;
        if ($block === null) {
            return Response::json(['error' => 'Элемент не найден.'], 422);
        }

        return [$block->toArray(), $block->type];
    }

    /**
     * The single section or block the editor is promoting, or null when the
     * request carries a whole canvas instead. Sending one node keeps the
     * request small and stops unrelated parts of the canvas from being copied.
     *
     * @return array<string, mixed>|null
     */
    private function promotionNode(Request $request): ?array
    {
        $payload = $request->input('payload', null);
        if (is_string($payload) && $payload !== '') {
            $payload = json_decode($payload, true);
        }

        return is_array($payload) && $payload !== [] ? $payload : null;
    }

    private function gridColumns(): int
    {
        return (int) $this->requireApp()->config->get('builder.grid_columns', 12);
    }

    /**
     * The layout a new page starts with: a header section and one empty body
     * section. The header is a normal section, so it can be edited or deleted
     * like any other.
     *
     * @return array<string, mixed>
     */
    private function defaultLayout(string $type): array
    {
        $configured = $this->requireApp()->config->get('builder.defaults.' . $type, null);
        if (is_array($configured) && $configured !== []) {
            return $configured;
        }

        $presets = [];
        foreach ($this->presets() as $preset) {
            $presets[$preset['name']] = $preset;
        }

        $sections = [];
        foreach (['header', 'one'] as $name) {
            $preset = $presets[$name] ?? null;
            if ($preset === null) {
                continue;
            }
            $sections[] = $this->sectionFromPreset($preset);
        }

        return (new Layout($this->sanitizeSections($sections), $this->gridColumns()))->toArray();
    }

    /**
     * Turn a preset into a section payload, which is what the editor and the
     * layout model both read.
     *
     * @param array<string, mixed> $preset
     *
     * @return array<string, mixed>
     */
    private function sectionFromPreset(array $preset): array
    {
        $blocks = is_array($preset['blocks'] ?? null) ? $preset['blocks'] : [];
        $columns = [];
        foreach ($preset['widths'] ?? [$this->gridColumns()] as $ci => $width) {
            $columnBlocks = [];
            foreach ($blocks[$ci] ?? [] as $entry) {
                if (is_array($entry) && isset($entry['type'])) {
                    $columnBlocks[] = $entry;
                }
            }
            $columns[] = ['width' => (int) $width, 'blocks' => $columnBlocks];
        }

        $settings = is_array($preset['settings'] ?? null) ? $preset['settings'] : [];
        if (($preset['role'] ?? '') !== '') {
            $settings['role'] = (string) $preset['role'];
        }

        return ['settings' => $settings, 'rows' => [['columns' => $columns]]];
    }

    /**
     * @param list<array<string, mixed>> $sections
     *
     * @return list<Section>
     */
    private function sanitizeSections(array $sections): array
    {
        $clean = [];
        foreach ($sections as $entry) {
            $section = $this->sanitizeSection($entry, $this->gridColumns());
            if ($section !== null) {
                $clean[] = $section;
            }
        }

        return $clean;
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
     * Ready-made body sections ("presets"). Each carries a name, a title and the
     * column widths of its first row.
     *
     * A preset is plain data: widths become one row, so nothing here needs a
     * dedicated particle type. Site chrome is not a preset — a header or footer
     * is a global section, offered from the same menu by the editor.
     *
     * @return list<array{name: string, title: string, widths: list<int>, icon?: string}>
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
        // Shared bands and blocks were edited in place, so their new content goes
        // to the library and the page keeps only a reference to it.
        $data['layout'] = $this->collapse($layout->toArray());
        $app->fs->writeYaml('content/' . $safe . '/item.yaml', $data);
        $app->build();

        return Response::redirect('/admin/builder?id=' . rawurlencode($id) . '&saved=1', 303);
    }

    /**
     * The inverse of hydrate(): write every expanded global back to the library
     * and leave a reference behind. Editing a shared band on one page therefore
     * updates it everywhere, which is the whole point of a global.
     *
     * @param array<string, mixed> $layout
     *
     * @return array<string, mixed>
     */
    private function collapse(array $layout): array
    {
        $library = $this->globals;
        if ($library === null || !is_array($layout['sections'] ?? null)) {
            return $layout;
        }

        $sections = [];
        foreach ($layout['sections'] as $section) {
            if (!is_array($section)) {
                continue;
            }
            $sections[] = $this->collapseSection($section, $library);
        }
        $layout['sections'] = $sections;

        return $layout;
    }

    /**
     * @param array<string, mixed> $section
     *
     * @return array<string, mixed>
     */
    private function collapseSection(array $section, GlobalLibrary $library): array
    {
        $name = trim((string) ($section['settings']['global'] ?? ''));
        if ($name === '') {
            $section['rows'] = $this->collapseRows($section['rows'] ?? [], $library);

            return $section;
        }

        $settings = is_array($section['settings'] ?? null) ? $section['settings'] : [];
        unset($settings['global']);
        $rows = $this->collapseRows($section['rows'] ?? [], $library);

        // A reference that arrives with no rows says nothing about the shared
        // content: it means the editor never expanded it. Writing it back would
        // empty the band and drop settings such as its role, so leave the stored
        // global alone.
        if ($rows === []) {
            $existing = $library->section($name);
            if ($existing !== null && $existing->rows() !== []) {
                $reference = ['settings' => ['global' => $name]];
                if (isset($section['id'])) {
                    $reference['id'] = $section['id'];
                }

                return $reference;
            }
        }

        $payload = ['settings' => $settings, 'rows' => $rows];
        if (($section['title'] ?? '') !== '') {
            $payload['title'] = $section['title'];
        }
        if (($section['class'] ?? '') !== '') {
            $payload['class'] = $section['class'];
        }
        $library->save(
            GlobalLibrary::SECTION,
            $name,
            (string) ($section['title'] ?? ''),
            ['section' => $payload],
        );

        $reference = ['settings' => ['global' => $name]];
        if (isset($section['id'])) {
            $reference['id'] = $section['id'];
        }

        return $reference;
    }

    /**
     * @param mixed $rows
     *
     * @return list<array<string, mixed>>
     */
    private function collapseRows(mixed $rows, GlobalLibrary $library): array
    {
        $out = [];
        foreach (is_array($rows) ? $rows : [] as $row) {
            if (!is_array($row)) {
                continue;
            }

            $rowName = trim((string) ($row['settings']['global'] ?? ''));
            $expandedRow = $rowName === '' ? null : ($library->row($rowName)?->columns() ?? []);
            foreach (is_array($row['columns'] ?? null) ? $row['columns'] : [] as $ci => $column) {
                if (!is_array($column)) {
                    continue;
                }

                $name = trim((string) ($column['settings']['global'] ?? ''));
                if ($name !== '') {
                    $column = $this->collapseColumn($column, $name, $library);
                    $row['columns'][$ci] = $column;
                }

                foreach (is_array($column['blocks'] ?? null) ? $column['blocks'] : [] as $bi => $block) {
                    if (!is_array($block)) {
                        continue;
                    }
                    $blockName = trim((string) ($block['settings']['global'] ?? ''));
                    if ($blockName === '') {
                        continue;
                    }
                    $row['columns'][$ci]['blocks'][$bi] = $this->collapseBlock($block, $blockName, $library);
                }
            }

            if ($rowName !== '') {
                $row = $this->collapseRow($row, $rowName, $expandedRow !== [], $library);
            }

            $out[] = $row;
        }

        return $out;
    }

    /**
     * Write a shared row back and leave a reference. A reference that arrives
     * without columns was never expanded, so the stored row is left alone
     * instead of being emptied.
     *
     * @param array<string, mixed> $row
     *
     * @return array<string, mixed>
     */
    private function collapseRow(array $row, string $name, bool $wasExpanded, GlobalLibrary $library): array
    {
        $settings = is_array($row['settings'] ?? null) ? $row['settings'] : [];
        unset($settings['global']);
        $columns = is_array($row['columns'] ?? null) ? $row['columns'] : [];

        if ($columns === [] && $wasExpanded) {
            return $this->rowReference($row, $name);
        }

        $payload = ['settings' => $settings, 'columns' => $columns];
        if (($row['title'] ?? '') !== '') {
            $payload['title'] = $row['title'];
        }
        if (($row['class'] ?? '') !== '') {
            $payload['class'] = $row['class'];
        }
        $library->save(GlobalLibrary::ROW, $name, (string) ($row['title'] ?? ''), ['row' => $payload]);

        return $this->rowReference($row, $name);
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return array<string, mixed>
     */
    private function rowReference(array $row, string $name): array
    {
        $reference = ['settings' => ['global' => $name], 'columns' => []];
        if (isset($row['id'])) {
            $reference['id'] = $row['id'];
        }

        return $reference;
    }

    /**
     * Write a shared column back and leave a reference. The width stays on the
     * reference, because it belongs to the row the column sits in, not to the
     * shared content.
     *
     * @param array<string, mixed> $column
     *
     * @return array<string, mixed>
     */
    private function collapseColumn(array $column, string $name, GlobalLibrary $library): array
    {
        $settings = is_array($column['settings'] ?? null) ? $column['settings'] : [];
        unset($settings['global']);

        $payload = ['settings' => $settings, 'blocks' => is_array($column['blocks'] ?? null) ? $column['blocks'] : []];
        if (($column['title'] ?? '') !== '') {
            $payload['title'] = $column['title'];
        }
        if (($column['class'] ?? '') !== '') {
            $payload['class'] = $column['class'];
        }
        $library->save(GlobalLibrary::COLUMN, $name, (string) ($column['title'] ?? ''), ['column' => $payload]);

        $reference = ['settings' => ['global' => $name], 'width' => (int) ($column['width'] ?? 0), 'blocks' => []];
        if (isset($column['id'])) {
            $reference['id'] = $column['id'];
        }

        return $reference;
    }

    /**
     * @param array<string, mixed> $block
     *
     * @return array<string, mixed>
     */
    private function collapseBlock(array $block, string $name, GlobalLibrary $library): array
    {
        $settings = is_array($block['settings'] ?? null) ? $block['settings'] : [];
        unset($settings['global']);

        $payload = ['type' => (string) ($block['type'] ?? ''), 'settings' => $settings];
        if (($block['title'] ?? '') !== '') {
            $payload['title'] = $block['title'];
        }
        if (($block['class'] ?? '') !== '') {
            $payload['class'] = $block['class'];
        }
        $library->save(
            GlobalLibrary::BLOCK,
            $name,
            (string) ($block['title'] ?? ''),
            ['block' => $payload],
        );

        $reference = ['type' => GlobalBlockType::NAME, 'settings' => ['name' => $name]];
        if (isset($block['id'])) {
            $reference['id'] = $block['id'];
        }

        return $reference;
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

        $rows = [];
        foreach (is_array($raw['rows'] ?? null) ? $raw['rows'] : [] as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $row = $this->sanitizeRow($entry, $columns);
            if ($row !== null) {
                $rows[] = $row;
            }
        }

        // Sections written before rows existed carry a flat "columns" list.
        if ($rows === [] && isset($raw['columns']) && is_array($raw['columns'])) {
            $legacy = $this->sanitizeRow(['columns' => $raw['columns']], $columns);
            if ($legacy !== null) {
                $rows[] = $legacy;
            }
        }

        // A section given as a flat block list keeps working.
        if ($rows === [] && isset($raw['blocks']) && is_array($raw['blocks'])) {
            $legacy = $this->sanitizeRow(['blocks' => $raw['blocks']], $columns);
            if ($legacy !== null) {
                $rows[] = $legacy;
            }
        }

        $settings = $this->sanitizeSettings($raw['settings'] ?? []);
        $id = isset($raw['id']) && is_string($raw['id']) ? $raw['id'] : null;
        $title = isset($raw['title']) && is_string($raw['title']) ? trim($raw['title']) : '';
        $class = isset($raw['class']) && is_string($raw['class']) ? trim($raw['class']) : '';

        // A section that only points at a global keeps no rows of its own.
        $isGlobalRef = trim((string) ($settings['global'] ?? '')) !== '';
        if ($rows === [] && !$isGlobalRef) {
            return null;
        }

        return new Section($rows, $settings, $id, $title, $class);
    }

    private function sanitizeRow(mixed $raw, int $columns): ?Row
    {
        if (!is_array($raw)) {
            return null;
        }

        $rowColumns = [];
        foreach (is_array($raw['columns'] ?? null) ? $raw['columns'] : [] as $column) {
            if (!is_array($column)) {
                continue;
            }
            $clean = $this->sanitizeColumn($column, $columns);
            if ($clean !== null) {
                $rowColumns[] = $clean;
            }
        }

        // A row given as a flat block list gets one full-width column.
        if ($rowColumns === [] && isset($raw['blocks']) && is_array($raw['blocks'])) {
            $blocks = [];
            foreach ($raw['blocks'] as $entry) {
                if (is_array($entry)) {
                    $block = $this->sanitizeBlock($entry);
                    if ($block !== null) {
                        $blocks[] = $block;
                    }
                }
            }
            $rowColumns[] = new Column($blocks, $columns);
        }

        $settings = $this->sanitizeSettings($raw['settings'] ?? []);
        $id = isset($raw['id']) && is_string($raw['id']) ? $raw['id'] : null;
        $title = isset($raw['title']) && is_string($raw['title']) ? trim($raw['title']) : '';
        $class = isset($raw['class']) && is_string($raw['class']) ? trim($raw['class']) : '';

        // A row that only points at a global keeps no columns of its own, the
        // same way a global section reference keeps no rows.
        $isGlobalRef = trim((string) ($settings['global'] ?? '')) !== '';
        if ($rowColumns === [] && !$isGlobalRef) {
            return null;
        }

        return new Row($rowColumns, $settings, $id, $title, $class);
    }

    private function sanitizeColumn(mixed $raw, int $columns): ?Column
    {
        if (!is_array($raw)) {
            return null;
        }

        $blocks = [];
        foreach (is_array($raw['blocks'] ?? null) ? $raw['blocks'] : [] as $entry) {
            if (is_array($entry)) {
                $block = $this->sanitizeBlock($entry);
                if ($block !== null) {
                    $blocks[] = $block;
                }
            }
        }

        $width = isset($raw['width']) ? max(0, min($columns, (int) $raw['width'])) : 0;
        $settings = $this->sanitizeSettings($raw['settings'] ?? []);
        $id = isset($raw['id']) && is_string($raw['id']) ? $raw['id'] : null;
        $title = isset($raw['title']) && is_string($raw['title']) ? trim($raw['title']) : '';
        $class = isset($raw['class']) && is_string($raw['class']) ? trim($raw['class']) : '';

        return new Column($blocks, $width, $settings, $id, $title, $class);
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
        $title = isset($entry['title']) && is_string($entry['title']) ? trim($entry['title']) : '';
        $class = isset($entry['class']) && is_string($entry['class']) ? trim($entry['class']) : '';

        return new Block($type, $settings, $id, $title, $class);
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
