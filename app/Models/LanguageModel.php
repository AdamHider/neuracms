<?php

namespace App\Models;

use CodeIgniter\Model;

class LanguageModel extends Model
{
    protected $table = 'languages';
    protected $primaryKey = 'id';
    protected $allowedFields = ['name', 'code'];

    protected $validationRules = [
        'id'    => 'max_length[19]',
        'name' => 'required|string|max_length[255]',
        'code' => 'required|string|max_length[5]|is_unique[languages.code, id, {id}]',
        'flag' => 'string|max_length[255]',
    ];

    protected $validationMessages = [
        'code' => [
            'is_unique' => 'The language code must be unique.'
        ],
        'flag' => [
            'required' => 'The flag field is required.'
        ]
    ];
}
