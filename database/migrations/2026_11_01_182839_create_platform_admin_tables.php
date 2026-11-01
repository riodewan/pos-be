<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Platform Approvals Table
        Schema::create('platform_approvals', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('type', 50); // TENANT_REGISTRATION, OUTLET_ADDITION, PLAN_UPGRADE, REFUND_REQUEST
            $table->char('tenant_id', 26)->nullable();
            $table->char('store_id', 26)->nullable();
            $table->char('user_id', 26)->nullable();
            $table->string('title', 255);
            $table->text('description')->nullable();
            $table->jsonb('payload')->nullable();
            $table->string('status', 30)->default('PENDING'); // PENDING, APPROVED, REJECTED
            $table->char('reviewed_by', 26)->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_notes')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->nullOnDelete();
            $table->foreign('store_id')->references('id')->on('stores')->nullOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('reviewed_by')->references('id')->on('users')->nullOnDelete();
            $table->index(['type', 'status']);
        });

        // 2. Master Business Categories Table
        Schema::create('platform_master_categories', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('name', 100);
            $table->string('slug', 100)->unique();
            $table->text('description')->nullable();
            $table->string('icon', 50)->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        // 3. Platform Subscription Plans Table
        Schema::create('platform_subscription_plans', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('name', 100);
            $table->string('code', 50)->unique();
            $table->decimal('price', 14, 2)->default(0);
            $table->string('billing_cycle', 20)->default('MONTHLY');
            $table->integer('max_outlets')->default(1);
            $table->integer('max_staff')->default(5);
            $table->jsonb('features')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_subscription_plans');
        Schema::dropIfExists('platform_master_categories');
        Schema::dropIfExists('platform_approvals');
    }
};
