<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Schema v2.1.0 — §33 order_items
 * Migration Phase 10 — Orders & Payments
 * Transactional — no soft delete.
 *
 * NOTE (Correction #3): product_id is NOT NULL with ON DELETE RESTRICT.
 * Products use soft delete, so historical orders maintain a valid FK reference.
 * product_name and unit_price remain immutable transaction snapshots.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_items', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->char('order_id', 26);
            $table->char('product_id', 26);
            $table->string('product_name', 100);
            $table->decimal('quantity', 15, 4);
            $table->decimal('unit_price', 15, 2);
            $table->decimal('discount_amount', 15, 2)->default(0);
            $table->decimal('subtotal', 15, 2);
            $table->text('notes')->nullable();
            $table->timestampTz('created_at', 6)->useCurrent();
            $table->timestampTz('updated_at', 6)->useCurrent();

            $table->foreign('order_id', 'fk_order_items_order_id')
                ->references('id')->on('orders')->cascadeOnDelete();
            $table->foreign('product_id', 'fk_order_items_product_id')
                ->references('id')->on('products')->restrictOnDelete();

            $table->index('order_id', 'idx_order_items_order_id');
            $table->index('product_id', 'idx_order_items_product_id');
        });

        DB::statement('ALTER TABLE order_items ADD CONSTRAINT chk_order_items_quantity CHECK (quantity > 0)');
        DB::statement('ALTER TABLE order_items ADD CONSTRAINT chk_order_items_unit_price CHECK (unit_price >= 0)');
        DB::statement('ALTER TABLE order_items ADD CONSTRAINT chk_order_items_discount_amount CHECK (discount_amount >= 0)');
        DB::statement('ALTER TABLE order_items ADD CONSTRAINT chk_order_items_subtotal CHECK (subtotal >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};
