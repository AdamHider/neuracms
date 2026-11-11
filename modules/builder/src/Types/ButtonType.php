<?php

declare(strict_types=1);

namespace FlatCms\Modules\Builder\Types;

use FlatCms\Modules\Builder\BlockType;

final class ButtonType extends BlockType
{
    public function __construct()
    {
        parent::__construct('button', 'Кнопка', [
            'text' => ['type' => 'string', 'label' => 'Текст', 'default' => 'Подробнее'],
            'url' => ['type' => 'string', 'label' => 'Ссылка', 'default' => '/'],
            'style' => ['type' => 'select', 'label' => 'Стиль', 'options' => ['primary', 'outline'], 'default' => 'primary'],
        ], null, 'Текст', 'button');
    }
}
