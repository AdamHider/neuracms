<?php

declare(strict_types=1);

namespace FlatCms\Modules\Builder;

/**
 * A page layout: an ordered list of sections. Each section holds columns, and
 * each column holds blocks (the Gantry 5 "section > column > particle" shape).
 *
 * A legacy layout may still be a flat list of blocks or carry a "grid"; both are
 * upgraded to sections on read so old content keeps rendering.
 */
final class Layout
{
    /**
     * @param list<Section> $sections
     */
    public function __construct(
        private readonly array $sections = [],
        private readonly int $columns = 12,
    ) {
    }

    /**
     * @return list<Section>
     */
    public function sections(): array
    {
        return $this->sections;
    }

    /**
     * Every block on the page, in reading order.
     *
     * @return list<Block>
     */
    public function blocks(): array
    {
        $blocks = [];
        foreach ($this->sections as $section) {
            foreach ($section->blocks() as $block) {
                $blocks[] = $block;
            }
        }

        return $blocks;
    }

    public function columns(): int
    {
        return $this->columns;
    }

    public function isEmpty(): bool
    {
        return $this->blocks() === [];
    }

    public function hasSections(): bool
    {
        return $this->sections !== [];
    }

    /**
     * Build a layout from the raw "layout" value of item.yaml. Accepts:
     *  - ["sections" => [...]]  (current format)
     *  - ["blocks" => [...], "grid" => [...]]  (legacy)
     *  - a plain list of blocks (legacy)
     */
    public static function fromValue(mixed $value, int $columns = 12): self
    {
        if (!is_array($value)) {
            return new self([], $columns);
        }

        // Current format: explicit sections.
        if (isset($value['sections']) && is_array($value['sections'])) {
            $sections = [];
            foreach ($value['sections'] as $entry) {
                if (is_array($entry)) {
                    $sections[] = Section::fromArray($entry, $columns);
                }
            }

            return new self($sections, $columns);
        }

        $rawBlocks = $value;
        $grid = null;
        if (isset($value['blocks']) && is_array($value['blocks'])) {
            $rawBlocks = $value['blocks'];
            $grid = self::parseGrid($value['grid'] ?? null);
        }

        return new self(self::sectionsFromFlatBlocks($rawBlocks, $grid, $columns), $columns);
    }

    /**
     * @return array{sections: list<array>}
     */
    public function toArray(): array
    {
        return ['sections' => array_map(static fn (Section $s): array => $s->toArray(), $this->sections)];
    }

    /**
     * Turn a flat block list plus an optional grid into sections: one section
     * per grid row, one column per span. Without a grid each block becomes its
     * own full-width section.
     *
     * @param mixed                 $rawBlocks
     * @param list<list<int>>|null  $grid
     *
     * @return list<Section>
     */
    private static function sectionsFromFlatBlocks(mixed $rawBlocks, ?array $grid, int $columns): array
    {
        $blocks = [];
        foreach (is_array($rawBlocks) ? $rawBlocks : [] as $entry) {
            if (is_array($entry) && isset($entry['type'])) {
                $blocks[] = Block::fromArray($entry);
            }
        }

        if ($blocks === []) {
            return [];
        }

        if ($grid === null || $grid === []) {
            return array_map(static fn (Block $b): Section => new Section([new Column([$b], $columns)]), $blocks);
        }

        $sections = [];
        $cursor = 0;
        foreach ($grid as $row) {
            $count = count($row);
            if ($count === 0) {
                continue;
            }
            $slice = array_slice($blocks, $cursor, $count);
            $cursor += $count;

            $rowColumns = [];
            foreach ($slice as $index => $block) {
                $rowColumns[] = new Column([$block], max(1, (int) ($row[$index] ?? 1)));
            }
            if ($rowColumns !== []) {
                $sections[] = new Section($rowColumns);
            }
        }

        // Any blocks beyond the grid keep rendering as their own sections.
        if ($cursor < count($blocks)) {
            foreach (array_slice($blocks, $cursor) as $block) {
                $sections[] = new Section([new Column([$block], $columns)]);
            }
        }

        return $sections;
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
