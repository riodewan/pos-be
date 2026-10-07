<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Schema v2.1.0 — §8 users
 * Migration Phase 2 — User & Tenant Foundation
 *
 * NOTE (Correction #4): users.is_internal is an administrative/cache flag only.
 * Authorization source of truth: user_roles -> roles -> roles.scope = 'INTERNAL'.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('name', 100);
            $table->string('email', 255);
            $table->timestampTz('email_verified_at', 6)->nullable();
            $table->string('password', 255);
            $table->boolean('is_internal')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestampTz('created_at', 6)->useCurrent();
            $table->timestampTz('updated_at', 6)->useCurrent();
            $table->softDeletesTz('deleted_at', 6);
        });

        // Decision #3 — partial unique index (email re-usable after soft delete)
        DB::statement('CREATE UNIQUE INDEX uq_users_email ON users (email) WHERE deleted_at IS NULL');
        DB::statement('CREATE INDEX idx_users_is_internal ON users (is_internal) WHERE is_internal = true');
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
