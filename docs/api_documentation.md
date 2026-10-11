# POS Multi-Tenant SaaS Platform — API Documentation

> **Base URL**: `http://127.0.0.1:8000/api` (Local Dev)  
> **Route Policy**: Tanpa prefix `v1` (contoh: `/api/auth/*`, `/api/tenant/*`, `/api/catalog/*`, `/api/pos/*`, `/api/reports/*`).  
> **Authentication**: Bearer Token via Laravel Sanctum.  
> **Primary Key Standard**: ULID 26 karakter (`CHAR(26)`).  
> **Monetary Standard**: `NUMERIC(15,2)`  
> **Quantity Standard**: `NUMERIC(15,4)`  
> **Database Architecture**: Single Database (`pos_db`), Single Schema (`public`), Shared Tables terisolasi via `tenant_id` dan `store_id`.

---

## 1. Context Headers & Multi-Tenancy

Setiap request yang mengakses data operasional wajib menyertakan header konteks berikut:

| Header | Wajib Pada | Deskripsi |
| :--- | :--- | :--- |
| `Authorization` | Semua rute terproteksi | `Bearer <sanctum_token>` |
| `X-Tenant-Id` | `/api/tenant/*`, `/api/system/*`, & semua rute toko | ULID dari tenant aktif pengguna. |
| `X-Store-Id` | `/api/catalog/*`, `/api/recipes/*`, `/api/menus/*`, `/api/inventory/*`, `/api/pos/*`, `/api/reports/*` | ULID dari outlet/store aktif. |
| `X-Store-Code` | Alternatif `X-Store-Id` | Kode unik outlet (contoh: `SCBD-01`). |

> **Catatan**: Rute `/api/public/qr/*` adalah endpoint publik untuk pelanggan scan meja dan **tidak memerlukan header autentikasi maupun context** (toko dan meja diresolusi otomatis dari token QR).

---

## 2. RBAC & Hak Akses (Permissions Matrix)

| Role Default | Scope | Deskripsi Cakupan |
| :--- | :--- | :--- |
| **Super Admin** | `INTERNAL` | Administrator platform SaaS dengan akses tak terbatas ke seluruh tenant dan store. |
| **Owner** | `TENANT` | Pemilik bisnis tenant dengan akses penuh ke seluruh modul, toko, tim, dan keuangan. |
| **Manager** | `TENANT` | Manajer operasional toko/tenant (seluruh modul kecuali hapus tenant dan billing). |
| **Inventory** | `TENANT` | Staf logistik & gudang (pembelian, stok opname, mutasi, wastage, resep, supplier). |
| **Cashier** | `TENANT` | Kasir kasir POS (pesanan kasir, meja, QR meja, pembayaran, katalog produk). |

---

## 3. Modul 1: Authentication (`/api/auth`)

### 3.1 Register User
- **Method**: `POST`
- **URL**: `/api/auth/register`
- **Auth**: None
- **Request Body**:
  ```json
  {
    "name": "Budi Santoso",
    "email": "budi@example.com",
    "password": "Password123!",
    "password_confirmation": "Password123!"
  }
  ```
- **Response** (`201 Created`):
  ```json
  {
    "success": true,
    "message": "Registration successful",
    "data": {
      "user": {
        "id": "01JM4MHYHRCYPBTEFP9NRKRES1",
        "name": "Budi Santoso",
        "email": "budi@example.com",
        "is_internal": false,
        "is_active": true
      },
      "token": "1|sanctum_token_string_here",
      "token_type": "Bearer"
    }
  }
  ```

### 3.2 Login
- **Method**: `POST`
- **URL**: `/api/auth/login`
- **Auth**: None (Mencatat event audit `LOGIN` otomatis)
- **Request Body**:
  ```json
  {
    "email": "budi@example.com",
    "password": "Password123!",
    "device_name": "web-pos"
  }
  ```
- **Response** (`200 OK`):
  ```json
  {
    "success": true,
    "message": "Login successful",
    "data": {
      "user": {
        "id": "01JM4MHYHRCYPBTEFP9NRKRES1",
        "name": "Budi Santoso",
        "email": "budi@example.com",
        "is_internal": false,
        "is_active": true
      },
      "token": "2|sanctum_token_string_here",
      "token_type": "Bearer"
    }
  }
  ```

### 3.3 Logout
- **Method**: `POST`
- **URL**: `/api/auth/logout`
- **Auth**: `Bearer Token` (Mencatat event audit `LOGOUT` otomatis)
- **Response** (`200 OK`):
  ```json
  {
    "success": true,
    "message": "Logged out successfully",
    "data": null
  }
  ```

### 3.4 Get Current User Profile (`Me`)
- **Method**: `GET`
- **URL**: `/api/auth/me`
- **Auth**: `Bearer Token`
- **Response** (`200 OK`): Mengembalikan profil pengguna beserta daftar tenant dan role yang dimiliki.

---

## 4. Modul 2: Tenant & Store Management (`/api/tenant`)

**Header Wajib**: `Authorization: Bearer <token>`, `X-Tenant-Id: <tenant_id>`

### 4.1 Tenant Profile
- **`GET /api/tenant/profile`** (`permission:tenant.view`): Ambil detail bisnis tenant.
- **`PUT /api/tenant/profile`** (`permission:tenant.update`):
  ```json
  {
    "name": "Kopi Senja Group",
    "phone": "081299998888",
    "address": "Jl. Sudirman Kav 20, Jakarta Selatan"
  }
  ```

### 4.2 Store Outlets CRUD
- **`GET /api/tenant/stores`** (`permission:store.view`): Daftar seluruh outlet toko milik tenant.
- **`POST /api/tenant/stores`** (`permission:store.create`):
  ```json
  {
    "name": "Outlet Senayan",
    "code": "SNY-01",
    "phone": "021-5551234",
    "address": "Mall Senayan City Lt. LG",
    "timezone": "Asia/Jakarta"
  }
  ```
- **`GET /api/tenant/stores/{id}`** (`permission:store.view`): Detail outlet.
- **`PUT /api/tenant/stores/{id}`** (`permission:store.update`): Update informasi outlet.
- **`DELETE /api/tenant/stores/{id}`** (`permission:store.delete`): Hapus outlet (Diproteksi: tidak bisa menghapus outlet terakhir tenant).

### 4.3 Tenant Team & Roles
- **`GET /api/tenant/users`** (`permission:user.view`): Daftar staf dan anggota tim tenant.
- **`POST /api/tenant/users`** (`permission:user.invite`):
  ```json
  {
    "name": "Siti Kasir",
    "email": "siti@kopisenja.com",
    "password": "Password123!",
    "role_id": "01JM4MHYHRCYPBTEFP9NRKRES9",
    "store_id": "01JM4MHYHRCYPBTEFP9NRKRES4"
  }
  ```
- **`PUT /api/tenant/users/{userId}/roles`** (`permission:user.assign_role`): Update penugasan role pengguna.
- **`DELETE /api/tenant/users/{userId}`** (`permission:user.delete`): Hapus anggota tim (Diproteksi: tidak bisa menghapus satu-satunya Owner).
- **`GET /api/tenant/roles`** (`permission:role.view`): Ambil daftar role tenant beserta permissions-nya.

---

## 5. Modul 3: Catalog & Raw Materials (`/api/catalog`)

**Header Wajib**: `Authorization`, `X-Tenant-Id`, `X-Store-Id`

### 5.1 Units (Satuan Ukur)
- **`GET /api/catalog/units`** (`permission:unit.view`): Ambil master satuan (KG, GR, LTR, ML, PCS).
- **`POST /api/catalog/units`** (`permission:unit.create`):
  ```json
  {
    "name": "Gram",
    "code": "GR",
    "type": "WEIGHT"
  }
  ```

### 5.2 Products (Produk Jual)
- **`GET /api/catalog/products`** (`permission:product.view`): Daftar produk per toko (otomatis scoped).
- **`POST /api/catalog/products`** (`permission:product.create`):
  ```json
  {
    "name": "Iced Caffe Latte",
    "sku": "LAT-01",
    "selling_price": 35000.00,
    "description": "Espresso dengan susu segar dan es batu",
    "status": "ACTIVE"
  }
  ```
- **`GET /api/catalog/products/{id}`** (`permission:product.view`)
- **`PUT /api/catalog/products/{id}`** (`permission:product.update`)
- **`DELETE /api/catalog/products/{id}`** (`permission:product.delete`): Soft delete produk.

### 5.3 Ingredients (Bahan Baku)
- **`GET /api/catalog/ingredients`** (`permission:ingredient.view`)
- **`POST /api/catalog/ingredients`** (`permission:ingredient.create`):
  ```json
  {
    "name": "Biji Kopi Arabica Gayo",
    "sku": "ING-GY-01",
    "unit_id": "01JM4MHYHRCYPBTEFP9NRKRES2",
    "status": "ACTIVE"
  }
  ```
- **`GET /api/catalog/ingredients/{id}`**
- **`PUT /api/catalog/ingredients/{id}`**
- **`DELETE /api/catalog/ingredients/{id}`**

### 5.4 Suppliers
- **`GET /api/catalog/suppliers`** (`permission:supplier.view`)
- **`POST /api/catalog/suppliers`** (`permission:supplier.create`):
  ```json
  {
    "name": "PT Roaster Jaya Abadi",
    "contact_name": "Pak Hendra",
    "phone": "081122334455",
    "email": "hendra@roasterjaya.com",
    "address": "Bandung, Jawa Barat"
  }
  ```
- **`GET /api/catalog/suppliers/{id}`**
- **`PUT /api/catalog/suppliers/{id}`**
- **`DELETE /api/catalog/suppliers/{id}`**

---

## 6. Modul 4: Recipes & Menus (`/api/recipes`, `/api/menus`)

**Header Wajib**: `Authorization`, `X-Tenant-Id`, `X-Store-Id`

### 6.1 Recipes (Resep Formula Produk & HPP)
- **`GET /api/recipes`** (`permission:recipe.view`): Daftar resep produk.
- **`POST /api/recipes`** (`permission:recipe.create`):
  ```json
  {
    "product_id": "01JM4MHYHRCYPBTEFP9NRKRES5",
    "name": "Resep Standar Iced Latte",
    "status": "ACTIVE",
    "items": [
      {
        "ingredient_id": "01JM4MHYHRCYPBTEFP9NRKRES7",
        "unit_id": "01JM4MHYHRCYPBTEFP9NRKRES2",
        "quantity": 18.0000
      },
      {
        "ingredient_id": "01JM4MHYHRCYPBTEFP9NRKRES8",
        "unit_id": "01JM4MHYHRCYPBTEFP9NRKRES3",
        "quantity": 150.0000
      }
    ]
  }
  ```
- **`GET /api/recipes/{id}`**
- **`PUT /api/recipes/{id}`**
- **`DELETE /api/recipes/{id}`**

### 6.2 Menus (Koleksi Menu Toko)
- **`GET /api/menus`** (`permission:menu.view`): Koleksi menu outlet.
- **`POST /api/menus`** (`permission:menu.create`):
  ```json
  {
    "name": "Coffee & Beverages",
    "status": "ACTIVE",
    "product_ids": [
      "01JM4MHYHRCYPBTEFP9NRKRES5",
      "01JM4MHYHRCYPBTEFP9NRKRES6"
    ]
  }
  ```
- **`GET /api/menus/{id}`**
- **`PUT /api/menus/{id}`**
- **`DELETE /api/menus/{id}`**

---

## 7. Modul 5: Inventory Management (`/api/inventory`)

**Header Wajib**: `Authorization`, `X-Tenant-Id`, `X-Store-Id`

### 7.1 Purchases (Pembelian Barang Masuk)
- **`GET /api/inventory/purchases`** (`permission:purchase.view`)
- **`POST /api/inventory/purchases`** (`permission:purchase.create`):
  ```json
  {
    "supplier_id": "01JM4MHYHRCYPBTEFP9NRKRES9",
    "purchase_date": "2026-10-11",
    "notes": "Pengadaan biji kopi mingguan",
    "items": [
      {
        "ingredient_id": "01JM4MHYHRCYPBTEFP9NRKRES7",
        "unit_id": "01JM4MHYHRCYPBTEFP9NRKRES2",
        "quantity": 5000.0000,
        "unit_cost": 200.00
      }
    ]
  }
  ```
- **`GET /api/inventory/purchases/{id}`**
- **`POST /api/inventory/purchases/{id}/receive`** (`permission:purchase.receive`): Terima PO ke gudang, menghitung *Moving Average Cost*, menambah stok, dan mencatat mutasi buku besar.
- **`POST /api/inventory/purchases/{id}/cancel`** (`permission:purchase.cancel`)

### 7.2 Stock Balances & Opname Adjustments
- **`GET /api/inventory/stocks`** (`permission:inventory.view`): Cek saldo stok bahan baku saat ini.
- **`GET /api/inventory/stocks/{ingredientId}`**: Detail stok satu bahan baku.
- **`POST /api/inventory/adjustments`** (`permission:inventory.adjust`):
  ```json
  {
    "ingredient_id": "01JM4MHYHRCYPBTEFP9NRKRES7",
    "quantity": 4850.0000,
    "notes": "Hasil stock opname fisik mingguan"
  }
  ```

### 7.3 Wastage (Pencatatan Bahan Rusak/Terbuang)
- **`GET /api/inventory/wastages`** (`permission:wastage.view`)
- **`POST /api/inventory/wastages`** (`permission:wastage.create`):
  ```json
  {
    "wastage_date": "2026-10-11",
    "reason": "Susu basi terkena mati listrik kulkas",
    "items": [
      {
        "ingredient_id": "01JM4MHYHRCYPBTEFP9NRKRES8",
        "unit_id": "01JM4MHYHRCYPBTEFP9NRKRES3",
        "quantity": 1000.0000
      }
    ]
  }
  ```

### 7.4 Inventory Movements Ledger (Audit Mutasi Imutabel)
- **`GET /api/inventory/movements`** (`permission:inventory.movement`): Buku besar mutasi stok (`PURCHASE`, `SALE`, `WASTAGE`, `ADJUSTMENT`) terurut kronologis dengan referensi ID transaksi.

---

## 8. Modul 6: POS, Tables, Orders & Payments (`/api/pos`)

**Header Wajib**: `Authorization`, `X-Tenant-Id`, `X-Store-Id`

### 8.1 Table Management & QR
- **`GET /api/pos/tables`** (`permission:table.view`): Ambil daftar meja dengan status operasional `is_occupied` dan `active_orders_count` terhitung real-time.
- **`POST /api/pos/tables`** (`permission:table.create`): Buat meja (otomatis generate QR code aktif perdana).
  ```json
  {
    "name": "Meja VIP 01",
    "code": "T-VIP-1",
    "capacity": 6,
    "status": "ACTIVE"
  }
  ```
- **`GET /api/pos/tables/{id}`**
- **`PUT /api/pos/tables/{id}`** (`permission:table.update`)
- **`DELETE /api/pos/tables/{id}`** (`permission:table.delete`): Hapus meja (Ditolak jika ada pesanan aktif).
- **`POST /api/pos/tables/{id}/qr`** (`permission:qr.generate`): Rotasi QR token meja (menonaktifkan QR lama).
- **`GET /api/pos/tables/{id}/qr`** (`permission:qr.view`): Riwayat token QR meja.

### 8.2 Orders (Pesanan Kasir)
- **`GET /api/pos/orders`** (`permission:order.view`): Daftar pesanan toko dengan filter `status`, `table_id`, `source`.
- **`POST /api/pos/orders`** (`permission:order.create`):
  ```json
  {
    "table_id": "01JM4MHYHRCYPBTEFP9NRKRES4",
    "customer_name": "Andi Pratama",
    "customer_phone": "081234567890",
    "discount_amount": 5000.00,
    "tax_amount": 3000.00,
    "service_charge": 1500.00,
    "notes": "Dine-in",
    "items": [
      {
        "product_id": "01JM4MHYHRCYPBTEFP9NRKRES5",
        "quantity": 2,
        "discount_amount": 0,
        "notes": "Less ice"
      }
    ]
  }
  ```
- **`GET /api/pos/orders/{id}`**
- **`PUT /api/pos/orders/{id}/status`** (`permission:order.update`):
  ```json
  {
    "status": "PREPARING" // PENDING | CONFIRMED | PREPARING | READY | COMPLETED | CANCELLED
  }
  ```
- **`POST /api/pos/orders/{id}/cancel`** (`permission:order.cancel`):
  ```json
  {
    "reason": "Customer meminta pembatalan"
  }
  ```

### 8.3 Payments & Recipe Stock Depletion
- **`GET /api/pos/orders/{id}/payments`** (`permission:payment.view`)
- **`POST /api/pos/orders/{id}/payments`** (`permission:payment.process`):
  ```json
  {
    "method": "CASH", // CASH | QRIS | TRANSFER | EDC | ONLINE
    "amount": 70000.00,
    "provider": null,
    "provider_reference": null,
    "metadata": {}
  }
  ```
  **Alur Eksekusi Otomatis**:
  1. Validasi nominal bayar $\ge$ total pesanan. Menghitung uang kembalian (*change*) ke metadata.
  2. Mencari resep aktif setiap item produk pesanan, mengunci stok bahan baku, memotong stok secara aman (`SELECT FOR UPDATE`), dan mencatat mutasi `SALE` bernilai negatif.
  3. Mengubah status pesanan menjadi `COMPLETED`.
  4. Status meja otomatis menjadi `is_occupied = false`.

---

## 9. Modul 7: Public Customer QR Ordering (`/api/public`)

**Header**: *None* (Publik tanpa autentikasi untuk pelanggan restoran).

### 9.1 Resolve Scanned QR Code
- **Method**: `GET`
- **URL**: `/api/public/qr/{token}`
- **Response** (`200 OK`):
  ```json
  {
    "message": "QR code resolved successfully.",
    "data": {
      "token": "a1b2c3d4e5f6...",
      "store": {
        "id": "01JM4MHYHRCYPBTEFP9NRKRES1",
        "name": "Outlet Senopati",
        "code": "SNP-01"
      },
      "table": {
        "id": "01JM4MHYHRCYPBTEFP9NRKRES4",
        "name": "Meja 04",
        "code": "M04",
        "capacity": 4
      },
      "products": [
        {
          "id": "01JM4MHYHRCYPBTEFP9NRKRES5",
          "name": "Iced Americano",
          "sku": "AME-01",
          "selling_price": "28000.00",
          "description": "Double shot espresso over ice"
        }
      ]
    }
  }
  ```

### 9.2 Place Customer Dine-In Order
- **Method**: `POST`
- **URL**: `/api/public/qr/{token}/order`
- **Request Body**:
  ```json
  {
    "customer_name": "Rina Wijaya",
    "customer_phone": "081298765432",
    "notes": "Mohon disajikan serentak",
    "items": [
      {
        "product_id": "01JM4MHYHRCYPBTEFP9NRKRES5",
        "quantity": 2,
        "notes": "Sedotan kertas"
      }
    ]
  }
  ```
- **Response** (`201 Created`): Pesanan terbuat dengan `source = 'QR'`, `status = 'PENDING'`, dan meja ditandai `is_occupied = true`.

---

## 10. Modul 8: Sales Reporting & Dashboard (`/api/reports`)

**Header Wajib**: `Authorization`, `X-Tenant-Id`, `X-Store-Id`

### 10.1 Unified Dashboard Overview
- **`GET /api/reports/dashboard?start_date=2026-10-01&end_date=2026-10-11`** (`permission:report.sales`):
  Mengembalikan seluruh KPI utama, tren penjualan harian, top 5 produk, dan ringkasan metode pembayaran dalam 1 response terpadu.

### 10.2 Detailed Sales Summary & Daily Trend
- **`GET /api/reports/sales?start_date=2026-10-01&end_date=2026-10-11`** (`permission:report.sales`):
  Menghitung *Gross Sales, Total Discounts, Net Sales, Tax, Service Charge, Total Revenue*, dan *Average Order Value* (AOV).

### 10.3 Top Selling Products
- **`GET /api/reports/products?start_date=2026-10-01&end_date=2026-10-11&limit=10`** (`permission:report.sales`):
  Peringkat item berdasarkan kuantitas terjual dan nominal rupiah penjualan.

### 10.4 Payment Methods Breakdown
- **`GET /api/reports/payments?start_date=2026-10-01&end_date=2026-10-11`** (`permission:report.sales`):
  Distribusi penerimaan per metode bayar (`CASH`, `QRIS`, `TRANSFER`, dll) dan persentase kontribusinya.

### 10.5 Raw Material Usage & COGS / HPP Financial Analysis
- **`GET /api/reports/inventory-usage?start_date=2026-10-01&end_date=2026-10-11`** (`permission:report.inventory`):
  Analisis HPP riil bahan baku, Laba Kotor (*Gross Profit*), dan Margin Laba Kotor (*Gross Profit Margin Percentage*).

### 10.6 Cashier Shift & Cash Drawer Audit
- **`GET /api/reports/cashiers?start_date=2026-10-01&end_date=2026-10-11`** (`permission:report.financial`):
  Audit penjualan per kasir/staf, total uang kas fisik yang wajib ada di laci kas (dikurangi uang kembalian), dan total transaksi non-tunai.

---

## 11. Modul 9: System Audit Logs & Hierarchical Settings (`/api/system`)

**Header Wajib**: `Authorization`, `X-Tenant-Id` (Opsional `X-Store-Id` untuk setting level toko).

### 11.1 Audit Logs
- **`GET /api/system/audit-logs?action=LOGIN&page=1`** (`permission:audit.view`):
  Inspeksi log keamanan audit append-only (`LOGIN`, `LOGOUT`) beserta IP address dan user agent.

### 11.2 Settings Management (Hierarchical: Store $\rightarrow$ Tenant $\rightarrow$ System)
- **`GET /api/system/settings`** (`permission:tenant.settings`):
  Mengambil dictionary setting efektif beserta asal level sumbernya (`STORE`, `TENANT`, atau `SYSTEM`).
- **`GET /api/system/settings/{key}`**:
  Mengambil nilai tunggal setting dengan resolusi hierarkis otomatis.
- **`POST /api/system/settings`**:
  Upsert setting tunggal atau batch settings.
  ```json
  {
    "settings": {
      "currency": "IDR",
      "tax_percentage": "11.0",
      "receipt_footer": "Terima kasih atas kunjungan Anda!"
    },
    "store_id": null // null untuk Tenant level, atau isi ID store untuk Store level override
  }
  ```

---

## 12. Standard Error Responses

Semua endpoint mengembalikan status HTTP standar dengan format pesan error terstruktur:

| Status Code | Arti | Format Error Contoh |
| :--- | :--- | :--- |
| **`400 Bad Request`** | Parameter request tidak valid | `{"message": "Invalid request parameter."}` |
| **`401 Unauthorized`** | Token Bearer tidak disertakan atau expired | `{"message": "Unauthenticated."}` |
| **`403 Forbidden`** | Role tidak memiliki hak akses permission | `{"message": "User does not have the required permission."}` |
| **`404 Not Found`** | Sumber daya atau QR token tidak ditemukan | `{"message": "Invalid or inactive QR code."}` |
| **`422 Unprocessable`** | Validasi gagal / Pelanggaran aturan bisnis | `{"message": "Insufficient stock for ingredient 'Arabica Gayo' to fulfill order."}` |
| **`500 Internal Error`**| Kesalahan server internal | `{"message": "An unexpected error occurred."}` |
