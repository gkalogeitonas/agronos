<?php

namespace App\Http\Controllers;

use App\Enums\DeviceStatus;
use App\Enums\FarmHealth;
use App\Http\Requests\FarmRequest;
use App\Http\Resources\AlertResource;
use App\Http\Resources\DeviceResource;
use App\Models\Alert;
use App\Models\Crop;
use App\Models\Farm;
use App\Models\Planting;
use App\Models\Zone;
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
        $sensors = $farm->sensors()->farmRelevant()->with('zone:id,name')->orderBy('sensors.name')->limit(self::SHOW_PAGE_LIST_LIMIT)->get();
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
            'zones' => $this->zonesWithCrops($farm),
            'nextZoneName' => $farm->nextZoneName(),
            'plantings' => $this->plantings($farm),
            'crops' => Crop::orderBy('name')->get(['id', 'name']),
            'sensorDbStats' => $sensorDbStats,
            // defer heavy/time-series work to a follow-up request so initial page is fast
            'timeSeriesStats' => Inertia::defer(fn () => $ts->farmStats($farm, '-24h')),
            'readingTrends' => Inertia::defer(fn () => $ts->readingTrends($farm)),
            'moistureSeries' => Inertia::defer(fn () => $ts->moistureSeriesByZone($farm)),
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

    /**
     * The farm's zones, each with its sensor and planting counts and its crop: its current planting's,
     * otherwise the farm's current whole-farm planting's (ADR-0010), otherwise none.
     *
     * @return list<array{id: int, name: string, sensors_count: int, plantings_count: int, crop: array{name: string, from_farm: bool}|null}>
     */
    private function zonesWithCrops(Farm $farm): array
    {
        $today = now()->startOfDay();
        $farmCrop = $farm->plantings()->onLand(null)->currentOn($today)->with('crop:id,name')->first()?->crop->name;

        return $farm->zones()
            ->withCount(['sensors', 'plantings'])
            ->with(['plantings' => fn ($query) => $query->currentOn($today)->with('crop:id,name')])
            ->orderBy('id')
            ->get(['id', 'name'])
            ->map(function (Zone $zone) use ($farmCrop) {
                $zoneCrop = $zone->plantings->first()?->crop->name;

                return [
                    'id' => $zone->id,
                    'name' => $zone->name,
                    'sensors_count' => $zone->sensors_count,
                    'plantings_count' => $zone->plantings_count,
                    'crop' => match (true) {
                        $zoneCrop !== null => ['name' => $zoneCrop, 'from_farm' => false],
                        $farmCrop !== null => ['name' => $farmCrop, 'from_farm' => true],
                        default => null,
                    },
                ];
            })
            ->values()
            ->all();
    }

    /**
     * The farm's plantings, of the whole farm and of its zones: current first, then upcoming, then past.
     * At the same status the whole farm comes before its zones, then the most recent start first.
     *
     * @return list<array{id: int, zone_id: int|null, zone_name: string|null, crop_id: int, crop_name: string, variety: string|null, started_on: string|null, ended_on: string|null, status: string}>
     */
    private function plantings(Farm $farm): array
    {
        $today = now()->startOfDay();
        $order = [Planting::STATUS_CURRENT => 0, Planting::STATUS_UPCOMING => 1, Planting::STATUS_PAST => 2];

        return $farm->plantings()->with(['crop:id,name', 'zone:id,name'])->get()
            ->map(fn (Planting $planting) => [
                'id' => $planting->id,
                'zone_id' => $planting->zone_id,
                'zone_name' => $planting->zone?->name,
                'crop_id' => $planting->crop_id,
                'crop_name' => $planting->crop->name,
                'variety' => $planting->variety,
                'started_on' => $planting->started_on?->toDateString(),
                'ended_on' => $planting->ended_on?->toDateString(),
                'status' => $planting->statusOn($today),
            ])
            ->sortBy([
                fn (array $a, array $b) => $order[$a['status']] <=> $order[$b['status']],
                fn (array $a, array $b) => ($a['zone_id'] !== null) <=> ($b['zone_id'] !== null),
                fn (array $a, array $b) => strcmp($b['started_on'] ?? '', $a['started_on'] ?? ''),
            ])
            ->values()
            ->all();
    }
}
