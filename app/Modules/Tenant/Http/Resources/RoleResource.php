<?php

namespace App\Modules\Tenant\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RoleResource extends JsonResource
{
    /**
     * Map database permissions to dashboard pages.
     */
    protected function getAccessiblePages(): array
    {
        $permNames = $this->permissions ? $this->permissions->pluck('name')->all() : [];

        $allPages = [
            [
                'id' => 'dashboard',
                'name' => 'Ringkasan Dashboard',
                'path' => '/dashboard',
                'group' => 'Operasional',
                'description' => 'Melihat ringkasan metrik penjualan, grafik pendapatan, dan performa harian outlet.',
                'required_permissions' => ['report.sales', 'report.financial', 'audit.view'],
            ],
            [
                'id' => 'pos',
                'name' => 'Kasir POS (Terminal Kasir)',
                'path' => '/dashboard/pos',
                'group' => 'Operasional',
                'description' => 'Operasional transaksi kasir, input pesanan, pembayaran tunai & QRIS, cetak struk.',
                'required_permissions' => ['order.create', 'payment.process'],
            ],
            [
                'id' => 'orders',
                'name' => 'Daftar Pesanan & Antrean',
                'path' => '/dashboard/orders',
                'group' => 'Operasional',
                'description' => 'Memantau seluruh antrean pesanan berjalan, status pesanan, dan rincian item pesanan.',
                'required_permissions' => ['order.view', 'order.create'],
            ],
            [
                'id' => 'transactions',
                'name' => 'Riwayat Transaksi & Struk',
                'path' => '/dashboard/transactions',
                'group' => 'Operasional',
                'description' => 'Melihat riwayat transaksi penjualan yang telah selesai, detail pembayaran, dan refund.',
                'required_permissions' => ['payment.view', 'payment.process'],
            ],
            [
                'id' => 'tables',
                'name' => 'Meja & Denah Ruangan',
                'path' => '/dashboard/tables',
                'group' => 'Operasional',
                'description' => 'Melihat denah ruangan outlet, status keterisian meja, dan reservasi tamu.',
                'required_permissions' => ['table.view', 'table.create'],
            ],
            [
                'id' => 'kds',
                'name' => 'Kitchen Display System (KDS)',
                'path' => '/dashboard/kds',
                'group' => 'Operasional',
                'description' => 'Layar antrean persiapan pesanan langsung untuk station dapur makanan dan barista.',
                'required_permissions' => ['order.view'],
            ],
            [
                'id' => 'catalog',
                'name' => 'Katalog Menu & Produk',
                'path' => '/dashboard/catalog',
                'group' => 'Katalog & Stok',
                'description' => 'Melihat dan mengelola daftar produk, variasi harga, kategori menu, dan status ketersediaan.',
                'required_permissions' => ['product.view', 'menu.view'],
            ],
            [
                'id' => 'recipes',
                'name' => 'Resep & Kalkulasi HPP',
                'path' => '/dashboard/recipes',
                'group' => 'Katalog & Stok',
                'description' => 'Menyusun takaran gramasi bahan baku tiap produk serta memonitor harga pokok penjualan (HPP).',
                'required_permissions' => ['recipe.view', 'recipe.create'],
            ],
            [
                'id' => 'inventory',
                'name' => 'Inventori & Stok Bahan',
                'path' => '/dashboard/inventory',
                'group' => 'Katalog & Stok',
                'description' => 'Mencatat stok masuk/keluar, purchase order (PO) supplier, penyesuaian stok opname, dan wastage.',
                'required_permissions' => ['inventory.view', 'purchase.view', 'ingredient.view', 'supplier.view'],
            ],
            [
                'id' => 'reports',
                'name' => 'Laporan Penjualan',
                'path' => '/dashboard/reports',
                'group' => 'Bisnis & Pengelolaan',
                'description' => 'Melihat laporan omset berkala, produk terlaris (best seller), dan export data excel/PDF.',
                'required_permissions' => ['report.sales', 'report.financial', 'report.inventory'],
            ],
            [
                'id' => 'finance',
                'name' => 'Keuangan & Kas Outlet',
                'path' => '/dashboard/finance',
                'group' => 'Bisnis & Pengelolaan',
                'description' => 'Rekapitulasi kas masuk/keluar harian, audit kasir shift, rekonsiliasi transfer bank & QRIS.',
                'required_permissions' => ['report.financial', 'payment.view'],
            ],
            [
                'id' => 'customers',
                'name' => 'Pelanggan & CRM Loyalitas',
                'path' => '/dashboard/customers',
                'group' => 'Bisnis & Pengelolaan',
                'description' => 'Database informasi kontak pelanggan, riwayat belanja, dan program loyalitas member.',
                'required_permissions' => ['order.view', 'user.view'],
            ],
            [
                'id' => 'staff',
                'name' => 'Staf & Hak Akses',
                'path' => '/dashboard/staff',
                'group' => 'Bisnis & Pengelolaan',
                'description' => 'Menambah anggota tim outlet, mengatur jabatan, dan menentukan izin peran (Role-Based Access).',
                'required_permissions' => ['user.view', 'role.view'],
            ],
            [
                'id' => 'settings',
                'name' => 'Pengaturan Outlet',
                'path' => '/dashboard/settings',
                'group' => 'Bisnis & Pengelolaan',
                'description' => 'Mengatur profil bisnis, jam buka/tutup, format struk printer, tarif pajak, dan metode bayar.',
                'required_permissions' => ['tenant.settings', 'tenant.update', 'store.update'],
            ],
        ];

        return array_map(function ($page) use ($permNames) {
            $isAccessible = false;
            foreach ($page['required_permissions'] as $req) {
                if (in_array($req, $permNames, true)) {
                    $isAccessible = true;
                    break;
                }
            }
            $page['is_accessible'] = $isAccessible;
            return $page;
        }, $allPages);
    }

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'name' => $this->name,
            'scope' => $this->scope,
            'is_default' => (bool) $this->is_default,
            'description' => $this->description,
            'staff_count' => (int) ($this->staff_count ?? 0),
            'permissions' => $this->whenLoaded('permissions', function () {
                return $this->permissions->map(function ($p) {
                    return [
                        'id' => $p->id,
                        'name' => $p->name,
                        'module' => $p->module,
                        'description' => $p->description,
                    ];
                });
            }),
            'accessible_pages' => $this->whenLoaded('permissions', function () {
                return $this->getAccessiblePages();
            }),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
