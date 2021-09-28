<?php

namespace Greensight\LaravelTelemetry\Console;

use Greensight\LaravelTelemetry\Metrics;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

abstract class Command extends \Illuminate\Console\Command
{
    public function execute(InputInterface $input, OutputInterface $output)
    {
        $start = microtime(true);
        $returnCode = parent::execute($input, $output);
        $duration = microtime(true) - $start;

        $metrics = Metrics::getInstance();
        $metrics->mainCliTransaction($duration);

        $metrics->pushMetrics();

        return $returnCode;
    }
}