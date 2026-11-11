<?php

declare(strict_types=1);

namespace FlatCms\Modules\Builder\Types;

use FlatCms\Modules\Builder\BlockType;

/**
 * Media particle: an image (or other attachment) uploaded from the builder. The
 * "file" field is rendered as an upload control by the editor, so media is added
 * without leaving the constructor.
 */
final class MediaType extends BlockType
{
    public function __construct()
    {
        parent::__construct('media', 'Медиа', [
            'src' => ['type' => 'file', 'label' => 'Файл', 'default' => ''],
            'alt' => ['type' => 'string', 'label' => 'Alt', 'default' => ''],
            'caption' => ['type' => 'string', 'label' => 'Подпись', 'default' => ''],
            'width' => ['type' => 'int', 'label' => 'Ширина, %', 'default' => 100],
        ], null, 'Медиа', 'media');
    }
}
