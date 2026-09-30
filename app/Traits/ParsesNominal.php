<?php

namespace App\Traits;

trait ParsesNominal
{
    /**
     * Normalisasi input nominal gaya Indonesia (pemisah ribuan titik, desimal koma).
     * Menerima angka integer/float, string polos ("50000"), string ribuan ("50.000", "1.000.000"),
     * atau dengan desimal ("1.000.000,50").
     */
    protected function parseNominal(mixed $val): float
    {
        if (is_int($val) || is_float($val)) {
            return (float) $val;
        }

        $s = str_replace(['Rp', ' '], '', trim((string) ($val ?? '')));
        if ($s === '') {
            return 0.0;
        }

        if (str_contains($s, ',')) {
            $s = str_replace('.', '', $s);
            $s = str_replace(',', '.', $s);
        } else {
            $dotCount = substr_count($s, '.');
            if ($dotCount > 1) {
                $s = str_replace('.', '', $s);
            } elseif ($dotCount === 1) {
                $parts = explode('.', $s);
                $dec = $parts[1] ?? '';
                if (strlen($dec) >= 3) {
                    $s = str_replace('.', '', $s);
                }
            }
        }

        $cleaned = preg_replace('/[^0-9.]/', '', $s);

        return is_numeric($cleaned) ? (float) $cleaned : 0.0;
    }
}
