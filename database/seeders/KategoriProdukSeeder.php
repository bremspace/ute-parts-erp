<?php

namespace Database\Seeders;

use App\Modules\Wms\Models\KategoriProduk;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class KategoriProdukSeeder extends Seeder
{
    /**
     * Struktur pohon kategori produk bawaan sistem Ute Parts.
     * Idempotent: updateOrCreate berdasarkan slug, tidak menghapus kategori bawaan user.
     */
    public static function defaultTree(): array
    {
        return [
            [
                'nama' => 'Sparepart HP',
                'children' => [
                    'LCD & Touchscreen',
                    'Baterai',
                    'Fleksibel & Board',
                    'Kamera',
                    'IC & Chipset',
                    'Speaker & Buzzer',
                    'Housing & Backdoor',
                    'Konektor & Sim Tray',
                ],
            ],
            [
                'nama' => 'Tools & Servis',
                'children' => [
                    'Obeng & Pembuka',
                    'Solder & Blower',
                    'Lem & Perekat',
                    'Tester & Pengukur',
                ],
            ],
            [
                'nama' => 'Aksesoris HP',
                'children' => [
                    'Tempered Glass',
                    'Kabel Data & Charger',
                    'Casing & Pelindung',
                ],
            ],
        ];
    }

    public function run(): void
    {
        $urutanRoot = 1;

        foreach (self::defaultTree() as $rootData) {
            $root = KategoriProduk::updateOrCreate(
                ['slug' => Str::slug($rootData['nama'])],
                [
                    'parent_id' => null,
                    'nama' => $rootData['nama'],
                    'urutan' => $urutanRoot++,
                    'is_active' => true,
                ],
            );

            $urutanChild = 1;
            foreach ($rootData['children'] as $nama) {
                KategoriProduk::updateOrCreate(
                    ['slug' => Str::slug($nama)],
                    [
                        'parent_id' => $root->id,
                        'nama' => $nama,
                        'urutan' => $urutanChild++,
                        'is_active' => true,
                    ],
                );
            }
        }
    }
}
