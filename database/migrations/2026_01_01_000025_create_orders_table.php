<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Schema v2.1.0 — §32 orders
 * Migration Phase 10 — Orders & Payments
 * Transactional — no soft delete (status = 'CANCELLED').
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->char('store_id', 26);
            $table->char('table_id', 26)->nullable();
            $table->string('order_number', 50);
            $table->string('source', 20)->default('POS');
            $table->string('customer_name', 100)->nullable();
            $table->string('customer_phone', 20)->nullable();
            $table->string('status', 20)->default('PENDING');
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('discount_amount', 15, 2)->default(0);
            $table->decimal('tax_amount', 15, 2)->default(0);
            $table->decimal('service_charge', 15, 2)->default(0);
            $table->decimal('total_amount', 15, 2)->default(0);
            $table->text('notes')->nullable();
            $table->char('created_by', 26)->nullable();
            $table->timestampTz('created_at', 6)->useCurrent();
            $table->timestampTz('updated_at', 6)->useCurrent();

            $table->foreign('store_id', 'fk_orders_store_id')
                ->references('id')->on('stores')->restrictOnDelete();
            $table->foreign('table_id', 'fk_orders_table_id')
                ->references('id')->on('tables')->restrictOnDelete();
            $table->foreign('created_by', 'fk_orders_created_by')
                ->references('id')->on('users')->nullOnDelete();

            $table->unique(['store_id', 'order_number'], 'uq_orders_store_number');
            $table->index('store_id', 'idx_orders_store_id');
            $table->index('status', 'idx_orders_status');
            $table->index('source', 'idx_orders_source');
            $table->index('created_at', 'idx_orders_created_at');
        });

        DB::statement("ALTER TABLE orders ADD CONSTRAINT chk_orders_source CHECK (source IN ('POS', 'QR'))");
        DB::statement("ALTER TABLE orders ADD CONSTRAINT chk_orders_status CHECK (status IN ('PENDING', 'CONFIRMED', 'PREPARING', 'READY', 'COMPLETED', 'CANCELLED'))");
        DB::statement('ALTER TABLE orders ADD CONSTRAINT chk_orders_subtotal CHECK (subtotal >= 0)');
        DB::statement('ALTER TABLE orders ADD CONSTRAINT chk_orders_discount_amount CHECK (discount_amount >= 0)');
        DB::statement('ALTER TABLE orders ADD CONSTRAINT chk_orders_tax_amount CHECK (tax_amount >= 0)');
        DB::statement('ALTER TABLE orders ADD CONSTRAINT chk_orders_service_charge CHECK (service_charge >= 0)');
        DB::statement('ALTER TABLE orders ADD CONSTRAINT chk_orders_total_amount CHECK (total_amount >= 0)');

        DB::statement('CREATE INDEX idx_orders_table_id ON orders (table_id) WHERE table_id IS NOT NULL');
        DB::statement('CREATE INDEX idx_orders_created_by ON orders (created_by) WHERE created_by IS NOT NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
