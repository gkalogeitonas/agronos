<?php

namespace App\Providers;

use App\Models\Farm;
use App\Policies\FarmPolicy;
use App\Models\Device;
use App\Policies\DevicePolicy;
use App\Models\Sensor;
use App\Policies\SensorPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        Farm::class => FarmPolicy::class,
        Device::class => DevicePolicy::class,
        Sensor::class => SensorPolicy::class,
    ];

    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        $this->registerPolicies();
    }
}
