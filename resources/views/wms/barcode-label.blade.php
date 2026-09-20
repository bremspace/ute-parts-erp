<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Cetak Label Barcode — Ute Parts</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Courier New', monospace; }
        .label { width: 58mm; padding: 4mm 3mm; border-bottom: 1px dashed #999; page-break-inside: avoid; }
        .label:last-child { border-bottom: none; }
        .brand { font-size: 9px; font-weight: bold; letter-spacing: 1px; }
        .name { font-size: 8px; margin: 2px 0; overflow: hidden; text-wrap: nowrap; }
        .barcode { margin: 2px 0; }
        .barcode svg { width: 100%; height: 22mm; }
        .sku { font-size: 9px; letter-spacing: 1px; }
        .print-page { max-width: 58mm; margin: 0 auto; }
        @media print {
            .no-print { display: none; }
            .print-page { max-width: none; }
        }
    </style>
</head>
<body>
    <div class="no-print" style="padding:16px; font-family: Arial;">
        <a href="javascript:window.print()" style="background:#5B4FE9;color:#fff;padding:10px 18px;border-radius:8px;text-decoration:none;font-weight:bold;">🖨️ Cetak Label ({{ $produks->count() }} produk)</a>
        <a href="javascript:history.back()" style="margin-left:10px;color:#555;">← Kembali</a>
    </div>

    <div class="print-page">
        @foreach($produks as $p)
            @php($barcode = $p->barcode ?? 'UTP-' . str_pad($p->id, 5, '0', STR_PAD_LEFT) . '-????')
            @php($gen = new Picqer\Barcode\BarcodeGeneratorSVG())
            <div class="label">
                <div class="brand">UTE PARTS</div>
                <div class="name">{{ $p->nama }}</div>
                <div class="barcode">{!! $gen->getBarcode($barcode, $gen::TYPE_CODE_128) !!}</div>
                <div class="sku">{{ $barcode }}</div>
            </div>
        @endforeach
    </div>
</body>
</html>