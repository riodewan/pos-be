<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reservations', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->char('tenant_id', 26);
            $table->char('store_id', 26);
            $table->char('table_id', 26)->nullable();
            $table->string('reservation_number', 50)->unique();
            $table->string('customer_name', 100);
            $table->string('customer_phone', 20)->nullable();
            $table->string('customer_email', 100)->nullable();
            $table->integer('guest_count')->default(2);
            $table->date('reservation_date');
            $table->string('reservation_time', 20); // e.g. '15:00'
            $table->string('status', 30)->default('Menunggu'); // 'Menunggu', 'Dikonfirmasi', 'Selesai', 'Dibatalkan'
            $table->text('notes')->nullable();
            $table->timestampTz('created_at', 6)->useCurrent();
            $table->timestampTz('updated_at', 6)->useCurrent();
            $table->softDeletesTz('deleted_at', 6);

            $table->foreign('tenant_id', 'fk_reservations_tenant_id')
                ->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('store_id', 'fk_reservations_store_id')
                ->references('id')->on('stores')->cascadeOnDelete();
            $table->foreign('table_id', 'fk_reservations_table_id')
                ->references('id')->on('tables')->nullOnDelete();

            $table->index(['store_id', 'reservation_date'], 'idx_reservations_store_date');
            $table->index('status', 'idx_reservations_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reservations');
    }
};
