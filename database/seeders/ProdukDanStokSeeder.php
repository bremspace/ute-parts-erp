<?php

namespace Database\Seeders;

use App\Modules\Crm\Models\Pelanggan;
use App\Modules\Crm\Models\TierMembership;
use App\Modules\Pos\Models\HargaTier;
use App\Modules\Rbac\Models\Cabang;
use App\Modules\Wms\Models\Gudang;
use App\Modules\Wms\Models\Produk;
use App\Modules\Wms\Models\SkuVariant;
use App\Modules\Wms\Models\StokItem;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ProdukDanStokSeeder extends Seeder
{
    public function run(): void
    {
        $cabang1 = Cabang::where('kode', 'CBG-01')->first() ?? Cabang::first();
        $cabang2 = Cabang::where('kode', 'CBG-02')->first();

        // 1. Gudang
        $gudangUtama = Gudang::firstOrCreate(
            ['kode' => 'GDG-01'],
            ['cabang_id' => $cabang1?->id ?? 1, 'nama' => 'Gudang Utama Pusat', 'is_active' => true]
        );

        $gudangToko = Gudang::firstOrCreate(
            ['kode' => 'GDG-02'],
            ['cabang_id' => $cabang1?->id ?? 1, 'nama' => 'Rak Toko Depan', 'is_active' => true]
        );

        if ($cabang2) {
            Gudang::firstOrCreate(
                ['kode' => 'GDG-03'],
                ['cabang_id' => $cabang2->id, 'nama' => 'Gudang Cabang Selatan', 'is_active' => true]
            );
        }

        // 2. Customers
        $tierGold = TierMembership::where('kode', 'gold')->first();
        $tierSilver = TierMembership::where('kode', 'silver')->first();

        $custReseller = Pelanggan::firstOrCreate(
            ['telepon' => '08119876543'],
            [
                'nama' => 'Budi Reseller Cell',
                'email' => 'budi@resellercell.com',
                'alamat' => 'ITC Roxy Mas Lt. 2 No. 15',
                'is_reseller' => true,
                'total_belanja_12bulan' => 12500000,
                'poin_loyalty' => 1500,
            ]
        );

        $custGold = Pelanggan::firstOrCreate(
            ['telepon' => '08123344556'],
            [
                'nama' => 'Denny Service Tech',
                'email' => 'denny@service.com',
                'alamat' => 'Mall Ambasador Lt. 3',
                'tier_membership_id' => $tierGold?->id,
                'is_reseller' => false,
                'total_belanja_12bulan' => 3500000,
                'poin_loyalty' => 450,
            ]
        );

        // 3. Products
        $productsData = [
            [
                'nama' => 'LCD Touchscreen iPhone 13 Original Equipment (OEM)',
                'kategori' => 'LCD / Layar',
                'brand_kompatibel' => 'Apple',
                'model_kompatibel' => 'iPhone 13',
                'kondisi' => 'oem',
                'harga_beli' => 750000,
                'harga_jual_retail' => 1100000,
                'reseller_price' => 880000,
                'stok' => 18,
            ],
            [
                'nama' => 'Baterai Double Power Samsung Galaxy A52 (4500mAh)',
                'kategori' => 'Baterai',
                'brand_kompatibel' => 'Samsung',
                'model_kompatibel' => 'Galaxy A52',
                'kondisi' => 'baru',
                'harga_beli' => 85000,
                'harga_jual_retail' => 165000,
                'reseller_price' => 115000,
                'stok' => 42,
            ],
            [
                'nama' => 'Flexible Board Konektor Charger Xiaomi Redmi Note 10 Pro',
                'kategori' => 'Flexible & Board',
                'brand_kompatibel' => 'Xiaomi',
                'model_kompatibel' => 'Redmi Note 10 Pro',
                'kondisi' => 'baru',
                'harga_beli' => 32000,
                'harga_jual_retail' => 75000,
                'reseller_price' => 48000,
                'stok' => 30,
            ],
            [
                'nama' => 'IC Power PM6150 Original IC Chip',
                'kategori' => 'IC & Chipset',
                'brand_kompatibel' => 'Universal',
                'model_kompatibel' => 'Multi-Brand Qualcomm',
                'kondisi' => 'baru',
                'harga_beli' => 45000,
                'harga_jual_retail' => 95000,
                'reseller_price' => 60000,
                'stok' => 12,
            ],
            [
                'nama' => 'Kamera Belakang Utama iPhone 11 (Main Back Camera)',
                'kategori' => 'Kamera',
                'brand_kompatibel' => 'Apple',
                'model_kompatibel' => 'iPhone 11',
                'kondisi' => 'compatible',
                'harga_beli' => 320000,
                'harga_jual_retail' => 520000,
                'reseller_price' => 410000,
                'stok' => 8,
            ],
            [
                'nama' => 'Lem LCD Touchscreen T-7000 Hitam 50ml',
                'kategori' => 'Tools & Cairan',
                'brand_kompatibel' => 'Universal',
                'model_kompatibel' => 'Semua Tipe HP',
                'kondisi' => 'baru',
                'harga_beli' => 14000,
                'harga_jual_retail' => 30000,
                'reseller_price' => 19000,
                'stok' => 75,
            ],
        ];

        foreach ($productsData as $data) {
            $slug = Str::slug($data['nama']);
            $produk = Produk::firstOrCreate(
                ['slug' => $slug],
                [
                    'nama' => $data['nama'],
                    'deskripsi' => "Sparepart {$data['nama']} kualitas terjamin untuk teknisi dan toko servis.",
                    'kategori' => $data['kategori'],
                    'brand_kompatibel' => $data['brand_kompatibel'],
                    'model_kompatibel' => $data['model_kompatibel'],
                    'kondisi' => $data['kondisi'],
                    'satuan' => 'pcs',
                    'harga_beli' => $data['harga_beli'],
                    'harga_jual_retail' => $data['harga_jual_retail'],
                    'is_active' => true,
                ]
            );

            // Sku Variant Default
            // [B-02/P2-1] Idempoten: key = produk_id + nama_varian, SKU deterministik
            // (SKU-{produk_id}-{slug nama}) — seeder bisa dijalankan berulang tanpa
            // menggandakan varian (pola lama firstOrCreate(['sku' => 'SKU-'.random()])
            // selalu membuat baris baru → 36× duplikat).
            $sku = SkuVariant::firstOrCreate(
                ['produk_id' => $produk->id, 'nama_varian' => 'Standar'],
                [
                    'sku' => 'SKU-'.$produk->id.'-'.Str::upper(Str::limit(Str::slug($data['nama']), 40, '')),
                    'harga_beli' => $data['harga_beli'],
                    'harga_jual_retail' => $data['harga_jual_retail'],
                    'is_active' => true,
                ]
            );

            // Stock in Gudang Utama
            StokItem::firstOrCreate(
                [
                    'produk_id' => $produk->id,
                    'sku_variant_id' => $sku->id,
                    'gudang_id' => $gudangUtama->id,
                ],
                [
                    'jumlah' => $data['stok'],
                    'jumlah_minimum' => 5,
                ]
            );

            // Harga Reseller in HargaTier
            HargaTier::firstOrCreate(
                [
                    'produk_id' => $produk->id,
                    'is_reseller' => true,
                ],
                [
                    'harga' => $data['reseller_price'],
                ]
            );
        }
    }
}
