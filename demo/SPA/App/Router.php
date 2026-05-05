<?php

namespace App;

use App\Request;

class Router
{
    private $routes;

    public function __construct(array $routes)
    {
        $this->routes = $routes;
    }

    public  function routeToAction()
    {
        // Normalize request
        $method = $_SERVER['REQUEST_METHOD'];
        $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

        // Match route
        if (isset($this->routes[$method][$uri])) {
            [$class, $action] = $this->routes[$method][$uri];

            if (class_exists($class) && method_exists($class, $action)) {
                $controller = new $class();
                return $controller->$action(new Request);
            } else {
                http_response_code(500);
                return ["error" => "Controller or method not found: {$class}::{$action}"];
            }
        }

        // GET requests that don't match a specific route serve the SPA root —
        // the standard SPA fallback so the app loads regardless of the URL the
        // user typed or bookmarked (e.g. /index.php, /about, etc.).
        if ($method === 'GET') {
            $controller = new \App\Controller();
            return $controller->start(new Request);
        }

        http_response_code(404);
        return ["error" => "404 Not Found: {$method} {$uri}"];
    }
}