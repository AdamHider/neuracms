<?php 
// app/Controllers/ComponentController.php

namespace App\Controllers;

use CodeIgniter\HTTP\Response;

class Component extends BaseController
{
    public function getComponent($type)
    {
        // Загрузка конфигурации и шаблона компонента
        $configPath = APPPATH . "Libraries/PageBuilder/components/$type/config.json";
        $templatePath = APPPATH . "Libraries/PageBuilder/components/$type/template.html";

        if (file_exists($configPath) && file_exists($templatePath)) {
            $config = json_decode(file_get_contents($configPath), true);
            $template = file_get_contents($templatePath);

            return $this->response->setJSON(['config' => $config, 'template' => $template]);
        }

        return $this->response->setStatusCode(Response::HTTP_NOT_FOUND)
                              ->setJSON(['error' => 'Component not found']);
    }
}
