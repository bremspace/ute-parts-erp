<?php

namespace App\Modules\Wms\Services;

use App\Modules\Akunting\Models\AkunCOA;
use App\Modules\Akunting\Models\JurnalAkuntansi;
use App\Modules\Akunting\Services\JurnalService;
use App\Modules\Pos\Models\HargaTier;
use App\Modules\Wms\Models\Gudang;
use App\Modules\Wms\Models\KategoriProduk;
use App\Modules\Wms\Models\NomorSeri;
use App\Modules\Wms\Models\Produk;
use App\Modules\Wms\Models\ProdukBiayaCabang;
use App\Modules\Wms\Models\Rak;
use App\Modules\Wms\Models\SatuanUnit;
use App\Modules\Wms\Models\SkuVariant;
use App\Modules\Wms\Models\StockMutationLog;
use App\Modules\Wms\Models\StokItem;
use App\Modules\Wms\Models\StokLog;
use App\Modules\Wms\Models\TipeHp;
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
        protected JurnalService $jurnalService,
        protected NomorSeriService $nomorSeriService // [F2-3]
    ) {}

    /**
     * Buat produk baru + varian default + stok awal (opsional).
     * Bila stok_awal > 0 → jurnal pembelian dibuat.
     *
     * [T-44] Parameter tambahan: brandId, kualitasId, satuanKode, tipeHpIds,
     * hargaTier (per tipe_konsumen: nominal_tetap / persen_diskon / tier_membership_id opsional).
     * [HARGA FLEKSIBEL] Parameter hargaFleksibel untuk produk jasa/harga manual.
     * [F2-3] Parameter sn: flag serial number per produk (default false).
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
        ?int $userId = null,
        ?int $brandId = null,
        ?int $kualitasId = null,
        ?string $satuanKode = null,
        array $tipeHpIds = [],
        array $hargaTier = [],
        bool $hargaFleksibel = false,
        bool $sn = false, // [F2-3]
        ?int $kategoriId = null,
        array $foto = [],
        ?string $deskripsi = null,
        array $produkKompatibelIds = [],
        string $abcClass = 'B',
        ?int $reorderPoint = null,
        ?int $minStock = null,
        ?int $maxStock = null,
        bool $isOndemand = false,
        ?string $barcode = null,
        string $metodeHargaBeli = 'average',
        ?float $marginPersen = null,
        ?int $rakId = null,
        array $multiSatuan = [],
        ?int $supplierId = null,
        array $barcodeAlt = [],
        string $opsiSku = 'otomatis'
    ): Produk {
        return DB::transaction(function () use ($nama, $kategori, $brand, $model, $kondisi, $hargaBeli, $hargaJual, $sku, $gudangId, $stokAwal, $stokMinimum, $userId, $brandId, $kualitasId, $satuanKode, $tipeHpIds, $hargaTier, $hargaFleksibel, $sn, $kategoriId, $foto, $deskripsi, $produkKompatibelIds, $abcClass, $reorderPoint, $minStock, $maxStock, $isOndemand, $barcode, $metodeHargaBeli, $marginPersen, $rakId, $multiSatuan, $supplierId, $barcodeAlt, $opsiSku) {
            $satuan = $satuanKode ?: 'pcs';
            $satuanRef = $satuanKode ? SatuanUnit::where('kode', $satuanKode)->first() : null;
            if ($satuanKode && ! $satuanRef) {
                throw new \Exception("Satuan '{$satuanKode}' tidak terdaftar di satuan_unit");
            }

            // Sinkronisasi kategori_id dan kategori string
            $finalKategoriId = $kategoriId;
            $finalKategoriStr = $kategori ?: 'Umum';
            if ($finalKategoriId) {
                $katRow = KategoriProduk::find($finalKategoriId);
                if ($katRow) {
                    $finalKategoriStr = $katRow->nama;
                }
            } elseif (! empty($kategori) && $kategori !== 'Umum') {
                $katRow = KategoriProduk::where('nama', $kategori)->orWhere('slug', Str::slug($kategori))->first();
                if (! $katRow) {
                    // Buat baru (slug di-generate otomatis oleh hook creating di model)
                    $katRow = KategoriProduk::create(['nama' => $kategori, 'is_active' => true]);
                }
                $finalKategoriId = $katRow->id;
                $finalKategoriStr = $katRow->nama;
            }

            // Normalisasi foto utama ke kolom gambar
            $fotoUtamaUrl = null;
            if (! empty($foto)) {
                foreach ($foto as $f) {
                    if (is_array($f) && ! empty($f['is_primary']) && ! empty($f['url'])) {
                        $fotoUtamaUrl = $f['url'];
                        break;
                    }
                }
                if (! $fotoUtamaUrl && isset($foto[0])) {
                    $fotoUtamaUrl = is_array($foto[0]) ? ($foto[0]['url'] ?? null) : $foto[0];
                }
            }

            // Sinkronisasi dua arah: brand_kompatibel, model_kompatibel, dan tipe_hp
            $brand = $brand ? trim($brand) : null;
            $model = $model ? trim($model) : null;
            $finalTipeHpIds = array_values(array_filter($tipeHpIds));

            if (! empty($brand) && ! empty($model) && ! str_contains($brand, ',') && ! str_contains($model, ',')) {
                $matchedTipeHp = TipeHp::firstOrCreate(
                    ['merk' => $brand, 'model' => $model],
                    ['nama' => "{$brand} {$model}", 'is_active' => true]
                );
                if (! in_array($matchedTipeHp->id, $finalTipeHpIds, true)) {
                    $finalTipeHpIds[] = $matchedTipeHp->id;
                }
            }

            $kompatibilitasHp = [];
            if (! empty($finalTipeHpIds)) {
                $tipeModels = TipeHp::whereIn('id', $finalTipeHpIds)->get();
                if ($tipeModels->isNotEmpty()) {
                    $brand = $tipeModels->pluck('merk')->filter()->unique()->implode(', ');
                    $model = $tipeModels->pluck('model')->filter()->unique()->implode(', ');
                    $kompatibilitasHp = $tipeModels->map(fn ($t) => ['merk' => $t->merk, 'model' => $t->model])->toArray();
                }
            }

            $cleanBarcode = $barcode ? trim($barcode) : null;
            $cleanBarcodeAlt = [];
            foreach ($barcodeAlt as $bAlt) {
                $bTrim = trim((string) $bAlt);
                if ($bTrim !== '' && $bTrim !== $cleanBarcode && ! in_array($bTrim, $cleanBarcodeAlt, true)) {
                    $cleanBarcodeAlt[] = $bTrim;
                }
            }

            $produk = Produk::create([
                'nama' => $nama,
                'slug' => (Str::limit(Str::slug($nama), 180, '') ?: 'produk').'-'.Str::lower(Str::random(4)),
                'barcode' => $cleanBarcode,
                'barcode_alt' => ! empty($cleanBarcodeAlt) ? $cleanBarcodeAlt : null,
                'deskripsi' => $deskripsi,
                'kategori' => $finalKategoriStr,
                'kategori_id' => $finalKategoriId,
                'brand_id' => $brandId,
                'kualitas_id' => $kualitasId,
                'supplier_id' => $supplierId,
                'brand_kompatibel' => $brand,
                'model_kompatibel' => $model,
                'kompatibilitas_hp' => ! empty($kompatibilitasHp) ? $kompatibilitasHp : null,
                'kondisi' => ! empty($kondisi) ? trim($kondisi) : 'baru',
                'satuan' => $satuan,
                'harga_beli' => $hargaBeli,
                'metode_harga_beli' => in_array($metodeHargaBeli, ['manual', 'average', 'tertinggi'], true) ? $metodeHargaBeli : 'manual',
                'harga_jual_retail' => $hargaJual,
                'margin_persen' => $marginPersen !== null && $marginPersen >= 0 ? $marginPersen : null,
                'gambar' => $fotoUtamaUrl,
                'foto' => ! empty($foto) ? array_values($foto) : null,
                'is_active' => true,
                'harga_fleksibel' => $hargaFleksibel,
                'sn' => $sn, // [F2-3]
                'abc_class' => in_array($abcClass, ['A', 'B', 'C']) ? $abcClass : 'B',
                'reorder_point' => $reorderPoint,
                'min_stock' => $minStock ?? ($stokMinimum > 0 ? $stokMinimum : null),
                'max_stock' => $maxStock,
                'is_ondemand' => $isOndemand,
            ]);

            if ($opsiSku === 'barcode' && ! empty($cleanBarcode)) {
                $finalSku = $cleanBarcode;
            } elseif ($opsiSku === 'manual' && ! empty($sku)) {
                $finalSku = trim($sku);
            } elseif ($opsiSku === 'otomatis') {
                $finalSku = ! empty($sku) ? trim($sku) : 'SKU-'.strtoupper(Str::random(6));
            } else {
                $barcodeFallback = $cleanBarcode ?: null;
                $finalSku = $sku ? trim($sku) : ($barcodeFallback ?: 'SKU-'.strtoupper(Str::random(6)));
            }

            $variant = SkuVariant::create([
                'produk_id' => $produk->id,
                'sku' => $finalSku,
                'barcode' => $cleanBarcode,
                'nama_varian' => 'Standar',
                'satuan_kode' => $satuanRef?->kode,
                'harga_beli' => $hargaBeli,
                'harga_jual_retail' => $hargaJual,
                'is_active' => true,
            ]);

            // Sinkronisasi multi barcode ke format barcode_1, barcode_2, ... di tabel terindeks
            $allInputBarcodes = array_merge($cleanBarcode ? [$cleanBarcode] : [], $cleanBarcodeAlt);
            if (! empty($allInputBarcodes)) {
                $produk->syncBarcodes($allInputBarcodes, $finalSku);
            }

            if ($finalTipeHpIds) {
                $produk->tipeHps()->sync($finalTipeHpIds);
            }

            if ($produkKompatibelIds) {
                $produk->produkKompatibel()->sync(array_values(array_filter($produkKompatibelIds)));
            }

            $this->simpanHargaTier($produk->id, $variant->id, $hargaTier, $hargaJual);

            if (! $gudangId) {
                $cabangId = session('cabang_id') ?? auth()->user()?->cabang_id;
                $gudang = Gudang::when($cabangId, fn ($q, $c) => $q->where('cabang_id', $c))
                    ->where('is_active', true)
                    ->first() ?? Gudang::where('is_active', true)->first();

                if (! $gudang) {
                    $effectiveCabangId = $cabangId ?? 1;
                    $gudang = Gudang::firstOrCreate(
                        ['kode' => 'GDG-01'],
                        ['cabang_id' => $effectiveCabangId, 'nama' => 'Gudang Utama', 'is_active' => true]
                    );
                }
                $gudangId = $gudang->id;
            }

            if (! $rakId && $gudangId) {
                $rak = Rak::where('gudang_id', $gudangId)->where('is_active', true)->first();
                if (! $rak) {
                    $rak = Rak::firstOrCreate(
                        ['kode' => "RAK-DEF-G{$gudangId}"],
                        ['gudang_id' => $gudangId, 'nama' => 'Rak Utama', 'is_active' => true]
                    );
                }
                $rakId = $rak->id;
            }

            if ($gudangId && $stokAwal > 0) {
                $this->inisialisasiStokAwal(
                    $produk->id,
                    $variant->id,
                    $gudangId,
                    $stokAwal,
                    $hargaBeli,
                    "Stok awal produk baru {$nama}",
                    $userId,
                    $rakId
                );
            } elseif ($gudangId) {
                StokItem::firstOrCreate(
                    ['produk_id' => $produk->id, 'sku_variant_id' => $variant->id, 'gudang_id' => $gudangId],
                    ['jumlah' => 0, 'jumlah_minimum' => $stokMinimum, 'rak_id' => $rakId]
                );
            }

            if (! empty($multiSatuan)) {
                $this->simpanMultiSatuan($produk, $multiSatuan, $satuan);
            }

            return $produk;
        });
    }

    /**
     * Update data master produk.
     */
    public function updateProduk(
        int $produkId,
        string $nama,
        ?string $kategori,
        ?string $brand,
        ?string $model,
        string $kondisi,
        float $hargaBeli,
        float $hargaJual,
        ?int $brandId = null,
        ?int $kualitasId = null,
        ?string $satuanKode = null,
        array $tipeHpIds = [],
        array $hargaTier = [],
        bool $hargaFleksibel = false,
        bool $sn = false,
        ?int $kategoriId = null,
        array $foto = [],
        ?string $deskripsi = null,
        array $produkKompatibelIds = [],
        ?string $abcClass = null,
        ?int $reorderPoint = null,
        ?int $minStock = null,
        ?int $maxStock = null,
        ?bool $isOndemand = null,
        ?string $barcode = null,
        ?string $metodeHargaBeli = null,
        ?float $marginPersen = null,
        ?array $multiSatuan = null,
        ?int $supplierId = null,
        ?string $sku = null,
        ?array $barcodeAlt = null,
        ?string $opsiSku = null
    ): Produk {
        return DB::transaction(function () use ($produkId, $nama, $kategori, $brand, $model, $kondisi, $hargaBeli, $hargaJual, $brandId, $kualitasId, $satuanKode, $tipeHpIds, $hargaTier, $hargaFleksibel, $sn, $kategoriId, $foto, $deskripsi, $produkKompatibelIds, $abcClass, $reorderPoint, $minStock, $maxStock, $isOndemand, $barcode, $metodeHargaBeli, $marginPersen, $multiSatuan, $supplierId, $sku, $barcodeAlt, $opsiSku) {
            $produk = Produk::findOrFail($produkId);

            $satuan = $satuanKode ?: ($produk->satuan ?: 'pcs');
            $satuanRef = $satuanKode ? SatuanUnit::where('kode', $satuanKode)->first() : null;

            // Sinkronisasi kategori
            $finalKategoriId = $kategoriId ?? $produk->kategori_id;
            $finalKategoriStr = $kategori ?: ($produk->kategori ?: 'Umum');
            if ($kategoriId) {
                $katRow = KategoriProduk::find($kategoriId);
                if ($katRow) {
                    $finalKategoriStr = $katRow->nama;
                }
            }

            // Normalisasi foto
            $fotoUtamaUrl = null;
            if (! empty($foto)) {
                foreach ($foto as $f) {
                    if (is_array($f) && ! empty($f['is_primary']) && ! empty($f['url'])) {
                        $fotoUtamaUrl = $f['url'];
                        break;
                    }
                }
                if (! $fotoUtamaUrl && isset($foto[0])) {
                    $fotoUtamaUrl = is_array($foto[0]) ? ($foto[0]['url'] ?? null) : $foto[0];
                }
            }

            // Sinkronisasi dua arah: brand_kompatibel, model_kompatibel, dan tipe_hp
            $finalTipeHpIds = $tipeHpIds !== null
                ? array_values(array_filter(array_map('intval', (array) $tipeHpIds)))
                : null;

            $kompatibilitasHp = [];
            if ($finalTipeHpIds !== null) {
                if (! empty($finalTipeHpIds)) {
                    $tipeModels = TipeHp::whereIn('id', $finalTipeHpIds)->get();
                    if ($tipeModels->isNotEmpty()) {
                        $brand = $tipeModels->pluck('merk')->filter()->unique()->implode(', ');
                        $model = $tipeModels->pluck('model')->filter()->unique()->implode(', ');
                        $kompatibilitasHp = $tipeModels->map(fn ($t) => [
                            'merk' => $t->merk,
                            'model' => $t->model,
                            'nama' => $t->nama,
                            'kode_produk' => $t->kode_produk,
                        ])->toArray();
                    } else {
                        $brand = null;
                        $model = null;
                    }
                } else {
                    $brand = null;
                    $model = null;
                    $kompatibilitasHp = null;
                }
            } else {
                $brand = $brand ? trim($brand) : null;
                $model = $model ? trim($model) : null;
            }

            $cleanBarcode = $barcode !== null ? (trim($barcode) ?: null) : $produk->barcode;

            $updatePayload = [
                'nama' => $nama,
                'barcode' => $cleanBarcode,
                'deskripsi' => $deskripsi ?? $produk->deskripsi,
                'kategori' => $finalKategoriStr,
                'kategori_id' => $finalKategoriId,
                'brand_id' => $brandId,
                'kualitas_id' => $kualitasId,
                'supplier_id' => $supplierId,
                'brand_kompatibel' => $brand,
                'model_kompatibel' => $model,
                'kompatibilitas_hp' => ! empty($kompatibilitasHp) ? $kompatibilitasHp : null,
                'kondisi' => ! empty($kondisi) ? trim($kondisi) : 'baru',
                'satuan' => $satuan,
                'harga_beli' => $hargaBeli,
                'metode_harga_beli' => $metodeHargaBeli !== null && in_array($metodeHargaBeli, ['manual', 'average', 'tertinggi'], true) ? $metodeHargaBeli : $produk->metode_harga_beli,
                'harga_jual_retail' => $hargaJual,
                'margin_persen' => $marginPersen !== null ? ($marginPersen >= 0 ? $marginPersen : null) : $produk->margin_persen,
                'gambar' => $fotoUtamaUrl,
                'foto' => ! empty($foto) ? array_values($foto) : null,
                'harga_fleksibel' => $hargaFleksibel,
                'sn' => $sn,
                'abc_class' => $abcClass !== null ? (in_array($abcClass, ['A', 'B', 'C']) ? $abcClass : 'B') : $produk->abc_class,
                'reorder_point' => $reorderPoint !== null ? $reorderPoint : $produk->reorder_point,
                'min_stock' => $minStock !== null ? $minStock : $produk->min_stock,
                'max_stock' => $maxStock !== null ? $maxStock : $produk->max_stock,
                'is_ondemand' => $isOndemand !== null ? $isOndemand : $produk->is_ondemand,
            ];

            if ($barcodeAlt !== null) {
                $cleanBarcodeAlt = [];
                foreach ($barcodeAlt as $bAlt) {
                    $bTrim = trim((string) $bAlt);
                    if ($bTrim !== '' && $bTrim !== $cleanBarcode && ! in_array($bTrim, $cleanBarcodeAlt, true)) {
                        $cleanBarcodeAlt[] = $bTrim;
                    }
                }
                $updatePayload['barcode_alt'] = ! empty($cleanBarcodeAlt) ? $cleanBarcodeAlt : null;
            }

            $produk->update($updatePayload);

            // Sync tipe HP kompatibel
            if ($finalTipeHpIds !== null) {
                $produk->tipeHps()->sync($finalTipeHpIds);
            }

            // Sync produk kompatibel / substitusi
            $produk->produkKompatibel()->sync(array_values(array_filter($produkKompatibelIds)));

            // Update primary SKU variant harga & sku
            $variant = $produk->skuVariants()->first();
            $cleanSku = ! empty($sku) ? trim((string) $sku) : null;

            if ($variant) {
                $skuToUpdate = $variant->sku;
                if ($opsiSku === 'barcode' && ! empty($cleanBarcode)) {
                    $skuToUpdate = $cleanBarcode;
                } elseif ($cleanSku !== null && $cleanSku !== '') {
                    $skuExists = SkuVariant::where('sku', $cleanSku)
                        ->where('id', '!=', $variant->id)
                        ->exists();
                    if ($skuExists) {
                        throw new \InvalidArgumentException("Kode SKU '{$cleanSku}' sudah digunakan oleh produk lain.");
                    }
                    $skuToUpdate = $cleanSku;
                } elseif ($opsiSku === 'otomatis') {
                    $skuToUpdate = 'SKU-'.strtoupper(Str::random(6));
                } elseif (empty($skuToUpdate) && ! empty($cleanBarcode)) {
                    $skuToUpdate = $cleanBarcode;
                }

                $variant->update([
                    'sku' => $skuToUpdate,
                    'barcode' => $cleanBarcode,
                    'satuan_kode' => $satuanRef?->kode ?? $variant->satuan_kode,
                    'harga_beli' => $hargaBeli,
                    'harga_jual_retail' => $hargaJual,
                ]);

                if (! empty($hargaTier)) {
                    $this->simpanHargaTier($produk->id, $variant->id, $hargaTier, $hargaJual);
                }
            } else {
                $finalSku = $cleanSku ?: ($cleanBarcode ?: 'SKU-'.strtoupper(Str::random(6)));
                $variant = SkuVariant::create([
                    'produk_id' => $produk->id,
                    'sku' => $finalSku,
                    'barcode' => $cleanBarcode,
                    'satuan_kode' => $satuanRef?->kode ?? $satuan,
                    'harga_beli' => $hargaBeli,
                    'harga_jual_retail' => $hargaJual,
                    'is_active' => true,
                ]);

                if (! empty($hargaTier)) {
                    $this->simpanHargaTier($produk->id, $variant->id, $hargaTier, $hargaJual);
                }
            }

            // Sync barcode ke format barcode_1, barcode_2, ... di tabel terindeks
            if ($barcode !== null || $barcodeAlt !== null) {
                $curBarcode = $cleanBarcode !== null ? $cleanBarcode : ($produk->barcode ?? null);
                $curAlt = $barcodeAlt !== null ? $cleanBarcodeAlt : ($produk->barcode_alt ?? []);
                $allToSync = array_merge($curBarcode ? [$curBarcode] : [], is_array($curAlt) ? array_values($curAlt) : []);
                $targetSku = $variant->sku;
                $produk->syncBarcodes($allToSync, $targetSku);
            }

            if ($multiSatuan !== null) {
                $this->simpanMultiSatuan($produk, $multiSatuan, $satuan);
            }

            return $produk->fresh();
        });
    }

    /**
     * Simpan multi satuan konversi bertingkat per produk.
     * Cth: 1 box = 10 pcs, 1 karton = 10 box (= 100 pcs).
     */
    public function simpanMultiSatuan(Produk $produk, array $multiSatuan, ?string $baseSatuan = null): void
    {
        $base = strtolower(trim($baseSatuan ?: ($produk->satuan ?: 'pcs')));
        $produk->satuanKonversi()->delete();

        $factors = [$base => 1.0];

        foreach ($multiSatuan as $idx => $row) {
            $kode = trim($row['satuan_kode'] ?? '');
            if (! $kode || strtolower($kode) === $base) {
                continue;
            }

            $asal = strtolower(trim($row['satuan_asal'] ?? $base));
            if (! $asal) {
                $asal = $base;
            }

            $pengali = max(0.0001, (float) ($row['pengali'] ?? 1));
            $parentFactor = $factors[$asal] ?? 1.0;
            $faktorKonversi = round($pengali * $parentFactor, 4);
            $factors[strtolower($kode)] = $faktorKonversi;

            // Daftarkan ke master satuan_unit agar terpusat dan tidak duplikat
            SatuanUnit::firstOrCreate(
                ['kode' => strtolower($kode)],
                ['nama' => ucfirst($kode), 'is_active' => true]
            );

            $hargaBeli = isset($row['harga_beli']) && $row['harga_beli'] !== '' && $row['harga_beli'] !== null
                ? (float) $row['harga_beli']
                : null;

            $hargaJual = isset($row['harga_jual']) && $row['harga_jual'] !== '' && $row['harga_jual'] !== null
                ? (float) $row['harga_jual']
                : null;

            $produk->satuanKonversi()->create([
                'satuan_kode' => $kode,
                'satuan_asal' => $asal,
                'pengali' => $pengali,
                'faktor_konversi' => $faktorKonversi,
                'harga_beli' => $hargaBeli,
                'harga_jual' => $hargaJual,
                'barcode' => ! empty($row['barcode']) ? trim($row['barcode']) : null,
                'is_satuan_beli' => (bool) ($row['is_satuan_beli'] ?? false),
                'is_satuan_jual' => (bool) ($row['is_satuan_jual'] ?? true),
                'urutan' => (int) ($row['urutan'] ?? ($idx + 1)),
            ]);
        }
    }

    /**
     * [T-44] Upsert HargaTier per tipe_konsumen dari form Master Produk.
     * Struktur: ['retail' => ['nominal_tetap' => x, 'persen_diskon' => y], 'reseller' => ..., 'agen' => ...]
     * Bila tier_membership_id dikirim (kunci 'tier'), dibuat baris eksak (produk, tier, tipe).
     */
    public function simpanHargaTier(int $produkId, ?int $variantId, array $hargaTier, float $hargaJualFallback = 0): void
    {
        if (! $hargaTier) {
            return;
        }

        foreach (['retail', 'reseller', 'agen'] as $tipe) {
            $data = $hargaTier[$tipe] ?? null;
            if (! is_array($data)) {
                continue;
            }

            $nominal = is_numeric($data['nominal_tetap'] ?? null)
                ? (float) $data['nominal_tetap']
                : null;
            $persen = is_numeric($data['persen_diskon'] ?? null)
                ? (float) $data['persen_diskon']
                : null;
            $tierId = ! empty($data['tier_membership_id']) ? (int) $data['tier_membership_id'] : null;

            if ($nominal === null && $persen === null && ! $tierId) {
                continue;
            }

            if ($nominal === null && $persen === null && $tierId) {
                // Baris eksak tier tanpa nilai nominal/persen — abaikan (butuh minimal 1 nilai harga)
                continue;
            }

            HargaTier::updateOrCreate(
                [
                    'produk_id' => $produkId,
                    'sku_variant_id' => $variantId,
                    'tier_membership_id' => $tierId,
                    'tipe_konsumen' => $tipe,
                ],
                [
                    'is_reseller' => $tipe === 'reseller',
                    'harga' => $nominal ?? round(($hargaJualFallback ?: 0) * (1 - ($persen ?: 0) / 100), 2),
                    'nominal_tetap' => $nominal,
                    'persen_diskon' => $persen,
                ]
            );
        }
    }

    /**
     * Inisialisasi stok awal saat setup master produk baru.
     * Jurnal: Persediaan (130-01) debit / Modal Pemilik (310-01) kredit.
     */
    public function inisialisasiStokAwal(
        int $produkId,
        ?int $variantId,
        int $gudangId,
        int $qty,
        float $hargaBeli,
        string $keterangan,
        ?int $userId = null,
        ?int $rakId = null,
        ?array $snList = null
    ): StokItem {
        if ($qty <= 0) {
            throw new \Exception('Kuantitas harus > 0');
        }

        return DB::transaction(function () use ($produkId, $variantId, $gudangId, $qty, $hargaBeli, $keterangan, $userId, $rakId, $snList) {
            $cabangId = Gudang::find($gudangId)?->cabang_id;

            $stok = StokItem::where('produk_id', $produkId)
                ->where('gudang_id', $gudangId)
                ->when($variantId, fn ($q) => $q->where('sku_variant_id', $variantId))
                ->first();

            if (! $stok) {
                $orphan = StokItem::where('produk_id', $produkId)
                    ->where('gudang_id', $gudangId)
                    ->whereNull('sku_variant_id')
                    ->first();

                if ($orphan && $variantId) {
                    $orphan->update(['sku_variant_id' => $variantId]);
                    $stok = $orphan;
                } else {
                    $stok = StokItem::create([
                        'produk_id' => $produkId,
                        'sku_variant_id' => $variantId,
                        'gudang_id' => $gudangId,
                        'jumlah' => 0,
                        'jumlah_minimum' => 0,
                        'rak_id' => $rakId,
                    ]);
                }
            }

            $sebelum = (int) $stok->jumlah;
            $setelah = $sebelum + $qty;

            $stok->update(['jumlah' => $setelah] + ($rakId ? ['rak_id' => $rakId] : []));

            StokLog::create([
                'gudang_id' => $gudangId,
                'produk_id' => $produkId,
                'sku_variant_id' => $variantId,
                'user_id' => $userId,
                'jenis' => 'stok_awal',
                'referensi_tipe' => Produk::class,
                'referensi_id' => $produkId,
                'jumlah_sebelum' => $sebelum,
                'perubahan' => $qty,
                'jumlah_setelah' => $setelah,
                'catatan' => $keterangan,
            ]);

            StockMutationLog::create([
                'produk_id' => $produkId,
                'sku_variant_id' => $variantId,
                'gudang_id' => $gudangId,
                'user_id' => $userId,
                'delta' => $qty,
                'sumber' => 'stok_awal',
                'referensi_tipe' => Produk::class,
                'referensi_id' => $produkId,
                'terjadi_at' => now(),
            ]);

            $total = round($hargaBeli * $qty, 2);
            if ($total > 0) {
                $this->jurnalService->post(
                    $this->jurnalService->generateNoJurnal('awal', $cabangId),
                    now(),
                    'stok_awal',
                    [
                        ['akun_kode' => '130-01', 'debit' => $total, 'kredit' => 0],   // Persediaan bertambah
                        ['akun_kode' => '310-01', 'debit' => 0, 'kredit' => $total],  // Modal Pemilik (Ekuitas)
                    ],
                    $keterangan,
                    $cabangId,
                    $userId,
                    Produk::class,
                    $produkId
                );
            }

            // Persist SN jika produk bertipe SN
            if ($snList !== null && ! empty($snList)) {
                foreach ($snList as $sn) {
                    NomorSeri::create([
                        'cabang_id' => $cabangId,
                        'produk_id' => $produkId,
                        'sku_variant_id' => $variantId,
                        'nomor_seri' => $sn,
                        'status' => NomorSeri::STATUS_TERSEDIA,
                        'keterangan' => $keterangan,
                    ]);
                }
            }

            return $stok;
        });
    }

    /**
     * Tambah stok (pembelian ke supplier) → stok + StokLog + jurnal akunting.
     *
     * [F2-3] snList: daftar SN utk produk sn=true (null = tanpa SN).
     * Bila diisi → validasi (jumlah=qty, dobel, sudah terdaftar) + persist SN
     * status 'tersedia' cabang-scoped DALAM transaksi yg sama — gagal validasi
     * → rollback penuh, tanpa perubahan stok sebagian.
     *
     * @param  array<int, string>|null  $snList
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
        bool $postJurnal = true,
        ?string $smlSumber = null,
        ?string $smlReferensiTipe = null,
        ?int $smlReferensiId = null,
        ?array $snList = null // [F2-3]
    ): StokItem {
        if ($qty <= 0) {
            throw new \Exception('Kuantitas harus > 0');
        }

        return DB::transaction(function () use ($produkId, $variantId, $gudangId, $qty, $hargaBeli, $keterangan, $userId, $rakId, $postJurnal, $smlSumber, $smlReferensiTipe, $smlReferensiId, $snList) {
            $cabangId = Gudang::find($gudangId)?->cabang_id;
            if (! $variantId) {
                $variantId = SkuVariant::where('produk_id', $produkId)->value('id');
            }

            // [F2-3] Validasi SN dulu — Exception di sini → rollback, stok tidak berubah
            if ($snList !== null) {
                $this->nomorSeriService->validasiUntukGrn($produkId, $snList, $qty);
            }

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

            // [T-26] SOT mutation log — [T-40] sumber 'po:receive' + referensi PO saat
            // dipanggil dari PurchaseOrderService::terimaBarang (override via sml* params).
            // [B-10i] user_id = pelaku yang menambah stok (sama dgn StokLog & jurnal di bawah);
            // pemanggil yang tidak punya konteks user (mis. impor/CLI) → NULL (kolom nullable).
            StockMutationLog::create([
                'produk_id' => $produkId,
                'sku_variant_id' => $variantId,
                'gudang_id' => $gudangId,
                'user_id' => $userId,
                'delta' => $qty,
                'sumber' => $smlSumber ?? 'po',
                'referensi_tipe' => $smlReferensiTipe ?? Produk::class,
                'referensi_id' => $smlReferensiId ?? $produkId,
                'terjadi_at' => now(),
            ]);

            // Jurnal pembelian (PRD §4.6): Persediaan debit / Utang Usaha kredit.
            // Saat dipanggil dari terimaBarang PO: jurnal agregat dibuat di PurchaseOrderService
            // (postJurnal=false) agar tidak dobel-posting per item.
            $total = round($hargaBeli * $qty, 2);
            if ($postJurnal && $total > 0) {
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

            // [F2-3] Persist SN sebagai 'tersedia' + tautan cabang (dalam transaksi)
            if ($snList !== null) {
                foreach ($snList as $sn) {
                    NomorSeri::create([
                        'cabang_id' => $cabangId,
                        'produk_id' => $produkId,
                        'sku_variant_id' => $variantId,
                        'nomor_seri' => $sn,
                        'status' => NomorSeri::STATUS_TERSEDIA,
                        'keterangan' => $keterangan,
                    ]);
                }
            }

            return $stok;
        });
    }

    /**
     * Hitung Moving Average Cost (MAC) dan sesuaikan harga modal/beli produk otomatis.
     * Jika margin_persen terisi, harga jual retail ikut disesuaikan otomatis.
     *
     * [H9] $cabangId men-scoped average ke cabang tsb (biaya per cabang disimpan
     * di produk_biaya_cabang; master = weighted-average global yang benar).
     */
    public function sesuaikanHargaBeliAverage(int $produkId, int $qtyMasuk, float $hargaBeliMasuk, ?int $cabangId = null): void
    {
        $this->sesuaikanHargaBeliProduk($produkId, $qtyMasuk, $hargaBeliMasuk, null, null, null, $cabangId);
    }

    /**
     * Sesuaikan harga modal/beli produk secara terpadu berdasarkan metode harga beli master produk
     * atau opsi penentuan harga yang ditentukan (average, tertinggi, manual).
     *
     * [H9] Kebijakan metode (IAS 2 ¶25 hanya mengizinkan FIFO / weighted average):
     * - 'average': weighted-average perpetual PER CABANG (produk_biaya_cabang).
     *   Master global menerima weighted-average global yang benar — bukan average
     *   cabang lain (kontaminasi antar-cabang ditutup). Semua varian SKU di-update.
     *   Metode yang direkomendasikan untuk produk persediaan.
     * - 'manual': harga beli terakhir (≈ replacement cost). BUKAN biaya historis
     *   IAS 2 — dipertahankan sebagai kebijakan harga manual (mis. produk jasa /
     *   non-persediaan / harga kontrak tetap). Jangan gunakan untuk persediaan
     *   yang dinilai IAS 2 tanpa kebijakan tertulis.
     * - 'tertinggi': harga tertinggi histori pembelian. TIDAK patuh IAS 2 —
     *   dipertahankan hanya sebagai kebijakan pricing konservatif (mis. penetapan
     *   harga jual minimum), bukan untuk valuasi neraca persediaan.
     * Semantik manual/tertinggi sengaja tidak diubah drastis (keputusan kebijakan
     * akuntansi ada di owner); lihat audit H9.
     */
    public function sesuaikanHargaBeliProduk(
        int $produkId,
        int $qtyMasuk,
        float $hargaBeliMasuk,
        ?string $opsiMetode = null,
        ?string $skemaJual = null,
        ?float $nilaiJualAtauMargin = null,
        ?int $cabangId = null
    ): array {
        if ($qtyMasuk <= 0 || $hargaBeliMasuk <= 0) {
            return ['sukses' => false, 'pesan' => 'Qty atau harga beli tidak valid'];
        }

        $produk = Produk::whereKey($produkId)->lockForUpdate()->first();
        if (! $produk) {
            return ['sukses' => false, 'pesan' => 'Produk tidak ditemukan'];
        }

        $metodeAktif = $opsiMetode ?: ($produk->metode_harga_beli ?: 'manual');

        $hargaBeliLama = (float) $produk->harga_beli;
        $hargaBeliBaru = $hargaBeliLama;
        $hargaCabangBaru = null;

        if ($metodeAktif === 'average') {
            $cabangTarget = $cabangId ?? currentCabangId();
            $stokQuery = StokItem::where('produk_id', $produkId);
            if ($cabangTarget) {
                $stokQuery->whereHas('gudang', fn ($q) => $q->where('cabang_id', $cabangTarget));
            }
            $stokTotalSekarang = (int) $stokQuery->sum('jumlah');
            $stokLama = max(0, $stokTotalSekarang - $qtyMasuk);

            // [H9] Basis average cabang = biaya cabang tersimpan (bukan master
            // global yang bisa berasal dari cabang lain).
            $avgLama = $cabangTarget
                ? $this->biayaCabang($produkId, $cabangTarget, $hargaBeliLama)
                : $hargaBeliLama;

            if ($stokLama <= 0) {
                $hargaBeliBaru = round($hargaBeliMasuk, 2);
            } else {
                $hargaBeliBaru = round((($stokLama * $avgLama) + ($qtyMasuk * $hargaBeliMasuk)) / ($stokLama + $qtyMasuk), 2);
            }

            if ($cabangTarget) {
                // [H9] Biaya cabang disimpan per cabang; master HANYA menerima
                // weighted-average global yang benar (dihitung SEBELUM baris
                // cabang di-upsert agar memakai biaya lama cabang ini).
                $hargaCabangBaru = $hargaBeliBaru;
                $hargaBeliBaru = $this->averageGlobalMasuk($produk, $qtyMasuk, $hargaBeliMasuk, $cabangTarget, $hargaBeliLama);
                ProdukBiayaCabang::updateOrCreate(
                    ['produk_id' => $produkId, 'cabang_id' => $cabangTarget],
                    ['harga_beli' => $hargaCabangBaru]
                );
            }
        } elseif ($metodeAktif === 'tertinggi') {
            // Ambil harga tertinggi dari histori pembelian produk + harga masuk saat ini
            $procurement = app(ProcurementService::class);
            $histori = $procurement->getHistoriPembelian($produkId);
            $hargaTertinggiHistori = (float) ($histori['statistik']['harga_tertinggi'] ?? 0);

            $hargaBeliBaru = max($hargaBeliLama, $hargaBeliMasuk, $hargaTertinggiHistori);
            $hargaBeliBaru = round($hargaBeliBaru, 2);
        } elseif ($metodeAktif === 'manual') {
            $hargaBeliBaru = round($hargaBeliMasuk, 2);
        }

        $updateData = [
            'harga_beli' => $hargaBeliBaru,
        ];

        // Tentukan harga jual jika ada skema
        $skema = $skemaJual ?: 'default_margin';
        $hargaJualBaru = (float) $produk->harga_jual_retail;

        if ($skema === 'default_margin') {
            if ($produk->margin_persen !== null && (float) $produk->margin_persen > 0) {
                $hargaJualBaru = round($hargaBeliBaru * (1 + ((float) $produk->margin_persen / 100)));
                $updateData['harga_jual_retail'] = $hargaJualBaru;
            }
        } elseif ($skema === 'persen') {
            $persen = (float) ($nilaiJualAtauMargin ?? $produk->margin_persen ?? 0);
            if ($persen > 0) {
                $hargaJualBaru = round($hargaBeliBaru * (1 + ($persen / 100)));
                $updateData['harga_jual_retail'] = $hargaJualBaru;
                $updateData['margin_persen'] = $persen;
            }
        } elseif ($skema === 'manual') {
            if ($nilaiJualAtauMargin !== null && (float) $nilaiJualAtauMargin > 0) {
                $hargaJualBaru = round((float) $nilaiJualAtauMargin, 2);
                $updateData['harga_jual_retail'] = $hargaJualBaru;
                if ($hargaBeliBaru > 0) {
                    $updateData['margin_persen'] = round((($hargaJualBaru - $hargaBeliBaru) / $hargaBeliBaru) * 100, 2);
                }
            }
        }

        if (isset($updateData['harga_jual_retail'])) {
            HargaTier::where('produk_id', $produk->id)
                ->where('tipe_konsumen', 'retail')
                ->update([
                    'nominal_tetap' => $updateData['harga_jual_retail'],
                    'harga' => $updateData['harga_jual_retail'],
                ]);
        }

        $produk->update($updateData);

        // [H9] Semua varian relevan di-update — transfer & POS membaca harga
        // varian lebih dulu dari master; hanya varian pertama = inkonsistensi.
        $this->updateHargaBeliSemuaVarian($produk, $hargaBeliBaru, $updateData['harga_jual_retail'] ?? null);

        return [
            'sukses' => true,
            'harga_beli_lama' => $hargaBeliLama,
            'harga_beli_baru' => $hargaBeliBaru,
            'harga_beli_cabang' => $hargaCabangBaru,
            'harga_jual_baru' => $hargaJualBaru,
            'metode' => $metodeAktif,
        ];
    }

    /**
     * [H9/M7] Hitung ulang weighted-average setelah barang keluar (retur pembelian).
     * Stok sudah dipotong caller; avgBaru = (qtySebelum x avgLama - qtyKeluar x biayaKeluar) / qtySisa.
     * Per cabang bila $cabangId ada (biaya cabang + master global konsisten);
     * tanpa cabang → formula global legacy. Mirror efek retail margin + HargaTier
     * + semua varian, tanpa mengubah harga jual bila tanpa margin.
     *
     * @return array{sukses:bool, pesan?:string, harga_beli_lama?:float, harga_beli_baru?:float, harga_beli_cabang?:float|null}
     */
    public function sesuaikanAverageRetur(int $produkId, int $qtyKeluar, float $biayaKeluarPerBase, ?int $cabangId = null): array
    {
        if ($qtyKeluar <= 0) {
            return ['sukses' => false, 'pesan' => 'Qty retur tidak valid'];
        }

        $produk = Produk::whereKey($produkId)->lockForUpdate()->first();
        if (! $produk) {
            return ['sukses' => false, 'pesan' => 'Produk tidak ditemukan'];
        }
        if (($produk->metode_harga_beli ?: 'manual') !== 'average') {
            return ['sukses' => false, 'pesan' => 'Bukan metode average'];
        }

        $cabangTarget = $cabangId ?? currentCabangId();
        $hargaLama = (float) $produk->harga_beli;

        $stokQuery = StokItem::where('produk_id', $produkId);
        if ($cabangTarget) {
            $stokQuery->whereHas('gudang', fn ($q) => $q->where('cabang_id', $cabangTarget));
        }
        $stokSisa = (int) $stokQuery->sum('jumlah');
        if ($stokSisa <= 0) {
            return ['sukses' => false, 'pesan' => 'Tidak ada sisa stok — average tidak bermakna, biarkan biaya terakhir'];
        }

        $avgLama = $cabangTarget
            ? $this->biayaCabang($produkId, $cabangTarget, $hargaLama)
            : $hargaLama;
        $qtySebelum = $stokSisa + $qtyKeluar;
        $nilaiSisa = ($qtySebelum * $avgLama) - ($qtyKeluar * $biayaKeluarPerBase);
        $hargaCabangBaru = round($nilaiSisa / $stokSisa, 2);
        if ($hargaCabangBaru <= 0) {
            $hargaCabangBaru = $avgLama; // jaga biaya tetap positif
        }

        if ($cabangTarget) {
            ProdukBiayaCabang::updateOrCreate(
                ['produk_id' => $produkId, 'cabang_id' => $cabangTarget],
                ['harga_beli' => $hargaCabangBaru]
            );
            $hargaMasterBaru = $this->averageGlobalSisa($produk, $hargaLama);
        } else {
            $hargaMasterBaru = $hargaCabangBaru;
        }

        $updateData = ['harga_beli' => $hargaMasterBaru];
        $hargaJualBaru = null;
        if ($produk->margin_persen !== null && (float) $produk->margin_persen > 0) {
            $hargaJualBaru = round($hargaMasterBaru * (1 + ((float) $produk->margin_persen / 100)));
            $updateData['harga_jual_retail'] = $hargaJualBaru;
            HargaTier::where('produk_id', $produk->id)
                ->where('tipe_konsumen', 'retail')
                ->update(['nominal_tetap' => $hargaJualBaru, 'harga' => $hargaJualBaru]);
        }
        $produk->update($updateData);
        $this->updateHargaBeliSemuaVarian($produk, $hargaMasterBaru, $hargaJualBaru);

        return [
            'sukses' => true,
            'harga_beli_lama' => $hargaLama,
            'harga_beli_baru' => $hargaMasterBaru,
            'harga_beli_cabang' => $cabangTarget ? $hargaCabangBaru : null,
        ];
    }

    /**
     * [H9] Rekonsiliasi GL persediaan (130-01) vs subledger Σ(stok x biaya).
     * Biaya per baris stok = biaya cabang tersimpan (produk_biaya_cabang),
     * fallback master harga_beli. Selisih (drift pembulatan antar-GRN) dijurnal
     * ke 520-08 Selisih Stok agar GL = subledger:
     *   subledger > GL → Dr 130-01 / Cr 520-08; sebaliknya Dr 520-08 / Cr 130-01.
     * Idempoten: tanpa drift (|selisih| < 0.01) tidak ada jurnal.
     *
     * @return array{nilai_subledger:float, nilai_gl:float, selisih:float, jurnal_diposting:bool, no_jurnal:string|null}
     */
    public function rekonsiliasiPersediaan(?int $cabangId = null, ?int $userId = null, bool $postJurnal = true): array
    {
        $stokQuery = StokItem::with(['produk', 'gudang']);
        if ($cabangId) {
            $stokQuery->whereHas('gudang', fn ($q) => $q->where('cabang_id', $cabangId));
        }

        $nilaiSubledger = 0.0;
        foreach ($stokQuery->get() as $stok) {
            $qty = (int) $stok->jumlah;
            if ($qty <= 0 || ! $stok->produk) {
                continue;
            }
            $master = (float) $stok->produk->harga_beli;
            $cabangStok = $stok->gudang?->cabang_id;
            $biaya = $cabangStok
                ? $this->biayaCabang((int) $stok->produk_id, (int) $cabangStok, $master)
                : $master;
            $nilaiSubledger += $qty * $biaya;
        }
        $nilaiSubledger = round($nilaiSubledger, 2);

        $nilaiGl = 0.0;
        $akun = AkunCOA::where('kode', '130-01')->first();
        if ($akun) {
            $q = JurnalAkuntansi::where('akun_coa_id', $akun->id);
            if ($cabangId) {
                $q->where('cabang_id', $cabangId);
            }
            $nilaiGl = round((float) $q->sum('debit') - (float) $q->sum('kredit'), 2);
        }

        $selisih = round($nilaiSubledger - $nilaiGl, 2);
        $diposting = false;
        $noJurnal = null;

        if ($postJurnal && $akun && abs($selisih) >= 0.01) {
            $nominal = abs($selisih);
            $lines = $selisih > 0
                ? [
                    ['akun_kode' => '130-01', 'debit' => $nominal, 'kredit' => 0],
                    ['akun_kode' => '520-08', 'debit' => 0, 'kredit' => $nominal],
                ]
                : [
                    ['akun_kode' => '520-08', 'debit' => $nominal, 'kredit' => 0],
                    ['akun_kode' => '130-01', 'debit' => 0, 'kredit' => $nominal],
                ];
            $noJurnal = $this->jurnalService->generateNoJurnal('manual', $cabangId);
            $this->jurnalService->post(
                $noJurnal,
                now(),
                'manual',
                $lines,
                'Rekonsiliasi valuasi persediaan [H9] — selisih GL vs subledger',
                $cabangId,
                $userId
            );
            $diposting = true;
        }

        return [
            'nilai_subledger' => $nilaiSubledger,
            'nilai_gl' => $nilaiGl,
            'selisih' => $selisih,
            'jurnal_diposting' => $diposting,
            'no_jurnal' => $noJurnal,
        ];
    }

    /**
     * [H9] Biaya cabang tersimpan, fallback bila cabang belum punya baris.
     */
    private function biayaCabang(int $produkId, int $cabangId, float $fallback): float
    {
        $tersimpan = ProdukBiayaCabang::where('produk_id', $produkId)
            ->where('cabang_id', $cabangId)
            ->value('harga_beli');

        return $tersimpan !== null ? (float) $tersimpan : $fallback;
    }

    /**
     * [H9] Peta stok per cabang + biaya average-nya. Cabang berstok yang belum
     * punya baris biaya di-materialisasi dengan fallback (master saat ini) agar
     * biaya implisitnya tidak ikut bergeser setiap master berubah (anti drift).
     *
     * @return array<int, array{cabang_id:int|null, qty:int, avg:float}>
     */
    private function petaBiayaCabang(Produk $produk, float $fallback): array
    {
        $rows = DB::table('stok_items')
            ->where('stok_items.produk_id', $produk->id)
            ->leftJoin('gudang', 'gudang.id', '=', 'stok_items.gudang_id')
            ->groupBy('gudang.cabang_id')
            ->selectRaw('gudang.cabang_id as cabang_id, COALESCE(SUM(stok_items.jumlah), 0) as qty')
            ->get();

        $peta = [];
        foreach ($rows as $r) {
            $cabangId = $r->cabang_id !== null ? (int) $r->cabang_id : null;
            $avg = $fallback;
            if ($cabangId !== null) {
                $tersimpan = ProdukBiayaCabang::where('produk_id', $produk->id)
                    ->where('cabang_id', $cabangId)
                    ->value('harga_beli');
                if ($tersimpan !== null) {
                    $avg = (float) $tersimpan;
                } elseif ((int) $r->qty > 0) {
                    ProdukBiayaCabang::updateOrCreate(
                        ['produk_id' => $produk->id, 'cabang_id' => $cabangId],
                        ['harga_beli' => $avg]
                    );
                }
            }
            $peta[] = ['cabang_id' => $cabangId, 'qty' => (int) $r->qty, 'avg' => $avg];
        }

        return $peta;
    }

    /**
     * [H9] Weighted-average GLOBAL setelah penerimaan (stok sudah termasuk qtyMasuk).
     * Nilai = Σ(qtySebelum x avgCabang) + qtyMasuk x hargaMasuk, dibagi total qty.
     * Cabang target memakai biaya LAMA-nya (peta dibaca sebelum upsert cabang).
     */
    private function averageGlobalMasuk(Produk $produk, int $qtyMasuk, float $hargaMasuk, int $cabangTarget, float $hargaLama): float
    {
        $peta = $this->petaBiayaCabang($produk, $hargaLama);
        $totalQty = $qtyMasuk;
        $totalNilai = $qtyMasuk * $hargaMasuk;

        foreach ($peta as $p) {
            $isTarget = $p['cabang_id'] === $cabangTarget;
            $qtyPre = $isTarget ? max(0, $p['qty'] - $qtyMasuk) : $p['qty'];
            $totalQty += $qtyPre;
            $totalNilai += $qtyPre * $p['avg'];
        }

        return $totalQty > 0 ? round($totalNilai / $totalQty, 2) : round($hargaMasuk, 2);
    }

    /**
     * [H9] Weighted-average GLOBAL atas stok sisa (setelah barang keluar).
     * Dipanggil SETELAH baris biaya cabang target di-upsert.
     */
    private function averageGlobalSisa(Produk $produk, float $hargaLama): float
    {
        $peta = $this->petaBiayaCabang($produk, $hargaLama);
        $totalQty = 0;
        $totalNilai = 0.0;

        foreach ($peta as $p) {
            if ($p['qty'] <= 0) {
                continue;
            }
            $totalQty += $p['qty'];
            $totalNilai += $p['qty'] * $p['avg'];
        }

        return $totalQty > 0 ? round($totalNilai / $totalQty, 2) : $hargaLama;
    }

    /**
     * [H9] Update harga beli (dan jual retail bila ada) ke SEMUA varian SKU.
     */
    private function updateHargaBeliSemuaVarian(Produk $produk, float $hargaBeliBaru, ?float $hargaJualBaru): void
    {
        foreach ($produk->skuVariants()->get() as $variant) {
            $update = ['harga_beli' => $hargaBeliBaru];
            if ($hargaJualBaru !== null) {
                $update['harga_jual_retail'] = $hargaJualBaru;
            }
            $variant->update($update);
        }
    }
}
