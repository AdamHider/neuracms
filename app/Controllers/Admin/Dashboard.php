<?php

namespace App\Controllers\Admin;

class Dashboard extends \App\Controllers\BaseController
{
    public function index()
    {
        // Проверка, если пользователь аутентифицирован
        // Если нет, перенаправление на страницу входа
        // Если да, отображение админ-панели

        return view('admin/dashboard');
    }
}