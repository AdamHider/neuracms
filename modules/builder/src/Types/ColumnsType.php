<?php

declare(strict_types=1);

namespace FlatCms\Modules\Builder\Types;

use FlatCms\Modules\Builder\BlockType;

/**
 * A nested two-column block. Each column holds its own list of child blocks.
 */
final class ColumnsType extends BlockType
{
    public function __construct()
    {
        parent::__construct('columns', 'Две колонки', [
            'left' => ['type' => 'blocks', 'label' => 'Левая колонка', 'default' => []],
            'right' => ['type' => 'blocks', 'label' => 'Правая колонка', 'default' => []],
            'ratio' => ['type' => 'select', 'label' => 'Пропорции', 'options' => ['6-6', '8-4', '4-8'], 'default' => '6-6'],
        ], null, 'Сетка');
    }
}
