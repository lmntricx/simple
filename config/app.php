<?php
function env($key, $default = null) {
    static $env;
    if (!$env) {
        $env = parse_ini_file(__DIR__ . '/../.env');
    }
    return $env[$key] ?? $default;
}

define('APP_NAME', env('APP_NAME', 'MyApp'));
define('APP_ENV', env('APP_ENV', 'development'));
