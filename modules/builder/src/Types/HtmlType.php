<?php

declare(strict_types=1);

namespace FlatCms\Modules\Builder\Types;

use FlatCms\Modules\Builder\BlockType;

final class HtmlType extends BlockType
{
    public function __construct()
    {
        parent::__construct('html', 'HTML', [
            'html' => ['type' => 'text', 'label' => 'HTML', 'default' => ''],
        ], null, 'Расширенное', 'html');
    }
}
