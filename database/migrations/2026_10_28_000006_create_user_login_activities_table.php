<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_login_activities', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->char('user_id', 26);
            $table->string('status', 20)->default('SUCCESS'); // 'SUCCESS', 'FAILED'
            $table->string('title', 100)->default('Login Berhasil');
            $table->string('device', 100)->default('Web Browser'); // 'MacOS • Chrome', 'iPhone • Mobile'
            $table->string('ip_address', 50)->nullable();
            $table->string('location', 100)->nullable(); // e.g. 'Jakarta, Indonesia'
            $table->timestampTz('logged_at', 6)->useCurrent();

            $table->foreign('user_id', 'fk_login_activities_user_id')
                ->references('id')->on('users')->cascadeOnDelete();

            $table->index(['user_id', 'logged_at'], 'idx_login_activities_user_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_login_activities');
    }
};
