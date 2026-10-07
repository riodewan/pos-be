<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Schema v2.1.0 — §30 tables
 * Migration Phase 9 — Tables & QR Codes
 * Soft delete supported.
 *
 * NOTE (Correction #1): status is strictly ACTIVE | INACTIVE.
 * OCCUPIED is an operational state derived at query/service layer from active orders.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tables', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->char('store_id', 26);
            $table->string('name', 50);
            $table->string('code', 20);
            $table->integer('capacity')->nullable();
            $table->string('status', 20)->default('ACTIVE');
            $table->timestampTz('created_at', 6)->useCurrent();
            $table->timestampTz('updated_at', 6)->useCurrent();
            $table->softDeletesTz('deleted_at', 6);

            $table->foreign('store_id', 'fk_tables_store_id')
                ->references('id')->on('stores')->restrictOnDelete();

            $table->index('store_id', 'idx_tables_store_id');
            $table->index('status', 'idx_tables_status');
        });

        DB::statement("ALTER TABLE tables ADD CONSTRAINT chk_tables_status CHECK (status IN ('ACTIVE', 'INACTIVE'))");
        DB::statement('ALTER TABLE tables ADD CONSTRAINT chk_tables_capacity CHECK (capacity IS NULL OR capacity > 0)');

        DB::statement('CREATE UNIQUE INDEX uq_tables_store_code ON tables (store_id, code) WHERE deleted_at IS NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('tables');
    }
};
