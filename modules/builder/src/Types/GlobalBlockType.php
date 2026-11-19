<?php

declare(strict_types=1);

namespace FlatCms\Modules\Builder\Types;

use FlatCms\Modules\Builder\BlockType;

/**
 * Reference to a global block. The stored block carries only a name; the renderer
 * swaps it for the shared block from GlobalLibrary, so editing the global updates
 * every page that references it.
 */
final class GlobalBlockType extends BlockType
{
    public const NAME = 'global';

    public function __construct()
    {
        parent::__construct('global', 'Глобальный элемент', [
            'name' => ['type' => 'string', 'label' => 'Имя глобального элемента', 'default' => ''],
        ], null, 'Общие', 'globe2');
    }
}
