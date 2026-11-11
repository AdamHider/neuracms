<?php

declare(strict_types=1);

namespace FlatCms\Content;

/**
 * Chainable queries over the index. Full Items are loaded only for the result.
 *
 *   $app->query->type('product')->where('price', '<', 5000)->sort('price')->find();
 */
final class Query
{
    private ?string $type = null;

    private ?string $kind = null;

    /** @var list<array{field: string, op: string, value: mixed}> */
    private array $wheres = [];

    /** @var list<array{field: string, values: array}> */
    private array $whereIns = [];

    private ?string $parent = null;

    private ?string $searchText = null;

    private ?string $sortField = null;

    private string $sortDir = 'asc';

    private ?int $limit = null;

    private int $offset = 0;

    private bool $includeUnpublished = false;

    public function __construct(private readonly Index $index, private readonly Storage $storage)
    {
    }

    public function type(string $type): self
    {
        $clone = clone $this;
        $clone->type = $type;

        return $clone;
    }

    /**
     * Restrict to page or element items (see Schema::KIND_*).
     */
    public function kind(string $kind): self
    {
        $clone = clone $this;
        $clone->kind = $kind;

        return $clone;
    }

    public function where(string $field, string $op, mixed $value): self
    {
        $clone = clone $this;
        $clone->wheres[] = ['field' => $field, 'op' => strtolower($op), 'value' => $value];

        return $clone;
    }

    public function whereIn(string $field, array $values): self
    {
        $clone = clone $this;
        $clone->whereIns[] = ['field' => $field, 'values' => $values];

        return $clone;
    }

    public function children(string $id): self
    {
        $clone = clone $this;
        $clone->parent = $id;

        return $clone;
    }

    public function siblings(string $id): self
    {
        $pos = strrpos($id, '/');
        $parent = $pos === false ? '' : substr($id, 0, $pos);

        return $this->children($parent);
    }

    public function search(string $text): self
    {
        $clone = clone $this;
        $clone->searchText = mb_strtolower(trim($text));

        return $clone;
    }

    public function sort(string $field, string $dir = 'asc'): self
    {
        $clone = clone $this;
        $clone->sortField = $field;
        $clone->sortDir = strtolower($dir) === 'desc' ? 'desc' : 'asc';

        return $clone;
    }

    public function limit(int $limit): self
    {
        $clone = clone $this;
        $clone->limit = $limit;

        return $clone;
    }

    public function offset(int $offset): self
    {
        $clone = clone $this;
        $clone->offset = $offset;

        return $clone;
    }

    public function withUnpublished(bool $include = true): self
    {
        $clone = clone $this;
        $clone->includeUnpublished = $include;

        return $clone;
    }

    /**
     * @return list<Item>
     */
    public function find(): array
    {
        $items = [];
        foreach ($this->matches() as $entry) {
            $item = $this->storage->load((string) $entry['id']);
            if ($item !== null) {
                $items[] = $item;
            }
        }

        return $items;
    }

    public function first(): ?Item
    {
        $items = $this->limit(1)->find();

        return $items[0] ?? null;
    }

    public function count(): int
    {
        return count($this->matches());
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function matches(): array
    {
        $entries = array_values($this->index->all());

        $result = array_filter($entries, function (array $entry): bool {
            if (!$this->includeUnpublished && ($entry['published'] ?? true) === false) {
                return false;
            }
            if ($this->type !== null && ($entry['type'] ?? 'page') !== $this->type) {
                return false;
            }
            if ($this->kind !== null && ($entry['kind'] ?? 'page') !== $this->kind) {
                return false;
            }
            if ($this->parent !== null && ($entry['parent'] ?? '') !== $this->parent) {
                return false;
            }
            foreach ($this->wheres as $where) {
                if (!$this->compare($this->value($entry, $where['field']), $where['op'], $where['value'])) {
                    return false;
                }
            }
            foreach ($this->whereIns as $in) {
                $actual = $this->value($entry, $in['field']);
                $actual = is_array($actual) ? $actual : [$actual];
                if (array_intersect($actual, $in['values']) === []) {
                    return false;
                }
            }
            if ($this->searchText !== null && $this->searchText !== '') {
                $haystack = mb_strtolower((string) ($entry['title'] ?? ''));
                foreach (($entry['indexed'] ?? []) as $value) {
                    if (is_scalar($value)) {
                        $haystack .= ' ' . mb_strtolower((string) $value);
                    }
                }
                if (!str_contains($haystack, $this->searchText)) {
                    return false;
                }
            }

            return true;
        });

        $result = $this->applySort($result);

        if ($this->offset > 0 || $this->limit !== null) {
            $result = array_slice($result, $this->offset, $this->limit);
        }

        return array_values($result);
    }

    private function applySort(array $entries): array
    {
        $field = $this->sortField ?? 'order';
        $dir = $this->sortDir;

        usort($entries, static function (array $a, array $b) use ($field, $dir): int {
            $left = $field === 'id' ? $a['id'] : ($a['indexed'][$field] ?? $a[$field] ?? null);
            $right = $field === 'id' ? $b['id'] : ($b['indexed'][$field] ?? $b[$field] ?? null);

            $cmp = $left <=> $right;
            if ($cmp === 0) {
                $cmp = strcmp((string) $a['id'], (string) $b['id']);
            }

            return $dir === 'desc' ? -$cmp : $cmp;
        });

        return $entries;
    }

    private function value(array $entry, string $field): mixed
    {
        if (array_key_exists($field, $entry['indexed'] ?? [])) {
            return $entry['indexed'][$field];
        }

        return $entry[$field] ?? null;
    }

    private function compare(mixed $left, string $op, mixed $right): bool
    {
        return match ($op) {
            '=' , '==' => $left == $right,
            '!=' , '<>' => $left != $right,
            '<' => $left < $right,
            '>' => $left > $right,
            '<=' => $left <= $right,
            '>=' => $left >= $right,
            'in' => in_array($left, (array) $right, true),
            'contains' => is_array($left)
                ? in_array($right, $left, true)
                : str_contains((string) $left, (string) $right),
            default => false,
        };
    }
}
