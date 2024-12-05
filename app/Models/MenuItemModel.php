<?php

namespace App\Models;

use CodeIgniter\Model;

class MenuItemModel extends Model
{
    protected $table = 'menu_items';
    protected $primaryKey = 'id';
    protected $allowedFields = ['title', 'url', 'order', 'parent_id'];

    protected $validationRules = [
        'id'    => 'max_length[19]',
        'title' => 'required|is_unique[menu_items.title,id,{id}]',
        'url' => 'required|valid_url|is_unique[menu_items.url,id,{id}]',
        'order' => 'required|integer',
        'parent_id' => 'permit_empty|integer',
    ];

    protected $validationMessages = [
        'title' => [
            'required' => 'The Title field is required.'
        ],
        'url' => [
            'required' => 'The URL field is required.',
            'valid_url' => 'The URL must be valid.'
        ],
        'order' => [
            'required' => 'The Order field is required.',
            'integer' => 'The Order must be an integer.'
        ],
        'parent_id' => [
            'integer' => 'The Parent ID must be an integer.'
        ]
    ];
    public function getTree()
    {
        $items = $this->orderBy('order', 'ASC')->findAll();
        return $this->buildTree($items);
    }

    private function buildTree(array $elements, $parentId = 0)
    {
        $branch = [];

        foreach ($elements as $element) {
            if ($element['parent_id'] == $parentId) {
                $children = $this->buildTree($elements, $element['id']);
                if ($children) {
                    $element['children'] = $children;
                }
                $branch[] = $element;
            }
        }

        return $branch;
    }
}
