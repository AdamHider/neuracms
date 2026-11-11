<?php

declare(strict_types=1);

namespace FlatCms\Modules\Builder\Types;

use FlatCms\Modules\Builder\BlockType;

final class QuoteType extends BlockType
{
    public function __construct()
    {
        parent::__construct('quote', 'Цитата', [
            'text' => ['type' => 'text', 'label' => 'Текст', 'default' => ''],
            'author' => ['type' => 'string', 'label' => 'Автор', 'default' => ''],
        ], null, 'Текст', 'quote');
    }
}
