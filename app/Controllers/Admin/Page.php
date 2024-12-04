<?php

namespace App\Controllers\Admin;

use App\Models\PageModel;

class Page extends  \App\Controllers\BaseController
{
    public $data;
    public function __construct()
    {
        if (!session()->get('logged_in')) {
            return redirect()->to('/auth/login')->send();
        }
    }

    public function index()
    {
        $model = new PageModel();
        $this->data['settings'] = [
            'layout' => 'admin',
            'menu' => [
                'id' => 2
            ],
            'title' => 'Pages',
            'path' => '/admin/pages'
        ];
        $this->data['pages'] = $model->findAll();
        return view('admin/pages/index', $this->data);
    }

    public function create()
    {
        $this->data['settings'] = [
            'layout' => 'admin',
            'menu' => [
                'id' => 2
            ],
            'title' => 'New Page',
            'path' => '/admin/pages/create'
        ];
        
        return view('admin/pages/create', $this->data);
    }

    public function store()
    {
        $model = new PageModel();

        $data = [
            'title' => $this->request->getVar('title'),
            'content' => $this->request->getVar('content'),
        ];

        if (!$this->validate($model->validationRules)) {
            $this->data['settings'] = [
                'layout' => 'admin',
                'menu' => [
                    'id' => 2
                ],
                'title' => 'New Page',
                'path' => '/admin/pages/create'
            ];
            $this->data['validation'] = $this->validator;
            return view('admin/pages/create', $this->data);
        }

        $model->save($data);

        return redirect()->to('/admin/pages');
    
    } 

    public function edit($id)
    {
        $model = new PageModel();
        $this->data['settings'] = [
            'layout' => 'admin',
            'menu' => [
                'id' => 2
            ],
            'title' => 'Edit Page',
            'path' => '/admin/pages/edit'
        ];
        $this->data['page'] = $model->find($id);

        return view('admin/pages/edit', $this->data);
    }

    public function update($id)
    {
        $model = new PageModel();

        $data = [
            'title' => $this->request->getVar('title'),
            'content' => $this->request->getVar('content'),
        ];

        $validationRules = $model->validationRules;
        $validationRules['title'] = str_replace('{id}', $id, subject: $validationRules['title']);
       
        if (!$this->validate(rules: $validationRules)) {
            $this->data['settings'] = [
                'layout' => 'admin',
                'menu' => [
                    'id' => 2
                ],
                'title' => 'Edit Page',
                'path' => '/admin/pages/edit'
            ];
            $this->data['page'] = $model->find($id);
            $this->data['validation'] = $this->validator;
            return view('admin/pages/edit', $this->data);
        }
        $model->update($id, $data);

        return redirect()->to('/admin/pages');
    }

    public function delete($id)
    {
        $model = new PageModel();
        $model->delete($id);

        return redirect()->to('/admin/pages');
    }
}