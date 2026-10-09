<?php

use Simple\Application;

$basePath = dirname(__DIR__);

if (!is_file($basePath . '/vendor/autoload.php')) {
    fwrite(PHP_SAPI === 'cli' ? STDERR : fopen('php://output', 'w'), "Dependencies are missing. Run `composer install`.\n");
    exit(1);
}

require_once $basePath . '/vendor/autoload.php';

$app = new Application($basePath);

$app->loadRoutes($basePath . '/routes/api.php', ['prefix' => config('app.route_prefix', '')]);

return $app;
