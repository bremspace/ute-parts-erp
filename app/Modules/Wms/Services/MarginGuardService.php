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
    public function hitungHargaAman(float $hargaBeli, ?string $kategori = null): float
    {
        if ($hargaBeli <= 0) {
            return 0.0;
        }

        $params = $this->getParameterMargin($kategori);
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
    public function evaluasiHarga(float $hargaBeli, float $hargaJual, ?string $kategori = null): array
    {
        $params = $this->getParameterMargin($kategori);
        $rekomendasi = $this->hitungHargaAman($hargaBeli, $kategori);

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
    public function evaluasiProduk(Produk $produk): array
    {
        $eval = $this->evaluasiHarga(
            (float) $produk->harga_beli,
            (float) $produk->harga_jual_retail,
            $produk->kategori
        );

        $eval['produk_id'] = $produk->id;
        $eval['nama_produk'] = $produk->nama;
        $eval['sku'] = $produk->sku ?? $produk->barcode ?? '-';
        $eval['kategori'] = $produk->kategori ?? 'Umum';

        return $eval;
    }

    /**
     * Scan seluruh master produk aktif dan kelompokkan berdasarkan status margin.
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
        $query = Produk::query()->where('is_active', true);

        if (! empty($filters['kategori'])) {
            $query->where('kategori', $filters['kategori']);
        }

        if (! empty($filters['search'])) {
            $keyword = '%'.$filters['search'].'%';
            $query->where(function ($q) use ($keyword) {
                $q->where('nama', 'like', $keyword)
                    ->orWhere('barcode', 'like', $keyword)
                    ->orWhereHas('skuVariants', function ($sub) use ($keyword) {
                        $sub->where('sku', 'like', $keyword)
                            ->orWhere('barcode', 'like', $keyword);
                    });
            });
        }

        $semuaProduk = $query->with('skuVariants')->orderBy('nama')->get();

        $items = collect();
        $totalAman = 0;
        $totalPeringatan = 0;
        $totalBahaya = 0;

        foreach ($semuaProduk as $p) {
            $eval = $this->evaluasiProduk($p);

            if ($eval['status'] === 'aman') {
                $totalAman++;
            } elseif ($eval['status'] === 'peringatan') {
                $totalPeringatan++;
            } else {
                $totalBahaya++;
            }

            // Filter status bila diminta
            if (! empty($filters['status']) && $filters['status'] !== 'semua') {
                if ($eval['status'] !== $filters['status']) {
                    continue;
                }
            }

            $items->push($eval);
        }

        $total = $semuaProduk->count();
        $persenSehat = $total > 0 ? round(($totalAman / $total) * 100, 1) : 100.0;

        return [
            'total' => $total,
            'total_aman' => $totalAman,
            'total_peringatan' => $totalPeringatan,
            'total_bahaya' => $totalBahaya,
            'persen_sehat' => $persenSehat,
            'items' => $items,
        ];
    }

    /**
     * Sesuaikan harga jual retail produk ke rekomendasi harga aman.
     */
    public function terapkanRekomendasi(int $produkId): bool
    {
        $produk = Produk::find($produkId);
        if (! $produk) {
            return false;
        }

        $rekomendasi = $this->hitungHargaAman((float) $produk->harga_beli, $produk->kategori);
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
