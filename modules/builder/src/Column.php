<?php

declare(strict_types=1);

namespace FlatCms\Modules\Builder;

/**
 * One column inside a row: a grid width plus the blocks stacked in it. Columns
 * are structural, the way Gantry 5 lays out a row, and they are flex containers
 * themselves so a column can centre its blocks vertically.
 */
final class Column
{
    /**
     * @param list<Block>          $blocks
     * @param array<string, mixed> $settings
     */
    public function __construct(
        private readonly array $blocks = [],
        private readonly int $width = 0,
        private readonly array $settings = [],
        private readonly ?string $id = null,
        private readonly string $title = '',
        private readonly string $cssClass = '',
    ) {
    }

    /**
     * @return list<Block>
     */
    public function blocks(): array
    {
        return $this->blocks;
    }

    /**
     * Grid width in columns. 0 means "auto" (the editor fills the remainder).
     */
    public function width(): int
    {
        return $this->width;
    }

    /**
     * @return array<string, mixed>
     */
    public function settings(): array
    {
        return $this->settings;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->settings[$key] ?? $default;
    }

    public function title(): string
    {
        return $this->title;
    }

    public function cssClass(): string
    {
        return $this->cssClass;
    }

    public function id(): string
    {
        return $this->id ?? substr(md5('column' . serialize($this->blocks) . $this->width), 0, 8);
    }

    public function isEmpty(): bool
    {
        return $this->blocks === [];
    }

    /**
     * @return array{id?: string, width: int, title?: string, class?: string, settings?: array<string, mixed>, blocks?: list<array>}
     */
    public function toArray(): array
    {
        $data = ['width' => $this->width];
        if ($this->id !== null) {
            $data['id'] = $this->id;
        }
        if ($this->title !== '') {
            $data['title'] = $this->title;
        }
        if ($this->cssClass !== '') {
            $data['class'] = $this->cssClass;
        }
        if ($this->settings !== []) {
            $data['settings'] = $this->settings;
        }
        if ($this->blocks !== []) {
            $data['blocks'] = array_map(static fn (Block $b): array => $b->toArray(), $this->blocks);
        }

        return $data;
    }

    /**
     * Accepts the current shape (width + settings + blocks) and the older bare
     * {width, blocks} pair, so existing content keeps loading.
     */
    public static function fromArray(array $data, int $defaultWidth = 0): self
    {
        $blocks = [];
        foreach (is_array($data['blocks'] ?? null) ? $data['blocks'] : [] as $entry) {
            if (is_array($entry) && isset($entry['type'])) {
                $blocks[] = Block::fromArray($entry);
            }
        }

        $width = isset($data['width']) ? max(0, (int) $data['width']) : $defaultWidth;
        $settings = is_array($data['settings'] ?? null) ? $data['settings'] : [];
        $id = isset($data['id']) && is_string($data['id']) ? $data['id'] : null;

        $title = isset($data['title']) && is_string($data['title']) ? trim($data['title']) : '';
        $class = isset($data['class']) && is_string($data['class']) ? trim($data['class']) : '';

        return new self($blocks, $width, $settings, $id, $title, $class);
    }
}
