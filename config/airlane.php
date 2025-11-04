<?php

return [
    'default_plan' => 'basic',

    'plans' => [
        'basic' => [
            'name' => 'Basic',
            'storage_limit_bytes' => 5 * 1024 * 1024 * 1024,
            'max_file_size_bytes' => 20 * 1024 * 1024,
            'version_cap' => 25,
        ],
        'premium' => [
            'name' => 'Premium',
            'storage_limit_bytes' => 200 * 1024 * 1024 * 1024,
            'max_file_size_bytes' => 1024 * 1024 * 1024,
            'version_cap' => 150,
        ],
    ],

    'trash_retention_days' => 30,

    'storage_disk' => env('AIRLANE_STORAGE_DISK', 'local'),
    'temporary_disk' => env('AIRLANE_TEMP_DISK', 'local'),
];
