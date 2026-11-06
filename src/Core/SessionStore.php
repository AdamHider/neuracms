<?php

declare(strict_types=1);

namespace FlatCms\Core;

/**
 * Session contract. Code depends on this instead of the concrete Session so it
 * can run in tests and CLI without starting a real PHP session.
 */
interface SessionStore
{
    public function get(string $key, mixed $default = null): mixed;

    public function set(string $key, mixed $value): void;

    public function forget(string $key): void;

    public function pull(string $key, mixed $default = null): mixed;

    public function csrfToken(): string;

    public function verifyCsrf(mixed $token): bool;
}
