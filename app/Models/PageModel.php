<?php

namespace App\Models;

use CodeIgniter\Model;

class PageModel extends Model
{
    protected $table = 'pages';
    protected $primaryKey = 'id';
    protected $allowedFields = ['slug', 'parent_id', 'language_id', 'title', 'content', 'meta_description'];

    protected $validationRules = [
        'id' => 'numeric',
        'title' => 'required',
        'slug' => 'required|is_unique[pages.slug, id, {id}]'
    ];
    protected $validationMessages = [
        'slug' => [
            'is_unique' => 'The slug  must be unique.'
        ]
    ];
    public function getTree()
    {
        $items = $this->orderBy('created_at', 'ASC')->findAll();
        return $this->buildTree($items);
    }

    private function buildTree(array $elements, $parentId = null)
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
