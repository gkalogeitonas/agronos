<?php

use App\Providers\AppServiceProvider;
use App\Providers\AuthServiceProvider;
use App\Providers\InfluxDBServiceProvider;

return [
    AppServiceProvider::class,
    AuthServiceProvider::class,
    InfluxDBServiceProvider::class,
];
