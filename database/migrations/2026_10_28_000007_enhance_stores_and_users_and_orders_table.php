<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Enhance stores table
        Schema::table('stores', function (Blueprint $table) {
            $table->string('logo_url', 500)->nullable();
            $table->string('thumbnail_url', 500)->nullable();
            $table->string('category', 100)->nullable();
            $table->text('description')->nullable();
            $table->string('email', 255)->nullable();
            $table->string('website', 255)->nullable();
            $table->string('maps_url', 500)->nullable();
            $table->jsonb('receipt_config')->nullable();
            $table->jsonb('operating_hours')->nullable();
            $table->jsonb('facilities')->nullable();
            $table->jsonb('photos')->nullable();
            $table->string('operational_status', 30)->default('OPEN');
            $table->string('publication_status', 30)->default('PUBLISHED');
        });

        // 2. Enhance users table
        Schema::table('users', function (Blueprint $table) {
            $table->string('avatar_url', 500)->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('job_title', 100)->nullable();
            $table->jsonb('notification_preferences')->nullable();
            $table->jsonb('user_preferences')->nullable();
        });

        // 3. Enhance orders table
        Schema::table('orders', function (Blueprint $table) {
            $table->string('channel', 30)->default('DINE_IN');
            $table->char('customer_id', 26)->nullable();
            $table->string('discount_reason', 255)->nullable();

            $table->foreign('customer_id', 'fk_orders_customer_id')
                ->references('id')->on('customers')->nullOnDelete();
            $table->index('channel', 'idx_orders_channel');
            $table->index('customer_id', 'idx_orders_customer_id');
        });

        // 4. Enhance payments table
        Schema::table('payments', function (Blueprint $table) {
            $table->decimal('mdr_fee', 15, 2)->default(0);
            $table->decimal('net_amount', 15, 2)->default(0);
            $table->string('settlement_status', 30)->default('MENUNGGU');

            $table->index('settlement_status', 'idx_payments_settlement_status');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex('idx_payments_settlement_status');
            $table->dropColumn(['mdr_fee', 'net_amount', 'settlement_status']);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign('fk_orders_customer_id');
            $table->dropIndex('idx_orders_customer_id');
            $table->dropIndex('idx_orders_channel');
            $table->dropColumn(['channel', 'customer_id', 'discount_reason']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'avatar_url',
                'phone',
                'job_title',
                'notification_preferences',
                'user_preferences',
            ]);
        });

        Schema::table('stores', function (Blueprint $table) {
            $table->dropColumn([
                'logo_url',
                'thumbnail_url',
                'category',
                'description',
                'email',
                'website',
                'maps_url',
                'receipt_config',
                'operating_hours',
                'facilities',
                'photos',
                'operational_status',
                'publication_status',
            ]);
        });
    }
};
