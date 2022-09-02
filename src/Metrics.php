<?php

namespace Ensi\LaravelTelemetry;

use Illuminate\Log\Events\MessageLogged;
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
    private static array $commandStartTime = [];
    private array $httpOutTimes = [];
    private float $dbOutTimes = 0;

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
        foreach (config('telemetry.http-replace') as $pattern => $replacement) {
            $uri = preg_replace($pattern, $replacement, $uri);
        }
        return $uri;
    }

    public static function normalizeDbQuery(string $query): string
    {
        $patterns = [
            "/\?,\s?/" => "",
            "/limit \d+/" => "limit ?",
            "/offset \d+/" => "offset ?",
            "/in ([^)]+)/" => "in (?)"
        ];
        foreach ($patterns as $pattern => $replacement) {
            $query = preg_replace($pattern, $replacement, $query);
        }
        return $query;
    }

    public static function cliMetricsEnabled(): bool
    {
        return config('telemetry.background-metrics.enabled');
    }

    public static function setCommandStartTime(string $name): void
    {
        self::$commandStartTime[$name] = microtime(true);
    }

    public static function getCommandExecutionDuration(string $name): float
    {
        $startTime = self::$commandStartTime[$name];
        if ($startTime) {
            return microtime(true) - $startTime;
        }
        return 0;
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
        $this->prom = new CollectorRegistry($metricsStorage, false);
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
                    $this->txnId = $laravelRoute->methods[0] . ' /' . ltrim($laravelRoute->uri, '/');
                } else {
                    if (isset($_SERVER['REQUEST_METHOD']) && isset($_SERVER['REQUEST_URI'])) {
                        $this->txnId = $_SERVER['REQUEST_METHOD'] . ' /' . ltrim(self::normalizeHttpUri($_SERVER['REQUEST_URI']), '/');
                    } else {
                        $this->txnId = 'undefined_web';
                    }
                }
            }

        }
        return $this->txnId;
    }

    public function collectWebExternalTime(float $start, float $end): void
    {
        $index = count($this->httpOutTimes) - 1;
        if ($index >= 0) {
            $oldEnd = $this->httpOutTimes[$index][1];
            if ($start < $oldEnd) {
                $this->httpOutTimes[$index][1] = $end;
            } else {
                $this->httpOutTimes[] = [$start, $end];
            }
        } else {
            $this->httpOutTimes[] = [$start, $end];
        }
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

        $webExternalDuration = $this->webExternalDuration();
        $phpDuration = $duration - $webExternalDuration - $this->dbOutTimes;
        $totalDuration = $this->prom->getOrRegisterCounter(
            config('telemetry.namespace'),
            "http_in_requests_seconds_total",
            "Http in requests duration",
            ["txnId", "type"]
        );
        $totalDuration->incBy($phpDuration, [$txnId, 'php']);
        $totalDuration->incBy($webExternalDuration, [$txnId, 'web_external']);
        $totalDuration->incBy($this->dbOutTimes, [$txnId, 'db']);

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

    public function consoleCommandExecution(float $duration, bool $commandFailed): void
    {
        $txnId = $this->getTxnId();
        $totalCount = $this->prom->getOrRegisterCounter(
            config('telemetry.namespace'),
            "cli_runs_total",
            "Executions count",
            ["txnId", "status"]
        );
        $totalCount->inc([$txnId, $commandFailed ? 'fail' : 'success']);

        $totalDuration = $this->prom->getOrRegisterCounter(
            config('telemetry.namespace'),
            "cli_runs_seconds_total",
            "Executions count",
            ["txnId",]
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
        foreach (config('telemetry.service-prefixes') as $servicePrefix) {
            if (str_starts_with($endpoint, $servicePrefix)) {
                $service .= $servicePrefix;
                $endpoint = str_replace($servicePrefix, "", $endpoint);
            }
        }

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
    }

    public function dbQuery($duration, $sql): void
    {
        $txnId = $this->getTxnId();
        $query = self::normalizeDbQuery($sql);
        $durationSeconds = $duration / 1000;

        $this->dbOutTimes += $durationSeconds;

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
    }

    public function logMessages(MessageLogged $event): void
    {
        $totalCount = $this->prom->getOrRegisterCounter(
            config('telemetry.namespace'),
            'log_messages',
            'Log messages count',
            ['txnId', 'level'],
        );

        $txnId = $this->getTxnId();
        $totalCount->inc([$txnId, $event->level]);
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

    protected function webExternalDuration(): float
    {
        $sum = 0;
        foreach ($this->httpOutTimes as [$start, $end]) {
            $sum += $end - $start;
        }
        return $sum;
    }
}