<?php

namespace App\Modules\Pos\Services;

/**
 * Minimal ESC/POS byte builder for 58mm & 80mm thermal receipts (ADR 0009 fallback path).
 * Pure PHP, no DB access, deterministic — output depends only on the input array.
 * Bytes are sent raw to the printer (CUPS `lp -o raw` or Web Bluetooth client).
 */
class EscPosWriter
{
    /** Paper width enum. */
    public const WIDTH_58MM = 58;

    public const WIDTH_80MM = 80;

    /** Columns per paper width at default font (Font A 12x24 dots). */
    private const COLUMNS_BY_WIDTH = [
        self::WIDTH_58MM => 32,  // 58mm / 12 dots = ~48 chars max, use 32 for safe printable area
        self::WIDTH_80MM => 42,  // 80mm / 12 dots = ~64 chars max, use 42 for safe printable area
    ];

    /** Font size modes. */
    public const FONT_NORMAL = 0x00;  // 1x1

    public const FONT_SMALL = 0x01;  // compressed (half width) via GS !

    private int $columns = 32;

    private string $buf = '';

    /** Non-ASCII → ASCII terdekat agar aman di charset cp437 printer. */
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
    ];

    /**
     * Build full thermal receipt bytes.
     *
     * @param  array  $data  {
     *                       items: [{nama, qty, harga, subtotal, harga_retail?, satuan?, catatan?}],
     *                       total: float, bayar: float, kembali: float,
     *                       no_transaksi: string, metode_bayar: string,
     *                       tanggal: string, kasir: string, cabang: string,
     *                       pelanggan?: string, tier?: string, tipe_konsumen?: string,
     *                       saldo_poin?: int, poin_ditukar?: int,
     *                       diskon_tier?: float, diskon_partai?: float, diskon_poin?: float,
     *                       diskon?: float, diskon_persen?: float, diskon_nominal?: float,
     *                       pajak?: float, ppn_persen?: float, ongkir?: float,
     *                       subtotal?: float, subtotal_retail?: float,
     *                       hemat_total?: float, komisi_mitra_nominal?: float,
     *                       is_piutang?: bool, jatuh_tempo?: string, sisa_piutang?: float,
     *                       no_tiket?: string, jenis_hp?: string, kurir?: string,
     *                       is_pickup?: bool, resi?: string, sumber?: string,
     *                       garansi?: string, footer_text?: string, kebijakan_retur?: string,
     *                       catatan_kaki?: string, nama_toko?: string, slogan?: string,
     *                       alamat?: string, telepon?: string, website_medsos?: string,
     *                       show_cabang?: bool, show_kasir?: bool, show_pelanggan?: bool,
     *                       show_waktu?: bool, show_subtotal_breakdown?: bool,
     *                       show_qr_retur?: bool, qr_caption?: string,
     *                       paper_width?: int (58|80), font_size?: string ('normal'|'small'),
     *                       }
     */
    public function receipt(array $data): string
    {
        $paperWidth = $data['paper_width'] ?? self::WIDTH_58MM;
        $this->columns = self::COLUMNS_BY_WIDTH[$paperWidth] ?? 32;

        $fontSize = ($data['font_size'] ?? 'normal') === 'small' ? self::FONT_SMALL : self::FONT_NORMAL;
        $this->init($fontSize);

        // ========== HEADER ==========
        $namaToko = $data['nama_toko'] ?? 'BREMSPACE';
        $slogan = $data['slogan'] ?? 'Pusat Sparepart & Servis Gadget';

        $this->align(1)->bold(true)->size(1)->text($namaToko)->size(0);
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

        if (! empty($data['is_offline'])) {
            $this->bold(true)->text('*** STRUK OFFLINE (BUKTI SEMENTARA) ***')->bold(false);
        }

        if (($data['show_waktu'] ?? true) && ! empty($data['waktu'])) {
            $this->text($data['waktu']);
        }

        $this->rule();

        // ========== TRANSACTION INFO ==========
        $this->text($this->pad('No. TRX:', $data['no_transaksi'] ?? '-'));

        if (! empty($data['sumber']) && ! in_array(strtolower($data['sumber']), ['pos', 'offline'])) {
            $this->text($this->pad('Pesanan:', strtoupper($data['sumber'])));
        }

        if (! empty($data['kurir'])) {
            $kurirLbl = ! empty($data['is_pickup']) ? 'Metode Ambil:' : 'Kurir:';
            $resiTeks = (! empty($data['resi']) && $data['resi'] !== '-') ? ' ('.$data['resi'].')' : '';
            $this->text($this->pad($kurirLbl, $data['kurir'].$resiTeks));
        }

        if (! empty($data['no_tiket'])) {
            $this->bold(true)->text($this->pad('No. Tiket:', $data['no_tiket']))->bold(false);
        }

        if (! empty($data['jenis_hp'])) {
            $this->text($this->pad('Unit HP:', $data['jenis_hp']));
        }

        if (($data['show_kasir'] ?? true) && ! empty($data['kasir'])) {
            $this->text($this->pad('Kasir:', $data['kasir']));
        }

        if (($data['show_pelanggan'] ?? true)) {
            $pelanggan = $data['pelanggan'] ?? 'Umum';
            $this->text($this->pad('Pelanggan:', $pelanggan));

            if (! empty($data['tier']) && $data['tier'] !== 'Retail' && $data['tier'] !== 'Umum') {
                $lbl = in_array($data['tipe_konsumen'] ?? '', ['reseller', 'agen'], true) ? 'Tipe Mitra:' : 'Tier Member:';
                $this->bold(true)->text($this->pad($lbl, $data['tier']))->bold(false);
            }

            if (! empty($data['saldo_poin']) && (int) $data['saldo_poin'] > 0) {
                $this->text($this->pad('Saldo Poin:', number_format((int) $data['saldo_poin']).' Poin'));
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

            // Line: "  1x  Rp 50.000          Rp 50.000"
            $this->text($this->pad("  {$qtySatuan} x {$harga}", $subtotal));

            // Strikethrough retail price if discounted (paper-efficient: show on same line if space allows)
            $hargaRetail = (float) ($item['harga_retail'] ?? 0);
            $hargaJual = (float) ($item['harga'] ?? 0);
            if ($hargaRetail > $hargaJual) {
                // Format: "Normal: Rp 75.000 → Rp 50.000" (arrow for strikethrough effect)
                $normal = $this->money($hargaRetail);
                $this->text("   Normal: {$normal} → {$harga}");
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
            $this->text($this->pad('Subtotal Retail:', $this->money($subRetail)));

            $tierLbl = (! empty($data['tier']) && $data['tier'] !== 'Retail')
                ? "Potongan {$data['tier']}:"
                : (in_array($data['tipe_konsumen'] ?? '', ['reseller', 'agen'], true) ? 'Potongan Mitra:' : 'Potongan Tier:');
            $this->text($this->pad($tierLbl, '-'.$this->money($data['diskon_tier'])));
            $this->text($this->pad('Subtotal:', $this->money($data['subtotal'] ?? $data['total'])));
        } else {
            $this->text($this->pad('Subtotal:', $this->money($data['subtotal'] ?? $data['total'])));
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
                ? "Diskon Kasir ({$data['diskon_persen']}%):"
                : 'Diskon Kasir:';
            $this->text($this->pad($lbl, '-'.$this->money($data['diskon'])));
        }

        if (! empty($data['pajak']) && (float) $data['pajak'] > 0) {
            $ppnLbl = ! empty($data['ppn_persen']) ? "PPN ({$data['ppn_persen']}%):" : 'PPN:';
            $this->text($this->pad($ppnLbl, '+'.$this->money($data['pajak'])));
        }

        if (! empty($data['ongkir']) && (float) $data['ongkir'] > 0) {
            $this->text($this->pad('Ongkos Kirim:', '+'.$this->money($data['ongkir'])));
        }

        $this->rule();

        // ========== TOTAL & PAYMENT ==========
        $this->bold(true)->size(1)->text($this->pad('TOTAL', $this->money($data['total'])))->size(0)->bold(false);

        if (! empty($data['is_piutang'])) {
            $this->text('Status  : BELUM LUNAS (PIUTANG)');
            if (! empty($data['jatuh_tempo'])) {
                $this->text('Jatuh Tmp: '.$data['jatuh_tempo']);
            }
            $this->text('Sisa Tag: '.$this->money($data['sisa_piutang'] ?? $data['total']));
        } else {
            $this->text('Bayar   : '.$this->money($data['bayar']));
            $this->text('Kembali : '.$this->money($data['kembali'] ?? 0));
            $this->text('Metode  : '.$data['metode_bayar']);
        }

        if (! empty($data['hemat_total']) && (float) $data['hemat_total'] > 0) {
            $this->bold(true)->text($this->pad('TOTAL HEMAT:', $this->money($data['hemat_total'])))->bold(false);
        }

        if (! empty($data['komisi_mitra_nominal']) && (float) $data['komisi_mitra_nominal'] > 0) {
            $this->text('Komisi  : +'.$this->money($data['komisi_mitra_nominal']).' (Saldo)');
        }

        if (isset($data['saldo_poin'])) {
            $this->text('Sisa Poin: '.number_format((float) $data['saldo_poin']).' pts');
        }

        $this->rule()->feed(1);

        // ========== QR CODE RETUR & GARANSI ==========
        if (($data['show_qr_retur'] ?? true) && ! empty($data['no_transaksi'])) {
            $this->align(1);
            $this->qrcode($data['no_transaksi'], $data['qr_size'] ?? 4);
            $this->bold(true)->text($data['no_transaksi'])->bold(false);
            $this->text($data['qr_caption'] ?? 'Scan QR untuk Retur & Garansi');
            $this->feed(1);
        }

        // ========== FOOTER ==========
        $this->align(1);
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

        // Feed 1 baris minimal lalu cut (hemat kertas struk)
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

    private function init(int $fontSize = self::FONT_NORMAL): self
    {
        $this->buf = "\x1b\x40"; // init printer

        // Set font size (compressed for paper saving)
        if ($fontSize === self::FONT_SMALL) {
            $this->buf .= "\x1d\x21\x01"; // GS ! 1 = compressed (half width)
        }

        return $this;
    }

    /** Alignment: 0 kiri, 1 tengah, 2 kanan. */
    private function align(int $mode): self
    {
        $this->buf .= "\x1b\x61".chr($mode);

        return $this;
    }

    private function bold(bool $on): self
    {
        $this->buf .= "\x1b\x45".chr($on ? 1 : 0);

        return $this;
    }

    /** GS ! — 0 normal, 1 double height+width, 0x10 double height, 0x80 double width. */
    private function size(int $n): self
    {
        $this->buf .= "\x1d\x21".chr($n);

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
        $this->buf .= "\x1d\x56\x42\x00"; // Full cut

        return $this;
    }

    private function text(string $text): self
    {
        $this->buf .= $this->ascii($text)."\n";

        return $this;
    }

    /** Left + right pada satu baris selebar $this->columns. */
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
}
