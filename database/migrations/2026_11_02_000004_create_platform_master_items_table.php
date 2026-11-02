<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('platform_master_items')) {
            Schema::create('platform_master_items', function (Blueprint $table) {
                $table->ulid('id')->primary();
                $table->string('group', 50)->index(); // FACILITY, BUSINESS_TYPE, PAYMENT_METHOD, TAX_FEE, BANK
                $table->string('name', 150);
                $table->string('code', 100)->nullable();
                $table->string('icon', 50)->nullable();
                $table->text('description')->nullable();
                $table->jsonb('metadata')->nullable();
                $table->boolean('is_active')->default(true);
                $table->integer('sort_order')->default(0);
                $table->timestampsTz(6);
            });
        }

        $now = now();

        if (DB::table('platform_master_items')->count() === 0) {
            $items = [
                // 1. FACILITY (Fasilitas Outlet Global)
                ['group' => 'FACILITY', 'name' => 'WiFi Gratis', 'code' => 'wifi', 'icon' => 'Wifi', 'description' => 'Koneksi internet nirkabel untuk pengunjung', 'sort_order' => 1],
                ['group' => 'FACILITY', 'name' => 'Ruangan Ber-AC', 'code' => 'ac', 'icon' => 'Wind', 'description' => 'Ruangan sejuk dengan pendingin udara', 'sort_order' => 2],
                ['group' => 'FACILITY', 'name' => 'Area Indoor', 'code' => 'indoor', 'icon' => 'Store', 'description' => 'Area makan di dalam ruangan', 'sort_order' => 3],
                ['group' => 'FACILITY', 'name' => 'Area Outdoor', 'code' => 'outdoor', 'icon' => 'Sun', 'description' => 'Area makan terbuka di luar ruangan', 'sort_order' => 4],
                ['group' => 'FACILITY', 'name' => 'Parkir Mobil & Motor', 'code' => 'parking', 'icon' => 'Car', 'description' => 'Area parkir kendaraan pelanggan', 'sort_order' => 5],
                ['group' => 'FACILITY', 'name' => 'Toilet', 'code' => 'toilet', 'icon' => 'Accessibility', 'description' => 'Fasilitas kamar kecil pelanggan', 'sort_order' => 6],
                ['group' => 'FACILITY', 'name' => 'Mushola', 'code' => 'mushola', 'icon' => 'Sparkles', 'description' => 'Tempat ibadah untuk pengunjung', 'sort_order' => 7],
                ['group' => 'FACILITY', 'name' => 'Stop Kontak', 'code' => 'power_outlet', 'icon' => 'Zap', 'description' => 'Colokan listrik di area meja', 'sort_order' => 8],
                ['group' => 'FACILITY', 'name' => 'Smoking Area', 'code' => 'smoking_area', 'icon' => 'Flame', 'description' => 'Area khusus merokok', 'sort_order' => 9],
                ['group' => 'FACILITY', 'name' => 'Akses Kursi Roda', 'code' => 'wheelchair', 'icon' => 'Accessibility', 'description' => 'Ramah pengguna kursi roda / disabilitas', 'sort_order' => 10],
                ['group' => 'FACILITY', 'name' => 'Live Music', 'code' => 'live_music', 'icon' => 'Music', 'description' => 'Hiburan musik langsung berkala', 'sort_order' => 11],
                ['group' => 'FACILITY', 'name' => 'VIP Room', 'code' => 'vip_room', 'icon' => 'Crown', 'description' => 'Ruang privat untuk reservasi / rapat', 'sort_order' => 12],

                // 2. BUSINESS_TYPE (Jenis Usaha / Badan Hukum Global)
                ['group' => 'BUSINESS_TYPE', 'name' => 'Perorangan', 'code' => 'PERORANGAN', 'icon' => 'User', 'description' => 'Usaha milik pribadi / UMKM perorangan', 'sort_order' => 1],
                ['group' => 'BUSINESS_TYPE', 'name' => 'PT (Perseroan Terbatas)', 'code' => 'PT', 'icon' => 'Building2', 'description' => 'Badan hukum Perseroan Terbatas', 'sort_order' => 2],
                ['group' => 'BUSINESS_TYPE', 'name' => 'CV (Commanditaire Vennootschap)', 'code' => 'CV', 'icon' => 'Briefcase', 'description' => 'Persekutuan Komanditer', 'sort_order' => 3],
                ['group' => 'BUSINESS_TYPE', 'name' => 'Firma', 'code' => 'FIRMA', 'icon' => 'Users', 'description' => 'Persekutuan Firma', 'sort_order' => 4],
                ['group' => 'BUSINESS_TYPE', 'name' => 'Koperasi', 'code' => 'KOPERASI', 'icon' => 'Handshake', 'description' => 'Badan usaha Koperasi', 'sort_order' => 5],

                // 3. PAYMENT_METHOD (Template Metode Pembayaran Global)
                ['group' => 'PAYMENT_METHOD', 'name' => 'Tunai (Cash)', 'code' => 'CASH', 'icon' => 'Banknote', 'description' => 'Pembayaran uang tunai di kasir', 'metadata' => json_encode(['category' => 'CASH', 'fee_percent' => 0]), 'sort_order' => 1],
                ['group' => 'PAYMENT_METHOD', 'name' => 'QRIS Dinamis / Statis', 'code' => 'QRIS', 'icon' => 'QrCode', 'description' => 'Standar QR Nasional semua e-wallet & m-banking', 'metadata' => json_encode(['category' => 'QRIS', 'fee_percent' => 0.7]), 'sort_order' => 2],
                ['group' => 'PAYMENT_METHOD', 'name' => 'Kartu Debit / Kredit (EDC)', 'code' => 'EDC', 'icon' => 'CreditCard', 'description' => 'Mesin EDC Bank (Visa / Mastercard / GPN)', 'metadata' => json_encode(['category' => 'CARD', 'fee_percent' => 1.5]), 'sort_order' => 3],
                ['group' => 'PAYMENT_METHOD', 'name' => 'Transfer Bank / Virtual Account', 'code' => 'BANK_TRANSFER', 'icon' => 'Landmark', 'description' => 'Transfer antar bank atau nomor VA', 'metadata' => json_encode(['category' => 'TRANSFER', 'fee_percent' => 0]), 'sort_order' => 4],
                ['group' => 'PAYMENT_METHOD', 'name' => 'GoPay / OVO / Dana / ShopeePay', 'code' => 'EWALLET', 'icon' => 'Wallet', 'description' => 'Dompet digital populer di Indonesia', 'metadata' => json_encode(['category' => 'EWALLET', 'fee_percent' => 1.5]), 'sort_order' => 5],

                // 4. TAX_FEE (Template Pajak & Biaya Layanan Global)
                ['group' => 'TAX_FEE', 'name' => 'PB1 / Pajak Restoran (10%)', 'code' => 'PB1_10', 'icon' => 'Percent', 'description' => 'Pajak Pembangunan 1 daerah untuk restoran & kafe', 'metadata' => json_encode(['type' => 'TAX', 'value_type' => 'PERCENTAGE', 'value' => 10]), 'sort_order' => 1],
                ['group' => 'TAX_FEE', 'name' => 'PPN (11%)', 'code' => 'PPN_11', 'icon' => 'Percent', 'description' => 'Pajak Pertambahan Nilai nasional', 'metadata' => json_encode(['type' => 'TAX', 'value_type' => 'PERCENTAGE', 'value' => 11]), 'sort_order' => 2],
                ['group' => 'TAX_FEE', 'name' => 'Service Charge (5%)', 'code' => 'SERVICE_5', 'icon' => 'HandCoins', 'description' => 'Biaya layanan standar restoran / hospitality', 'metadata' => json_encode(['type' => 'FEE', 'value_type' => 'PERCENTAGE', 'value' => 5]), 'sort_order' => 3],
                ['group' => 'TAX_FEE', 'name' => 'Biaya Kemasan / Takeaway', 'code' => 'PACKAGING_FEE', 'icon' => 'Package', 'description' => 'Biaya tambahan kemasan pesanan bungkus', 'metadata' => json_encode(['type' => 'FEE', 'value_type' => 'FIXED', 'value' => 2000]), 'sort_order' => 4],

                // 5. BANK (Daftar Bank Pencairan Dana Global)
                ['group' => 'BANK', 'name' => 'Bank Central Asia (BCA)', 'code' => 'BCA', 'icon' => 'Landmark', 'description' => 'Kliring / Transfer Real-time BCA', 'sort_order' => 1],
                ['group' => 'BANK', 'name' => 'Bank Mandiri', 'code' => 'MANDIRI', 'icon' => 'Landmark', 'description' => 'Kliring / Transfer Bank Mandiri', 'sort_order' => 2],
                ['group' => 'BANK', 'name' => 'Bank Rakyat Indonesia (BRI)', 'code' => 'BRI', 'icon' => 'Landmark', 'description' => 'Kliring / Transfer Bank BRI', 'sort_order' => 3],
                ['group' => 'BANK', 'name' => 'Bank Negara Indonesia (BNI)', 'code' => 'BNI', 'icon' => 'Landmark', 'description' => 'Kliring / Transfer Bank BNI', 'sort_order' => 4],
                ['group' => 'BANK', 'name' => 'Bank Syariah Indonesia (BSI)', 'code' => 'BSI', 'icon' => 'Landmark', 'description' => 'Kliring / Transfer Bank BSI', 'sort_order' => 5],
                ['group' => 'BANK', 'name' => 'CIMB Niaga', 'code' => 'CIMB', 'icon' => 'Landmark', 'description' => 'Kliring / Transfer CIMB Niaga', 'sort_order' => 6],
            ];

            foreach ($items as $item) {
                DB::table('platform_master_items')->insert([
                    'id' => (string) Str::ulid(),
                    'group' => $item['group'],
                    'name' => $item['name'],
                    'code' => $item['code'],
                    'icon' => $item['icon'] ?? null,
                    'description' => $item['description'] ?? null,
                    'metadata' => $item['metadata'] ?? null,
                    'is_active' => true,
                    'sort_order' => $item['sort_order'] ?? 0,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        // Ensure default business categories exist in platform_master_categories if empty
        if (Schema::hasTable('platform_master_categories') && DB::table('platform_master_categories')->count() === 0) {
            $categories = [
                ['name' => 'Coffee Shop & Cafe', 'slug' => 'coffee-shop-cafe', 'description' => 'Kedai kopi, teh, dan tempat nongkrong kasual', 'icon' => 'Coffee', 'sort_order' => 1],
                ['name' => 'Restoran & Rumah Makan', 'slug' => 'restoran-rumah-makan', 'description' => 'Restoran keluarga, fine dining, dan rumah makan', 'icon' => 'Utensils', 'sort_order' => 2],
                ['name' => 'Bakery & Pastry', 'slug' => 'bakery-pastry', 'description' => 'Toko roti, kue, dan dessert', 'icon' => 'Croissant', 'sort_order' => 3],
                ['name' => 'Fast Food & Cloud Kitchen', 'slug' => 'fast-food-cloud-kitchen', 'description' => 'Makanan cepat saji dan dapur pesan-antar', 'icon' => 'Flame', 'sort_order' => 4],
                ['name' => 'Retail & Minimarket', 'slug' => 'retail-minimarket', 'description' => 'Toko kelontong, minimarket, dan ritel harian', 'icon' => 'ShoppingBag', 'sort_order' => 5],
                ['name' => 'Bar & Lounge', 'slug' => 'bar-lounge', 'description' => 'Tempat hiburan malam dan minuman spesial', 'icon' => 'Wine', 'sort_order' => 6],
            ];

            foreach ($categories as $cat) {
                DB::table('platform_master_categories')->insert([
                    'id' => (string) Str::ulid(),
                    'name' => $cat['name'],
                    'slug' => $cat['slug'],
                    'description' => $cat['description'],
                    'icon' => $cat['icon'],
                    'is_active' => true,
                    'sort_order' => $cat['sort_order'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        // Ensure global measurement units exist in units if empty
        if (Schema::hasTable('units') && DB::table('units')->count() === 0) {
            $units = [
                ['name' => 'Kilogram', 'code' => 'kg', 'type' => 'WEIGHT'],
                ['name' => 'Gram', 'code' => 'g', 'type' => 'WEIGHT'],
                ['name' => 'Liter', 'code' => 'l', 'type' => 'VOLUME'],
                ['name' => 'Mililiter', 'code' => 'ml', 'type' => 'VOLUME'],
                ['name' => 'Pieces', 'code' => 'pcs', 'type' => 'COUNT'],
                ['name' => 'Pack', 'code' => 'pack', 'type' => 'COUNT'],
                ['name' => 'Porsi', 'code' => 'porsi', 'type' => 'COUNT'],
            ];

            foreach ($units as $unit) {
                DB::table('units')->insert([
                    'id' => (string) Str::ulid(),
                    'name' => $unit['name'],
                    'code' => $unit['code'],
                    'type' => $unit['type'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_master_items');
    }
};
