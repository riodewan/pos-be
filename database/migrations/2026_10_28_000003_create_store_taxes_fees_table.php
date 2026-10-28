<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('store_taxes_fees', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->char('store_id', 26);
            $table->string('name', 100);
            $table->string('type', 20)->default('Pajak'); // 'Pajak', 'Biaya'
            $table->string('value_type', 20)->default('percent'); // 'percent', 'fixed'
            $table->string('value', 50); // e.g. '11%', 'Rp2.000'
            $table->string('applies_to', 100)->default('Semua Menu'); // 'Semua Menu', 'Dine-in, Takeway', 'Delivery'
            $table->boolean('is_active')->default(true);
            $table->timestampTz('created_at', 6)->useCurrent();
            $table->timestampTz('updated_at', 6)->useCurrent();
            $table->softDeletesTz('deleted_at', 6);

            $table->foreign('store_id', 'fk_taxes_fees_store_id')
                ->references('id')->on('stores')->cascadeOnDelete();

            $table->index('store_id', 'idx_taxes_fees_store_id');
            $table->index('type', 'idx_taxes_fees_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('store_taxes_fees');
    }
};
