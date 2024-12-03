<?php

namespace App\Cells;

use CodeIgniter\View\Cells\Cell;

class Menu extends Cell
{
    public $data;
    public function render(): string
    {
        $menus = [
            '1' => [
                [
                    'title' => 'Home',
                    'link'  => '/admin',
                    'icon'  => ''
                ],
                [
                    'title' => 'Dashboard',
                    'link'  => '/dashboard'
                ]
            ], 
            '2' => [
                [
                    'title' => 'Dashboard',
                    'link'  => '/admin/dashboard',
                    'icon'  => 'speedometer2',
                    'type'  => 'menu'
                ],
                [
                    'title' => 'Pages',
                    'link'  => '/admin/pages',
                    'icon'  => 'files',
                    'type'  => 'menu'
                ],
                [
                    'type'  => 'separator'
                ],
                [
                    'title' => 'Media',
                    'link'  => '/admin/dashboard',
                    'icon'  => 'images',
                    'type'  => 'menu'
                ],
            ]
        ];
        $this->data['items'] =  $menus[$this->data['menu']['id']];
        $this->setActive();
        return view('cells/menu', $this->data);
    }

    private function setActive()
    {
        foreach($this->data['items'] as &$menu){
            if(isset($menu['link']) && $menu['link'] == $this->data['current_uri']){
                
                $menu['is_active'] = true;
                return;
            }
        }
    }
}