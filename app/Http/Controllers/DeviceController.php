<?php

namespace App\Http\Controllers;

use App\Enums\DeviceType;
use App\Http\Requests\RegisterDeviceRequest;
use App\Models\Device;
use App\Services\MqttCredentialService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;

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
            'deviceTypes' => collect(DeviceType::cases())->map(fn ($type) => [
                'label' => DeviceType::labels()[$type->value],
                'value' => $type->value,
            ])->values(),
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
            'secret' => Hash::make($validated['secret']), // Hashing here is correct
            'name' => $validated['name'],
            'type' => $validated['type'],
            'status' => 'registered',
        ]);

        return redirect()->route('devices.index')->with('success', 'Device registered successfully');
    }

    /**
     * Display the specified resource.
     */
    public function show(Device $device)
    {
        $this->authorize('view', $device);

        $sensors = $device->sensors()->get();

        return Inertia::render('Devices/Show', [
            'device' => $device,
            'sensors' => $sensors,
        ]);
    }

    /**
     * Ensure MQTT credentials exist for the given device and return them via flash.
     */
    public function createMqttCredentials(Device $device)
    {
        $this->authorize('update', $device);

        $service = app(MqttCredentialService::class);
        $result = $service->createCredentials($device);

        return redirect()->route('devices.show', $device)->with('mqtt_credentials', $result);
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
