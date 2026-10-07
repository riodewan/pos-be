<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Schema v2.1.0 — §35 audit_logs
 * Migration Phase 11 — Cross-Cutting
 * Append-only immutable log — no updated_at.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->char('tenant_id', 26)->nullable();
            $table->char('store_id', 26)->nullable();
            $table->char('user_id', 26)->nullable();
            $table->string('action', 50);
            $table->string('module', 50)->nullable();
            $table->string('entity_type', 50)->nullable();
            $table->char('entity_id', 26)->nullable();
            $table->jsonb('old_values')->nullable();
            $table->jsonb('new_values')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestampTz('created_at', 6)->useCurrent();

            $table->foreign('tenant_id', 'fk_audit_logs_tenant_id')
                ->references('id')->on('tenants')->nullOnDelete();
            $table->foreign('store_id', 'fk_audit_logs_store_id')
                ->references('id')->on('stores')->nullOnDelete();
            $table->foreign('user_id', 'fk_audit_logs_user_id')
                ->references('id')->on('users')->nullOnDelete();

            $table->index('user_id', 'idx_audit_logs_user_id');
            $table->index('action', 'idx_audit_logs_action');
            $table->index('created_at', 'idx_audit_logs_created_at');
            $table->index(['entity_type', 'entity_id'], 'idx_audit_logs_entity');
        });

        // Phase 1 scope: LOGIN, LOGOUT
        DB::statement("ALTER TABLE audit_logs ADD CONSTRAINT chk_audit_logs_action CHECK (action IN ('LOGIN', 'LOGOUT'))");

        DB::statement('CREATE INDEX idx_audit_logs_tenant_id ON audit_logs (tenant_id) WHERE tenant_id IS NOT NULL');
        DB::statement('CREATE INDEX idx_audit_logs_store_id ON audit_logs (store_id) WHERE store_id IS NOT NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
