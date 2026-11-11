<?php

declare(strict_types=1);

namespace FlatCms\Modules\Builder;

use FlatCms\App;
use FlatCms\Content\Item;
use FlatCms\Contracts\Module;
use FlatCms\Core\Request;
use FlatCms\Core\Response;
use FlatCms\Modules\Builder\Types\ButtonType;
use FlatCms\Modules\Builder\Types\ColumnsType;
use FlatCms\Modules\Builder\Types\HeadingType;
use FlatCms\Modules\Builder\Types\HtmlType;
use FlatCms\Modules\Builder\Types\ImageType;
use FlatCms\Modules\Builder\Types\ProductsType;
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
        $app->renderer->setLayoutResolver(function (mixed $item) use ($app): string {
            if (!$item instanceof Item) {
                return '';
            }

            return $this->renderer?->render($item) ?? '';
        });

        $router = $app->router;
        $router->add('GET', '/admin/builder', fn (Request $r) => $this->editor($r));
        $router->add('POST', '/admin/builder/save', fn (Request $r) => $this->save($r));
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
            new ButtonType(),
            new ColumnsType(),
            new ProductsType(),
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

        return Response::json(['types' => $types]);
    }

    private function editor(Request $request): Response
    {
        if (!$this->isAdmin()) {
            return Response::redirect('/admin/login', 302);
        }

        $app = $this->requireApp();
        $id = (string) $request->query('id', '');
        if ($id === '') {
            return Response::redirect('/admin/content', 302);
        }

        $item = $app->storage->load($id);
        if ($item === null) {
            return Response::html($app->renderer->renderTemplate('admin/404', ['title' => 'Не найдено']), 404);
        }

        $layout = Layout::fromValue($item->get('layout'), (int) $app->config->get('builder.grid_columns', 12));

        return Response::html($app->renderer->renderTemplate('admin/builder', [
            'item' => $item,
            'id' => $id,
            'layout' => $layout,
            'types' => $this->types?->all() ?? [],
            'saved' => $request->query('saved') === '1',
            'error' => (string) $request->query('error', ''),
        ]));
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

        $raw = $request->input('layout_json', null);
        if (is_string($raw) && $raw !== '') {
            $decoded = json_decode($raw, true);
            $raw = is_array($decoded) ? $decoded : [];
        } else {
            $raw = $request->input('layout', []);
        }

        $layout = $this->sanitize($raw, (int) $app->config->get('builder.grid_columns', 12));

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
     * Rebuild a Layout from request data, dropping unknown block types and
     * normalising settings to scalars so nothing unsafe reaches disk.
     */
    private function sanitize(mixed $raw, int $columns): Layout
    {
        if (!is_array($raw)) {
            return new Layout([], null, $columns);
        }

        $blocks = $raw['blocks'] ?? $raw;
        $grid = $raw['grid'] ?? null;

        $clean = [];
        if (is_array($blocks)) {
            foreach ($blocks as $entry) {
                if (!is_array($entry)) {
                    continue;
                }
                $type = (string) ($entry['type'] ?? '');
                if ($type === '' || !($this->types?->has($type) ?? false)) {
                    continue;
                }
                $settings = $this->sanitizeSettings($entry['settings'] ?? []);
                $clean[] = new Block($type, $settings, isset($entry['id']) ? (string) $entry['id'] : null);
            }
        }

        return Layout::fromValue(['blocks' => array_map(static fn (Block $b): array => $b->toArray(), $clean), 'grid' => $grid], $columns);
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
