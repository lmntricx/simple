<?php

$environment = require __DIR__ . '/environment.php';
$database = require __DIR__ . '/database.php';

if (!defined('APP_NAME')) {
    define('APP_NAME', $environment['APP_NAME']);
}
if (!defined('APP_ENV')) {
    define('APP_ENV', $environment['APP_ENV']);
}
if (!defined('APP_DEBUG')) {
    define('APP_DEBUG', filter_var($environment['APP_DEBUG'], FILTER_VALIDATE_BOOLEAN));
}
if (!defined('APP_URL')) {
    define('APP_URL', $environment['APP_URL']);
}
if (!defined('APP_API_PREFIX')) {
    define('APP_API_PREFIX', '/api');
}
if (!defined('DB_CONFIG')) {
    define('DB_CONFIG', $database);
}

return [
    'name' => APP_NAME,
    'env' => $environment,
    'database' => $database,
];
