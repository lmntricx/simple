<?php

return [
    // sync     - jobs run immediately, inside the request (default; no worker needed)
    // database - jobs go into the "jobs" table and run in `php simple queue:work`
    'default' => env('QUEUE_CONNECTION', 'sync'),

    'connections' => [
        'database' => [
            'driver' => 'database',
            'table' => 'jobs',
            // Seconds before a reserved job is assumed abandoned (worker crashed) and
            // handed out again. Must be longer than your slowest job.
            'retry_after' => 90,
            // Database connection from config/database.php (null = default)
            'connection' => null,
        ],
    ],

    'failed' => [
        'table' => 'failed_jobs',
    ],
];
