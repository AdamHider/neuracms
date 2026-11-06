<?php

declare(strict_types=1);

namespace FlatCms\Core;

/**
 * Simple event bus: modules react to events with on/emit and change data with filter.
 */
final class Events
{
    /** @var array<string, list<array{priority: int, callback: callable}>> */
    private array $listeners = [];

    public function on(string $event, callable $fn, int $priority = 0): void
    {
        $this->listeners[$event][] = ['priority' => $priority, 'callback' => $fn];
        usort(
            $this->listeners[$event],
            static fn (array $a, array $b): int => $b['priority'] <=> $a['priority']
        );
    }

    public function emit(string $event, mixed ...$args): void
    {
        foreach ($this->listeners[$event] ?? [] as $listener) {
            ($listener['callback'])(...$args);
        }
    }

    /**
     * Let listeners change a value. Each listener receives the current value and
     * returns the new one.
     */
    public function filter(string $event, mixed $value, mixed ...$args): mixed
    {
        foreach ($this->listeners[$event] ?? [] as $listener) {
            $value = ($listener['callback'])($value, ...$args);
        }

        return $value;
    }

    public function has(string $event): bool
    {
        return !empty($this->listeners[$event]);
    }
}
