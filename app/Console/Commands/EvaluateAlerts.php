<?php

namespace App\Console\Commands;

use App\Enums\AlertRule;
use App\Enums\DeviceStatus;
use App\Models\Alert;
use App\Models\Device;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

#[Signature('alerts:evaluate')]
#[Description('Open alerts for conditions that started and resolve alerts whose condition cleared')]
class EvaluateAlerts extends Command
{
    public function handle(): int
    {
        $this->clearAlertsWithoutSubject();

        $this->evaluate(AlertRule::DEVICE_OFFLINE, Device::allTenants()->where('status', DeviceStatus::OFFLINE));

        return self::SUCCESS;
    }

    /**
     * Bring the open alerts of a rule in line with the devices that currently meet its condition.
     *
     * @param  Builder<Device>  $affectedDevices
     */
    private function evaluate(AlertRule $rule, Builder $affectedDevices): void
    {
        $devices = $affectedDevices->get(['id', 'user_id']);
        $openAlerts = Alert::allTenants()->open()->where('rule', $rule)->get(['id', 'subject_id']);

        $devices->whereNotIn('id', $openAlerts->pluck('subject_id'))->each(fn (Device $device) => $this->open($rule, $device));

        Alert::allTenants()
            ->whereKey($openAlerts->whereNotIn('subject_id', $devices->modelKeys())->modelKeys())
            ->update(['resolved_at' => now(), 'open_marker' => null]);
    }

    /**
     * Device deletion removes its alerts, but a run can still open one for a device deleted just before.
     */
    private function clearAlertsWithoutSubject(): void
    {
        Alert::allTenants()
            ->whereDoesntHaveMorph('subject', '*', fn (Builder $subject) => $subject->withoutGlobalScopes())
            ->delete();
    }

    /**
     * Tolerates losing the race against an overlapping run: the unique index rejects the second alert.
     */
    private function open(AlertRule $rule, Device $device): void
    {
        Alert::allTenants()->createOrFirst([
            'rule' => $rule,
            'subject_type' => $device->getMorphClass(),
            'subject_id' => $device->id,
            'open_marker' => true,
        ], [
            'user_id' => $device->user_id,
            'severity' => $rule->severity(),
            'opened_at' => now(),
        ]);
    }
}
