<?php

// `php simple serve`: let PHP's built-in server serve real files (e.g. uploads) directly.
if (PHP_SAPI === 'cli-server') {
    $file = __DIR__ . rawurldecode(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');
    if ($file !== __FILE__ && is_file($file) && !str_ends_with($file, '.php')) {
        return false;
    }
}

/** @var \Simple\Application $app */
$app = require __DIR__ . '/../bootstrap/app.php';

$app->run();
