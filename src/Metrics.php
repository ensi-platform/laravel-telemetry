<?php

namespace Ensi\LaravelTelemetry;

use Illuminate\Support\Facades\Route;
use Prometheus\CollectorRegistry;
use Prometheus\RenderTextFormat;
use Prometheus\Storage\APC;
use Prometheus\Storage\Redis;
use PrometheusPushGateway\PushGateway;
use Throwable;

class Metrics
{
    private static ?Metrics $instance = null;

    private CollectorRegistry $prom;
    private ?string $txnId = null;

    public static function getInstance(): Metrics
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

         return self::$instance;
    }

    public static function normalizeHttpUri(string $uri): string
    {
        return preg_replace(["/\?.*/", "/\/\d+\//"], ["", "/{id}/"], $uri);
    }

    public static function normalizeDbQuery(string $query): string
    {
        return preg_replace("/\?,\s?/", "", $query);
    }

    public static function cliMetricsEnabled(): bool
    {
        return config('telemetry.background-metrics.enabled');
    }

    public function __construct()
    {
        if (php_sapi_name() == "cli" && self::cliMetricsEnabled()) {
            $metricsStorage = new Redis([
                'host' => config('telemetry.background-metrics.redis-host'),
                'port' => config('telemetry.background-metrics.redis-port'),
            ]);
        } else {
            $metricsStorage = new APC();
        }
        $this->prom = new CollectorRegistry($metricsStorage);
    }

    public function dumpTxt(): string
    {
        $renderer = new RenderTextFormat();
        return $renderer->render($this->prom->getMetricFamilySamples());
    }

    public function pushMetrics(): void
    {
        $gatewayHost = config('telemetry.background-metrics.push-gateway');

        $pushGateway = new PushGateway($gatewayHost);
        $pushGateway->push($this->prom, 'app_cli', ['app_name' => config('app.name')]);
    }

    public function setTxnId(?string $txnId): void
    {
        $this->txnId = $txnId;
    }
    public function getTxnId(): string
    {
        if (!$this->txnId) {
            if (php_sapi_name() == "cli") {
                # in cli commands and queue jobs transaction name is always set explicit
                $this->txnId = 'undefined_cli';
            } else {
                $laravelRoute = Route::current();
                if ($laravelRoute) {
                    $this->txnId = $laravelRoute->uri;
                } else {
                    if (isset($_SERVER['REQUEST_METHOD']) && isset($_SERVER['REQUEST_URI'])) {
                        $this->txnId = $_SERVER['REQUEST_METHOD'] . ' ' . self::normalizeHttpUri($_SERVER['REQUEST_URI']);
                    } else {
                        $this->txnId = 'undefined_web';
                    }
                }
            }

        }
        return $this->txnId;
    }

    public function httpInRequest($duration, $statusCode): void
    {
        $txnId = $this->getTxnId();
        if (in_array($txnId, config('telemetry.http-in-endpoints-ignore'))) {
            return;
        }

        $totalCount = $this->prom->getOrRegisterCounter(
            config('telemetry.namespace'),
            "http_in_requests_total",
            "Http in requests count",
            ["txnId", "code"]
        );
        $totalCount->inc([$txnId, $statusCode]);

        $totalDuration = $this->prom->getOrRegisterCounter(
            config('telemetry.namespace'),
            "http_in_requests_seconds_total",
            "Http in requests duration",
            ["txnId", "code"]
        );
        $totalDuration->incBy($duration, [$txnId, $statusCode]);

        if (config('telemetry.http-in-histogram.enabled')) {
            $histogram = $this->prom->getOrRegisterHistogram(
                config('telemetry.namespace'),
                "http_in_requests_histogram_seconds",
                "Http in requests histogram",
                [],
                config('telemetry.http-in-histogram.buckets'),
            );
            $histogram->observe($duration);
        }
    }

    public function consoleCommandExecution($duration): void
    {
        $txnId = $this->getTxnId();
        $totalCount = $this->prom->getOrRegisterCounter(
            config('telemetry.namespace'),
            "cli_runs_total",
            "Executions count",
            ["txnId"]
        );
        $totalCount->inc([$txnId]);

        $totalDuration = $this->prom->getOrRegisterCounter(
            config('telemetry.namespace'),
            "cli_runs_seconds_total",
            "Executions count",
            ["txnId"]
        );
        $totalDuration->incBy($duration, [$txnId]);
    }

    public function queueJobExecution($jobName, $duration): void
    {
        $totalCount = $this->prom->getOrRegisterCounter(
            config('telemetry.namespace'),
            "queue_jobs_total",
            "Queue job executions count",
            ["txnId"]
        );
        $totalCount->inc([$jobName]);

        $totalDuration = $this->prom->getOrRegisterCounter(
            config('telemetry.namespace'),
            "queue_jobs_seconds_total",
            "Queue job executions count",
            ["txnId"]
        );
        $totalDuration->incBy($duration, [$jobName]);
    }

    public function httpOutRequest($service, $endpoint, $duration): void
    {
        $txnId = $this->getTxnId();
        $totalCount = $this->prom->getOrRegisterCounter(
            config('telemetry.namespace'),
            "http_out_requests_total",
            "Http out requests count",
            ["txnId", "service", "to"]
        );
        $totalCount->inc([$txnId, $service, $endpoint]);

        $totalDuration = $this->prom->getOrRegisterCounter(
            config('telemetry.namespace'),
            "http_out_requests_seconds_total",
            "Http out requests duration",
            ["txnId", "service", "to"]
        );
        $totalDuration->incBy($duration, [$txnId, $service, $endpoint]);

        if (config('telemetry.http-out-histogram.enabled')) {
            $histogram = $this->prom->getOrRegisterHistogram(
                config('telemetry.namespace'),
                "http_out_requests_histogram_seconds",
                "Http out requests histogram",
                [],
                config('telemetry.http-out-histogram.buckets'),
            );
            $histogram->observe($duration);
        }
    }

    public function dbQuery($duration, $sql): void
    {
        $txnId = $this->getTxnId();
        $query = self::normalizeDbQuery($sql);
        $durationSeconds = $duration / 1000;

        $totalCount = $this->prom->getOrRegisterCounter(
            config('telemetry.namespace'),
            "db_queries_total",
            "Database queries count",
            ['txnId', 'query'],
        );
        $totalCount->inc([$txnId, $query]);

        $totalDuration = $this->prom->getOrRegisterCounter(
            config('telemetry.namespace'),
            "db_queries_seconds_total",
            "Database queries duration",
            ['txnId', 'query'],
        );
        $totalDuration->incBy($durationSeconds, [$txnId, $query]);

        if (config('telemetry.db-query-histogram.enabled')) {
            $histogram = $this->prom->getOrRegisterHistogram(
                config('telemetry.namespace'),
                "db-query-histogram_seconds",
                "Database queries duration histogram",
                [],
                config('telemetry.db-query-histogram.buckets'),
            );
            $histogram->observe($durationSeconds);
        }
    }

    public function unhandledException(Throwable $e): void
    {
        $totalCount = $this->prom->getOrRegisterCounter(
            config('telemetry.namespace'),
            'errors_total',
            'Unhandled exceptions count',
            ['txnId'],
        );

        $txnId = $this->getTxnId();
        $totalCount->inc([$txnId]);
    }

    public function up(): void
    {
        $gauge = $this->prom->getOrRegisterGauge(
            config('telemetry.namespace'),
            'up',
            'App is up'
        );

        $gauge->set(1);
    }
}