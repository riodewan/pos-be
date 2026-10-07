<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Schema v2.1.0 — §23 suppliers
 * Migration Phase 5 — Store Catalog
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('suppliers', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->char('store_id', 26);
            $table->string('name', 100);
            $table->string('code', 50);
            $table->string('contact_person', 100)->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('email', 255)->nullable();
            $table->text('address')->nullable();
            $table->string('status', 20)->default('ACTIVE');
            $table->timestampTz('created_at', 6)->useCurrent();
            $table->timestampTz('updated_at', 6)->useCurrent();
            $table->softDeletesTz('deleted_at', 6);

            $table->foreign('store_id', 'fk_suppliers_store_id')
                ->references('id')->on('stores')->restrictOnDelete();

            $table->index('store_id', 'idx_suppliers_store_id');
        });

        DB::statement("ALTER TABLE suppliers ADD CONSTRAINT chk_suppliers_status CHECK (status IN ('ACTIVE', 'INACTIVE'))");

        DB::statement('CREATE UNIQUE INDEX uq_suppliers_store_code ON suppliers (store_id, code) WHERE deleted_at IS NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('suppliers');
    }
};
