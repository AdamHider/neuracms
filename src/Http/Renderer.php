<?php

declare(strict_types=1);

namespace FlatCms\Http;

use FlatCms\Contracts\Subsystem;
use FlatCms\Content\Item;
use FlatCms\Core\Events;

/**
 * Renders items into HTML using plain PHP templates.
 *
 * Template lookup order: item template, then type, then "page".
 * A template is searched in the theme first, then in modules.
 */
final class Renderer implements Subsystem
{
    private ?string $theme = null;

    private ?\FlatCms\App $app = null;

    /** @var callable|null */
    private $layoutResolver = null;

    public function __construct(private readonly Events $events)
    {
    }

    public function boot(\FlatCms\App $app): void
    {
        $this->app = $app;
        $this->theme = (string) ($app->config->get('site.theme', 'default'));
    }

    /**
     * Module template folders are resolved on demand because modules boot after
     * the renderer does.
     *
     * @return list<string>
     */
    private function modulePaths(): array
    {
        return $this->app?->moduleTemplatePaths() ?? [];
    }

    /**
     * Core owned template folders (the admin panel). Resolved after the theme but
     * before modules, so a theme may override core views if it wants to.
     *
     * @return list<string>
     */
    private function corePaths(): array
    {
        return $this->app?->coreTemplatePaths() ?? [];
    }

    public function build(): void
    {
    }

    public function theme(): string
    {
        return $this->theme ?? 'default';
    }

    /**
     * The builder module registers a layout resolver so templates can call
     * $this->layout($item). Without the module the call returns an empty string.
     */
    public function setLayoutResolver(?callable $resolver): void
    {
        $this->layoutResolver = $resolver;
    }

    public function layout(mixed $item = null): string
    {
        if ($this->layoutResolver === null) {
            return '';
        }

        return (string) ($this->layoutResolver)($item);
    }

    public function render(Item $item, array $data = []): string
    {
        $data['item'] = $item;
        $data['site'] = $this->siteData();

        return $this->renderTemplate($this->templateName($item), $data);
    }

    public function renderTemplate(string $name, array $data = []): string
    {
        $file = $this->locate($name);
        if ($file === null) {
            return '';
        }

        $this->events->emit('render.before', $name, $data);
        $html = $this->includeTemplate($file, $data);
        $this->events->emit('render.after', $name, $html);

        return $html;
    }

    public function renderString(string $name, array $data = []): string
    {
        return $this->renderTemplate($name, $data);
    }

    /**
     * Path of a theme or module asset for use in templates.
     */
    public function asset(string $path): string
    {
        $relative = ltrim($path, '/');
        $url = '/assets/' . $this->theme() . '/' . $relative;

        return $this->versioned($url, ['themes/' . $this->theme() . '/assets/' . $relative]);
    }

    /**
     * Path of an asset owned by a named provider (a module or a core subsystem),
     * served at /assets/<owner>/<path>.
     */
    public function ownerAsset(string $owner, string $path): string
    {
        $relative = ltrim($path, '/');
        $url = '/assets/' . $owner . '/' . $relative;
        $base = $this->app?->coreAssetPath($owner) ?? 'modules/' . $owner . '/assets';

        return $this->versioned($url, [$base . '/' . $relative]);
    }

    /**
     * Append a ?v=<mtime> query so browsers refetch an asset after it changes.
     * Assets are served with a one-day cache, which otherwise hides edits.
     *
     * @param list<string> $candidates paths relative to the project root
     */
    private function versioned(string $url, array $candidates): string
    {
        foreach ($candidates as $candidate) {
            $file = $this->abs($candidate);
            if (is_file($file)) {
                return $url . '?v=' . filemtime($file);
            }
        }

        return $url;
    }

    public function url(string $path): string
    {
        return '/' . ltrim($path, '/');
    }

    public function templateName(Item $item): string
    {
        return $item->template() ?? $item->type();
    }

    public function locate(string $name): ?string
    {
        $relative = 'templates/' . $name . '.php';

        $themeFile = 'themes/' . $this->theme() . '/' . $relative;
        if (is_file($this->abs($themeFile))) {
            return $this->abs($themeFile);
        }

        foreach ($this->corePaths() as $base) {
            $file = $base . '/' . $relative;
            if (is_file($file)) {
                return $file;
            }
        }

        foreach ($this->modulePaths() as $base) {
            $file = $base . '/' . $relative;
            if (is_file($file)) {
                return $file;
            }
        }

        $fallback = $this->abs('themes/default/' . $relative);
        if (is_file($fallback)) {
            return $fallback;
        }

        return null;
    }

    public function partial(string $name, array $data = []): string
    {
        $relative = 'partials/' . $name . '.php';

        $themeFile = $this->abs('themes/' . $this->theme() . '/' . $relative);
        if (is_file($themeFile)) {
            return $this->renderFile($themeFile, $data);
        }

        foreach ($this->corePaths() as $base) {
            $file = $base . '/' . $relative;
            if (is_file($file)) {
                return $this->renderFile($file, $data);
            }
        }

        foreach ($this->modulePaths() as $base) {
            $file = $base . '/' . $relative;
            if (is_file($file)) {
                return $this->renderFile($file, $data);
            }
        }

        $fallback = $this->abs('themes/default/' . $relative);

        return is_file($fallback) ? $this->renderFile($fallback, $data) : '';
    }

    private function renderFile(string $file, array $data): string
    {
        return $this->includeTemplate($file, $data);
    }

    /**
     * Include a template file with $this bound to the TemplateContext so
     * templates can use $this->e(), $this->url() and friends.
     */
    private function includeTemplate(string $file, array $data): string
    {
        $context = new TemplateContext($this);
        $app = $this->app;
        $site = $this->siteData();

        return (function () use ($file, $data, $app, $site): string {
            extract($data, EXTR_SKIP);
            ob_start();
            include $file;

            return (string) ob_get_clean();
        })->call($context);
    }

    private function siteData(): array
    {
        return $this->events->filter('site.data', [
            'title' => (string) ($this->app?->config->get('site.title', 'FlatCMS') ?? 'FlatCMS'),
            'base_url' => (string) ($this->app?->config->get('site.base_url', '') ?? ''),
            'theme' => $this->theme(),
        ]);
    }

    private function abs(string $path): string
    {
        return defined('FLATCMS_ROOT') ? FLATCMS_ROOT . '/' . $path : $path;
    }
}
