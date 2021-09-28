<?php

return [
    'metrics-route' => '/metrics',
    'namespace' => env('TELEMETRY_NAMESPACE', 'laravel_app'),

    'push-gateway' => env('TELEMETRY_PUSH_GATEWAY', 'http://localhost:9091'),

    'http_percentiles' => env("TELEMETRY_HTTP_PERCENTILES", '[0.5, 0.95, 0.99]'),
    'http_percentile_window' => env("TELEMETRY_HTTP_PERCENTILE_WINDOW", 60),

    'cli_percentiles' => env("TELEMETRY_CLI_PERCENTILES", '[0.5, 0.95, 0.99]'),
    'cli_percentile_window' => env("TELEMETRY_CLI_PERCENTILE_WINDOW", 60),

    'out_http_percentiles' => env("TELEMETRY_OUT_HTTP_PERCENTILES", '[0.5, 0.95, 0.99]'),
    'out_http_percentile_window' => env("TELEMETRY_OUT_HTTP_PERCENTILE_WINDOW", 60),

    'db_percentiles' => env("TELEMETRY_DB_PERCENTILES", '[0.5, 0.95, 0.99]'),
    'db_percentile_window' => env("TELEMETRY_DB_PERCENTILE_WINDOW", 60),
];