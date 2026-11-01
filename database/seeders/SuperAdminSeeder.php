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
        $user = User::where('email', 'admin@pos.com')->first();
        if ($user) {
            $user->update([
                'name' => 'Rio (Admin Internal)',
                'password' => 'Password123!',
                'phone' => '0812-0000-0001',
                'job_title' => 'Platform Super Admin',
                'is_internal' => true,
                'is_active' => true,
            ]);
        } else {
            User::create([
                'id' => (string) Str::ulid(),
                'name' => 'Rio (Admin Internal)',
                'email' => 'admin@pos.com',
                'password' => 'Password123!',
                'phone' => '0812-0000-0001',
                'job_title' => 'Platform Super Admin',
                'is_internal' => true,
                'is_active' => true,
            ]);
        }
    }
}
