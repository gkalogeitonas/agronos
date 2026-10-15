<?php

use App\Models\Device;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('Device model', function () {
    it('can be created with valid attributes', function () {
        $user = User::factory()->create();
        $device = Device::factory()->create(['user_id' => $user->id]);
        expect($device)->toBeInstanceOf(Device::class);
        expect($device->user_id)->toBe($user->id);
    });

    it('belongs to a user', function () {
        $user = User::factory()->create();
        $device = Device::factory()->create(['user_id' => $user->id]);
        expect($device->user)->toBeInstanceOf(User::class);
    });

    it('flags low battery below the threshold only', function (?int $batteryLevel, bool $expected) {
        config(['agronos.low_battery_percent' => 20]);
        $device = Device::factory()->make(['battery_level' => $batteryLevel]);

        expect($device->isLowBattery())->toBe($expected);
    })->with([
        'just below' => [19, true],
        'at threshold' => [20, false],
        'not reported' => [null, false],
    ]);

    it('scopes queries to devices below the configured threshold', function () {
        config(['agronos.low_battery_percent' => 30]);
        $user = User::factory()->create();
        $low = Device::factory()->create(['user_id' => $user->id, 'battery_level' => 29]);
        Device::factory()->create(['user_id' => $user->id, 'battery_level' => 30]);
        Device::factory()->create(['user_id' => $user->id, 'battery_level' => null]);

        expect(Device::withoutGlobalScopes()->lowBattery()->pluck('id')->all())->toBe([$low->id]);
    });
});
