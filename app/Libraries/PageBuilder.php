<?php 

namespace App\Libraries;

class PageBuilder
{
    public function getComponent($path)
    {
        $configPath = "$path/config.json";
        $templatePath = "$path/template.html";

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
        $componentGroupDirs = array_diff(scandir($componentsPath), ['..', '.']);
        $components = [];

        foreach ($componentGroupDirs as $key => $group) {
            if (is_dir($componentsPath . $group)) {
                $componentGroup = [
                    'title' => $group,
                    'children' => []
                ];
                $componentDirs = array_diff(scandir($componentsPath . $group), ['..', '.']);
                foreach ($componentDirs as $dir) {
                    if (is_dir($componentsPath . $group .'/'. $dir)) {
                        $componentGroup['children'][] = $this->getComponent($componentsPath . $group .'/'. $dir);
                    }
                }
                $components[] = $componentGroup;
            }
        }
        return $components;
    }

    public function buildHtmlFromJson($contentJson)
    {
        $contentArray = json_decode($contentJson, true);
        $html = '';

        foreach ($contentArray as $component) {
            $html .= $this->buildComponentHtml($component);
        }
        return $html;
    }

    private function buildComponentHtml($component)
    {
        $componentsPath = APPPATH . "Libraries/PageBuilder/components/";
        $componentData = $this->getComponent($componentsPath . $component['group'] .'/'. $component['code']);
        $template = $componentData['template'];

        foreach ($component['properties'] as $key => $value) {
            $template = str_replace("{{{$key}}}", $value, $template);
        }

        // Если у компонента есть дочерние элементы, обрабатываем их рекурсивно
        $childHtml = '';
        if (!empty($component['children'])) {
            foreach ($component['children'] as $child) {
                $childHtml .= $this->buildComponentHtml($child);
            }
        }
        $template = str_replace('{{children}}', $childHtml, $template);

        return $template;
    }
}
