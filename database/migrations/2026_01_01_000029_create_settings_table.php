<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Schema v2.1.0 — §36 settings
 * Migration Phase 11 — Cross-Cutting
 * Hierarchical settings: System -> Tenant -> Store.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->char('tenant_id', 26)->nullable();
            $table->char('store_id', 26)->nullable();
            $table->string('key', 100);
            $table->text('value');
            $table->timestampTz('created_at', 6)->useCurrent();
            $table->timestampTz('updated_at', 6)->useCurrent();

            $table->foreign('tenant_id', 'fk_settings_tenant_id')
                ->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('store_id', 'fk_settings_store_id')
                ->references('id')->on('stores')->cascadeOnDelete();

            $table->index(['tenant_id', 'store_id'], 'idx_settings_tenant_store');
        });

        // Decision #8 — 3 partial unique indexes covering system, tenant, and store scopes
        DB::statement('CREATE UNIQUE INDEX uq_settings_system ON settings (key) WHERE tenant_id IS NULL AND store_id IS NULL');
        DB::statement('CREATE UNIQUE INDEX uq_settings_tenant ON settings (tenant_id, key) WHERE tenant_id IS NOT NULL AND store_id IS NULL');
        DB::statement('CREATE UNIQUE INDEX uq_settings_store ON settings (tenant_id, store_id, key) WHERE store_id IS NOT NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
