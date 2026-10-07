<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Schema v2.1.0 — §25 purchase_items
 * Migration Phase 7 — Purchasing & Inventory
 * Transactional — no soft delete; cascades with parent purchase.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_items', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->char('purchase_id', 26);
            $table->char('ingredient_id', 26);
            $table->decimal('quantity', 15, 4);
            $table->char('unit_id', 26);
            $table->decimal('unit_cost', 15, 2);
            $table->decimal('total_cost', 15, 2);
            $table->timestampTz('created_at', 6)->useCurrent();
            $table->timestampTz('updated_at', 6)->useCurrent();

            $table->foreign('purchase_id', 'fk_purchase_items_purchase_id')
                ->references('id')->on('purchases')->cascadeOnDelete();
            $table->foreign('ingredient_id', 'fk_purchase_items_ingredient_id')
                ->references('id')->on('ingredients')->restrictOnDelete();
            $table->foreign('unit_id', 'fk_purchase_items_unit_id')
                ->references('id')->on('units')->restrictOnDelete();

            $table->index('purchase_id', 'idx_purchase_items_purchase_id');
            $table->index('ingredient_id', 'idx_purchase_items_ingredient_id');
        });

        DB::statement('ALTER TABLE purchase_items ADD CONSTRAINT chk_purchase_items_quantity CHECK (quantity > 0)');
        DB::statement('ALTER TABLE purchase_items ADD CONSTRAINT chk_purchase_items_unit_cost CHECK (unit_cost >= 0)');
        DB::statement('ALTER TABLE purchase_items ADD CONSTRAINT chk_purchase_items_total_cost CHECK (total_cost >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_items');
    }
};
