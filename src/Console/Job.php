<?php

namespace Greensight\LaravelTelemetry\Console;

use Greensight\LaravelTelemetry\Metrics;

abstract class Job
{
    public function handle()
    {
        $start = microtime(true);
        $result = $this->run();
        $duration = microtime(true) - $start;

        if (Metrics::cliMetricsEnabled()) {
            try {
                $metrics = Metrics::getInstance();
                $metrics->mainQueueTransaction(static::class, $duration);

                $metrics->pushMetrics();
            } catch (\Throwable $e) {
                logger()->error('Exception while metrics processing', ['exception' => $e]);
            }
        }

        return $result;
    }

    public function run(): mixed
    {
        return true;
    }
}