<?php

namespace App\Support;

/**
 * Satu sumber kebenaran normalisasi nomor HP Indonesia.
 *
 * Semua input (08…, 8…, 62…, +62, spasi, tanda hubung, titik, kurung)
 * distandardisasi ke "62" diikuti angka, misalnya:
 *   0812-3456-7890 → 6281234567890
 *   81234567890    → 6281234567890
 *   +62 812 3456 7890 → 6281234567890
 */
class NomorTeleponIndonesia
{
    /** Standarisasi ke format 62xxxxxxxxx. Mengembalikan '' bila bukan nomor. */
    public static function standar(?string $nomor): string
    {
        $digits = preg_replace('/\D+/', '', (string) $nomor) ?? '';

        if ($digits === '') {
            return '';
        }

        if (str_starts_with($digits, '62')) {
            return $digits;
        }

        if (str_starts_with($digits, '0')) {
            return '62'.substr($digits, 1);
        }

        if (str_starts_with($digits, '8')) {
            return '62'.$digits;
        }

        // Angka asing / format tidak dikenal tetap dikembalikan agar tidak
        // menghilangkan data, tetapi tidak dianggap nomor Indonesia.
        return $digits;
    }

    /**
     * Semua varian yang dipakai sistem lama untuk pencocokan.
     * Dipakai login/auto-sync agar nomor tersimpan format lama masih ketemu.
     *
     * @return array<int, string>
     */
    public static function kandidat(?string $nomor): array
    {
        $standar = self::standar($nomor);

        if ($standar === '') {
            return [];
        }

        if (! str_starts_with($standar, '62')) {
            return [$standar];
        }

        $lokal = '0'.substr($standar, 2);
        $tanpaKode = substr($standar, 2);

        return array_values(array_unique([$standar, $lokal, $tanpaKode]));
    }

    public static function validIndonesia(?string $nomor): bool
    {
        $standar = self::standar($nomor);

        return str_starts_with($standar, '62') && strlen($standar) >= 10;
    }
}
