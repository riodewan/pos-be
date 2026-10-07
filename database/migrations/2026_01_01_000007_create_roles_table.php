<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Schema v2.1.0 — §12 roles
 * Migration Phase 3 — Tenant-Store Structure
 *
 * INTERNAL role: scope = 'INTERNAL' AND tenant_id IS NULL
 * TENANT role:   scope = 'TENANT'   AND tenant_id IS NOT NULL
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->char('tenant_id', 26)->nullable();
            $table->string('name', 100);
            $table->string('scope', 20);
            $table->boolean('is_default')->default(false);
            $table->text('description')->nullable();
            $table->timestampTz('created_at', 6)->useCurrent();
            $table->timestampTz('updated_at', 6)->useCurrent();
            $table->softDeletesTz('deleted_at', 6);

            $table->foreign('tenant_id', 'fk_roles_tenant_id')
                ->references('id')->on('tenants')->cascadeOnDelete();

            $table->index('tenant_id', 'idx_roles_tenant_id');
            $table->index('scope', 'idx_roles_scope');
        });

        DB::statement("ALTER TABLE roles ADD CONSTRAINT chk_roles_scope CHECK (scope IN ('INTERNAL', 'TENANT'))");
        DB::statement("ALTER TABLE roles ADD CONSTRAINT chk_roles_scope_tenant_consistency CHECK ((scope = 'INTERNAL' AND tenant_id IS NULL) OR (scope = 'TENANT' AND tenant_id IS NOT NULL))");

        // Decision #4 — 2 partial unique indexes
        DB::statement('CREATE UNIQUE INDEX uq_roles_tenant_name ON roles (tenant_id, name) WHERE tenant_id IS NOT NULL AND deleted_at IS NULL');
        DB::statement('CREATE UNIQUE INDEX uq_roles_internal_name ON roles (name) WHERE tenant_id IS NULL AND deleted_at IS NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('roles');
    }
};
