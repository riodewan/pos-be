<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Schema v2.1.0 — §28 wastages
 * Migration Phase 8 — Wastage
 * Transactional — no soft delete (status = 'CANCELLED').
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wastages', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->char('store_id', 26);
            $table->string('wastage_number', 50);
            $table->date('wastage_date');
            $table->text('reason')->nullable();
            $table->string('status', 20)->default('DRAFT');
            $table->char('created_by', 26)->nullable();
            $table->timestampTz('created_at', 6)->useCurrent();
            $table->timestampTz('updated_at', 6)->useCurrent();

            $table->foreign('store_id', 'fk_wastages_store_id')
                ->references('id')->on('stores')->restrictOnDelete();
            $table->foreign('created_by', 'fk_wastages_created_by')
                ->references('id')->on('users')->nullOnDelete();

            $table->unique(['store_id', 'wastage_number'], 'uq_wastages_store_number');
            $table->index('store_id', 'idx_wastages_store_id');
            $table->index('status', 'idx_wastages_status');
            $table->index('wastage_date', 'idx_wastages_wastage_date');
        });

        DB::statement("ALTER TABLE wastages ADD CONSTRAINT chk_wastages_status CHECK (status IN ('DRAFT', 'COMPLETED', 'CANCELLED'))");

        DB::statement('CREATE INDEX idx_wastages_created_by ON wastages (created_by) WHERE created_by IS NOT NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('wastages');
    }
};
