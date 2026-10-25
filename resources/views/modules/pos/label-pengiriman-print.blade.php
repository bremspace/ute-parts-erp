@php
    $labelConfig = app(\App\Modules\Crm\Services\KonfigurasiService::class)->getLabelPengirimanConfig();
    $printHeader = app(\App\Modules\Crm\Services\KonfigurasiService::class)->getPrintHeader();
    $qrService = app(\App\Modules\Wms\Services\QrCodeService::class);

    $paperSize = request('paper', $labelConfig['paper_size'] ?? '100x150');
    $marginMm = (int) request('margin', $labelConfig['margin_mm'] ?? 5);
    $showSender = (bool) ($labelConfig['show_sender'] ?? true);
    $showItemList = (bool) ($labelConfig['show_item_list'] ?? true);
    $showQrResi = (bool) ($labelConfig['show_qr_resi'] ?? true);
    $showFragile = (bool) ($labelConfig['show_fragile_warning'] ?? true);
    $catatanPengiriman = $labelConfig['catatan_pengiriman'] ?? 'FRAGILE: Barang Elektronik & Sparepart HP. Jangan dibanting.';

    $isPickup = str_contains(strtolower($transaksi->catatan ?? ''), 'ambil_ke_toko');
    $kurir = $isPickup ? 'AMBIL DI TOKO (PICKUP)' : ($transaksi->pengiriman?->kurir ?? 'EKSPEDISI / REGULER');
    $resi = $transaksi->pengiriman?->tracking_id ?? ($transaksi->no_transaksi);
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Label Pengiriman — {{ $transaksi->no_transaksi }}</title>
    <style>
        @page {
            size: {{ $paperSize === '100x150' ? '100mm 150mm' : ($paperSize === '80x100' ? '80mm 100mm' : ($paperSize === '80mm' ? '80mm auto' : '105mm 148mm')) }};
            margin: {{ $marginMm }}mm;
        }
        * {
            box-sizing: border-box;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            color: #000;
            background: #f3f4f6;
            margin: 0;
            padding: 12px;
            line-height: 1.25;
        }
        .container {
            width: 100%;
            max-width: {{ $paperSize === '100x150' ? '96mm' : ($paperSize === '80x100' ? '78mm' : ($paperSize === '80mm' ? '78mm' : '100mm')) }};
            margin: 0 auto;
            background: #fff;
            padding: 10px;
            border: 2px solid #000;
            border-radius: 4px;
        }
        .courier-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 2px solid #000;
            padding-bottom: 6px;
            margin-bottom: 6px;
        }
        .courier-badge {
            font-size: 13pt;
            font-weight: 900;
            text-transform: uppercase;
            letter-spacing: -0.5px;
        }
        .courier-type {
            font-size: 8pt;
            font-weight: 700;
            background: #000;
            color: #fff;
            padding: 2px 6px;
            border-radius: 3px;
        }
        .resi-box {
            text-align: center;
            border-bottom: 2px dashed #000;
            padding-bottom: 8px;
            margin-bottom: 8px;
        }
        .resi-code {
            font-family: monospace;
            font-size: 11pt;
            font-weight: 800;
            margin-top: 4px;
            letter-spacing: 1px;
        }
        .address-box {
            border-bottom: 1px solid #000;
            padding-bottom: 6px;
            margin-bottom: 6px;
            font-size: 8.5pt;
        }
        .address-title {
            font-size: 7pt;
            font-weight: 800;
            text-transform: uppercase;
            color: #4b5563;
        }
        .address-name {
            font-size: 9.5pt;
            font-weight: 800;
            margin: 2px 0;
        }
        .items-box {
            font-size: 8pt;
            border-bottom: 1px solid #000;
            padding-bottom: 6px;
            margin-bottom: 6px;
        }
        .fragile-box {
            border: 1.5px solid #000;
            background: #000;
            color: #fff;
            padding: 4px;
            text-align: center;
            font-size: 7.5pt;
            font-weight: 800;
            text-transform: uppercase;
            margin-top: 6px;
            letter-spacing: 0.5px;
        }
        .action-bar {
            background: #1f2937;
            color: #fff;
            padding: 8px 14px;
            border-radius: 8px;
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            font-size: 11px;
            max-width: 500px;
            margin-left: auto;
            margin-right: auto;
        }
        .btn {
            border: none;
            padding: 6px 12px;
            border-radius: 6px;
            cursor: pointer;
            font-weight: bold;
            font-size: 11px;
            text-decoration: none;
        }
        .btn-print { background: #4f46e5; color: #fff; }
        .btn-close { background: #4b5563; color: #fff; }
        .action-select {
            background: #374151;
            color: #fff;
            border: 1px solid #4b5563;
            border-radius: 6px;
            padding: 4px 8px;
            font-size: 11px;
        }
        @media print {
            body { background: #fff; padding: 0; margin: 0; }
            .container { border: 2px solid #000; box-shadow: none; border-radius: 0; }
            .action-bar { display: none !important; }
        }
    </style>
</head>
<body>

<div class="action-bar">
    <span>Kertas:</span>
    <select class="action-select" onchange="location.href='?paper='+this.value+'&margin={{ $marginMm }}'">
        <option value="100x150" {{ $paperSize === '100x150' ? 'selected' : '' }}>100 × 150 mm (Thermal Ekspedisi)</option>
        <option value="80x100" {{ $paperSize === '80x100' ? 'selected' : '' }}>80 × 100 mm (Label Box)</option>
        <option value="80mm" {{ $paperSize === '80mm' ? 'selected' : '' }}>80 mm Roll Continuous</option>
    </select>
    <button onclick="window.print()" class="btn btn-print">🖨️ Cetak Label</button>
    <button onclick="window.close()" class="btn btn-close">✕ Tutup</button>
</div>

<div class="container">
    <div class="courier-header">
        <div class="courier-badge">{{ $kurir }}</div>
        <div class="courier-type">{{ $isPickup ? 'SELF-PICKUP' : 'STANDARD PACK' }}</div>
    </div>

    @if($showQrResi && !empty($resi))
        <div class="resi-box">
            <div style="display:flex; justify-content:center; align-items:center;">
                {!! $qrService->generateSvg($resi, 75, 0) !!}
            </div>
            <div class="resi-code">{{ $resi }}</div>
            <div style="font-size:7pt; color:#6b7280;">No. TRX: {{ $transaksi->no_transaksi }}</div>
        </div>
    @endif

    <!-- Penerima -->
    <div class="address-box">
        <div class="address-title">Penerima (Tujuan):</div>
        <div class="address-name">{{ $transaksi->pelanggan?->nama ?? 'Pelanggan Umum' }}</div>
        <div>Telp/WA: <strong>{{ $transaksi->pengiriman?->telepon_tujuan ?? $transaksi->pelanggan?->telepon ?? '-' }}</strong></div>
        <div style="margin-top:2px;">{{ $isPickup ? 'Ambil di Toko / Counter Cabang' : ($transaksi->pengiriman?->alamat_tujuan ?? $transaksi->pelanggan?->alamat ?? 'Alamat pengiriman belum diisi') }}</div>
    </div>

    <!-- Pengirim -->
    @if($showSender)
        <div class="address-box">
            <div class="address-title">Pengirim:</div>
            <div class="address-name" style="font-size:8.5pt;">{{ $transaksi->cabang?->nama ?? ($printHeader['nama_toko'] ?? 'UTE PARTS') }}</div>
            <div>Telp: {{ $transaksi->cabang?->telepon ?? ($printHeader['telepon'] ?? '-') }}</div>
            <div>{{ $transaksi->cabang?->alamat ?? ($printHeader['alamat'] ?? 'Kantor Pusat') }}</div>
        </div>
    @endif

    <!-- Isi Paket -->
    @if($showItemList)
        <div class="items-box">
            <div class="address-title" style="margin-bottom:3px;">Isi Paket ({{ $transaksi->items->count() }} macam item):</div>
            <ul style="margin:0; padding-left:14px;">
                @foreach($transaksi->items as $it)
                    <li>{{ $it->produk?->nama ?? 'Produk' }} <strong>×{{ (int)$it->jumlah }}</strong></li>
                @endforeach
            </ul>
        </div>
    @endif

    <div style="font-size:7.5pt; display:flex; justify-content:space-between; margin-top:4px;">
        <span>Bayar: <strong>{{ strtoupper($transaksi->metode_bayar ?? 'ONLINE') }}</strong></span>
        <span>Total: <strong>Rp {{ number_format((float)$transaksi->total_akhir, 0, ',', '.') }}</strong></span>
    </div>

    @if($showFragile)
        <div class="fragile-box">
            ⚠️ {{ $catatanPengiriman }}
        </div>
    @endif
</div>

</body>
</html>
