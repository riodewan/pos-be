@extends('emails.layout')

@section('content')
    <div style="display: inline-block; background-color: #F3F4F6; color: #374151; font-weight: 700; font-size: 11px; padding: 4px 10px; border-radius: 6px; margin-bottom: 12px;">
        AUDIT SHIFT KASIR
    </div>

    <h1>Rekap Penutupan Shift Kasir</h1>
    
    <p>Halo <strong>{{ $ownerName ?? 'Owner' }}</strong>,</p>
    
    <p>
        Kasir telah melakukan proses tutup buku shift kasir di <strong>{{ $outletName ?? 'Outlet' }}</strong> dengan rincian operasional berikut:
    </p>

    <!-- Kas Status Highlight Box -->
    @php
        $diff = ($actualCash ?? 0) - ($expectedCash ?? 0);
    @endphp

    @if ($diff === 0)
        <div class="highlight-box success">
            <div style="font-size: 11px; text-transform: uppercase; font-weight: bold;">Status Selisih Kas</div>
            <div style="font-size: 20px; font-weight: bold; margin-top: 2px;">Cocok / Pas (Rp0)</div>
            <p style="margin: 4px 0 0 0; font-size: 12px;">Uang fisik di laci kasir persis sesuai dengan total transaksi sistem.</p>
        </div>
    @elseif ($diff < 0)
        <div class="highlight-box danger">
            <div style="font-size: 11px; text-transform: uppercase; font-weight: bold;">Peringatan: Selisih Kas Minus</div>
            <div style="font-size: 20px; font-weight: bold; margin-top: 2px; font-family: monospace;">-Rp{{ number_format(abs($diff), 0, ',', '.') }}</div>
            <p style="margin: 4px 0 0 0; font-size: 12px;">Uang kas fisik di laci lebih sedikit daripada catatan transaksi sistem.</p>
        </div>
    @else
        <div class="highlight-box warning">
            <div style="font-size: 11px; text-transform: uppercase; font-weight: bold;">Pemberitahuan: Selisih Kas Lebih</div>
            <div style="font-size: 20px; font-weight: bold; margin-top: 2px; font-family: monospace;">+Rp{{ number_format($diff, 0, ',', '.') }}</div>
            <p style="margin: 4px 0 0 0; font-size: 12px;">Uang kas fisik di laci lebih banyak daripada catatan transaksi sistem.</p>
        </div>
    @endif

    <table class="summary-table">
        <tr>
            <td class="label">Nama Shift</td>
            <td>{{ $shiftName ?? 'Shift Pagi (08:00 - 16:00)' }}</td>
        </tr>
        <tr>
            <td class="label">Tanggal Operasional</td>
            <td>{{ $shiftDate ?? now()->translatedFormat('d M Y') }}</td>
        </tr>
        <tr>
            <td class="label">Petugas Kasir</td>
            <td>{{ $cashierName ?? 'Lestari Putri' }}</td>
        </tr>
        <tr>
            <td class="label">Kas Awal Laci (Modal Kasir)</td>
            <td>Rp{{ number_format($openingCash ?? 200000, 0, ',', '.') }}</td>
        </tr>
        <tr>
            <td class="label">Total Penjualan Tunai (Cash)</td>
            <td>Rp{{ number_format($cashSales ?? 0, 0, ',', '.') }}</td>
        </tr>
        <tr>
            <td class="label">Total Penjualan Non-Tunai (QRIS/EDC)</td>
            <td>Rp{{ number_format($nonCashSales ?? 0, 0, ',', '.') }}</td>
        </tr>
        <tr style="border-top: 2px solid #E5E7EB;">
            <td class="label" style="font-weight: bold; color: #111827;">Ekspektasi Kas Fisik Sistem</td>
            <td style="font-family: monospace;">Rp{{ number_format($expectedCash ?? 0, 0, ',', '.') }}</td>
        </tr>
        <tr>
            <td class="label" style="font-weight: bold; color: #111827;">Kas Fisik Dihitung Nyata</td>
            <td style="font-family: monospace; color: #FF5C38;">Rp{{ number_format($actualCash ?? 0, 0, ',', '.') }}</td>
        </tr>
        @if (!empty($notes))
        <tr>
            <td class="label">Catatan Kasir</td>
            <td style="font-style: italic; color: #6B7280;">"{{ $notes }}"</td>
        </tr>
        @endif
    </table>

    <div style="text-align: center; margin: 24px 0;">
        <a href="{{ $financeUrl ?? '#' }}" class="btn-primary" target="_blank">
            Buka Riwayat Audit Kas
        </a>
    </div>
@endsection
