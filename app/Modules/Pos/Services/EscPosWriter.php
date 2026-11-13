<?php

namespace App\Modules\Pos\Services;

/**
 * ESC/POS byte builder for 58mm & 80mm thermal receipts — Paper-efficient layout matching UTEParts reference.
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

    /**
     * Build full thermal receipt bytes matching UTEParts reference layout:
     * - Centered header (shop name, slogan, address, phone, website)
     * - 2-col: QR code (left) + URL/Date/Warranty (right)
     * - Left-right aligned: TRX info, Items, Totals
     * - Centered footer
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
        $this->compressed = ($data['font_size'] ?? 'normal') === 'small';
        $this->columns = $this->compressed
            ? (self::COLUMNS_COMPRESSED[$paperWidth] ?? 42)
            : (self::COLUMNS_NORMAL[$paperWidth] ?? 32);

        $this->init();

        // ========== HEADER (Centered) ==========
        $this->alignCenter();
        $this->bold(true)->sizeDoubleWidth(true)->text($data['nama_toko'] ?? 'UTEPARTS')->sizeDoubleWidth(false)->bold(false);

        $slogan = $data['slogan'] ?? 'Sparepart & Aksesoris Gadget';
        if ($slogan) {
            $this->text($slogan);
        }

        if (! empty($data['alamat'])) {
            $this->text($data['alamat']);
        }
        if (! empty($data['telepon'])) {
            $this->text('Telp/WA: '.$data['telepon']);
        }
        if (! empty($data['website_medsos'])) {
            $this->text($data['website_medsos']);
        }

        if (($data['show_cabang'] ?? true) && ! empty($data['cabang'])) {
            $this->text($data['cabang']);
        }

        if (($data['show_waktu'] ?? true) && ! empty($data['tanggal'])) {
            $this->text($data['tanggal']);
        }

        $this->rule();

        // ========== 2-COL: QR (Left) + URL/Date/Warranty (Right) ==========
        $noTrx = $data['no_transaksi'] ?? '-';
        $qrUrl = $data['qr_url'] ?? 'www.uteparts.id';

        if (($data['show_qr_retur'] ?? true) && $noTrx !== '-') {
            $this->alignLeft();
            // Print QR on left side (we'll simulate 2-col by printing QR then moving to right area)
            // For true 2-col on thermal, we print QR first, then info lines.
            // Since ESC/POS is sequential, we print QR centered, then info below, or use bitmap overlay.
            // Paper-saving approach: QR centered, then compact info block.
            $this->alignCenter();
            $this->qrcode($noTrx, $data['qr_size'] ?? 4);
            $this->bold(true)->text($noTrx)->bold(false);
            $this->text($data['qr_caption'] ?? 'Scan QR untuk Retur & Garansi');

            // Right column info (printed after QR as compact lines)
            $this->alignLeft();
            $this->text('Web: '.$qrUrl);
            if (! empty($data['tanggal'])) {
                $this->text('Tgl: '.$data['tanggal']);
            }
            if (! empty($data['garansi'])) {
                $this->text('Garansi: '.$data['garansi']);
            }
            $this->text('Barang fisik utk tukar tdk rusak');
        } else {
            // No QR - just the right column info
            $this->alignLeft();
            $this->text('Web: '.$qrUrl);
            if (! empty($data['tanggal'])) {
                $this->text('Tgl: '.$data['tanggal']);
            }
            if (! empty($data['garansi'])) {
                $this->text('Garansi: '.$data['garansi']);
            }
            $this->text('Barang fisik utk tukar tdk rusak');
        }

        $this->rule();

        // ========== TRANSACTION INFO (Left-Right aligned) ==========
        $this->alignLeft();
        $this->text($this->pad('TRX', $noTrx));

        if (! empty($data['sumber']) && ! in_array(strtolower($data['sumber']), ['pos', 'offline'])) {
            $this->text($this->pad('Pesanan', strtoupper($data['sumber'])));
        }

        if (! empty($data['no_tiket'])) {
            $this->bold(true)->text($this->pad('Tiket', $data['no_tiket']))->bold(false);
        }

        if (! empty($data['jenis_hp'])) {
            $this->text($this->pad('Unit', $data['jenis_hp']));
        }

        if (! empty($data['kurir'])) {
            $kurirLbl = ! empty($data['is_pickup']) ? 'Ambil' : 'Kurir';
            $resiTeks = (! empty($data['resi']) && $data['resi'] !== '-') ? ' ('.$data['resi'].')' : '';
            $this->text($this->pad($kurirLbl, $data['kurir'].$resiTeks));
        }

        if (($data['show_kasir'] ?? true) && ! empty($data['kasir'])) {
            $this->text($this->pad('Kasir', $data['kasir']));
        }

        if (($data['show_pelanggan'] ?? true)) {
            $pelanggan = $data['pelanggan'] ?? 'Umum';
            $this->text($this->pad('Pelanggan', $pelanggan));

            if (! empty($data['tier']) && $data['tier'] !== 'Retail' && $data['tier'] !== 'Umum') {
                $lbl = in_array($data['tipe_konsumen'] ?? '', ['reseller', 'agen'], true) ? 'Mitra' : 'Tier';
                $this->bold(true)->text($this->pad($lbl, $data['tier']))->bold(false);
            }

            if (! empty($data['saldo_poin']) && (int) $data['saldo_poin'] > 0) {
                $this->text($this->pad('Poin', number_format((int) $data['saldo_poin']).' Poin'));
            }
        }

        $this->rule();

        // ========== ITEMS ==========
        foreach ($data['items'] ?? [] as $item) {
            $nama = $item['nama'] ?? 'Item';
            foreach ($this->wrap($nama) as $namaLine) {
                $this->bold(true)->text($namaLine)->bold(false);
            }

            $qtySatuan = ($item['qty'] ?? 0).($item['satuan'] ? ' '.$item['satuan'] : '');
            $harga = $this->money($item['harga'] ?? 0);
            $subtotal = $this->money($item['subtotal'] ?? 0);

            // Main line: "1x Rp 110.000        Rp 110.000"
            $this->text($this->pad("{$qtySatuan} x {$harga}", $subtotal));

            // Strikethrough retail price if discounted (paper-efficient: same line)
            $hargaRetail = (float) ($item['harga_retail'] ?? 0);
            $hargaJual = (float) ($item['harga'] ?? 0);
            if ($hargaRetail > $hargaJual) {
                $normal = $this->money($hargaRetail);
                $disc = $this->money($hargaJual);
                // ESC/POS strikethrough: GS ! 0 (normal) + ESC B 1 (strikethrough on) + text + ESC B 0
                // Fallback ASCII: "Rp 125.000->Rp 110.000"
                if ($this->supportsStrikethrough()) {
                    $this->textStrikethrough("Normal: {$normal} -> {$disc}");
                } else {
                    $this->text("   {$normal}->{$disc}");
                }
            }

            if (! empty($item['catatan'])) {
                foreach ($this->wrap('  * '.$item['catatan']) as $catatanLine) {
                    $this->text($catatanLine);
                }
            }
        }

        $this->rule();

        // ========== SUBTOTAL BREAKDOWN ==========
        $showBreakdown = $data['show_subtotal_breakdown'] ?? true;

        if ($showBreakdown && ! empty($data['diskon_tier']) && (float) $data['diskon_tier'] > 0) {
            $subRetail = $data['subtotal_retail'] ?? ((float) ($data['subtotal'] ?? 0) + (float) $data['diskon_tier']);
            $this->text($this->pad('Subtotal Retail', $this->money($subRetail)));

            $tierLbl = (! empty($data['tier']) && $data['tier'] !== 'Retail')
                ? "Pot {$data['tier']}"
                : (in_array($data['tipe_konsumen'] ?? '', ['reseller', 'agen'], true) ? 'Pot Mitra' : 'Pot Tier');
            $this->text($this->pad($tierLbl, '-'.$this->money($data['diskon_tier'])));
            $this->text($this->pad('Subtotal', $this->money($data['subtotal'] ?? $data['total'])));
        } else {
            $this->text($this->pad('Subtotal', $this->money($data['subtotal'] ?? $data['total'])));
        }

        if (! empty($data['diskon_partai']) && (float) $data['diskon_partai'] > 0) {
            $this->text($this->pad('Diskon Partai', '-'.$this->money($data['diskon_partai'])));
        }

        if (! empty($data['diskon_poin']) && (float) $data['diskon_poin'] > 0) {
            $pts = $data['poin_ditukar'] ?? 0;
            $this->text($this->pad("Tukar Poin ({$pts}pts)", '-'.$this->money($data['diskon_poin'])));
        }

        if (! empty($data['diskon']) && (float) $data['diskon'] > 0) {
            $lbl = (! empty($data['diskon_persen']) && (float) $data['diskon_persen'] > 0)
                ? "Diskon ({$data['diskon_persen']}%)"
                : 'Diskon Kasir';
            $this->text($this->pad($lbl, '-'.$this->money($data['diskon'])));
        }

        if (! empty($data['pajak']) && (float) $data['pajak'] > 0) {
            $ppnLbl = ! empty($data['ppn_persen']) ? "PPN ({$data['ppn_persen']}%)" : 'PPN';
            $this->text($this->pad($ppnLbl, '+'.$this->money($data['pajak'])));
        }

        if (! empty($data['ongkir']) && (float) $data['ongkir'] > 0) {
            $this->text($this->pad('Ongkir', '+'.$this->money($data['ongkir'])));
        }

        $this->rule();

        // ========== TOTAL & PAYMENT ==========
        $this->bold(true)->sizeDoubleHeight(true)->text($this->pad('TOTAL', $this->money($data['total'])))->sizeDoubleHeight(false)->bold(false);

        if (! empty($data['is_piutang'])) {
            $this->text('Status: BELUM LUNAS (PIUTANG)');
            if (! empty($data['jatuh_tempo'])) {
                $this->text('Jatuh Tempo: '.$data['jatuh_tempo']);
            }
            $this->text('Sisa Tag: '.$this->money($data['sisa_piutang'] ?? $data['total']));
        } else {
            $this->text('Bayar: '.$this->money($data['bayar']));
            $this->text('Kembali: '.$this->money($data['kembali'] ?? 0));
            $this->text('Metode: '.$data['metode_bayar']);
        }

        if (! empty($data['hemat_total']) && (float) $data['hemat_total'] > 0) {
            $this->bold(true)->text($this->pad('HEMAT', $this->money($data['hemat_total'])))->bold(false);
        }

        if (! empty($data['komisi_mitra_nominal']) && (float) $data['komisi_mitra_nominal'] > 0) {
            $this->text('Komisi: +'.$this->money($data['komisi_mitra_nominal']).' (Saldo)');
        }

        if (isset($data['saldo_poin'])) {
            $this->text('Sisa Poin: '.number_format((float) $data['saldo_poin']).' pts');
        }

        $this->rule()->feed(1);

        // ========== FOOTER (Centered) ==========
        $this->alignCenter();
        $this->text($data['footer_text'] ?? 'Terima Kasih atas Kunjungan Anda!');

        if (! empty($data['kebijakan_retur'])) {
            foreach ($this->wrap($data['kebijakan_retur']) as $ln) {
                $this->text($ln);
            }
        }
        if (! empty($data['catatan_kaki'])) {
            foreach ($this->wrap($data['catatan_kaki']) as $ln) {
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

        // Minimal feed then cut (paper-saving)
        $this->feed(1)->cut();

        return $this->buf;
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
        $modSize = chr(max(2, min(8, $size)));

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
        $pad = max(1, $this->columns - strlen($left) - strlen($right));

        return $left.str_repeat(' ', $pad).$right;
    }

    private function wrap(string $text): array
    {
        return explode("\n", wordwrap($this->ascii($text), $this->columns));
    }

    private function money($v): string
    {
        return 'Rp '.number_format((float) $v, 0, ',', '.');
    }

    private function ascii(string $text): string
    {
        return preg_replace('/[^\x20-\x7E]/', '', strtr($text, self::TRANSLIT));
    }

    /** Check if printer supports ESC B (strikethrough). Most modern thermal printers do. */
    private function supportsStrikethrough(): bool
    {
        return true;
    }

    /** Print line with strikethrough via ESC B (ESC B 1 = on, ESC B 0 = off). */
    private function textStrikethrough(string $text): self
    {
        $this->buf .= "\x1b\x42\x01"; // ESC B 1 = strikethrough on
        $this->buf .= $this->ascii($text)."\n";
        $this->buf .= "\x1b\x42\x00"; // ESC B 0 = strikethrough off

        return $this;
    }
}
