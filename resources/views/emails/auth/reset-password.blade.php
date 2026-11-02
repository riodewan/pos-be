@extends('emails.layout')

@section('content')
    <h1>Atur Ulang Kata Sandi Akun</h1>
    
    <p>Halo <strong>{{ $userName ?? 'Pengguna NemuHUB' }}</strong>,</p>
    
    <p>
        Kami menerima permintaan untuk mengatur ulang kata sandi akun NemuHUB Anda yang terdaftar dengan email: 
        <strong style="color: #111827;">{{ $userEmail ?? '-' }}</strong>.
    </p>

    <div style="text-align: center; margin: 28px 0;">
        <a href="{{ $resetUrl ?? '#' }}" class="btn-primary" target="_blank">
            Atur Ulang Kata Sandi
        </a>
    </div>

    <div class="highlight-box warning">
        <p style="margin: 0; font-size: 12px; line-height: 1.5;">
            <strong>Penting:</strong> Tautan ini hanya berlaku selama <strong>60 menit</strong> sejak email ini dikirim. 
            Jika Anda tidak meminta perubahan kata sandi, abaikan email ini dan akun Anda tetap aman.
        </p>
    </div>

    <p style="font-size: 12px; color: #6B7280; margin-top: 24px;">
        Jika tombol di atas tidak berfungsi, salin dan tempel tautan berikut ke browser Anda:<br>
        <a href="{{ $resetUrl ?? '#' }}" style="color: #FF5C38; word-break: break-all;">{{ $resetUrl ?? '#' }}</a>
    </p>
@endsection
