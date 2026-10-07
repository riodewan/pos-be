<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Schema v2.1.0 — §24 purchases
 * Migration Phase 7 — Purchasing & Inventory
 *
 * Transactional — no soft delete (status = 'CANCELLED').
 * supplier_id NULLABLE + ON DELETE RESTRICT (Correction #2): NULL = walk-in purchase.
 * Cross-store (suppliers.store_id = purchases.store_id) is service-layer validation.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchases', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->char('store_id', 26);
            $table->char('supplier_id', 26)->nullable();
            $table->string('purchase_number', 50);
            $table->date('purchase_date');
            $table->string('status', 20)->default('DRAFT');
            $table->decimal('total_amount', 15, 2)->default(0);
            $table->text('notes')->nullable();
            $table->char('created_by', 26)->nullable();
            $table->timestampTz('created_at', 6)->useCurrent();
            $table->timestampTz('updated_at', 6)->useCurrent();

            $table->foreign('store_id', 'fk_purchases_store_id')
                ->references('id')->on('stores')->restrictOnDelete();
            $table->foreign('supplier_id', 'fk_purchases_supplier_id')
                ->references('id')->on('suppliers')->restrictOnDelete();
            $table->foreign('created_by', 'fk_purchases_created_by')
                ->references('id')->on('users')->nullOnDelete();

            $table->unique(['store_id', 'purchase_number'], 'uq_purchases_store_number');
            $table->index('store_id', 'idx_purchases_store_id');
            $table->index('status', 'idx_purchases_status');
            $table->index('purchase_date', 'idx_purchases_purchase_date');
        });

        DB::statement("ALTER TABLE purchases ADD CONSTRAINT chk_purchases_status CHECK (status IN ('DRAFT', 'RECEIVED', 'CANCELLED'))");
        DB::statement('ALTER TABLE purchases ADD CONSTRAINT chk_purchases_total_amount CHECK (total_amount >= 0)');

        DB::statement('CREATE INDEX idx_purchases_supplier_id ON purchases (supplier_id) WHERE supplier_id IS NOT NULL');
        DB::statement('CREATE INDEX idx_purchases_created_by ON purchases (created_by) WHERE created_by IS NOT NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('purchases');
    }
};
