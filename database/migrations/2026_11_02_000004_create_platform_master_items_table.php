<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('platform_master_items')) {
            Schema::create('platform_master_items', function (Blueprint $table) {
                $table->ulid('id')->primary();
                $table->string('group', 50)->index(); // FACILITY, BUSINESS_TYPE, PAYMENT_METHOD, TAX_FEE, BANK
                $table->string('name', 150);
                $table->string('code', 100)->nullable();
                $table->string('icon', 50)->nullable();
                $table->text('description')->nullable();
                $table->jsonb('metadata')->nullable();
                $table->boolean('is_active')->default(true);
                $table->integer('sort_order')->default(0);
                $table->timestampsTz(6);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_master_items');
    }
};
