<?php

namespace App\Controllers\Admin;

use App\Models\PageModel;
use App\Models\LanguageModel;
use App\Controllers\BaseController;
use App\Libraries\PageBuilder\PageBuilder;

class Component extends BaseController
{
    public function store()
    {
        return $this->save();
    }
    private function save()
    {
        $data = $this->request->getJSON();
        $data = [
            'name' => $data->name,
            'group' => $data->group ?: null,
            'json_content' => $data->json_content,
        ];
        if (empty($data['name']) || empty($data['json_content'])) {
            return $this->response->setStatusCode(400)->setJSON(['status' => 'error', 'message' => 'Invalid input']);
        }

        // Обработка имени для использования в качестве имени директории
        $data['code'] = preg_replace('/[^a-zA-Z0-9]/', '_', strtolower($data['name']));

        // Проверка уникальности имени компонента (без учета регистра)
        $componentsDir = APPPATH . 'Components/';
        $existingComponents = array_diff(scandir($componentsDir), array('..', '.'));

        foreach ($existingComponents as $component) {
            if (strtolower($component) == $data['code']) {
                return $this->response->setStatusCode(400)->setJSON(['status' => 'error', 'message' => 'Component name already exists']);
            }
        }

        // Создание директории для нового компонента
        $componentDir = $componentsDir . $data['code'];

        if (!is_dir($componentDir)) {
            mkdir($componentDir, 0777, true);
        }
        $config = json_decode($data['json_content'], true);
        // Рекурсивное удаление всех полей id
        $config = $this->removeIdFields($config);

        $config['type'] = 'template';
        $config['name'] = $data['name'];
        $config['code'] = $data['code'];
        $config['group'] = $data['group'];
        $config['properties']['title']['default'] = $data['name'];

        file_put_contents($componentDir . '/config.json', json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        // Сохранение template.html
        $template = $jsonContent['template'] ?? '<div></div>';
        file_put_contents($componentDir . '/template.html', $template);

        return $this->response->setJSON(['status' => 'error', 'message' => 'Component created successfully.']);
    }

    // Функция для рекурсивного удаления всех полей id
    private function removeIdFields($array)
    {
        foreach ($array as $key => &$value) {
            if (is_array($value)) {
                $value = $this->removeIdFields($value);
            }
        }
        unset($array['id']);
        return $array;
    }
}
