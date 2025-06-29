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
        Schema::create('devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('name')->nullable();
            $table->string('uuid')->unique();
            $table->string('secret');
            $table->enum('type', ['wifi', 'lora', 'other'])->default('wifi');
            $table->enum('status', ['registered', 'online', 'offline', 'error'])->default('pending');
            $table->timestamp('last_seen_at')->nullable();
            $table->integer('battery_level')->nullable();
            $table->integer('signal_strength')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('devices');
    }
};
