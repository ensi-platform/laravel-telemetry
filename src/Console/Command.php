<?php

namespace Ensi\LaravelTelemetry\Console;

use Ensi\LaravelTelemetry\Metrics;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

abstract class Command extends \Illuminate\Console\Command
{
    public function execute(InputInterface $input, OutputInterface $output)
    {
        $metrics = Metrics::getInstance();
        $metrics->setTxnId($this->getName());

        $start = microtime(true);
        $returnCode = parent::execute($input, $output);
        $duration = microtime(true) - $start;

        if (Metrics::cliMetricsEnabled()) {
            try {
                $metrics->consoleCommandExecution($duration);
                $metrics->pushMetrics();
            } catch (\Throwable $e) {
                logger()->error('Exception while metrics processing', ['exception' => $e]);
            }
        }
        $metrics->setTxnId(null);

        return $returnCode;
    }
}