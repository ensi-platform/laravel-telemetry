<?php

namespace Greensight\LaravelTelemetry\Http;

use Greensight\LaravelTelemetry\Metrics;
use Psr\Http\Message\RequestInterface;

class GuzzleHandler
{
    public static function middleware()
    {
        return function(callable $handler) {
            return static function(RequestInterface $request, array $options) use ($handler) {
                $start = microtime(true);
                $response = $handler($request, $options);
                $duration = microtime(true) - $start;

                $endpoint = Metrics::normalizeHttpUri($request->getUri()->getPath());
                Metrics::getInstance()->httpRequest($request->getUri()->getHost(),$endpoint, $duration);

                return $response;
            };
        };
    }
}