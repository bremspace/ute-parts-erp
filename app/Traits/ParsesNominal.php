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

        $hasDot = str_contains($s, '.');
        $hasComma = str_contains($s, ',');

        if ($hasDot && $hasComma) {
            $lastDot = strrpos($s, '.');
            $lastComma = strrpos($s, ',');
            if ($lastComma > $lastDot) {
                // "120.000,50" -> titik ribuan, koma desimal
                $s = str_replace('.', '', $s);
                $s = str_replace(',', '.', $s);
            } else {
                // "120,000.50" -> koma ribuan, titik desimal
                $s = str_replace(',', '', $s);
            }
        } elseif ($hasComma) {
            $commaCount = substr_count($s, ',');
            if ($commaCount > 1) {
                // "1,000,000"
                $s = str_replace(',', '', $s);
            } else {
                $parts = explode(',', $s);
                $dec = $parts[1] ?? '';
                // 3 digit setelah koma tanpa titik = pemisah ribuan ("120,000")
                if (strlen($dec) === 3 && ! str_contains($parts[0], '.')) {
                    $s = str_replace(',', '', $s);
                } else {
                    // Desimal ("120,50" / "120,5")
                    $s = str_replace(',', '.', $s);
                }
            }
        } elseif ($hasDot) {
            $dotCount = substr_count($s, '.');
            if ($dotCount > 1) {
                // "1.000.000"
                $s = str_replace('.', '', $s);
            } else {
                $parts = explode('.', $s);
                $dec = $parts[1] ?? '';
                // 3 digit atau lebih setelah titik = ribuan ("120.000")
                if (strlen($dec) >= 3) {
                    $s = str_replace('.', '', $s);
                }
            }
        }

        $cleaned = preg_replace('/[^0-9.]/', '', $s);

        return is_numeric($cleaned) ? (float) $cleaned : 0.0;
    }
}
