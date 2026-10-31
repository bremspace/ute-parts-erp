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
    <title>Surat Pengantar Retur Pembelian — {{ $retur->no_return }}</title>
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
            border-bottom: 2px solid #e5e7eb;
            padding-bottom: 10px;
            margin-bottom: 12px;
        }
        .brand-title {
            font-size: 14pt;
            font-weight: 800;
            color: #dc2626;
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
        .doc-number {
            font-size: 10pt;
            font-weight: 700;
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
            color: #dc2626;
        }
        .meta-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
            margin-bottom: 12px;
            font-size: 8.5pt;
        }
        .meta-card {
            background: #f9fafb;
            border: 1px solid #e5e7eb;
            border-radius: 4px;
            padding: 8px 10px;
        }
        .meta-card-title {
            font-size: 7.5pt;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #6b7280;
            margin-bottom: 4px;
            border-bottom: 1px dashed #d1d5db;
            padding-bottom: 2px;
        }
        .meta-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 2px;
        }
        .meta-label {
            color: #6b7280;
        }
        .meta-val {
            font-weight: 600;
            color: #111827;
            text-align: right;
        }
        table.items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 12px;
            font-size: 8.5pt;
        }
        table.items-table th {
            background: #f3f4f6;
            color: #374151;
            font-weight: 700;
            text-align: left;
            padding: 6px 8px;
            border-top: 1px solid #d1d5db;
            border-bottom: 1px solid #d1d5db;
            font-size: 8pt;
            text-transform: uppercase;
        }
        table.items-table td {
            padding: 6px 8px;
            border-bottom: 1px solid #e5e7eb;
            vertical-align: top;
        }
        table.items-table tr:last-child td {
            border-bottom: 2px solid #d1d5db;
        }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .font-mono { font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace; }
        .summary-box {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 14px;
            font-size: 8.5pt;
        }
        .notes-box {
            width: 58%;
            background: #fef2f2;
            border: 1px solid #fecaca;
            border-radius: 4px;
            padding: 6px 8px;
            font-size: 8pt;
        }
        .notes-title {
            font-weight: 700;
            color: #991b1b;
            margin-bottom: 2px;
        }
        .notes-content {
            color: #7f1d1d;
            white-space: pre-line;
        }
        .totals-table {
            width: 38%;
            font-size: 9pt;
        }
        .totals-row {
            display: flex;
            justify-content: space-between;
            padding: 3px 0;
            border-bottom: 1px dashed #e5e7eb;
        }
        .totals-row.grand {
            border-top: 2px solid #111827;
            border-bottom: 2px solid #111827;
            font-size: 10pt;
            font-weight: 800;
            color: #dc2626;
            margin-top: 4px;
            padding: 5px 0;
        }
        .signatures {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 10px;
            margin-top: 18px;
            text-align: center;
            font-size: 8pt;
            page-break-inside: avoid;
        }
        .sig-box {
            border: 1px dashed #d1d5db;
            border-radius: 4px;
            padding: 6px 4px 4px 4px;
            background: #fafafa;
        }
        .sig-role {
            font-weight: 700;
            color: #4b5563;
            margin-bottom: 40px;
        }
        .sig-name {
            font-weight: 700;
            color: #111827;
            border-top: 1px solid #9ca3af;
            padding-top: 3px;
            margin: 0 8px;
        }
        .sig-date {
            font-size: 7pt;
            color: #6b7280;
            margin-top: 1px;
        }
        .print-btn-bar {
            position: fixed;
            top: 12px;
            right: 12px;
            display: flex;
            gap: 8px;
            z-index: 1000;
        }
        .btn-print {
            background: #dc2626;
            color: #fff;
            border: none;
            padding: 8px 14px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 700;
            cursor: pointer;
            box-shadow: 0 2px 4px rgba(0,0,0,0.15);
        }
        .btn-print:hover { background: #b91c1c; }
        .btn-close {
            background: #4b5563;
            color: #fff;
            border: none;
            padding: 8px 14px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 700;
            cursor: pointer;
        }
        @media print {
            body {
                background: #fff;
                padding: 0;
            }
            .container {
                box-shadow: none;
                padding: 0;
                width: 100%;
                max-width: 100%;
            }
            .print-btn-bar {
                display: none;
            }
        }
    </style>
</head>
<body>
    <div class="print-btn-bar">
        <button class="btn-print" onclick="window.print()">🖨️ Cetak Surat Jalan Retur</button>
        <button class="btn-close" onclick="window.close()">Tutup</button>
    </div>

    <div class="container">
        @if($showKop)
            <div class="header">
                <div>
                    <h1 class="brand-title">{{ $printHeader['nama_toko'] ?? config('app.name', 'UTE PARTS') }}</h1>
                    <p class="brand-subtitle">
                        {{ $printHeader['alamat'] ?? 'Pusat Sparepart & Layanan Gadget Terpercaya' }}<br>
                        Telp: {{ $printHeader['telepon'] ?? '-' }}
                    </p>
                </div>
                <div class="doc-title-box">
                    <div class="doc-title">Surat Pengantar Retur Supplier</div>
                    <div class="doc-number">{{ $retur->no_return }}</div>
                    <div style="font-size: 8pt; color: #6b7280; margin-top: 2px;">
                        Status: <strong style="text-transform: uppercase; color: {{ $retur->status === 'selesai' ? '#16a34a' : ($retur->status === 'draft' ? '#d97706' : '#2563eb') }}">{{ $retur->status }}</strong>
                    </div>
                </div>
            </div>
        @endif

        <div class="meta-grid">
            <div class="meta-card">
                <div class="meta-card-title">Tujuan Pengembalian (Supplier)</div>
                <div class="meta-row">
                    <span class="meta-label">Nama Vendor:</span>
                    <span class="meta-val">{{ $retur->supplier?->nama ?? '-' }}</span>
                </div>
                <div class="meta-row">
                    <span class="meta-label">PIC / Kontak:</span>
                    <span class="meta-val">{{ $retur->supplier?->kontak ?? '-' }} ({{ $retur->supplier?->telepon ?? '-' }})</span>
                </div>
                <div class="meta-row">
                    <span class="meta-label">Email:</span>
                    <span class="meta-val">{{ $retur->supplier?->email ?? '-' }}</span>
                </div>
                <div class="meta-row">
                    <span class="meta-label">Alamat:</span>
                    <span class="meta-val" style="font-size: 7.5pt; max-width: 60%;">{{ $retur->supplier?->alamat ?? '-' }}</span>
                </div>
            </div>

            <div class="meta-card">
                <div class="meta-card-title">Informasi Pengiriman & Dokumen</div>
                <div class="meta-row">
                    <span class="meta-label">Tanggal Dokumen:</span>
                    <span class="meta-val">{{ \Carbon\Carbon::parse($retur->tanggal ?? now())->isoFormat('D MMMM Y') }}</span>
                </div>
                <div class="meta-row">
                    <span class="meta-label">Gudang Asal:</span>
                    <span class="meta-val">{{ $retur->gudang?->nama ?? 'Gudang Utama' }} ({{ $retur->gudang?->cabang?->nama ?? '-' }})</span>
                </div>
                <div class="meta-row">
                    <span class="meta-label">Opsi Penyelesaian:</span>
                    <span class="meta-val" style="text-transform: uppercase;">
                        @if($retur->metode_pengembalian === 'saldo')
                            Deposit Saldo Supplier
                        @elseif($retur->metode_pengembalian === 'utang')
                            Potong Utang Usaha (AP)
                        @elseif($retur->metode_pengembalian === 'kas')
                            Pengembalian Kas / Bank
                        @elseif($retur->metode_pengembalian === 'tukar_unit')
                            Tukar Unit Fisik Baru
                        @else
                            {{ $retur->metode_pengembalian }}
                        @endif
                    </span>
                </div>
                <div class="meta-row">
                    <span class="meta-label">Acuan Klaim:</span>
                    <span class="meta-val">
                        @php
                            $kebijakan = $retur->kebijakan_retur ?: ($retur->supplier?->kebijakan_retur ?: 'faktur_asli');
                        @endphp
                        @if($kebijakan === 'harga_pasar')
                            Harga Pasar Terkini
                        @elseif($kebijakan === 'tukar_unit')
                            Tukar Unit Fisik
                        @else
                            Faktur Asli PO
                        @endif
                    </span>
                </div>
                <div class="meta-row">
                    <span class="meta-label">Dibuat Oleh:</span>
                    <span class="meta-val">{{ $retur->user?->name ?? (auth()->user()?->name ?? 'Staff Gudang') }}</span>
                </div>
            </div>
        </div>

        <table class="items-table">
            <thead>
                <tr>
                    <th style="width: 25px;" class="text-center">No</th>
                    <th>Produk / Deskripsi</th>
                    <th style="width: 120px;">Nomor Seri (SN)</th>
                    <th class="text-center" style="width: 50px;">Qty</th>
                    <th class="text-right" style="width: 85px;">Estimasi Satuan</th>
                    <th class="text-right" style="width: 95px;">Subtotal Klaim</th>
                </tr>
            </thead>
            <tbody>
                @forelse($retur->items as $idx => $it)
                    <tr>
                        <td class="text-center">{{ $idx + 1 }}</td>
                        <td>
                            <strong style="color: #111827;">{{ $it->produk?->nama ?? "Produk #{$it->produk_id}" }}</strong>
                            <div class="font-mono" style="font-size: 7.5pt; color: #6b7280;">
                                SKU: {{ $it->produk?->sku ?? $it->produk?->barcode ?? '-' }}
                            </div>
                        </td>
                        <td class="font-mono" style="font-size: 7.5pt; color: #4b5563;">
                            @php
                                $snArr = is_array($it->nomor_seri) ? $it->nomor_seri : (!empty($it->nomor_seri) ? json_decode($it->nomor_seri, true) : []);
                            @endphp
                            {{ !empty($snArr) ? implode(', ', $snArr) : '-' }}
                        </td>
                        <td class="text-center" style="font-weight: 700;">{{ (int) $it->jumlah }}</td>
                        <td class="text-right tabular-nums">
                            Rp {{ number_format($it->harga_beli, 0, ',', '.') }}
                        </td>
                        <td class="text-right tabular-nums font-bold" style="color: #111827;">
                            Rp {{ number_format($it->subtotal, 0, ',', '.') }}
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="text-center" style="color: #6b7280; padding: 12px;">Tidak ada item retur.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        <div class="summary-box">
            <div class="notes-box">
                <div class="notes-title">Alasan Pengembalian / Kerusakan:</div>
                <div class="notes-content">{{ $retur->alasan ?: 'Retur barang rusak dari garansi toko untuk penggantian/klaim deposit supplier.' }}</div>
                @if($retur->catatan_penyelesaian)
                    <div style="margin-top: 4px; font-weight: 600; color: #374151;">Catatan Pengiriman: {{ $retur->catatan_penyelesaian }}</div>
                @endif
            </div>

            <div class="totals-table">
                <div class="totals-row">
                    <span style="color: #6b7280;">Total Qty:</span>
                    <span class="font-bold">{{ (int) $retur->items->sum('jumlah') }} unit</span>
                </div>
                @if($retur->nilai_realisasi !== null && (float) $retur->nilai_realisasi > 0)
                    <div class="totals-row">
                        <span style="color: #6b7280;">Nilai Buku Awal:</span>
                        <span class="tabular-nums">Rp {{ number_format($retur->jumlah, 0, ',', '.') }}</span>
                    </div>
                    @if((float) $retur->selisih_klaim != 0)
                        <div class="totals-row">
                            <span style="color: {{ (float) $retur->selisih_klaim < 0 ? '#b91c1c' : '#15803d' }};">
                                {{ (float) $retur->selisih_klaim < 0 ? 'Selisih Rugi Klaim:' : 'Selisih Lebih Klaim:' }}
                            </span>
                            <span class="tabular-nums" style="font-weight: 600; color: {{ (float) $retur->selisih_klaim < 0 ? '#b91c1c' : '#15803d' }};">
                                {{ (float) $retur->selisih_klaim < 0 ? '-' : '+' }}Rp {{ number_format(abs((float) $retur->selisih_klaim), 0, ',', '.') }}
                            </span>
                        </div>
                    @endif
                    <div class="totals-row grand">
                        <span>Realisasi Klaim:</span>
                        <span class="tabular-nums">Rp {{ number_format($retur->nilai_realisasi, 0, ',', '.') }}</span>
                    </div>
                @else
                    <div class="totals-row grand">
                        <span>Estimasi Klaim:</span>
                        <span class="tabular-nums">Rp {{ number_format($retur->jumlah, 0, ',', '.') }}</span>
                    </div>
                @endif
            </div>
        </div>

        @if($showTtd)
            <div class="signatures">
                <div class="sig-box">
                    <div class="sig-role">Pengirim / Bag. Gudang</div>
                    <div class="sig-name">{{ $retur->user?->name ?? 'Staff Gudang' }}</div>
                    <div class="sig-date">Tgl: {{ \Carbon\Carbon::parse($retur->tanggal)->format('d/m/Y') }}</div>
                </div>
                <div class="sig-box">
                    <div class="sig-role">Kurir / Driver / Ekspedisi</div>
                    <div class="sig-name">( ..................................... )</div>
                    <div class="sig-date">No. Kend / Resi: ______________</div>
                </div>
                <div class="sig-box">
                    <div class="sig-role">Diterima Pihak Supplier</div>
                    <div class="sig-name">( ..................................... )</div>
                    <div class="sig-date">Tgl & Cap: _______________</div>
                </div>
            </div>
        @endif
    </div>

    @if(request('autoprint'))
        <script>
            window.addEventListener('load', function() {
                window.print();
            });
        </script>
    @endif
</body>
</html>
