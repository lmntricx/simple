<?php

return [
    'name' => env('APP_NAME', 'Simple'),
    'env' => env('APP_ENV', 'production'),

    // Shows exception details in error responses. Never enable in production.
    'debug' => (bool) env('APP_DEBUG', false),

    'url' => env('APP_URL', 'http://localhost'),
    'timezone' => env('APP_TIMEZONE', 'UTC'),

    // Prefix for every route in routes/api.php, e.g. "api" => /api/users
    'route_prefix' => env('APP_ROUTE_PREFIX', ''),

    // Run on every request, in order.
    'middleware' => [
        Simple\Http\Middleware\Cors::class,
    ],

    // Short names for route middleware: ->middleware('auth')
    'middleware_aliases' => [
        // 'auth' => App\Middleware\Authenticate::class,
    ],
];
