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
    public function handle(): int
    {
        $threshold = now()->subMinutes((int) config('agronos.offline_after_minutes'));

        $marked = Device::allTenants()
            ->where('status', DeviceStatus::ONLINE)
            ->where(fn ($query) => $query->whereNull('last_seen_at')->orWhere('last_seen_at', '<', $threshold))
            ->update(['status' => DeviceStatus::OFFLINE]);

        $this->info("Marked {$marked} device(s) offline.");

        return self::SUCCESS;
    }
}
