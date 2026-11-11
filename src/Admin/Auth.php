<?php

declare(strict_types=1);

namespace FlatCms\Admin;

use FlatCms\Core\SessionStore;

/**
 * Login state and role checks. The session only holds the login and role, never
 * the password hash.
 */
final class Auth
{
    private const KEY = 'admin.user';

    public function __construct(
        private readonly UserRepository $users,
        private readonly SessionStore $session,
    ) {
    }

    public function attempt(string $login, string $password): bool
    {
        $user = $this->users->verify($login, $password);
        if ($user === null) {
            return false;
        }

        $this->session->set(self::KEY, [
            'login' => (string) $user['login'],
            'role' => (string) ($user['role'] ?? 'editor'),
        ]);
        if (method_exists($this->session, 'regenerate')) {
            $this->session->regenerate();
        }

        return true;
    }

    public function logout(): void
    {
        $this->session->forget(self::KEY);
    }

    public function check(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array{login: string, role: string}|null
     */
    public function user(): ?array
    {
        $user = $this->session->get(self::KEY);
        if (!is_array($user) || !isset($user['login'])) {
            return null;
        }

        return ['login' => (string) $user['login'], 'role' => (string) ($user['role'] ?? 'editor')];
    }

    public function role(): ?string
    {
        return $this->user()['role'] ?? null;
    }

    public function isAdmin(): bool
    {
        return $this->role() === 'admin';
    }

    /**
     * Roles that may edit content. Everyone who can log in can edit; only admins
     * manage users and settings.
     */
    public function canEdit(): bool
    {
        return in_array($this->role(), ['admin', 'editor'], true);
    }
}
