<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Schema v2.1.0 — §26 inventory_stocks
 * Migration Phase 7 — Purchasing & Inventory
 *
 * Decision #6: CHECK quantity >= 0. Application must decrement inside a DB
 * transaction using SELECT ... FOR UPDATE.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_stocks', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->char('store_id', 26);
            $table->char('ingredient_id', 26);
            $table->decimal('quantity', 15, 4)->default(0);
            $table->decimal('average_cost', 15, 2)->default(0);
            $table->timestampTz('created_at', 6)->useCurrent();
            $table->timestampTz('updated_at', 6)->useCurrent();

            $table->foreign('store_id', 'fk_inventory_stocks_store_id')
                ->references('id')->on('stores')->restrictOnDelete();
            $table->foreign('ingredient_id', 'fk_inventory_stocks_ingredient_id')
                ->references('id')->on('ingredients')->restrictOnDelete();

            $table->unique(['store_id', 'ingredient_id'], 'uq_inventory_stocks_store_ingredient');
            $table->index('store_id', 'idx_inventory_stocks_store_id');
        });

        DB::statement('ALTER TABLE inventory_stocks ADD CONSTRAINT chk_inventory_stocks_quantity CHECK (quantity >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_stocks');
    }
};
