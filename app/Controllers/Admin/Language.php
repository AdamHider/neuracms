<?php

namespace App\Controllers\Admin;

use App\Models\LanguageModel;
use App\Controllers\BaseController;

class Language extends BaseController
{
    public function index()
    {
        $data['settings'] = [
            'layout' => 'admin',
            'menu' => [
                'id' => 2
            ],
            'title' => 'Languages',
            'path' => '/admin/languages/create'
        ];
        $languageModel = new LanguageModel();
        $data['languages'] = $languageModel->findAll();
        return view('admin/languages/index', $data);
    }

    public function create()
    {
        $data['settings'] = [
            'layout' => 'admin',
            'menu' => [
                'id' => 2
            ],
            'title' => 'New Language',
            'path' => '/admin/languages/create'
        ];
        return view('admin/languages/create', $data);
    }

    public function store()
    {
        $languageModel = new LanguageModel();
        $languageData = [
            'name' => $this->request->getVar('name'),
            'code' => $this->request->getVar('code'),
            'flag' => $this->request->getVar('flag'), 
        ];

        if (!$languageModel->save($languageData)) {
            return redirect()->back()->withInput()->with('errors', $languageModel->errors());
        }

        return redirect()->to('/admin/languages')->with('status', 'Language created successfully');
    }

    public function edit($id)
    {
        $data['settings'] = [
            'layout' => 'admin',
            'menu' => [
                'id' => 2
            ],
            'title' => 'Edit Language',
            'path' => '/admin/languages/edit'
        ];
        $languageModel = new LanguageModel();
        $data['language'] = $languageModel->find($id);

        if (empty($data['language'])) {
            throw new \CodeIgniter\Exceptions\PageNotFoundException('Language not found');
        }

        return view('admin/languages/edit', $data);
    }

    public function update($id)
    {
        $languageModel = new LanguageModel();
        $languageData = [
            'id'   => $id,
            'name' => $this->request->getVar('name'),
            'code' => $this->request->getVar('code'),
            'flag' => $this->request->getVar('flag'), 
        ];

        if (!$languageModel->save($languageData)) {
            return redirect()->back()->withInput()->with('errors', $languageModel->errors());
        }

        return redirect()->to('/admin/languages')->with('status', 'Language updated successfully');
    }

    public function delete($id)
    {
        $languageModel = new LanguageModel();

        if (!$languageModel->delete($id)) {
            return redirect()->back()->with('errors', $languageModel->errors());
        }

        return redirect()->to('/admin/languages')->with('status', 'Language deleted successfully');
    }
}
