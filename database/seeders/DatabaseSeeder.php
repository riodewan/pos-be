<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database with essential initial data.
     */
    public function run(): void
    {
        $this->call([
            UnitSeeder::class,
            PlatformMasterDataSeeder::class,
            RbacSeeder::class,
            SuperAdminSeeder::class,
            DemoTenantSeeder::class,
            DemoOutletSeeder::class,
            PlatformAdminSeeder::class,
        ]);
    }
}
