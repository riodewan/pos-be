<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Schema v2.1.0 — §14 role_permissions
 * Migration Phase 4 — RBAC
 * No updated_at per schema (created_at only).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('role_permissions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->char('role_id', 26);
            $table->char('permission_id', 26);
            $table->timestampTz('created_at', 6)->useCurrent();

            $table->foreign('role_id', 'fk_role_permissions_role_id')
                ->references('id')->on('roles')->cascadeOnDelete();
            $table->foreign('permission_id', 'fk_role_permissions_permission_id')
                ->references('id')->on('permissions')->restrictOnDelete();

            $table->unique(['role_id', 'permission_id'], 'uq_role_permissions');
            $table->index('role_id', 'idx_role_permissions_role_id');
            $table->index('permission_id', 'idx_role_permissions_permission_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('role_permissions');
    }
};
