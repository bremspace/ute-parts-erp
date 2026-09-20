<?php

namespace Database\Seeders;

use App\Modules\Servis\Models\JenisServis;
use Illuminate\Database\Seeder;

class JenisServisSeeder extends Seeder
{
    public function run(): void
    {
        $jenis = [
            ['nama' => 'Servis Umum (Software & Sistem)', 'kode' => 'SVC-UMUM', 'durasi_garansi_hari' => 30, 'is_part_original' => false],
            ['nama' => 'Ganti LCD / Layar',             'kode' => 'SVC-LCD',  'durasi_garansi_hari' => 30, 'is_part_original' => false],
            ['nama' => 'Ganti Baterai',                 'kode' => 'SVC-BAT',  'durasi_garansi_hari' => 30, 'is_part_original' => false],
            ['nama' => 'Servis Part Original',          'kode' => 'SVC-ORIG', 'durasi_garansi_hari' => 90, 'is_part_original' => true],
            ['nama' => 'Servis Water Damage / IC',      'kode' => 'SVC-IC',   'durasi_garansi_hari' => 14, 'is_part_original' => false],
        ];

        foreach ($jenis as $data) {
            JenisServis::firstOrCreate(
                ['kode' => $data['kode']],
                $data
            );
        }
    }
}