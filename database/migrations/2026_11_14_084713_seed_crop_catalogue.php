<?php

use Database\Seeders\CropCatalogueSeeder;
use Illuminate\Database\Migrations\Migration;

/**
 * Loads the shared crop catalogue on every environment on deploy (ADR-0010). The seeder is idempotent.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        (new CropCatalogueSeeder)->run();
    }

    /**
     * Reverse the migrations. The catalogue goes with the crops table.
     */
    public function down(): void {}
};
