<?php

namespace Ensi\LaravelTelemetry\Http;

use Ensi\LaravelTelemetry\Metrics;
use GuzzleHttp\Promise\PromiseInterface;
use Psr\Http\Message\RequestInterface;

class GuzzleHandler
{
    public static function middleware()
    {
        return function(callable $handler) {
            return static function(RequestInterface $request, array $options) use ($handler) {
                $start = microtime(true);
                $response = $handler($request, $options);
                if ($response instanceof PromiseInterface) {
                    return $response->then(function ($result) use ($start, $request) {
                        self::handleResponse($start, $request);
                        return $result;
                    });
                } else {
                    self::handleResponse($start, $request);
                }

                return $response;
            };
        };
    }

    public static function handleResponse($start, $request)
    {
        $duration = microtime(true) - $start;

        $endpoint = Metrics::normalizeHttpUri($request->getUri()->getPath());
        Metrics::getInstance()->httpOutRequest($request->getUri()->getHost(),$endpoint, $duration);
    }
}