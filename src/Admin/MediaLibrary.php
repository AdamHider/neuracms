<?php

declare(strict_types=1);

namespace FlatCms\Admin;

use FlatCms\Support\Fs;

/**
 * Media library for a single content item. Uploads are validated by extension
 * and real MIME type, and stored next to the item's item.yaml.
 */
final class MediaLibrary
{
    private const ALLOWED = [
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'gif' => 'image/gif',
        'webp' => 'image/webp',
        'avif' => 'image/avif',
        'svg' => 'image/svg+xml',
        'pdf' => 'application/pdf',
    ];

    private const MAX_BYTES = 5_242_880;

    public function __construct(private readonly Fs $fs, private readonly string $base = 'content')
    {
    }

    /**
     * @return list<string>
     */
    public function list(string $id): array
    {
        $dir = $this->dir($id);
        if ($dir === null) {
            return [];
        }

        $files = [];
        foreach ($this->fs->listDir($dir) as $name) {
            if ($name[0] === '.' || in_array($name, ['item.yaml', 'content.md'], true)) {
                continue;
            }
            if ($this->fs->isFile($dir . '/' . $name)) {
                $files[] = $name;
            }
        }

        return $files;
    }

    /**
     * @param array{name?: string, tmp_name?: string, error?: int, size?: int} $file
     */
    public function upload(string $id, array $file): string
    {
        $dir = $this->dir($id);
        if ($dir === null) {
            return 'Неверный путь.';
        }

        $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($error !== UPLOAD_ERR_OK) {
            return 'Ошибка загрузки файла.';
        }

        $tmp = (string) ($file['tmp_name'] ?? '');
        if ($tmp === '' || !is_uploaded_file($tmp)) {
            return 'Файл не был загружен.';
        }

        if ((int) ($file['size'] ?? 0) > self::MAX_BYTES) {
            return 'Файл больше 5 МБ.';
        }

        $name = $this->safeName((string) ($file['name'] ?? ''));
        if ($name === null) {
            return 'Недопустимое имя файла.';
        }

        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        if (!isset(self::ALLOWED[$ext])) {
            return 'Недопустимый тип файла.';
        }

        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($tmp);
        if (!is_string($mime) || $mime !== self::ALLOWED[$ext]) {
            // SVG is reported inconsistently across systems.
            if (!($ext === 'svg' && str_contains($mime, 'xml'))) {
                return 'Содержимое файла не соответствует расширению.';
            }
        }

        $this->fs->ensureDir($dir);
        if (!$this->fs->write($dir . '/' . $name, (string) file_get_contents($tmp))) {
            return 'Не удалось сохранить файл.';
        }

        return '';
    }

    public function delete(string $id, string $name): bool
    {
        $dir = $this->dir($id);
        if ($dir === null || $this->safeName($name) === null) {
            return false;
        }

        return $this->fs->delete($dir . '/' . $name);
    }

    private function safeName(string $name): ?string
    {
        $name = basename(str_replace('\\', '/', $name));
        if ($name === '' || $name === '.' || $name === '..') {
            return null;
        }

        return preg_match('/^[A-Za-z0-9._-]{1,120}$/', $name) === 1 ? $name : null;
    }

    private function dir(string $id): ?string
    {
        $id = trim(str_replace('\\', '/', $id), '/');
        if ($id === '') {
            return $this->base;
        }

        $safe = $this->fs->safePath($this->base, $id);

        return $safe === null ? null : $this->base . '/' . $safe;
    }
}
