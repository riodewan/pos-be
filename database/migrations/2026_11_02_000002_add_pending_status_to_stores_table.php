<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Update check constraint on stores table to include PENDING and REJECTED
        DB::statement("ALTER TABLE stores DROP CONSTRAINT IF EXISTS chk_stores_status");
        DB::statement("ALTER TABLE stores ADD CONSTRAINT chk_stores_status CHECK (status IN ('ACTIVE', 'INACTIVE', 'PENDING', 'REJECTED'))");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE stores DROP CONSTRAINT IF EXISTS chk_stores_status");
        DB::statement("ALTER TABLE stores ADD CONSTRAINT chk_stores_status CHECK (status IN ('ACTIVE', 'INACTIVE'))");
    }
};

