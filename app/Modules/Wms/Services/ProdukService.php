<?php

namespace App\Modules\Wms\Services;

use App\Modules\Akunting\Services\JurnalService;
use App\Modules\Wms\Models\Gudang;
use App\Modules\Wms\Models\Produk;
use App\Modules\Wms\Models\SkuVariant;
use App\Modules\Wms\Models\StockMutationLog;
use App\Modules\Wms\Models\StokItem;
use App\Modules\Wms\Models\StokLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * WMS ProdukService — master produk + penerimaan stok.
 * Setiap penambahan stok = pembelian ke supplier → jurnal otomatis:
 *   Debit Persediaan (130-01) = harga_beli × qty
 *   Kredit Utang Usaha (210-01) = nominal
 * Tersinkron penuh ke Akunting (PRD §4.6) & StokLog (audit).
 */
class ProdukService
{
    public function __construct(
        protected JurnalService $jurnalService
    ) {}

    /**
     * Buat produk baru + varian default + stok awal (opsional).
     * Bila stok_awal > 0 → jurnal pembelian dibuat.
     */
    public function buatProduk(
        string $nama,
        string $kategori,
        ?string $brand,
        ?string $model,
        string $kondisi,
        float $hargaBeli,
        float $hargaJual,
        ?string $sku = null,
        ?int $gudangId = null,
        int $stokAwal = 0,
        int $stokMinimum = 0,
        ?int $userId = null
    ): Produk {
        return DB::transaction(function () use ($nama, $kategori, $brand, $model, $kondisi, $hargaBeli, $hargaJual, $sku, $gudangId, $stokAwal, $stokMinimum, $userId) {
            $produk = Produk::create([
                'nama' => $nama,
                'slug' => Str::slug($nama).'-'.Str::lower(Str::random(4)),
                'deskripsi' => null,
                'kategori' => $kategori ?: 'Umum',
                'brand_kompatibel' => $brand,
                'model_kompatibel' => $model,
                'kondisi' => in_array($kondisi, ['baru', 'oem', 'compatible'], true) ? $kondisi : 'baru',
                'satuan' => 'pcs',
                'harga_beli' => $hargaBeli,
                'harga_jual_retail' => $hargaJual,
                'is_active' => true,
            ]);

            $variant = SkuVariant::create([
                'produk_id' => $produk->id,
                'sku' => $sku ?: 'SKU-'.strtoupper(Str::random(6)),
                'nama_varian' => 'Standar',
                'harga_beli' => $hargaBeli,
                'harga_jual_retail' => $hargaJual,
                'is_active' => true,
            ]);

            if ($gudangId && $stokAwal > 0) {
                $this->tambahStokPembelian(
                    $produk->id,
                    $variant->id,
                    $gudangId,
                    $stokAwal,
                    $hargaBeli,
                    "Stok awal produk baru {$nama}",
                    $userId
                );
            } elseif ($gudangId) {
                StokItem::firstOrCreate(
                    ['produk_id' => $produk->id, 'sku_variant_id' => $variant->id, 'gudang_id' => $gudangId],
                    ['jumlah' => 0, 'jumlah_minimum' => $stokMinimum]
                );
            }

            return $produk;
        });
    }

    /**
     * Tambah stok (pembelian ke supplier) → stok + StokLog + jurnal akunting.
     */
    public function tambahStokPembelian(
        int $produkId,
        ?int $variantId,
        int $gudangId,
        int $qty,
        float $hargaBeli,
        string $keterangan,
        ?int $userId = null,
        ?int $rakId = null,
        bool $postJurnal = true
    ): StokItem {
        if ($qty <= 0) {
            throw new \Exception('Kuantitas harus > 0');
        }

        return DB::transaction(function () use ($produkId, $variantId, $gudangId, $qty, $hargaBeli, $keterangan, $userId, $rakId, $postJurnal) {
            $stok = StokItem::firstOrCreate(
                ['produk_id' => $produkId, 'sku_variant_id' => $variantId, 'gudang_id' => $gudangId],
                ['jumlah' => 0, 'jumlah_minimum' => 0, 'rak_id' => $rakId]
            );

            $sebelum = $stok->jumlah;
            $setelah = $sebelum + $qty;
            $update = ['jumlah' => $setelah];
            if ($rakId) {
                $update['rak_id'] = $rakId; // [T-12] stok masuk ke rak terpilih
            }
            $stok->update($update);

            StokLog::create([
                'gudang_id' => $gudangId,
                'produk_id' => $produkId,
                'sku_variant_id' => $variantId,
                'user_id' => $userId,
                'jenis' => 'pembelian',
                'referensi_tipe' => Produk::class,
                'referensi_id' => $produkId,
                'jumlah_sebelum' => $sebelum,
                'perubahan' => $qty,
                'jumlah_setelah' => $setelah,
                'catatan' => $keterangan,
            ]);

            // [T-26] SOT mutation log
            StockMutationLog::create([
                'produk_id' => $produkId,
                'sku_variant_id' => $variantId,
                'gudang_id' => $gudangId,
                'delta' => $qty,
                'sumber' => 'po',
                'referensi_tipe' => Produk::class,
                'referensi_id' => $produkId,
                'terjadi_at' => now(),
            ]);

            // Jurnal pembelian (PRD §4.6): Persediaan debit / Utang Usaha kredit.
            // Saat dipanggil dari terimaBarang PO: jurnal agregat dibuat di PurchaseOrderService
            // (postJurnal=false) agar tidak dobel-posting per item.
            $total = round($hargaBeli * $qty, 2);
            if ($postJurnal && $total > 0) {
                $cabangId = Gudang::find($gudangId)?->cabang_id;
                $this->jurnalService->post(
                    $this->jurnalService->generateNoJurnal('beli', $cabangId),
                    now(),
                    'pembelian',
                    [
                        ['akun_kode' => '130-01', 'debit' => $total, 'kredit' => 0],   // Persediaan bertambah
                        ['akun_kode' => '210-01', 'debit' => 0, 'kredit' => $total],  // Utang Usaha bertambah
                    ],
                    $keterangan,
                    $cabangId,
                    $userId
                );
            }

            return $stok;
        });
    }
}
