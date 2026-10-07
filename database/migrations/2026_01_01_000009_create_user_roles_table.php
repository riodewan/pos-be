<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Schema v2.1.0 — §15 user_roles
 * Migration Phase 4 — RBAC
 *
 * store_id NULL     = tenant-wide role
 * store_id NOT NULL = store-specific role
 *
 * Correction #5 — application/service-layer validation (not enforceable by simple FK):
 *   Rule A: TENANT role  -> user_roles.tenant_id = roles.tenant_id
 *           INTERNAL role -> must not have a tenant assignment
 *   Rule B: store_id (if NOT NULL) must belong to a store whose tenant_id = user_roles.tenant_id
 *   Rule C: INTERNAL role -> store_id must be NULL
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_roles', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->char('user_id', 26);
            $table->char('role_id', 26);
            $table->char('tenant_id', 26);
            $table->char('store_id', 26)->nullable();
            $table->timestampTz('created_at', 6)->useCurrent();
            $table->timestampTz('updated_at', 6)->useCurrent();

            $table->foreign('user_id', 'fk_user_roles_user_id')
                ->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('role_id', 'fk_user_roles_role_id')
                ->references('id')->on('roles')->cascadeOnDelete();
            $table->foreign('tenant_id', 'fk_user_roles_tenant_id')
                ->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('store_id', 'fk_user_roles_store_id')
                ->references('id')->on('stores')->cascadeOnDelete();

            $table->index('user_id', 'idx_user_roles_user_id');
            $table->index('tenant_id', 'idx_user_roles_tenant_id');
        });

        // Decision #5 — 2 partial unique indexes based on nullable store_id
        DB::statement('CREATE UNIQUE INDEX uq_user_roles_tenant_wide ON user_roles (user_id, role_id, tenant_id) WHERE store_id IS NULL');
        DB::statement('CREATE UNIQUE INDEX uq_user_roles_store ON user_roles (user_id, role_id, tenant_id, store_id) WHERE store_id IS NOT NULL');
        DB::statement('CREATE INDEX idx_user_roles_store_id ON user_roles (store_id) WHERE store_id IS NOT NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('user_roles');
    }
};
