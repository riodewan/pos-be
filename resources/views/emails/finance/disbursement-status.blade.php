@extends('emails.layout')

@section('content')
    <div style="display: inline-block; background-color: #ECFDF5; color: #059669; font-weight: 700; font-size: 11px; padding: 4px 10px; border-radius: 6px; margin-bottom: 12px;">
        STATUS: PENCAIRAN BERHASIL
    </div>

    <h1>Pencairan Saldo Pendapatan Berhasil</h1>
    
    <p>Halo <strong>{{ $ownerName ?? 'Mitra NemuHUB' }}</strong>,</p>
    
    <p>
        Dana hasil transaksi outlet Anda di <strong>{{ $outletName ?? 'Outlet' }}</strong> telah berhasil ditransfer ke rekening bank tujuan Anda.
    </p>

    <div class="highlight-box success">
        <div style="font-size: 11px; color: #047857; text-transform: uppercase; font-weight: bold;">
            Total Dana Diterima
        </div>
        <div style="font-size: 26px; font-weight: 800; color: #065F46; margin-top: 4px; font-family: monospace;">
            Rp{{ number_format($netAmount ?? 0, 0, ',', '.') }}
        </div>
    </div>

    <table class="summary-table">
        <tr>
            <td class="label">Nomor Referensi (Batch ID)</td>
            <td><code style="font-family: monospace;">{{ $batchId ?? 'WD-' . date('Ymd') . '-001' }}</code></td>
        </tr>
        <tr>
            <td class="label">Bank Tujuan</td>
            <td>{{ $bankName ?? 'BCA' }}</td>
        </tr>
        <tr>
            <td class="label">Nomor Rekening</td>
            <td>{{ $accountNumber ?? '-' }}</td>
        </tr>
        <tr>
            <td class="label">Nominal Kotor Diajukan</td>
            <td>Rp{{ number_format($grossAmount ?? 0, 0, ',', '.') }}</td>
        </tr>
        <tr>
            <td class="label">Biaya Transfer Bank</td>
            <td>Rp{{ number_format($fee ?? 0, 0, ',', '.') }}</td>
        </tr>
        <tr>
            <td class="label">Waktu Transfer</td>
            <td>{{ $processedAt ?? now()->translatedFormat('d M Y, H:i') }} WIB</td>
        </tr>
    </table>

    <div style="text-align: center; margin: 24px 0;">
        <a href="{{ $financeUrl ?? '#' }}" class="btn-primary" target="_blank">
            Lihat Histori Penarikan di Dashboard
        </a>
    </div>

    <p style="font-size: 12px; color: #6B7280;">
        Jika dana belum masuk ke mutasi rekening bank Anda dalam 1x24 jam hari kerja, silakan hubungi tim dukungan NemuHUB dengan menyertakan Nomor Referensi di atas.
    </p>
@endsection

