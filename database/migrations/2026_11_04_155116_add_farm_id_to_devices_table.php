<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Places each device on one farm (ADR-0008). Existing devices take the one farm their
 * sensors are on, or stay unassigned when their sensors have none.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $farmsByDevice = DB::table('sensors')
            ->whereNotNull('farm_id')
            ->select('device_id', 'farm_id')
            ->distinct()
            ->get()
            ->groupBy('device_id');

        $devicesOnSeveralFarms = $farmsByDevice->filter(fn ($rows) => $rows->count() > 1)->keys();

        if ($devicesOnSeveralFarms->isNotEmpty()) {
            throw new RuntimeException(
                'These devices have sensors on more than one farm, so they cannot be placed on one farm: '
                .$devicesOnSeveralFarms->sort()->implode(', ')
                .'. Move their sensors onto one farm and run the migration again.'
            );
        }

        Schema::table('devices', function (Blueprint $table) {
            $table->foreignId('farm_id')->nullable()->after('user_id')->constrained()->nullOnDelete();
        });

        foreach ($farmsByDevice as $deviceId => $rows) {
            DB::table('devices')->where('id', $deviceId)->update(['farm_id' => $rows->first()->farm_id]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            $table->dropConstrainedForeignId('farm_id');
        });
    }
};
