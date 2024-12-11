<?php

namespace App\Models;

use CodeIgniter\Model;

class PageModel extends Model
{
    protected $table = 'pages';
    protected $primaryKey = 'id';
    protected $allowedFields = ['slug', 'parent_id', 'language_id', 'title', 'content', 'json_content', 'meta_description'];

    protected $validationRules = [
        'id' => 'numeric',
        'title' => 'required',
        'slug' => 'required|is_unique[pages.slug, id, {id}]'
    ];
    protected $validationMessages = [
        'slug' => [
            'is_unique' => 'The slug  must be unique.'
        ]
    ];
    public function getTree()
    {
        $items = $this->orderBy('created_at', 'ASC')->findAll();
        return $this->buildTree($items);
    }

    private function buildTree(array $elements, $parentId = null)
    {
        $branch = [];

        foreach ($elements as $element) {
            if ($element['parent_id'] == $parentId) {
                $children = $this->buildTree($elements, $element['id']);
                if ($children) {
                    $element['children'] = $children;
                }
                $branch[] = $element;
            }
        }

        return $branch;
    }

    public function generateUniqueSlug($title)
    {
        // Генерируем первоначальный slug
        $slug = $this->generateSlug($title);

        // Проверка существования slug в базе данных
        $uniqueSlug = $slug;
        $count = 1;
        while ($this->isSlugExists($uniqueSlug)) {
            // Если slug существует, добавляем число к нему
            $uniqueSlug = $slug . '-' . $count;
            $count++;
        }

        return $uniqueSlug;
    }

    // Функция для генерации slug с учетом кириллицы
    private function generateSlug($title)
    {
        // Преобразуем название в нижний регистр
        $title = mb_strtolower($title);

        // Замена кириллических символов на латиницу
        $cyrillic = array('а', 'б', 'в', 'г', 'д', 'е', 'ё', 'ж', 'з', 'и', 'й', 'к', 'л', 'м', 'н', 'о', 'п', 'р', 'с', 'т', 'у', 'ф', 'х', 'ц', 'ч', 'ш', 'щ', 'ъ', 'ы', 'ь', 'э', 'ю', 'я');
        $latin = array('a', 'b', 'v', 'g', 'd', 'e', 'yo', 'zh', 'z', 'i', 'y', 'k', 'l', 'm', 'n', 'o', 'p', 'r', 's', 't', 'u', 'f', 'kh', 'ts', 'ch', 'sh', 'shch', '', 'y', '', 'e', 'yu', 'ya');
        $title = str_replace($cyrillic, $latin, $title);

        // Удаление всех символов, кроме букв, цифр и дефисов
        $title = preg_replace('/[^a-z0-9-]/', '-', $title);

        // Замена последовательных дефисов на один
        $title = preg_replace('/-+/', '-', $title);

        // Удаление ведущих и завершающих дефисов
        $title = trim($title, '-');

        return $title;
    }
    // Функция для проверки существования slug в базе данных
    private function isSlugExists($slug)
    {
        return $this->where('slug', $slug)->countAllResults() > 0;
    }
    // Функция для формирования пути страницы
    public function generatePagePath($pageId)
    {
        $page = $this->find($pageId);
        $path = $page['slug'];

        while ($page['parent_id'] !== null) {
            $page = $this->find($page['parent_id']);
            $path = $page['slug'] . '/' . $path;
        }

        return $path;
    }

}
