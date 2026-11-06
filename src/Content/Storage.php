<?php

declare(strict_types=1);

namespace FlatCms\Content;

use FlatCms\Contracts\Subsystem;
use FlatCms\Core\Events;
use FlatCms\Support\Arr;
use FlatCms\Support\Fs;

/**
 * Reads content from disk. Responsible only for turning folders into Items and
 * never caches anything.
 */
final class Storage implements Subsystem
{
    public function __construct(
        private readonly Fs $fs,
        private readonly Events $events,
        private readonly string $base = 'content',
    ) {
    }

    public function boot(\FlatCms\App $app): void
    {
    }

    public function build(): void
    {
    }

    public function base(): string
    {
        return $this->base;
    }

    /**
     * Load one item by its id (relative path). Returns null for a missing or
     * out-of-tree path.
     */
    public function load(string $id): ?Item
    {
        $id = $this->normalizeId($id);
        if ($id === null) {
            return null;
        }

        $dir = $this->dirPath($id);
        if (!$this->fs->isDir($dir)) {
            return null;
        }

        $item = $this->events->filter('item.loaded', $this->makeItem($id));

        return $item instanceof Item ? $item : null;
    }

    /**
     * Walk content/ and yield every item id (folder that contains item.yaml or
     * content.md). Folders whose name starts with "_" are skipped.
     *
     * @return iterable<string>
     */
    public function scan(): iterable
    {
        yield from $this->walk('');
    }

    /**
     * @return list<string>
     */
    public function media(string $id): array
    {
        $dir = $this->dirPath($this->normalizeId($id) ?? '');
        $files = [];
        foreach ($this->fs->listDir($dir) as $name) {
            if ($this->fs->isFile($dir . '/' . $name)
                && !in_array($name, ['item.yaml', 'content.md'], true)
                && $name[0] !== '.'
            ) {
                $files[] = $name;
            }
        }

        return $files;
    }

    public function exists(string $id): bool
    {
        $id = $this->normalizeId($id);

        return $id !== null && $this->fs->isDir($this->dirPath($id));
    }

    private function makeItem(string $id, array $seen = []): Item
    {
        $dir = $this->dirPath($id);
        $data = $this->fs->readYaml($dir . '/item.yaml', []);
        $body = $this->fs->read($dir . '/content.md');

        if (($data['inherit'] ?? false) === true && $id !== '' && !in_array($id, $seen, true)) {
            $parentId = $this->parentId($id);
            $seen[] = $id;
            if ($parentId !== null && $this->fs->isDir($this->dirPath($parentId))) {
                $parent = $this->makeItem($parentId, $seen);
                $data = Arr::merge($parent->all(), $data);
                unset($data['inherit']);
            }
        }

        return new Item(
            $id,
            $data,
            $body,
            $this->media($id),
            $this->childIds($id),
        );
    }

    /**
     * @return list<string>
     */
    private function childIds(string $id): array
    {
        $dir = $this->dirPath($id);
        $children = [];
        foreach ($this->fs->listDir($dir, true) as $name) {
            if (str_starts_with($name, '_') || str_starts_with($name, '.')) {
                continue;
            }
            $children[] = $id === '' ? $name : $id . '/' . $name;
        }

        return $children;
    }

    /**
     * @return iterable<string>
     */
    private function walk(string $id): iterable
    {
        $dir = $this->dirPath($id);
        if ($this->fs->isFile($dir . '/item.yaml') || $this->fs->isFile($dir . '/content.md')) {
            yield $id;
        }

        foreach ($this->fs->listDir($dir, true) as $name) {
            if (str_starts_with($name, '_') || str_starts_with($name, '.')) {
                continue;
            }
            $child = $id === '' ? $name : $id . '/' . $name;
            yield from $this->walk($child);
        }
    }

    private function dirPath(string $id): string
    {
        return $id === '' ? $this->base : $this->base . '/' . $id;
    }

    private function parentId(string $id): ?string
    {
        $pos = strrpos($id, '/');

        return $pos === false ? '' : substr($id, 0, $pos);
    }

    private function normalizeId(string $id): ?string
    {
        $id = trim(str_replace('\\', '/', $id), '/');
        if ($id === '') {
            return '';
        }

        return $this->fs->safePath($this->base, $id);
    }
}
