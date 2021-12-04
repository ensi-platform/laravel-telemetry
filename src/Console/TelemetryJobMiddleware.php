<?php

namespace Ensi\LaravelTelemetry\Console;

use Ensi\LaravelTelemetry\Metrics;

class TelemetryJobMiddleware
{
    public function handle($job, $next)
    {
        $metrics = Metrics::getInstance();
        $metrics->setTxnId($job::class);

        $start = microtime(true);
        $next($job);
        $duration = microtime(true) - $start;

        if (Metrics::cliMetricsEnabled()) {
            try {
                $metrics->queueJobExecution($job::class, $duration);
                $metrics->pushMetrics();
            } catch (\Throwable $e) {
                logger()->error('Exception while metrics processing', ['exception' => $e]);
            }
        }
        $metrics->setTxnId(null);
    }
}