<?php

namespace App\Controllers\Admin;

use App\Models\PageModel;
use App\Models\LanguageModel;
use App\Controllers\BaseController;
use App\Libraries\PageBuilder\PageBuilder;

class Page extends BaseController
{
    public function index()
    {
        $data['settings'] = [
            'layout' => 'admin',
            'menu' => [
                'id' => 2
            ],
            'title' => 'Pages',
            'path' => '/admin/pages'
        ];
        $pageModel = new PageModel();
        $data['pages'] = $pageModel->getTree();
        return view('admin/pages/index', $data);
    }
    public function form($id = null)
    {
        $pageModel = new PageModel();
        $pageBuilder = new PageBuilder();
        $languageModel = new LanguageModel();

        $data['languages'] = $languageModel->findAll();
        $data['pages'] = array_filter($pageModel->orderBy('created_at', 'ASC')->findAll(), function($p) use ($id) {
            return $p['id'] !== $id && $p['parent_id'] !== $id;
        });
        $data['settings'] = [
            'layout' => 'admin_edit',
            'menu' => [
                'id' => 2
            ],
            'path' => '/admin/pages/form'
        ];

        $data['components'] = $pageBuilder->listComponents();

        if ($id) {
            $data['page'] = $pageModel->find($id);
            if (empty($data['page'])) {
                throw new \CodeIgniter\Exceptions\PageNotFoundException('Page not found');
            }
            $data['action'] = 'update/' . $id;
            $data['button_text'] = 'Update';
            $data['settings']['title'] = 'Edit page'; 
        } else {
            $data['page'] = ['title' => '', 'slug' => '', 'meta_description' => '', 'language_id' => '', 'parent_id' => '', 'content' => ''];
            $data['action'] = 'store';
            $data['button_text'] = 'Create';
            $data['settings']['title'] = 'New page';
            $data['page']['json_content'] = json_encode([[
                'type' => 'workspace',
                'code' => 'workspace',
                'group' => 'layout',
                'accept' => 'all',
                'lock' => true,
                'properties' => [
                    'title' => 'My Workspace',
                    'backgroundColor' => '#ffffff'
                ],
                'children' => []
            ]]);
        }

        return view('admin/pages/form', $data);
    }

    public function store()
    {
        return $this->save();
    }

    public function update($id)
    {
        return $this->save($id);
    }

    private function save($id = null)
    {
        $pageModel = new PageModel();
        $data = [
            'slug' => $this->request->getVar('slug'),
            'parent_id' => $this->request->getVar('parent_id') ?: null,
            'language_id' => $this->request->getVar('language_id'),
            'title' => $this->request->getVar('title'),
            'content' => $this->request->getVar('content'),
            'json_content' => $this->request->getVar('json_content'),
            'meta_description' => $this->request->getVar('meta_description'),
        ];

        if ($id) {
            $data['id'] = $id;
            if (!$pageModel->save($data)) {
                return redirect()->back()->with('errors', $pageModel->errors());
            }
            return redirect()->back()->with('status', 'Page created successfully');
        } else {
            if (!$pageModel->insert($data)) {
                return redirect()->back()->with('errors', $pageModel->errors());
            }
            return redirect()->back()->with('status', 'Page updated successfully');
        }
    }
    public function delete($id)
    {
        $pageModel = new PageModel();

        if (!$pageModel->delete($id)) {
            return redirect()->back()->with('errors', $pageModel->errors());
        }
        return redirect()->to('/admin/pages')->with('status', 'Page deleted successfully');
    }
}
