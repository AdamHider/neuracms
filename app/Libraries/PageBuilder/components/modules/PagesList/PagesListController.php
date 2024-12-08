<?php 
namespace App\Libraries\PageBuilder\Components\Modules\PagesList;

use App\Models\PageModel;

class PagesListController
{
    public function getPagesHtml($component)
    {
        $pageModel = new PageModel();
        $pageCount = !empty($component['properties']['page_count']) ? $component['properties']['page_count'] : 5;
        $pages = $pageModel->findAll(); // В реальном приложении можно использовать метод для ограничения выборки
        $pages = array_slice($pages, 0, $pageCount); // Ограничиваем количество страниц
        $pagesHtml = '';

        foreach ($pages as $page) {
            $pagesHtml .= "<li>{$page['title']}</li>";
        }

        return $pagesHtml;
    }
}

