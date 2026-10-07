<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Schema v2.1.0 — §19 recipes
 * Migration Phase 6 — Recipes & Menus
 * No soft delete: use status = 'INACTIVE'.
 * Cross-store (recipes.store_id = products.store_id) is service-layer validation (Decision #9).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recipes', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->char('store_id', 26);
            $table->char('product_id', 26);
            $table->string('name', 100);
            $table->string('status', 20)->default('ACTIVE');
            $table->timestampTz('created_at', 6)->useCurrent();
            $table->timestampTz('updated_at', 6)->useCurrent();

            $table->foreign('store_id', 'fk_recipes_store_id')
                ->references('id')->on('stores')->restrictOnDelete();
            $table->foreign('product_id', 'fk_recipes_product_id')
                ->references('id')->on('products')->restrictOnDelete();

            $table->index('store_id', 'idx_recipes_store_id');
            $table->index('product_id', 'idx_recipes_product_id');
            $table->index(['store_id', 'product_id'], 'idx_recipes_store_product');
        });

        DB::statement("ALTER TABLE recipes ADD CONSTRAINT chk_recipes_status CHECK (status IN ('ACTIVE', 'INACTIVE'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('recipes');
    }
};
