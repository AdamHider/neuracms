<?php

declare(strict_types=1);

namespace FlatCms\Modules\Builder\Types;

use FlatCms\Modules\Builder\BlockType;

/**
 * Menu particle. Renders navigation from the site's content items, so the header
 * and footer navigation is edited as content rather than hard-coded in a theme.
 *
 * Source modes:
 *  - pages: items of a chosen content type (default "page"), ordered by "order",
 *    optionally narrowed by any number of property filters (for example a
 *    "Показывать в меню" flag created on the page type).
 *  - custom: a plain list of "Title | /url" lines typed in the particle.
 */
final class MenuType extends BlockType
{
    public function __construct()
    {
        parent::__construct('menu', 'Меню', [
            'source' => ['type' => 'select', 'label' => 'Источник', 'options' => ['pages', 'custom'], 'default' => 'pages'],
            'content_type' => ['type' => 'string', 'label' => 'Тип контента', 'default' => 'page'],
            'root' => ['type' => 'string', 'label' => 'Родительский элемент (id)', 'default' => ''],
            'depth' => ['type' => 'int', 'label' => 'Глубина вложенности', 'default' => 1],
            'filters' => ['type' => 'filters', 'label' => 'Фильтры по свойствам', 'default' => []],
            'items' => ['type' => 'text', 'label' => 'Свои ссылки (Заголовок | /адрес, по строке)', 'default' => ''],
            'class' => ['type' => 'string', 'label' => 'CSS-класс', 'default' => 'site-nav'],
        ], null, 'Навигация', 'menu-button-wide');
    }
}
