<?php

declare(strict_types=1);

namespace FlatCms\Modules\Builder;

use FlatCms\Support\Fs;

/**
 * Reusable sections and blocks ("globals"). A page can promote one of its own
 * sections or blocks here, and every other page can then insert a reference to
 * it: the header band is the obvious case, but any section works.
 *
 * Globals live outside the content tree, in content/_global/<kind>/<name>/
 * item.yaml. The leading underscore keeps them out of Storage::scan(), so they
 * never show up as pages or elements.
 */
final class GlobalLibrary
{
    public const SECTION = 'section';

    public const BLOCK = 'block';

    private const KINDS = [self::SECTION, self::BLOCK];

    public function __construct(private readonly Fs $fs, private readonly string $base = 'content/_global')
    {
    }

    /**
     * Every global of a kind, sorted by order then title.
     *
     * @return array<string, array{name: string, title: string, kind: string, role: string, order: int}>
     */
    public function all(string $kind): array
    {
        $out = [];
        foreach ($this->fs->listDir($this->dir($kind), true) as $name) {
            $data = $this->read($kind, $name);
            if ($data === null) {
                continue;
            }
            $out[$name] = [
                'name' => $name,
                'title' => (string) ($data['title'] ?? $name),
                'kind' => $kind,
                'role' => (string) ($data['section']['settings']['role'] ?? ''),
                'order' => (int) ($data['order'] ?? 0),
            ];
        }

        uasort($out, static fn (array $a, array $b): int => [$a['order'], $a['title']] <=> [$b['order'], $b['title']]);

        return $out;
    }

    /**
     * Both kinds at once, keyed by kind then name.
     *
     * @return array<string, array<string, array{name: string, title: string, kind: string, role: string, order: int}>>
     */
    public function catalog(): array
    {
        $out = [];
        foreach (self::KINDS as $kind) {
            $out[$kind] = $this->all($kind);
        }

        return $out;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function read(string $kind, string $name): ?array
    {
        $path = $this->path($kind, $name);
        if ($path === null) {
            return null;
        }

        return $this->fs->readYaml($path, []) ?: null;
    }

    /**
     * Store a section or block payload under a stable name.
     *
     * @param array<string, mixed> $payload
     */
    public function save(string $kind, string $name, string $title, array $payload): bool
    {
        $name = $this->slug($name);
        if ($name === '') {
            return false;
        }

        $existing = $this->read($kind, $name) ?? [];
        // An in-place edit posts the payload without the library title, so keep
        // the stored one instead of degrading it to the slug.
        if ($title === '') {
            $title = (string) ($existing['title'] ?? $name);
        }
        $data = ['title' => $title, 'kind' => $kind] + $payload;
        // Order and any other bookkeeping a human added live beside the payload;
        // rewriting the file from a page edit must not drop them.
        foreach ($existing as $key => $value) {
            if (!array_key_exists($key, $data)) {
                $data[$key] = $value;
            }
        }
        $this->fs->ensureDir($this->dir($kind) . '/' . $name);

        return $this->fs->writeYaml($this->base . '/' . $kind . '/' . $name . '/item.yaml', $data);
    }

    public function delete(string $kind, string $name): bool
    {
        $path = $this->path($kind, $name);
        if ($path === null) {
            return false;
        }

        return $this->fs->deleteDir(dirname($this->fs->path($path)));
    }

    public function exists(string $kind, string $name): bool
    {
        return $this->path($kind, $name) !== null;
    }

    /**
     * A stored section as a renderable Section, or null when it is missing.
     */
    public function section(string $name): ?Section
    {
        $data = $this->read(self::SECTION, $name);
        if ($data === null || !is_array($data['section'] ?? null)) {
            return null;
        }

        return Section::fromArray($this->withFileMeta($data));
    }

    public function block(string $name): ?Block
    {
        $data = $this->read(self::BLOCK, $name);
        if ($data === null || !is_array($data['block'] ?? null)) {
            return null;
        }

        $block = Block::fromArray($this->withFileMeta($data));

        return $block->type === '' ? null : $block;
    }

    /**
     * The stored payload for any kind, in the shape the editor consumes: the
     * inner node object with the library title folded in.
     *
     * @return array<string, mixed>|null
     */
    public function structure(string $kind, string $name): ?array
    {
        $data = $this->read($kind, $name);
        if ($data === null || !is_array($data[$kind] ?? null)) {
            return null;
        }

        return $this->withFileMeta($data);
    }

    /**
     * The title of a global is stored next to the payload, not inside it, so a
     * loaded section or block has to borrow it. Without this an in-place edit
     * would write the name back over the human title.
     *
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    private function withFileMeta(array $data): array
    {
        $payload = is_array($data['section'] ?? null)
            ? $data['section']
            : (is_array($data['block'] ?? null) ? $data['block'] : []);

        if (($payload['title'] ?? '') === '' && is_string($data['title'] ?? null)) {
            $payload['title'] = $data['title'];
        }

        return $payload;
    }

    /**
     * A unique, filesystem-safe name derived from a title.
     */
    public function uniqueName(string $kind, string $title, string $fallback = 'global'): string
    {
        $base = $this->slug($title);
        if ($base === '') {
            $base = $fallback;
        }

        $name = $base;
        $i = 2;
        while ($this->exists($kind, $name)) {
            $name = $base . '-' . $i;
            $i++;
        }

        return $name;
    }

    public function slug(string $value): string
    {
        $map = [
            'а' => 'a', 'б' => 'b', 'в' => 'v', 'г' => 'g', 'д' => 'd', 'е' => 'e', 'ё' => 'e',
            'ж' => 'zh', 'з' => 'z', 'и' => 'i', 'й' => 'y', 'к' => 'k', 'л' => 'l', 'м' => 'm',
            'н' => 'n', 'о' => 'o', 'п' => 'p', 'р' => 'r', 'с' => 's', 'т' => 't', 'у' => 'u',
            'ф' => 'f', 'х' => 'h', 'ц' => 'c', 'ч' => 'ch', 'ш' => 'sh', 'щ' => 'sch',
            'ъ' => '', 'ы' => 'y', 'ь' => '', 'э' => 'e', 'ю' => 'yu', 'я' => 'ya',
        ];
        $value = mb_strtolower(trim($value));
        $value = strtr($value, $map);
        $value = (string) preg_replace('/[^a-z0-9]+/', '-', $value);

        return trim($value, '-');
    }

    private function path(string $kind, string $name): ?string
    {
        if (!in_array($kind, self::KINDS, true)) {
            return null;
        }
        $name = $this->slug($name);
        if ($name === '') {
            return null;
        }

        $relative = $this->base . '/' . $kind . '/' . $name . '/item.yaml';

        return $this->fs->isFile($relative) ? $relative : null;
    }

    private function dir(string $kind): string
    {
        return $this->base . '/' . $kind;
    }
}
