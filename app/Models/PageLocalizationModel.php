<?php

namespace App\Models;

use CodeIgniter\Model;

class PageLocalizationModel extends Model
{
    protected $table = 'page_localizations';
    protected $primaryKey = 'id';
    protected $allowedFields = ['page_id', 'language', 'alias', 'title', 'content', 'meta_description'];

    protected $validationRules = [
        'page_id' => 'required|integer',
        'language' => 'required|string|max_length[5]',
        'alias' => 'required|string|max_length[255]|is_unique[page_localizations.alias,id,{id}]',
        'title' => 'required|string|max_length[255]',
        'content' => 'required',
        'meta_description' => 'required|string|max_length[255]',
    ];
    
    protected $validationMessages = [
        'alias' => [
            'is_unique' => 'The alias must be unique.'
        ]
    ];
}
