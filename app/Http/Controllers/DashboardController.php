<?php

namespace App\Http\Controllers;

use App\Enums\DeviceStatus;
use App\Models\Device;
use App\Models\Farm;
use App\Services\FarmOverviewService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    use AuthorizesRequests;

    /**
     * Organisation overview across all of the user's farms.
     */
    public function __invoke(FarmOverviewService $farmOverview): Response
    {
        $this->authorize('viewAny', Farm::class);
        $this->authorize('viewAny', Device::class);

        return Inertia::render('Dashboard', [
            'fleet' => [
                'farms' => Farm::query()->count(),
                'devicesTotal' => Device::query()->count(),
                'devicesOnline' => Device::query()->where('status', DeviceStatus::ONLINE)->count(),
                'lowBattery' => Device::query()->lowBattery()->count(),
            ],
            'farms' => $farmOverview->summarize(Farm::query()->orderBy('name')->get()),
        ]);
    }
}
