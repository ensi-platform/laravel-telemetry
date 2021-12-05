<?php

return [
    'metrics-route' => '/metrics',
    'namespace' => env('TELEMETRY_NAMESPACE', 'app'),

    'background-metrics' => [
        'enabled' => env('TELEMETRY_BACKGROUND_ENABLED', false),
        'push-gateway' => env('TELEMETRY_PUSH_GATEWAY', 'http://localhost:9091'),
        'redis-host' => env('TELEMETRY_REDIS_HOST', '127.0.0.1'),
        'redis-port' => env('TELEMETRY_REDIS_PORT', '6379'),
    ],

    'http-in-endpoints-ignore' => ['metrics'],

    'http-in-histogram' => [
        'enabled' => env('TELEMETRY_HTTP_IN_HISTOGRAM_ENABLED', false),
        'buckets' => [0.016, 0.032, 0.064, 0.128, 0.256, 0.512, 1.024]
    ],

    'http-out-histogram' => [
        'enabled' => env('TELEMETRY_HTTP_OUT_HISTOGRAM_ENABLED', false),
        'buckets' => [0.016, 0.032, 0.064, 0.128, 0.256, 0.512, 1.024]
    ],

    'db-query-histogram' => [
        'enabled' => env('TELEMETRY_DB_QUERY_HISTOGRAM_ENABLED', false),
        'buckets' => [0.001, 0.002, 0.004, 0.008, 0.016, 0.032, 0.064, 0.128, 0.256, 0.512, 1.024]
    ]
];