<?php
// Start session
session_start();

// Load required files
require_once 'autoloader.php';
require_once 'config/app.php';

use app\Managers\SchemaManager;

// Create database schema if necessary
$schemaManager = new SchemaManager();
$schemaManager->createTables();

try {
    // Load routes
    $routes = require 'config/routes.php';

    // Parse URI and HTTP method
    $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    $method = $_SERVER['REQUEST_METHOD'];

    // Check if the route exists
    if (isset($routes[$method][$uri])) {
        [$controller, $action] = explode('@', $routes[$method][$uri]);
        $controllerClass = 'app\\Controllers\\' . $controller;

        if (class_exists($controllerClass)) {
            $controllerInstance = new $controllerClass();

            if (method_exists($controllerInstance, $action)) {
                // Call the action
                $controllerInstance->$action();
            } else {
                throw new Exception("Method '$action' not found in controller '$controllerClass'.");
            }
        } else {
            throw new Exception("Controller '$controllerClass' not found.");
        }
    } else {
        // Route not found
        http_response_code(404);
        echo '404 Not Found';
    }
} catch (Exception $e) {
    // Handle any errors gracefully
    http_response_code(500);
    echo '500 Internal Server Error: ' . $e->getMessage();
}
