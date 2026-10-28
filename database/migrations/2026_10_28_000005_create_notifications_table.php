<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->char('tenant_id', 26);
            $table->char('store_id', 26)->nullable();
            $table->char('user_id', 26)->nullable();
            $table->string('title', 150);
            $table->string('subtitle', 255);
            $table->string('category', 30)->default('Sistem'); // 'Pesanan', 'Reservasi', 'Inventori', 'Sistem'
            $table->boolean('is_read')->default(false);
            $table->timestampTz('read_at', 6)->nullable();
            $table->string('target_url', 255)->nullable();
            $table->timestampTz('created_at', 6)->useCurrent();
            $table->timestampTz('updated_at', 6)->useCurrent();

            $table->foreign('tenant_id', 'fk_notifications_tenant_id')
                ->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('store_id', 'fk_notifications_store_id')
                ->references('id')->on('stores')->cascadeOnDelete();
            $table->foreign('user_id', 'fk_notifications_user_id')
                ->references('id')->on('users')->cascadeOnDelete();

            $table->index(['tenant_id', 'is_read'], 'idx_notifications_tenant_read');
            $table->index('category', 'idx_notifications_category');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
