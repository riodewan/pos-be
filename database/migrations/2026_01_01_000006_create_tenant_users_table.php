<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Schema v2.1.0 — §11 tenant_users
 * Migration Phase 3 — Tenant-Store Structure
 * No soft delete: hard delete when membership is revoked.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_users', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->char('tenant_id', 26);
            $table->char('user_id', 26);
            $table->timestampTz('joined_at', 6)->useCurrent();
            $table->timestampTz('created_at', 6)->useCurrent();
            $table->timestampTz('updated_at', 6)->useCurrent();

            $table->foreign('tenant_id', 'fk_tenant_users_tenant_id')
                ->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('user_id', 'fk_tenant_users_user_id')
                ->references('id')->on('users')->cascadeOnDelete();

            $table->unique(['tenant_id', 'user_id'], 'uq_tenant_users');
            $table->index('user_id', 'idx_tenant_users_user_id');
            $table->index('tenant_id', 'idx_tenant_users_tenant_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_users');
    }
};
