<?php

use App\Mail\DailySalesDigestMail;
use App\Mail\DisbursementStatusMail;
use App\Mail\LowStockAlertMail;
use App\Mail\PlatformApprovalNoticeMail;
use App\Mail\ResetPasswordMail;
use App\Mail\ShiftClosingSummaryMail;
use App\Mail\StaffInvitationMail;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

/*
|--------------------------------------------------------------------------
| Email Templates Live Preview (No SMTP Required)
|--------------------------------------------------------------------------
*/
Route::prefix('mail-preview')->group(function () {
    Route::get('/', function () {
        return '
        <div style="font-family: sans-serif; max-width: 600px; margin: 40px auto; padding: 24px; border: 1px solid #e5e7eb; border-radius: 12px; background: #fafafa;">
            <h2 style="color: #FF5C38; margin-top: 0;">NemuHUB Email Templates Preview</h2>
            <p style="color: #4b5563; font-size: 14px;">Klik tautan di bawah untuk melihat tampilan masing-masing template email responsif:</p>
            <ul style="line-height: 2; font-size: 14px;">
                <li><a href="/mail-preview/reset-password">1A. Reset Kata Sandi (Auth)</a></li>
                <li><a href="/mail-preview/staff-invitation">1B. Undangan Staf Baru (Onboarding)</a></li>
                <li><a href="/mail-preview/disbursement-status">2A. Pencairan Dana Berhasil (Finance Payout)</a></li>
                <li><a href="/mail-preview/shift-closing">2B. Rekap Penutupan Shift Kasir (Cash Audit)</a></li>
                <li><a href="/mail-preview/daily-sales">3. Laporan Omset Harian Toko (Daily Sales Digest)</a></li>
                <li><a href="/mail-preview/low-stock">4. Peringatan Stok Bahan Baku Menipis (Inventory Alert)</a></li>
                <li><a href="/mail-preview/approval-notice">5. Persetujuan Cabang / Verifikasi (Platform Approval)</a></li>
            </ul>
        </div>';
    });

    Route::get('/reset-password', function () {
        return (new ResetPasswordMail([
            'userName' => 'Bambang Sudiro',
            'userEmail' => 'owner@kopinusantara.com',
            'resetUrl' => 'https://app.nemuhub.com/reset-password?token=sample_token_xyz',
            'outletName' => 'Kopi Nusantara - Outlet Senopati',
        ]))->render();
    });

    Route::get('/staff-invitation', function () {
        return (new StaffInvitationMail([
            'staffName' => 'Lestari Putri',
            'businessName' => 'Kopi Nusantara',
            'outletName' => 'Outlet Senopati',
            'roleName' => 'Kasir Utama',
            'email' => 'kasir@kopinusantara.com',
            'tempPassword' => 'Kopi123!',
            'loginUrl' => 'https://app.nemuhub.com/login',
        ]))->render();
    });

    Route::get('/disbursement-status', function () {
        return (new DisbursementStatusMail([
            'ownerName' => 'Bambang Sudiro',
            'outletName' => 'Kopi Nusantara - Outlet Senopati',
            'batchId' => 'WD-' . date('Ymd') . '-001',
            'bankName' => 'Bank Central Asia (BCA)',
            'accountNumber' => '5420192837 a.n PT Kopi Nusantara Abadi',
            'grossAmount' => 1500000,
            'fee' => 0,
            'netAmount' => 1500000,
            'processedAt' => now()->translatedFormat('d M Y, H:i'),
            'financeUrl' => 'https://app.nemuhub.com/dashboard/finance',
        ]))->render();
    });

    Route::get('/shift-closing', function () {
        return (new ShiftClosingSummaryMail([
            'ownerName' => 'Bambang Sudiro',
            'outletName' => 'Kopi Nusantara - Outlet Senopati',
            'shiftName' => 'Shift Pagi (08:00 - 16:00)',
            'shiftDate' => now()->translatedFormat('d M Y'),
            'cashierName' => 'Lestari Putri',
            'openingCash' => 200000,
            'cashSales' => 85800,
            'nonCashSales' => 172500,
            'expectedCash' => 285800,
            'actualCash' => 285800,
            'notes' => 'Kas fisik cocok, transaksi ramai saat jam makan siang.',
            'financeUrl' => 'https://app.nemuhub.com/dashboard/finance',
        ]))->render();
    });

    Route::get('/daily-sales', function () {
        return (new DailySalesDigestMail([
            'ownerName' => 'Bambang Sudiro',
            'outletName' => 'Kopi Nusantara - Outlet Senopati',
            'reportDate' => now()->translatedFormat('l, d F Y'),
            'totalRevenue' => 2450000,
            'totalOrders' => 48,
            'averageOrderValue' => 51040,
            'dineInCount' => 36,
            'dineInRevenue' => 1840000,
            'takeawayCount' => 12,
            'takeawayRevenue' => 610000,
            'nonCashTotal' => 1950000,
            'cashTotal' => 500000,
            'topProducts' => [
                ['name' => 'Iced Americano', 'qty' => 22],
                ['name' => 'Caffe Latte', 'qty' => 18],
                ['name' => 'Butter Croissant', 'qty' => 14],
            ],
            'reportUrl' => 'https://app.nemuhub.com/dashboard/reports',
        ]))->render();
    });

    Route::get('/low-stock', function () {
        return (new LowStockAlertMail([
            'managerName' => 'Ahmad Rian (Logistik & Gudang)',
            'outletName' => 'Kopi Nusantara - Outlet Senopati',
            'lowStockItems' => [
                ['name' => 'Biji Kopi Espresso Blend', 'sku' => 'ING-ESP-01', 'current_stock' => 1.2, 'min_stock' => 3.0, 'unit' => 'KG'],
                ['name' => 'Susu Segar Full Cream', 'sku' => 'ING-MLK-01', 'current_stock' => 4.5, 'min_stock' => 10.0, 'unit' => 'LTR'],
                ['name' => 'Paper Cup Hot/Cold 12oz', 'sku' => 'ING-CUP-12', 'current_stock' => 45.0, 'min_stock' => 100.0, 'unit' => 'PCS'],
            ],
            'inventoryUrl' => 'https://app.nemuhub.com/dashboard/inventory',
        ]))->render();
    });

    Route::get('/approval-notice', function () {
        return (new PlatformApprovalNoticeMail([
            'recipientName' => 'Bambang Sudiro',
            'tenantName' => 'Kopi Nusantara',
            'status' => 'APPROVED',
            'title' => 'Pengajuan Cabang Baru',
            'requestType' => 'Penambahan Cabang Outlet',
            'targetName' => 'Kopi Nusantara - Outlet Kemang',
            'reviewedAt' => now()->translatedFormat('d M Y, H:i'),
            'reviewNotes' => 'Lokasi dan berkas operasional telah diverifikasi Super Admin.',
            'dashboardUrl' => 'https://app.nemuhub.com/dashboard/settings/profile',
        ]))->render();
    });
});

