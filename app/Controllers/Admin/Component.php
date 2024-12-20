<?php

namespace App\Controllers\Admin;

use App\Models\PageModel;
use App\Models\LanguageModel;
use App\Controllers\BaseController;
use App\Libraries\PageBuilder\PageBuilder;

class Component extends BaseController
{
    private $basePath = APPPATH . "Components";

    public function getItem($code)
    {
        $configPath = "$this->basePath/$code/config.json";
        $templatePath = "$this->basePath/$code/template.html";

        if (file_exists($configPath) && file_exists($templatePath)) {
            $config = json_decode(file_get_contents($configPath), true);
            $template = file_get_contents($templatePath);

            return ['config' => $config, 'template' => $template];
        }

        return null;
    }

    public function update()
    {
        return $this->save(true);
    }

    public function store()
    {
        return $this->save();
    }
    private function save($update = false)
    {
        $data = $this->request->getJSON();
        $data = [
            'name' => $data->name,
            'group' => $data->group ?? 'default',
            'json_content' => $data->json_content,
            'update' => $data->update ?? false,
        ];
        if (empty($data['name']) || empty($data['json_content'])) {
            return $this->response->setStatusCode(400)->setJSON(['status' => 'error', 'message' => 'Invalid input']);
        }

        $data['code'] = preg_replace('/[^a-zA-Z0-9]/', '_', strtolower($data['name']));
        $componentDir = $this->basePath . '/' . $data['code'];

        $config = json_decode($data['json_content'], true);

        if(!$data['update']){
            // Проверка уникальности имени компонента (без учета регистра)
            $existingComponents = array_diff(scandir($this->basePath), array('..', '.'));

            foreach ($existingComponents as $component) {
                if (strtolower($component) == $data['code']) {
                    return $this->response->setStatusCode(400)->setJSON(['status' => 'error', 'message' => 'Component name already exists']);
                }
            }
            if (!is_dir($componentDir)) {
                mkdir($componentDir, 0777, true);
            }   
            $sourceComponent = $this->getItem($config['code']);

            $template = $sourceComponent['template'];
            
            file_put_contents($componentDir . '/template.html', $template);
        }

        $config = $this->removeIdFields($config);

        $config['type'] = 'template';
        $config['name'] = $data['name'];
        $config['code'] = $data['code'];
        $config['group'] = $data['group'];
        $config['properties']['title']['value'] = $data['name'];
        
        file_put_contents($componentDir . '/config.json', json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        return $this->response->setJSON(['status' => 'success', 'data' => $config]);
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
