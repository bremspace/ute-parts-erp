<?php

declare(strict_types=1);

return [
    'exclude' => [
        'tests/',
        'resources/views/',
    ],

    // Tidak ada metric tambahan/kustom.
    'add' => [],
    'remove' => [],

    'config' => [
        // Laravel preset terdeteksi otomatis; thread dikontrol via CLI flag --threads.
    ],

    'requirements' => [
        // Skor minimum — DIBIARKAN kosong: laporan dulu, jangan gagalkan CI
        // sebelum baseline bersih. Aktifkan bertahap ('min-quality' => 80, dst).
    ],
];
