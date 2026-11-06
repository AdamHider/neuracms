<?php

declare(strict_types=1);

namespace FlatCms\Content;

/**
 * Immutable content object. A page, a product and a blog post differ only by the
 * "type" field and their set of properties.
 */
final class Item
{
    public const RESERVED = [
        'type', 'title', 'slug', 'order', 'published', 'template', 'date', 'inherit', 'layout',
    ];

    private ?string $html = null;

    /**
     * @param array<string, mixed> $data
     * @param list<string>         $media
     * @param list<string>         $children
     */
    public function __construct(
        private readonly string $id,
        private readonly array $data,
        private readonly string $body = '',
        private readonly array $media = [],
        private readonly array $children = [],
    ) {
    }

    public function id(): string
    {
        return $this->id;
    }

    public function type(): string
    {
        $type = $this->data['type'] ?? 'page';

        return is_string($type) && $type !== '' ? $type : 'page';
    }

    public function title(): string
    {
        $title = $this->data['title'] ?? null;
        if (is_string($title) && $title !== '') {
            return $title;
        }

        $segments = explode('/', $this->id);
        $last = end($segments);

        return $last === '' || $last === false ? 'Home' : ucfirst(str_replace('-', ' ', $last));
    }

    public function slug(): string
    {
        $slug = $this->data['slug'] ?? null;
        if (is_string($slug) && $slug !== '') {
            return $slug;
        }

        $segments = explode('/', $this->id);

        return (string) end($segments);
    }

    public function url(): string
    {
        if ($this->id === '' || $this->id === 'index') {
            return '/';
        }

        $segments = explode('/', $this->id);
        $segments[count($segments) - 1] = $this->slug();

        return '/' . implode('/', $segments);
    }

    public function template(): ?string
    {
        $template = $this->data['template'] ?? null;

        return is_string($template) && $template !== '' ? $template : null;
    }

    public function published(): bool
    {
        return (bool) ($this->data['published'] ?? true);
    }

    public function order(): int
    {
        return (int) ($this->data['order'] ?? 0);
    }

    public function date(): ?string
    {
        $date = $this->data['date'] ?? null;

        return is_string($date) && $date !== '' ? $date : null;
    }

    public function parentId(): string
    {
        $pos = strrpos($this->id, '/');

        return $pos === false ? '' : substr($this->id, 0, $pos);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->data[$key] ?? $default;
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->data);
    }

    /**
     * All raw properties including the reserved ones.
     */
    public function all(): array
    {
        return $this->data;
    }

    /**
     * Only user properties (reserved system fields removed).
     */
    public function props(): array
    {
        return array_diff_key($this->data, array_flip(self::RESERVED));
    }

    public function body(): string
    {
        return $this->body;
    }

    public function html(): string
    {
        return $this->html ??= (new Markdown())->parse($this->body);
    }

    public function withHtml(?string $html): self
    {
        $clone = clone $this;
        $clone->html = $html;

        return $clone;
    }

    /**
     * @return list<string>
     */
    public function media(): array
    {
        return $this->media;
    }

    /**
     * @return list<string>
     */
    public function children(): array
    {
        return $this->children;
    }
}
