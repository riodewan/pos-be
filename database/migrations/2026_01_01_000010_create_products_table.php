<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Schema v2.1.0 — §17 products
 * Migration Phase 5 — Store Catalog
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->char('store_id', 26);
            $table->string('name', 100);
            $table->string('sku', 50);
            $table->text('description')->nullable();
            $table->decimal('selling_price', 15, 2)->default(0);
            $table->string('status', 20)->default('ACTIVE');
            $table->timestampTz('created_at', 6)->useCurrent();
            $table->timestampTz('updated_at', 6)->useCurrent();
            $table->softDeletesTz('deleted_at', 6);

            $table->foreign('store_id', 'fk_products_store_id')
                ->references('id')->on('stores')->restrictOnDelete();

            $table->index('store_id', 'idx_products_store_id');
            $table->index('status', 'idx_products_status');
        });

        DB::statement("ALTER TABLE products ADD CONSTRAINT chk_products_status CHECK (status IN ('ACTIVE', 'INACTIVE'))");
        DB::statement('ALTER TABLE products ADD CONSTRAINT chk_products_selling_price CHECK (selling_price >= 0)');

        DB::statement('CREATE UNIQUE INDEX uq_products_store_sku ON products (store_id, sku) WHERE deleted_at IS NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
