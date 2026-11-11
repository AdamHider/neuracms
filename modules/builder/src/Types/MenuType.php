<?php

declare(strict_types=1);

namespace FlatCms\Modules\Builder\Types;

use FlatCms\Modules\Builder\BlockType;

/**
 * Menu particle. Renders navigation from the "menu-item" elements, so the header
 * and footer navigation is edited as content rather than hard-coded in a theme.
 *
 * Source modes:
 *  - menu: the menu-item elements, ordered by "order" (nesting through children).
 *  - pages: the routable pages of the site, ordered by "order".
 *  - custom: a plain list of "Title | /url" lines typed in the particle.
 */
final class MenuType extends BlockType
{
    public function __construct()
    {
        parent::__construct('menu', 'Меню', [
            'source' => ['type' => 'select', 'label' => 'Источник', 'options' => ['menu', 'pages', 'custom'], 'default' => 'menu'],
            'root' => ['type' => 'string', 'label' => 'Родительский пункт (для пунктов меню)', 'default' => ''],
            'depth' => ['type' => 'int', 'label' => 'Глубина вложенности', 'default' => 1],
            'items' => ['type' => 'text', 'label' => 'Свои ссылки (Заголовок | /адрес, по строке)', 'default' => ''],
            'class' => ['type' => 'string', 'label' => 'CSS-класс', 'default' => 'site-nav'],
        ], null, 'Навигация', 'menu-button-wide');
    }
}
