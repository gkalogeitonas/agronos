<?php

namespace App\Http\Controllers;

use App\Models\Device;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use App\Http\Requests\RegisterDeviceRequest;

class DeviceController extends Controller
{
    use AuthorizesRequests;
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $devices = Device::all();
        return Inertia::render('Devices/Index', [
            'devices' => $devices,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return Inertia::render('Devices/Create', [
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(RegisterDeviceRequest $request)
    {
        $validated = $request->validated();

        $device = Device::create([
            'user_id' => $request->user()->id,
            'uuid' => $validated['uuid'],
            'secret' => $validated['secret'],
            'name' => $validated['name'],
            'type' => $validated['type'],
            'status' => 'registered',
        ]);

        return response()->json([
            'message' => 'Device registered successfully',
            'device' => $device,
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Device $device)
    {
        $this->authorize('view', $device);
        return Inertia::render('Devices/Show', [
            'device' => $device,
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Device $device)
    {
        $this->authorize('delete', $device);
        $device->delete();
        return redirect()->route('devices.index');
    }
}
