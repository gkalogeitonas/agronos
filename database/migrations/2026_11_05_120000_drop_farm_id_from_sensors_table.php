<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Sensors take their farm from their device (ADR-0008), so they no longer carry one.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('sensors', function (Blueprint $table) {
            $table->dropConstrainedForeignId('farm_id');
        });
    }

    /**
     * Reverse the migrations. Each sensor takes its device's farm back, because the
     * migration that added `devices.farm_id` rebuilds it from the sensors.
     */
    public function down(): void
    {
        Schema::table('sensors', function (Blueprint $table) {
            $table->foreignId('farm_id')->nullable()->after('device_id')->constrained()->onDelete('cascade');
        });

        DB::table('sensors')->update([
            'farm_id' => DB::table('devices')->whereColumn('devices.id', 'sensors.device_id')->select('farm_id'),
        ]);
    }
};
