<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Schema v2.1.0 — §34 payments
 * Migration Phase 10 — Orders & Payments
 * Transactional — no soft delete.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->char('order_id', 26);
            $table->string('payment_number', 50)->unique('uq_payments_number');
            $table->string('method', 30);
            $table->string('provider', 50)->nullable();
            $table->string('provider_reference', 100)->nullable();
            $table->decimal('amount', 15, 2);
            $table->string('status', 20)->default('PENDING');
            $table->timestampTz('paid_at', 6)->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestampTz('created_at', 6)->useCurrent();
            $table->timestampTz('updated_at', 6)->useCurrent();

            $table->foreign('order_id', 'fk_payments_order_id')
                ->references('id')->on('orders')->cascadeOnDelete();

            $table->index('order_id', 'idx_payments_order_id');
            $table->index('status', 'idx_payments_status');
            $table->index('method', 'idx_payments_method');
        });

        DB::statement("ALTER TABLE payments ADD CONSTRAINT chk_payments_method CHECK (method IN ('CASH', 'QRIS', 'TRANSFER', 'EDC', 'ONLINE'))");
        DB::statement("ALTER TABLE payments ADD CONSTRAINT chk_payments_status CHECK (status IN ('PENDING', 'SUCCESS', 'FAILED', 'EXPIRED', 'CANCELLED'))");
        DB::statement('ALTER TABLE payments ADD CONSTRAINT chk_payments_amount CHECK (amount > 0)');

        // Decision #7 — Only one SUCCESS payment per order
        DB::statement("CREATE UNIQUE INDEX uq_payments_order_success ON payments (order_id) WHERE status = 'SUCCESS'");
        DB::statement('CREATE INDEX idx_payments_paid_at ON payments (paid_at) WHERE paid_at IS NOT NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
