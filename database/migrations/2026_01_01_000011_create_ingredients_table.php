<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Schema v2.1.0 — §18 ingredients
 * Migration Phase 5 — Store Catalog
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ingredients', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->char('store_id', 26);
            $table->char('unit_id', 26);
            $table->string('name', 100);
            $table->string('sku', 50);
            $table->string('status', 20)->default('ACTIVE');
            $table->timestampTz('created_at', 6)->useCurrent();
            $table->timestampTz('updated_at', 6)->useCurrent();
            $table->softDeletesTz('deleted_at', 6);

            $table->foreign('store_id', 'fk_ingredients_store_id')
                ->references('id')->on('stores')->restrictOnDelete();
            $table->foreign('unit_id', 'fk_ingredients_unit_id')
                ->references('id')->on('units')->restrictOnDelete();

            $table->index('store_id', 'idx_ingredients_store_id');
            $table->index('unit_id', 'idx_ingredients_unit_id');
        });

        DB::statement("ALTER TABLE ingredients ADD CONSTRAINT chk_ingredients_status CHECK (status IN ('ACTIVE', 'INACTIVE'))");

        DB::statement('CREATE UNIQUE INDEX uq_ingredients_store_sku ON ingredients (store_id, sku) WHERE deleted_at IS NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('ingredients');
    }
};
