<?php

namespace Database\Seeders;

use App\Modules\Rbac\Models\Cabang;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class CabangSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        Cabang::firstOrCreate(
            ['kode' => 'CBG-01'],
            ['nama' => 'Cabang Pusat', 'alamat' => 'Jl. Raya Utama No. 1', 'telepon' => '021-1234567', 'is_active' => true]
        );

        Cabang::firstOrCreate(
            ['kode' => 'CBG-02'],
            ['nama' => 'Cabang Selatan', 'alamat' => 'Jl. Selatan No. 10', 'telepon' => '021-7654321', 'is_active' => true]
        );
    }
}
