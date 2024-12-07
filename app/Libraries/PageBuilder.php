<?php 

namespace App\Libraries;

class PageBuilder
{
    public function getComponent($type)
    {
        $configPath = APPPATH . "Libraries/PageBuilder/components/$type/config.json";
        $templatePath = APPPATH . "Libraries/PageBuilder/components/$type/template.html";

        if (file_exists($configPath) && file_exists($templatePath)) {
            $config = json_decode(file_get_contents($configPath), true);
            $template = file_get_contents($templatePath);

            return ['config' => $config, 'template' => $template];
        }

        return null;
    }

    public function listComponents()
    {
        $componentsPath = APPPATH . "Libraries/PageBuilder/components/";
        $componentDirs = array_diff(scandir($componentsPath), ['..', '.']);
        $components = [];

        foreach ($componentDirs as $dir) {
            if (is_dir($componentsPath . $dir)) {
                $components[] = $dir;
            }
        }

        return $components;
    }
}
