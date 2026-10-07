<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Schema v2.1.0 — §31 qr_codes
 * Migration Phase 9 — Tables & QR Codes
 * Soft delete supported.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('qr_codes', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->char('store_id', 26);
            $table->char('table_id', 26);
            $table->string('token', 64)->unique('uq_qr_codes_token');
            $table->string('status', 20)->default('ACTIVE');
            $table->timestampTz('created_at', 6)->useCurrent();
            $table->timestampTz('updated_at', 6)->useCurrent();
            $table->softDeletesTz('deleted_at', 6);

            $table->foreign('store_id', 'fk_qr_codes_store_id')
                ->references('id')->on('stores')->restrictOnDelete();
            $table->foreign('table_id', 'fk_qr_codes_table_id')
                ->references('id')->on('tables')->restrictOnDelete();

            $table->index('store_id', 'idx_qr_codes_store_id');
            $table->index('table_id', 'idx_qr_codes_table_id');
        });

        DB::statement("ALTER TABLE qr_codes ADD CONSTRAINT chk_qr_codes_status CHECK (status IN ('ACTIVE', 'INACTIVE', 'EXPIRED'))");

        // Partial unique index: Only one ACTIVE QR per table at a time
        DB::statement("CREATE UNIQUE INDEX uq_qr_codes_table_active ON qr_codes (table_id) WHERE status = 'ACTIVE' AND deleted_at IS NULL");
    }

    public function down(): void
    {
        Schema::dropIfExists('qr_codes');
    }
};
