<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->text('logo_url')->nullable()->change();
            $table->text('thumbnail_url')->nullable()->change();
            $table->text('maps_url')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->string('logo_url', 500)->nullable()->change();
            $table->string('thumbnail_url', 500)->nullable()->change();
            $table->string('maps_url', 500)->nullable()->change();
        });
    }
};
