<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Services\InfluxDBService;
use App\Services\InfluxDBFake;

class InfluxDBServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Bind real or fake service based on config
        $this->app->singleton(InfluxDBService::class, function ($app) {
            if (config('services.influxdb.fake')) {
                return new InfluxDBFake();
            }
            return new InfluxDBService();
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
