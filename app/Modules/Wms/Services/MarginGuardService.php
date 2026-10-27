<?php

namespace App\Modules\Wms\Services;

use App\Modules\Crm\Services\KonfigurasiService;
use App\Modules\Pos\Models\HargaTier;
use App\Modules\Wms\Models\Produk;
use App\Modules\Wms\Models\SkuVariant;
use Illuminate\Support\Collection;

class MarginGuardService
{
    /**
     * Ambil parameter kalkulasi margin aman berdasarkan konfigurasi aktif sistem.
     *
     * @return array{
     *     max_tier_diskon: float,
     *     diskon_agen: float,
     *     diskon_reseller: float,
     *     max_mitra_diskon: float,
     *     komisi_persen: float,
     *     buffer_biaya_persen: float,
     *     total_potongan_persen: float,
     *     min_markup_persen: float
     * }
     */
    public function getParameterMargin(?string $kategori = null): array
    {
        // [SSOT] Ambil parameter konsolidasi resmi dari Konfigurasi CRM
        return app(KonfigurasiService::class)->getRingkasanPotonganSistem($kategori);
    }

    /**
     * Hitung rekomendasi harga jual minimum yang aman dari potensi rugi.
     */
    public function hitungHargaAman(float $hargaBeli, ?string $kategori = null, ?array $cachedParams = null): float
    {
        if ($hargaBeli <= 0) {
            return 0.0;
        }

        $params = $cachedParams ?? $this->getParameterMargin($kategori);
        $faktorPembagi = max(0.1, (100 - $params['total_potongan_persen']) / 100);

        // Dibulatkan ke atas ke kelipatan 500 terdekat agar rapi di kasir & marketplace
        $hargaRaw = $hargaBeli / $faktorPembagi;

        return ceil($hargaRaw / 500) * 500;
    }

    /**
     * Evaluasi satu pasangan harga beli vs harga jual.
     *
     * @return array{
     *     status: 'aman'|'peringatan'|'bahaya',
     *     status_label: string,
     *     harga_beli: float,
     *     harga_jual: float,
     *     margin_nominal: float,
     *     margin_persen: float,
     *     rekomendasi_harga: float,
     *     selisih_rekomendasi: float,
     *     total_potongan_persen: float,
     *     pesan: string
     * }
     */
    public function evaluasiHarga(float $hargaBeli, float $hargaJual, ?string $kategori = null, ?array $cachedParams = null): array
    {
        $params = $cachedParams ?? $this->getParameterMargin($kategori);
        $rekomendasi = $this->hitungHargaAman($hargaBeli, $kategori, $params);

        $marginNominal = round($hargaJual - $hargaBeli, 2);
        $marginPersen = $hargaJual > 0 ? round(($marginNominal / $hargaJual) * 100, 1) : -100.0;
        $selisihRekomendasi = round($rekomendasi - $hargaJual, 2);

        if ($hargaBeli > 0 && $hargaJual <= 0) {
            return [
                'status' => 'bahaya',
                'status_label' => 'Harga Kosong',
                'harga_beli' => $hargaBeli,
                'harga_jual' => $hargaJual,
                'margin_nominal' => $marginNominal,
                'margin_persen' => $marginPersen,
                'rekomendasi_harga' => $rekomendasi,
                'selisih_rekomendasi' => $selisihRekomendasi,
                'total_potongan_persen' => $params['total_potongan_persen'],
                'pesan' => 'Harga jual belum diisi padahal produk memiliki HPP modal.',
            ];
        }

        if ($hargaBeli > 0 && $hargaJual <= $hargaBeli) {
            return [
                'status' => 'bahaya',
                'status_label' => 'Potensi Rugi Keras',
                'harga_beli' => $hargaBeli,
                'harga_jual' => $hargaJual,
                'margin_nominal' => $marginNominal,
                'margin_persen' => $marginPersen,
                'rekomendasi_harga' => $rekomendasi,
                'selisih_rekomendasi' => $selisihRekomendasi,
                'total_potongan_persen' => $params['total_potongan_persen'],
                'pesan' => sprintf(
                    'Harga jual (Rp %s) di bawah atau sama dengan HPP modal (Rp %s). Toko pasti mengalami kerugian langsung!',
                    number_format($hargaJual, 0, ',', '.'),
                    number_format($hargaBeli, 0, ',', '.')
                ),
            ];
        }

        if ($hargaBeli > 0 && $hargaJual < $rekomendasi) {
            return [
                'status' => 'peringatan',
                'status_label' => 'Margin Tipis / Berisiko',
                'harga_beli' => $hargaBeli,
                'harga_jual' => $hargaJual,
                'margin_nominal' => $marginNominal,
                'margin_persen' => $marginPersen,
                'rekomendasi_harga' => $rekomendasi,
                'selisih_rekomendasi' => $selisihRekomendasi,
                'total_potongan_persen' => $params['total_potongan_persen'],
                'pesan' => sprintf(
                    'Margin kotor (%s%%) tidak cukup menutup total potensi diskon member (%s%%) + komisi agen (%s%%) + buffer (%s%%). Minimal aman: Rp %s.',
                    $marginPersen,
                    $params['max_mitra_diskon'],
                    $params['komisi_persen'],
                    $params['buffer_biaya_persen'],
                    number_format($rekomendasi, 0, ',', '.')
                ),
            ];
        }

        return [
            'status' => 'aman',
            'status_label' => 'Margin Sehat',
            'harga_beli' => $hargaBeli,
            'harga_jual' => $hargaJual,
            'margin_nominal' => $marginNominal,
            'margin_persen' => $marginPersen,
            'rekomendasi_harga' => $rekomendasi,
            'selisih_rekomendasi' => 0.0,
            'total_potongan_persen' => $params['total_potongan_persen'],
            'pesan' => sprintf('Margin kotor (%s%%) aman untuk seluruh potensi diskon dan komisi.', $marginPersen),
        ];
    }

    /**
     * Evaluasi satu model produk (termasuk variannya bila ada).
     */
    public function evaluasiProduk(Produk $produk, ?array $cachedParams = null): array
    {
        $eval = $this->evaluasiHarga(
            (float) $produk->harga_beli,
            (float) $produk->harga_jual_retail,
            $produk->kategori,
            $cachedParams
        );

        $eval['produk_id'] = $produk->id;
        $eval['nama_produk'] = $produk->nama;
        $eval['sku'] = $produk->skuVariants->first()?->sku ?? $produk->barcode ?? '-';
        $eval['kategori'] = $produk->kategori ?? 'Umum';

        return $eval;
    }

    /**
     * Hitung ringkasan statistik margin langsung via agregat SQL database.
     * Sangat cepat (sub-detik) untuk ribuan bahkan puluhan ribu produk.
     *
     * @return array{
     *     total: int,
     *     total_aman: int,
     *     total_peringatan: int,
     *     total_bahaya: int,
     *     persen_sehat: float
     * }
     */
    public function getRingkasanMetrik(array $filters = []): array
    {
        $params = $this->getParameterMargin($filters['kategori'] ?? null);
        $faktor = max(0.1, (100 - $params['total_potongan_persen']) / 100);

        $query = Produk::query()->where('is_active', true);

        if (! empty($filters['kategori'])) {
            $query->where('kategori', $filters['kategori']);
        }

        if (! empty($filters['search'])) {
            $keyword = '%'.$filters['search'].'%';
            $query->where(function ($q) use ($keyword) {
                $q->where('nama', 'like', $keyword)
                    ->orWhere('barcode', 'like', $keyword)
                    ->orWhere('barcode_alt', 'like', $keyword)
                    ->orWhere('kode_lama', 'like', $keyword)
                    ->orWhereHas('skuVariants', function ($sub) use ($keyword) {
                        $sub->where('sku', 'like', $keyword)
                            ->orWhere('barcode', 'like', $keyword);
                    });
            });
        }

        $stats = $query->selectRaw('
            COUNT(*) as total,
            SUM(CASE WHEN (harga_beli > 0 AND (harga_jual_retail <= harga_beli OR harga_jual_retail IS NULL OR harga_jual_retail = 0)) THEN 1 ELSE 0 END) as total_bahaya,
            SUM(CASE WHEN (harga_beli > 0 AND harga_jual_retail > harga_beli AND harga_jual_retail < (harga_beli / ?)) THEN 1 ELSE 0 END) as total_peringatan,
            SUM(CASE WHEN (harga_beli <= 0 OR harga_jual_retail >= (harga_beli / ?)) THEN 1 ELSE 0 END) as total_aman
        ', [$faktor, $faktor])->first();

        $total = (int) ($stats->total ?? 0);
        $totalAman = (int) ($stats->total_aman ?? 0);
        $totalPeringatan = (int) ($stats->total_peringatan ?? 0);
        $totalBahaya = (int) ($stats->total_bahaya ?? 0);
        $persenSehat = $total > 0 ? round(($totalAman / $total) * 100, 1) : 100.0;

        return [
            'total' => $total,
            'total_aman' => $totalAman,
            'total_peringatan' => $totalPeringatan,
            'total_bahaya' => $totalBahaya,
            'persen_sehat' => $persenSehat,
        ];
    }

    /**
     * Ambil data produk terpilih dengan paginasi level database (efisien memory).
     */
    public function getPagedProduk(array $filters = [], int $perPage = 25, int $page = 1)
    {
        $params = $this->getParameterMargin($filters['kategori'] ?? null);
        $faktor = max(0.1, (100 - $params['total_potongan_persen']) / 100);

        $query = Produk::query()->where('is_active', true);

        if (! empty($filters['kategori'])) {
            $query->where('kategori', $filters['kategori']);
        }

        if (! empty($filters['search'])) {
            $keyword = '%'.$filters['search'].'%';
            $query->where(function ($q) use ($keyword) {
                $q->where('nama', 'like', $keyword)
                    ->orWhere('barcode', 'like', $keyword)
                    ->orWhere('barcode_alt', 'like', $keyword)
                    ->orWhere('kode_lama', 'like', $keyword)
                    ->orWhereHas('skuVariants', function ($sub) use ($keyword) {
                        $sub->where('sku', 'like', $keyword)
                            ->orWhere('barcode', 'like', $keyword);
                    });
            });
        }

        if (! empty($filters['status']) && $filters['status'] !== 'semua') {
            if ($filters['status'] === 'bahaya') {
                $query->where('harga_beli', '>', 0)
                    ->where(function ($q) {
                        $q->whereRaw('harga_jual_retail <= harga_beli')
                            ->orWhereNull('harga_jual_retail')
                            ->orWhere('harga_jual_retail', '<=', 0);
                    });
            } elseif ($filters['status'] === 'peringatan') {
                $query->where('harga_beli', '>', 0)
                    ->whereRaw('harga_jual_retail > harga_beli')
                    ->whereRaw('harga_jual_retail < (harga_beli / ?)', [$faktor]);
            } elseif ($filters['status'] === 'aman') {
                $query->where(function ($q) use ($faktor) {
                    $q->where('harga_beli', '<=', 0)
                        ->orWhereNull('harga_beli')
                        ->orWhereRaw('harga_jual_retail >= (harga_beli / ?)', [$faktor]);
                });
            }
        }

        $paged = $query->with('skuVariants')
            ->orderBy('nama')
            ->paginate($perPage, ['*'], 'page', $page);

        // Transformasi hanya 25 row yang ditampilkan di memory
        $items = $paged->getCollection()->map(function ($produk) use ($params) {
            return $this->evaluasiProduk($produk, $params);
        });

        $paged->setCollection($items);

        return $paged;
    }

    /**
     * Scan produk dengan kompatibilitas backward (mengembalikan ringkasan & items).
     *
     * @return array{
     *     total: int,
     *     total_aman: int,
     *     total_peringatan: int,
     *     total_bahaya: int,
     *     persen_sehat: float,
     *     items: Collection
     * }
     */
    public function scanProduk(array $filters = []): array
    {
        $ringkasan = $this->getRingkasanMetrik($filters);
        $paged = $this->getPagedProduk($filters, $filters['limit'] ?? 25, $filters['page'] ?? 1);

        return array_merge($ringkasan, [
            'items' => $paged->getCollection(),
            'paginator' => $paged,
        ]);
    }

    /**
     * Sesuaikan harga jual retail produk ke rekomendasi harga aman.
     */
    public function terapkanRekomendasi(int $produkId, ?array $cachedParams = null): bool
    {
        $produk = Produk::find($produkId);
        if (! $produk) {
            return false;
        }

        $rekomendasi = $this->hitungHargaAman((float) $produk->harga_beli, $produk->kategori, $cachedParams);
        if ($rekomendasi <= 0 || $rekomendasi === (float) $produk->harga_jual_retail) {
            return false;
        }

        $marginPersen = round((($rekomendasi - (float) $produk->harga_beli) / $rekomendasi) * 100, 2);

        $produk->update([
            'harga_jual_retail' => $rekomendasi,
            'margin_persen' => $marginPersen,
        ]);

        // Sinkronkan juga ke master tier retail & SKU variant
        HargaTier::updateOrCreate(
            ['produk_id' => $produk->id, 'tipe_konsumen' => 'retail'],
            ['nominal_tetap' => $rekomendasi, 'harga' => $rekomendasi]
        );

        SkuVariant::where('produk_id', $produk->id)->update([
            'harga_jual_retail' => $rekomendasi,
        ]);

        return true;
    }

    /**
     * Auto-fix seluruh produk berisiko rugi/peringatan menggunakan chunk database.
     * Aman dari kehabisan memori atau timeout PHP.
     */
    public function terapkanSemuaBerisiko(?string $kategori = null): int
    {
        $params = $this->getParameterMargin($kategori);
        $faktor = max(0.1, (100 - $params['total_potongan_persen']) / 100);

        $query = Produk::where('is_active', true)
            ->where('harga_beli', '>', 0)
            ->where(function ($q) use ($faktor) {
                $q->whereRaw('harga_jual_retail < (harga_beli / ?)', [$faktor])
                    ->orWhereRaw('harga_jual_retail <= harga_beli')
                    ->orWhereNull('harga_jual_retail')
                    ->orWhere('harga_jual_retail', '<=', 0);
            });

        if ($kategori) {
            $query->where('kategori', $kategori);
        }

        $count = 0;
        // Gunakan chunkById agar memory footprint tetap minimal (< 5 MB)
        $query->chunkById(100, function ($produks) use (&$count, $params) {
            foreach ($produks as $produk) {
                if ($this->terapkanRekomendasi((int) $produk->id, $params)) {
                    $count++;
                }
            }
        });

        return $count;
    }

    /**
     * Bulk penyesuaian harga seluruh produk terpilih ke harga rekomendasi.
     */
    public function bulkTerapkanRekomendasi(array $produkIds): int
    {
        $sukses = 0;
        foreach ($produkIds as $id) {
            if ($this->terapkanRekomendasi((int) $id)) {
                $sukses++;
            }
        }

        return $sukses;
    }
}
