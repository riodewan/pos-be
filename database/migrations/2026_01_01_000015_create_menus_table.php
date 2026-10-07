<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Schema v2.1.0 — §21 menus
 * Migration Phase 6 — Recipes & Menus
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('menus', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->char('store_id', 26);
            $table->string('name', 100);
            $table->text('description')->nullable();
            $table->string('status', 20)->default('ACTIVE');
            $table->timestampTz('created_at', 6)->useCurrent();
            $table->timestampTz('updated_at', 6)->useCurrent();
            $table->softDeletesTz('deleted_at', 6);

            $table->foreign('store_id', 'fk_menus_store_id')
                ->references('id')->on('stores')->restrictOnDelete();

            $table->index('store_id', 'idx_menus_store_id');
            $table->index('status', 'idx_menus_status');
        });

        DB::statement("ALTER TABLE menus ADD CONSTRAINT chk_menus_status CHECK (status IN ('ACTIVE', 'INACTIVE'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('menus');
    }
};
