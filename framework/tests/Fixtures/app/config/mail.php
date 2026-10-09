<?php

return [
    // log   - write emails to storage/logs/mail.log (development)
    // smtp  - send through an SMTP server
    // array - keep in memory (tests)
    'driver' => env('MAIL_DRIVER', 'log'),

    'from' => [
        'address' => env('MAIL_FROM_ADDRESS', 'hello@example.com'),
        'name' => env('MAIL_FROM_NAME', env('APP_NAME', 'Simple')),
    ],

    'smtp' => [
        'host' => env('MAIL_HOST', '127.0.0.1'),
        'port' => (int) env('MAIL_PORT', 587),
        'username' => env('MAIL_USERNAME'),
        'password' => env('MAIL_PASSWORD'),
        // tls = STARTTLS (usually port 587), ssl = implicit TLS (port 465), none = unencrypted
        'encryption' => env('MAIL_ENCRYPTION', 'tls'),
        'timeout' => 10,
    ],
];
