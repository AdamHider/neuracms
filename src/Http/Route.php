<?php

declare(strict_types=1);

namespace FlatCms\Http;

/**
 * Result of a successful Router::match.
 */
final class Route
{
    public function __construct(
        public readonly string $kind,
        public readonly array $params = [],
        public readonly mixed $handler = null,
    ) {
    }

    public function isRedirect(): bool
    {
        return $this->kind === 'redirect';
    }

    public function isContent(): bool
    {
        return $this->kind === 'content';
    }

    public function isHandler(): bool
    {
        return $this->kind === 'handler';
    }
}
