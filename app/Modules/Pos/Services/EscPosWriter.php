<?php

namespace App\Modules\Pos\Services;

use App\Modules\Wms\Services\QrCodeService;

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

    private const SIZE_DBL_H = 0x10;   // double height

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

    /** Font 5x7 untuk nomor TRX di samping QR (bit-image ESC/POS). */
    private const FONT_5X7 = [
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
        'A' => ['01110', '10001', '10001', '11111', '10001', '10001', '10001'],
        'B' => ['11110', '10001', '10001', '11110', '10001', '10001', '11110'],
        'C' => ['01111', '10000', '10000', '10000', '10000', '10000', '01111'],
        'D' => ['11110', '10001', '10001', '10001', '10001', '10001', '11110'],
        'E' => ['11111', '10000', '10000', '11110', '10000', '10000', '11111'],
        'F' => ['11111', '10000', '10000', '11110', '10000', '10000', '10000'],
        'G' => ['01111', '10000', '10000', '10111', '10001', '10001', '01111'],
        'H' => ['10001', '10001', '10001', '11111', '10001', '10001', '10001'],
        'I' => ['01110', '00100', '00100', '00100', '00100', '00100', '01110'],
        'J' => ['00111', '00010', '00010', '00010', '00010', '10010', '01100'],
        'K' => ['10001', '10010', '10100', '11000', '10100', '10010', '10001'],
        'L' => ['10000', '10000', '10000', '10000', '10000', '10000', '11111'],
        'M' => ['10001', '11011', '10101', '10101', '10001', '10001', '10001'],
        'N' => ['10001', '11001', '10101', '10011', '10001', '10001', '10001'],
        'O' => ['01110', '10001', '10001', '10001', '10001', '10001', '01110'],
        'P' => ['11110', '10001', '10001', '11110', '10000', '10000', '10000'],
        'Q' => ['01110', '10001', '10001', '10001', '10101', '10010', '01101'],
        'R' => ['11110', '10001', '10001', '11110', '10100', '10010', '10001'],
        'S' => ['01111', '10000', '10000', '01110', '00001', '00001', '11110'],
        'T' => ['11111', '00100', '00100', '00100', '00100', '00100', '00100'],
        'U' => ['10001', '10001', '10001', '10001', '10001', '10001', '01110'],
        'V' => ['10001', '10001', '10001', '10001', '10001', '01010', '00100'],
        'W' => ['10001', '10001', '10001', '10101', '10101', '10101', '01010'],
        'X' => ['10001', '10001', '01010', '00100', '01010', '10001', '10001'],
        'Y' => ['10001', '10001', '01010', '00100', '00100', '00100', '00100'],
        'Z' => ['11111', '00001', '00010', '00100', '01000', '10000', '11111'],
        '-' => ['00000', '00000', '00000', '11111', '00000', '00000', '00000'],
        ':' => ['00000', '00100', '00100', '00000', '00100', '00100', '00000'],
        '.' => ['00000', '00000', '00000', '00000', '00000', '01100', '01100'],
        '/' => ['00001', '00010', '00010', '00100', '01000', '01000', '10000'],
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
        // Selalu pakai font compressed agar struk tetap 42 kolom dan hemat kertas.
        $this->compressed = true;
        $this->columns = $this->compressed
            ? (self::COLUMNS_COMPRESSED[$paperWidth] ?? 42)
            : (self::COLUMNS_NORMAL[$paperWidth] ?? 32);

        $this->init();

        // ========== HEADER — alamat + telepon digabung jadi satu baris ==========
        $this->alignCenter();
        $this->bold(true)->sizeDoubleWidth(true)->text($data['nama_toko'] ?? 'BREMSPACE')->sizeDoubleWidth(false)->bold(false);

        $slogan = $data['slogan'] ?? 'Pusat Sparepart & Servis Gadget';
        if ($slogan) {
            $this->text($slogan);
        }

        $headerContact = $this->contactLine($data['alamat'] ?? null, $data['telepon'] ?? null);
        foreach ($this->wrap($headerContact) as $contactLine) {
            $this->text($contactLine);
        }

        if (! empty($data['show_cabang']) && ! empty($data['cabang'])) {
            $this->text($data['cabang']);
        }
        $this->rule();

        // ========== QR + TRANSAKSI — QR kecil menyatu dengan blok No. TRX ==========
        $this->alignLeft();
        $noTrx = $data['no_transaksi'] ?? '-';
        $qrUrl = $data['website_medsos'] ?? $data['qr_url'] ?? 'www.bremspace.com';
        $tglStr = $data['tanggal'] ?? $data['waktu'] ?? '';
        $qrShown = ($data['show_qr_retur'] ?? true) && ! empty($noTrx) && $noTrx !== '-';

        $qrMatrix = $data['qr_matrix'] ?? null;
        if ($qrShown && ! is_array($qrMatrix)) {
            $qrMatrix = (new QrCodeService)->generateMatrix($noTrx);
        }
        $qrInline = $qrShown && ! empty($qrMatrix);

        if ($qrInline) {
            // QR + nomor transaksi digambar sebagai satu bit-image satu baris.
            $this->bitImageWithText($qrMatrix, self::normalizeQrSize($data['qr_size'] ?? 4), $noTrx);
        } elseif ($qrShown) {
            $this->qrcode($noTrx, self::normalizeQrSize($data['qr_size'] ?? 4));
        }

        if (! empty($data['label_struk'])) {
            $this->alignCenter();
            $this->bold(true)->text($data['label_struk'])->bold(false);
            $this->alignLeft();
        }

        // Label "No. TRX:" sengaja dihilangkan saat nomor sudah ada di samping QR.
        if (! $qrInline) {
            $this->text($this->pad('No. TRX:', $noTrx));
        }

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
            $this->text($this->pad('Pelanggan:', $pelanggan));

            if (! empty($data['tier']) && $data['tier'] !== 'Retail' && $data['tier'] !== 'Umum') {
                $lbl = in_array($data['tipe_konsumen'] ?? '', ['reseller', 'agen'], true) ? 'Mitra:' : 'Tier:';
                $this->bold(true)->text($this->pad($lbl, $data['tier']))->bold(false);
            }
        }

        // Web + tanggal digabung jadi satu baris ringkas.
        $metaLine = implode(' | ', array_filter([
            'Web: '.$qrUrl,
            (($data['show_waktu'] ?? true) && ! empty($tglStr)) ? 'Tgl: '.$tglStr : null,
        ]));
        if ($metaLine !== '') {
            $this->text($metaLine);
        }

        // ========== ITEMS ==========
        $strikethroughMode = $data['strikethrough_mode'] ?? 'esc_b';

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

            if ($hargaRetail > $hargaJual && $strikethroughMode === 'esc_b') {
                // Baris disusun manual: "1 PCS x " + [ESC B 1]harga normal[ESC B 0] + " harga final" + spasi + subtotal.
                $normal = $this->money($hargaRetail);
                $disc = $this->money($hargaJual);
                $prefix = "{$qtySatuan} x ";
                $totalLineLen = strlen($prefix) + strlen($normal) + 1 + strlen($disc) + strlen($rightVal);

                if ($totalLineLen >= $this->columns) {
                    $this->raw($prefix);
                    $this->escB(true)->raw($normal)->escB(false);
                    $this->raw(' '.$disc."\n");
                    $padRight = max(0, $this->columns - strlen($rightVal));
                    $this->raw(str_repeat(' ', $padRight).$rightVal."\n");
                } else {
                    $gap = $this->columns - $totalLineLen;
                    $this->raw($prefix);
                    $this->escB(true)->raw($normal)->escB(false);
                    $this->raw(' '.$disc);
                    $this->raw(str_repeat(' ', $gap).$rightVal."\n");
                }
            } elseif ($hargaRetail > $hargaJual) {
                $this->text($this->pad("{$qtySatuan} x (".$this->money($hargaRetail).') -> '.$this->money($hargaJual), $rightVal));
            } else {
                $this->text($this->pad("{$qtySatuan} x {$harga}", $rightVal));
            }

            if (! empty($item['catatan'])) {
                foreach ($this->wrap('  * '.$item['catatan']) as $catatanLine) {
                    $this->text($catatanLine);
                }
            }
        }

        // ========== SUBTOTAL BREAKDOWN & TOTALS (Directly follows items) ==========
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

        if (! empty($data['diskon_partai']) && (float) $data['diskon_partai'] > 0) {
            $this->text($this->pad('Diskon Partai:', '-'.$this->money($data['diskon_partai'])));
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

        // Total dalam satu baris (bold, tinggi normal).
        $this->bold(true)->text($this->pad('TOTAL:', $this->money($data['total'])))->bold(false);

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

        if (! empty($data['saldo_poin']) && (float) $data['saldo_poin'] > 0) {
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

        // ========== FOOTER — digabung jadi beberapa baris padat ==========
        $this->alignCenter();
        if (! empty($data['footer_text'])) {
            $this->text($data['footer_text']);
        }

        // Catatan QR sengaja tidak dicetak; hanya field yang memang ada di template.
        $footerInfo = implode(' | ', array_filter([
            ! empty($data['garansi']) ? 'Garansi: '.$data['garansi'] : null,
            $data['kebijakan_retur'] ?? null,
            $data['catatan_kaki'] ?? null,
        ]));
        foreach ($this->wrap($footerInfo) as $ln) {
            $this->text($ln);
        }

        if (! empty($data['is_piutang'])) {
            $this->feed(1);
            $this->text('"Barang diterima & berjanji melunasi sblm jatuh tempo"');
            $this->feed(1);
            $this->text('Penerima,              Kasir,');
            $this->feed(2);
            $this->text('(............)        (............)');
        }

        // Tanpa feed kosong — potong langsung di ujung teks terakhir.
        $this->feed(0)->cut();

        return $this->buf;
    }

    /**
     * Gambar QR + nomor transaksi dalam satu bit-image (QR di kiri, nomor di kanan).
     * ESC * mode 0: satu byte = satu kolom dot horizontal, 8 dot vertikal.
     */
    private function bitImageWithText(array $matrix, int $module, string $text): self
    {
        $rows = count($matrix);
        if ($rows === 0) {
            return $this;
        }
        $cols = count($matrix[0]);
        if ($cols === 0) {
            return $this;
        }

        $quiet = $module; // 1 modul margin putih agar QR tetap mudah dipindai
        $qrWidth = $cols * $module;
        $qrHeight = $rows * $module;
        $qrX = $quiet;
        $qrY = $quiet;
        $gap = 4;
        $available = $this->columns * 8;

        $chars = str_split($this->ascii($text));
        $scale = 1;
        foreach ([3, 2, 1] as $candidate) {
            if ($qrWidth + 2 * $quiet + $gap + count($chars) * 6 * $candidate <= $available) {
                $scale = $candidate;
                break;
            }
        }
        while ($chars !== [] && $qrWidth + 2 * $quiet + $gap + count($chars) * 6 * $scale > $available) {
            array_pop($chars);
        }

        $textWidth = count($chars) * 6 * $scale;
        $textX = $qrX + $qrWidth + $gap;
        $totalWidth = $textX + $textWidth + $quiet;
        $totalHeight = $qrHeight + 2 * $quiet;
        if ($totalWidth <= 0) {
            return $this;
        }
        $textTop = $qrY + max(0, (int) floor(($qrHeight - 7 * $scale) / 2));

        for ($y = 0; $y < $totalHeight; $y += 8) {
            $bytes = '';
            for ($x = 0; $x < $totalWidth; $x++) {
                $byte = 0;
                for ($b = 0; $b < 8; $b++) {
                    $py = $y + $b;
                    $mask = 1 << (7 - $b);

                    if ($x >= $qrX && $x < $qrX + $qrWidth && $py >= $qrY && $py < $qrY + $qrHeight) {
                        $mx = (int) floor(($x - $qrX) / $module);
                        $my = (int) floor(($py - $qrY) / $module);
                        if (! empty($matrix[$my][$mx])) {
                            $byte |= $mask;
                        }
                    }

                    if ($x >= $textX && $py >= $textTop) {
                        $tx = $x - $textX;
                        $charIndex = (int) floor($tx / (6 * $scale));
                        $glyphX = (int) floor(($tx % (6 * $scale)) / $scale);
                        $glyphY = (int) floor(($py - $textTop) / $scale);
                        $ch = $chars[$charIndex] ?? ' ';
                        $glyph = self::FONT_5X7[$ch] ?? self::FONT_5X7[' '];
                        if ($glyphX < 5 && $glyphY >= 0 && $glyphY < 7 && ($glyph[$glyphY][$glyphX] ?? '0') === '1') {
                            $byte |= $mask;
                        }
                    }
                }
                $bytes .= chr($byte);
            }

            $this->buf .= "\x1b\x2a\x00".chr(strlen($bytes) & 0xFF).chr((strlen($bytes) >> 8) & 0xFF).$bytes;
        }

        return $this;
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

    private function sizeDoubleHeight(bool $on): self
    {
        $this->size($on ? self::SIZE_DBL_H : self::SIZE_NORMAL);

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

    /** Baris kontak toko: verbose bila muat, jika tidak pakai versi pendek tanpa label telepon. */
    private function contactLine(?string $alamat, ?string $telepon): string
    {
        $verbose = implode(' - ', array_filter([
            $alamat,
            ! empty($telepon) ? 'Telp/WA: '.$telepon : null,
        ]));

        if (strlen($verbose) <= $this->columns) {
            return $verbose;
        }

        $compact = implode(' | ', array_filter([$alamat, $telepon]));

        return strlen($compact) <= $this->columns ? $compact : $verbose;
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
    private function raw(string $text): self
    {
        $this->buf .= $this->ascii($text);

        return $this;
    }

    /** ESC B n — strikethrough on/off (Epson ESC/POS standar). */
    private function escB(bool $on): self
    {
        $this->buf .= "\x1b\x42".chr($on ? 1 : 0);

        return $this;
    }

    private function ascii(string $text): string
    {
        return preg_replace('/[^\x20-\x7E]/', '', strtr($text, self::TRANSLIT));
    }
}
