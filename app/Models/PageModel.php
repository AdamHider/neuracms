<?php

namespace App\Models;

use CodeIgniter\Model;

class PageModel extends Model
{
    protected $table = 'pages';
    protected $primaryKey = 'id';
    protected $allowedFields = ['title', 'content'];
    protected $validationRules = [
        'title' => 'required|is_unique[pages.title,id,{id}]',
        'content' => 'required',
    ];
    protected $validationMessages = [
        'title' => [
            'required' => 'The Title field is required.',
            'is_unique' => 'The Title must be unique. This title already exists.'
        ],
        'content' => [
            'required' => 'The Content field is required.'
        ]
    ];
}