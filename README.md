# POS SaaS Backend (Modular Monolith)

Multi-Tenant Point of Sale (POS) backend platform for coffee shops and F&B businesses, built with **Laravel 11**, **PHP 8+**, and **PostgreSQL**.

---

## 🚀 Fitur & Modul Utama

1. **Authentication & Identity (`App\Modules\Auth`)**: Sanctum token auth, registrasi, login, logout, profil, dan auto-logging audit ke `audit_logs`.
2. **Multi-Tenant & Store Scoping (`App\Modules\Tenant`)**: Profil tenant, manajemen toko/outlet, anggota tim, dan context resolver middleware (`X-Tenant-Id` & `X-Store-Id`).
3. **RBAC Engine (`App\Modules\Rbac`)**: Role `INTERNAL` (Super Admin) & `TENANT` (Owner, Manager, Inventory, Cashier), middleware hak akses `permission` & `role`.
4. **Catalog & Raw Materials (`App\Modules\Catalog`)**: Satuan unit, produk, bahan baku, supplier dengan keunikan SKU per toko.
5. **Recipes & Menus (`App\Modules\RecipeMenu`)**: Formulasi resep produk (HPP), katalog menu per outlet, validasi isolasi lintas toko (*cross-store*).
6. **Purchasing & Inventory (`App\Modules\Inventory`)**: Purchase Orders (PO), penerimaan barang dengan perhitungan *Moving Average Cost*, stok opname, pencatatan wastage, dan buku besar mutasi imutabel (`inventory_movements`).
7. **POS & Table Management (`App\Modules\PosOrder`)**: Meja outlet, status okupansi real-time, rotasi QR token, pesanan kasir, pesanan QR pelanggan (`/api/public/qr/{token}`), dan pemrosesan pembayaran dengan *recipe-based stock depletion*.
8. **Sales Reporting & Dashboard (`App\Modules\Reporting`)**: KPI penjualan, tren harian, produk terlaris, rekap metode bayar, konsumsi bahan baku & margin laba kotor, dan rekap shift kasir.
9. **System Audit & Settings (`App\Modules\System`)**: Append-only audit logs keamanan dan pengaturan hierarkis (`System -> Tenant -> Store`) sesuai Decision #8.

---

## 🛠️ Persyaratan Lingkungan

- **PHP**: 8.2+ (dengan ekstensi `pdo_pgsql`, `pgsql`, `mbstring`, `bcmath`, `ctype`, `json`)
- **PostgreSQL**: 15+ (Database `pos_db`, schema `public`)
- **Composer**: 2.x

---

## ⚙️ Panduan Menjalankan Lokal

1. **Clone & Masuk ke Direktori**:
   ```bash
   cd c:/Users/USER/Documents/Rio/pos-app/pos-be
   ```

2. **Install Dependensi Composer**:
   ```bash
   composer install
   ```

3. **Konfigurasi Environment (`.env`)**:
   Pastikan variabel PostgreSQL diatur dengan benar:
   ```env
   DB_CONNECTION=pgsql
   DB_HOST=127.0.0.1
   DB_PORT=5432
   DB_DATABASE=pos_db
   DB_USERNAME=postgres
   DB_PASSWORD=postgres
   ```

4. **Jalankan Migrasi & Database Seeder**:
   ```bash
   php artisan migrate
   php artisan db:seed --class=RbacSeeder
   ```

5. **Jalankan Server Lokal**:
   ```bash
   php artisan serve
   ```
   API akan aktif di: `http://127.0.0.1:8000/api`

---

## 🧪 Menjalankan Test Suite

Proyek ini dilengkapi dengan 77 feature dan unit test yang mencakup seluruh skenario validasi bisnis:

```bash
php artisan test
```

Semua pengujian menggunakan trait `DatabaseTransactions` sehingga tidak meninggalkan data sampah di database.

---

## 📖 Dokumentasi Lengkap API

Dokumentasi lengkap seluruh 85 endpoint API (Request, Response, Headers, Permission, dan Format JSON) tersedia di:

👉 **[`../docs/api_documentation.md`](../docs/api_documentation.md)**
