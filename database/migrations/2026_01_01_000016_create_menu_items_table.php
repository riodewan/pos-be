<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Schema v2.1.0 — §22 menu_items
 * Migration Phase 6 — Recipes & Menus
 * No updated_at per schema (created_at only).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('menu_items', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->char('menu_id', 26);
            $table->char('product_id', 26);
            $table->integer('sort_order')->default(0);
            $table->timestampTz('created_at', 6)->useCurrent();

            $table->foreign('menu_id', 'fk_menu_items_menu_id')
                ->references('id')->on('menus')->cascadeOnDelete();
            $table->foreign('product_id', 'fk_menu_items_product_id')
                ->references('id')->on('products')->restrictOnDelete();

            $table->unique(['menu_id', 'product_id'], 'uq_menu_items_menu_product');
            $table->index('product_id', 'idx_menu_items_product_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('menu_items');
    }
};
