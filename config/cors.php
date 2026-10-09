<?php

return [
    // e.g. ['https://app.example.com', 'http://localhost:5173'], or ['*'] for any origin
    'allowed_origins' => array_map('trim', explode(',', env('CORS_ALLOWED_ORIGINS', '*'))),

    'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],
    'allowed_headers' => ['Content-Type', 'Authorization', 'X-Requested-With', 'Accept'],

    // Cookies / Authorization across origins. Requires explicit origins (not "*").
    'supports_credentials' => (bool) env('CORS_SUPPORTS_CREDENTIALS', false),

    // Seconds browsers may cache a preflight response.
    'max_age' => 3600,
];
