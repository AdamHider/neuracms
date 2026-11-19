<?php

declare(strict_types=1);

namespace FlatCms\Modules\Builder;

/**
 * Alignment vocabulary shared by every structural node (section, row, column).
 *
 * Each node is a flex container, so the same two properties work everywhere:
 * "justify" is the main axis and "align" the cross axis. For a section and a
 * column the main axis is vertical (they stack their children), for a row it is
 * horizontal. That is what makes the editor universal: one pair of dropdowns
 * per node, no node-specific layout options.
 */
final class Structure
{
    /** @var array<string, string> value => CSS keyword */
    public const JUSTIFY = [
        'start' => 'flex-start',
        'center' => 'center',
        'end' => 'flex-end',
        'between' => 'space-between',
        'around' => 'space-around',
        'evenly' => 'space-evenly',
    ];

    /** @var array<string, string> value => CSS keyword */
    public const ALIGN = [
        'start' => 'flex-start',
        'center' => 'center',
        'end' => 'flex-end',
        'stretch' => 'stretch',
        'baseline' => 'baseline',
    ];

    public static function justify(mixed $value): string
    {
        $value = is_string($value) ? trim($value) : '';

        return self::JUSTIFY[$value] ?? '';
    }

    public static function align(mixed $value): string
    {
        $value = is_string($value) ? trim($value) : '';

        return self::ALIGN[$value] ?? '';
    }

    /**
     * Inline style declaring whichever alignment values are set. Returns an empty
     * string when the node uses the CSS defaults, so untouched nodes keep the
     * plain markup they always had.
     *
     * @param array<string, mixed> $settings
     */
    public static function style(array $settings): string
    {
        $declarations = [];
        if (($justify = self::justify($settings['justify'] ?? null)) !== '') {
            $declarations[] = '--g-justify:' . $justify;
        }
        if (($align = self::align($settings['align'] ?? null)) !== '') {
            $declarations[] = '--g-align:' . $align;
        }

        return $declarations === [] ? '' : ' style="' . implode(';', $declarations) . '"';
    }

    /**
     * A node's CSS class list: its base class, its custom classes and nothing
     * else. Custom classes are the author's own hooks ("my-hero"), so they are
     * sanitised to the characters a class name may contain.
     */
    public static function classes(string $base, string ...$extra): string
    {
        $classes = [$base];
        foreach ($extra as $value) {
            foreach (preg_split('/\s+/', trim($value)) ?: [] as $class) {
                $class = (string) preg_replace('/[^A-Za-z0-9_-]/', '', $class);
                if ($class !== '' && !in_array($class, $classes, true)) {
                    $classes[] = $class;
                }
            }
        }

        return implode(' ', $classes);
    }
}
