<?php

namespace Ensi\LaravelTelemetry\Console;

use Ensi\LaravelTelemetry\Metrics;
use Illuminate\Console\Events\CommandFinished;
use Illuminate\Console\Events\CommandStarting;

class CliTelemetryEvensSubscriber
{
    public function handleFinish(CommandFinished $event)
    {
        if (!$event->command) {
            return;
        }
        $duration = Metrics::getCommandExecutionDuration($event->command);
        $commandFailed = $event->exitCode > 0;
        $metrics = Metrics::getInstance();
        if (Metrics::cliMetricsEnabled()) {
            try {
                $metrics->setTxnId($event->command);
                $metrics->consoleCommandExecution($duration, $commandFailed);
                $metrics->pushMetrics();
            } catch (\Throwable $e) {
                logger()->error('Exception while metrics processing', ['exception' => $e]);
            }
        }
        $metrics->setTxnId(null);
    }

    public function handleStart(CommandStarting $event)
    {
        if (!$event->command) {
            return;
        }
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