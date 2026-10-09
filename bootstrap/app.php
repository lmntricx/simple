<?php

use Simple\Application;

$basePath = dirname(__DIR__);

require_once is_file($basePath . '/vendor/autoload.php')
    ? $basePath . '/vendor/autoload.php'
    : $basePath . '/framework/autoload.php';

$app = new Application($basePath);

$app->loadRoutes($basePath . '/routes/api.php', ['prefix' => config('app.route_prefix', '')]);

return $app;
