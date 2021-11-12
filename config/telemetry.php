<?php

return [
    'metrics-route' => '/metrics',
    'namespace' => env('TELEMETRY_NAMESPACE', 'app'),

    'push-gateway' => env('TELEMETRY_PUSH_GATEWAY', 'http://localhost:9091'),
    'redis-host' => env('TELEMETRY_REDIS_HOST', '127.0.0.1'),
    'redis-port' => env('TELEMETRY_REDIS_PORT', '6379'),

    'http_in_endpoints_ignore' => ['metrics'],

    'http_in_histogram' => [
        'enabled' => true,
        'buckets' => [0.016, 0.032, 0.064, 0.128, 0.256, 0.512, 1.024]
    ],

    'http_out_histogram' => [
        'enabled' => true,
        'buckets' => [0.016, 0.032, 0.064, 0.128, 0.256, 0.512, 1.024]
    ],

    'db_query_histogram' => [
        'enabled' => true,
        'buckets' => [0.001, 0.002, 0.004, 0.008, 0.016, 0.032, 0.064, 0.128, 0.256, 0.512, 1.024]
    ]
];