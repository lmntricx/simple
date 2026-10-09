<?php

return [
    'default' => 'local',
    'disks' => [
        'local' => ['root' => dirname(__DIR__) . '/storage/app'],
        'public' => ['root' => dirname(__DIR__) . '/storage/public', 'url' => 'http://simple.test/uploads'],
    ],
];
