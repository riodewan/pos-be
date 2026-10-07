<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Schema v2.1.0 — §10 stores
 * Migration Phase 3 — Tenant-Store Structure
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stores', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->char('tenant_id', 26);
            $table->string('name', 100);
            $table->string('code', 50);
            $table->text('address')->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('timezone', 50)->default('Asia/Jakarta');
            $table->string('status', 20)->default('ACTIVE');
            $table->timestampTz('created_at', 6)->useCurrent();
            $table->timestampTz('updated_at', 6)->useCurrent();
            $table->softDeletesTz('deleted_at', 6);

            $table->foreign('tenant_id', 'fk_stores_tenant_id')
                ->references('id')->on('tenants')->restrictOnDelete();

            $table->index('tenant_id', 'idx_stores_tenant_id');
            $table->index('status', 'idx_stores_status');
        });

        DB::statement("ALTER TABLE stores ADD CONSTRAINT chk_stores_status CHECK (status IN ('ACTIVE', 'INACTIVE'))");

        DB::statement('CREATE UNIQUE INDEX uq_stores_tenant_code ON stores (tenant_id, code) WHERE deleted_at IS NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('stores');
    }
};
