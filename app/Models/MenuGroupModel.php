<?php

namespace App\Models;

use CodeIgniter\Model;

class MenuGroupModel extends Model
{
    protected $table = 'menu_groups';
    protected $primaryKey = 'id';
    protected $allowedFields = ['name'];

    protected $validationRules = [
        'name' => 'required|is_unique[menu_groups.name,id,{id}]',
    ];

    protected $validationMessages = [
        'name' => [
            'required' => 'The Name field is required.',
            'is_unique' => 'The Name must be unique. This name already exists.'
        ]
    ];
}
