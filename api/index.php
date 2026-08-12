<?php

// ========================================
// Bootstrap & Config
// ========================================

// Load environment & configs
require_once __DIR__ . '/config/errors.php';
require_once __DIR__ . '/config/cors.php';

header('Content-Type: application/json; charset=utf-8');
session_start();
require_once __DIR__ . '/autoloader.php';
require_once __DIR__ . '/config/app.php';

function matchRoute(string $uri, array $routes): ?array
{
    foreach ($routes as $route => $handler) {
        if ($route === $uri) {
            return ['handler' => $handler, 'params' => []];
        }

        if (strpos($route, '{') === false) {
            continue;
        }

        $pattern = preg_replace_callback(
            '/\{([a-zA-Z_][a-zA-Z0-9_-]*)\}/',
            function ($matches) {
                return '(?P<' . $matches[1] . '>[^\/]+)';
            },
            $route
        );

        $pattern = '#^' . $pattern . '$#';

        if (preg_match($pattern, $uri, $matches)) {
            $params = [];
            foreach ($matches as $key => $value) {
                if (!is_int($key)) {
                    $params[$key] = $value;
                }
            }

            return ['handler' => $handler, 'params' => $params];
        }
    }

    return null;
}

// ========================================
// Routing
// ========================================
try {
    $routes = require __DIR__ . '/config/routes.php';

    $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    $uri = rtrim($uri, '/');
    if ($uri === '') {
        $uri = '/';
    }

    $method = $_SERVER['REQUEST_METHOD'];

    $basePrefix = '/api';
    if (strpos($uri, $basePrefix) === 0) {
        $uri = substr($uri, strlen($basePrefix));
        $uri = $uri === '' ? '/' : $uri;
    }

    $routeMatch = isset($routes[$method]) ? matchRoute($uri, $routes[$method]) : null;

    if ($routeMatch !== null) {
        [$controller, $action] = explode('@', $routeMatch['handler']) + [1 => null];

        if (!$controller || !$action) {
            throw new Exception("Invalid route definition for $method $uri");
        }

        $controllerClass = "app\\Controllers\\{$controller}";

        if (!class_exists($controllerClass)) {
            throw new Exception("Controller '{$controllerClass}' not found.");
        }

        $controllerInstance = new $controllerClass();

        if (!method_exists($controllerInstance, $action)) {
            throw new Exception("Method '{$action}' not found in controller '{$controllerClass}'.");
        }

        if (!empty($routeMatch['params'])) {
            call_user_func_array([$controllerInstance, $action], [$routeMatch['params']]);
        } else {
            $controllerInstance->$action();
        }

        return;
    }

    foreach ($routes as $routeMethod => $routeItems) {
        if ($routeMethod !== $method && matchRoute($uri, $routeItems) !== null) {
            http_response_code(405);
            echo json_encode(['error' => '405 Method Not Allowed']);
            return;
        }
    }

    http_response_code(404);
    echo json_encode(['error' => '404 Not Found']);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => '500 Internal Server Error',
        'message' => $e->getMessage()
    ]);
}
