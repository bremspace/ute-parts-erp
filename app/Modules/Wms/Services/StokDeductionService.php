<?php

namespace App\Modules\Wms\Services;

use App\Modules\Wms\Models\Gudang;
use App\Modules\Wms\Models\StockMutationLog;
use App\Modules\Wms\Models\StokItem;
use App\Modules\Wms\Models\StokLog;
use App\Modules\Wms\Models\StokTransfer;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Reusable stock deduction + audit log (PRD StokLog).
 * Dipakai POS, transfer, servis, dan pembayaran marketplace lunas.
 */
class StokDeductionService
{
    /**
     * Kurangi stok produk di sebuah gudang, catat StokLog + StockMutationLog.
     *
     * [B-03/P1-3] Resolusi baris stok memakai SATU kriteria (lihat cariBarisStok):
     * praseleksi multi-gudang dan eksekusi tidak lagi beda kriteria.
     *
     * [B-02/P0-1] KEBIJAKAN STOK: default `false` = TOLAK stok kurang / stok
     * negatif. Backorder di POS dibatalkan owner — `PosKasir` tidak lagi
     * mengirim `true`, dan `PosKasir::validasiStokKeranjang()` menolak stok
     * 0/kurang sebelum transaksi dibuat (pesan Indonesia, tanpa sisa efek).
     * Parameter `$izinkanNegatif` TETAP ADA di signature (tidak dihapus —
     * pemanggil lain mengisinya lewat named argument) dan tetap default `false`
     * supaya tidak ada jalur pintas diam-diam ke stok negatif.
     *
     * @param  bool  $izinkanNegatif  true → izinkan stok negatif (backorder).
     *                                HANYA untuk jalur yang kebijakannya masih
     *                                memakai backorder; jalur penjualan POS
     *                                (Livewire) sudah tidak mengirimnya lagi.
     *                                Bila false dan baris stok belum ada →
     *                                exception, TIDAK dibuat baris stok kosong.
     *
     * @throws \Exception jika stok tidak mencukupi (ketika $izinkanNegatif = false)
     */
    public function kurangi(
        int $produkId,
        ?int $skuVariantId,
        int $gudangId,
        int $qty,
        string $jenis,
        string $referensiTipe,
        ?int $referensiId,
        ?int $userId,
        ?string $catatan = null,
        bool $izinkanNegatif = false
    ): StokItem {
        if ($qty <= 0) {
            throw new \Exception('Kuantitas stok harus > 0');
        }

        $stok = $this->cariBarisStok($produkId, $skuVariantId, $gudangId, lock: true);

        if (! $stok) {
            if (! $izinkanNegatif) {
                throw new \Exception("Stok tidak mencukupi (produk ID {$produkId}, tersedia: 0)");
            }

            // Baris stok belum ada → buat dgn saldo 0. Log 0 → (-qty) jadi akurat
            // dan StokItem::sum tetap sinkron dgn StokLog / StockMutationLog
            // (dilarang menulis log tanpa baris stok nyata).
            $stok = StokItem::create([
                'produk_id' => $produkId,
                'sku_variant_id' => $skuVariantId,
                'gudang_id' => $gudangId,
                'jumlah' => 0,
                'jumlah_minimum' => 0,
            ]);
        }

        if (! $izinkanNegatif && $stok->jumlah < $qty) {
            throw new \Exception("Stok tidak mencukupi (produk ID {$produkId}, tersedia: {$stok->jumlah})");
        }

        // [T-13] Guard pending transfer (status draft): qty yang menunggu kirim
        // di gudang asal tidak boleh dipakai transaksi lain (POS/servis/marketplace).
        // Backorder hanya diizinkan bila stok mentah saja memang tidak cukup —
        // stok tersedia (jumlah >= qty) tetap wajib menghormati kunci transfer.
        $locked = StokTransfer::pendingLockedFor($stok);
        if ($locked > 0 && $stok->jumlah >= $qty && $stok->jumlah - $locked < $qty) {
            throw new \Exception(
                "Stok tidak mencukupi karena {$locked} unit sedang terkunci transfer pending (produk ID {$produkId}, tersedia bebas: ".max(0, $stok->jumlah - $locked).')'
            );
        }

        $sebelum = $stok->jumlah;
        $setelah = $sebelum - $qty;
        $stok->update(['jumlah' => $setelah]);

        // [B-03] sku_variant_id di log = varian baris yang benar-benar terpotong
        // (bila permintaan tanpa varian, resolusi jatuh ke baris kanonik).
        StokLog::create([
            'gudang_id' => $gudangId,
            'produk_id' => $produkId,
            'sku_variant_id' => $stok->sku_variant_id,
            'user_id' => $userId,
            'jenis' => $jenis,
            'referensi_tipe' => $referensiTipe,
            'referensi_id' => $referensiId,
            'jumlah_sebelum' => $sebelum,
            'perubahan' => -$qty,
            'jumlah_setelah' => $setelah,
            'catatan' => $catatan,
        ]);

        // [T-26] SOT mutation log
        // [B-10f] user_id WAJIB ikut: kolomnya sudah ada (migrasi
        // add_user_id_to_stock_mutation_log, B-10b) tapi belum diisi writer
        // kanonik ini → mutasi stok tidak bisa dibuktikan pelakunya.
        StockMutationLog::create([
            'produk_id' => $produkId,
            'sku_variant_id' => $stok->sku_variant_id,
            'gudang_id' => $gudangId,
            'user_id' => $userId,
            'delta' => -$qty,
            'sumber' => $jenis,
            'referensi_tipe' => $referensiTipe,
            'referensi_id' => $referensiId,
            'terjadi_at' => now(),
        ]);

        return $stok;
    }

    /**
     * [B-03/P1-3] Satu kriteria resolusi baris stok utk seluruh jalur deduksi.
     *
     * - varian terisi → baris varian eksak;
     * - varian null → baris tanpa varian bila ada; kalau tidak ada → baris
     *   kanonik utk produk+gudang (jumlah terbesar, id terkecil sbg tie-break),
     *   sehingga gauge POS (agregat produk) & potong stok tidak lagi beda angka.
     */
    private function cariBarisStok(int $produkId, ?int $skuVariantId, int $gudangId, bool $lock): ?StokItem
    {
        if ($skuVariantId !== null) {
            $query = StokItem::where('produk_id', $produkId)
                ->where('sku_variant_id', $skuVariantId)
                ->where('gudang_id', $gudangId);

            return $lock ? $query->lockForUpdate()->first() : $query->first();
        }

        $query = StokItem::where('produk_id', $produkId)->where('gudang_id', $gudangId);
        if ($lock) {
            $query->lockForUpdate();
        }

        $tanpaVarian = (clone $query)->whereNull('sku_variant_id')->orderBy('id')->first();
        if ($tanpaVarian) {
            return $tanpaVarian;
        }

        return $query->orderByDesc('jumlah')->orderBy('id')->first();
    }

    /**
     * Versi kumpulan dari cariBarisStok() utk praseleksi multi-gudang —
     * memilih SATU baris kanonik per gudang dgn kriteria identik.
     *
     * @param  Collection<int, StokItem>  $rows  baris stok milik 1 gudang
     */
    private function barisKanonik(Collection $rows, ?int $skuVariantId): ?StokItem
    {
        if ($skuVariantId !== null) {
            foreach ($rows as $row) {
                if ((int) $row->sku_variant_id === $skuVariantId) {
                    return $row;
                }
            }

            return null;
        }

        $tanpaVarian = $rows->first(fn (StokItem $row) => $row->sku_variant_id === null);
        if ($tanpaVarian) {
            return $tanpaVarian;
        }

        // Varian default = jumlah terbesar (tie-break: id terkecil) — sama dgn cariBarisStok()
        $terbesar = (int) $rows->max('jumlah');

        return $rows
            ->filter(fn (StokItem $row) => (int) $row->jumlah === $terbesar)
            ->sortBy('id')
            ->first();
    }

    /**
     * Kurangi stok dari gudang yang memiliki cukup — fallback multi-gudang per cabang.
     * Untuk order marketplace: pecah pengurangan ke beberapa gudang bila perlu.
     *
     * @return array list StokLog terbuat
     */
    public function kurangiDariGudangTersedia(
        int $produkId,
        ?int $skuVariantId,
        int $qty,
        int $cabangId,
        string $jenis,
        string $referensiTipe,
        ?int $referensiId,
        ?int $userId,
        ?string $catatan = null
    ): array {
        $gudangIds = Gudang::where('cabang_id', $cabangId)->pluck('id');
        $tersisa = $qty;
        $logs = [];

        DB::transaction(function () use ($produkId, $skuVariantId, $jenis, $referensiTipe, $referensiId, $userId, $catatan, $gudangIds, &$tersisa, &$logs) {
            // [B-03/P1-3] Praseleksi PAKAI kriteria identik dgn eksekusi (cariBarisStok/
            // barisKanonik): per gudang hanya 1 baris kanonik — dulu praseleksi mengabaikan
            // varian lalu eksekusi memakai varian eksak → "Stok tidak mencukupi" palsu.
            // Prioritaskan gudang dengan stok cukup paling besar terlebih dahulu.
            $stoks = StokItem::where('produk_id', $produkId)
                ->whereIn('gudang_id', $gudangIds)
                ->get()
                ->groupBy('gudang_id')
                ->map(fn ($rows) => $this->barisKanonik($rows, $skuVariantId))
                ->filter()
                ->filter(fn (StokItem $stok) => (int) $stok->jumlah > 0)
                ->sortByDesc('jumlah')
                ->values();

            foreach ($stoks as $stok) {
                if ($tersisa <= 0) {
                    break;
                }

                $ambil = min($stok->jumlah, $tersisa);
                $log = $this->kurangi(
                    $produkId,
                    $stok->sku_variant_id,
                    $stok->gudang_id,
                    $ambil,
                    $jenis,
                    $referensiTipe,
                    $referensiId,
                    $userId,
                    $catatan
                );
                $logs[] = $log;
                $tersisa -= $ambil;
            }

            if ($tersisa > 0) {
                throw new \Exception("Stok tidak mencukupi di cabang (kurang {$tersisa} unit)");
            }
        });

        return $logs;
    }
}
