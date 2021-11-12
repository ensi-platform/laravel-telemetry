<?php

namespace Ensi\LaravelTelemetry\Controllers;

use Ensi\LaravelTelemetry\Metrics;
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