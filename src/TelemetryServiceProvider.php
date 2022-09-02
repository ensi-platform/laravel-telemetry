<?php

namespace Ensi\LaravelTelemetry;

use Ensi\LaravelTelemetry\Console\CliTelemetryEvensSubscriber;
use Ensi\LaravelTelemetry\Controllers\TelemetryController;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class TelemetryServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->app->bind(Metrics::class, function () {
            return Metrics::getInstance();
        });

        $this->app['events']->listen(MessageLogged::class, function (MessageLogged $event) {
            Metrics::getInstance()->logMessages($event);
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

        Event::subscribe(new CliTelemetryEvensSubscriber());
    }
}