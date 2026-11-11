<?php

declare(strict_types=1);

namespace FlatCms\Modules\Builder\Types;

use FlatCms\Modules\Builder\BlockType;

final class DividerType extends BlockType
{
    public function __construct()
    {
        parent::__construct('divider', 'Разделитель', [
            'style' => ['type' => 'select', 'label' => 'Стиль', 'options' => ['solid', 'dashed', 'dotted'], 'default' => 'solid'],
        ], null, 'Разметка', 'divider');
    }
}
