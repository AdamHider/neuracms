<?php

declare(strict_types=1);

namespace FlatCms\Modules\Builder\Types;

use FlatCms\Modules\Builder\BlockType;

final class ImageType extends BlockType
{
    public function __construct()
    {
        parent::__construct('image', 'Изображение', [
            'src' => ['type' => 'string', 'label' => 'Файл', 'default' => ''],
            'alt' => ['type' => 'string', 'label' => 'Подпись', 'default' => ''],
            'width' => ['type' => 'int', 'label' => 'Ширина, %', 'default' => 100],
        ], null, 'Медиа');
    }
}
