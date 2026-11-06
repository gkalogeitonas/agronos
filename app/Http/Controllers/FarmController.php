<?php

namespace App\Http\Controllers;

use App\Enums\DeviceStatus;
use App\Enums\FarmHealth;
use App\Http\Requests\FarmRequest;
use App\Http\Resources\AlertResource;
use App\Http\Resources\DeviceResource;
use App\Models\Alert;
use App\Models\Farm;
use App\Services\FarmOverviewService;
use App\Services\TimeSeries\FarmTimeSeriesService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class FarmController extends Controller
{
    use AuthorizesRequests;

    /**
     * How many sensors and devices the farm page lists before linking to the full, filtered list.
     */
    private const SHOW_PAGE_LIST_LIMIT = 10;

    public function __construct() {}

    /**
     * Display a listing of the resource.
     */
    public function index(FarmOverviewService $farmOverview): Response
    {
        $this->authorize('viewAny', Farm::class);
        $this->authorize('viewAny', Alert::class);
        $farms = Farm::orderBy('name')->get();

        return Inertia::render('Farms/Index', [
            'farms' => $farmOverview->summarize($farms),
            'farmHealthOptions' => FarmHealth::options(),
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return Inertia::render('Farms/Create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(FarmRequest $request)
    {
        $validated = $request->validated();
        $validated['user_id'] = Auth::id();

        Farm::create($validated);

        return redirect()->route('farms.index')
            ->with('success', 'Farm created successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(Farm $farm)
    {
        $this->authorize('view', $farm);
        $this->authorize('viewAny', Alert::class);
        $sensors = $farm->sensors()->farmRelevant()->orderBy('sensors.name')->limit(self::SHOW_PAGE_LIST_LIMIT)->get();
        $sensorLocations = $farm->sensors()->farmRelevant()->get(['sensors.id', 'sensors.name', 'sensors.lat', 'sensors.lon']);

        // Calculate sensor statistics from database (exclude internal-only types)
        $totalSensors = $farm->sensors()->farmRelevant()->count();
        $sensorTypeStats = $farm->sensors()
            ->farmRelevant()
            ->selectRaw('sensors.type, COUNT(*) as count')
            ->whereNotNull('sensors.type')
            ->groupBy('sensors.type')
            ->pluck('count', 'type')
            ->toArray();

        // Calculate average of last reading per sensor type (using sensors.last_reading)
        // Only include sensors whose device is currently ONLINE
        $lastAvgByType = $farm->sensors()
            ->farmRelevant()
            ->deviceOnline()
            ->selectRaw('sensors.type, AVG(sensors.last_reading) as avg_last_reading')
            ->whereNotNull('sensors.type')
            ->whereNotNull('sensors.last_reading')
            ->groupBy('sensors.type')
            ->pluck('avg_last_reading', 'type')
            ->mapWithKeys(function ($v, $k) {
                // cast to float and keep keys as type
                return [$k => $v !== null ? (float) $v : null];
            })->toArray();

        // Prepare time series service for deferred evaluation (do not call it now)
        $ts = app(FarmTimeSeriesService::class);

        // Split statistics so frontend can defer / lazy-load / poll them independently.
        $sensorDbStats = [
            'totalSensors' => $totalSensors,
            'sensorTypeStats' => $sensorTypeStats,
            'lastAvgByType' => $lastAvgByType,
        ];

        $devices = $farm->devices()->orderBy('name')->limit(self::SHOW_PAGE_LIST_LIMIT)->get();

        return Inertia::render('Farms/Show', [
            'farm' => $farm,
            'sensors' => $sensors,
            'sensorLocations' => $sensorLocations->map->only(['id', 'name', 'lat', 'lon'])->values(),
            'devices' => DeviceResource::collection($devices)->resolve(),
            'devicesTotal' => $farm->devices()->count(),
            'devicesOnline' => $farm->devices()->where('status', DeviceStatus::ONLINE)->count(),
            'alerts' => AlertResource::collection(
                $farm->alerts()->open()->withLineDetails()->mostUrgentFirst()->limit(Alert::CARD_LIMIT)->get()
            )->resolve(),
            'sensorDbStats' => $sensorDbStats,
            // defer heavy/time-series work to a follow-up request so initial page is fast
            'timeSeriesStats' => Inertia::defer(fn () => $ts->farmStats($farm, '-24h')),
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Farm $farm)
    {
        $this->authorize('update', $farm);

        return Inertia::render('Farms/Edit', [
            'farm' => $farm,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(FarmRequest $request, Farm $farm)
    {
        $this->authorize('update', $farm);
        $farm->update($request->validated());

        return redirect()->route('farms.show', $farm)
            ->with('success', 'Farm updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Farm $farm)
    {
        $this->authorize('delete', $farm);
        $farm->delete();

        return redirect()->route('farms.index')
            ->with('success', 'Farm deleted successfully.');
    }
}
