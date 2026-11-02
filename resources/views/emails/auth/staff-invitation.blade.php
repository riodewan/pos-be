@extends('emails.layout')

@section('content')
    <h1>Undangan Bergabung sebagai Staf</h1>
    
    <p>Halo <strong>{{ $staffName ?? 'Staf Baru' }}</strong>,</p>
    
    <p>
        Anda telah didaftarkan sebagai bagian dari tim <strong>{{ $businessName ?? 'Outlet' }}</strong> pada sistem kasir dan operasional NemuHUB.
    </p>

    <div class="highlight-box">
        <h4 style="margin: 0 0 12px 0; font-size: 13px; color: #111827; text-transform: uppercase; letter-spacing: 0.5px;">
            Kredensial Akses Akun
        </h4>
        <table class="summary-table" style="margin: 0;">
            <tr>
                <td class="label">Nama Lengkap</td>
                <td>{{ $staffName ?? '-' }}</td>
            </tr>
            <tr>
                <td class="label">Peran / Jabatan</td>
                <td><span style="background-color: #FFECE8; color: #FF5C38; padding: 2px 8px; border-radius: 4px; font-size: 11px;">{{ $roleName ?? 'Staff' }}</span></td>
            </tr>
            <tr>
                <td class="label">Outlet Penugasan</td>
                <td>{{ $outletName ?? '-' }}</td>
            </tr>
            <tr>
                <td class="label">Email Login</td>
                <td>{{ $email ?? '-' }}</td>
            </tr>
            <tr>
                <td class="label">Kata Sandi Awal</td>
                <td><code style="background-color: #F3F4F6; padding: 2px 6px; border-radius: 4px; font-size: 12px; color: #E11D48;">{{ $tempPassword ?? '******' }}</code></td>
            </tr>
        </table>
    </div>

    <div style="text-align: center; margin: 24px 0;">
        <a href="{{ $loginUrl ?? '#' }}" class="btn-primary" target="_blank">
            Buka Halaman Login NemuHUB
        </a>
    </div>

    <div class="highlight-box success">
        <p style="margin: 0; font-size: 12px;">
            Demi keamanan, silakan langsung mengganti kata sandi default Anda setelah berhasil masuk pertama kali di menu <strong>Profil Akun</strong>.
        </p>
    </div>
@endsection
