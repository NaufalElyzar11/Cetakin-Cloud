<?php

return [
    'driver' => env('SESSION_DRIVER', 'database'),
    'connection' => null,
    'table' => 'sessions',
    'lifetime' => 120,
    'expire_on_close' => false,
    'encrypt' => false,
    'files' => storage_path('framework/sessions'),
    'lottery' => [2, 100],
    'cookie' => env('SESSION_COOKIE', 'cetakin_cloud_session'),
    'path' => '/',
    'domain' => null,
    'secure' => env('SESSION_SECURE_COOKIE', env('APP_ENV', 'production') === 'production'),
    'http_only' => true,
    'same_site' => 'lax',
    'partitioned' => false,
    'serialization' => 'json',
];
