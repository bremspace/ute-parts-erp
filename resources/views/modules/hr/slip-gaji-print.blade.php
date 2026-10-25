@php
    $slipConfig = app(\App\Modules\Crm\Services\KonfigurasiService::class)->getSlipGajiConfig();
    $printHeader = app(\App\Modules\Crm\Services\KonfigurasiService::class)->getPrintHeader();

    $paperSize = request('paper', $slipConfig['paper_size'] ?? 'a5');
    $orientation = request('orient', $slipConfig['orientation'] ?? 'portrait');
    $marginMm = (int) request('margin', $slipConfig['margin_mm'] ?? 8);
    $fontSize = request('font', $slipConfig['font_size'] ?? '10pt');
    $showKop = (bool) ($slipConfig['show_kop'] ?? true);
    $showKomisiDetail = (bool) ($slipConfig['show_komisi_detail'] ?? true);
    $showPotongan = (bool) ($slipConfig['show_potongan'] ?? true);
    $showTtd = (bool) ($slipConfig['show_ttd'] ?? true);
    $catatanKaki = $slipConfig['catatan_kaki'] ?? 'Dokumen ini bersifat rahasia dan diterbitkan secara elektronik oleh sistem ERP Ute Parts.';

    $isThermal = $paperSize === '80mm';
    $rincian = is_string($slip->rincian ?? null) ? json_decode($slip->rincian, true) : ($slip->rincian ?? []);
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Slip Gaji — {{ $slip->karyawan?->nama ?? 'Karyawan' }} ({{ $slip->periode?->nama ?? $slip->periode ?? '-' }})</title>
    <style>
        @page {
            size: {{ $isThermal ? '80mm auto' : ($paperSize . ' ' . $orientation) }};
            margin: {{ $isThermal ? '3mm' : ($marginMm . 'mm') }};
        }
        * {
            box-sizing: border-box;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            font-size: {{ $isThermal ? '9pt' : $fontSize }};
            color: #111827;
            background: #f3f4f6;
            margin: 0;
            padding: 12px;
            line-height: 1.35;
        }
        .container {
            width: 100%;
            max-width: {{ $isThermal ? '78mm' : ($paperSize === 'a5' && $orientation === 'portrait' ? '148mm' : ($paperSize === 'a5' ? '210mm' : '210mm')) }};
            margin: 0 auto;
            background: #fff;
            padding: {{ $isThermal ? '8px 10px' : '16px 20px' }};
            border-radius: 6px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            border-bottom: 2px solid #e5e7eb;
            padding-bottom: 10px;
            margin-bottom: 12px;
        }
        .brand-title {
            font-size: 13pt;
            font-weight: 800;
            color: #4f46e5;
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
            color: #111827;
            margin: 0 0 2px 0;
            text-transform: uppercase;
        }
        .doc-badge {
            display: inline-block;
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 7.5pt;
            font-weight: 700;
            text-transform: uppercase;
            background: #e0e7ff;
            color: #3730a3;
        }
        .meta-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 8px;
            background: #f9fafb;
            border: 1px solid #e5e7eb;
            border-radius: 6px;
            padding: 8px 12px;
            margin-bottom: 12px;
            font-size: 8.5pt;
        }
        .meta-item {
            display: flex;
            justify-content: space-between;
        }
        .meta-label {
            color: #6b7280;
        }
        .meta-val {
            font-weight: 600;
            color: #111827;
        }
        table.slip-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 8.5pt;
            margin-bottom: 12px;
        }
        table.slip-table th, table.slip-table td {
            padding: 6px 8px;
            border-bottom: 1px solid #e5e7eb;
        }
        table.slip-table th {
            background: #f3f4f6;
            color: #374151;
            text-align: left;
            font-weight: 700;
            text-transform: uppercase;
            font-size: 7.5pt;
        }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .tabular-nums { font-variant-numeric: tabular-nums; }
        .total-box {
            background: #eef2ff;
            border: 1.5px solid #c7d2fe;
            border-radius: 6px;
            padding: 8px 12px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 14px;
        }
        .total-label {
            font-size: 10pt;
            font-weight: 800;
            color: #312e81;
            text-transform: uppercase;
        }
        .total-val {
            font-size: 13pt;
            font-weight: 900;
            color: #4338ca;
            font-variant-numeric: tabular-nums;
        }
        .ttd-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-top: 18px;
            text-align: center;
            font-size: 8pt;
        }
        .ttd-box {
            padding: 8px;
        }
        .ttd-line {
            margin-top: 45px;
            border-top: 1px solid #374151;
            font-weight: 700;
            padding-top: 4px;
        }
        .footer-note {
            font-size: 7.5pt;
            color: #6b7280;
            font-style: italic;
            text-align: center;
            margin-top: 14px;
            border-top: 1px dashed #d1d5db;
            padding-top: 6px;
        }
        .action-bar {
            background: #1f2937;
            color: #fff;
            padding: 8px 14px;
            border-radius: 8px;
            margin-bottom: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            font-size: 11px;
            max-width: 600px;
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
            display: inline-flex;
            align-items: center;
            gap: 4px;
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
            .container { box-shadow: none; padding: 0; max-width: 100% !important; border-radius: 0; }
            .action-bar { display: none !important; }
        }
    </style>
</head>
<body>

<div class="action-bar">
    <span style="font-weight:600;">Format Kertas:</span>
    <select class="action-select" onchange="location.href='?paper='+this.value+'&orient={{ $orientation }}&margin={{ $marginMm }}'">
        <option value="a5" {{ $paperSize === 'a5' ? 'selected' : '' }}>A5 (Setengah A4)</option>
        <option value="a4" {{ $paperSize === 'a4' ? 'selected' : '' }}>A4 Standar</option>
        <option value="80mm" {{ $paperSize === '80mm' ? 'selected' : '' }}>Thermal 80mm</option>
    </select>
    <select class="action-select" onchange="location.href='?paper={{ $paperSize }}&orient='+this.value+'&margin={{ $marginMm }}'">
        <option value="portrait" {{ $orientation === 'portrait' ? 'selected' : '' }}>Potret</option>
        <option value="landscape" {{ $orientation === 'landscape' ? 'selected' : '' }}>Lanskap</option>
    </select>
    <button onclick="window.print()" class="btn btn-print">🖨️ Cetak Slip</button>
    <button onclick="window.close()" class="btn btn-close">✕ Tutup</button>
</div>

<div class="container">
    @if($showKop)
        <div class="header">
            <div>
                <div class="brand-title">{{ $slip->karyawan?->cabang?->nama ?? ($printHeader['nama_toko'] ?? 'UTE PARTS') }}</div>
                <p class="brand-subtitle">
                    {{ $printHeader['slogan'] ?? 'Pusat Sparepart & Servis Handphone' }}<br>
                    {{ $slip->karyawan?->cabang?->alamat ?? ($printHeader['alamat'] ?? 'Kantor Pusat') }}<br>
                    Telp/WA: {{ $slip->karyawan?->cabang?->telepon ?? ($printHeader['telepon'] ?? '-') }}
                </p>
            </div>
            <div class="doc-title-box">
                <h1 class="doc-title">Slip Gaji</h1>
                <div style="font-size: 9pt; font-weight: bold; color: #4b5563; margin-bottom: 3px;">
                    Periode: {{ $slip->periode?->nama ?? $slip->periode ?? '-' }}
                </div>
                <span class="doc-badge">{{ strtoupper($slip->status ?? 'DIBAYAR') }}</span>
            </div>
        </div>
    @endif

    <!-- Data Identitas Karyawan -->
    <div class="meta-grid">
        <div class="meta-item"><span class="meta-label">Nama Karyawan:</span><span class="meta-val">{{ $slip->karyawan?->nama ?? '-' }}</span></div>
        <div class="meta-item"><span class="meta-label">NIK:</span><span class="meta-val font-mono">{{ $slip->karyawan?->nik ?? '-' }}</span></div>
        <div class="meta-item"><span class="meta-label">Jabatan:</span><span class="meta-val capitalize">{{ $slip->karyawan?->jabatan ?? '-' }}</span></div>
        <div class="meta-item"><span class="meta-label">Penempatan:</span><span class="meta-val">{{ $slip->karyawan?->cabang?->nama ?? 'Pusat' }}</span></div>
        <div class="meta-item"><span class="meta-label">Rekening Bank:</span><span class="meta-val">{{ $slip->karyawan?->rekening_bank ?? '-' }}</span></div>
        <div class="meta-item"><span class="meta-label">No. Jurnal:</span><span class="meta-val font-mono">{{ $slip->jurnal_id ? '#' . $slip->jurnal_id : '-' }}</span></div>
    </div>

    <!-- Rincian Penghasilan & Potongan -->
    <table class="slip-table">
        <thead>
            <tr>
                <th>Komponen Gaji &amp; Penghasilan</th>
                <th class="text-right">Nominal (Rp)</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td><strong>Gaji Pokok</strong></td>
                <td class="text-right tabular-nums">Rp {{ number_format((float)$slip->gaji_pokok, 0, ',', '.') }}</td>
            </tr>
            @if((float)$slip->total_tunjangan > 0)
                <tr>
                    <td>Tunjangan &amp; Insentif Kehadiran</td>
                    <td class="text-right tabular-nums text-emerald-700">+Rp {{ number_format((float)$slip->total_tunjangan, 0, ',', '.') }}</td>
                </tr>
            @endif
            @if((float)$slip->total_komisi > 0)
                <tr>
                    <td>
                        Komisi Teknisi &amp; Performa
                        @if(!empty($slip->komisiDetails) && count($slip->komisiDetails) > 0)
                            <span style="font-size:7pt; color:#6b7280;">({{ count($slip->komisiDetails) }} tiket servis)</span>
                        @endif
                    </td>
                    <td class="text-right tabular-nums text-emerald-700">+Rp {{ number_format((float)$slip->total_komisi, 0, ',', '.') }}</td>
                </tr>
            @endif

            @if($showPotongan && (float)$slip->total_potongan > 0)
                <tr>
                    <td>Potongan (Kedisiplinan, Keterlambatan, Kasbon)</td>
                    <td class="text-right tabular-nums text-red-600">-Rp {{ number_format((float)$slip->total_potongan, 0, ',', '.') }}</td>
                </tr>
            @endif
        </tbody>
    </table>

    <!-- Rincian Komisi Tiket Servis jika Ada -->
    @if($showKomisiDetail && !empty($slip->komisiDetails) && count($slip->komisiDetails) > 0)
        <div style="margin-bottom: 12px;">
            <div style="font-size: 8pt; font-weight: bold; color: #374151; margin-bottom: 4px; text-transform: uppercase;">
                Rincian Tiket Servis Terkait:
            </div>
            <table class="slip-table" style="font-size: 7.5pt; margin-bottom: 0;">
                <thead>
                    <tr>
                        <th>No. Tiket</th>
                        <th>Kategori</th>
                        <th class="text-right">Nominal Komisi</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($slip->komisiDetails as $kd)
                        <tr>
                            <td class="font-mono">{{ $kd->tiketServis?->no_tiket ?? ('#' . $kd->tiket_servis_id) }}</td>
                            <td>{{ strtoupper($kd->jenis ?? 'JASA') }}</td>
                            <td class="text-right tabular-nums font-semibold">+Rp {{ number_format((float)$kd->nominal, 0, ',', '.') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    <!-- Total Net Take Home Pay -->
    <div class="total-box">
        <div>
            <div class="total-label">Gaji Bersih Diterima (Take Home Pay)</div>
            <div style="font-size: 7.5pt; color: #4338ca;">Ditransfer ke rekening terdaftar</div>
        </div>
        <div class="total-val">
            Rp {{ number_format((float)$slip->total_gaji, 0, ',', '.') }}
        </div>
    </div>

    @if($showTtd && !$isThermal)
        <!-- Kolom Tanda Tangan -->
        <div class="ttd-grid">
            <div class="ttd-box">
                <div>Penerima (Karyawan)</div>
                <div class="ttd-line">{{ $slip->karyawan?->nama ?? 'Karyawan' }}</div>
            </div>
            <div class="ttd-box">
                <div>Bagian Keuangan / HRD</div>
                <div class="ttd-line">{{ $printHeader['nama_toko'] ?? 'Manajemen UTE PARTS' }}</div>
            </div>
        </div>
    @endif

    @if($catatanKaki)
        <div class="footer-note">
            {{ $catatanKaki }}
        </div>
    @endif
</div>

</body>
</html>
