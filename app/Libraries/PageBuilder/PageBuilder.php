<?php 

namespace App\Libraries\PageBuilder;

use App\Libraries\PageBuilder\Handlers\ComponentHandler;

class PageBuilder 
{
    private $handler;

    public function __construct()
    {
        $this->handler = new ComponentHandler();
    }
    
    public function getComponent($type, $group)
    {
        $configPath = APPPATH . "Libraries/PageBuilder/components/$group/$type/config.json";
        $templatePath = APPPATH . "Libraries/PageBuilder/components/$group/$type/template.html";

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
                        $componentGroup['children'][] = $this->getComponent($dir, $group);
                    }
                }
                $components[] = $componentGroup;
            }
        }
        return $components;
    }

    public function buildHtmlFromJson($contentJson)
    {
        if(empty($contentJson)) return '';
        $contentArray = json_decode($contentJson, true);
        $html = '';

        foreach ($contentArray as $component) {
            $html .= $this->buildComponentHtml($component);
        }
        return $html;
    }


    private function buildComponentHtml($component)
    {
        $componentData = $this->getComponent($component['code'], $component['group']);
        $template = $componentData['template'];

        foreach ($component['properties'] as $key => $value) {
            $template = str_replace("{{{$key}}}", $value, $template);
        }

        if (isset($component['controller']) && isset($component['method'])) {
            $childHtml = $this->handler->handle($component);
            $template = str_replace('{{children}}', $childHtml, $template);
        } else {
            $childHtml = '';
            if (!empty($component['children'])) {
                foreach ($component['children'] as $child) {
                    $childHtml .= $this->buildComponentHtml($child);
                }
            }
            $template = str_replace('{{children}}', $childHtml, $template);
        }

        return $template;
    }


}
