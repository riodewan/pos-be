<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('store_payment_methods', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->char('store_id', 26);
            $table->string('name', 100);
            $table->string('type', 30)->default('E - Wallet'); // 'Tunai', 'E - Wallet', 'Kartu', 'Transfer'
            $table->string('description', 255)->nullable();
            $table->string('icon_type', 30)->default('qris'); // 'cash', 'qris', 'ovo', 'dana', 'shopeepay', 'card', 'bank'
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestampTz('created_at', 6)->useCurrent();
            $table->timestampTz('updated_at', 6)->useCurrent();
            $table->softDeletesTz('deleted_at', 6);

            $table->foreign('store_id', 'fk_payment_methods_store_id')
                ->references('id')->on('stores')->cascadeOnDelete();

            $table->index(['store_id', 'sort_order'], 'idx_payment_methods_store_sort');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('store_payment_methods');
    }
};
