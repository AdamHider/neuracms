<?php

namespace App\Controllers\Admin;

use App\Models\MenuItemModel;
use CodeIgniter\Controller;

class Menu extends Controller
{
    public $data; 
    public function index()
    {
        $itemModel = new MenuItemModel();

        $this->data['settings'] = [
            'layout' => 'admin',
            'menu' => [
                'id' => 2
            ],
            'title' => 'Menu',
            'path' => '/admin/menus'
        ];
        $this->data['items'] = $itemModel->getTree();

        return view('admin/menus/index', $this->data);
    }

    public function create()
    {
        $itemModel = new MenuItemModel();
        $data['settings'] = [
            'layout' => 'admin',
            'menu' => [
                'id' => 2
            ],
            'title' => 'New Menu',
            'path' => '/admin/menus'
        ];
        $data['items'] = $itemModel->orderBy('order', 'ASC')->findAll();

        $data['parent_id'] = $this->request->getVar('parent_id') ?? '0';
        
        return view('admin/menus/create', $data);
    }

    public function store()
    {
        $itemModel = new MenuItemModel();

        $data = [
            'title' => $this->request->getVar('title'),
            'url' => $this->request->getVar('url'),
            'order' => $this->request->getVar('order'),
            'parent_id' => $this->request->getVar('parent_id'),
        ];

        if (!$itemModel->insert($data)) {
            return redirect()->back()->withInput()->with('errors', $itemModel->errors());
        }

        return redirect()->to('/admin/menus')->with('status', 'Menu item created successfully');
    }
}
