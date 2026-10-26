<?php

namespace App\Modules\Pos\Services;

/**
 * Minimal ESC/POS byte builder for 58mm thermal receipts (ADR 0009 fallback path).
 * Pure PHP, no DB access, deterministic — output depends only on the input array.
 * Bytes are sent raw to the printer (CUPS `lp -o raw` or Web Bluetooth client).
 */
class EscPosWriter
{
    private const COLUMNS = 32; // 58mm @ default font

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
     * Build full 58mm receipt bytes.
     *
     * @param  array  $data  {
     *                       headerLines: string[], items: [{nama, qty, harga, subtotal}],
     *                       total: float, bayar: float, kembali: float,
     *                       no_transaksi: string, metode_bayar: string, footerLines: string[],
     *                       tanggal: string, kasir: string, cabang: string,
     *                       }
     */
    public function receipt(array $data): string
    {
        $this->buf = "\x1b\x40"; // init

        // Header — baris pertama double-size bold, sisanya bold
        if ($header = $data['headerLines'] ?? []) {
            $this->align(1)->bold(true)->size(1);
            $this->text($header[0]);
            $this->size(0);
            foreach (array_slice($header, 1) as $line) {
                $this->text($line);
            }
            $this->bold(false);
        }

        $this->align(0)->rule();
        $this->text('No    : '.$data['no_transaksi']);
        if (! empty($data['no_tiket'])) {
            $this->text('Tiket : '.$data['no_tiket']);
        }
        if (! empty($data['jenis_hp'])) {
            $this->text('Unit  : '.$data['jenis_hp']);
        }
        $this->text('Tgl   : '.$data['tanggal']);
        $this->text('Kasir : '.$data['kasir']);
        $this->text('Cabang: '.$data['cabang']);
        if (! empty($data['pelanggan']) && $data['pelanggan'] !== 'Umum') {
            $this->text('Plg   : '.$data['pelanggan']);
            if (! empty($data['tier']) && $data['tier'] !== 'Retail' && $data['tier'] !== 'Umum') {
                $lbl = in_array($data['tipe_konsumen'] ?? '', ['reseller', 'agen'], true) ? 'Mitra : ' : 'Tier  : ';
                $this->text($lbl.$data['tier']);
            }
        }
        $this->rule();

        foreach ($data['items'] as $item) {
            foreach ($this->wrap($item['nama']) as $namaLine) {
                $this->text($namaLine);
            }
            if (! empty($item['catatan'])) {
                foreach ($this->wrap('  * '.$item['catatan']) as $catatanLine) {
                    $this->text($catatanLine);
                }
            }
            $this->text($this->pad(
                "{$item['qty']} x ".$this->money($item['harga']),
                $this->money($item['subtotal'])
            ));
            if (! empty($item['harga_retail']) && (float) $item['harga_retail'] > (float) $item['harga']) {
                $this->text('  (Normal: '.$this->money($item['harga_retail']).')');
            }
        }

        $this->rule();
        if (! empty($data['subtotal_retail']) && ! empty($data['diskon_tier']) && (float) $data['diskon_tier'] > 0) {
            $this->text($this->pad('Subtotal Retail', $this->money($data['subtotal_retail'])));
            $tierLabel = ! empty($data['tier']) && $data['tier'] !== 'Retail'
                ? "Potongan {$data['tier']}"
                : (in_array($data['tipe_konsumen'] ?? '', ['reseller', 'agen'], true) ? 'Potongan Mitra' : 'Potongan Tier');
            $this->text($this->pad($tierLabel, '-'.$this->money($data['diskon_tier'])));
            $this->text($this->pad('Subtotal', $this->money($data['subtotal'] ?? $data['total'])));
        } else {
            $this->text($this->pad('Subtotal', $this->money($data['subtotal'] ?? $data['total'])));
        }

        if (! empty($data['diskon_partai']) && (float) $data['diskon_partai'] > 0) {
            $this->text($this->pad('Diskon Partai', '-'.$this->money($data['diskon_partai'])));
        }

        if (! empty($data['diskon_poin']) && (float) $data['diskon_poin'] > 0) {
            $pts = $data['poin_ditukar'] ?? 0;
            $this->text($this->pad("Tukar Poin ({$pts}p)", '-'.$this->money($data['diskon_poin'])));
        }

        if (! empty($data['diskon']) && (float) $data['diskon'] > 0) {
            $lbl = ! empty($data['diskon_persen']) && (float) $data['diskon_persen'] > 0 ? "Diskon ({$data['diskon_persen']}%)" : 'Diskon Kasir';
            $this->text($this->pad($lbl, '-'.$this->money($data['diskon'])));
        }

        if (! empty($data['pajak']) && (float) $data['pajak'] > 0) {
            $ppnLabel = ! empty($data['ppn_persen']) ? "PPN ({$data['ppn_persen']}%)" : 'PPN';
            $this->text($this->pad($ppnLabel, '+'.$this->money($data['pajak'])));
        }

        $this->bold(true)->text($this->pad('TOTAL', $this->money($data['total'])))->bold(false);
        $this->text('Bayar   : '.$this->money($data['bayar']));
        $this->text('Kembali : '.$this->money($data['kembali']));
        $this->text('Metode  : '.$data['metode_bayar']);

        if (! empty($data['hemat_total']) && (float) $data['hemat_total'] > 0) {
            $this->bold(true)->text($this->pad('TOTAL HEMAT', $this->money($data['hemat_total'])))->bold(false);
        }

        if (! empty($data['komisi_mitra_nominal']) && (float) $data['komisi_mitra_nominal'] > 0) {
            $this->text('Komisi  : +'.$this->money($data['komisi_mitra_nominal']).' (Saldo)');
        }

        if (isset($data['saldo_poin'])) {
            $this->text('Sisa Poin: '.number_format((float) $data['saldo_poin']).' pts');
        }

        $this->rule()->feed(1);

        // QR Code Faktur Retur & Garansi ESC/POS native (Model 2)
        $this->align(1);
        $this->qrcode($data['no_transaksi']);
        $this->text($data['no_transaksi']);
        $this->text('Scan QR utk Retur/Garansi');
        $this->feed(1);

        // Footer
        foreach ($data['footerLines'] ?? [] as $line) {
            $this->text($line);
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

    private function init(): self
    {
        $this->buf = "\x1b\x40";

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

    /** GS ! — 0 normal, 1 double height+width. */
    private function size(int $n): self
    {
        $this->buf .= "\x1d\x21".chr($n);

        return $this;
    }

    private function rule(): self
    {
        return $this->text(str_repeat('-', self::COLUMNS));
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
    }

    private function text(string $text): self
    {
        $this->buf .= $this->ascii($text)."\n";

        return $this;
    }

    /** Left + right pada satu baris selebar COLUMNS. */
    private function pad(string $left, string $right): string
    {
        $left = $this->ascii($left);
        $right = $this->ascii($right);
        $pad = max(1, self::COLUMNS - strlen($left) - strlen($right));

        return $left.str_repeat(' ', $pad).$right;
    }

    private function wrap(string $text): array
    {
        return explode("\n", wordwrap($this->ascii($text), self::COLUMNS));
    }

    private function money($v): string
    {
        return 'Rp'.number_format((float) $v, 0, ',', '.');
    }

    private function ascii(string $text): string
    {
        return preg_replace('/[^\x20-\x7E]/', '', strtr($text, self::TRANSLIT));
    }
}
