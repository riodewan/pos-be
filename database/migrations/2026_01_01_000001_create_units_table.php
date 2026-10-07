<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Schema v2.1.0 — §16 units
 * Migration Phase 1 — No Dependencies
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('units', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('name', 50);
            $table->string('code', 20);
            $table->string('type', 20);
            $table->timestampTz('created_at', 6)->useCurrent();
            $table->timestampTz('updated_at', 6)->useCurrent();

            $table->unique('code', 'uq_units_code');
            $table->unique('name', 'uq_units_name');
        });

        DB::statement("ALTER TABLE units ADD CONSTRAINT chk_units_type CHECK (type IN ('WEIGHT', 'VOLUME', 'COUNT'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('units');
    }
};
