<?php

return [
    'APP_NAME' => getenv('APP_NAME') ?: 'Simple API',
    'APP_ENV' => getenv('APP_ENV') ?: 'local',
    'APP_DEBUG' => getenv('APP_DEBUG') ?: 'true',
    'APP_URL' => getenv('APP_URL') ?: 'http://localhost',

    'DB_CONNECTION' => getenv('DB_CONNECTION') ?: 'sqlite',
    'DB_HOST' => getenv('DB_HOST') ?: '127.0.0.1',
    'DB_PORT' => getenv('DB_PORT') ?: '3306',
    'DB_DATABASE' => getenv('DB_DATABASE') ?: 'simple_api',
    'DB_USERNAME' => getenv('DB_USERNAME') ?: 'root',
    'DB_PASSWORD' => getenv('DB_PASSWORD') ?: '',
];
