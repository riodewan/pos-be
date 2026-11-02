@extends('emails.layout')

@section('content')
    <div style="display: inline-block; background-color: #FEF2F2; color: #DC2626; font-weight: 700; font-size: 11px; padding: 4px 10px; border-radius: 6px; margin-bottom: 12px;">
        PERINGATAN INVENTORI
    </div>

    <h1>Peringatan Stok Bahan Baku Menipis</h1>
    
    <p>Halo <strong>{{ $managerName ?? 'Manager Inventori' }}</strong>,</p>
    
    <p>
        Sistem mendeteksi ada bahan baku di outlet <strong>{{ $outletName ?? 'Outlet' }}</strong> yang telah mencapai atau berada di bawah batas minimum (*safety stock*). Segera lakukan pengadaan agar operasional kasir dan dapur tidak terganggu.
    </p>

    <!-- Table of low stock items -->
    <div style="margin: 20px 0; border: 1px solid #FCA5A5; border-radius: 12px; overflow: hidden;">
        <table style="width: 100%; border-collapse: collapse; font-size: 13px; text-align: left;">
            <thead style="background-color: #FEF2F2; color: #991B1B; font-size: 11px; text-transform: uppercase;">
                <tr>
                    <th style="padding: 10px 14px;">Bahan Baku</th>
                    <th style="padding: 10px 14px;">Sisa Stok</th>
                    <th style="padding: 10px 14px; text-align: right;">Batas Min.</th>
                </tr>
            </thead>
            <tbody style="background-color: #FFFFFF;">
                @forelse ($lowStockItems ?? [] as $item)
                <tr style="border-bottom: 1px solid #FEE2E2;">
                    <td style="padding: 12px 14px;">
                        <strong style="color: #111827;">{{ $item['name'] }}</strong>
                        <div style="font-size: 11px; color: #6B7280; font-family: monospace;">{{ $item['sku'] }}</div>
                    </td>
                    <td style="padding: 12px 14px; color: #DC2626; font-weight: bold; font-family: monospace;">
                        {{ number_format($item['current_stock'], 1) }} {{ $item['unit'] }}
                    </td>
                    <td style="padding: 12px 14px; text-align: right; color: #6B7280; font-family: monospace;">
                        {{ number_format($item['min_stock'], 1) }} {{ $item['unit'] }}
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="3" style="padding: 16px; text-align: center; color: #6B7280;">
                        Tidak ada bahan baku yang di bawah batas minimum saat ini.
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div style="text-align: center; margin: 24px 0;">
        <a href="{{ $inventoryUrl ?? '#' }}" class="btn-primary" target="_blank">
            Buat Purchase Order (PO) Bahan
        </a>
    </div>

    <p style="font-size: 12px; color: #6B7280;">
        Tips: Anda dapat memperbarui formula batas minimum bahan baku pada menu <strong>Inventori ➔ Bahan Baku</strong> di dashboard NemuHUB.
    </p>
@endsection

