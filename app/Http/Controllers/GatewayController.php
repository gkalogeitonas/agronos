<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreGatewayRequest;
use App\Jobs\RemoveBrokerUser;
use App\Models\Gateway;
use App\Services\MqttCredentialService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class GatewayController extends Controller
{
    use AuthorizesRequests;

    public const BROKER_UNAVAILABLE = 'The MQTT broker could not be reached. Try again in a moment.';

    public function __construct(private MqttCredentialService $credentials) {}

    /**
     * Display a listing of the resource.
     */
    public function index(): Response
    {
        $this->authorize('viewAny', Gateway::class);

        return Inertia::render('Gateways/Index', [
            'gateways' => Gateway::query()->latest()->get(['id', 'name', 'mqtt_username', 'created_at']),
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): Response
    {
        $this->authorize('create', Gateway::class);

        return Inertia::render('Gateways/Create');
    }

    /**
     * Create the gateway's EMQX user and rules, and store the gateway only once the broker accepted them.
     * Whatever reached the broker before a failure is queued for removal, so no orphan is left behind.
     */
    public function store(StoreGatewayRequest $request): RedirectResponse
    {
        $this->authorize('create', Gateway::class);

        $gateway = new Gateway([
            'user_id' => $request->user()->id,
            'name' => $request->validated('name'),
            'mqtt_username' => Gateway::generateUsername(),
            'mqtt_password' => MqttCredentialService::generatePassword(),
        ]);

        if (! $this->credentials->provisionGateway($gateway, $gateway->mqtt_password)) {
            RemoveBrokerUser::dispatch($gateway->mqtt_username);

            return back()->withInput()->with('error', self::BROKER_UNAVAILABLE);
        }

        try {
            $gateway->save();
        } catch (\Throwable $e) {
            RemoveBrokerUser::dispatch($gateway->mqtt_username);

            throw $e;
        }

        return redirect()->route('gateways.show', $gateway)->with('success', 'Gateway created.');
    }

    /**
     * Display the specified resource.
     */
    public function show(Gateway $gateway): Response
    {
        $this->authorize('view', $gateway);

        return Inertia::render('Gateways/Show', [
            'gateway' => $gateway->only(['id', 'name', 'mqtt_username', 'created_at']),
            'password' => $gateway->mqtt_password,
            'topic' => $gateway->dataTopic(),
            'brokerUrl' => config('services.mqtt.public_url'),
        ]);
    }

    /**
     * Give the gateway a new password, storing it only once the broker accepted it.
     */
    public function rotatePassword(Gateway $gateway): RedirectResponse
    {
        $this->authorize('update', $gateway);

        $password = MqttCredentialService::generatePassword();

        if (! $this->credentials->provisionGateway($gateway, $password)) {
            return back()->with('error', self::BROKER_UNAVAILABLE);
        }

        $gateway->update(['mqtt_password' => $password]);

        return redirect()->route('gateways.show', $gateway)->with('success', 'Password rotated. Enter the new one in the gateway.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Gateway $gateway): RedirectResponse
    {
        $this->authorize('delete', $gateway);
        $gateway->delete();

        return redirect()->route('gateways.index')->with('success', 'Gateway deleted.');
    }
}
