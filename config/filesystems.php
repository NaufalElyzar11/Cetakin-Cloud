<?php

return [
    'design_objects' => [
        'driver' => env('DESIGN_OBJECT_DRIVER', 'local'),
        's3' => [
            'endpoint' => env('CETAKIN_S3_ENDPOINT'),
            'bucket' => env('CETAKIN_S3_BUCKET'),
            'key' => env('CETAKIN_S3_KEY'),
            'secret' => env('CETAKIN_S3_SECRET'),
            'region' => env('CETAKIN_S3_REGION', 'us-east-1'),
            'prefix' => 'designs',
        ],
    ],
    'disks' => [
        'local' => [
            'driver' => 'local',
            'root' => storage_path('app/private'),
            'serve' => false,
        ],
    ],
    'links' => [],
];
