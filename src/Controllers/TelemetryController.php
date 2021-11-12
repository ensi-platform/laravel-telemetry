<?php

namespace Greensight\LaravelTelemetry\Controllers;

use Greensight\LaravelTelemetry\Metrics;
use Illuminate\Http\Response;
use Prometheus\RenderTextFormat;

class TelemetryController
{
    public function metrics(Metrics $metrics)
    {
        Metrics::getInstance()->up();
        return new Response($metrics->dumpTxt(), 200, ['Content-type' => RenderTextFormat::MIME_TYPE]);
    }
}