<?php

declare(strict_types=1);

namespace FlatCms\Modules\Builder\Types;

use FlatCms\Modules\Builder\BlockType;

final class HeadingType extends BlockType
{
    public function __construct()
    {
        parent::__construct('heading', 'Заголовок', [
            'text' => ['type' => 'string', 'label' => 'Текст', 'default' => 'Заголовок'],
            'level' => ['type' => 'select', 'label' => 'Уровень', 'options' => ['h1', 'h2', 'h3'], 'default' => 'h2'],
        ], null, 'Текст', 'heading');
    }
}
