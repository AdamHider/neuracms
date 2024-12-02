<?php

namespace App\Controllers;

class Admin extends BaseController
{

    public function index()
    {
        // Проверка, если пользователь аутентифицирован
        // Если нет, перенаправление на страницу входа
        // Если да, отображение админ-панели
        return redirect()->to(site_url('admin/dashboard')); 
    }
}