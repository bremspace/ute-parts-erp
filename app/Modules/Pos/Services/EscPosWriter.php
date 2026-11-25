<?php

namespace App\Modules\Pos\Services;

/**
 * ESC/POS byte builder for 58mm & 80mm thermal receipts — paper-efficient Opsi B layout.
 * Header alamat+telepon digabung, QR kecil di blok No. TRX, footer padat.
 * Pure PHP, no DB access, deterministic — output depends only on the input array.
 * Bytes are sent raw to the printer (CUPS `lp -o raw` or Web Bluetooth client).
 * Preview parity: JS EscPos class in thermal-print.js mirrors this byte-for-byte.
 */
class EscPosWriter
{
    /** Paper width enum. */
    public const WIDTH_58MM = 58;

    public const WIDTH_80MM = 80;

    /** Columns per paper width at default font (Font A 12x24 dots, compressed GS ! 1 = 8x24). */
    private const COLUMNS_NORMAL = [
        self::WIDTH_58MM => 32,  // 58mm / 12 dots ≈ 48, safe 32
        self::WIDTH_80MM => 48,  // 80mm / 12 dots ≈ 64, safe 48
    ];

    private const COLUMNS_COMPRESSED = [
        self::WIDTH_58MM => 42,  // 58mm / 8 dots = 72, safe 42
        self::WIDTH_80MM => 64,  // 80mm / 8 dots = 100, safe 64
    ];

    /** Font size modes (GS ! n). */
    public const FONT_NORMAL = 0x00;   // 1x1 normal

    public const FONT_COMPRESSED = 0x01; // GS ! 1 = half width (8x24)

    /** Double-size flags for GS ! n. */
    private const SIZE_NORMAL = 0x00;


    private const SIZE_DBL_W = 0x80;   // double width

    private int $columns = 32;

    private bool $compressed = false;

    private string $buf = '';

    /** Non-ASCII → nearest ASCII for cp437-safe printer output. */
    private const TRANSLIT = [
        'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
        'á' => 'a', 'à' => 'a', 'â' => 'a', 'ä' => 'a',
        'í' => 'i', 'ì' => 'i', 'î' => 'i', 'ï' => 'i',
        'ó' => 'o', 'ò' => 'o', 'ô' => 'o', 'ö' => 'o',
        'ú' => 'u', 'ù' => 'u', 'û' => 'u', 'ü' => 'u',
        'ñ' => 'n', 'ç' => 'c',
        '±' => '+/-', '×' => 'x', '÷' => '/',
        '–' => '-', '—' => '-', '’' => "'", '‘' => "'",
        '“' => '"', '”' => '"',
        '→' => '->',
    ];

    /** Font 5x7 minimal untuk harga Retail yang dicoret via raster GS v 0. */
    private const STRIKE_FONT = [
        '0' => ['01110', '10001', '10011', '10101', '11001', '10001', '01110'],
        '1' => ['00100', '01100', '00100', '00100', '00100', '00100', '01110'],
        '2' => ['01110', '10001', '00001', '00010', '00100', '01000', '11111'],
        '3' => ['11110', '00001', '00001', '01110', '00001', '00001', '11110'],
        '4' => ['00010', '00110', '01010', '10010', '11111', '00010', '00010'],
        '5' => ['11111', '10000', '11110', '00001', '00001', '10001', '01110'],
        '6' => ['00110', '01000', '10000', '11110', '10001', '10001', '01110'],
        '7' => ['11111', '00001', '00010', '00100', '01000', '01000', '01000'],
        '8' => ['01110', '10001', '10001', '01110', '10001', '10001', '01110'],
        '9' => ['01110', '10001', '10001', '01111', '00001', '00010', '01100'],
        'R' => ['11110', '10001', '10001', '11110', '10100', '10010', '10001'],
        'P' => ['11110', '10001', '10001', '11110', '10000', '10000', '10000'],
        'H' => ['10001', '10001', '10001', '11111', '10001', '10001', '10001'],
        'A' => ['01110', '10001', '10001', '11111', '10001', '10001', '10001'],
        'G' => ['01111', '10000', '10000', '10111', '10001', '10001', '01111'],
        'E' => ['11111', '10000', '10000', '11110', '10000', '10000', '11111'],
        'T' => ['11111', '00100', '00100', '00100', '00100', '00100', '00100'],
        'L' => ['10000', '10000', '10000', '10000', '10000', '10000', '11111'],
        'I' => ['01110', '00100', '00100', '00100', '00100', '00100', '01110'],
        ':' => ['00000', '00100', '00100', '00000', '00100', '00100', '00000'],
        'p' => ['11110', '10001', '10001', '11110', '10000', '10000', '10000'],
        '.' => ['00000', '00000', '00000', '00000', '00000', '01100', '01100'],
        '-' => ['00000', '00000', '00000', '11111', '00000', '00000', '00000'],
        ' ' => ['00000', '00000', '00000', '00000', '00000', '00000', '00000'],
    ];

    /**
     * Build full thermal receipt bytes with the paper-efficient Opsi B layout:
     * - Centered header (shop name, slogan, alamat + telepon in one line)
     * - Small QR code directly above the No. TRX identity block
     * - Left-right aligned: TRX info, Items, Totals
     * - Compact centered footer (few lines only)
     *
     * @param  array  $data  {
     *                       items: [{nama, qty, harga, subtotal, harga_retail?, satuan?, catatan?, diskon_per_unit?, diskon_total?}],
     *                       total, bayar, kembali: float,
     *                       no_transaksi, metode_bayar, tanggal, kasir, cabang: string,
     *                       pelanggan?, tier?, tipe_konsumen?, saldo_poin?, poin_ditukar?,
     *                       diskon_tier?, diskon_partai?, diskon_poin?, diskon?, diskon_persen?, diskon_nominal?,
     *                       pajak?, ppn_persen?, ongkir?, subtotal?, subtotal_retail?, hemat_total?,
     *                       is_piutang?, jatuh_tempo?, sisa_piutang?, komisi_mitra_nominal?,
     *                       no_tiket?, jenis_hp?, kurir?, is_pickup?, resi?, sumber?, garansi?,
     *                       footer_text?, kebijakan_retur?, catatan_kaki?,
     *                       nama_toko?, slogan?, alamat?, telepon?, website_medsos?, show_cabang?, show_kasir?, show_pelanggan?, show_waktu?,
     *                       show_qr_retur?, qr_caption?, qr_size?, qr_url?,
     *                       paper_width?: int (58|80), font_size?: string ('normal'|'small'),
     *                       }
     */
    public function receipt(array $data): string
    {
        $paperWidth = $data['paper_width'] ?? self::WIDTH_58MM;
        $this->compressed = ($data['font_size'] ?? 'small') === 'small';
        $this->columns = $this->compressed
            ? (self::COLUMNS_COMPRESSED[$paperWidth] ?? 42)
            : (self::COLUMNS_NORMAL[$paperWidth] ?? 32);

        $this->init();

        // ========== HEADER — alamat + telepon digabung jadi satu baris ==========
        $this->alignCenter();
        $this->bold(true)
            ->size(0x90) // double width + double height
            ->text($data['nama_toko'] ?? 'BREMSPACE')
            ->size(0x00)
            ->bold(false);

        $slogan = $data['slogan'] ?? 'Pusat Sparepart & Servis Gadget';
        if ($slogan) {
            $this->text($slogan);
        }

        // Alamat dan nomor HP各自 baris, agar tidak ada tanda '-' menggantung.
        if (! empty($data['alamat'])) {
            foreach ($this->wrap($data['alamat']) as $alamatLine) {
                $this->text($alamatLine);
            }
        }
        if (! empty($data['telepon'])) {
            $this->text($data['telepon']);
        }

        if (! empty($data['show_cabang']) && ! empty($data['cabang'])) {
            $this->text($data['cabang']);
        }
        $this->rule();

        // ========== QR + TRANSAKSI — QR tengah, ukuran penuh, ada jarak ke bawah ==========
        $this->alignCenter();
        $noTrx = $data['no_transaksi'] ?? '-';
        $tglStr = $data['tanggal'] ?? $data['waktu'] ?? '';
        $qrShown = ($data['show_qr_retur'] ?? true) && ! empty($noTrx) && $noTrx !== '-';

        if ($qrShown) {
            $this->qrcode($noTrx, self::effectiveQrSize($data['qr_size'] ?? 4));
            // Beri satu baris kosong agar QR tidak menempel pada No. TRX.
            $this->feed(1);
        }
        $this->alignLeft();

        if (! empty($data['label_struk'])) {
            $this->alignCenter();
            $this->bold(true)->text($data['label_struk'])->bold(false);
            $this->alignLeft();
        }

        $this->text($this->pad('No. TRX:', $noTrx));

        if (! empty($data['no_transaksi_asal']) && $data['no_transaksi_asal'] !== '-') {
            $this->text($this->pad('Asal TRX:', $data['no_transaksi_asal']));
        }

        if (! empty($data['sumber']) && ! in_array(strtolower($data['sumber']), ['pos', 'offline'], true)) {
            $this->text($this->pad('Pesanan:', strtoupper($data['sumber'])));
        }

        if (! empty($data['no_tiket'])) {
            $this->bold(true)->text($this->pad('No. Tiket:', $data['no_tiket']))->bold(false);
        }

        if (! empty($data['jenis_hp'])) {
            $this->text($this->pad('Unit:', $data['jenis_hp']));
        }

        if (! empty($data['kurir'])) {
            $kurirLbl = ! empty($data['is_pickup']) ? 'Ambil:' : 'Kurir:';
            $resiTeks = (! empty($data['resi']) && $data['resi'] !== '-') ? ' ('.$data['resi'].')' : '';
            $this->text($this->pad($kurirLbl, $data['kurir'].$resiTeks));
        }

        if (($data['show_kasir'] ?? true) && ! empty($data['kasir'])) {
            $this->text($this->pad('Kasir:', $data['kasir']));
        }

        if (($data['show_pelanggan'] ?? true)) {
            $pelanggan = $data['pelanggan'] ?? 'Umum';
            $pelanggan = preg_replace('/^Pelanggan\s+/i', '', (string) $pelanggan) ?: 'Umum';
            $this->text($this->pad('Pelanggan:', $pelanggan));

            if (! empty($data['tier']) && $data['tier'] !== 'Retail' && $data['tier'] !== 'Umum') {
                $lbl = in_array($data['tipe_konsumen'] ?? '', ['reseller', 'agen'], true) ? 'Mitra:' : 'Tier:';
                $this->bold(true)->text($this->pad($lbl, $data['tier']))->bold(false);
            }
        }

        // Web sengaja tidak dicetak; cukup tanggal bila diaktifkan.
        $metaLine = (($data['show_waktu'] ?? true) && ! empty($tglStr)) ? 'Tgl: '.$tglStr : '';
        if ($metaLine !== '') {
            $this->text($metaLine);
        }

        // ========== ITEMS ==========
        $strikethroughMode = $data['strikethrough_mode'] ?? 'esc_b';
        $opsiBItem = false;

        // Diskon partai adalah persentase dari subtotal sebelum potongan partai.
        // Faktor ini dipakai untuk menampilkan harga satuan "versi partai".
        $faktorPartai = null;
        $diskonPartai = (float) ($data['diskon_partai'] ?? 0);
        $subtotalSebelumPartai = (float) ($data['subtotal'] ?? 0);
        if ($diskonPartai > 0 && $subtotalSebelumPartai > 0) {
            $faktorPartai = max(0.0, min(1.0, 1 - ($diskonPartai / $subtotalSebelumPartai)));
        }

        foreach ($data['items'] ?? [] as $item) {
            $nama = $item['nama'] ?? 'Item';
            foreach ($this->wrap($nama) as $namaLine) {
                $this->bold(true)->text($namaLine)->bold(false);
            }

            $qtySatuan = ($item['qty'] ?? 1).' '.$this->unitLabel($item['satuan'] ?? null);
            $harga = $this->money($item['harga'] ?? 0);
            $rightVal = $this->numberOnly($item['subtotal'] ?? 0);

            // Inline strikethrough: normal price next to final price without extra rows
            $hargaRetail = (float) ($item['harga_retail'] ?? 0);
            $hargaJual = (float) ($item['harga'] ?? 0);
            $unitLbl = $this->unitLabel($item['satuan'] ?? null);

            if ($hargaRetail > $hargaJual && in_array($strikethroughMode, ['esc_b', 'reverse'], true)) {
                // Opsi B: harga Retail dicoret, Harga Box, qty x box = subtotal, Potongan Box.
                $opsiBItem = true;
                $qty = (int) ($item['qty'] ?? 1);
                $normal = $this->money($hargaRetail);
                $disc = $this->money($hargaJual);
                $potongan = $this->money(($hargaRetail - $hargaJual) * $qty);

                // Seluruh baris dicetak sebagai raster agar label + nominal tetap searis.
                $this->strikeRaster('Harga Retail: '.$normal, strlen('Harga Retail: '), strlen($normal));
                $this->feed(1)->alignLeft();

                $this->text('Harga Box: '.$disc);
                $this->text($qtySatuan.' x '.$disc.' = '.$rightVal);
                $this->text('Potongan Box: -'.$potongan);
            } elseif ($hargaRetail > $hargaJual) {
                $this->text($this->pad("{$qtySatuan} x (".$this->money($hargaRetail).') -> '.$this->money($hargaJual), $rightVal));
            } else {
                $this->text($this->pad("{$qtySatuan} x {$harga}", $rightVal));
            }

            // Harga satuan setelah diskon partai, biar konsumen tahu harga per PCS versi partai.
            if ($faktorPartai !== null && $hargaJual > 0) {
                $hargaPartai = round($hargaJual * $faktorPartai);
                $this->text('Harga Partai/'.$unitLbl.': '.$this->money($hargaPartai));
            }

            if (! empty($item['catatan'])) {
                foreach ($this->wrap('  * '.$item['catatan']) as $catatanLine) {
                    $this->text($catatanLine);
                }
            }
        }

        // ========== SUBTOTAL BREAKDOWN & TOTALS ==========
        // Opsi B sudah menampilkan subtotal dan Potongan Box di baris item.
        if (! $opsiBItem) {
            $showBreakdown = $data['show_subtotal_breakdown'] ?? true;

            if ($showBreakdown && ! empty($data['diskon_tier']) && (float) $data['diskon_tier'] > 0) {
                $subRetail = $data['subtotal_retail'] ?? ((float) ($data['subtotal'] ?? 0) + (float) $data['diskon_tier']);
                $this->text($this->pad('Subtotal Retail:', $this->money($subRetail)));

                $tierLbl = (! empty($data['tier']) && $data['tier'] !== 'Retail')
                    ? "Pot {$data['tier']}:"
                    : (in_array($data['tipe_konsumen'] ?? '', ['reseller', 'agen'], true) ? 'Pot Mitra:' : 'Pot Tier:');
                $this->text($this->pad($tierLbl, '-'.$this->money($data['diskon_tier'])));
                $this->text($this->pad('Subtotal:', $this->money($data['subtotal'] ?? $data['total'])));
            } else {
                $this->text($this->pad('Subtotal:', $this->money($data['subtotal'] ?? $data['total'])));
            }

            if (! empty($data['diskon_box']) && (float) $data['diskon_box'] > 0) {
                $this->text($this->pad('Diskon Box/Kemasan:', '-'.$this->money($data['diskon_box'])));
            }
        }

        if (! empty($data['diskon_partai']) && (float) $data['diskon_partai'] > 0) {
            $persenPartai = self::diskonPartaiPersen(
                $data['diskon_partai_persen'] ?? null,
                $data['diskon_partai'],
                $data['subtotal'] ?? 0
            );
            $labelPartai = 'Diskon Partai:'.($persenPartai !== null ? ' '.self::formatPersen($persenPartai).'%' : '');
            $this->text($this->pad($labelPartai, '-'.$this->money($data['diskon_partai'])));
        }

        if (! empty($data['diskon_poin']) && (float) $data['diskon_poin'] > 0) {
            $pts = $data['poin_ditukar'] ?? 0;
            $this->text($this->pad("Tukar Poin ({$pts}pts):", '-'.$this->money($data['diskon_poin'])));
        }

        if (! empty($data['diskon']) && (float) $data['diskon'] > 0) {
            $lbl = (! empty($data['diskon_persen']) && (float) $data['diskon_persen'] > 0)
                ? "Diskon ({$data['diskon_persen']}%):"
                : 'Diskon Kasir:';
            $this->text($this->pad($lbl, '-'.$this->money($data['diskon'])));
        }

        if (! empty($data['pajak']) && (float) $data['pajak'] > 0) {
            $ppnLbl = ! empty($data['ppn_persen']) ? "PPN ({$data['ppn_persen']}%):" : 'PPN:';
            $this->text($this->pad($ppnLbl, '+'.$this->money($data['pajak'])));
        }

        if (! empty($data['ongkir']) && (float) $data['ongkir'] > 0) {
            $this->text($this->pad('Ongkir:', '+'.$this->money($data['ongkir'])));
        }

        // TOTAL dibuat lebih besar (double width) tapi tetap satu baris.
        $this->bold(true)
            ->sizeDoubleWidth(true)
            ->text('TOTAL: '.$this->money($data['total']))
            ->sizeDoubleWidth(false)
            ->bold(false);

        if (! empty($data['is_piutang'])) {
            $this->text($this->pad('Status:', 'BELUM LUNAS (PIUTANG)'));
            if (! empty($data['jatuh_tempo'])) {
                $this->text($this->pad('Jatuh Tempo:', $data['jatuh_tempo']));
            }
            $this->text($this->pad('Sisa Tag:', $this->money($data['sisa_piutang'] ?? $data['total'])));
        } else {
            $metodeStr = strtoupper($data['metode_bayar'] ?? $data['metode'] ?? 'TUNAI');
            $this->text($this->pad("BAYAR ({$metodeStr}):", $this->money($data['bayar'] ?? $data['total'])));
            $this->text($this->pad('KEMBALI:', $this->money($data['kembali'] ?? $data['kembalian'] ?? 0)));
        }

        if (isset($data['saldo_poin'])) {
            $this->text($this->pad('Sisa Saldo Poin:', $this->numberOnly($data['saldo_poin']).' pts'));
        }

        if (! empty($data['hemat_total']) && (float) $data['hemat_total'] > 0) {
            $this->bold(true)->text($this->pad('HEMAT:', $this->money($data['hemat_total'])))->bold(false);
        }

        if (! empty($data['komisi_mitra_nominal']) && (float) $data['komisi_mitra_nominal'] > 0) {
            $this->text($this->pad('Komisi:', '+'.$this->money($data['komisi_mitra_nominal']).' (Saldo)'));
        }

        if (! empty($data['goodwill'])) {
            $this->text($this->pad('Goodwill:', (string) $data['goodwill']));
        }

        if (! empty($data['voucher'])) {
            $this->text($this->pad('Voucher:', (string) $data['voucher']));
        }

        if (! empty($data['komplain'])) {
            foreach ($this->wrap((string) $data['komplain']) as $ln) {
                $this->text($ln);
            }
        }

        $this->rule();

        // ========== FOOTER — Opsi B: cukup garansi satu baris di paling bawah ==========
        $this->alignCenter();
        if (! empty($data['garansi'])) {
            foreach ($this->wrap('Garansi: '.$this->compactGaransi($data['garansi'])) as $ln) {
                $this->text($ln);
            }
        }

        if (! empty($data['is_piutang'])) {
            $this->feed(1);
            $this->text('"Barang diterima & berjanji melunasi sblm jatuh tempo"');
            $this->feed(1);
            $this->text('Penerima,              Kasir,');
            $this->feed(2);
            $this->text('(............)        (............)');
        }

        // Margin bawah dipepet: potong langsung setelah baris terakhir.
        $this->feed(0)->cut();

        return $this->buf;
    }

    /**
     * Persentase diskon partai yang dipakai.
     * Mengutamakan nilai persen eksplisit dari PartaiPricingService; bila tidak ada
     * (mis. struk lama), diturunkan dari diskon/subtotal yang keduanya tersimpan.
     */
    public static function diskonPartaiPersen(mixed $override, mixed $diskon, mixed $subtotal): ?float
    {
        $diskon = (float) $diskon;
        $subtotal = (float) $subtotal;

        if ($diskon <= 0) {
            return null;
        }

        if (is_numeric($override) && (float) $override > 0) {
            return round((float) $override, 2);
        }

        if ($subtotal <= 0) {
            return null;
        }

        return round(($diskon / $subtotal) * 100, 2);
    }

    /** Format persentase ringkas: 3 → "3", 3.5 → "3.5", 3.25 → "3.25". */
    public static function formatPersen(?float $persen): string
    {
        if ($persen === null) {
            return '';
        }

        return rtrim(rtrim(number_format($persen, 2, '.', ''), '0'), '.');
    }

    /**
     * Ukuran QR lama disimpan dalam "px" (40/60/80/100/150).
     * Printer memakai module size 2..8, jadi nilai lama > 8 dinormalisasi ke 4
     * (≈48px di preview) agar tetap scan-able namun hemat kertas.
     */
    public static function normalizeQrSize(mixed $value): int
    {
        if (! is_numeric($value)) {
            return 4;
        }

        $n = (int) $value;
        if ($n <= 0) {
            return 4;
        }
        if ($n > 8) {
            return 4;
        }

        return max(2, min(8, $n));
    }

    /** Ukuran QR efektif yang dipakai struk: ukuran normal modul 4 agar mudah dipindai. */
    public static function effectiveQrSize(mixed $value): int
    {
        return self::normalizeQrSize($value);
    }

    /** ESC - n — underline (fallback bila raster tidak didukung). */

    /**
     * Cetak teks dengan coret tengah memakai raster GS v 0.
     * Dipakai untuk "Harga Retail" karena printer tidak punya perintah strikethrough.
     */
    private function strikeRaster(string $text, int $strikeFrom = 0, ?int $strikeLength = null): self
    {
        $chars = str_split($this->ascii($text));
        if ($chars === []) {
            return $this;
        }

        $scaleY = 2;
        $width = count($chars) * 6;
        $height = 7 * $scaleY;
        $bytesPerRow = (int) ceil($width / 8);
        $rows = [];
        $strikeEnd = $strikeLength === null
            ? count($chars)
            : min(count($chars), $strikeFrom + $strikeLength);

        foreach ([0, 1, 2, 3, 4, 5, 6] as $gy) {
            for ($sy = 0; $sy < $scaleY; $sy++) {
                $bits = array_fill(0, $bytesPerRow * 8, 0);

                foreach ($chars as $i => $ch) {
                    $key = strtoupper($ch);
                    $glyph = self::STRIKE_FONT[$key] ?? self::STRIKE_FONT[' '];
                    for ($gx = 0; $gx < 5; $gx++) {
                        if ($glyph[$gy][$gx] === '1') {
                            $bits[$i * 6 + $gx] = 1;
                        }
                    }
                }

                // Garis coret hanya pada rentang nominal (baris ke-4 dari 7).
                if ($gy === 3) {
                    for ($i = max(0, $strikeFrom); $i < $strikeEnd; $i++) {
                        for ($gx = 0; $gx < 6; $gx++) {
                            $bits[$i * 6 + $gx] = 1;
                        }
                    }
                }

                $row = '';
                for ($b = 0; $b < $bytesPerRow; $b++) {
                    $byte = 0;
                    for ($bit = 0; $bit < 8; $bit++) {
                        if ($bits[$b * 8 + $bit]) {
                            $byte |= 1 << (7 - $bit);
                        }
                    }
                    $row .= chr($byte);
                }
                $rows[] = $row;
            }
        }

        $this->buf .= "\x1d\x76\x30\x00"
            .chr($bytesPerRow & 0xFF).chr(($bytesPerRow >> 8) & 0xFF)
            .chr($height & 0xFF).chr(($height >> 8) & 0xFF)
            .implode('', $rows);

        return $this;
    }

    /** ESC/POS standard Model 2 QR Code. */
    public function qrcode(string $content, int $size = 4): self
    {
        $d = $this->ascii($content);
        if ($d === '') {
            return $this;
        }

        $len = strlen($d) + 3;
        $pL = chr($len & 0xFF);
        $pH = chr(($len >> 8) & 0xFF);
        $modSize = chr(self::normalizeQrSize($size));

        // 1. Model 2: GS ( k 4 0 49 65 50 0
        $this->buf .= "\x1d\x28\x6b\x04\x00\x31\x41\x32\x00";
        // 2. Module size: GS ( k 3 0 49 67 size
        $this->buf .= "\x1d\x28\x6b\x03\x00\x31\x43".$modSize;
        // 3. Error correction M: GS ( k 3 0 49 69 49
        $this->buf .= "\x1d\x28\x6b\x03\x00\x31\x45\x31";
        // 4. Store data: GS ( k pL pH 49 80 48 data
        $this->buf .= "\x1d\x28\x6b".$pL.$pH."\x31\x50\x30".$d;
        // 5. Print: GS ( k 3 0 49 81 48
        $this->buf .= "\x1d\x28\x6b\x03\x00\x31\x51\x30";

        return $this;
    }

    private function init(): self
    {
        $this->buf = "\x1b\x40"; // ESC @ = init printer

        // Set compressed font for paper saving (GS ! 1 = half width)
        if ($this->compressed) {
            $this->buf .= "\x1d\x21\x01";
        }

        return $this;
    }

    private function alignCenter(): self
    {
        $this->buf .= "\x1b\x61\x01";

        return $this;
    }

    private function alignLeft(): self
    {
        $this->buf .= "\x1b\x61\x00";

        return $this;
    }

    private function bold(bool $on): self
    {
        $this->buf .= "\x1b\x45".chr($on ? 1 : 0);

        return $this;
    }

    /** ESC G n — double-strike, membuat huruf lebih tebal pada printer thermal. */

    /** GS B n — white/black reverse. Dipakai untuk menandai harga lama. */

    /** GS ! n — size flags: 0 normal, 0x10 double height, 0x80 double width, 0x90 both. */
    private function size(int $n): self
    {
        $this->buf .= "\x1d\x21".chr($n);

        return $this;
    }

    private function sizeDoubleWidth(bool $on): self
    {
        $this->size($on ? self::SIZE_DBL_W : self::SIZE_NORMAL);

        return $this;
    }


    private function rule(): self
    {
        return $this->text(str_repeat('-', $this->columns));
    }

    private function feed(int $n): self
    {
        $this->buf .= str_repeat("\n", $n);

        return $this;
    }

    private function cut(): self
    {
        $this->buf .= "\x1d\x56\x42\x00";

        return $this;
    } // GS V B 0 = full cut

    private function text(string $text): self
    {
        $this->buf .= $this->ascii($text)."\n";

        return $this;
    }

    /** Left + right on one line within column width. */
    private function pad(string $left, string $right): string
    {
        $left = $this->ascii($left);
        $right = $this->ascii($right);
        $totalLen = strlen($left) + strlen($right);

        if ($totalLen >= $this->columns) {
            $padRight = max(0, $this->columns - strlen($right));

            return $left."\n".str_repeat(' ', $padRight).$right;
        }

        $pad = $this->columns - $totalLen;

        return $left.str_repeat(' ', $pad).$right;
    }

    /** Ringkas teks garansi agar cukup satu baris di kertas 58mm. */
    private function compactGaransi(string $garansi): string
    {
        $s = $this->ascii($garansi);
        $s = preg_replace('/\b20\d{2}\b/', '', $s) ?? $s;
        $s = str_replace(['(', ')'], '', $s);
        $s = trim(preg_replace('/\s+/', ' ', $s) ?? $s);
        $s = rtrim($s, '/ -');

        return $s !== '' ? $s : $this->ascii($garansi);
    }

    private function wrap(string $text): array
    {
        $clean = $this->ascii($text);
        $lines = [];
        while (strlen($clean) > $this->columns) {
            // Cari pemisah terakhir (slash/koma/tanda kurung/spasi) pada batas kolom
            $cut = $this->columns;
            for ($i = $this->columns; $i >= 1; $i--) {
                if (str_contains('/,-) ', $clean[$i - 1])) {
                    $cut = $i;

                    break;
                }
            }
            $lines[] = rtrim(substr($clean, 0, $cut), ' ');
            $clean = substr($clean, $cut);
        }
        if (trim($clean) !== '') {
            $lines[] = $clean;
        }

        return $lines;
    }

    private function money($v): string
    {
        return 'Rp '.number_format((float) $v, 0, ',', '.');
    }

    /** Angka mentah tanpa "Rp" — kolom kanan baris item (senilai contoh pengaturan). */
    private function numberOnly(string|int|float $v): string
    {
        return number_format((float) $v, 0, ',', '.');
    }

    /** Satuan singkat (pcs/box/set) UPPERCASE agar padan dengan contoh struk. */
    private function unitLabel(?string $satuan): string
    {
        $u = $this->ascii($satuan ?: 'PCS');

        return strlen($u) <= 4 ? strtoupper($u) : $u;
    }

    /** Raw text tanpa LF — untuk baris yang disusun dengan escape di tengah (ESC B). */

    /** ESC B n — strikethrough on/off (Epson ESC/POS standar). */

    private function ascii(string $text): string
    {
        return preg_replace('/[^\x20-\x7E]/', '', strtr($text, self::TRANSLIT));
    }
}
