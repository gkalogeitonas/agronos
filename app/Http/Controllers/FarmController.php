<?php

namespace App\Http\Controllers;

use App\Http\Requests\FarmRequest;
use App\Models\Farm;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class FarmController extends Controller
{
    use AuthorizesRequests;

    public function __construct()
    {

    }

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
        return Inertia::render('Farms/Show', [
            'farm' => $farm,
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
