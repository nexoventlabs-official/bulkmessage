<?php

return [
    'default' => env('QUEUE_CONNECTION', 'redis'),

    'connections' => [
        'sync' => [
            'driver' => 'sync',
        ],

        'redis' => [
            'driver' => 'redis',
            'connection' => 'default',
            'queue' => env('REDIS_QUEUE', 'campaign_messages'),
            'retry_after' => 300,
            'block_for' => null,
            'after_commit' => false,
        ],
    ],

    'batching' => [
        'database' => 'mongodb',
        'table' => 'job_batches',
    ],

    'failed' => [
        'driver' => 'mongodb',
        'database' => 'mongodb',
        'table' => 'failed_jobs',
    ],
];
