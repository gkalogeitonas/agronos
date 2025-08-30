<?php

namespace App\Http\Controllers;

use App\Http\Requests\FarmRequest;
use App\Models\Farm;
use App\Services\TimeSeries\FarmTimeSeriesService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class FarmController extends Controller
{
    use AuthorizesRequests;

    public function __construct() {}

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $this->authorize('viewAny', Farm::class);
        $farms = Farm::where('user_id', Auth::id())->get();

        return Inertia::render('Farms/Index', [
            'farms' => $farms,
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
        $sensors = $farm->sensors()->get();

        // Calculate sensor statistics from database
        $totalSensors = $farm->sensors()->count();
        $sensorTypeStats = $farm->sensors()
            ->selectRaw('type, COUNT(*) as count')
            ->whereNotNull('type')
            ->groupBy('type')
            ->pluck('count', 'type')
            ->toArray();

        // Calculate average of last reading per sensor type (using sensors.last_reading)
        // Only include sensors whose device is currently ONLINE
        $lastAvgByType = $farm->sensors()
            ->deviceOnline()
            ->selectRaw('type, AVG(last_reading) as avg_last_reading')
            ->whereNotNull('type')
            ->whereNotNull('last_reading')
            ->groupBy('type')
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

        return Inertia::render('Farms/Show', [
            'farm' => $farm,
            'sensors' => $sensors,
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
