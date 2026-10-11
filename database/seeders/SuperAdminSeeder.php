<?php

namespace Database\Seeders;

use App\Modules\Auth\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class SuperAdminSeeder extends Seeder
{
    /**
     * Seed initial SaaS platform Super Admin account.
     * Platform internal admins are recognized via `users.is_internal = true`.
     */
    public function run(): void
    {
        User::firstOrCreate(
            ['email' => 'admin@pos.com'],
            [
                'id' => (string) Str::ulid(),
                'name' => 'Platform Super Admin',
                'password' => 'SuperAdmin123!',
                'is_internal' => true,
                'is_active' => true,
            ]
        );
    }
}
