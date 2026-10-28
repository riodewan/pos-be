<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('disbursements', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->char('store_id', 26);
            $table->string('batch_id', 40)->unique();
            $table->string('bank_name', 100);
            $table->string('account_number', 50);
            $table->decimal('gross_amount', 15, 2);
            $table->decimal('fee', 15, 2)->default(0);
            $table->decimal('net_received', 15, 2);
            $table->string('status', 20)->default('Diproses'); // Berhasil, Diproses, Gagal
            $table->char('requested_by', 26)->nullable();
            $table->timestampTz('requested_at', 6)->useCurrent();
            $table->timestampTz('created_at', 6)->useCurrent();
            $table->timestampTz('updated_at', 6)->useCurrent();

            $table->foreign('store_id', 'fk_disbursements_store_id')
                ->references('id')->on('stores')->cascadeOnDelete();
            $table->index('store_id', 'idx_disbursements_store_id');
        });

        Schema::create('cash_shift_audits', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->char('store_id', 26);
            $table->char('cashier_id', 26)->nullable();
            $table->string('shift_name', 100);
            $table->date('shift_date');
            $table->decimal('opening_cash', 15, 2)->default(0);
            $table->decimal('actual_cash', 15, 2)->nullable();
            $table->text('notes')->nullable();
            $table->timestampTz('created_at', 6)->useCurrent();
            $table->timestampTz('updated_at', 6)->useCurrent();

            $table->foreign('store_id', 'fk_cash_audits_store_id')
                ->references('id')->on('stores')->cascadeOnDelete();
            $table->index(['store_id', 'shift_date'], 'idx_cash_audits_store_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_shift_audits');
        Schema::dropIfExists('disbursements');
    }
};
