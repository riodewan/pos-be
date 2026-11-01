<?php

namespace Database\Seeders;

use App\Modules\Auth\Models\User;
use App\Modules\Tenant\Models\Tenant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PlatformAdminSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Seed Master Business Categories
        $categories = [
            ['name' => 'Coffee Shop', 'slug' => 'coffee-shop', 'description' => 'Kedai kopi, espresso bar, dan cafe minuman khusus.', 'icon' => 'Coffee', 'sort_order' => 1],
            ['name' => 'Restoran & Cafe', 'slug' => 'restoran-cafe', 'description' => 'Restoran dine-in dengan layanan meja lengkap dan menu makanan berat.', 'icon' => 'UtensilsCrossed', 'sort_order' => 2],
            ['name' => 'Bakery & Pastry', 'slug' => 'bakery-pastry', 'description' => 'Toko roti, kue basah, pastry, dan dessert.', 'icon' => 'Cake', 'sort_order' => 3],
            ['name' => 'Fast Food & Quick Service', 'slug' => 'fast-food', 'description' => 'Makanan cepat saji dengan pemesanan counter dan takeaway cepat.', 'icon' => 'Pizza', 'sort_order' => 4],
            ['name' => 'Food Court & Booth', 'slug' => 'food-court', 'description' => 'Kios makanan dan stand kuliner di area food court atau mall.', 'icon' => 'Store', 'sort_order' => 5],
            ['name' => 'Bar & Lounge', 'slug' => 'bar-lounge', 'description' => 'Bar minuman, cocktail lounge, dan tempat hiburan malam.', 'icon' => 'Wine', 'sort_order' => 6],
            ['name' => 'Retail & Toko Kelontong', 'slug' => 'retail', 'description' => 'Penjualan produk ritel, minimarket, dan toko serba ada.', 'icon' => 'ShoppingBag', 'sort_order' => 7],
        ];

        foreach ($categories as $cat) {
            DB::table('platform_master_categories')->updateOrInsert(
                ['slug' => $cat['slug']],
                [
                    'id' => (string) Str::ulid(),
                    'name' => $cat['name'],
                    'description' => $cat['description'],
                    'icon' => $cat['icon'],
                    'is_active' => true,
                    'sort_order' => $cat['sort_order'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }

        // 2. Seed Subscription Plans
        $plans = [
            [
                'name' => 'Starter / UMKM',
                'code' => 'STARTER',
                'price' => 149000,
                'billing_cycle' => 'MONTHLY',
                'max_outlets' => 1,
                'max_staff' => 3,
                'features' => json_encode([
                    '1 Outlet Aktif',
                    'Hingga 3 Staf Kasir',
                    'Katalog Produk Tanpa Batas',
                    'Laporan Penjualan Dasar',
                    'Pemesanan QR Meja',
                    'Dukungan Komunitas',
                ]),
            ],
            [
                'name' => 'Professional Business',
                'code' => 'PRO',
                'price' => 349000,
                'billing_cycle' => 'MONTHLY',
                'max_outlets' => 3,
                'max_staff' => 15,
                'features' => json_encode([
                    'Hingga 3 Outlet Aktif',
                    'Hingga 15 Staf Kasir & Gudang',
                    'Resep & HPP Otomatis',
                    'Manajemen Multi-Gudang (PO & Transfer)',
                    'Laporan Analitik Lengkap & Export Excel',
                    'Integrasi QRIS Dinamis & EDC',
                    'Kitchen Display System (KDS)',
                    'Dukungan Prioritas 24/7',
                ]),
            ],
            [
                'name' => 'Enterprise Franchise',
                'code' => 'ENTERPRISE',
                'price' => 899000,
                'billing_cycle' => 'MONTHLY',
                'max_outlets' => 10,
                'max_staff' => 100,
                'features' => json_encode([
                    'Hingga 10+ Outlet Multi-Cabang',
                    'Staf Tanpa Batas dengan Hak Akses Kustom',
                    'Manajemen Franchise Terpusat',
                    'Pusat Pasokan & Central Kitchen Support',
                    'Akses API Platform & Webhook',
                    'Dedicated Account Manager & SLA 99.9%',
                    'Audit Log & Compliance Enterprise',
                ]),
            ],
        ];

        foreach ($plans as $p) {
            DB::table('platform_subscription_plans')->updateOrInsert(
                ['code' => $p['code']],
                [
                    'id' => (string) Str::ulid(),
                    'name' => $p['name'],
                    'price' => $p['price'],
                    'billing_cycle' => $p['billing_cycle'],
                    'max_outlets' => $p['max_outlets'],
                    'max_staff' => $p['max_staff'],
                    'features' => $p['features'],
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }

        // 3. Seed Platform Approvals (Realistic pending and approved requests)
        $tenant = Tenant::first();
        $admin = User::where('is_internal', true)->first();

        if ($tenant && DB::table('platform_approvals')->count() === 0) {
            DB::table('platform_approvals')->insert([
                [
                    'id' => (string) Str::ulid(),
                    'type' => 'OUTLET_ADDITION',
                    'tenant_id' => $tenant->id,
                    'store_id' => null,
                    'user_id' => null,
                    'title' => 'Pengajuan Cabang Baru: Outlet Kemang',
                    'description' => 'Tenant Kopi Nusantara mengajukan pembukaan outlet cabang ke-3 di Jl. Kemang Raya No. 12, Jakarta Selatan.',
                    'payload' => json_encode([
                        'branch_name' => 'Outlet Kemang',
                        'city' => 'Jakarta Selatan',
                        'requested_package' => 'PRO',
                        'contact_person' => 'Bambang Sudiro (0812-3456-7890)',
                    ]),
                    'status' => 'PENDING',
                    'reviewed_by' => null,
                    'reviewed_at' => null,
                    'review_notes' => null,
                    'created_at' => now()->subHours(3),
                    'updated_at' => now()->subHours(3),
                ],
                [
                    'id' => (string) Str::ulid(),
                    'type' => 'PLAN_UPGRADE',
                    'tenant_id' => $tenant->id,
                    'store_id' => null,
                    'user_id' => null,
                    'title' => 'Upgrade Paket Langganan: STARTER ke PRO',
                    'description' => 'Tenant mengajukan upgrade paket langganan tahunan untuk mengaktifkan modul Resep & HPP dan Multi-Outlet.',
                    'payload' => json_encode([
                        'current_plan' => 'STARTER',
                        'target_plan' => 'PRO',
                        'payment_method' => 'BANK_TRANSFER',
                        'amount' => 3769200,
                    ]),
                    'status' => 'PENDING',
                    'reviewed_by' => null,
                    'reviewed_at' => null,
                    'review_notes' => null,
                    'created_at' => now()->subHours(7),
                    'updated_at' => now()->subHours(7),
                ],
                [
                    'id' => (string) Str::ulid(),
                    'type' => 'TENANT_REGISTRATION',
                    'tenant_id' => $tenant->id,
                    'store_id' => null,
                    'user_id' => null,
                    'title' => 'Verifikasi Bisnis: Kopi Nusantara HQ',
                    'description' => 'Verifikasi dokumen legalitas usaha NIB dan rekening operasional tenant.',
                    'payload' => json_encode([
                        'business_entity' => 'PT Kopi Nusantara Abadi',
                        'nib' => '9120001234567',
                        'bank_account' => 'BCA 5420192837 a.n PT Kopi Nusantara Abadi',
                    ]),
                    'status' => 'APPROVED',
                    'reviewed_by' => $admin?->id,
                    'reviewed_at' => now()->subDays(2),
                    'review_notes' => 'Dokumen NIB dan rekening valid dan telah diverifikasi.',
                    'created_at' => now()->subDays(3),
                    'updated_at' => now()->subDays(2),
                ],
            ]);
        }
    }
}
