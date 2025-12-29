<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Enabled Engines
    |--------------------------------------------------------------------------
    |
    | Configure which engines should be loaded and available in your application.
    | Set to false to disable an engine completely and improve performance.
    | By default, all engines are enabled.
    |
    | Available engines:
    | - saga: Distributed transaction management
    | - workflow: Multi-step business process orchestration
    | - sync: Model synchronization with conflict resolution
    | - version: Model versioning and snapshot management
    | - event_sourcing: Event-driven state management
    | - rule: Business rule engine with fluent API
    | - dependency: Dependency resolution and validation
    |
    */
    'engines' => [
        'saga' => env('ORCHESTRATION_SAGA_ENABLED', true),
        'workflow' => env('ORCHESTRATION_WORKFLOW_ENABLED', true),
        'sync' => env('ORCHESTRATION_SYNC_ENABLED', true),
        'version' => env('ORCHESTRATION_VERSION_ENABLED', true),
        'event_sourcing' => env('ORCHESTRATION_EVENT_SOURCING_ENABLED', true),
        'rule' => env('ORCHESTRATION_RULE_ENABLED', true),
        'dependency' => env('ORCHESTRATION_DEPENDENCY_ENABLED', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Storage Drivers
    |--------------------------------------------------------------------------
    |
    | Configure how orchestration data is stored and retrieved.
    | Supports: database, redis, queue
    |
    */
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