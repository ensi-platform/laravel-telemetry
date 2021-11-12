# Laravel telemetry

Пакет для сбора не специфичных метрик с laravel приложения и отправки их в prometheus.
Позволяет отслеживать количество и длительность выполнения входящих и исходящих http запросов,
запросов к БД, а так же количество непойманных исключений.

## Installation

1. Установка пакета
```shell
composer require ensi/laravel-telemetry
```

2. Регистрация сервиса в config/app.php
```
'providers' => [
    Ensi\LaravelTelemetry\TelemetryServiceProvider::class,
]
```
3. Установка счётчика входящих http запросов в public/index.php
Необходимо добавить код в самый конец файла.
```
Metrics::getInstance()->httpInRequest(
    microtime(true) - LARAVEL_START,
    $response->getStatusCode()
);
```
4. Установка счётчика выполнения консольных команд.
Необходимо унаследовать свои консольные команды от `Ensi\LaravelTelemetry\Console\Command`
5. Установка счётчика выполенения заданий очереди.
Необходимо унаследовать свои задания от `Ensi\LaravelTelemetry\Console\Job` и использовать метод `run()` вместо `handle()`.
6. Установка счётчика исходящих http запросов.
Метод `Ensi\LaravelTelemetry\Http\GuzzleHandler::middleware()` возвращает middleware для guzzle, который вы можете использовать при настройке http клиента.

## Configuration

Переменные окружения

| Name | Default | Description |
| --- | --- | --- |
| TELEMETRY_NAMESPACE | app | prefix of all prometheus metrics |
| TELEMETRY_BACKGROUND_ENABLED | false | enable metrics for background processes (cli and queue) |
| TELEMETRY_PUSH_GATEWAY | http://localhost:9091 | address of prometheus pushgateway (for cli and queue metrics) |
| TELEMETRY_REDIS_HOST | | host of redis metrics storage (for cli and queue metrics) |
| TELEMETRY_REDIS_PORT | 6379 | port of redis metrics storage |
| TELEMETRY_HTTP_IN_HISTOGRAM_ENABLED | false | enable histogram metric for incoming http requests |
| TELEMETRY_HTTP_OUT_HISTOGRAM_ENABLED | false | enable histogram metric for outgoing http requests |
| TELEMETRY_DB_QUERY_HISTOGRAM_ENABLED | false | enable histogram metric for db queries |

Настройки в configs/telemetry.php

| Name | Default | Description |
| --- | --- | ---|
| metrics-route | /metrics | prometheus metrics endpoint |
| http-in-endpoints-ignore | ['metrics'] | list of http endpoints for ignore |
| http-in-histogram.buckets | [0.016, 0.032, 0.064, 0.128, 0.256, 0.512, 1.024] | buckets for histogram |
| http-out-histogram.buckets | [0.016, 0.032, 0.064, 0.128, 0.256, 0.512, 1.024] | buckets for histogram |
| db-query-histogram.buckets | [0.001, 0.002, 0.004, 0.008, 0.016, 0.032, 0.064, 0.128, 0.256, 0.512, 1.024] | buckets for histogram |

## Security Vulnerabilities

Please review [our security policy](../../security/policy) on how to report security vulnerabilities.

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
