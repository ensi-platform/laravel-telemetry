<?php

namespace Ensi\LaravelTelemetry\Http;

use Closure;
use Ensi\LaravelTelemetry\Metrics;
use Illuminate\Http\Request;

class TelemetryMiddleware
{
    public function handle(Request $request, Closure $next) {
        $startTime = defined('LARAVEL_START') ? LARAVEL_START : microtime(true);
        $response = $next($request);

        Metrics::getInstance()->httpInRequest(
            microtime(true) - $startTime,
            $response->getStatusCode()
        );

        return $response;
    }
}