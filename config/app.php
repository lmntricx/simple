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

    // Proxies / load balancers in front of the app, so Request::ip() returns the real client IP
    // (which rate limiting depends on). Comma-separated IPs or CIDR ranges, or "*" when every
    // request arrives through a proxy you control (e.g. most PaaS hosts). Behind Cloudflare,
    // list Cloudflare's ranges: https://www.cloudflare.com/ips/
    // Leave empty if clients connect directly, or anyone could fake their IP.
    'trusted_proxies' => array_filter(explode(',', (string) env('TRUSTED_PROXIES', ''))),

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
