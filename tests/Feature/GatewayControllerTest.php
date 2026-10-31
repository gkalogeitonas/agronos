<?php

use App\Http\Controllers\GatewayController;
use App\Jobs\RemoveBrokerUser;
use App\Models\Gateway;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['services.emqx.topic_prefix' => 'agronos']);
});

describe('GatewayController@index', function () {
    test('guest is redirected to login', function () {
        $this->get(route('gateways.index'))->assertRedirect(route('login'));
    });

    test('lists only the user\'s own gateways and never their passwords', function () {
        $user = User::factory()->create();
        $gateway = Gateway::factory()->for($user)->create(['name' => 'Barn gateway']);
        Gateway::factory()->create(['name' => 'Someone else\'s gateway']);

        $this->actingAs($user)
            ->get(route('gateways.index'))
            ->assertInertia(fn ($page) => $page->component('Gateways/Index')
                ->has('gateways', 1)
                ->where('gateways.0.name', 'Barn gateway')
                ->where('gateways.0.mqtt_username', $gateway->mqtt_username)
                ->missing('gateways.0.mqtt_password')
            );
    });
});

describe('GatewayController@store', function () {
    test('writes the EMQX rules and user and then stores the gateway', function () {
        $uuid = Str::freezeUuids();
        fakeEmqxProvisioning("gw-{$uuid}");
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('gateways.store'), ['name' => 'Barn gateway']);

        $gateway = Gateway::allTenants()->sole();
        $response->assertRedirect(route('gateways.show', $gateway));
        expect($gateway->user_id)->toBe($user->id)
            ->and($gateway->name)->toBe('Barn gateway')
            ->and($gateway->mqtt_username)->toBe("gw-{$uuid}")
            ->and($gateway->mqtt_password)->toBe(emqxPasswordSent())
            ->and(emqxRulesWritten("gw-{$uuid}", "agronos/lora/gw-{$uuid}"))->toBeTrue();
    });

    test('requires a name', function () {
        Http::preventStrayRequests();

        $this->actingAs(User::factory()->create())
            ->post(route('gateways.store'), ['name' => ''])
            ->assertSessionHasErrors(['name' => 'The name field is required.']);

        expect(Gateway::allTenants()->count())->toBe(0);
    });

    test('stores nothing and queues cleanup when EMQX rejects the rules or the user', function (int $userStatus, int $rulesStatus) {
        Bus::fake([RemoveBrokerUser::class]);
        $uuid = Str::freezeUuids();
        fakeEmqxProvisioning("gw-{$uuid}", userStatus: $userStatus, rulesStatus: $rulesStatus);

        $this->actingAs(User::factory()->create())
            ->from(route('gateways.create'))
            ->post(route('gateways.store'), ['name' => 'Barn gateway'])
            ->assertRedirect(route('gateways.create'))
            ->assertSessionHas('error', GatewayController::BROKER_UNAVAILABLE);

        expect(Gateway::allTenants()->count())->toBe(0);
        Bus::assertDispatched(RemoveBrokerUser::class, fn (RemoveBrokerUser $job) => $job->username === "gw-{$uuid}");
    })->with([
        'rules rejected' => [201, 503],
        'user rejected' => [503, 204],
    ]);

    test('queues cleanup of the broker user when the gateway cannot be stored', function () {
        Bus::fake([RemoveBrokerUser::class]);
        $uuid = Str::freezeUuids();
        fakeEmqxProvisioning("gw-{$uuid}");
        Gateway::factory()->create(['mqtt_username' => "gw-{$uuid}"]);

        $this->actingAs(User::factory()->create())
            ->post(route('gateways.store'), ['name' => 'Barn gateway'])
            ->assertServerError();

        expect(Gateway::allTenants()->count())->toBe(1);
        Bus::assertDispatched(RemoveBrokerUser::class, fn (RemoveBrokerUser $job) => $job->username === "gw-{$uuid}");
    });
});

describe('GatewayController@show', function () {
    test('gives the owner the broker, username, password and topic', function () {
        config(['services.mqtt.public_url' => 'mqtts://mqtt.example.com:8883']);
        $user = User::factory()->create();
        $gateway = Gateway::factory()->for($user)->create(['mqtt_username' => 'gw-barn', 'mqtt_password' => 'barn-pass']);

        $this->actingAs($user)
            ->get(route('gateways.show', $gateway))
            ->assertInertia(fn ($page) => $page->component('Gateways/Show')
                ->where('brokerUrl', 'mqtts://mqtt.example.com:8883')
                ->where('gateway.mqtt_username', 'gw-barn')
                ->where('password', 'barn-pass')
                ->where('topic', 'agronos/lora/gw-barn/data')
                ->missing('gateway.mqtt_password')
            );
    });

    test('another user cannot see a gateway', function () {
        $gateway = Gateway::factory()->create();

        $this->actingAs(User::factory()->create())
            ->get(route('gateways.show', $gateway))
            ->assertNotFound();
    });
});

describe('GatewayController@rotatePassword', function () {
    test('updates the existing EMQX user and then the stored password', function () {
        fakeEmqxProvisioning('gw-barn', userStatus: 409);
        $user = User::factory()->create();
        $gateway = Gateway::factory()->for($user)->create(['mqtt_username' => 'gw-barn', 'mqtt_password' => 'old-pass']);

        $this->actingAs($user)
            ->post(route('gateways.rotate-password', $gateway))
            ->assertRedirect(route('gateways.show', $gateway));

        expect($gateway->fresh()->mqtt_password)->not->toBe('old-pass')
            ->toBe(emqxPasswordSent())
            ->and(emqxRulesWritten('gw-barn', 'agronos/lora/gw-barn'))->toBeTrue();
    });

    test('keeps the stored password when EMQX rejects the user update', function () {
        fakeEmqxProvisioning('gw-barn', userStatus: 503);
        $user = User::factory()->create();
        $gateway = Gateway::factory()->for($user)->create(['mqtt_username' => 'gw-barn', 'mqtt_password' => 'old-pass']);

        $this->actingAs($user)
            ->post(route('gateways.rotate-password', $gateway))
            ->assertSessionHas('error', GatewayController::BROKER_UNAVAILABLE);

        expect($gateway->fresh()->mqtt_password)->toBe('old-pass');
    });

    test('leaves the broker password alone when EMQX rejects the rules', function () {
        fakeEmqxProvisioning('gw-barn', rulesStatus: 503);
        $user = User::factory()->create();
        $gateway = Gateway::factory()->for($user)->create(['mqtt_username' => 'gw-barn', 'mqtt_password' => 'old-pass']);

        $this->actingAs($user)
            ->post(route('gateways.rotate-password', $gateway))
            ->assertSessionHas('error', GatewayController::BROKER_UNAVAILABLE);

        expect($gateway->fresh()->mqtt_password)->toBe('old-pass')
            ->and(emqxPasswordSent())->toBeNull();
    });

    test('another user cannot rotate a gateway\'s password', function () {
        Http::preventStrayRequests();
        $gateway = Gateway::factory()->create(['mqtt_password' => 'old-pass']);

        $this->actingAs(User::factory()->create())
            ->post(route('gateways.rotate-password', $gateway))
            ->assertNotFound();

        expect($gateway->fresh()->mqtt_password)->toBe('old-pass');
    });
});

describe('GatewayController@destroy', function () {
    test('the owner deleting a gateway queues the removal of its EMQX user and rules', function () {
        Bus::fake([RemoveBrokerUser::class]);
        $user = User::factory()->create();
        $gateway = Gateway::factory()->for($user)->create(['mqtt_username' => 'gw-barn']);

        $this->actingAs($user)
            ->delete(route('gateways.destroy', $gateway))
            ->assertRedirect(route('gateways.index'));

        $this->assertModelMissing($gateway);
        Bus::assertDispatched(RemoveBrokerUser::class, fn (RemoveBrokerUser $job) => $job->username === 'gw-barn');
    });

    test('another user cannot delete a gateway so the broker is left alone', function () {
        Bus::fake([RemoveBrokerUser::class]);
        $gateway = Gateway::factory()->create();

        $this->actingAs(User::factory()->create())
            ->delete(route('gateways.destroy', $gateway))
            ->assertNotFound();

        $this->assertModelExists($gateway);
        Bus::assertNotDispatched(RemoveBrokerUser::class);
    });
});
