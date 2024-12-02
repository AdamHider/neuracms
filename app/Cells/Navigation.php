<?php

namespace App\Cells;

use CodeIgniter\View\Cells\Cell;

class Navigation extends Cell
{
    public $data;
    public function render(): string
    {
        $this->data['items'] = [
            [
                'title' => 'Home',
                'link'  => '/admin'
            ],
            [
                'title' => 'Dashboard',
                'link'  => '/dashboard'
            ]
        ];
        return view('cells/navigation', $this->data);
    }
}