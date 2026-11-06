<?php

declare(strict_types=1);

namespace FlatCms\Content;

use Parsedown;

/**
 * Thin wrapper over the Markdown parser so it can be swapped without touching callers.
 */
final class Markdown
{
    private static ?Parsedown $parser = null;

    public function parse(string $markdown): string
    {
        if (trim($markdown) === '') {
            return '';
        }

        return $this->parser()->text($markdown);
    }

    private function parser(): Parsedown
    {
        return self::$parser ??= new Parsedown();
    }
}
