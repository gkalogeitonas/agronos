<?php

use App\Jobs\RemoveDeviceFromBroker;
use App\Models\Device;
use App\Models\User;
use App\Services\EmqxService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

function fakeEmqxRemoval(string $username, int $userStatus = 204, int $rulesStatus = 204): void
{
    config([
        'services.emqx.url' => 'http://emqx.test',
        'services.emqx.base_path' => '/api/v5',
        'services.emqx.authenticator_id' => 'password_based:built_in_database',
    ]);

    Http::preventStrayRequests();
    Http::fake([
        "http://emqx.test/api/v5/authentication/password_based:built_in_database/users/{$username}" => Http::response(null, $userStatus),
        "http://emqx.test/api/v5/authorization/sources/built_in_database/rules/users/{$username}" => Http::response(null, $rulesStatus),
    ]);
}

test('deleting a device with mqtt credentials queues its broker cleanup', function () {
    Bus::fake();
    $device = Device::factory()->create(['mqtt_username' => 'device-user', 'mqtt_password' => 'secret']);

    $device->delete();

    Bus::assertDispatched(RemoveDeviceFromBroker::class, fn (RemoveDeviceFromBroker $job) => $job->username === 'device-user');
});

test('deleting a device without mqtt credentials queues nothing', function () {
    Bus::fake();
    $device = Device::factory()->create(['mqtt_username' => null, 'mqtt_password' => null]);

    $device->delete();

    Bus::assertNotDispatched(RemoveDeviceFromBroker::class);
});

test('the owner deleting a device removes its EMQX user and rules', function () {
    fakeEmqxRemoval('device-user');
    $user = User::factory()->create();
    $device = Device::factory()->create(['user_id' => $user->id, 'mqtt_username' => 'device-user', 'mqtt_password' => 'secret']);

    $this->actingAs($user)->delete(route('devices.destroy', $device))->assertRedirect(route('devices.index'));

    Http::assertSent(fn (Request $request) => $request->method() === 'DELETE'
        && str_ends_with($request->url(), '/authentication/password_based:built_in_database/users/device-user'));
    Http::assertSent(fn (Request $request) => $request->method() === 'DELETE'
        && str_ends_with($request->url(), '/authorization/sources/built_in_database/rules/users/device-user'));
});

test('another user cannot see the device to delete it so the broker is left alone', function () {
    Bus::fake();
    $device = Device::factory()->create(['mqtt_username' => 'device-user', 'mqtt_password' => 'secret']);

    $this->actingAs(User::factory()->create())->delete(route('devices.destroy', $device))->assertNotFound();

    Bus::assertNotDispatched(RemoveDeviceFromBroker::class);
    $this->assertDatabaseHas('devices', ['id' => $device->id]);
});

test('cleanup treats a user or rules already gone from the broker as done', function () {
    fakeEmqxRemoval('device-user', 404, 404);

    (new RemoveDeviceFromBroker('device-user'))->handle(app(EmqxService::class));

    Http::assertSentCount(2);
});

test('cleanup fails so the queue retries when EMQX errors', function () {
    fakeEmqxRemoval('device-user', 503, 204);

    (new RemoveDeviceFromBroker('device-user'))->handle(app(EmqxService::class));
})->throws(RuntimeException::class);

test('deleting a user account queues broker cleanup for each of their devices', function () {
    Bus::fake();
    $user = User::factory()->create();
    Device::factory()->create(['user_id' => $user->id, 'mqtt_username' => 'first-device', 'mqtt_password' => 'secret']);
    Device::factory()->create(['user_id' => $user->id, 'mqtt_username' => 'second-device', 'mqtt_password' => 'secret']);
    $other = Device::factory()->create(['mqtt_username' => 'other-device', 'mqtt_password' => 'secret']);

    $user->delete();

    Bus::assertDispatchedTimes(RemoveDeviceFromBroker::class, 2);
    Bus::assertNotDispatched(RemoveDeviceFromBroker::class, fn (RemoveDeviceFromBroker $job) => $job->username === 'other-device');
    $this->assertDatabaseHas('devices', ['id' => $other->id]);
});
