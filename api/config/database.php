<?php

$env = require __DIR__ . '/environment.php';

$development = [
    'driver' => getenv('DB_CONNECTION') ?: $env['DB_CONNECTION'] ?: 'mysql',
    'host' => getenv('DB_HOST') ?: $env['DB_HOST'] ?: '127.0.0.1',
    'port' => getenv('DB_PORT') ?: $env['DB_PORT'] ?: '3306',
    'database' => getenv('DB_DATABASE') ?: $env['DB_DATABASE'] ?: 'simple_api',
    'username' => getenv('DB_USERNAME') ?: $env['DB_USERNAME'] ?: 'root',
    'password' => getenv('DB_PASSWORD') ?: $env['DB_PASSWORD'] ?: '',
    'charset' => 'utf8mb4',
];

$production = [
    'driver' => getenv('DB_CONNECTION_PRODUCTION') ?: $env['DB_CONNECTION'] ?: 'mysql',
    'host' => getenv('DB_HOST_PRODUCTION') ?: $env['DB_HOST'] ?: '127.0.0.1',
    'port' => getenv('DB_PORT_PRODUCTION') ?: $env['DB_PORT'] ?: '3306',
    'database' => getenv('DB_DATABASE_PRODUCTION') ?: $env['DB_DATABASE'] ?: 'simple_api',
    'username' => getenv('DB_USERNAME_PRODUCTION') ?: $env['DB_USERNAME'] ?: 'root',
    'password' => getenv('DB_PASSWORD_PRODUCTION') ?: $env['DB_PASSWORD'] ?: '',
    'charset' => 'utf8mb4',
];

$active = in_array($env['APP_ENV'], ['production', 'prod'], true) ? 'production' : 'development';

return $active === 'production' ? $production : $development;
