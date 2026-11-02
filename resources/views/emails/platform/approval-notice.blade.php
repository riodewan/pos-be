@extends('emails.layout')

@section('content')
    @php
        $isApproved = ($status ?? 'APPROVED') === 'APPROVED';
    @endphp

    @if ($isApproved)
        <div style="display: inline-block; background-color: #ECFDF5; color: #059669; font-weight: 700; font-size: 11px; padding: 4px 10px; border-radius: 6px; margin-bottom: 12px;">
            PERSETUJUAN DITERIMA
        </div>
        <h1>Pengajuan Anda Telah Disetujui</h1>
    @else
        <div style="display: inline-block; background-color: #FEF2F2; color: #DC2626; font-weight: 700; font-size: 11px; padding: 4px 10px; border-radius: 6px; margin-bottom: 12px;">
            PERSETUJUAN DITOLAK
        </div>
        <h1>Status Pengajuan Anda</h1>
    @endif
    
    <p>Halo <strong>{{ $recipientName ?? 'Mitra NemuHUB' }}</strong>,</p>
    
    <p>
        Tim Administrator NemuHUB telah meninjau pengajuan <strong>{{ $title ?? 'Pengajuan Layanan' }}</strong> untuk bisnis Anda (<strong>{{ $tenantName ?? 'Bisnis' }}</strong>).
    </p>

    <div class="highlight-box {{ $isApproved ? 'success' : 'danger' }}">
        <h4 style="margin: 0 0 8px 0; font-size: 13px; text-transform: uppercase;">
            Status: {{ $isApproved ? 'DISETUJUI (APPROVED)' : 'DITOLAK (REJECTED)' }}
        </h4>
        <p style="margin: 0; font-size: 13px;">
            {{ $messageText ?? ($isApproved ? 'Cabang / permintaan layanan Anda telah aktif dan dapat langsung digunakan pada aplikasi.' : 'Mohon periksa kembali kelengkapan dokumen atau informasi yang diajukan.') }}
        </p>
        @if (!empty($reviewNotes))
            <div style="margin-top: 10px; padding-top: 10px; border-top: 1px dashed rgba(0,0,0,0.1); font-size: 12px;">
                <strong>Catatan Admin:</strong> "{{ $reviewNotes }}"
            </div>
        @endif
    </div>

    <table class="summary-table">
        <tr>
            <td class="label">Jenis Pengajuan</td>
            <td>{{ $requestType ?? 'Penambahan Cabang Outlet' }}</td>
        </tr>
        <tr>
            <td class="label">Nama Outlet / Layanan</td>
            <td>{{ $targetName ?? 'Outlet Baru' }}</td>
        </tr>
        <tr>
            <td class="label">Waktu Keputusan</td>
            <td>{{ $reviewedAt ?? now()->translatedFormat('d M Y, H:i') }} WIB</td>
        </tr>
    </table>

    <div style="text-align: center; margin: 24px 0;">
        <a href="{{ $dashboardUrl ?? '#' }}" class="btn-primary" target="_blank">
            Buka Dashboard NemuHUB
        </a>
    </div>
@endsection

