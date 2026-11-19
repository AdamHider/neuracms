<?php

declare(strict_types=1);

namespace FlatCms\Modules\Builder;

/**
 * A row inside a section: a horizontal group of columns. Rows are what makes a
 * section able to hold several bands of different column counts, which is the
 * whole reason the "columns" and "products" particles are gone — both were
 * layouts you can now build from plain rows and columns.
 */
final class Row
{
    /**
     * @param list<Column>         $columns
     * @param array<string, mixed> $settings
     */
    public function __construct(
        private readonly array $columns = [],
        private readonly array $settings = [],
        private readonly ?string $id = null,
        private readonly string $title = '',
        private readonly string $cssClass = '',
    ) {
    }

    /**
     * @return list<Column>
     */
    public function columns(): array
    {
        return $this->columns;
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
        return $this->id ?? substr(md5('row' . serialize($this->settings) . count($this->columns)), 0, 8);
    }

    /**
     * @return list<Block>
     */
    public function blocks(): array
    {
        $blocks = [];
        foreach ($this->columns as $column) {
            foreach ($column->blocks() as $block) {
                $blocks[] = $block;
            }
        }

        return $blocks;
    }

    public function isEmpty(): bool
    {
        return $this->blocks() === [];
    }

    /**
     * @return array{id?: string, title?: string, class?: string, settings?: array<string, mixed>, columns?: list<array>}
     */
    public function toArray(): array
    {
        $data = [];
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
        if ($this->columns !== []) {
            $data['columns'] = array_map(static fn (Column $c): array => $c->toArray(), $this->columns);
        }

        return $data;
    }

    /**
     * A row given as a plain column list or as a flat block list is normalised to
     * a row of columns, so hand-written YAML stays short.
     */
    public static function fromArray(array $data, int $gridColumns = 12): self
    {
        $id = isset($data['id']) && is_string($data['id']) ? $data['id'] : null;
        $settings = is_array($data['settings'] ?? null) ? $data['settings'] : [];

        $columns = [];
        foreach (is_array($data['columns'] ?? null) ? $data['columns'] : [] as $column) {
            if (is_array($column)) {
                $columns[] = Column::fromArray($column);
            }
        }

        if ($columns === []) {
            $blocks = [];
            foreach (is_array($data['blocks'] ?? null) ? $data['blocks'] : [] as $entry) {
                if (is_array($entry) && isset($entry['type'])) {
                    $blocks[] = Block::fromArray($entry);
                }
            }
            $columns = [new Column($blocks, $gridColumns)];
        }

        $title = isset($data['title']) && is_string($data['title']) ? trim($data['title']) : '';
        $class = isset($data['class']) && is_string($data['class']) ? trim($data['class']) : '';

        return new self($columns, $settings, $id, $title, $class);
    }
}
