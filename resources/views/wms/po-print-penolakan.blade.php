<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Berita Acara Penolakan Barang — {{ $po->no_po }}</title>
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
            border-bottom: 2px solid #ef4444;
            padding-bottom: 16px;
            margin-bottom: 20px;
        }
        .brand-title {
            font-size: 18pt;
            font-weight: 800;
            color: #111827;
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
            font-size: 15pt;
            font-weight: 800;
            color: #dc2626;
            margin: 0 0 4px 0;
            text-transform: uppercase;
        }
        .doc-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 4px;
            font-size: 9.5pt;
            font-weight: 700;
            text-transform: uppercase;
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #f87171;
        }
        .alert-box {
            background: #fef2f2;
            border: 1px solid #fca5a5;
            border-radius: 8px;
            padding: 14px 16px;
            color: #991b1b;
            font-size: 9.5pt;
            line-height: 1.5;
            margin-bottom: 20px;
        }
        .grid-info {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
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
        .signatures {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 20px;
            text-align: center;
            margin-top: 40px;
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
        .btn-print { background: #dc2626; color: #fff; }
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
        <a href="{{ $po->generateWaPenolakanUrl() }}" target="_blank" class="btn btn-wa">
            💬 Kirim WA Penolakan
        </a>
    @endif
    <button onclick="window.print()" class="btn btn-print">
        🖨️ Cetak Berita Acara
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
                Departemen Logistik &amp; Quality Control Gudang<br>
                {{ $po->gudangTujuan?->cabang?->alamat ?? 'Kantor Pusat & Gudang Utama' }}<br>
                Telp: {{ $po->gudangTujuan?->cabang?->telepon ?? '-' }}
            </p>
        </div>
        <div class="doc-title-box">
            <h1 class="doc-title">Berita Acara Penolakan</h1>
            <div style="font-family: monospace; font-size: 11pt; color: #4b5563; margin-bottom: 4px;">
                Ref. PO: <strong>{{ $po->no_po }}</strong>
            </div>
            <span class="doc-badge">BARANG DITOLAK / BATAL</span>
        </div>
    </div>

    <!-- Pernyataan Penolakan -->
    <div class="alert-box">
        <strong>PEMBERITAHUAN RESMI PENOLAKAN PENERIMAAN BARANG:</strong><br>
        Berdasarkan hasil pemeriksaan fisik dan Quality Control saat pengiriman tiba di gudang kami, dinyatakan bahwa kiriman untuk pesanan tersebut di bawah ini <strong>TIDAK DAPAT DITERIMA (DITOLAK)</strong> dan seluruh fisik barang dikembalikan kepada pihak Supplier / Ekspedisi pengirim.
    </div>

    <!-- Info Grid -->
    <div class="grid-info">
        <div class="info-card">
            <h4>Data Supplier / Pengirim</h4>
            <div class="info-row">
                <span class="info-label">Nama Supplier:</span>
                <span class="info-value">{{ $po->supplier?->nama ?? '-' }}</span>
            </div>
            <div class="info-row">
                <span class="info-label">Telepon / WhatsApp:</span>
                <span class="info-value">{{ $po->supplier?->telepon ?? '-' }}</span>
            </div>
            <div class="info-row">
                <span class="info-label">Alamat Supplier:</span>
                <span class="info-value" style="max-width: 220px; text-align: right;">{{ $po->supplier?->alamat ?? '-' }}</span>
            </div>
        </div>

        <div class="info-card">
            <h4>Detail Dokumen &amp; Lokasi</h4>
            <div class="info-row">
                <span class="info-label">Nomor PO:</span>
                <span class="info-value" style="font-family: monospace;">{{ $po->no_po }}</span>
            </div>
            <div class="info-row">
                <span class="info-label">Tanggal Penolakan:</span>
                <span class="info-value">{{ now()->format('d/m/Y H:i') }} WIB</span>
            </div>
            <div class="info-row">
                <span class="info-label">Gudang Pemeriksa:</span>
                <span class="info-value">{{ $po->gudangTujuan?->nama ?? '-' }}</span>
            </div>
            <div class="info-row">
                <span class="info-label">Status Sistem:</span>
                <span class="info-value" style="color: #dc2626;">DIBATALKAN</span>
            </div>
        </div>
    </div>

    <!-- Catatan Alasan Penolakan -->
    <div style="background: #fff; border: 2px solid #ef4444; border-radius: 8px; padding: 14px 16px; margin-bottom: 24px;">
        <h4 style="margin: 0 0 6px 0; color: #dc2626; font-size: 10pt; text-transform: uppercase;">Alasan Penolakan Kiriman:</h4>
        <div style="font-size: 11pt; font-weight: 600; color: #111827; line-height: 1.5;">
            {{ $po->catatan ?: 'Ketidaksesuaian fisik barang, kemasan rusak, atau spesifikasi tidak sesuai dengan Purchase Order saat diterima di pintu gudang.' }}
        </div>
    </div>

    <!-- Daftar Item Terkait -->
    <h4 style="margin: 0 0 8px 0; font-size: 10pt; text-transform: uppercase; color: #374151;">Daftar Item dalam Purchase Order Terkait:</h4>
    <table>
        <thead>
            <tr>
                <th style="width: 30px;" class="text-center">No</th>
                <th>Nama Part / Produk</th>
                <th style="width: 120px;">SKU</th>
                <th style="width: 80px;" class="text-right">Qty PO</th>
                <th style="width: 70px;">Satuan</th>
                <th style="width: 120px;" class="text-center">Status Barang</th>
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
                    </td>
                    <td style="font-family: monospace;">{{ $prod?->sku ?? '-' }}</td>
                    <td class="text-right tabular" style="font-weight: 700;">{{ number_format($item->jumlah) }}</td>
                    <td>{{ $item->satuan ?: ($prod?->satuan ?: 'pcs') }}</td>
                    <td class="text-center">
                        <span style="color: #dc2626; font-weight: 700; font-size: 8.5pt;">DITOLAK KEMBALI</span>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="text-center" style="padding: 20px; color: #9ca3af;">Tidak ada item data.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <!-- Keterangan Finansial & Administrasi -->
    <div style="font-size: 8.5pt; color: #4b5563; line-height: 1.5; margin-bottom: 24px; border-left: 3px solid #ef4444; padding-left: 10px;">
        <strong>Catatan Administrasi &amp; Keuangan:</strong><br>
        1. Dengan diterbitkannya Berita Acara ini, dokumen PO berstatus <strong>DIBATALKAN</strong>.<br>
        2. <strong>Tidak ada barang yang masuk</strong> ke stok fisik maupun sistem inventori gudang Ute Parts.<br>
        3. <strong>Tidak ada Utang Usaha (AP)</strong> atau kewajiban pembayaran yang timbul atas pengiriman ini.<br>
        4. Supplier wajib menarik kembali barang atau melakukan pengiriman ulang unit pengganti yang sesuai melalui penerbitan PO baru.
    </div>

    <!-- Signatures -->
    <div class="signatures">
        <div>
            <div class="signature-title">Pemeriksa Gudang (QC / Penerima)</div>
            <div class="signature-box">( ............................................ )</div>
        </div>
        <div>
            <div class="signature-title">Mengetahui (Kepala Gudang)</div>
            <div class="signature-box">( ............................................ )</div>
        </div>
        <div>
            <div class="signature-title">Pembawa Barang (Kurir / Ekspedisi)</div>
            <div class="signature-box">( ............................................ )</div>
        </div>
    </div>
</div>

</body>
</html>
