<?php

namespace App\Controllers;

use App\Models\PageModel;
use CodeIgniter\Controller;
use App\Libraries\PageBuilder\PageBuilder;

class Page extends Controller
{

    public $data;
    public function view($slugPath)
    {
        $pageBuilder = new PageBuilder();
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
        

        $data['settings'] = [
            'layout' => 'default',
            'menu' => [
                'id' => 1
            ],
            'title' => $page['title'],
            'path' => $page['slug']
        ];
        $data['page'] = $page;

        $data['page']['content'] = $pageBuilder->buildHtmlFromJson($page['json_content']);

        return view('page', $data);
    }
}
