<?php

namespace App\Modules\Marketplace\Imports;

use App\Modules\Wms\Models\Produk;
use App\Modules\Wms\Models\SkuVariant;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;

/**
 * Import produk dari Excel/CSV dengan mode preview (dry-run).
 * PRD §4.11: driver data import wajib tawarkan dry-run/preview sebelum commit.
 *
 * Template kolom: nama, kategori, brand_kompatibel, model_kompatibel,
 *                 kondisi, harga_beli, harga_jual, stok, sku (opsional)
 */
class ProdukImport implements ToModel, WithHeadingRow, WithValidation
{
    /** true = hanya kalkulasi tanpa insert (preview). */
    public bool $preview = false;

    public function __construct(bool $preview = false)
    {
        $this->preview = $preview;
    }

    public function rules(): array
    {
        return [
            'nama' => ['required', 'string'],
            'harga_jual' => ['required', 'numeric', 'min:0'],
        ];
    }

    public function model(array $row)
    {
        $nama = (string) $row['nama'];
        $slug = Str::slug($nama);
        $kondisi = in_array($row['kondisi'] ?? 'baru', ['baru', 'oem', 'compatible'], true)
            ? $row['kondisi']
            : 'baru';

        if ($this->preview) {
            // Preview: hasil kalkulasi tanpa insert
            return null;
        }

        $produk = Produk::firstOrCreate(
            ['slug' => $slug],
            [
                'nama' => $nama,
                'kategori' => $row['kategori'] ?? 'Umum',
                'brand_kompatibel' => $row['brand_kompatibel'] ?? null,
                'model_kompatibel' => $row['model_kompatibel'] ?? null,
                'kondisi' => $kondisi,
                'satuan' => 'pcs',
                'harga_beli' => (float) ($row['harga_beli'] ?? 0),
                'harga_jual_retail' => (float) $row['harga_jual'],
                'is_active' => true,
            ]
        );

        $sku = $row['sku'] ?? ('SKU-' . strtoupper(Str::random(8)));
        SkuVariant::firstOrCreate(
            ['sku' => $sku],
            [
                'produk_id' => $produk->id,
                'nama_varian' => 'Standar',
                'harga_beli' => (float) ($row['harga_beli'] ?? 0),
                'harga_jual_retail' => (float) $row['harga_jual'],
                'is_active' => true,
            ]
        );

        return $produk;
    }
}