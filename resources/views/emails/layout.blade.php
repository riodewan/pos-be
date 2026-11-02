<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>{{ $subject ?? 'Notifikasi NemuHUB' }}</title>
    <style>
        /* Base reset */
        body, table, td, a { -webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%; }
        table, td { mso-table-lspace: 0pt; mso-table-rspace: 0pt; }
        img { -ms-interpolation-mode: bicubic; border: 0; outline: none; text-decoration: none; }
        body { margin: 0; padding: 0; width: 100% !important; background-color: #F8F9FA; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; color: #1F2937; }
        
        /* Container */
        .wrapper { width: 100%; background-color: #F8F9FA; padding: 32px 16px; }
        .main-card { max-width: 600px; margin: 0 auto; background-color: #FFFFFF; border-radius: 16px; border: 1px solid #E5E7EB; overflow: hidden; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05); }
        
        /* Header */
        .header { padding: 28px 32px 20px 32px; border-bottom: 1px solid #F3F4F6; }
        .brand-badge { display: inline-block; background-color: #FF5C38; color: #FFFFFF; font-weight: 800; font-size: 14px; letter-spacing: 0.5px; padding: 6px 14px; border-radius: 8px; text-decoration: none; }
        .brand-subtitle { font-size: 11px; color: #6B7280; margin-top: 6px; }

        /* Body */
        .content { padding: 32px; font-size: 14px; line-height: 1.6; color: #374151; }
        .content h1 { font-size: 20px; font-weight: 700; color: #111827; margin: 0 0 16px 0; }
        .content p { margin: 0 0 16px 0; }
        
        /* Highlight Box */
        .highlight-box { background-color: #F9FAFB; border: 1px solid #E5E7EB; border-radius: 12px; padding: 20px; margin: 20px 0; }
        .highlight-box.success { background-color: #ECFDF5; border-color: #A7F3D0; color: #065F46; }
        .highlight-box.warning { background-color: #FFFBEB; border-color: #FDE68A; color: #92400E; }
        .highlight-box.danger { background-color: #FEF2F2; border-color: #FECACA; color: #991B1B; }
        
        /* Button */
        .btn-primary { display: inline-block; background-color: #FF5C38; color: #FFFFFF !important; font-weight: 600; font-size: 13px; text-align: center; text-decoration: none; padding: 12px 24px; border-radius: 10px; margin: 16px 0; }
        
        /* Table Summary */
        .summary-table { width: 100%; border-collapse: collapse; margin: 16px 0; font-size: 13px; }
        .summary-table td { padding: 10px 0; border-bottom: 1px solid #F3F4F6; }
        .summary-table td:last-child { text-align: right; font-weight: 600; color: #111827; }
        .summary-table .label { color: #6B7280; font-weight: normal; }

        /* Footer */
        .footer { padding: 24px 32px; background-color: #FAFAFA; border-top: 1px solid #F3F4F6; text-align: center; font-size: 11px; color: #9CA3AF; line-height: 1.5; }
        .footer a { color: #6B7280; text-decoration: underline; }
    </style>
</head>
<body>
    <div class="wrapper">
        <div class="main-card">
            <!-- Header -->
            <div class="header">
                <table width="100%" border="0" cellpadding="0" cellspacing="0">
                    <tr>
                        <td>
                            <div class="brand-badge">NemuHUB</div>
                            <div class="brand-subtitle">{{ $outletName ?? 'Sistem Manajemen POS & Operasional' }}</div>
                        </td>
                        <td align="right" style="vertical-align: top;">
                            <span style="font-size: 11px; font-weight: 600; color: #9CA3AF; background-color: #F3F4F6; padding: 4px 8px; border-radius: 6px;">
                                {{ $date ?? now()->translatedFormat('d M Y') }}
                            </span>
                        </td>
                    </tr>
                </table>
            </div>

            <!-- Content Slot -->
            <div class="content">
                @yield('content')
            </div>

            <!-- Footer -->
            <div class="footer">
                <p style="margin: 0 0 6px 0;">
                    Email ini dikirim otomatis oleh platform <strong>NemuHUB</strong>.
                </p>
                <p style="margin: 0;">
                    Jika ada pertanyaan, silakan hubungi pusat bantuan atau kunjungi <a href="{{ config('app.url', '#') }}">NemuHUB Dashboard</a>.
                </p>
            </div>
        </div>
    </div>
</body>
</html>
