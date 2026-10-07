<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Schema v2.1.0 — §27 inventory_movements
 * Migration Phase 7 — Purchasing & Inventory
 *
 * IMMUTABLE ledger: no updated_at, no soft delete, never updated/deleted.
 * quantity is signed: positive = IN, negative = OUT; zero is rejected.
 * reference_type/reference_id is polymorphic — intentionally no FK.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_movements', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->char('store_id', 26);
            $table->char('ingredient_id', 26);
            $table->char('unit_id', 26);
            $table->string('type', 20);
            $table->decimal('quantity', 15, 4);
            $table->string('reference_type', 50)->nullable();
            $table->char('reference_id', 26)->nullable();
            $table->text('notes')->nullable();
            $table->char('created_by', 26)->nullable();
            $table->timestampTz('created_at', 6)->useCurrent();

            $table->foreign('store_id', 'fk_inventory_movements_store_id')
                ->references('id')->on('stores')->restrictOnDelete();
            $table->foreign('ingredient_id', 'fk_inventory_movements_ingredient_id')
                ->references('id')->on('ingredients')->restrictOnDelete();
            $table->foreign('unit_id', 'fk_inventory_movements_unit_id')
                ->references('id')->on('units')->restrictOnDelete();
            $table->foreign('created_by', 'fk_inventory_movements_created_by')
                ->references('id')->on('users')->nullOnDelete();

            $table->index('store_id', 'idx_inventory_movements_store_id');
            $table->index('ingredient_id', 'idx_inventory_movements_ingredient_id');
            $table->index('type', 'idx_inventory_movements_type');
            $table->index(['reference_type', 'reference_id'], 'idx_inventory_movements_reference');
            $table->index('created_at', 'idx_inventory_movements_created_at');
        });

        DB::statement("ALTER TABLE inventory_movements ADD CONSTRAINT chk_inventory_movements_type CHECK (type IN ('PURCHASE', 'SALE', 'WASTAGE', 'ADJUSTMENT'))");
        DB::statement('ALTER TABLE inventory_movements ADD CONSTRAINT chk_inventory_movements_quantity CHECK (quantity != 0)');

        DB::statement('CREATE INDEX idx_inventory_movements_created_by ON inventory_movements (created_by) WHERE created_by IS NOT NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_movements');
    }
};
