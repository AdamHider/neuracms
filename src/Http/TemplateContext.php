<?php

declare(strict_types=1);

namespace FlatCms\Http;

/**
 * The $this available inside templates. Exposes template helpers so plain PHP
 * templates stay short and safe.
 */
final class TemplateContext
{
    public function __construct(private readonly Renderer $renderer)
    {
    }

    public function partial(string $name, array $data = []): string
    {
        return $this->renderer->partial($name, $data);
    }

    public function url(string $path): string
    {
        return $this->renderer->url($path);
    }

    /**
     * Normalise a user-entered link (absolute URLs and anchors are kept as-is).
     */
    public function link(string $path): string
    {
        return $this->renderer->link($path);
    }

    public function asset(string $path): string
    {
        return $this->renderer->asset($path);
    }

    /**
     * Asset owned by a module or core subsystem, served at /assets/<owner>/<path>.
     */
    public function ownerAsset(string $owner, string $path): string
    {
        return $this->renderer->ownerAsset($owner, $path);
    }

    public function e(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /**
     * Render an admin icon (Bootstrap Icons) by semantic name.
     */
    public function icon(string $name, string $class = '', string $title = ''): string
    {
        return \FlatCms\Admin\Icons::render($name, $class, $title);
    }

    /**
     * Render the page builder layout for an item, if the builder module is loaded.
     */
    public function layout(mixed $item = null): string
    {
        return $this->renderer->layout($item);
    }

    /**
     * Render a theme region (header, footer, …) defined in the builder.
     */
    public function region(string $name): string
    {
        return $this->renderer->region($name);
    }
}
