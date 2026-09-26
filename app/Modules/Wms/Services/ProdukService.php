<?php

namespace App\Modules\Wms\Services;

use App\Modules\Akunting\Services\JurnalService;
use App\Modules\Pos\Models\HargaTier;
use App\Modules\Wms\Models\Gudang;
use App\Modules\Wms\Models\NomorSeri;
use App\Modules\Wms\Models\Produk;
use App\Modules\Wms\Models\SatuanUnit;
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
        bool $sn = false // [F2-3]
    ): Produk {
        return DB::transaction(function () use ($nama, $kategori, $brand, $model, $kondisi, $hargaBeli, $hargaJual, $sku, $gudangId, $stokAwal, $stokMinimum, $userId, $brandId, $kualitasId, $satuanKode, $tipeHpIds, $hargaTier, $hargaFleksibel, $sn) {
            $satuan = $satuanKode ?: 'pcs';
            $satuanRef = $satuanKode ? SatuanUnit::where('kode', $satuanKode)->first() : null;
            if ($satuanKode && ! $satuanRef) {
                throw new \Exception("Satuan '{$satuanKode}' tidak terdaftar di satuan_unit");
            }

            $produk = Produk::create([
                'nama' => $nama,
                'slug' => Str::slug($nama).'-'.Str::lower(Str::random(4)),
                'deskripsi' => null,
                'kategori' => $kategori ?: 'Umum',
                'brand_id' => $brandId,
                'kualitas_id' => $kualitasId,
                'brand_kompatibel' => $brand,
                'model_kompatibel' => $model,
                'kondisi' => in_array($kondisi, ['baru', 'oem', 'compatible'], true) ? $kondisi : 'baru',
                'satuan' => $satuan,
                'harga_beli' => $hargaBeli,
                'harga_jual_retail' => $hargaJual,
                'is_active' => true,
                'harga_fleksibel' => $hargaFleksibel,
                'sn' => $sn, // [F2-3]
            ]);

            $variant = SkuVariant::create([
                'produk_id' => $produk->id,
                'sku' => $sku ?: 'SKU-'.strtoupper(Str::random(6)),
                'nama_varian' => 'Standar',
                'satuan_kode' => $satuanRef?->kode,
                'harga_beli' => $hargaBeli,
                'harga_jual_retail' => $hargaJual,
                'is_active' => true,
            ]);

            if ($tipeHpIds) {
                $produk->tipeHps()->sync(array_values(array_filter($tipeHpIds)));
            }

            $this->simpanHargaTier($produk->id, $variant->id, $hargaTier, $hargaJual);

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
}
