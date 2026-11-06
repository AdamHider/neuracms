<?php

declare(strict_types=1);

namespace FlatCms\Support;

/**
 * Small array helpers used across the core.
 */
final class Arr
{
    /**
     * Read a value from a nested array using a dot path.
     */
    public static function get(array $data, string $key, mixed $default = null): mixed
    {
        if ($key === '') {
            return $default;
        }

        if (array_key_exists($key, $data)) {
            return $data[$key];
        }

        $current = $data;
        foreach (explode('.', $key) as $part) {
            if (!is_array($current) || !array_key_exists($part, $current)) {
                return $default;
            }
            $current = $current[$part];
        }

        return $current;
    }

    /**
     * Write a value into a nested array using a dot path.
     */
    public static function set(array &$data, string $key, mixed $value): void
    {
        $parts = explode('.', $key);
        $current = &$data;
        foreach ($parts as $part) {
            if (!isset($current[$part]) || !is_array($current[$part])) {
                $current[$part] = [];
            }
            $current = &$current[$part];
        }
        $current = $value;
    }

    public static function has(array $data, string $key): bool
    {
        if (array_key_exists($key, $data)) {
            return true;
        }

        $current = $data;
        foreach (explode('.', $key) as $part) {
            if (!is_array($current) || !array_key_exists($part, $current)) {
                return false;
            }
            $current = $current[$part];
        }

        return true;
    }

    /**
     * Merge two arrays recursively; values from $override win.
     */
    public static function merge(array $base, array $override): array
    {
        foreach ($override as $key => $value) {
            if (is_array($value) && isset($base[$key]) && is_array($base[$key])) {
                $base[$key] = self::merge($base[$key], $value);
            } else {
                $base[$key] = $value;
            }
        }

        return $base;
    }

    public static function flatten(array $data, string $prefix = ''): array
    {
        $result = [];
        foreach ($data as $key => $value) {
            $full = $prefix === '' ? (string) $key : $prefix . '.' . $key;
            if (is_array($value)) {
                $result += self::flatten($value, $full);
            } else {
                $result[$full] = $value;
            }
        }

        return $result;
    }
}
