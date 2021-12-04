<?php

namespace Ensi\LaravelTelemetry\Console;

use Ensi\LaravelTelemetry\Metrics;
use Illuminate\Console\Events\CommandFinished;
use Illuminate\Console\Events\CommandStarting;

class CliTelemetryEvensSubscriber
{
    public function handleFinish(CommandFinished $event)
    {
        $duration = Metrics::getCommandExecutionDuration($event->command);
        $metrics = Metrics::getInstance();
        if (Metrics::cliMetricsEnabled()) {
            try {
                $metrics->setTxnId($event->command);
                $metrics->consoleCommandExecution($duration);
                $metrics->pushMetrics();
            } catch (\Throwable $e) {
                logger()->error('Exception while metrics processing', ['exception' => $e]);
            }
        }
        $metrics->setTxnId(null);
    }

    public function handleStart(CommandStarting $event)
    {
        $metrics = Metrics::getInstance();
        $metrics->setTxnId($event->command);
        Metrics::setCommandStartTime($event->command);
    }

    public function subscribe($events)
    {
        return [
            CommandStarting::class => 'handleStart',
            CommandFinished::class => 'handleFinish',
        ];
    }
}