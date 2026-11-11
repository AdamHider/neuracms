<?php

declare(strict_types=1);

namespace FlatCms\Admin;

use FlatCms\Content\Item;
use FlatCms\Support\Fs;

/**
 * Writes content back to disk. Every save keeps a copy of the previous state in
 * var/history/ so edits can be inspected or restored.
 */
final class ContentWriter
{
    public function __construct(
        private readonly Fs $fs,
        private readonly string $base = 'content',
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public function save(string $id, array $data, string $body): bool
    {
        $id = $this->normalize($id);
        if ($id === null) {
            return false;
        }

        $this->snapshot($id);

        $dir = $this->dir($id);
        $this->fs->ensureDir($dir);

        $this->fs->writeYaml($dir . '/item.yaml', $data);
        if ($body !== '' || $this->fs->isFile($dir . '/content.md')) {
            $this->fs->write($dir . '/content.md', $body);
        }

        return true;
    }

    public function delete(string $id): bool
    {
        $id = $this->normalize($id);
        if ($id === null || $id === '') {
            return false;
        }

        $this->snapshot($id);

        return $this->fs->deleteDir($this->dir($id));
    }

    /**
     * Move an item to a new id. Refuses when the target already exists.
     */
    public function move(string $from, string $to): bool
    {
        $from = $this->normalize($from);
        $to = $this->normalize($to);
        if ($from === null || $to === null || $from === '' || $to === '' || $from === $to) {
            return false;
        }

        if ($this->fs->isDir($this->dir($to))) {
            return false;
        }

        return $this->fs->move($this->dir($from), $this->dir($to));
    }

    public function load(string $id): ?Item
    {
        $id = $this->normalize($id);
        if ($id === null) {
            return null;
        }

        $dir = $this->dir($id);
        if (!$this->fs->isDir($dir)) {
            return null;
        }

        return new Item($id, $this->fs->readYaml($dir . '/item.yaml', []), $this->fs->read($dir . '/content.md'));
    }

    /**
     * @return list<array{id: string, saved_at: string}>
     */
    public function history(string $id): array
    {
        $id = $this->normalize($id);
        if ($id === null || $id === '') {
            return [];
        }

        $dir = 'var/history/' . str_replace('/', '__', $id);
        $entries = [];
        foreach ($this->fs->listDir($dir, true) as $stamp) {
            if ($this->fs->isDir($dir . '/' . $stamp)) {
                $entries[] = ['id' => $stamp, 'saved_at' => $stamp];
            }
        }

        rsort($entries);

        return $entries;
    }

    /**
     * Copy the current item folder into the history tree before it changes.
     */
    private function snapshot(string $id): void
    {
        if ($id === '') {
            return;
        }

        $dir = $this->dir($id);
        if (!$this->fs->isDir($dir)) {
            return;
        }

        $target = 'var/history/' . str_replace('/', '__', $id) . '/' . date('Ymd-His') . '-' . bin2hex(random_bytes(3));
        $this->fs->copyDir($dir, $target);
    }

    private function dir(string $id): string
    {
        return $id === '' ? $this->base : $this->base . '/' . $id;
    }

    private function normalize(string $id): ?string
    {
        $id = trim(str_replace('\\', '/', $id), '/');
        if ($id === '') {
            return '';
        }

        return $this->fs->safePath($this->base, $id);
    }
}
