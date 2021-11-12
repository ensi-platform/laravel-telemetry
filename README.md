# Laravel telemetry

`Deprecated, use https://github.com/ensi-platform/laravel-telemetry instead`

Пакет для сбора метрик и отправки их в prometheus

## Установка

1. `composer require greensight/laravel-telemetry`

Зарегистрировать обработчик ошибок
```
# app/Exceptions/Handler.php
...
public function register()
    {
        $this->reportable(function (Throwable $e) {
            if ($this->shouldReport($e)) {
                Metrics::getInstance()->handleGlobalException($e);
            }
        });
    }
...
```

## Лицензия

[The MIT License (MIT)](LICENSE.md).
