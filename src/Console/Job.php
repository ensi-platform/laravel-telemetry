<?php

namespace Greensight\LaravelTelemetry\Console;

use Greensight\LaravelTelemetry\Metrics;

abstract class Job
{
    public function handle()
    {
        $metrics = Metrics::getInstance();
        $metrics->setTxnId(static::class);

        $start = microtime(true);
        $result = $this->run();
        $duration = microtime(true) - $start;

        if (Metrics::cliMetricsEnabled()) {
            try {
                $metrics->queueJobExecution(static::class, $duration);
                $metrics->pushMetrics();
            } catch (\Throwable $e) {
                logger()->error('Exception while metrics processing', ['exception' => $e]);
            }
        }
        $metrics->setTxnId(null);

        return $result;
    }

    public abstract function run(): mixed;
}