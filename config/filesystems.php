<?php

return [
    // Used by Storage::disk() and $file->store() when no disk is named.
    'default' => env('FILESYSTEM_DISK', 'local'),

    'disks' => [
        // Private: not reachable by URL. Serve files through a controller with
        // Storage::disk()->download(...) after checking permissions.
        'local' => [
            'root' => dirname(__DIR__) . '/storage/app',
        ],

        // Public: served directly by the web server at APP_URL/uploads/...
        'public' => [
            'root' => dirname(__DIR__) . '/public/uploads',
            'url' => rtrim((string) env('APP_URL', 'http://localhost'), '/') . '/uploads',
        ],
    ],
];
