<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            // store the mqtt username/password for devices that will authenticate with the broker
            $table->string('mqtt_username')->nullable()->unique()->after('uuid');
            $table->string('mqtt_password')->nullable()->after('mqtt_username');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            if (Schema::hasColumn('devices', 'mqtt_username')) {
                // drop unique index first then drop the column
                $table->dropUnique(['mqtt_username']);
                $table->dropColumn('mqtt_username');
            }

            if (Schema::hasColumn('devices', 'mqtt_password')) {
                $table->dropColumn('mqtt_password');
            }
        });
    }
};
