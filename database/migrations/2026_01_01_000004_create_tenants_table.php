<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Schema v2.1.0 — §9 tenants
 * Migration Phase 2 — User & Tenant Foundation
 *
 * Business rule (application/transaction layer): a tenant must have at least one store.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('name', 100);
            $table->string('slug', 100);
            $table->string('email', 255);
            $table->string('phone', 20)->nullable();
            $table->text('address')->nullable();
            $table->string('status', 20)->default('ACTIVE');
            $table->timestampTz('created_at', 6)->useCurrent();
            $table->timestampTz('updated_at', 6)->useCurrent();
            $table->softDeletesTz('deleted_at', 6);

            $table->index('status', 'idx_tenants_status');
        });

        DB::statement("ALTER TABLE tenants ADD CONSTRAINT chk_tenants_status CHECK (status IN ('ACTIVE', 'INACTIVE', 'SUSPENDED'))");

        DB::statement('CREATE UNIQUE INDEX uq_tenants_slug ON tenants (slug) WHERE deleted_at IS NULL');
        DB::statement('CREATE UNIQUE INDEX uq_tenants_email ON tenants (email) WHERE deleted_at IS NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('tenants');
    }
};
