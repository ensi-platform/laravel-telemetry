<?php

namespace Greensight\LaravelTelemetry;

use Greensight\LaravelTelemetry\Controllers\TelemetryController;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class TelemetryServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->app->bind(Metrics::class, function () {
            return Metrics::getInstance();
        });
    }

    public function boot()
    {
        $this->mergeConfigFrom(__DIR__.'/../config/telemetry.php', 'telemetry');

        Route::namespace('Ensi\LaravelTelemetry\Controllers')
            ->get(config('telemetry.metrics-route'), [TelemetryController::class, 'metrics'])
            ->name('telemetry.metrics');

        DB::listen(function ($query) {
            Metrics::getInstance()->dbQuery($query->time, $query->sql);
        });
    }
}