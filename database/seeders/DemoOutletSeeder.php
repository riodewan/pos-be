<?php

namespace Database\Seeders;

use App\Core\Enums\CommonStatus;
use App\Core\Enums\OrderSource;
use App\Core\Enums\OrderStatus;
use App\Core\Enums\PaymentMethod;
use App\Core\Enums\PaymentStatus;
use App\Core\Enums\TableStatus;
use App\Modules\Auth\Models\User;
use App\Modules\Auth\Models\UserLoginActivity;
use App\Modules\Catalog\Models\Product;
use App\Modules\Customer\Models\Customer;
use App\Modules\PosOrder\Models\Order;
use App\Modules\PosOrder\Models\OrderItem;
use App\Modules\PosOrder\Models\Payment;
use App\Modules\PosOrder\Models\Reservation;
use App\Modules\PosOrder\Models\Table;
use App\Modules\System\Models\Notification;
use App\Modules\Tenant\Models\Store;
use App\Modules\Tenant\Models\StorePaymentMethod;
use App\Modules\Tenant\Models\StoreTaxFee;
use App\Modules\Tenant\Models\Tenant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DemoOutletSeeder extends Seeder
{
    public function run(): void
    {
        $tenant = Tenant::first();
        if (!$tenant) {
            return;
        }

        $store = Store::where('tenant_id', $tenant->id)->first();
        if (!$store) {
            return;
        }

        // 1. Enhance Store Profile
        $store->update([
            'category' => 'Coffee Shop & Eatery',
            'description' => 'Spesialis kopi arabika Nusantara dengan artisan pastry segar dan suasana cozy untuk bekerja maupun santai.',
            'phone' => '021-7654321',
            'email' => 'senopati@kopinusantara.com',
            'website' => 'https://kopinusantara.com',
            'maps_url' => 'https://maps.google.com/?q=-6.2345,106.8123',
            'logo_url' => 'https://images.unsplash.com/photo-1514432324607-a09d9b4aefdd?w=200&h=200&fit=crop&crop=faces',
            'thumbnail_url' => 'https://images.unsplash.com/photo-1554118811-1e0d58224f24?w=800&auto=format&fit=crop',
            'operational_status' => 'OPEN',
            'publication_status' => 'PUBLISHED',
            'operating_hours' => [
                'monday' => ['open' => '07:00', 'close' => '22:00', 'is_closed' => false],
                'tuesday' => ['open' => '07:00', 'close' => '22:00', 'is_closed' => false],
                'wednesday' => ['open' => '07:00', 'close' => '22:00', 'is_closed' => false],
                'thursday' => ['open' => '07:00', 'close' => '22:00', 'is_closed' => false],
                'friday' => ['open' => '07:00', 'close' => '23:00', 'is_closed' => false],
                'saturday' => ['open' => '08:00', 'close' => '23:00', 'is_closed' => false],
                'sunday' => ['open' => '08:00', 'close' => '22:00', 'is_closed' => false],
            ],
            'receipt_config' => [
                'header_text' => "KOPI NUSANTARA - SENOPATI\nJl. Senopati No. 45, Jakarta Selatan",
                'footer_text' => "Terima kasih atas kunjungan Anda!\nFollow Instagram: @kopinusantara",
                'show_logo' => true,
                'show_social' => true,
                'show_table_number' => true,
                'paper_size' => '80mm',
            ],
            'facilities' => [
                'Wi-Fi Kecepatan Tinggi (100 Mbps)',
                'Colokan Listrik Tiap Meja',
                'Ruang Ber-AC Non-Smoking',
                'Area Merokok Semi-Outdoor',
                'Musholla & Tempat Wudhu',
                'Area Parkir Luas',
            ],
            'photos' => [
                'https://images.unsplash.com/photo-1554118811-1e0d58224f24?w=800',
                'https://images.unsplash.com/photo-1501339847302-ac426a4a7cbb?w=800',
                'https://images.unsplash.com/photo-1442512595331-e89e73853f31?w=800',
            ],
        ]);

        // 2. Enhance User Profile (owner/admin)
        $user = User::where('email', 'owner@kopinusantara.com')->first();
        if ($user) {
            $user->update([
                'avatar_url' => 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?w=200&h=200&fit=crop&crop=face',
                'phone' => '+62 812-3456-7890',
                'job_title' => 'General Manager & Co-Founder',
                'notification_preferences' => [
                    'order_email' => true,
                    'stock_warning' => true,
                    'promo_push' => false,
                    'sales_summary' => true,
                ],
                'user_preferences' => [
                    'theme' => 'light',
                    'language' => 'id',
                    'compact_view' => false,
                ],
            ]);

            // User login activities
            UserLoginActivity::where('user_id', $user->id)->delete();
            UserLoginActivity::create([
                'user_id' => $user->id,
                'status' => 'SUCCESS',
                'title' => 'Login Berhasil',
                'device' => 'MacOS • Chrome 130',
                'ip_address' => '182.253.12.98',
                'location' => 'Jakarta Selatan, Indonesia',
                'logged_at' => now()->subMinutes(15),
            ]);
            UserLoginActivity::create([
                'user_id' => $user->id,
                'status' => 'SUCCESS',
                'title' => 'Login Berhasil',
                'device' => 'iPhone 15 Pro • Safari',
                'ip_address' => '114.122.45.101',
                'location' => 'Jakarta Pusat, Indonesia',
                'logged_at' => now()->subHours(6),
            ]);
            UserLoginActivity::create([
                'user_id' => $user->id,
                'status' => 'SUCCESS',
                'title' => 'Login Berhasil',
                'device' => 'Windows 11 • Edge',
                'ip_address' => '36.85.120.4',
                'location' => 'Depok, Indonesia',
                'logged_at' => now()->subDays(1),
            ]);
        }

        // 3. Customers CRM
        Customer::withTrashed()->where('tenant_id', $tenant->id)->forceDelete();
        $customers = [
            [
                'name' => 'Rian Pratama',
                'phone' => '081298765432',
                'email' => 'rian.pratama@gmail.com',
                'tier' => 'Gold',
                'points' => 1250,
                'total_spent' => 3450000,
                'total_visits' => 24,
                'last_visit_at' => now()->subDays(1),
            ],
            [
                'name' => 'Siti Rahmawati',
                'phone' => '081387654321',
                'email' => 'siti.rahma@yahoo.com',
                'tier' => 'Silver',
                'points' => 540,
                'total_spent' => 1250000,
                'total_visits' => 11,
                'last_visit_at' => now()->subDays(3),
            ],
            [
                'name' => 'Dimas Aditya',
                'phone' => '085711223344',
                'email' => 'dimas.aditya@outlook.com',
                'tier' => 'Bronze',
                'points' => 120,
                'total_spent' => 380000,
                'total_visits' => 3,
                'last_visit_at' => now()->subDays(8),
            ],
            [
                'name' => 'Amanda Putri',
                'phone' => '081122334455',
                'email' => 'amanda.putri@gmail.com',
                'tier' => 'Gold',
                'points' => 2100,
                'total_spent' => 5890000,
                'total_visits' => 38,
                'last_visit_at' => now()->subHours(4),
            ],
            [
                'name' => 'Budi Santoso',
                'phone' => '081999888777',
                'email' => 'budi.santoso@gmail.com',
                'tier' => 'Silver',
                'points' => 450,
                'total_spent' => 980000,
                'total_visits' => 8,
                'last_visit_at' => now()->subDays(2),
            ],
        ];

        foreach ($customers as $c) {
            Customer::create(array_merge($c, ['tenant_id' => $tenant->id]));
        }

        // 4. Taxes & Fees
        StoreTaxFee::withTrashed()->where('store_id', $store->id)->forceDelete();
        StoreTaxFee::create([
            'store_id' => $store->id,
            'name' => 'PB1 / Pajak Restoran',
            'type' => 'Pajak',
            'value_type' => 'percent',
            'value' => '10%',
            'applies_to' => 'Semua Menu',
            'is_active' => true,
        ]);
        StoreTaxFee::create([
            'store_id' => $store->id,
            'name' => 'Biaya Layanan (Service Charge)',
            'type' => 'Biaya',
            'value_type' => 'percent',
            'value' => '5%',
            'applies_to' => 'Dine-in, Takeway',
            'is_active' => true,
        ]);
        StoreTaxFee::create([
            'store_id' => $store->id,
            'name' => 'Biaya Kemasan Eco-Takeaway',
            'type' => 'Biaya',
            'value_type' => 'fixed',
            'value' => 'Rp2.000',
            'applies_to' => 'Takeaway & Delivery',
            'is_active' => true,
        ]);

        // 5. Payment Methods
        StorePaymentMethod::withTrashed()->where('store_id', $store->id)->forceDelete();
        StorePaymentMethod::create([
            'store_id' => $store->id,
            'name' => 'QRIS Dinamis (GoPay, OVO, ShopeePay)',
            'type' => 'E - Wallet',
            'description' => 'Scan QRIS langsung dari kasir atau struk meja, real-time terverifikasi',
            'icon_type' => 'qris',
            'is_active' => true,
            'sort_order' => 1,
        ]);
        StorePaymentMethod::create([
            'store_id' => $store->id,
            'name' => 'Tunai / Cash',
            'type' => 'Tunai',
            'description' => 'Pembayaran tunai di meja kasir dengan uang pas atau kembalian',
            'icon_type' => 'cash',
            'is_active' => true,
            'sort_order' => 2,
        ]);
        StorePaymentMethod::create([
            'store_id' => $store->id,
            'name' => 'Kartu Debit & Kredit (EDC)',
            'type' => 'Kartu',
            'description' => 'Mendukung Visa, Mastercard, GPN BCA, Mandiri, BRI',
            'icon_type' => 'card',
            'is_active' => true,
            'sort_order' => 3,
        ]);
        StorePaymentMethod::create([
            'store_id' => $store->id,
            'name' => 'Transfer Bank Virtual Account',
            'type' => 'Transfer',
            'description' => 'BCA, Mandiri, BNI, BRI Virtual Account',
            'icon_type' => 'bank',
            'is_active' => true,
            'sort_order' => 4,
        ]);

        // 6. Tables & Reservations
        $tables = Table::where('store_id', $store->id)->get();
        if ($tables->isEmpty()) {
            $tables = collect([
                Table::create(['store_id' => $store->id, 'name' => 'Meja 01', 'code' => 'T-01', 'capacity' => 2, 'status' => TableStatus::ACTIVE]),
                Table::create(['store_id' => $store->id, 'name' => 'Meja 02', 'code' => 'T-02', 'capacity' => 4, 'status' => TableStatus::ACTIVE]),
                Table::create(['store_id' => $store->id, 'name' => 'Meja 03', 'code' => 'T-03', 'capacity' => 4, 'status' => TableStatus::ACTIVE]),
                Table::create(['store_id' => $store->id, 'name' => 'Meja 04', 'code' => 'T-04', 'capacity' => 6, 'status' => TableStatus::ACTIVE]),
                Table::create(['store_id' => $store->id, 'name' => 'Meja 05', 'code' => 'T-05', 'capacity' => 2, 'status' => TableStatus::ACTIVE]),
            ]);
        }

        Reservation::withTrashed()->where('store_id', $store->id)->forceDelete();
        $tableList = $tables->values();

        Reservation::create([
            'tenant_id' => $tenant->id,
            'store_id' => $store->id,
            'table_id' => $tableList->get(1)?->id,
            'reservation_number' => 'RSV-' . date('Ymd') . '-001',
            'customer_name' => 'Budi Santoso',
            'customer_phone' => '081999888777',
            'customer_email' => 'budi.santoso@gmail.com',
            'guest_count' => 4,
            'reservation_date' => now()->toDateString(),
            'reservation_time' => '18:30',
            'status' => 'Dikonfirmasi',
            'notes' => 'Dekat stopkontak, membawa kue ulang tahun',
        ]);
        Reservation::create([
            'tenant_id' => $tenant->id,
            'store_id' => $store->id,
            'table_id' => $tableList->get(3)?->id,
            'reservation_number' => 'RSV-' . date('Ymd') . '-002',
            'customer_name' => 'Sarah Jenkins',
            'customer_phone' => '08129990001',
            'customer_email' => 'sarah.j@gmail.com',
            'guest_count' => 2,
            'reservation_date' => now()->toDateString(),
            'reservation_time' => '19:00',
            'status' => 'Menunggu',
            'notes' => 'Romantic anniversary dinner',
        ]);
        Reservation::create([
            'tenant_id' => $tenant->id,
            'store_id' => $store->id,
            'table_id' => $tableList->get(2)?->id,
            'reservation_number' => 'RSV-' . date('Ymd') . '-003',
            'customer_name' => 'Doni Kurniawan',
            'customer_phone' => '085611223344',
            'customer_email' => 'doni.k@corporate.id',
            'guest_count' => 6,
            'reservation_date' => now()->toDateString(),
            'reservation_time' => '20:00',
            'status' => 'Dikonfirmasi',
            'notes' => 'Reuni kantor divisi marketing',
        ]);

        // 7. Notifications
        Notification::where('tenant_id', $tenant->id)->delete();
        Notification::create([
            'tenant_id' => $tenant->id,
            'store_id' => $store->id,
            'title' => 'Stok Biji Kopi Rendah',
            'subtitle' => 'Biji Kopi Gayo tinggal 1.2 kg di bawah batas minimum 2 kg.',
            'category' => 'Inventori',
            'is_read' => false,
            'target_url' => '/dashboard/inventory',
        ]);
        Notification::create([
            'tenant_id' => $tenant->id,
            'store_id' => $store->id,
            'title' => 'Pesanan Baru Masuk #ORD-1092',
            'subtitle' => 'Dine-in Meja 02 telah mengirimkan pesanan lewat QR Order.',
            'category' => 'Pesanan',
            'is_read' => false,
            'target_url' => '/dashboard/kds',
        ]);
        Notification::create([
            'tenant_id' => $tenant->id,
            'store_id' => $store->id,
            'title' => 'Reservasi Baru Menunggu Konfirmasi',
            'subtitle' => 'Sarah Jenkins memesan Meja 04 untuk jam 19:00 malam ini.',
            'category' => 'Reservasi',
            'is_read' => false,
            'target_url' => '/dashboard/table-booking',
        ]);
        Notification::create([
            'tenant_id' => $tenant->id,
            'store_id' => $store->id,
            'title' => 'Penutupan Kasir Berhasil',
            'subtitle' => 'Shift Kasir Siang telah dicatat dan direkonsiliasi tanpa selisih.',
            'category' => 'Sistem',
            'is_read' => true,
            'target_url' => '/dashboard/finance',
        ]);

        // 8. Orders & Payments for KDS & Finance
        $products = Product::where('store_id', $store->id)->get();
        if ($products->isNotEmpty()) {
            // Clean old demo orders
            Order::where('store_id', $store->id)->delete();

            // Order 1: PREPARING (in KDS)
            $order1 = Order::create([
                'store_id' => $store->id,
                'table_id' => $tableList->first()?->id,
                'order_number' => 'ORD-' . date('ymd') . '-01',
                'source' => OrderSource::POS,
                'channel' => 'DINE_IN',
                'customer_name' => 'Dimas Aditya',
                'status' => OrderStatus::PREPARING,
                'subtotal' => 65000,
                'tax_amount' => 6500,
                'service_charge' => 3250,
                'total_amount' => 74750,
                'notes' => 'Less sugar untuk latte',
                'created_at' => now()->subMinutes(12),
            ]);
            OrderItem::create([
                'order_id' => $order1->id,
                'product_id' => $products->first()->id,
                'product_name' => $products->first()->name,
                'quantity' => 2,
                'unit_price' => 28000,
                'subtotal' => 56000,
                'notes' => 'Less ice',
            ]);

            // Order 2: READY (in KDS)
            $order2 = Order::create([
                'store_id' => $store->id,
                'table_id' => $tableList->get(1)?->id,
                'order_number' => 'ORD-' . date('ymd') . '-02',
                'source' => OrderSource::POS,
                'channel' => 'DINE_IN',
                'customer_name' => 'Rian Pratama',
                'status' => OrderStatus::READY,
                'subtotal' => 95000,
                'tax_amount' => 9500,
                'service_charge' => 4750,
                'total_amount' => 109250,
                'notes' => 'Dine in Meja 02',
                'created_at' => now()->subMinutes(25),
            ]);
            OrderItem::create([
                'order_id' => $order2->id,
                'product_id' => $products->first()->id,
                'product_name' => $products->first()->name,
                'quantity' => 1,
                'unit_price' => 35000,
                'subtotal' => 35000,
            ]);

            // Order 3: COMPLETED with Payment (for Finance Reconciliation)
            $order3 = Order::create([
                'store_id' => $store->id,
                'table_id' => $tableList->get(2)?->id,
                'order_number' => 'ORD-' . date('ymd') . '-03',
                'source' => OrderSource::POS,
                'channel' => 'DINE_IN',
                'customer_name' => 'Amanda Putri',
                'status' => OrderStatus::COMPLETED,
                'subtotal' => 150000,
                'tax_amount' => 15000,
                'service_charge' => 7500,
                'total_amount' => 172500,
                'created_at' => now()->subHours(2),
            ]);
            Payment::create([
                'order_id' => $order3->id,
                'payment_number' => 'PAY-' . date('ymd') . '-001',
                'method' => PaymentMethod::QRIS,
                'provider' => 'QRIS Dinamis',
                'amount' => 172500,
                'mdr_fee' => 1208, // ~0.7% MDR
                'net_amount' => 171292,
                'settlement_status' => 'SIAP_CAIR',
                'status' => PaymentStatus::SUCCESS,
                'paid_at' => now()->subHours(2),
            ]);

            // Order 4: COMPLETED with Cash
            $order4 = Order::create([
                'store_id' => $store->id,
                'table_id' => null,
                'order_number' => 'ORD-' . date('ymd') . '-04',
                'source' => OrderSource::POS,
                'channel' => 'TAKEAWAY',
                'customer_name' => 'Budi Santoso',
                'status' => OrderStatus::COMPLETED,
                'subtotal' => 78000,
                'tax_amount' => 7800,
                'service_charge' => 0,
                'total_amount' => 85800,
                'created_at' => now()->subHours(3),
            ]);
            Payment::create([
                'order_id' => $order4->id,
                'payment_number' => 'PAY-' . date('ymd') . '-002',
                'method' => PaymentMethod::CASH,
                'provider' => 'Kasir Tunai',
                'amount' => 85800,
                'mdr_fee' => 0,
                'net_amount' => 85800,
                'settlement_status' => 'SELESAI',
                'status' => PaymentStatus::SUCCESS,
                'paid_at' => now()->subHours(3),
            ]);
        }

        // 9. Disbursements & Cash Shift Audits
        $cashierUser = User::where('email', 'kasir@kopinusantara.com')->first();
        DB::table('cash_shift_audits')->insertOrIgnore([
            [
                'id' => (string) Str::ulid(),
                'store_id' => $store->id,
                'cashier_id' => $cashierUser?->id,
                'shift_name' => 'Shift Pagi (08:00 - 16:00)',
                'shift_date' => now()->toDateString(),
                'opening_cash' => 200000,
                'actual_cash' => 285800,
                'notes' => 'Shift pagi selesai tanpa selisih kas fisik.',
                'created_at' => now()->subHours(1),
                'updated_at' => now()->subHours(1),
            ],
        ]);

        DB::table('disbursements')->insertOrIgnore([
            [
                'id' => (string) Str::ulid(),
                'store_id' => $store->id,
                'batch_id' => 'WD-' . date('Ymd') . '-001',
                'bank_name' => 'BCA',
                'account_number' => '5420192837',
                'gross_amount' => 1500000,
                'fee' => 0,
                'net_received' => 1500000,
                'status' => 'Berhasil',
                'requested_by' => $user?->id,
                'requested_at' => now()->subDays(1),
                'created_at' => now()->subDays(1),
                'updated_at' => now()->subDays(1),
            ],
        ]);

        // 10. Suppliers & Purchase Orders
        $supplier1 = \App\Modules\Catalog\Models\Supplier::firstOrCreate(
            ['store_id' => $store->id, 'code' => 'SUP-GYO-01'],
            [
                'name' => 'PT Kopi Gayo Mandiri',
                'contact_person' => 'Irfan Syahputra',
                'phone' => '0812-7788-9900',
                'email' => 'sales@gayomandiri.co.id',
                'address' => 'Jl. Takengon No. 88, Aceh Tengah',
                'status' => CommonStatus::ACTIVE,
            ]
        );

        $supplier2 = \App\Modules\Catalog\Models\Supplier::firstOrCreate(
            ['store_id' => $store->id, 'code' => 'SUP-MLK-02'],
            [
                'name' => 'CV Susu Segar Sejahtera',
                'contact_person' => 'Dewi Anggraini',
                'phone' => '0813-1122-3344',
                'email' => 'order@sususejahtera.id',
                'address' => 'Kawasan Industri Sentul, Bogor',
                'status' => CommonStatus::ACTIVE,
            ]
        );

        $purchaseUser = User::where('email', 'inventory@kopinusantara.com')->first() ?? $user;
        $ingredientEspresso = \App\Modules\Catalog\Models\Ingredient::where('store_id', $store->id)
            ->where('sku', 'ING-ESP-01')
            ->first();
        $unitKg = \App\Modules\Catalog\Models\Unit::where('code', 'KG')->first() 
            ?? \App\Modules\Catalog\Models\Unit::first();

        if ($supplier1 && $ingredientEspresso && $unitKg) {
            $purchase = \App\Modules\Inventory\Models\Purchase::firstOrCreate(
                ['store_id' => $store->id, 'purchase_number' => 'PO-' . date('Ym') . '-0001'],
                [
                    'supplier_id' => $supplier1->id,
                    'purchase_date' => now()->subDays(2)->toDateString(),
                    'status' => \App\Core\Enums\PurchaseStatus::RECEIVED,
                    'total_amount' => 1100000,
                    'notes' => 'Pengadaan biji kopi espresso blend 5 kg restock awal bulan',
                    'created_by' => $purchaseUser?->id,
                ]
            );

            \App\Modules\Inventory\Models\PurchaseItem::firstOrCreate(
                [
                    'purchase_id' => $purchase->id,
                    'ingredient_id' => $ingredientEspresso->id,
                ],
                [
                    'id' => (string) Str::ulid(),
                    'quantity' => 5.0000,
                    'unit_id' => $unitKg->id,
                    'unit_cost' => 220000,
                    'total_cost' => 1100000,
                ]
            );
        }
    }
}
