<?php

return [
    'name' => 'Fixture',
    'env' => env('APP_ENV', 'testing'),
    'debug' => (bool) env('APP_DEBUG', true),
    'url' => env('APP_URL', 'http://simple.test'),
    'key' => env('APP_KEY', ''),
    'timezone' => 'UTC',
    'trusted_proxies' => [],
    'route_prefix' => env('APP_ROUTE_PREFIX', ''),

    'providers' => [
        Simple\Tests\Fixtures\FixtureServiceProvider::class,
    ],

    'middleware' => [
        Simple\Http\Middleware\Cors::class,
    ],

    'middleware_aliases' => [
        'auth' => Simple\Auth\Middleware\Authenticate::class,
        'ability' => Simple\Auth\Middleware\CheckAbilities::class,
        'verified' => Simple\Auth\Middleware\EnsureEmailIsVerified::class,
        'throttle' => Simple\Http\Middleware\Throttle::class,
    ],
];
