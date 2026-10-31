<?php

use App\Jobs\RemoveBrokerUser;
use App\Models\Gateway;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['services.emqx.topic_prefix' => 'agronos']);
});

/**
 * The password the last request to create or update the EMQX user carried.
 */
function emqxPasswordSent(): ?string
{
    [$request] = Http::recorded(fn (Request $request) => str_contains($request->url(), '/authentication/'))->last();

    return $request['password'];
}

test('guest is redirected to login from the gateway index', function () {
    $this->get(route('gateways.index'))->assertRedirect(route('login'));
});

test('gateway index lists only the user\'s own gateways and never their passwords', function () {
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

test('creating a gateway writes its EMQX user and rules and then stores it', function () {
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

test('creating a gateway requires a name', function () {
    Http::preventStrayRequests();

    $this->actingAs(User::factory()->create())
        ->post(route('gateways.store'), ['name' => ''])
        ->assertSessionHasErrors(['name' => 'The name field is required.']);

    expect(Gateway::allTenants()->count())->toBe(0);
});

test('no gateway is stored when EMQX rejects its user', function () {
    $uuid = Str::freezeUuids();
    fakeEmqxProvisioning("gw-{$uuid}", userStatus: 503);

    $this->actingAs(User::factory()->create())
        ->from(route('gateways.create'))
        ->post(route('gateways.store'), ['name' => 'Barn gateway'])
        ->assertRedirect(route('gateways.create'))
        ->assertSessionHas('error');

    expect(Gateway::allTenants()->count())->toBe(0);
});

test('the owner sees the gateway\'s username, password and topic', function () {
    $user = User::factory()->create();
    $gateway = Gateway::factory()->for($user)->create(['mqtt_username' => 'gw-barn', 'mqtt_password' => 'barn-pass']);

    $this->actingAs($user)
        ->get(route('gateways.show', $gateway))
        ->assertInertia(fn ($page) => $page->component('Gateways/Show')
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

test('rotating the password updates the EMQX user and then the stored password', function () {
    fakeEmqxProvisioning('gw-barn');
    $user = User::factory()->create();
    $gateway = Gateway::factory()->for($user)->create(['mqtt_username' => 'gw-barn', 'mqtt_password' => 'old-pass']);

    $this->actingAs($user)
        ->post(route('gateways.rotate-password', $gateway))
        ->assertRedirect(route('gateways.show', $gateway));

    expect($gateway->fresh()->mqtt_password)->not->toBe('old-pass')
        ->toBe(emqxPasswordSent());
});

test('the stored password is kept when EMQX rejects the rotation', function () {
    fakeEmqxProvisioning('gw-barn', userStatus: 503);
    $user = User::factory()->create();
    $gateway = Gateway::factory()->for($user)->create(['mqtt_username' => 'gw-barn', 'mqtt_password' => 'old-pass']);

    $this->actingAs($user)
        ->post(route('gateways.rotate-password', $gateway))
        ->assertSessionHas('error');

    expect($gateway->fresh()->mqtt_password)->toBe('old-pass');
});

test('another user cannot rotate a gateway\'s password', function () {
    Http::preventStrayRequests();
    $gateway = Gateway::factory()->create(['mqtt_password' => 'old-pass']);

    $this->actingAs(User::factory()->create())
        ->post(route('gateways.rotate-password', $gateway))
        ->assertNotFound();

    expect($gateway->fresh()->mqtt_password)->toBe('old-pass');
});

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

test('deleting a user account queues broker cleanup for each of their gateways', function () {
    Bus::fake([RemoveBrokerUser::class]);
    $user = User::factory()->create();
    Gateway::factory()->for($user)->create(['mqtt_username' => 'gw-first']);
    Gateway::factory()->for($user)->create(['mqtt_username' => 'gw-second']);
    Gateway::factory()->create(['mqtt_username' => 'gw-other']);

    $user->delete();

    Bus::assertDispatchedTimes(RemoveBrokerUser::class, 2);
    Bus::assertNotDispatched(RemoveBrokerUser::class, fn (RemoveBrokerUser $job) => $job->username === 'gw-other');
});

test('a gateway password is encrypted at rest', function () {
    $gateway = Gateway::factory()->create(['mqtt_password' => 'barn-pass']);

    expect(DB::table('gateways')->where('id', $gateway->id)->value('mqtt_password'))->not->toBe('barn-pass')
        ->and($gateway->fresh()->mqtt_password)->toBe('barn-pass');
});

test('two tenants cannot share a gateway username', function () {
    Gateway::factory()->create(['mqtt_username' => 'gw-barn']);

    Gateway::factory()->create(['mqtt_username' => 'gw-barn']);
})->throws(UniqueConstraintViolationException::class);
