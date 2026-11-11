<?php

declare(strict_types=1);

namespace FlatCms\Admin;

use FlatCms\Content\Markdown;
use FlatCms\Support\Fs;

/**
 * Reads the project documentation (docs/*.md) so the admin panel can show it
 * without leaving the site. Documentation stays a plain folder of Markdown.
 */
final class Docs
{
    public function __construct(private readonly Fs $fs)
    {
    }

    /**
     * Doc file name (without .md) => human title, taken from the first heading.
     *
     * @return array<string, string>
     */
    public function all(): array
    {
        $docs = [];
        foreach ($this->fs->listDir('docs') as $file) {
            if (!str_ends_with($file, '.md')) {
                continue;
            }
            $name = substr($file, 0, -3);
            $docs[$name] = $this->title($name);
        }

        ksort($docs);

        return $docs;
    }

    public function has(string $name): bool
    {
        return $this->safe($name) !== null && $this->fs->isFile('docs/' . $name . '.md');
    }

    /**
     * Raw Markdown of a doc.
     */
    public function raw(string $name): string
    {
        $safe = $this->safe($name);
        if ($safe === null) {
            return '';
        }

        return $this->fs->read('docs/' . $safe . '.md');
    }

    /**
     * Doc rendered to HTML.
     */
    public function html(string $name): string
    {
        return (new Markdown())->parse($this->raw($name));
    }

    public function title(string $name): string
    {
        foreach (explode("\n", $this->raw($name)) as $line) {
            if (str_starts_with($line, '# ')) {
                return trim(substr($line, 2));
            }
        }

        return ucfirst(str_replace(['-', '_'], ' ', $name));
    }

    private function safe(string $name): ?string
    {
        return preg_match('/^[a-z0-9][a-z0-9_-]{0,60}$/', $name) === 1 ? $name : null;
    }
}
