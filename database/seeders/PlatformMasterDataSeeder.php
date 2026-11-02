<?php

namespace Database\Seeders;

use App\Modules\Tenant\Models\PlatformMasterCategory;
use App\Modules\Tenant\Models\PlatformMasterItem;
use App\Modules\Tenant\Models\PlatformSubscriptionPlan;
use Illuminate\Database\Seeder;

class PlatformMasterDataSeeder extends Seeder
{
    /**
     * Seed global platform master data managed by Internal Admin and used across all user/merchant pages.
     */
    public function run(): void
    {
        // 1. Kategori Bisnis Global (platform_master_categories)
        $categories = [
            [
                'slug' => 'coffee-shop-cafe',
                'name' => 'Coffee Shop & Cafe',
                'description' => 'Kedai kopi, espresso bar, teh, dan tempat nongkrong kasual.',
                'icon' => 'Coffee',
                'sort_order' => 1,
            ],
            [
                'slug' => 'restoran-rumah-makan',
                'name' => 'Restoran & Rumah Makan',
                'description' => 'Restoran keluarga, fine dining, dan rumah makan layanan meja lengkap.',
                'icon' => 'UtensilsCrossed',
                'sort_order' => 2,
            ],
            [
                'slug' => 'bakery-pastry',
                'name' => 'Bakery & Pastry',
                'description' => 'Toko roti, kue basah, pastry, gelato, dan dessert.',
                'icon' => 'Cake',
                'sort_order' => 3,
            ],
            [
                'slug' => 'fast-food-cloud-kitchen',
                'name' => 'Fast Food & Cloud Kitchen',
                'description' => 'Makanan cepat saji, counter takeaway, dan dapur pesan-antar.',
                'icon' => 'Pizza',
                'sort_order' => 4,
            ],
            [
                'slug' => 'food-court-booth',
                'name' => 'Food Court & Booth',
                'description' => 'Kios makanan, stand minuman, dan tenant pusat kuliner.',
                'icon' => 'Store',
                'sort_order' => 5,
            ],
            [
                'slug' => 'bar-lounge',
                'name' => 'Bar & Lounge',
                'description' => 'Tempat hiburan malam, lounge, dan minuman spesial.',
                'icon' => 'Wine',
                'sort_order' => 6,
            ],
            [
                'slug' => 'retail-minimarket',
                'name' => 'Retail & Minimarket',
                'description' => 'Toko kelontong, minimarket, dan penjualan produk ritel harian.',
                'icon' => 'ShoppingBag',
                'sort_order' => 7,
            ],
        ];

        foreach ($categories as $cat) {
            PlatformMasterCategory::firstOrCreate(
                ['slug' => $cat['slug']],
                [
                    'name' => $cat['name'],
                    'description' => $cat['description'],
                    'icon' => $cat['icon'],
                    'is_active' => true,
                    'sort_order' => $cat['sort_order'],
                ]
            );
        }

        // 2. Item Master Global (platform_master_items)
        $items = [
            // A. FACILITY (Fasilitas Outlet Global)
            ['group' => 'FACILITY', 'code' => 'wifi', 'name' => 'WiFi Gratis', 'icon' => 'Wifi', 'description' => 'Koneksi internet nirkabel untuk pengunjung', 'sort_order' => 1],
            ['group' => 'FACILITY', 'code' => 'ac', 'name' => 'Ruangan Ber-AC', 'icon' => 'Wind', 'description' => 'Ruangan sejuk dengan pendingin udara', 'sort_order' => 2],
            ['group' => 'FACILITY', 'code' => 'indoor', 'name' => 'Area Indoor', 'icon' => 'Store', 'description' => 'Area makan di dalam ruangan', 'sort_order' => 3],
            ['group' => 'FACILITY', 'code' => 'outdoor', 'name' => 'Area Outdoor', 'icon' => 'Sun', 'description' => 'Area makan terbuka di luar ruangan', 'sort_order' => 4],
            ['group' => 'FACILITY', 'code' => 'parking', 'name' => 'Parkir Mobil & Motor', 'icon' => 'Car', 'description' => 'Area parkir kendaraan pelanggan', 'sort_order' => 5],
            ['group' => 'FACILITY', 'code' => 'toilet', 'name' => 'Toilet', 'icon' => 'Accessibility', 'description' => 'Fasilitas kamar kecil pelanggan', 'sort_order' => 6],
            ['group' => 'FACILITY', 'code' => 'mushola', 'name' => 'Mushola', 'icon' => 'Sparkles', 'description' => 'Tempat ibadah untuk pengunjung', 'sort_order' => 7],
            ['group' => 'FACILITY', 'code' => 'power_outlet', 'name' => 'Stop Kontak', 'icon' => 'Zap', 'description' => 'Colokan listrik di area meja', 'sort_order' => 8],
            ['group' => 'FACILITY', 'code' => 'smoking_area', 'name' => 'Smoking Area', 'icon' => 'Flame', 'description' => 'Area khusus merokok', 'sort_order' => 9],
            ['group' => 'FACILITY', 'code' => 'wheelchair', 'name' => 'Akses Kursi Roda', 'icon' => 'Accessibility', 'description' => 'Ramah pengguna kursi roda / disabilitas', 'sort_order' => 10],
            ['group' => 'FACILITY', 'code' => 'live_music', 'name' => 'Live Music', 'icon' => 'Music', 'description' => 'Hiburan musik langsung berkala', 'sort_order' => 11],
            ['group' => 'FACILITY', 'code' => 'vip_room', 'name' => 'VIP Room', 'icon' => 'Crown', 'description' => 'Ruang privat untuk reservasi / rapat', 'sort_order' => 12],

            // B. BUSINESS_TYPE (Jenis Usaha / Badan Hukum Global)
            ['group' => 'BUSINESS_TYPE', 'code' => 'PERORANGAN', 'name' => 'Perorangan', 'icon' => 'User', 'description' => 'Usaha milik pribadi / UMKM perorangan', 'sort_order' => 1],
            ['group' => 'BUSINESS_TYPE', 'code' => 'PT', 'name' => 'PT (Perseroan Terbatas)', 'icon' => 'Building2', 'description' => 'Badan hukum Perseroan Terbatas', 'sort_order' => 2],
            ['group' => 'BUSINESS_TYPE', 'code' => 'CV', 'name' => 'CV (Commanditaire Vennootschap)', 'icon' => 'Briefcase', 'description' => 'Persekutuan Komanditer', 'sort_order' => 3],
            ['group' => 'BUSINESS_TYPE', 'code' => 'FIRMA', 'name' => 'Firma', 'icon' => 'Users', 'description' => 'Persekutuan Firma', 'sort_order' => 4],
            ['group' => 'BUSINESS_TYPE', 'code' => 'KOPERASI', 'name' => 'Koperasi', 'icon' => 'Handshake', 'description' => 'Badan usaha Koperasi', 'sort_order' => 5],

            // C. PAYMENT_METHOD (Template Metode Pembayaran Global)
            ['group' => 'PAYMENT_METHOD', 'code' => 'CASH', 'name' => 'Tunai (Cash)', 'icon' => 'Banknote', 'description' => 'Pembayaran uang tunai di kasir', 'metadata' => ['category' => 'CASH', 'fee_percent' => 0], 'sort_order' => 1],
            ['group' => 'PAYMENT_METHOD', 'code' => 'QRIS', 'name' => 'QRIS Dinamis / Statis', 'icon' => 'QrCode', 'description' => 'Standar QR Nasional semua e-wallet & m-banking', 'metadata' => ['category' => 'QRIS', 'fee_percent' => 0.7], 'sort_order' => 2],
            ['group' => 'PAYMENT_METHOD', 'code' => 'EDC', 'name' => 'Kartu Debit / Kredit (EDC)', 'icon' => 'CreditCard', 'description' => 'Mesin EDC Bank (Visa / Mastercard / GPN)', 'metadata' => ['category' => 'CARD', 'fee_percent' => 1.5], 'sort_order' => 3],
            ['group' => 'PAYMENT_METHOD', 'code' => 'BANK_TRANSFER', 'name' => 'Transfer Bank / Virtual Account', 'icon' => 'Landmark', 'description' => 'Transfer antar bank atau nomor VA', 'metadata' => ['category' => 'TRANSFER', 'fee_percent' => 0], 'sort_order' => 4],
            ['group' => 'PAYMENT_METHOD', 'code' => 'EWALLET', 'name' => 'GoPay / OVO / Dana / ShopeePay', 'icon' => 'Wallet', 'description' => 'Dompet digital populer di Indonesia', 'metadata' => ['category' => 'EWALLET', 'fee_percent' => 1.5], 'sort_order' => 5],

            // D. TAX_FEE (Template Pajak & Biaya Layanan Global)
            ['group' => 'TAX_FEE', 'code' => 'PB1_10', 'name' => 'PB1 / Pajak Restoran (10%)', 'icon' => 'Percent', 'description' => 'Pajak Pembangunan 1 daerah untuk restoran & kafe', 'metadata' => ['type' => 'TAX', 'value_type' => 'PERCENTAGE', 'value' => 10], 'sort_order' => 1],
            ['group' => 'TAX_FEE', 'code' => 'PPN_11', 'name' => 'PPN (11%)', 'icon' => 'Percent', 'description' => 'Pajak Pertambahan Nilai nasional', 'metadata' => ['type' => 'TAX', 'value_type' => 'PERCENTAGE', 'value' => 11], 'sort_order' => 2],
            ['group' => 'TAX_FEE', 'code' => 'SERVICE_5', 'name' => 'Service Charge (5%)', 'icon' => 'HandCoins', 'description' => 'Biaya layanan standar restoran / hospitality', 'metadata' => ['type' => 'FEE', 'value_type' => 'PERCENTAGE', 'value' => 5], 'sort_order' => 3],
            ['group' => 'TAX_FEE', 'code' => 'PACKAGING_FEE', 'name' => 'Biaya Kemasan / Takeaway', 'icon' => 'Package', 'description' => 'Biaya tambahan kemasan pesanan bungkus', 'metadata' => ['type' => 'FEE', 'value_type' => 'FIXED', 'value' => 2000], 'sort_order' => 4],

            // E. BANK (Daftar Bank Pencairan Dana Global)
            ['group' => 'BANK', 'code' => 'BCA', 'name' => 'Bank Central Asia (BCA)', 'icon' => 'Landmark', 'description' => 'Kliring / Transfer Real-time BCA', 'sort_order' => 1],
            ['group' => 'BANK', 'code' => 'MANDIRI', 'name' => 'Bank Mandiri', 'icon' => 'Landmark', 'description' => 'Kliring / Transfer Bank Mandiri', 'sort_order' => 2],
            ['group' => 'BANK', 'code' => 'BRI', 'name' => 'Bank Rakyat Indonesia (BRI)', 'icon' => 'Landmark', 'description' => 'Kliring / Transfer Bank BRI', 'sort_order' => 3],
            ['group' => 'BANK', 'code' => 'BNI', 'name' => 'Bank Negara Indonesia (BNI)', 'icon' => 'Landmark', 'description' => 'Kliring / Transfer Bank BNI', 'sort_order' => 4],
            ['group' => 'BANK', 'code' => 'BSI', 'name' => 'Bank Syariah Indonesia (BSI)', 'icon' => 'Landmark', 'description' => 'Kliring / Transfer Bank BSI', 'sort_order' => 5],
            ['group' => 'BANK', 'code' => 'CIMB', 'name' => 'CIMB Niaga', 'icon' => 'Landmark', 'description' => 'Kliring / Transfer CIMB Niaga', 'sort_order' => 6],
        ];

        foreach ($items as $item) {
            PlatformMasterItem::firstOrCreate(
                [
                    'group' => $item['group'],
                    'code' => $item['code'],
                ],
                [
                    'name' => $item['name'],
                    'icon' => $item['icon'] ?? 'Sparkles',
                    'description' => $item['description'] ?? null,
                    'metadata' => $item['metadata'] ?? null,
                    'is_active' => true,
                    'sort_order' => $item['sort_order'] ?? 0,
                ]
            );
        }

        // 3. Skema Kemitraan & Fee Transaksi Global (platform_subscription_plans)
        $plans = [
            [
                'code' => 'PARTNER_STANDARD',
                'name' => 'Kemitraan Standar UMKM',
                'price' => 1000,
                'billing_cycle' => 'PER_TRANSACTION',
                'max_outlets' => 3,
                'max_staff' => 15,
                'features' => [
                    '0 Biaya Langganan Bulanan',
                    'Fee Jasa Transaksi Dibebankan ke Customer',
                    'Katalog, Resep & HPP Otomatis',
                    'Pemesanan QR Meja & KDS',
                ],
            ],
            [
                'code' => 'PARTNER_MULTI_OUTLET',
                'name' => 'Kemitraan Multi-Cabang',
                'price' => 1000,
                'billing_cycle' => 'PER_TRANSACTION',
                'max_outlets' => 10,
                'max_staff' => 50,
                'features' => [
                    '0 Biaya Langganan Bulanan',
                    'Hingga 10 Cabang Outlet Aktif',
                    'Manajemen Inventaris & Purchase Order',
                    'Rekonsiliasi & Settlement Otomatis',
                ],
            ],
            [
                'code' => 'PARTNER_ENTERPRISE',
                'name' => 'Kemitraan Enterprise & Franchise',
                'price' => 500,
                'billing_cycle' => 'PER_TRANSACTION',
                'max_outlets' => 100,
                'max_staff' => 500,
                'features' => [
                    'Skema Khusus Jaringan Franchise',
                    'Cabang & Staf Ekstra Besar',
                    'Audit Log & Hak Akses Kustom',
                    'Prioritas Settlement Harian',
                ],
            ],
        ];

        foreach ($plans as $plan) {
            PlatformSubscriptionPlan::firstOrCreate(
                ['code' => $plan['code']],
                [
                    'name' => $plan['name'],
                    'price' => $plan['price'],
                    'billing_cycle' => $plan['billing_cycle'],
                    'max_outlets' => $plan['max_outlets'],
                    'max_staff' => $plan['max_staff'],
                    'features' => $plan['features'],
                    'is_active' => true,
                ]
            );
        }
    }
}
