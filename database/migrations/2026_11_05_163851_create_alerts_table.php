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
        Schema::create('alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('rule');
            $table->string('severity');
            $table->morphs('subject');
            $table->timestamp('opened_at')->useCurrent();
            $table->timestamp('resolved_at')->nullable();
            // True while open and null once resolved: unique indexes treat nulls as distinct, so resolved alerts never collide
            $table->boolean('open_marker')->nullable()->default(true);
            $table->timestamps();

            $table->unique(['rule', 'subject_type', 'subject_id', 'open_marker'], 'alerts_one_open_per_rule_and_subject');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('alerts');
    }
};
