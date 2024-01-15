<?php

return [
    'drivers' => [
        'default' => env('BUSINESS_ORCHESTRATION_DRIVER', 'database'),
        'database' => [
            'connection' => env('DB_CONNECTION', 'mysql'),
        ],
        'redis' => [
            'connection' => env('REDIS_CONNECTION', 'default'),
        ],
        'queue' => [
            'connection' => env('QUEUE_CONNECTION', 'sync'),
        ],
    ],
];