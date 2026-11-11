<?php

declare(strict_types=1);

namespace FlatCms\Admin;

use FlatCms\Support\Fs;

/**
 * File based user store. Each user is one YAML file under var/users/. Passwords
 * are stored as password_hash() values.
 */
final class UserRepository
{
    public function __construct(private readonly Fs $fs)
    {
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function all(): array
    {
        $users = [];
        foreach ($this->fs->listDir('var/users') as $name) {
            if (!str_ends_with($name, '.yaml')) {
                continue;
            }
            $id = substr($name, 0, -5);
            $data = $this->fs->readYaml('var/users/' . $name, []);
            if ($data !== []) {
                $users[$id] = $data;
            }
        }

        return $users;
    }

    public function find(string $login): ?array
    {
        $login = $this->normalize($login);
        if ($login === null) {
            return null;
        }

        $data = $this->fs->readYaml('var/users/' . $login . '.yaml', []);

        return $data === [] ? null : $data;
    }

    public function create(string $login, string $password, string $role = 'editor'): bool
    {
        $login = $this->normalize($login);
        if ($login === null || $this->find($login) !== null) {
            return false;
        }

        return $this->fs->writeYaml('var/users/' . $login . '.yaml', [
            'login' => $login,
            'role' => $role,
            'password' => password_hash($password, PASSWORD_DEFAULT),
            'created_at' => date('c'),
        ]);
    }

    public function verify(string $login, string $password): ?array
    {
        $user = $this->find($login);
        if ($user === null) {
            return null;
        }

        $hash = $user['password'] ?? null;

        return is_string($hash) && password_verify($password, $hash) ? $user : null;
    }

    public function exists(string $login): bool
    {
        return $this->find($login) !== null;
    }

    public function count(): int
    {
        return count($this->all());
    }

    private function normalize(string $login): ?string
    {
        $login = strtolower(trim($login));
        if ($login === '' || preg_match('/^[a-z0-9._-]{3,32}$/', $login) !== 1) {
            return null;
        }

        return $login;
    }
}
