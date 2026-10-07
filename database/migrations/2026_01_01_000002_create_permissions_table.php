<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Schema v2.1.0 — §13 permissions
 * Migration Phase 1 — No Dependencies
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('permissions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('name', 100);
            $table->string('module', 50);
            $table->text('description')->nullable();
            $table->timestampTz('created_at', 6)->useCurrent();
            $table->timestampTz('updated_at', 6)->useCurrent();

            $table->unique('name', 'uq_permissions_name');
            $table->index('module', 'idx_permissions_module');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('permissions');
    }
};
