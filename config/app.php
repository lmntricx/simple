<?php

return [
    'name' => env('APP_NAME', 'Simple'),
    'env' => env('APP_ENV', 'production'),

    // Shows exception details in error responses. Never enable in production.
    'debug' => (bool) env('APP_DEBUG', false),

    // Public URL of this API (used to build links in emails). Include any sub-folder.
    'url' => env('APP_URL', 'http://localhost'),

    // Secret used to sign URLs. Generate with `php simple key:generate`.
    'key' => env('APP_KEY', ''),
    'timezone' => env('APP_TIMEZONE', 'UTC'),

    // Prefix for every route in routes/api.php, e.g. "api" => /api/users
    'route_prefix' => env('APP_ROUTE_PREFIX', ''),

    // Run on every request, in order.
    'middleware' => [
        Simple\Http\Middleware\Cors::class,
    ],

    // Short names for route middleware: ->middleware('auth', 'throttle:5,1')
    'middleware_aliases' => [
        'auth' => Simple\Auth\Middleware\Authenticate::class,
        'ability' => Simple\Auth\Middleware\CheckAbilities::class,
        'verified' => Simple\Auth\Middleware\EnsureEmailIsVerified::class,
        'throttle' => Simple\Http\Middleware\Throttle::class,
    ],
];
