<?php

/*
 * Fallback PSR-4 autoloader, used when `composer install` has not been run.
 * Mirrors the "autoload" section of composer.json.
 */

spl_autoload_register(function (string $class): void {
    $prefixes = [
        'Simple\\' => __DIR__ . '/',
        'App\\' => dirname(__DIR__) . '/app/',
    ];

    foreach ($prefixes as $prefix => $dir) {
        if (str_starts_with($class, $prefix)) {
            $file = $dir . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
            if (is_file($file)) {
                require $file;
            }
            return;
        }
    }
});

require __DIR__ . '/helpers.php';
