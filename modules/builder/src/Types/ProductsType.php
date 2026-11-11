<?php

declare(strict_types=1);

namespace FlatCms\Modules\Builder\Types;

use FlatCms\Modules\Builder\BlockType;

/**
 * Product grid. Reads from the shop module when it is enabled; renders a note
 * otherwise so a page never breaks.
 */
final class ProductsType extends BlockType
{
    public function __construct()
    {
        parent::__construct('products', 'Товары', [
            'title' => ['type' => 'string', 'label' => 'Заголовок', 'default' => ''],
            'limit' => ['type' => 'int', 'label' => 'Сколько товаров', 'default' => 6],
            'tags' => ['type' => 'string', 'label' => 'Теги через запятую', 'default' => ''],
        ], null, 'Магазин', 'products');
    }
}
