<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Update check constraint on tenants table to include PENDING and REJECTED
        DB::statement("ALTER TABLE tenants DROP CONSTRAINT IF EXISTS chk_tenants_status");
        DB::statement("ALTER TABLE tenants ADD CONSTRAINT chk_tenants_status CHECK (status IN ('ACTIVE', 'INACTIVE', 'SUSPENDED', 'PENDING', 'REJECTED'))");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE tenants DROP CONSTRAINT IF EXISTS chk_tenants_status");
        DB::statement("ALTER TABLE tenants ADD CONSTRAINT chk_tenants_status CHECK (status IN ('ACTIVE', 'INACTIVE', 'SUSPENDED'))");
    }
};

