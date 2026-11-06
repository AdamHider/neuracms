<?php

declare(strict_types=1);

namespace FlatCms\Support;

use RuntimeException;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

/**
 * Safe filesystem helpers. Every write in the project goes through this class:
 * data is written to a temporary file next to the target, locked, then renamed
 * over the target so the replacement is atomic.
 */
final class Fs
{
    private readonly string $root;

    public function __construct(string $root)
    {
        $this->root = rtrim($root, '/');
    }

    public function root(): string
    {
        return $this->root;
    }

    /**
     * Resolve a path relative to the project root. Absolute paths are used as is.
     */
    public function path(string $path): string
    {
        if ($path === '') {
            return $this->root;
        }

        if ($path[0] === '/' || preg_match('#^[A-Za-z]:[\\\\/]#', $path) === 1) {
            return $path;
        }

        return $this->root . '/' . $path;
    }

    public function exists(string $path): bool
    {
        return file_exists($this->path($path));
    }

    public function isDir(string $path): bool
    {
        return is_dir($this->path($path));
    }

    public function isFile(string $path): bool
    {
        return is_file($this->path($path));
    }

    public function mtime(string $path): int
    {
        $real = $this->path($path);
        $mtime = @filemtime($real);

        return $mtime === false ? 0 : $mtime;
    }

    public function read(string $path): string
    {
        $real = $this->path($path);
        if (!is_file($real)) {
            return '';
        }

        $data = @file_get_contents($real);

        return $data === false ? '' : $data;
    }

    public function readYaml(string $path, array $default = []): array
    {
        $raw = $this->read($path);
        if (trim($raw) === '') {
            return $default;
        }

        try {
            $data = Yaml::parse($raw);
        } catch (ParseException) {
            return $default;
        }

        return is_array($data) ? $data : $default;
    }

    public function writeYaml(string $path, array $data): bool
    {
        $yaml = Yaml::dump($data, 8, 2, Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK);

        return $this->write($path, $yaml);
    }

    public function write(string $path, string $data): bool
    {
        $real = $this->path($path);
        $dir = dirname($real);
        $this->ensureDir($dir);

        $tmp = $dir . '/.' . basename($real) . '.' . bin2hex(random_bytes(6)) . '.tmp';
        $handle = @fopen($tmp, 'wb');
        if ($handle === false) {
            throw new RuntimeException('Unable to open temporary file for writing: ' . $tmp);
        }

        try {
            if (!flock($handle, LOCK_EX)) {
                throw new RuntimeException('Unable to lock temporary file: ' . $tmp);
            }
            fwrite($handle, $data);
            fflush($handle);
            flock($handle, LOCK_UN);
        } finally {
            fclose($handle);
        }

        if (!@rename($tmp, $real)) {
            @unlink($tmp);

            return false;
        }

        return true;
    }

    public function ensureDir(string $path): void
    {
        $real = $this->path($path);
        if (is_dir($real)) {
            return;
        }

        if (!@mkdir($real, 0775, true) && !is_dir($real)) {
            throw new RuntimeException('Unable to create directory: ' . $real);
        }
    }

    public function delete(string $path): bool
    {
        $real = $this->path($path);
        if (is_dir($real)) {
            return $this->deleteDir($real);
        }

        return is_file($real) && @unlink($real);
    }

    public function deleteDir(string $path): bool
    {
        $real = $this->path($path);
        if (!is_dir($real)) {
            return false;
        }

        $items = new \FilesystemIterator($real, \FilesystemIterator::SKIP_DOTS);
        foreach ($items as $item) {
            $item->isDir() && !$item->isLink()
                ? $this->deleteDir($item->getPathname())
                : @unlink($item->getPathname());
        }

        return @rmdir($real);
    }

    public function copy(string $source, string $target): bool
    {
        $src = $this->path($source);
        if (!is_file($src)) {
            return false;
        }

        $this->ensureDir(dirname($this->path($target)));

        return @copy($src, $this->path($target));
    }

    public function move(string $source, string $target): bool
    {
        $src = $this->path($source);
        $dst = $this->path($target);
        $this->ensureDir(dirname($dst));

        if (@rename($src, $dst)) {
            return true;
        }

        if (!is_dir($src)) {
            return false;
        }

        return $this->copyDir($src, $dst) && $this->deleteDir($src);
    }

    public function copyDir(string $source, string $target): bool
    {
        $src = $this->path($source);
        $dst = $this->path($target);
        if (!is_dir($src)) {
            return false;
        }

        $this->ensureDir($dst);
        $items = new \FilesystemIterator($src, \FilesystemIterator::SKIP_DOTS);
        foreach ($items as $item) {
            $from = $item->getPathname();
            $to = $dst . '/' . $item->getFilename();
            $item->isDir()
                ? $this->copyDir($from, $to)
                : @copy($from, $to);
        }

        return true;
    }

    /**
     * List entries of a directory. Returns file names, directories first.
     *
     * @return list<string>
     */
    public function listDir(string $path, bool $dirsOnly = false): array
    {
        $real = $this->path($path);
        if (!is_dir($real)) {
            return [];
        }

        $dirs = [];
        $files = [];
        $items = new \FilesystemIterator($real, \FilesystemIterator::SKIP_DOTS);
        foreach ($items as $item) {
            if ($dirsOnly && !$item->isDir()) {
                continue;
            }
            $item->isDir() ? $dirs[] = $item->getFilename() : $files[] = $item->getFilename();
        }

        sort($dirs);
        sort($files);

        return [...$dirs, ...$files];
    }

    /**
     * Normalize a path and make sure it stays inside the given base directory.
     */
    public function safePath(string $base, string $path): ?string
    {
        $base = trim(str_replace('\\', '/', $base), '/');
        $path = trim(str_replace('\\', '/', $path), '/');

        $parts = [];
        foreach (explode('/', $path) as $part) {
            if ($part === '' || $part === '.') {
                continue;
            }
            if ($part === '..') {
                array_pop($parts);
                if ($parts === []) {
                    return null;
                }
                continue;
            }
            $parts[] = $part;
        }

        return implode('/', $parts);
    }
}
