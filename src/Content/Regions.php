<?php

declare(strict_types=1);

namespace FlatCms\Content;

use FlatCms\Contracts\Subsystem;
use FlatCms\Support\Fs;

/**
 * Theme regions. A region is a named slot of the site chrome (header, footer,
 * …) whose contents are a builder layout, exactly like a page's "layout". The
 * layout of each region is stored in its own file under config/regions/ so it is
 * independent of the content tree and survives a content reset.
 */
final class Regions implements Subsystem
{
    /** Built-in regions every theme may render. */
    public const DEFAULTS = [
        'header' => 'Шапка сайта',
        'footer' => 'Подвал сайта',
    ];

    public function __construct(private readonly Fs $fs)
    {
    }

    public function boot(\FlatCms\App $app): void
    {
    }

    public function build(): void
    {
    }

    /**
     * Region name => human label. Built-ins plus any config/regions/*.yaml file
     * that carries a "label".
     *
     * @return array<string, string>
     */
    public function all(): array
    {
        $regions = self::DEFAULTS;
        foreach ($this->names() as $name) {
            $label = $this->fs->readYaml($this->path($name), [])['label'] ?? null;
            if (is_string($label) && $label !== '') {
                $regions[$name] = $label;
            }
        }

        return $regions;
    }

    public function has(string $name): bool
    {
        if (!$this->valid($name)) {
            return false;
        }

        return isset(self::DEFAULTS[$name]) || in_array($name, $this->names(), true);
    }

    /**
     * The builder layout of a region as a raw array, or an empty layout.
     *
     * @return array<string, mixed>
     */
    public function layout(string $name): array
    {
        if (!$this->valid($name)) {
            return [];
        }

        $data = $this->fs->readYaml($this->path($name), []);
        $layout = $data['layout'] ?? [];

        return is_array($layout) ? $layout : [];
    }

    /**
     * Store a region's layout.
     *
     * @param array<string, mixed> $layout
     */
    public function save(string $name, array $layout): bool
    {
        if (!$this->valid($name)) {
            return false;
        }

        $data = $this->fs->readYaml($this->path($name), []);
        $data['layout'] = $layout;
        if (!isset($data['label'])) {
            $data['label'] = self::DEFAULTS[$name] ?? ucfirst($name);
        }

        return $this->fs->writeYaml($this->path($name), $data);
    }

    /**
     * @return list<string>
     */
    private function names(): array
    {
        $names = [];
        foreach ($this->fs->listDir('config/regions') as $file) {
            if (str_ends_with($file, '.yaml')) {
                $names[] = substr($file, 0, -5);
            }
        }

        return $names;
    }

    private function valid(string $name): bool
    {
        return preg_match('/^[a-z][a-z0-9_-]{0,48}$/', $name) === 1;
    }

    private function path(string $name): string
    {
        return 'config/regions/' . $name . '.yaml';
    }
}
