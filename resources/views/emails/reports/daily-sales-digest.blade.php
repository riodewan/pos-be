@extends('emails.layout')

@section('content')
    <div style="display: inline-block; background-color: #FFF7ED; color: #EA580C; font-weight: 700; font-size: 11px; padding: 4px 10px; border-radius: 6px; margin-bottom: 12px;">
        DAILY SALES DIGEST
    </div>

    <h1>Laporan Penjualan Harian Toko</h1>
    
    <p>Halo <strong>{{ $ownerName ?? 'Owner' }}</strong>,</p>
    
    <p>
        Berikut adalah ringkasan performa penjualan outlet <strong>{{ $outletName ?? 'Outlet' }}</strong> untuk hari ini, <strong>{{ $reportDate ?? now()->translatedFormat('l, d F Y') }}</strong>:
    </p>

    <!-- Total Omset Box -->
    <div class="highlight-box" style="background-color: #FFF7ED; border-color: #FFEDD5; text-align: center; padding: 24px;">
        <div style="font-size: 12px; font-weight: bold; color: #9A3412; text-transform: uppercase; letter-spacing: 0.5px;">
            Total Omset Kotor Hari Ini
        </div>
        <div style="font-size: 32px; font-weight: 900; color: #EA580C; margin: 8px 0; font-family: monospace;">
            Rp{{ number_format($totalRevenue ?? 0, 0, ',', '.') }}
        </div>
        <div style="font-size: 12px; color: #7C2D12;">
            Dari <strong>{{ $totalOrders ?? 0 }} transaksi</strong> berhasil diproses
        </div>
    </div>

    <!-- Metrics Breakdown Table -->
    <table class="summary-table">
        <tr>
            <td class="label">Rata-Rata Pembelian / Keranjang</td>
            <td>Rp{{ number_format($averageOrderValue ?? 0, 0, ',', '.') }}</td>
        </tr>
        <tr>
            <td class="label">Pesanan Dine-In (Makan di Tempat)</td>
            <td>{{ $dineInCount ?? 0 }} pesanan (Rp{{ number_format($dineInRevenue ?? 0, 0, ',', '.') }})</td>
        </tr>
        <tr>
            <td class="label">Pesanan Takeaway (Bungkus)</td>
            <td>{{ $takeawayCount ?? 0 }} pesanan (Rp{{ number_format($takeawayRevenue ?? 0, 0, ',', '.') }})</td>
        </tr>
        <tr>
            <td class="label">Metode Bayar Non-Tunai (QRIS & EDC)</td>
            <td>Rp{{ number_format($nonCashTotal ?? 0, 0, ',', '.') }}</td>
        </tr>
        <tr>
            <td class="label">Metode Bayar Tunai (Cash)</td>
            <td>Rp{{ number_format($cashTotal ?? 0, 0, ',', '.') }}</td>
        </tr>
    </table>

    <!-- Top Products -->
    @if (!empty($topProducts))
    <div style="margin-top: 24px;">
        <h4 style="font-size: 13px; font-weight: bold; color: #111827; margin: 0 0 12px 0; text-transform: uppercase;">
            Top 3 Menu Terlaris Hari Ini
        </h4>
        <div style="background-color: #F9FAFB; border: 1px solid #E5E7EB; border-radius: 12px; padding: 12px 16px;">
            @foreach ($topProducts as $idx => $prod)
            <div style="display: flex; justify-content: space-between; align-items: center; padding: 8px 0; {{ $loop->last ? '' : 'border-bottom: 1px solid #F3F4F6;' }}">
                <div style="font-size: 13px; color: #111827;">
                    <span style="display: inline-block; width: 20px; font-weight: bold; color: #FF5C38;">#{{ $idx + 1 }}</span>
                    {{ $prod['name'] ?? 'Menu' }}
                </div>
                <div style="font-size: 12px; font-weight: 600; color: #6B7280; font-family: monospace;">
                    {{ $prod['qty'] ?? 0 }} terjual
                </div>
            </div>
            @endforeach
        </div>
    </div>
    @endif

    <div style="text-align: center; margin: 28px 0 16px 0;">
        <a href="{{ $reportUrl ?? '#' }}" class="btn-primary" target="_blank">
            Lihat Laporan Lengkap di Dashboard
        </a>
    </div>
@endsection
