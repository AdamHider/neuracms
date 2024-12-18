<?php 

namespace App\Libraries\PageBuilder;

use App\Libraries\PageBuilder\Handlers\ComponentHandler;

class PageBuilder 
{
    private $handler;

    private $basePath = APPPATH . "Components";
    public function __construct()
    {
        $this->handler = new ComponentHandler();
    }
    
    public function getComponent($code)
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

    public function listComponents()
    {
        $componentDirs = array_diff(scandir($this->basePath), ['..', '.']);
        $components = [];
        foreach ($componentDirs as $code) {
            if (is_dir($this->basePath.'/'.$code)) {
                $component = $this->getComponent($code);
                $components[$component['config']['group']??'default'][] = $component;
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
        $componentData = $this->getComponent($component['code']);
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
        $template = preg_replace('/{{.*}}/', '', $template);

        return $template;
    }


}
