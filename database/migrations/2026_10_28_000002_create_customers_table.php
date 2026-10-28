<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->char('tenant_id', 26);
            $table->string('name', 100);
            $table->string('phone', 20)->nullable();
            $table->string('email', 100)->nullable();
            $table->string('tier', 20)->default('Bronze'); // Bronze, Silver, Gold
            $table->integer('points')->default(0);
            $table->decimal('total_spent', 15, 2)->default(0);
            $table->integer('total_visits')->default(0);
            $table->timestampTz('last_visit_at', 6)->nullable();
            $table->timestampTz('created_at', 6)->useCurrent();
            $table->timestampTz('updated_at', 6)->useCurrent();
            $table->softDeletesTz('deleted_at', 6);

            $table->foreign('tenant_id', 'fk_customers_tenant_id')
                ->references('id')->on('tenants')->cascadeOnDelete();

            $table->index('tenant_id', 'idx_customers_tenant_id');
            $table->index('phone', 'idx_customers_phone');
            $table->index('tier', 'idx_customers_tier');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
