<?php

namespace App\Http\Controllers;

use App\Enums\DeviceStatus;
use App\Models\Device;
use App\Models\Farm;
use App\Services\FarmOverviewService;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Organisation overview across all of the user's farms.
     */
    public function __invoke(FarmOverviewService $farmOverview): Response
    {
        $devices = Device::query()->get(['id', 'status', 'battery_level']);

        return Inertia::render('Dashboard', [
            'fleet' => [
                'farms' => Farm::query()->count(),
                'devicesTotal' => $devices->count(),
                'devicesOnline' => $devices->where('status', DeviceStatus::ONLINE)->count(),
                'lowBattery' => $devices->filter(fn (Device $device) => $device->battery_level !== null && $device->battery_level < 20)->count(),
            ],
            'farms' => $farmOverview->summarize(Farm::query()->orderBy('name')->get()),
        ]);
    }
}
