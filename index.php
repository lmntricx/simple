<?php
// ========================================
// Bootstrap & Config
// ========================================

// Load environment & configs
require_once __DIR__ . '/config/errors.php';
require_once __DIR__ . '/config/cors.php';

session_start();
require_once __DIR__ . '/autoloader.php';
require_once __DIR__ . '/config/app.php';

// ========================================
// Routing
// ========================================
try {
    $routes = require __DIR__ . '/config/routes.php';

    $uri = parse_url($_SERVER["REQUEST_URI"], PHP_URL_PATH);
    $method = $_SERVER["REQUEST_METHOD"];

    // API prefix handling
    $basePrefix = '/api';
    if (strpos($uri, $basePrefix) === 0) {
        $uri = substr($uri, strlen($basePrefix));
        if ($uri === '') $uri = '/';
    }

    if (isset($routes[$method][$uri])) {
        [$controller, $action] = explode("@", $routes[$method][$uri]);
        $controllerClass = "app\\Controllers\\" . $controller;

        if (!class_exists($controllerClass)) {
            throw new Exception("Controller '$controllerClass' not found.");
        }

        $controllerInstance = new $controllerClass();

        if (!method_exists($controllerInstance, $action)) {
            throw new Exception("Method '$action' not found in controller '$controllerClass'.");
        }

        $controllerInstance->$action();

    } else {
        http_response_code(404);
        echo json_encode(["error" => "404 Not Found"]);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "error" => "500 Internal Server Error",
        "message" => $e->getMessage()
    ]);
}
