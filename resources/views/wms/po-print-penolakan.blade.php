@php
    $wmsConfig = app(\App\Modules\Crm\Services\KonfigurasiService::class)->getWmsDocConfig();
    $printHeader = app(\App\Modules\Crm\Services\KonfigurasiService::class)->getPrintHeader();
    $paperSize = request('paper', $wmsConfig['paper_size'] ?? 'a5');
    $orientation = request('orient', $wmsConfig['orientation'] ?? 'portrait');
    $marginMm = (int) request('margin', $wmsConfig['margin_mm'] ?? 8);
    $fontSize = request('font', $wmsConfig['font_size'] ?? '10pt');
    $showKop = (bool) ($wmsConfig['show_kop'] ?? true);
    $showTtd = (bool) ($wmsConfig['show_ttd'] ?? true);
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Berita Acara Penolakan Barang — {{ $po->no_po }}</title>
    <style>
        @page {
            size: {{ $paperSize }} {{ $orientation }};
            margin: {{ $marginMm }}mm;
        }
        * {
            box-sizing: border-box;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            font-size: {{ $fontSize }};
            color: #111827;
            background: #f3f4f6;
            margin: 0;
            padding: 12px;
            line-height: 1.35;
        }
        .container {
            width: 100%;
            max-width: {{ $paperSize === 'a5' && $orientation === 'portrait' ? '148mm' : ($paperSize === 'a5' ? '210mm' : '210mm') }};
            margin: 0 auto;
            background: #fff;
            padding: 14px 16px;
            border-radius: 6px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            border-bottom: 2px solid #ef4444;
            padding-bottom: 10px;
            margin-bottom: 12px;
        }
        .brand-title {
            font-size: 14pt;
            font-weight: 800;
            color: #111827;
            letter-spacing: -0.3px;
            margin: 0 0 2px 0;
        }
        .brand-subtitle {
            font-size: 8pt;
            color: #4b5563;
            margin: 0;
            line-height: 1.3;
        }
        .doc-title-box {
            text-align: right;
        }
        .doc-title {
            font-size: 12pt;
            font-weight: 800;
            color: #dc2626;
            margin: 0 0 2px 0;
            text-transform: uppercase;
        }
        .doc-badge {
            display: inline-block;
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 8pt;
            font-weight: 700;
            text-transform: uppercase;
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #f87171;
        }
        .alert-box {
            background: #fef2f2;
            border: 1px solid #fecaca;
            border-left: 4px solid #ef4444;
            border-radius: 5px;
            padding: 8px 10px;
            margin-bottom: 12px;
            font-size: 8pt;
            color: #991b1b;
        }
        .alert-box strong {
            font-size: 8.5pt;
            display: block;
            margin-bottom: 2px;
        }
        .grid-info {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            margin-bottom: 12px;
        }
        .info-card {
            background: #f9fafb;
            border: 1px solid #e5e7eb;
            border-radius: 5px;
            padding: 8px 10px;
        }
        .info-card h4 {
            margin: 0 0 6px 0;
            font-size: 8pt;
            text-transform: uppercase;
            color: #6b7280;
            letter-spacing: 0.5px;
            border-bottom: 1px dashed #e5e7eb;
            padding-bottom: 3px;
        }
        .info-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 3px;
            font-size: 8.5pt;
        }
        .info-row:last-child {
            margin-bottom: 0;
        }
        .info-label {
            color: #6b7280;
        }
        .info-value {
            font-weight: 600;
            color: #111827;
            text-align: right;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 12px;
        }
        th {
            background: #fef2f2;
            color: #991b1b;
            font-size: 8pt;
            font-weight: 700;
            text-transform: uppercase;
            padding: 6px 8px;
            border-top: 1px solid #fecaca;
            border-bottom: 1px solid #fecaca;
            text-align: left;
        }
        td {
            padding: 6px 8px;
            border-bottom: 1px solid #f3f4f6;
            font-size: 8.5pt;
            vertical-align: top;
        }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .tabular { font-variant-numeric: tabular-nums; }
        .signatures {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 10px;
            text-align: center;
            margin-top: 18px;
            page-break-inside: avoid;
        }
        .signature-box {
            padding-top: 42px;
            border-top: 1px dashed #9ca3af;
            font-size: 8pt;
            font-weight: 600;
            color: #374151;
        }
        .signature-title {
            font-size: 7.5pt;
            color: #6b7280;
            margin-bottom: 30px;
            text-transform: uppercase;
        }
        .action-bar {
            position: fixed;
            top: 12px;
            right: 12px;
            display: flex;
            align-items: center;
            gap: 8px;
            background: #111827;
            padding: 8px 12px;
            border-radius: 12px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.3);
            z-index: 999;
            color: #fff;
            font-size: 11px;
        }
        .action-select {
            background: #1f2937;
            color: #fff;
            border: 1px solid #374151;
            padding: 5px 8px;
            border-radius: 6px;
            font-size: 11px;
            outline: none;
            cursor: pointer;
        }
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 6px 12px;
            border-radius: 6px;
            font-weight: 700;
            font-size: 11px;
            cursor: pointer;
            text-decoration: none;
            border: none;
            transition: opacity 0.2s;
        }
        .btn:hover { opacity: 0.9; }
        .btn-print { background: #dc2626; color: #fff; }
        .btn-wa { background: #10b981; color: #fff; }
        .btn-close { background: #374151; color: #e5e7eb; }

        @media print {
            body { background: #fff; padding: 0; margin: 0; }
            .container { box-shadow: none; padding: 0; max-width: 100% !important; border-radius: 0; }
            .action-bar { display: none !important; }
        }
    </style>
</head>
<body>

<div class="action-bar">
    <div style="font-weight: 600; margin-right: 4px;">Kertas:</div>
    <select class="action-select" onchange="location.href='?paper='+this.value+'&orient={{ $orientation }}&margin={{ $marginMm }}'">
        <option value="a5" {{ $paperSize === 'a5' ? 'selected' : '' }}>A5 (Setengah A4)</option>
        <option value="a4" {{ $paperSize === 'a4' ? 'selected' : '' }}>A4 Standar</option>
    </select>
    <select class="action-select" onchange="location.href='?paper={{ $paperSize }}&orient='+this.value+'&margin={{ $marginMm }}'">
        <option value="portrait" {{ $orientation === 'portrait' ? 'selected' : '' }}>Potret</option>
        <option value="landscape" {{ $orientation === 'landscape' ? 'selected' : '' }}>Lanskap</option>
    </select>
    @if($po->supplier?->telepon)
        <a href="{{ $po->generateWaPenolakanUrl() }}" target="_blank" class="btn btn-wa">
            💬 WA
        </a>
    @endif
    <button onclick="window.print()" class="btn btn-print">
        🖨️ Cetak
    </button>
    <button onclick="window.close()" class="btn btn-close">
        ✕
    </button>
</div>

<div class="container">
    @if($showKop)
        <!-- Header -->
        <div class="header">
            <div>
                <div class="brand-title">{{ $po->gudangTujuan?->cabang?->nama ?? ($printHeader['nama_toko'] ?? 'UTE PARTS') }}</div>
                <p class="brand-subtitle">
                    {{ $printHeader['slogan'] ?? 'Gudang & Distribusi Sparepart Ponsel' }}<br>
                    {{ $po->gudangTujuan?->cabang?->alamat ?? ($printHeader['alamat'] ?? 'Kantor Pusat & Gudang Utama') }}<br>
                    Telp/WA: {{ $po->gudangTujuan?->cabang?->telepon ?? ($printHeader['telepon'] ?? '-') }}
                </p>
            </div>
            <div class="doc-title-box">
                <h1 class="doc-title">Berita Acara Penolakan</h1>
                <div style="font-family: monospace; font-size: 11pt; font-weight: bold; margin-bottom: 2px;">
                    PO: {{ $po->no_po }}
                </div>
                <span class="doc-badge">DITOLAK / RETUR PENUH</span>
            </div>
        </div>
    @endif

    <!-- Alert Box -->
    <div class="alert-box">
        <strong>Pemberitahuan Resmi Penolakan Barang:</strong>
        Barang kiriman dari Purchase Order di bawah ini tidak dapat diterima ke dalam persediaan gudang karena tidak memenuhi standar fisik / segel / spesifikasi teknis dan telah dikembalikan secara penuh ke supplier / kurir pengantar.
    </div>

    <!-- Info Grid -->
    <div class="grid-info">
        <div class="info-card">
            <h4>Pihak Supplier (Pengirim)</h4>
            <div class="info-row">
                <span class="info-label">Nama Supplier:</span>
                <span class="info-value">{{ $po->supplier?->nama ?? '-' }}</span>
            </div>
            <div class="info-row">
                <span class="info-label">Kontak:</span>
                <span class="info-value">{{ $po->supplier?->kontak ?? '-' }}</span>
            </div>
            <div class="info-row">
                <span class="info-label">Telp / WA:</span>
                <span class="info-value">{{ $po->supplier?->telepon ?? '-' }}</span>
            </div>
        </div>

        <div class="info-card">
            <h4>Detail Verifikasi Gudang</h4>
            <div class="info-row">
                <span class="info-label">Tanggal Penolakan:</span>
                <span class="info-value">{{ date('d/m/Y H:i') }}</span>
            </div>
            <div class="info-row">
                <span class="info-label">Lokasi Gudang:</span>
                <span class="info-value">{{ $po->gudangTujuan?->nama ?? '-' }}</span>
            </div>
            <div class="info-row">
                <span class="info-label">Alasan Utama:</span>
                <span class="info-value" style="color: #dc2626;">{{ $po->catatan ?: 'Fisik rusak / segel cacat' }}</span>
            </div>
        </div>
    </div>

    <!-- Items Table -->
    <table>
        <thead>
            <tr>
                <th style="width: 25px;" class="text-center">No</th>
                <th>Item Produk / SKU yang Ditolak</th>
                <th style="width: 70px;" class="text-center">Qty PO</th>
                <th style="width: 80px;" class="text-center">Status</th>
            </tr>
        </thead>
        <tbody>
            @foreach($po->items as $index => $item)
                <tr>
                    <td class="text-center tabular">{{ $index + 1 }}</td>
                    <td>
                        <div style="font-weight: 700; color: #111827;">{{ $item->produk?->nama ?? '-' }}</div>
                        <div style="font-size: 7.5pt; font-family: monospace; color: #6b7280;">
                            SKU: {{ $item->skuVariant?->sku ?? $item->produk?->kode ?? '-' }}
                        </div>
                    </td>
                    <td class="text-center tabular font-bold">
                        {{ $item->jumlah }} {{ $item->satuan ?? 'pcs' }}
                    </td>
                    <td class="text-center">
                        <span style="color: #dc2626; font-weight: 700; font-size: 8pt;">DITOLAK 100%</span>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>

    @if($showTtd)
        <!-- Signatures -->
        <div class="signatures">
            <div>
                <div class="signature-title">Petugas QC Gudang</div>
                <div class="signature-box">Staff Pemeriksa Fisik</div>
            </div>
            <div>
                <div class="signature-title">Mengetahui Kepala Gudang</div>
                <div class="signature-box">Kepala Cabang / Gudang</div>
            </div>
            <div>
                <div class="signature-title">Kurir / Supir Ekspedisi</div>
                <div class="signature-box">Nama Jelas &amp; Tanda Tangan</div>
            </div>
        </div>
    @endif
</div>

</body>
</html>
