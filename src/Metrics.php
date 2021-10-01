<?php

namespace Greensight\LaravelTelemetry;

use Illuminate\Support\Facades\Route;
use Prometheus\CollectorRegistry;
use Prometheus\RenderTextFormat;
use Prometheus\Storage\APC;
use Prometheus\Storage\Redis;
use PrometheusPushGateway\PushGateway;

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
        return !!config('telemetry.redis-host');
    }

    public function __construct()
    {
        if (php_sapi_name() == "cli" && self::cliMetricsEnabled()) {
            $metricsStorage = new Redis([
                'host' => config('telemetry.redis-host'),
                'port' => config('telemetry.redis-port'),
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
        $gatewayHost = config('telemetry.push-gateway');
        if (!$gatewayHost) return;

        $pushGateway = new PushGateway($gatewayHost);
        $pushGateway->push($this->prom, 'ensi_cli', ['app_name' => config('app.name')]);
    }

    public function getTxnId(): string
    {
        if (!$this->txnId) {
            if (php_sapi_name() == "cli") {
                if ($_SERVER['argv'][0] == 'artisan' && isset($_SERVER['argv'][1])) {
                    $this->txnId = $_SERVER['argv'][1];
                } else {
                    $this->txnId = 'undefined cli';
                }
            } else {
                $laravelRoute = Route::current();
                if ($laravelRoute) {
                    $this->txnId = $laravelRoute->uri;
                } else {
                    if (isset($_SERVER['REQUEST_METHOD']) && isset($_SERVER['REQUEST_URI'])) {
                        $this->txnId = $_SERVER['REQUEST_METHOD'] . ' ' . self::normalizeHttpUri($_SERVER['REQUEST_URI']);
                    } else {
                        $this->txnId = 'undefined web';
                    }
                }
            }

        }
        return $this->txnId;
    }

    public function mainWebTransaction($duration, $statusCode): void
    {
        $txnId = $this->getTxnId();
        $summary = $this->prom->getOrRegisterSummary(
            config('telemetry.namespace'),
            "http_perc",
            'Request duration, s',
            ["txnId"],
            config('telemetry.http_percentile_window'),
            json_decode(config('telemetry.http_percentiles')),
        );
        $summary->observe($duration, [$txnId]);

        $counter = $this->prom->getOrRegisterCounter(
            config('telemetry.namespace'),
            "http",
            "Request code count",
            ["txnId", "code"]
        );
        $counter->inc([$txnId, $statusCode]);
    }

    public function mainCliTransaction($duration)
    {
        $txnId = $this->getTxnId();
        $summary = $this->prom->getOrRegisterSummary(
            config('telemetry.namespace'),
            "cli_perc",
            'Execution time, s',
            ["txnId"],
            config('telemetry.cli_percentile_window'),
            json_decode(config('telemetry.cli_percentiles')),
        );
        $summary->observe($duration, [$txnId]);

        $counter = $this->prom->getOrRegisterCounter(
            config('telemetry.namespace'),
            "cli",
            "Executions count",
            ["txnId"]
        );
        $counter->inc([$txnId]);
    }

    public function mainQueueTransaction($jobName, $duration)
    {
        $summary = $this->prom->getOrRegisterSummary(
            config('telemetry.namespace'),
            "queue_perc",
            'Execution time, s',
            ["txnId"],
            config('telemetry.cli_percentile_window'),
            json_decode(config('telemetry.cli_percentiles')),
        );
        $summary->observe($duration, [$jobName]);

        $counter = $this->prom->getOrRegisterCounter(
            config('telemetry.namespace'),
            "queue",
            "Executions count",
            ["txnId"]
        );
        $counter->inc([$jobName]);
    }

    public function httpRequest($service, $endpoint, $duration)
    {
        $txnId = $this->getTxnId();
        $histogram = $this->prom->getOrRegisterSummary(
            config('telemetry.namespace'),
            "out_http_perc",
            'Request duration, s',
            ["txnId", "service", "to"],
            config('telemetry.out_http_percentile_window'),
            json_decode(config('telemetry.out_http_percentiles')),
        );
        $histogram->observe($duration, [$txnId, $service, $endpoint]);

        $counter = $this->prom->getOrRegisterCounter(
            config('telemetry.namespace'),
            "out_http",
            "Request code count",
            ["txnId", "service", "to"]
        );
        $counter->inc([$txnId, $service, $endpoint]);
    }

    public function dbQuery($duration, $sql)
    {
        $txnId = $this->getTxnId();
        $query = self::normalizeDbQuery($sql);
        $durationSeconds = $duration / 1000;

        $histogram = $this->prom->getOrRegisterSummary(
            config('telemetry.namespace'),
            "db_perc",
            'Database query duration, ms',
            ['txnId', 'query'],
            config('telemetry.db_percentile_window'),
            json_decode(config('telemetry.db_percentiles')),
        );
        $histogram->observe($durationSeconds, [$txnId, $query]);

        $counter = $this->prom->getOrRegisterCounter(
            config('telemetry.namespace'),
            "db",
            "Request code count",
            ['txnId', 'query'],
        );
        $counter->inc([$txnId, $query]);
    }
}