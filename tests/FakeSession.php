<?php

declare(strict_types=1);

namespace FlatCms\Tests;

use FlatCms\Core\SessionStore;

/**
 * In-memory session so tests never start a real PHP session.
 */
final class FakeSession implements SessionStore
{
    /** @var array<string, mixed> */
    private array $data = [];

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->data[$key] ?? $default;
    }

    public function set(string $key, mixed $value): void
    {
        $this->data[$key] = $value;
    }

    public function forget(string $key): void
    {
        unset($this->data[$key]);
    }

    public function pull(string $key, mixed $default = null): mixed
    {
        $value = $this->get($key, $default);
        $this->forget($key);

        return $value;
    }

    public function csrfToken(): string
    {
        $token = $this->get('_csrf');
        if (!is_string($token) || $token === '') {
            $token = bin2hex(random_bytes(16));
            $this->set('_csrf', $token);
        }

        return $token;
    }

    public function verifyCsrf(mixed $token): bool
    {
        $expected = $this->get('_csrf');

        return is_string($token) && is_string($expected) && $expected !== '' && hash_equals($expected, $token);
    }
}
