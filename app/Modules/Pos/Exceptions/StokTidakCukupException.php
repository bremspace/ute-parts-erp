<?php

namespace App\Modules\Pos\Exceptions;

use Exception;

/**
 * [B-02 revisi] Stok tidak mencukupi saat transaksi POS.
 *
 * Exception domain terpisah (bukan \Exception generik) supaya controller bisa
 * mengembalikan 422 + pesan Indonesia tanpa menutupi error lain jadi 422 —
 * bug tak terduga tetap naik sebagai 500.
 */
class StokTidakCukupException extends Exception
{
    public function __construct(
        string $message,
        public readonly ?int $produkId = null,
        public readonly int $tersedia = 0,
        public readonly int $diminta = 0,
    ) {
        parent::__construct($message);
    }
}
