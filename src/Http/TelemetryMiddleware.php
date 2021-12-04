<?php

namespace Ensi\LaravelTelemetry\Http;

use Closure;
use Ensi\LaravelTelemetry\Metrics;
use Illuminate\Http\Request;

class TelemetryMiddleware
{
    public function handle(Request $request, Closure $next) {

        $response = $next($request);

        /** @noinspection PhpUndefinedConstantInspection */
        Metrics::getInstance()->httpInRequest(
            microtime(true) - LARAVEL_START,
            $response->getStatusCode()
        );

        return $response;
    }
}