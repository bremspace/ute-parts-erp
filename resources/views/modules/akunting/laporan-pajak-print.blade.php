@php
    $laporanConfig = app(\App\Modules\Crm\Services\KonfigurasiService::class)->getLaporanDocConfig();
    $printHeader = app(\App\Modules\Crm\Services\KonfigurasiService::class)->getPrintHeader();

    $paperSize = request('paper', $laporanConfig['paper_size'] ?? 'a4');
    $orientation = request('orient', $laporanConfig['orientation'] ?? 'landscape');
    $marginMm = (int) request('margin', $laporanConfig['margin_mm'] ?? 10);
    $fontSize = request('font', $laporanConfig['font_size'] ?? '9pt');
    $showKop = (bool) ($laporanConfig['show_kop'] ?? true);
    $showTtd = (bool) ($laporanConfig['show_ttd'] ?? true);
    $showAuditFooter = (bool) ($laporanConfig['show_audit_footer'] ?? true);

    $cabangNama = auth()->user()?->cabang?->nama ?? session('cabang_nama', 'Semua Cabang / Konsolidasi');
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Pajak Bulanan — {{ $periodeDari }} s/d {{ $periodeSampai }}</title>
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
            margin: 0 auto;
            background: #fff;
            padding: 16px 20px;
            border-radius: 6px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            border-bottom: 2px solid #111827;
            padding-bottom: 10px;
            margin-bottom: 12px;
        }
        .brand-title {
            font-size: 14pt;
            font-weight: 800;
            color: #111827;
            margin: 0 0 2px 0;
        }
        .brand-subtitle {
            font-size: 8pt;
            color: #4b5563;
            margin: 0;
        }
        .doc-title-box {
            text-align: right;
        }
        .doc-title {
            font-size: 13pt;
            font-weight: 800;
            margin: 0 0 2px 0;
            text-transform: uppercase;
        }
        .summary-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 10px;
            margin-bottom: 14px;
        }
        .summary-card {
            background: #f9fafb;
            border: 1px solid #e5e7eb;
            border-radius: 6px;
            padding: 8px 10px;
        }
        .summary-title {
            font-size: 7.5pt;
            text-transform: uppercase;
            font-weight: 700;
            color: #6b7280;
        }
        .summary-val {
            font-size: 12pt;
            font-weight: 800;
            margin-top: 2px;
            font-variant-numeric: tabular-nums;
        }
        table.data-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 8.5pt;
            margin-bottom: 14px;
        }
        table.data-table th, table.data-table td {
            padding: 6px 8px;
            border: 1px solid #d1d5db;
        }
        table.data-table th {
            background: #f3f4f6;
            font-weight: 700;
            text-align: left;
            font-size: 8pt;
            text-transform: uppercase;
        }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .tabular-nums { font-variant-numeric: tabular-nums; }
        .ttd-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            margin-top: 25px;
            text-align: center;
            font-size: 8pt;
            page-break-inside: avoid;
        }
        .ttd-line {
            margin-top: 50px;
            border-top: 1px solid #111827;
            font-weight: 700;
            padding-top: 4px;
        }
        .footer-audit {
            margin-top: 16px;
            border-top: 1px dashed #d1d5db;
            padding-top: 6px;
            font-size: 7pt;
            color: #6b7280;
            display: flex;
            justify-content: space-between;
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
    <span>Kertas:</span>
    <select class="action-select" onchange="location.href='?dari={{ $periodeDari }}&sampai={{ $periodeSampai }}&paper='+this.value+'&orient={{ $orientation }}'">
        <option value="a4" {{ $paperSize === 'a4' ? 'selected' : '' }}>A4 Standar</option>
        <option value="f4" {{ $paperSize === 'f4' ? 'selected' : '' }}>F4 / Folio</option>
        <option value="letter" {{ $paperSize === 'letter' ? 'selected' : '' }}>Letter</option>
    </select>
    <select class="action-select" onchange="location.href='?dari={{ $periodeDari }}&sampai={{ $periodeSampai }}&paper={{ $paperSize }}&orient='+this.value">
        <option value="landscape" {{ $orientation === 'landscape' ? 'selected' : '' }}>Lanskap (Rekomendasi Tabel)</option>
        <option value="portrait" {{ $orientation === 'portrait' ? 'selected' : '' }}>Potret</option>
    </select>
    <button onclick="window.print()" class="btn btn-print">🖨️ Cetak Laporan</button>
    <button onclick="window.close()" class="btn btn-close">✕ Tutup</button>
</div>

<div class="container">
    @if($showKop)
        <div class="header">
            <div>
                <div class="brand-title">{{ $printHeader['nama_toko'] ?? config('app.name', 'BREMSPACE') }}</div>
                <p class="brand-subtitle">
                    {{ $printHeader['slogan'] ?? 'Pusat Sparepart & Servis' }} · {{ $printHeader['alamat'] ?? 'Kantor Pusat' }}<br>
                    Telp/WA: {{ $printHeader['telepon'] ?? '-' }} · Website: {{ $printHeader['website_medsos'] ?? 'www.bremspace.com' }}
                </p>
            </div>
            <div class="doc-title-box">
                <h1 class="doc-title">Laporan Pajak Bulanan</h1>
                <div style="font-size: 8.5pt; font-weight: 600; color: #4b5563;">
                    Periode: {{ $periodeDari }} s/d {{ $periodeSampai }}
                </div>
                <div style="font-size: 7.5pt; color: #6b7280;">Cabang: {{ $cabangNama }}</div>
            </div>
        </div>
    @endif

    <!-- Ringkasan Pajak -->
    <div class="summary-grid">
        <div class="summary-card">
            <div class="summary-title">PPN Keluaran</div>
            <div class="summary-val">Rp {{ number_format($rekap['keluaran_ppn'] ?? 0, 0, ',', '.') }}</div>
            <div style="font-size:7pt; color:#6b7280; margin-top:2px;">DPP: Rp {{ number_format($rekap['keluaran_dpp'] ?? 0, 0, ',', '.') }} ({{ $rekap['jumlah_transaksi'] ?? 0 }} TRX)</div>
        </div>
        <div class="summary-card">
            <div class="summary-title">PPN Masukan (Akun 110-03)</div>
            <div class="summary-val" style="color:#d97706;">Rp {{ number_format($rekap['masukan'] ?? 0, 0, ',', '.') }}</div>
            <div style="font-size:7pt; color:#6b7280; margin-top:2px;">Klaim Pajak Masukan Pembelian</div>
        </div>
        <div class="summary-card">
            <div class="summary-title">PPN Terutang Bersih</div>
            <div class="summary-val" style="color:{{ ($rekap['terutang'] ?? 0) >= 0 ? '#059669' : '#dc2626' }};">
                Rp {{ number_format(abs($rekap['terutang'] ?? 0), 0, ',', '.') }}
            </div>
            <div style="font-size:7pt; color:#6b7280; margin-top:2px;">{{ ($rekap['terutang'] ?? 0) >= 0 ? 'Wajib Disetor ke Kas Negara' : 'Kompensasi / Surplus Masukan' }}</div>
        </div>
        <div class="summary-card">
            <div class="summary-title">Status Tarif PPN</div>
            <div class="summary-val" style="font-size:11pt; color:#4f46e5;">
                {{ ($pajakConfig['enabled'] ?? false) ? 'AKTIF (' . number_format($pajakConfig['percent'] ?? 11, 0) . '%)' : 'NONAKTIF' }}
            </div>
            <div style="font-size:7pt; color:#6b7280; margin-top:2px;">Pengaturan Cabang Aktif</div>
        </div>
    </div>

    <!-- Tabel Rincian -->
    <table class="data-table">
        <thead>
            <tr>
                <th style="width: 35px;" class="text-center">No</th>
                <th>Tanggal</th>
                <th>No. Transaksi</th>
                <th class="text-right">DPP (Rp)</th>
                <th class="text-center">%</th>
                <th class="text-right">PPN Keluaran (Rp)</th>
            </tr>
        </thead>
        <tbody>
            @forelse($rows as $idx => $r)
                <tr>
                    <td class="text-center">{{ $idx + 1 }}</td>
                    <td>{{ $r['tanggal'] ?? '-' }}</td>
                    <td style="font-family:monospace; font-weight:600;">{{ $r['no_transaksi'] ?? '-' }}</td>
                    <td class="text-right tabular-nums">{{ number_format($r['dpp'] ?? 0, 0, ',', '.') }}</td>
                    <td class="text-center tabular-nums">{{ number_format($r['percent'] ?? 0, 2, ',', '.') }}%</td>
                    <td class="text-right tabular-nums font-bold">{{ number_format($r['ppn'] ?? 0, 0, ',', '.') }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="text-center" style="padding:18px; color:#6b7280;">Tidak ada data transaksi kena pajak pada rentang periode ini.</td>
                </tr>
            @endforelse
        </tbody>
        @if(!empty($rows) && count($rows) > 0)
            <tfoot>
                <tr style="background:#f9fafb; font-weight:bold;">
                    <td colspan="3" class="text-right">TOTAL KELUARAN:</td>
                    <td class="text-right tabular-nums">Rp {{ number_format($rekap['keluaran_dpp'] ?? 0, 0, ',', '.') }}</td>
                    <td></td>
                    <td class="text-right tabular-nums">Rp {{ number_format($rekap['keluaran_ppn'] ?? 0, 0, ',', '.') }}</td>
                </tr>
            </tfoot>
        @endif
    </table>

    @if($showTtd)
        <!-- Kolom Tanda Tangan Laporan 3 Kolom -->
        <div class="ttd-grid">
            <div>
                <div>Dibuat Oleh:</div>
                <div style="font-size:7.5pt; color:#6b7280;">{{ $laporanConfig['jabatan_pembuat'] ?? 'Staf Akuntansi / Kasir' }}</div>
                <div class="ttd-line">{{ auth()->user()?->name ?? 'Staf Terkait' }}</div>
            </div>
            <div>
                <div>Diperiksa Oleh:</div>
                <div style="font-size:7.5pt; color:#6b7280;">{{ $laporanConfig['jabatan_pemeriksa'] ?? 'Kepala Cabang / Spv' }}</div>
                <div class="ttd-line">( ............................................ )</div>
            </div>
            <div>
                <div>Disetujui Oleh:</div>
                <div style="font-size:7.5pt; color:#6b7280;">{{ $laporanConfig['jabatan_penyetuju'] ?? 'Owner / Direktur' }}</div>
                <div class="ttd-line">( ............................................ )</div>
            </div>
        </div>
    @endif

    @if($showAuditFooter)
        <div class="footer-audit">
            <span>Dicetak oleh: <strong>{{ auth()->user()?->name ?? 'System' }}</strong> ({{ auth()->user()?->email ?? '-' }})</span>
            <span>Tanggal Cetak: {{ now()->format('d/m/Y H:i:s') }}</span>
            <span>Dokumen Resmi ERP</span>
        </div>
    @endif
</div>

</body>
</html>
