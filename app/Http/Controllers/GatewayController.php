<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreGatewayRequest;
use App\Models\Gateway;
use App\Services\MqttCredentialService;
use App\Services\MqttTopics;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class GatewayController extends Controller
{
    use AuthorizesRequests;

    private const BROKER_UNAVAILABLE = 'The MQTT broker could not be reached. Try again in a moment.';

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
     */
    public function store(StoreGatewayRequest $request): RedirectResponse
    {
        $this->authorize('create', Gateway::class);

        $username = 'gw-'.Str::uuid();
        $password = $this->generatePassword();

        if (! $this->credentials->provisionBrokerUser($username, $password, MqttTopics::loraGatewayBase($username), ['gateway_username' => $username])) {
            return back()->withInput()->with('error', self::BROKER_UNAVAILABLE);
        }

        $gateway = Gateway::create([
            'user_id' => $request->user()->id,
            'name' => $request->validated('name'),
            'mqtt_username' => $username,
            'mqtt_password' => $password,
        ]);

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
        ]);
    }

    /**
     * Give the gateway a new password, storing it only once the broker accepted it.
     */
    public function rotatePassword(Gateway $gateway): RedirectResponse
    {
        $this->authorize('update', $gateway);

        $password = $this->generatePassword();

        if (! $this->credentials->provisionBrokerUser($gateway->mqtt_username, $password, MqttTopics::loraGatewayBase($gateway->mqtt_username), ['gateway_id' => $gateway->id])) {
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

    private function generatePassword(): string
    {
        return bin2hex(random_bytes(16));
    }
}
