<?php

declare(strict_types=1);

namespace FlatCms\Modules\Builder;

/**
 * A section is a horizontal band holding one or more rows, each with its own
 * columns and blocks: the Gantry 5 "section > row > column > particle" shape.
 *
 * Rows are what let one band carry several layouts (a 2-up headline strip over a
 * 3-up card row), which is why no dedicated multi-column particle is needed.
 */
final class Section
{
    /**
     * @param list<Row>            $rows
     * @param array<string, mixed> $settings
     */
    public function __construct(
        private readonly array $rows = [],
        private readonly array $settings = [],
        private readonly ?string $id = null,
        private readonly string $title = '',
        private readonly string $cssClass = '',
    ) {
    }

    /**
     * @return list<Row>
     */
    public function rows(): array
    {
        return $this->rows;
    }

    /**
     * Every column of every row, flattened.
     *
     * @return list<Column>
     */
    public function columns(): array
    {
        $columns = [];
        foreach ($this->rows as $row) {
            foreach ($row->columns() as $column) {
                $columns[] = $column;
            }
        }

        return $columns;
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
        return $this->id ?? substr(md5('section' . serialize($this->settings) . count($this->rows)), 0, 8);
    }

    /**
     * Structural role of the section: "header" and "footer" render as the site
     * chrome instead of an ordinary band, everything else is a plain section.
     * Roles also tell the builder where a section belongs in the page order.
     */
    public function role(): string
    {
        $role = (string) $this->get('role', '');

        return in_array($role, ['header', 'footer'], true) ? $role : '';
    }

    /**
     * @return list<Block>
     */
    public function blocks(): array
    {
        $blocks = [];
        foreach ($this->rows as $row) {
            foreach ($row->blocks() as $block) {
                $blocks[] = $block;
            }
        }

        return $blocks;
    }

    /**
     * Column widths of the first row, which is what a one-row section shows.
     *
     * @return list<int>
     */
    public function widths(): array
    {
        $row = $this->rows[0] ?? null;

        return $row === null ? [] : array_map(static fn (Column $c): int => $c->width(), $row->columns());
    }

    public function isEmpty(): bool
    {
        return $this->blocks() === [];
    }

    /**
     * @return array{id?: string, title?: string, class?: string, settings?: array<string, mixed>, rows?: list<array>}
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
        if ($this->rows !== []) {
            $data['rows'] = array_map(static fn (Row $r): array => $r->toArray(), $this->rows);
        }

        return $data;
    }

    /**
     * Build a section from raw data. Two older shapes still load: a section with
     * a flat "columns" list becomes a single row, and a section with a bare
     * "blocks" list becomes one row with one full-width column.
     */
    public static function fromArray(array $data, int $gridColumns = 12): self
    {
        $id = isset($data['id']) && is_string($data['id']) ? $data['id'] : null;
        $settings = is_array($data['settings'] ?? null) ? $data['settings'] : [];

        $rows = [];
        foreach (is_array($data['rows'] ?? null) ? $data['rows'] : [] as $row) {
            if (is_array($row)) {
                $rows[] = Row::fromArray($row, $gridColumns);
            }
        }

        if ($rows === []) {
            $legacy = self::legacyRow($data, $gridColumns);
            if ($legacy !== null) {
                $rows = [$legacy];
            }
        }

        $title = isset($data['title']) && is_string($data['title']) ? trim($data['title']) : '';
        $class = isset($data['class']) && is_string($data['class']) ? trim($data['class']) : '';

        return new self($rows, $settings, $id, $title, $class);
    }

    /**
     * Upgrade a section written before rows existed. Returns null when the data
     * carries neither "columns" nor "blocks", which is the case for a section
     * that only points at a global: such a section must keep zero rows.
     *
     * @param array<string, mixed> $data
     */
    private static function legacyRow(array $data, int $gridColumns): ?Row
    {
        $columns = [];
        foreach (is_array($data['columns'] ?? null) ? $data['columns'] : [] as $column) {
            if (is_array($column)) {
                $columns[] = Column::fromArray($column);
            }
        }

        if ($columns !== []) {
            return new Row($columns);
        }

        $blocks = [];
        foreach (is_array($data['blocks'] ?? null) ? $data['blocks'] : [] as $entry) {
            if (is_array($entry) && isset($entry['type'])) {
                $blocks[] = Block::fromArray($entry);
            }
        }

        return $blocks === [] ? null : new Row([new Column($blocks, $gridColumns)]);
    }
}
