<?php

declare(strict_types=1);

namespace FlatCms\Modules\Builder;

/**
 * One column inside a section: a grid width and the blocks stacked in it.
 * Columns are structural, the way Gantry 5 lays out a section row.
 */
final class Column
{
    /**
     * @param list<Block> $blocks
     */
    public function __construct(
        private readonly array $blocks = [],
        private readonly int $width = 0,
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

    public function isEmpty(): bool
    {
        return $this->blocks === [];
    }

    /**
     * @return array{width: int, blocks: list<array>}
     */
    public function toArray(): array
    {
        return [
            'width' => $this->width,
            'blocks' => array_map(static fn (Block $b): array => $b->toArray(), $this->blocks),
        ];
    }

    public static function fromArray(array $data, int $defaultWidth = 0): self
    {
        $blocks = [];
        foreach (is_array($data['blocks'] ?? null) ? $data['blocks'] : [] as $entry) {
            if (is_array($entry) && isset($entry['type'])) {
                $blocks[] = Block::fromArray($entry);
            }
        }

        $width = isset($data['width']) ? max(0, (int) $data['width']) : $defaultWidth;

        return new self($blocks, $width);
    }
}
