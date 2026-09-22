<?php

namespace Database\Seeders;

use App\Modules\Reseller\Models\SkemaKomisi;
use Illuminate\Database\Seeder;

class KomisiSkemaSeeder extends Seeder
{
    public function run(): void
    {
        $skema = [
            ['nama' => 'Komisi Standar Semua Produk', 'kategori' => null,     'tipe' => 'persen',  'nilai' => 5],
            ['nama' => 'Komisi LCD / Layar',          'kategori' => 'LCD / Layar', 'tipe' => 'persen', 'nilai' => 7],
            ['nama' => 'Komisi Baterai',              'kategori' => 'Baterai', 'tipe' => 'nominal', 'nilai' => 10000],
            ['nama' => 'Komisi Jasa Servis',          'kategori' => 'Servis',  'tipe' => 'persen', 'nilai' => 10],
        ];

        foreach ($skema as $data) {
            SkemaKomisi::firstOrCreate(
                ['nama' => $data['nama']],
                $data
            );
        }
    }
}
