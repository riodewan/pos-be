<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->timestampTz('onboarded_at', 6)->nullable()->after('status');
        });

        // Existing tenants are considered already onboarded; only newly approved tenants see first-login setup.
        DB::statement('UPDATE tenants SET onboarded_at = created_at');
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn('onboarded_at');
        });
    }
};

