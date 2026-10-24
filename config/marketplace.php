<?php

return [
    /*
     * Durasi reservasi stok online (menit) saat order dibuat dengan status menunggu_pembayaran.
     * Default: 60 menit.
     */
    'reservation_minutes' => env('MARKETPLACE_RESERVATION_MINUTES', 60),
];
