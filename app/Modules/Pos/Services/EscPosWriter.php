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
     * @param array $data {
     *   headerLines: string[], items: [{nama, qty, harga, subtotal}],
     *   total: float, bayar: float, kembali: float,
     *   no_transaksi: string, metode_bayar: string, footerLines: string[],
     *   tanggal: string, kasir: string, cabang: string,
     * }
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
        $this->text('No    : ' . $data['no_transaksi']);
        $this->text('Tgl   : ' . $data['tanggal']);
        $this->text('Kasir : ' . $data['kasir']);
        $this->text('Cabang: ' . $data['cabang']);
        $this->rule();

        foreach ($data['items'] as $item) {
            foreach ($this->wrap($item['nama']) as $namaLine) {
                $this->text($namaLine);
            }
            $this->text($this->pad(
                "{$item['qty']} x " . $this->money($item['harga']),
                $this->money($item['subtotal'])
            ));
        }

        $this->rule();
        $this->bold(true)->text($this->pad('TOTAL', $this->money($data['total'])))->bold(false);
        $this->text('Bayar   : ' . $this->money($data['bayar']));
        $this->text('Kembali : ' . $this->money($data['kembali']));
        $this->text('Metode  : ' . $data['metode_bayar']);

        $this->rule()->feed(1);

        // Barcode Code128 (GS k m=73, panjang 2 byte little-endian) + HRI di bawah
        $no = $this->ascii($data['no_transaksi']);
        $this->align(1);
        $this->buf .= "\x1d\x48\x02"; // HRI below
        $this->buf .= "\x1d\x6b\x49" . chr(strlen($no) & 0xFF) . chr((strlen($no) >> 8) & 0xFF) . $no;
        $this->feed(1);

        // Footer
        foreach ($data['footerLines'] ?? [] as $line) {
            $this->text($line);
        }

        $this->feed(3)->cut();

        return $this->buf;
    }

    private function init(): self
    {
        $this->buf = "\x1b\x40";

        return $this;
    }

    /** Alignment: 0 kiri, 1 tengah, 2 kanan. */
    private function align(int $mode): self
    {
        $this->buf .= "\x1b\x61" . chr($mode);

        return $this;
    }

    private function bold(bool $on): self
    {
        $this->buf .= "\x1b\x45" . chr($on ? 1 : 0);

        return $this;
    }

    /** GS ! — 0 normal, 1 double height+width. */
    private function size(int $n): self
    {
        $this->buf .= "\x1d\x21" . chr($n);

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
        $this->buf .= $this->ascii($text) . "\n";

        return $this;
    }

    /** Left + right pada satu baris selebar COLUMNS. */
    private function pad(string $left, string $right): string
    {
        $left = $this->ascii($left);
        $right = $this->ascii($right);
        $pad = max(1, self::COLUMNS - strlen($left) - strlen($right));

        return $left . str_repeat(' ', $pad) . $right;
    }

    private function wrap(string $text): array
    {
        return explode("\n", wordwrap($this->ascii($text), self::COLUMNS));
    }

    private function money($v): string
    {
        return 'Rp' . number_format((float) $v, 0, ',', '.');
    }

    private function ascii(string $text): string
    {
        return preg_replace('/[^\x20-\x7E]/', '', strtr($text, self::TRANSLIT));
    }
}