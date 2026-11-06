<?php

declare(strict_types=1);

namespace FlatCms\Modules\Builder;

use FlatCms\Core\Events;

/**
 * Catalog of block types. Core ships a few; other modules register their own
 * through the builder.types filter.
 */
final class TypeRegistry
{
    /** @var array<string, BlockType> */
    private array $types = [];

    public function __construct(private readonly Events $events)
    {
    }

    public function register(BlockType $type): void
    {
        $this->types[$type->name()] = $type;
    }

    /**
     * @return array<string, BlockType>
     */
    public function all(): array
    {
        $this->events->emit('builder.types', $this);

        return $this->types;
    }

    public function get(string $name): ?BlockType
    {
        $this->all();

        return $this->types[$name] ?? null;
    }

    public function has(string $name): bool
    {
        return $this->get($name) !== null;
    }
}
