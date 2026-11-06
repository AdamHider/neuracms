<?php

declare(strict_types=1);

namespace FlatCms\Core;

use FlatCms\Contracts\Subsystem;
use FlatCms\Support\Arr;
use FlatCms\Support\Fs;

/**
 * Configuration reader. Loads config/site.yaml and module configs.
 * Values are read through dot keys (site.title) and can be changed in memory.
 */
final class Config implements Subsystem
{
    private array $data = [];

    public function __construct(private readonly Fs $fs)
    {
    }

    public function boot(\FlatCms\App $app): void
    {
        $this->data = $this->fs->readYaml('config/site.yaml', []);
    }

    public function build(): void
    {
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return Arr::get($this->data, $key, $default);
    }

    public function set(string $key, mixed $value): void
    {
        Arr::set($this->data, $key, $value);
    }

    public function has(string $key): bool
    {
        return Arr::has($this->data, $key);
    }

    public function all(): array
    {
        return $this->data;
    }

    /**
     * Merge an additional configuration file into memory.
     */
    public function mergeFile(string $path): void
    {
        $this->data = Arr::merge($this->data, $this->fs->readYaml($path, []));
    }
}
