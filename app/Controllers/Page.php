<?php

namespace App\Controllers;

use App\Models\PageModel;
use CodeIgniter\Controller;

class Page extends Controller
{

    public $data;
    public function view($slugPath)
    {
        $model = new PageModel();

        $slugs = explode('/', $slugPath);
        $page = null;

        foreach ($slugs as $slug) {
            if ($page) {
                $page = $model->where('slug', $slug)->where('parent_id', $page['id'])->first();
            } else {
                $page = $model->where('slug', $slug)->where('parent_id', null)->first();
            }

            if (!$page) {
                throw new \CodeIgniter\Exceptions\PageNotFoundException('Page not found');
            }
        }
        $this->data['settings'] = [
            'layout' => 'default',
            'menu' => [
                'id' => 1
            ],
            'title' => $page['title'],
            'path' => $page['slug']
        ];
        $this->data['page'] = $page;



        return view('page', $this->data);
    }
}
