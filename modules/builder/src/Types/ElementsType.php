<?php

declare(strict_types=1);

namespace FlatCms\Modules\Builder\Types;

use FlatCms\Modules\Builder\BlockType;

/**
 * Element list particle. Shows items of a chosen element type as cards, mapping
 * the element's own properties onto the card fields (title, description, price,
 * link, image). The mapping is stored per particle, so the same element type can
 * be presented differently on different pages.
 */
final class ElementsType extends BlockType
{
    public function __construct()
    {
        parent::__construct('elements', 'Список элементов', [
            'element_type' => ['type' => 'string', 'label' => 'Тип элемента', 'default' => ''],
            'title' => ['type' => 'string', 'label' => 'Заголовок блока', 'default' => ''],
            'limit' => ['type' => 'int', 'label' => 'Сколько элементов', 'default' => 6],
            'sort' => ['type' => 'select', 'label' => 'Сортировка', 'options' => ['order', 'title', 'date'], 'default' => 'order'],
            'direction' => ['type' => 'select', 'label' => 'Порядок', 'options' => ['asc', 'desc'], 'default' => 'asc'],
            // Property mapping: which element property feeds each card field.
            'map_title' => ['type' => 'string', 'label' => 'Название → свойство', 'default' => 'title'],
            'map_description' => ['type' => 'string', 'label' => 'Описание → свойство', 'default' => ''],
            'map_price' => ['type' => 'string', 'label' => 'Цена → свойство', 'default' => ''],
            'map_link' => ['type' => 'string', 'label' => 'Ссылка → свойство', 'default' => ''],
            'map_image' => ['type' => 'string', 'label' => 'Изображение → свойство', 'default' => ''],
            'columns' => ['type' => 'select', 'label' => 'Колонок', 'options' => ['2', '3', '4'], 'default' => '3'],
        ], null, 'Элементы', 'list');
    }
}
