<?php

declare(strict_types=1);

namespace FlatCms\Modules\Builder;

/**
 * A page layout: an ordered list of blocks plus optional column widths for a
 * grid. The grid is a list of rows, each row a list of column spans.
 */
final class Layout
{
    /**
     * @param list<Block>        $blocks
     * @param list<list<int>>|null $grid
     */
    public function __construct(
        private readonly array $blocks = [],
        private readonly ?array $grid = null,
        private readonly int $columns = 12,
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
     * @return list<list<int>>|null
     */
    public function grid(): ?array
    {
        return $this->grid;
    }

    public function columns(): int
    {
        return $this->columns;
    }

    public function isEmpty(): bool
    {
        return $this->blocks === [];
    }

    public function hasGrid(): bool
    {
        return $this->grid !== null && $this->grid !== [];
    }

    /**
     * Column spans of the grid row that holds the block at $index.
     *
     * @return list<int>|null
     */
    public function rowFor(int $index): ?array
    {
        if (!$this->hasGrid()) {
            return null;
        }

        $cursor = 0;
        foreach ($this->grid as $row) {
            if ($index < $cursor + count($row)) {
                return $index >= $cursor ? $row : null;
            }
            $cursor += count($row);
        }

        return null;
    }

    /**
     * Build a layout from the raw "layout" value of item.yaml. Accepts a plain
     * list of blocks or a mapping with "blocks" and "grid" keys.
     */
    public static function fromValue(mixed $value, int $columns = 12): self
    {
        if (!is_array($value)) {
            return new self([], null, $columns);
        }

        $rawBlocks = $value;
        $grid = null;
        if (isset($value['blocks']) && is_array($value['blocks'])) {
            $rawBlocks = $value['blocks'];
            $grid = self::parseGrid($value['grid'] ?? null);
        }

        $blocks = [];
        foreach ($rawBlocks as $entry) {
            if (is_array($entry) && isset($entry['type'])) {
                $blocks[] = Block::fromArray($entry);
            }
        }

        return new self($blocks, $grid, $columns);
    }

    /**
     * @return array{blocks: list<array>, grid?: list<list<int>>}
     */
    public function toArray(): array
    {
        $data = ['blocks' => array_map(static fn (Block $b): array => $b->toArray(), $this->blocks)];
        if ($this->grid !== null) {
            $data['grid'] = $this->grid;
        }

        return $data;
    }

    /**
     * Column spans for a block, or null when no grid is set.
     *
     * @return list<int>|null
     */
    public function columnsFor(int $index): ?array
    {
        if ($this->grid === null) {
            return null;
        }

        $cursor = 0;
        foreach ($this->grid as $row) {
            if ($index >= $cursor && $index < $cursor + count($row)) {
                return $row;
            }
            $cursor += count($row);
        }

        return null;
    }

    /**
     * @return list<list<int>>|null
     */
    private static function parseGrid(mixed $grid): ?array
    {
        if (!is_array($grid)) {
            return null;
        }

        $rows = [];
        foreach ($grid as $row) {
            if (!is_array($row)) {
                continue;
            }
            $cols = [];
            foreach ($row as $span) {
                $cols[] = max(1, (int) $span);
            }
            if ($cols !== []) {
                $rows[] = $cols;
            }
        }

        return $rows === [] ? null : $rows;
    }
}
