<?php

namespace Database\Seeders;

use App\Modules\Wms\Models\Brand;
use App\Modules\Wms\Models\KualitasProduk;
use App\Modules\Wms\Models\SatuanUnit;
use App\Modules\Wms\Models\TipeHp;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class MasterDataSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        // 1. Satuan Unit
        $satuan = ['pcs', 'box', 'unit', 'set', 'pasang', 'botol', 'roll', 'meter', 'lembar', 'liter'];
        foreach ($satuan as $s) {
            SatuanUnit::firstOrCreate(
                ['kode' => $s],
                ['nama' => ucfirst($s), 'is_active' => true]
            );
        }

        // 2. Brands
        $brands = ['Apple', 'Samsung', 'Xiaomi', 'Oppo', 'Vivo', 'Realme', 'Infinix', 'Tecno', 'Google', 'Huawei', 'Universal', 'OEM', 'Umum'];
        foreach ($brands as $b) {
            Brand::firstOrCreate(
                ['nama' => $b],
                ['is_active' => true]
            );
        }

        // 3. Kualitas Produk
        $kualitas = ['Original', 'Grade A', 'Grade B', 'Refurbished', 'Compatible'];
        foreach ($kualitas as $k) {
            KualitasProduk::firstOrCreate(
                ['nama' => $k],
                ['is_active' => true]
            );
        }

        // 4. Tipe HP
        $tipeHp = [
            ['Apple', 'iPhone 15'], ['Apple', 'iPhone 14'], ['Apple', 'iPhone 13'], ['Apple', 'iPhone 12'], ['Apple', 'iPhone 11'],
            ['Samsung', 'Galaxy A54'], ['Samsung', 'Galaxy S23'], ['Samsung', 'Galaxy A13'], ['Samsung', 'Galaxy S24'],
            ['Xiaomi', 'Redmi Note 12'], ['Xiaomi', 'Redmi 13C'], ['Xiaomi', 'Redmi Note 13'], ['Xiaomi', 'Poco X6'],
            ['Oppo', 'Reno 11'], ['Oppo', 'A78'], ['Oppo', 'A18'],
            ['Vivo', 'Y17s'], ['Vivo', 'V27'], ['Vivo', 'Y36'],
            ['Realme', 'C67'], ['Realme', '11 Pro'], ['Realme', 'Note 50'],
            ['Infinix', 'Hot 40i'], ['Infinix', 'Note 30'], ['Infinix', 'Hot 40'],
            ['Tecno', 'Spark 20'], ['Tecno', 'Camon 30'],
            ['Google', 'Pixel 7'], ['Google', 'Pixel 8'],
            ['Huawei', 'Nova 11i'],
            ['Universal', 'Semua Tipe HP'],
        ];
        foreach ($tipeHp as [$merk, $model]) {
            TipeHp::firstOrCreate(
                ['merk' => $merk, 'model' => $model],
                ['nama' => "{$merk} {$model}", 'is_active' => true]
            );
        }
    }
}
