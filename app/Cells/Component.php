<?php

namespace App\Cells;

use CodeIgniter\View\Cells\Cell;

class Component extends Cell
{
    public $data;
    public function render(): string
    {
        return view('cells/component', $this->data);
    }
}