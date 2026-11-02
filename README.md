# NemuHUB / NemuPOS Backend (Laravel Modular Monolith)

Platform Backend Multi-Tenant Point of Sale (POS) & Manajemen Restoran/F&B berbasis **Laravel 12**, **PHP 8.2+**, dan **PostgreSQL**.

---

## 🌟 Fitur & Arsitektur Modul

Backend dibangun dengan arsitektur **Modular Monolith** yang memisahkan tanggung jawab bisnis per domain:

| Modul | Namespace | Deskripsi |
|---|---|---|
| **Auth & Security** | `App\Modules\Auth` | Otentikasi Sanctum, Profil Akun, Ganti Password, Histori Login, Audit Keamanan |
| **Tenant & Store** | `App\Modules\Tenant` | Isolasi Multi-Tenant, Cabang Outlet, Data Master Global, Approval Super Admin |
| **RBAC Engine** | `App\Modules\Rbac` | Manajemen Peran & Izin Granular (`INTERNAL` Super Admin & `TENANT` Mitra) |
| **Catalog & Products** | `App\Modules\Catalog` | Manajemen Produk, Bahan Baku, Pemasok (Suppliers), Satuan Unit (Units) |
| **Recipe & Menus** | `App\Modules\RecipeMenu` | Formulasi Resep, HPP Otomatis, Katalog Menu per Cabang |
| **Inventory & PO** | `App\Modules\Inventory` | Purchase Order (PO), Mutasi Stok Imutabel, Penyesuaian/Opname, Wastage |
| **POS & Orders** | `App\Modules\PosOrder` | Kasir Kilat, Meja & Rotasi QR Meja, Kitchen Display (KDS), Auto Recipe Depletion |
| **Finance & Reports** | `App\Modules\Reporting` | Omset Bersih, Rekonsiliasi, Penarikan Dana (Disbursement), Audit Kas Shift |
| **System & Notifications**| `App\Modules\System` | Pusat Notifikasi Interaktif, Audit Trail Log, Pengaturan Hierarkis |

---

## 🔑 Akun Pengujian Default (Seeded Test Accounts)

Jalankan seeder untuk mendapatkan data pengujian lengkap:

| Peran (Role) | Email | Password | Akses & Tanggung Jawab |
|---|---|---|---|
| **Super Admin Platform** | `admin@pos.com` | `Password123!` | Panel Admin Internal, Approval Cabang & Paket |
| **Mitra Owner** | `owner@kopinusantara.com` | `Password123!` | Akses Penuh Bisnis Kopi Nusantara |
| **Store Manager** | `manager@kopinusantara.com` | `Password123!` | Operasional Toko Outlet Senopati |
| **Kasir (Head Cashier)** | `kasir@kopinusantara.com` | `Password123!` | Terminal Kasir (POS) & Tutup Kasir Shift |
| **Inventory / Gudang** | `inventory@kopinusantara.com` | `Password123!` | Stok Bahan Baku, Mutasi, dan Purchase Orders |
| **Dapur / Barista** | `dapur@kopinusantara.com` | `Password123!` | Layar Kitchen Display System (KDS) |

---

## ⚙️ Panduan Menjalankan Lokal

### 1. Prasyarat Sistem
* **PHP**: 8.2 atau lebih baru (ekstensi: `pdo_pgsql`, `pgsql`, `mbstring`, `bcmath`, `ctype`, `json`, `curl`)
* **PostgreSQL**: 15 atau lebih baru
* **Composer**: 2.x

### 2. Instalasi Dependensi
```bash
cd pos-be
composer install
```

### 3. Konfigurasi Environment (`.env`)
Salin `.env.example` ke `.env` lalu sesuaikan kredensial database PostgreSQL:
```env
APP_NAME=NemuHUB
APP_ENV=local
APP_KEY=base64:...
APP_DEBUG=true
APP_URL=http://127.0.0.1:8000

DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=pos_db
DB_USERNAME=postgres
DB_PASSWORD=postgres
```

### 4. Migrasi & Seeder Bersih (Fresh Seed)
Eksekusi migrasi dari awal beserta data seeder resmi:
```bash
php artisan migrate:fresh --seed
```

### 5. Jalankan Server API
```bash
php artisan serve
```
API endpoint aktif di: `http://127.0.0.1:8000/api`

---

## 📧 Template Email & Live Preview (Tanpa SMTP)

Tersedia rute pratinjau (*live preview*) di browser untuk 7 template email responsif:

* Buka: **`http://localhost:8000/mail-preview`**
  * `mail-preview/reset-password`: Atur Ulang Kata Sandi
  * `mail-preview/staff-invitation`: Undangan Login Staf Baru
  * `mail-preview/disbursement-status`: Status Pencairan Dana (Payout) Berhasil
  * `mail-preview/shift-closing`: Rekap Penutupan Shift Kasir (Audit Kas)
  * `mail-preview/daily-sales`: Laporan Penjualan Harian Toko (Daily Sales Digest)
  * `mail-preview/low-stock`: Peringatan Stok Bahan Baku Menipis
  * `mail-preview/approval-notice`: Status Persetujuan Cabang / Verifikasi

*Saat Anda sudah memiliki SMTP di production, cukup isi kredensial `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD` di `.env`.*

---

## 🚀 Panduan Kesiapan Production

Sebelum melakukan deployment ke server production:
1. Pastikan **`APP_ENV=production`** dan **`APP_DEBUG=false`** pada `.env`.
2. Atur **`CORS_ALLOWED_ORIGINS`** dan **`SANCTUM_STATEFUL_DOMAINS`** sesuai domain frontend Anda.
3. Jalankan optimasi cache Laravel:
   ```bash
   php artisan config:cache
   php artisan route:cache
   php artisan view:cache
   ```
4. Pasang backup otomatis database harian via PostgreSQL `pg_dump`.

---

## 📖 Dokumentasi Endpoint API
Dokumentasi teknis parameter request, response, dan headers:
👉 **[`../docs/api_documentation.md`](../docs/api_documentation.md)**
