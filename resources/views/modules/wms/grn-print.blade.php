@php
    $wmsConfig = app(\App\Modules\Crm\Services\KonfigurasiService::class)->getWmsDocConfig();
    $printHeader = app(\App\Modules\Crm\Services\KonfigurasiService::class)->getPrintHeader();
    $paperSize = request('paper', $wmsConfig['paper_size'] ?? 'a5');
    $orientation = request('orient', $wmsConfig['orientation'] ?? 'portrait');
    $marginMm = (int) request('margin', $wmsConfig['margin_mm'] ?? 8);
    $fontSize = request('font', $wmsConfig['font_size'] ?? '10pt');
    $showKop = (bool) ($wmsConfig['show_kop'] ?? true);
    $showTtd = (bool) ($wmsConfig['show_ttd'] ?? true);
    $po = $grn->purchaseOrder;
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bukti Penerimaan Barang (GRN) — {{ $grn->no_grn }}</title>
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
            border-bottom: 2px solid #10b981;
            padding-bottom: 10px;
            margin-bottom: 12px;
        }
        .brand-title {
            font-size: 14pt;
            font-weight: 800;
            color: #047857;
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
            font-size: 12.5pt;
            font-weight: 800;
            color: #111827;
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
            background: #d1fae5;
            color: #065f46;
            border: 1px solid #a7f3d0;
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
            background: #f3f4f6;
            color: #374151;
            font-size: 8pt;
            font-weight: 700;
            text-transform: uppercase;
            padding: 6px 8px;
            border-top: 1px solid #e5e7eb;
            border-bottom: 1px solid #e5e7eb;
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
        .notes-card {
            background: #f0fdf4;
            border: 1px solid #bbf7d0;
            border-radius: 5px;
            padding: 6px 10px;
            font-size: 8pt;
            color: #166534;
            margin-bottom: 14px;
        }
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
        .btn-print { background: #059669; color: #fff; }
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
    <button onclick="window.print()" class="btn btn-print">
        🖨️ Cetak GRN
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
                <div class="brand-title">{{ $grn->gudang?->cabang?->nama ?? $po?->gudangTujuan?->cabang?->nama ?? ($printHeader['nama_toko'] ?? config('app.name', 'BREMSPACE')) }}</div>
                <p class="brand-subtitle">
                    {{ $printHeader['slogan'] ?? 'Gudang Logistik & Penerimaan Barang' }}<br>
                    {{ $grn->gudang?->cabang?->alamat ?? ($printHeader['alamat'] ?? 'Kantor Cabang & Gudang Logistik') }}<br>
                    Telp/WA: {{ $grn->gudang?->cabang?->telepon ?? ($printHeader['telepon'] ?? '-') }}
                </p>
            </div>
            <div class="doc-title-box">
                <h1 class="doc-title">Bukti Terima Barang</h1>
                <div style="font-family: monospace; font-size: 11pt; font-weight: bold; margin-bottom: 2px;">
                    {{ $grn->no_grn }}
                </div>
                <span class="doc-badge">{{ strtoupper($grn->status) }}</span>
            </div>
        </div>
    @endif

    <!-- Info Grid -->
    <div class="grid-info">
        <div class="info-card">
            <h4>Penerimaan &amp; Gudang</h4>
            <div class="info-row">
                <span class="info-label">No. Purchase Order:</span>
                <span class="info-value font-mono">{{ $po?->no_po ?? '-' }}</span>
            </div>
            <div class="info-row">
                <span class="info-label">Gudang Tujuan:</span>
                <span class="info-value">{{ $grn->gudang?->nama ?? '-' }}</span>
            </div>
            <div class="info-row">
                <span class="info-label">Penerima Gudang:</span>
                <span class="info-value">{{ $grn->user?->name ?? 'Staf Gudang' }}</span>
            </div>
            <div class="info-row">
                <span class="info-label">Waktu Terima:</span>
                <span class="info-value">{{ $grn->created_at->format('d/m/Y H:i') }}</span>
            </div>
        </div>

        <div class="info-card">
            <h4>Supplier &amp; Status Pembayaran</h4>
            <div class="info-row">
                <span class="info-label">Supplier Pengirim:</span>
                <span class="info-value">{{ $po?->supplier?->nama ?? '-' }}</span>
            </div>
            <div class="info-row">
                <span class="info-label">Metode Pembayaran:</span>
                <span class="info-value" style="color: {{ ($po?->metode_bayar ?? 'kredit') === 'tunai' ? '#059669' : '#d97706' }};">
                    {{ ($po?->metode_bayar ?? 'kredit') === 'tunai' ? 'TUNAI LANGSUNG' : 'KREDIT / TEMPO' }}
                </span>
            </div>
            @if(($po?->metode_bayar ?? 'kredit') === 'tunai')
                <div class="info-row">
                    <span class="info-label">Kas / Bank:</span>
                    <span class="info-value font-mono">{{ $grn->akun_kas_bank ?: ($po?->akun_kas_bank ?: '110-01') }}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Dibayar Oleh:</span>
                    <span class="info-value">{{ $grn->dibayar_oleh ?: 'Kasir / Staf' }}</span>
                </div>
            @else
                <div class="info-row">
                    <span class="info-label">Jatuh Tempo:</span>
                    <span class="info-value">{{ $po?->jatuh_tempo ? $po->jatuh_tempo->format('d/m/Y') : '-' }}</span>
                </div>
            @endif
        </div>
    </div>

    <!-- Items Table -->
    <table>
        <thead>
            <tr>
                <th style="width: 25px;" class="text-center">No</th>
                <th>Deskripsi Produk &amp; SKU</th>
                <th style="width: 75px;" class="text-center">Lokasi Rak</th>
                <th style="width: 60px;" class="text-center">Qty PO</th>
                <th style="width: 65px;" class="text-center">Qty Masuk</th>
                <th style="width: 65px;" class="text-center">Kondisi</th>
            </tr>
        </thead>
        <tbody>
            @forelse($grn->item_qty_received ?? [] as $index => $item)
                <tr>
                    <td class="text-center tabular">{{ $index + 1 }}</td>
                    <td>
                        <div style="font-weight: 700; color: #111827;">{{ $item['produk_nama'] ?? ('Produk #'.$item['produk_id']) }}</div>
                        @if(!empty($item['sku']))
                            <div style="font-size: 7.5pt; font-family: monospace; color: #4b5563;">SKU: {{ $item['sku'] }}</div>
                        @endif
                    </td>
                    <td class="text-center" style="font-size: 8pt; font-family: monospace;">
                        @if(!empty($item['rak_id']))
                            @php $rk = \App\Modules\Wms\Models\Rak::find($item['rak_id']); @endphp
                            {{ $rk ? $rk->kode : 'Rak #'.$item['rak_id'] }}
                        @else
                            -
                        @endif
                    </td>
                    <td class="text-center tabular">{{ $item['qty_po'] ?? '-' }}</td>
                    <td class="text-center tabular font-bold" style="color: #059669;">
                        {{ $item['qty_received'] ?? 0 }}
                    </td>
                    <td class="text-center">
                        <span style="font-size: 7.5pt; font-weight: 700; text-transform: uppercase; color: {{ ($item['status'] ?? '') === 'lengkap' ? '#059669' : '#d97706' }};">
                            {{ $item['status'] ?? 'lengkap' }}
                        </span>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="text-center" style="padding: 16px; color: #6b7280;">Tidak ada rincian item dalam dokumen GRN ini.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <!-- Notes Card -->
    <div class="notes-card">
        <strong>Catatan Penerimaan Gudang:</strong><br>
        {{ $grn->catatan ?: 'Semua fisik barang telah diperiksa dan dicocokkan dengan surat jalan serta di-input ke stok gudang.' }}
        @if(($po?->metode_bayar ?? 'kredit') === 'tunai')
            <div style="margin-top: 3px; font-weight: 600;">
                ✓ Pembayaran tunai telah dikonfirmasi diselesaikan via kas {{ $grn->akun_kas_bank ?: '110-01' }} oleh {{ $grn->dibayar_oleh ?: 'Kasir' }}.
            </div>
        @endif
    </div>

    @if($showTtd)
        <!-- Signatures -->
        <div class="signatures">
            <div>
                <div class="signature-title">Petugas Penerima</div>
                <div class="signature-box">{{ $grn->user?->name ?? 'Staff Gudang' }}</div>
            </div>
            <div>
                <div class="signature-title">Mengetahui Kepala Cabang</div>
                <div class="signature-box">Kepala Cabang / Spv</div>
            </div>
            <div>
                <div class="signature-title">Kurir / Pengantar Supplier</div>
                <div class="signature-box">Nama Jelas &amp; Tanda Tangan</div>
            </div>
        </div>
    @endif
</div>

</body>
</html>
