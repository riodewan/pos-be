<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Schema v2.1.0 — §29 wastage_items
 * Migration Phase 8 — Wastage
 * Transactional — no soft delete; cascades with parent wastage.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wastage_items', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->char('wastage_id', 26);
            $table->char('ingredient_id', 26);
            $table->decimal('quantity', 15, 4);
            $table->char('unit_id', 26);
            $table->decimal('unit_cost', 15, 2)->default(0);
            $table->decimal('total_cost', 15, 2)->default(0);
            $table->timestampTz('created_at', 6)->useCurrent();
            $table->timestampTz('updated_at', 6)->useCurrent();

            $table->foreign('wastage_id', 'fk_wastage_items_wastage_id')
                ->references('id')->on('wastages')->cascadeOnDelete();
            $table->foreign('ingredient_id', 'fk_wastage_items_ingredient_id')
                ->references('id')->on('ingredients')->restrictOnDelete();
            $table->foreign('unit_id', 'fk_wastage_items_unit_id')
                ->references('id')->on('units')->restrictOnDelete();

            $table->index('wastage_id', 'idx_wastage_items_wastage_id');
            $table->index('ingredient_id', 'idx_wastage_items_ingredient_id');
        });

        DB::statement('ALTER TABLE wastage_items ADD CONSTRAINT chk_wastage_items_quantity CHECK (quantity > 0)');
        DB::statement('ALTER TABLE wastage_items ADD CONSTRAINT chk_wastage_items_unit_cost CHECK (unit_cost >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('wastage_items');
    }
};
