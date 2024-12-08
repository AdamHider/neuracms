<?php 
namespace App\Libraries\PageBuilder\Handlers;


class ComponentHandler
{
    public function handle($component)
    {
        $controllerClass = $component['controller'];
        $method = $component['method'];

        if (!class_exists($controllerClass)) {
            throw new \Exception("Controller class $controllerClass does not exist.");
        }

        $controller = new $controllerClass();

        if (!method_exists($controller, $method)) {
            throw new \Exception("Method $method does not exist in controller $controllerClass.");
        }

        return $controller->$method($component);
    }
}
