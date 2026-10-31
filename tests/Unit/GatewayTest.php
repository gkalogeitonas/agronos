<?php

use App\Jobs\RemoveBrokerUser;
use App\Models\Gateway;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

describe('Gateway model', function () {
    it('stores its password encrypted', function () {
        $gateway = Gateway::factory()->create(['mqtt_password' => 'barn-pass']);

        expect(DB::table('gateways')->where('id', $gateway->id)->value('mqtt_password'))->not->toBe('barn-pass')
            ->and($gateway->fresh()->mqtt_password)->toBe('barn-pass');
    });

    it('cannot share a username with another tenant\'s gateway', function () {
        Gateway::factory()->create(['mqtt_username' => 'gw-barn']);

        Gateway::factory()->create(['mqtt_username' => 'gw-barn']);
    })->throws(UniqueConstraintViolationException::class);

    it('queues broker cleanup for each gateway when its user account is deleted', function () {
        Bus::fake([RemoveBrokerUser::class]);
        $user = User::factory()->create();
        Gateway::factory()->for($user)->create(['mqtt_username' => 'gw-first']);
        Gateway::factory()->for($user)->create(['mqtt_username' => 'gw-second']);
        Gateway::factory()->create(['mqtt_username' => 'gw-other']);

        $user->delete();

        Bus::assertDispatchedTimes(RemoveBrokerUser::class, 2);
        Bus::assertNotDispatched(RemoveBrokerUser::class, fn (RemoveBrokerUser $job) => $job->username === 'gw-other');
    });
});
