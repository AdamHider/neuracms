<?php

declare(strict_types=1);

namespace FlatCms\Core;

/**
 * Minimal session wrapper. Starts lazily so requests that do not need state never
 * touch the session, and uses hardened cookie flags.
 */
final class Session implements SessionStore
{
    private bool $started = false;

    public function __construct(private readonly string $name = 'flatcms')
    {
    }

    public function start(): void
    {
        if ($this->started || session_status() === PHP_SESSION_ACTIVE) {
            $this->started = true;

            return;
        }

        if (headers_sent()) {
            $this->started = true;

            return;
        }

        session_name($this->name);
        session_set_cookie_params([
            'httponly' => true,
            'samesite' => 'Lax',
            'secure' => (($_SERVER['HTTPS'] ?? '') === 'on'),
            'path' => '/',
        ]);
        session_start();
        $this->started = true;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $this->start();

        return $_SESSION[$key] ?? $default;
    }

    public function set(string $key, mixed $value): void
    {
        $this->start();
        $_SESSION[$key] = $value;
    }

    public function forget(string $key): void
    {
        $this->start();
        unset($_SESSION[$key]);
    }

    public function regenerate(): void
    {
        $this->start();
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
    }

    /**
     * One-shot value, used for flash messages and CSRF-free redirect flows.
     */
    public function pull(string $key, mixed $default = null): mixed
    {
        $value = $this->get($key, $default);
        $this->forget($key);

        return $value;
    }

    /**
     * Rotate a per-session token used by forms to prevent CSRF.
     */
    public function csrfToken(): string
    {
        $token = $this->get('_csrf');
        if (!is_string($token) || $token === '') {
            $token = bin2hex(random_bytes(32));
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
