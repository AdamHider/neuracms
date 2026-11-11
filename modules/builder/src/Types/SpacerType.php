<?php

declare(strict_types=1);

namespace FlatCms\Modules\Builder\Types;

use FlatCms\Modules\Builder\BlockType;

final class SpacerType extends BlockType
{
    public function __construct()
    {
        parent::__construct('spacer', 'Отступ', [
            'size' => ['type' => 'select', 'label' => 'Высота', 'options' => ['sm', 'md', 'lg', 'xl'], 'default' => 'md'],
        ], null, 'Разметка', 'spacer');
    }
}
