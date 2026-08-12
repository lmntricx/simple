<?php

$environment = require __DIR__ . '/environment.php';
$debug = filter_var($environment['APP_DEBUG'], FILTER_VALIDATE_BOOLEAN);

ini_set('display_errors', $debug ? '1' : '0');
ini_set('display_startup_errors', $debug ? '1' : '0');
error_reporting(E_ALL);

set_error_handler(function (int $severity, string $message, string $file, int $line) use ($debug) {
    throw new ErrorException($message, 0, $severity, $file, $line);
});

set_exception_handler(function (Throwable $exception) use ($debug) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');

    $payload = [
        'status' => 'error',
        'message' => 'Internal Server Error',
    ];

    if ($debug) {
        $payload['exception'] = [
            'message' => $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
        ];
    }

    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit();
});

// Always log errors
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/../storage/logs/php_errors.log');
