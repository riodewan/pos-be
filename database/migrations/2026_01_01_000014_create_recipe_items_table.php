<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Schema v2.1.0 — §20 recipe_items
 * Migration Phase 6 — Recipes & Menus
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recipe_items', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->char('recipe_id', 26);
            $table->char('ingredient_id', 26);
            $table->decimal('quantity', 15, 4);
            $table->char('unit_id', 26);
            $table->timestampTz('created_at', 6)->useCurrent();
            $table->timestampTz('updated_at', 6)->useCurrent();

            $table->foreign('recipe_id', 'fk_recipe_items_recipe_id')
                ->references('id')->on('recipes')->cascadeOnDelete();
            $table->foreign('ingredient_id', 'fk_recipe_items_ingredient_id')
                ->references('id')->on('ingredients')->restrictOnDelete();
            $table->foreign('unit_id', 'fk_recipe_items_unit_id')
                ->references('id')->on('units')->restrictOnDelete();

            $table->unique(['recipe_id', 'ingredient_id'], 'uq_recipe_items_recipe_ingredient');
            $table->index('ingredient_id', 'idx_recipe_items_ingredient_id');
        });

        DB::statement('ALTER TABLE recipe_items ADD CONSTRAINT chk_recipe_items_quantity CHECK (quantity > 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('recipe_items');
    }
};
