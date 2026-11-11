<?php

declare(strict_types=1);

namespace FlatCms\Modules\Builder;

/**
 * A section is a horizontal band that holds one or more columns, each with its
 * own stack of blocks. Sections are the top level of a page layout, mirroring
 * the Gantry 5 "section > column > particle" hierarchy.
 */
final class Section
{
    /**
     * @param list<Column>          $columns
     * @param array<string, mixed>  $settings
     */
    public function __construct(
        private readonly array $columns = [],
        private readonly array $settings = [],
        private readonly ?string $id = null,
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

    public function id(): string
    {
        return $this->id ?? substr(md5(serialize($this->settings) . count($this->columns)), 0, 8);
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

    /**
     * Column widths of this section.
     *
     * @return list<int>
     */
    public function widths(): array
    {
        return array_map(static fn (Column $c): int => $c->width(), $this->columns);
    }

    public function isEmpty(): bool
    {
        return $this->blocks() === [];
    }

    /**
     * @return array{id: string, settings: array<string, mixed>, columns: list<array>}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id(),
            'settings' => $this->settings,
            'columns' => array_map(static fn (Column $c): array => $c->toArray(), $this->columns),
        ];
    }

    /**
     * Build a section from raw data. A section given as a plain block list is
     * wrapped in a single full-width column.
     */
    public static function fromArray(array $data, int $gridColumns = 12): self
    {
        $id = isset($data['id']) && is_string($data['id']) ? $data['id'] : null;
        $settings = is_array($data['settings'] ?? null) ? $data['settings'] : [];

        $columns = [];
        if (isset($data['columns']) && is_array($data['columns'])) {
            foreach ($data['columns'] as $column) {
                if (is_array($column)) {
                    $columns[] = Column::fromArray($column);
                }
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

        return new self($columns, $settings, $id);
    }
}
