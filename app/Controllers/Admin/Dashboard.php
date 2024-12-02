<?php

namespace App\Controllers\Admin;

class Dashboard extends \App\Controllers\BaseController
{
    private $data = [];
    public function index()
    {
        // Проверка, если пользователь аутентифицирован
        // Если нет, перенаправление на страницу входа
        // Если да, отображение админ-панели
        $this->data['layout'] = 'default';
        $this->data['page_title'] = 'Dashboard';

        $this->data['sections'] = [
            'main' => [
                'rows' => [
                    [
                        'cols' => [ 
                            [
                                'width' => 12,
                                'name' => 'Component',
                                'data' => [
                                    'name' => 'It is a component, dude!'
                                ]
                            ],
                            [
                                'width' => 6,
                                'name' => 'Component',
                                'data' => [
                                    'name' => 'It is a component 2, dude!'
                                ]
                            ]
                        ]
                    ]
                ]
            ]
        ];

        return view('admin/dashboard', $this->data);
    }
}