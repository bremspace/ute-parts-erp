<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cetak Label QR & Barcode Produk — Ute Parts</title>
    @php
        $cfg = $labelConfig ?? [
            'preset' => '33x15',
            'width' => 33.0,
            'height' => 15.0,
            'margin_top' => 1.0,
            'margin_bottom' => 1.0,
            'margin_left' => 1.5,
            'margin_right' => 1.5,
            'show_brand' => true,
            'show_price' => true,
            'show_sku' => true,
            'copies' => 1,
        ];
        $qrService = app(\App\Modules\Wms\Services\QrCodeService::class);
    @endphp
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            background: #0f172a;
            color: #1e293b;
        }

        @page {
            size: {{ $cfg['width'] }}mm {{ $cfg['height'] }}mm;
            margin: 0;
        }

        .screen-canvas {
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 24px 16px;
            min-height: 100vh;
        }

        .labels-grid {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            justify-content: center;
            max-width: 1000px;
            margin: 0 auto;
        }

        /* Label Container: Mengikuti ukuran mm persis */
        .qr-label-card {
            width: {{ $cfg['width'] }}mm;
            height: {{ $cfg['height'] }}mm;
            padding: {{ $cfg['margin_top'] }}mm {{ $cfg['margin_right'] }}mm {{ $cfg['margin_bottom'] }}mm {{ $cfg['margin_left'] }}mm;
            background: #ffffff;
            color: #000000;
            border-radius: 2px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.3);
            display: flex;
            align-items: center;
            overflow: hidden;
            position: relative;
            page-break-inside: avoid;
            page-break-after: always;
            break-inside: avoid;
        }

        .qr-code-box {
            flex-shrink: 0;
            width: {{ max(10, min(14, $cfg['height'] - ($cfg['margin_top'] + $cfg['margin_bottom']) - 0.5)) }}mm;
            height: {{ max(10, min(14, $cfg['height'] - ($cfg['margin_top'] + $cfg['margin_bottom']) - 0.5)) }}mm;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .qr-code-box svg {
            width: 100%;
            height: 100%;
            display: block;
        }

        .qr-info-box {
            flex: 1;
            min-width: 0;
            margin-left: 1.2mm;
            display: flex;
            flex-direction: column;
            justify-content: center;
            overflow: hidden;
            line-height: 1.15;
        }

        .brand-text {
            font-size: 6px;
            font-weight: 900;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #312e81;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .name-text {
            font-size: 6.5px;
            font-weight: 700;
            color: #09090b;
            margin-top: 0.5px;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            word-break: break-word;
        }

        .sku-text {
            font-size: 5.5px;
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
            font-weight: 600;
            color: #4b5563;
            margin-top: 0.5px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .price-text {
            font-size: 7px;
            font-weight: 900;
            color: #047857;
            margin-top: 0.5px;
            white-space: nowrap;
        }

        /* Toolbar Layar */
        .toolbar {
            background: #1e293b;
            color: #ffffff;
            border: 1px solid #334155;
            border-radius: 16px;
            padding: 12px 20px;
            margin-bottom: 24px;
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 16px;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.5);
            max-width: 950px;
            width: 100%;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 16px;
            border-radius: 10px;
            font-size: 13px;
            font-weight: 700;
            text-decoration: none;
            cursor: pointer;
            border: none;
            transition: all 0.15s ease;
        }

        .btn-primary {
            background: #5B4FE9;
            color: #ffffff;
            box-shadow: 0 4px 14px rgba(91, 79, 233, 0.4);
        }
        .btn-primary:hover {
            background: #4a3ec8;
            transform: translateY(-1px);
        }

        .btn-secondary {
            background: rgba(255, 255, 255, 0.08);
            color: #e2e8f0;
            border: 1px solid rgba(255, 255, 255, 0.15);
        }
        .btn-secondary:hover {
            background: rgba(255, 255, 255, 0.15);
        }

        .select-input, .number-input {
            background: #0f172a;
            color: #ffffff;
            border: 1px solid #334155;
            border-radius: 8px;
            padding: 6px 10px;
            font-size: 12px;
        }

        /* Mode Cetak: Sembunyikan chrome dan render pas 1 label per page */
        @media print {
            body {
                background: #ffffff !important;
                margin: 0 !important;
                padding: 0 !important;
            }

            .no-print {
                display: none !important;
            }

            .screen-canvas {
                padding: 0 !important;
                margin: 0 !important;
                display: block !important;
            }

            .labels-grid {
                display: block !important;
                margin: 0 !important;
                padding: 0 !important;
                gap: 0 !important;
            }

            .qr-label-card {
                box-shadow: none !important;
                border: none !important;
                border-radius: 0 !important;
                margin: 0 !important;
            }
        }
    </style>
</head>
<body>
    <div class="screen-canvas">
        <!-- Floating Toolbar (Hanya Tampil di Layar) -->
        <div class="toolbar no-print">
            <div style="display:flex; align-items:center; gap:10px;">
                <button type="button" onclick="window.print()" class="btn btn-primary">
                    🖨️ Cetak Label ({{ $produks->count() }} Produk)
                </button>
                <a href="{{ url('/app/wms') }}" class="btn btn-secondary">
                    ← Kembali ke WMS
                </a>
            </div>

            <div style="display:flex; align-items:center; gap:12px; margin-left:auto; font-size:12px; color:#cbd5e1;">
                <div>
                    <span>Preset:</span>
                    <select class="select-input" onchange="updateParam('preset', this.value)">
                        <option value="33x15" {{ $cfg['preset'] === '33x15' ? 'selected' : '' }}>33mm × 15mm (Default)</option>
                        <option value="40x20" {{ $cfg['preset'] === '40x20' ? 'selected' : '' }}>40mm × 20mm</option>
                        <option value="50x30" {{ $cfg['preset'] === '50x30' ? 'selected' : '' }}>50mm × 30mm</option>
                        <option value="58x40" {{ $cfg['preset'] === '58x40' ? 'selected' : '' }}>58mm × 40mm</option>
                    </select>
                </div>

                <div>
                    <span>Copy:</span>
                    <input type="number" min="1" max="20" value="{{ $cfg['copies'] }}" class="number-input" style="width:55px;" onchange="updateParam('copies', this.value)">
                </div>

                <a href="{{ url('/app/pengaturan?tab=printer') }}" class="btn btn-secondary" style="font-size:11px; padding:6px 10px;">
                    ⚙️ Pengaturan Margin
                </a>
            </div>
        </div>

        <!-- Render Labels -->
        <div class="labels-grid">
            @forelse($produks as $p)
                @php
                    $kode = $p->barcode ?: ($p->skuVariants->first()?->sku ?: ('UTP-' . str_pad((string)$p->id, 5, '0', STR_PAD_LEFT)));
                    $qrSvg = $qrService->generateSvg($kode, 100, 0);
                    $hargaJual = $p->harga_jual_retail ?: ($p->harga_jual ?: 0);
                    $brandNama = $p->brand?->nama ?: (app(\App\Modules\Crm\Services\KonfigurasiService::class)->getPrintHeader()['nama_toko'] ?? 'UTE PARTS');
                    $copies = (int) ($cfg['copies'] ?? 1);
                @endphp

                @for($c = 0; $c < $copies; $c++)
                    <div class="qr-label-card" title="{{ $p->nama }} ({{ $kode }})">
                        <!-- QR Code Svg -->
                        <div class="qr-code-box">
                            {!! $qrSvg !!}
                        </div>

                        <!-- Info Produk -->
                        <div class="qr-info-box">
                            @if(!empty($cfg['show_brand']))
                                <div class="brand-text">{{ $brandNama }}</div>
                            @endif

                            <div class="name-text" title="{{ $p->nama }}">{{ $p->nama }}</div>

                            @if(!empty($cfg['show_sku']))
                                <div class="sku-text">{{ $kode }}</div>
                            @endif

                            @if(!empty($cfg['show_price']) && $hargaJual > 0)
                                <div class="price-text">Rp {{ number_format($hargaJual, 0, ',', '.') }}</div>
                            @endif
                        </div>
                    </div>
                @endfor
            @empty
                <div style="color:#94a3b8; text-align:center; padding:40px;">
                    Tidak ada produk yang dipilih untuk dicetak.
                </div>
            @endforelse
        </div>
    </div>

    <script>
        function updateParam(key, value) {
            const url = new URL(window.location.href);
            if (key === 'preset') {
                if (value === '33x15') {
                    url.searchParams.set('preset', '33x15');
                    url.searchParams.set('w', '33');
                    url.searchParams.set('h', '15');
                    url.searchParams.set('m_top', '1');
                    url.searchParams.set('m_bottom', '1');
                    url.searchParams.set('m_left', '1.5');
                    url.searchParams.set('m_right', '1.5');
                } else if (value === '40x20') {
                    url.searchParams.set('preset', '40x20');
                    url.searchParams.set('w', '40');
                    url.searchParams.set('h', '20');
                    url.searchParams.set('m_top', '1.5');
                    url.searchParams.set('m_bottom', '1.5');
                    url.searchParams.set('m_left', '2');
                    url.searchParams.set('m_right', '2');
                } else if (value === '50x30') {
                    url.searchParams.set('preset', '50x30');
                    url.searchParams.set('w', '50');
                    url.searchParams.set('h', '30');
                    url.searchParams.set('m_top', '2');
                    url.searchParams.set('m_bottom', '2');
                    url.searchParams.set('m_left', '2.5');
                    url.searchParams.set('m_right', '2.5');
                } else if (value === '58x40') {
                    url.searchParams.set('preset', '58x40');
                    url.searchParams.set('w', '58');
                    url.searchParams.set('h', '40');
                    url.searchParams.set('m_top', '3');
                    url.searchParams.set('m_bottom', '3');
                    url.searchParams.set('m_left', '3');
                    url.searchParams.set('m_right', '3');
                }
            } else {
                url.searchParams.set(key, value);
            }
            window.location.href = url.toString();
        }
    </script>
</body>
</html>
