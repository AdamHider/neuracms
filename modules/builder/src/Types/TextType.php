<?php

declare(strict_types=1);

namespace FlatCms\Modules\Builder\Types;

use FlatCms\Modules\Builder\BlockType;

final class TextType extends BlockType
{
    public function __construct()
    {
        parent::__construct('text', 'Текст', [
            'body' => ['type' => 'markdown', 'label' => 'Текст', 'default' => ''],
        ], null, 'Текст');
    }
}
