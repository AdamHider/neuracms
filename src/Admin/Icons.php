<?php

declare(strict_types=1);

namespace FlatCms\Admin;

/**
 * Semantic icon names mapped to Bootstrap Icons classes. Templates ask for a
 * meaning ("edit"), not a glyph, so the icon set can be swapped in one place.
 */
final class Icons
{
    /** @var array<string, string> */
    private const MAP = [
        'dashboard' => 'speedometer2',
        'content' => 'files',
        'add' => 'plus-lg',
        'types' => 'tags',
        'users' => 'people',
        'tools' => 'wrench-adjustable',
        'builder' => 'grid-1x2',
        'media' => 'images',
        'history' => 'clock-history',
        'edit' => 'pencil',
        'delete' => 'trash',
        'move' => 'arrows-move',
        'save' => 'check-lg',
        'view' => 'box-arrow-up-right',
        'logout' => 'box-arrow-right',
        'login' => 'box-arrow-in-right',
        'search' => 'search',
        'filter' => 'funnel',
        'settings' => 'sliders',
        'drag' => 'grip-vertical',
        'up' => 'arrow-up',
        'down' => 'arrow-down',
        'copy' => 'copy',
        'close' => 'x-lg',
        'columns' => 'columns-gap',
        'section' => 'bounding-box',
        'warning' => 'exclamation-triangle',
        'info' => 'info-circle',
        'home' => 'house',
        'shop' => 'bag',
        'link' => 'link-45deg',
        'image' => 'image',
        'text' => 'font',
        'heading' => 'type-h1',
        'button' => 'hand-index',
        'html' => 'code-slash',
        'products' => 'box-seam',
        'check' => 'check2',
        'grid' => 'grid-3x3-gap',
        'spacer' => 'arrows-expand-vertical',
        'divider' => 'dash-lg',
        'quote' => 'quote',
        'elements' => 'collection',
        'list' => 'list-ul',
    ];

    public static function name(string $key): string
    {
        return self::MAP[$key] ?? $key;
    }

    /**
     * Render an icon tag. Unknown keys fall back to the key itself, so
     * "bi bi-<key>" still works for any Bootstrap Icons name.
     */
    public static function render(string $key, string $class = '', string $title = ''): string
    {
        $classes = trim('bi bi-' . self::name($key) . ' ' . $class);
        $html = '<i class="' . htmlspecialchars($classes, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '" aria-hidden="true"';
        if ($title !== '') {
            $html .= ' title="' . htmlspecialchars($title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"';
        }

        return $html . '></i>';
    }
}
