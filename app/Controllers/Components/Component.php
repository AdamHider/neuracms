<?php

namespace App\Controllers\Components;

class Component extends \App\Controllers\BaseController
{
    private $data = [];
    public function render($data)
    {
        // Проверка, если пользователь аутентифицирован
        // Если нет, перенаправление на страницу входа
        // Если да, отображение админ-панели
        
        return view('partials/component', data: $data);
    }
}