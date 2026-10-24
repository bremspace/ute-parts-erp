<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Purchase Order — {{ $po->no_po }}</title>
    <style>
        @page {
            size: A4 portrait;
            margin: 15mm;
        }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            font-size: 11pt;
            color: #111827;
            background: #f9fafb;
            margin: 0;
            padding: 20px;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            background: #fff;
            padding: 28px 32px;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            border-bottom: 2px solid #e5e7eb;
            padding-bottom: 16px;
            margin-bottom: 20px;
        }
        .brand-title {
            font-size: 18pt;
            font-weight: 800;
            color: #4f46e5;
            letter-spacing: -0.5px;
            margin: 0 0 4px 0;
        }
        .brand-subtitle {
            font-size: 9pt;
            color: #6b7280;
            margin: 0;
            line-height: 1.4;
        }
        .doc-title-box {
            text-align: right;
        }
        .doc-title {
            font-size: 16pt;
            font-weight: 800;
            color: #111827;
            margin: 0 0 4px 0;
            text-transform: uppercase;
        }
        .doc-badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 9pt;
            font-weight: 700;
            text-transform: uppercase;
            background: #e0e7ff;
            color: #3730a3;
        }
        .grid-info {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 24px;
        }
        .info-card {
            background: #f9fafb;
            border: 1px solid #e5e7eb;
            border-radius: 6px;
            padding: 12px 14px;
        }
        .info-card h4 {
            margin: 0 0 8px 0;
            font-size: 9pt;
            text-transform: uppercase;
            color: #6b7280;
            letter-spacing: 0.5px;
        }
        .info-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 4px;
            font-size: 9.5pt;
        }
        .info-label {
            color: #6b7280;
        }
        .info-value {
            font-weight: 600;
            color: #111827;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        th {
            background: #f3f4f6;
            color: #374151;
            font-size: 9pt;
            font-weight: 700;
            text-transform: uppercase;
            padding: 10px 12px;
            border-top: 1px solid #e5e7eb;
            border-bottom: 1px solid #e5e7eb;
            text-align: left;
        }
        td {
            padding: 10px 12px;
            border-bottom: 1px solid #e5e7eb;
            font-size: 9.5pt;
        }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .tabular { font-variant-numeric: tabular-nums; }
        .total-box {
            display: flex;
            justify-content: flex-end;
            margin-bottom: 28px;
        }
        .total-card {
            width: 280px;
            border: 1px solid #e5e7eb;
            border-radius: 6px;
            padding: 12px 16px;
            background: #fafafa;
        }
        .total-row {
            display: flex;
            justify-content: space-between;
            font-size: 11pt;
            font-weight: 800;
            color: #111827;
        }
        .notes-box {
            background: #fffbeb;
            border: 1px solid #fef3c7;
            border-radius: 6px;
            padding: 10px 14px;
            font-size: 9pt;
            color: #92400e;
            margin-bottom: 28px;
        }
        .signatures {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 20px;
            text-align: center;
            margin-top: 36px;
            page-break-inside: avoid;
        }
        .signature-box {
            padding-top: 60px;
            border-top: 1px dashed #9ca3af;
            font-size: 9pt;
            font-weight: 600;
        }
        .signature-title {
            font-size: 8.5pt;
            color: #6b7280;
            margin-bottom: 40px;
        }
        .action-bar {
            position: fixed;
            bottom: 20px;
            right: 20px;
            display: flex;
            gap: 10px;
            z-index: 100;
        }
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 10px 18px;
            border-radius: 8px;
            font-weight: 700;
            font-size: 12px;
            cursor: pointer;
            text-decoration: none;
            border: none;
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1), 0 2px 4px -1px rgba(0,0,0,0.06);
        }
        .btn-print { background: #4f46e5; color: #fff; }
        .btn-wa { background: #10b981; color: #fff; }
        .btn-close { background: #e5e7eb; color: #374151; }

        @media print {
            body { background: #fff; padding: 0; }
            .container { box-shadow: none; padding: 0; max-width: 100%; }
            .action-bar { display: none !important; }
        }
    </style>
</head>
<body>

<div class="action-bar">
    @if($po->supplier?->telepon)
        <a href="{{ $po->generateWaPesananUrl() }}" target="_blank" class="btn btn-wa">
            💬 Kirim WhatsApp
        </a>
    @endif
    <button onclick="window.print()" class="btn btn-print">
        🖨️ Cetak Dokumen
    </button>
    <button onclick="window.close()" class="btn btn-close">
        ✕ Tutup
    </button>
</div>

<div class="container">
    <!-- Header -->
    <div class="header">
        <div>
            <div class="brand-title">{{ $po->gudangTujuan?->cabang?->nama ?? 'UTE PARTS ERP' }}</div>
            <p class="brand-subtitle">
                Gudang &amp; Distribusi Sparepart Ponsel<br>
                {{ $po->gudangTujuan?->cabang?->alamat ?? 'Kantor Pusat & Gudang Utama' }}<br>
                Telp: {{ $po->gudangTujuan?->cabang?->telepon ?? '-' }}
            </p>
        </div>
        <div class="doc-title-box">
            <h1 class="doc-title">Purchase Order</h1>
            <div style="font-family: monospace; font-size: 13pt; font-weight: bold; margin-bottom: 4px;">
                {{ $po->no_po }}
            </div>
            <span class="doc-badge">{{ strtoupper($po->status) }}</span>
        </div>
    </div>

    <!-- Info Grid -->
    <div class="grid-info">
        <div class="info-card">
            <h4>Tujuan Pesanan (Kepada Supplier)</h4>
            <div class="info-row">
                <span class="info-label">Supplier:</span>
                <span class="info-value">{{ $po->supplier?->nama ?? '-' }}</span>
            </div>
            <div class="info-row">
                <span class="info-label">Kontak / Sales:</span>
                <span class="info-value">{{ $po->supplier?->kontak ?? '-' }}</span>
            </div>
            <div class="info-row">
                <span class="info-label">Telepon / WA:</span>
                <span class="info-value">{{ $po->supplier?->telepon ?? '-' }}</span>
            </div>
            <div class="info-row">
                <span class="info-label">Alamat:</span>
                <span class="info-value" style="max-width: 220px; text-align: right;">{{ $po->supplier?->alamat ?? '-' }}</span>
            </div>
        </div>

        <div class="info-card">
            <h4>Detail Pengiriman &amp; Syarat</h4>
            <div class="info-row">
                <span class="info-label">Tanggal Order:</span>
                <span class="info-value">{{ $po->created_at ? $po->created_at->format('d/m/Y H:i') : date('d/m/Y') }}</span>
            </div>
            <div class="info-row">
                <span class="info-label">Gudang Tujuan:</span>
                <span class="info-value">{{ $po->gudangTujuan?->nama ?? '-' }}</span>
            </div>
            <div class="info-row">
                <span class="info-label">Metode Pembayaran:</span>
                <span class="info-value" style="text-transform: uppercase;">{{ $po->metode_bayar }}</span>
            </div>
            <div class="info-row">
                <span class="info-label">Jatuh Tempo:</span>
                <span class="info-value">{{ $po->jatuh_tempo ? $po->jatuh_tempo->format('d/m/Y') : ($po->metode_bayar === 'tunai' ? 'Tunai / COD' : '-') }}</span>
            </div>
        </div>
    </div>

    <!-- Items Table -->
    <table>
        <thead>
            <tr>
                <th style="width: 30px;" class="text-center">No</th>
                <th>Deskripsi Part / Barang</th>
                <th style="width: 110px;">SKU / Part No</th>
                <th style="width: 70px;" class="text-right">Qty</th>
                <th style="width: 60px;">Satuan</th>
                <th style="width: 120px;" class="text-right">Harga Beli (Rp)</th>
                <th style="width: 130px;" class="text-right">Total (Rp)</th>
            </tr>
        </thead>
        <tbody>
            @forelse($po->items as $idx => $item)
                @php
                    $prod = $item->produk;
                @endphp
                <tr>
                    <td class="text-center">{{ $idx + 1 }}</td>
                    <td>
                        <div style="font-weight: 700; color: #111827;">{{ $prod?->nama ?? ('Produk #' . $item->produk_id) }}</div>
                        @if($prod?->barcode)
                            <div style="font-size: 8pt; color: #6b7280; font-family: monospace;">Barcode: {{ $prod->barcode }}</div>
                        @endif
                    </td>
                    <td style="font-family: monospace;">{{ $prod?->sku ?? '-' }}</td>
                    <td class="text-right tabular" style="font-weight: 700;">{{ number_format($item->jumlah) }}</td>
                    <td>{{ $item->satuan ?: ($prod?->satuan ?: 'pcs') }}</td>
                    <td class="text-right tabular">Rp {{ number_format((float) $item->harga_beli, 0, ',', '.') }}</td>
                    <td class="text-right tabular" style="font-weight: 700;">Rp {{ number_format((float) $item->subtotal, 0, ',', '.') }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="text-center" style="padding: 20px; color: #9ca3af;">Tidak ada item dalam PO ini.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <!-- Total -->
    <div class="total-box">
        <div class="total-card">
            <div class="total-row">
                <span>TOTAL ESTIMASI:</span>
                <span class="tabular">Rp {{ number_format((float) $po->total, 0, ',', '.') }}</span>
            </div>
        </div>
    </div>

    <!-- Catatan & Ketentuan -->
    @if($po->catatan)
        <div class="notes-box">
            <strong>Catatan Pembelian:</strong> {{ $po->catatan }}
        </div>
    @endif

    <div style="font-size: 8.5pt; color: #6b7280; line-height: 1.5; margin-bottom: 24px;">
        <strong>Ketentuan Penerimaan:</strong><br>
        1. Harap sertakan Surat Jalan dan Salinan Purchase Order ini saat pengiriman fisik barang ke gudang tujuan.<br>
        2. Tim Quality Control berhak menolak barang bila tidak sesuai spesifikasi, kemasan rusak, atau cacat pabrik.<br>
        3. Pembayaran tempo diproses berdasarkan Berita Acara Penerimaan Barang (GRN) yang telah disetujui.
    </div>

    <!-- Signatures -->
    <div class="signatures">
        <div>
            <div class="signature-title">Dibuat Oleh (Purchasing)</div>
            <div class="signature-box">( ............................................ )</div>
        </div>
        <div>
            <div class="signature-title">Disetujui Oleh (Pimpinan)</div>
            <div class="signature-box">( ............................................ )</div>
        </div>
        <div>
            <div class="signature-title">Diterima Oleh (Supplier)</div>
            <div class="signature-box">{{ $po->supplier?->nama ?? '( ............................................ )' }}</div>
        </div>
    </div>
</div>

</body>
</html>
