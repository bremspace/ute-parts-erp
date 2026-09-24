<?php

return [
    // [F3-8b] §4.2 alur 4 — potongan disiplin absen (opt-in per cabang; kosong = nonaktif semua)
    'potongan_absen' => [
        'threshold' => 3,            // hari absen tanpa izin yg ditoleransi
        'nominal_per_hari' => 25000, // potongan per hari di atas threshold
        'cabang_aktif' => [],        // daftar cabang_id yg mengaktifkan potongan ini
    ],
];
