<?php

namespace App\Console\Commands;

use App\Enums\DeviceStatus;
use App\Models\Device;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('devices:mark-offline')]
#[Description('Mark online devices offline when they have not reported within the offline threshold')]
class MarkDevicesOffline extends Command
{
    /**
     * Lowest accepted threshold. It must exceed the five-minute schedule, or a misconfigured value would sweep every online device on each run.
     */
    public const MINIMUM_OFFLINE_AFTER_MINUTES = 6;

    public function handle(): int
    {
        $minutes = max(self::MINIMUM_OFFLINE_AFTER_MINUTES, (int) config('agronos.offline_after_minutes'));
        $threshold = now()->subMinutes($minutes);

        $marked = Device::allTenants()
            ->where('status', DeviceStatus::ONLINE)
            ->where(fn ($query) => $query->whereNull('last_seen_at')->orWhere('last_seen_at', '<', $threshold))
            ->update(['status' => DeviceStatus::OFFLINE]);

        $this->info("Marked {$marked} device(s) offline.");

        return self::SUCCESS;
    }
}
