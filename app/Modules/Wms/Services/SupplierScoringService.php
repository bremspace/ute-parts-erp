<?php

namespace App\Modules\Wms\Services;

use App\Modules\Wms\Models\PurchaseOrder;
use App\Modules\Wms\Models\Supplier;
use App\Modules\Wms\Models\SupplierScore;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * [F3-2 / G-11] Supplier Scoring Service.
 *
 * Formula: total_score = (on_time_percent * 0.4) + ((100 - quality_return_percent) * 0.3) + (normalisasi_harga * 0.3)
 * Normalisasi harga: harga terendah = 100, harga tertinggi = 0, linear interpolation.
 */
class SupplierScoringService
{
    /**
     * Hitung skor untuk semua supplier aktif dalam periode.
     *
     * [B-15d] Batching: PO periode di-fetch SATU kali untuk seluruh supplier
     * (`with('items')` eager-load — sebelumnya `hitungAvgHarga` lazy-load
     * `->items` per PO = N+1 di dalam N supplier). `Supplier::findOrFail()`
     * yang redundan per supplier (supplier sudah ada di loop) dihapus.
     * Rumus skor & angka TIDAK berubah.
     *
     * @param  string  $periode  format YYYY-MM
     * @return array{diproses: int, periode: string}
     */
    public function hitungSemua(string $periode, ?int $cabangId = null): array
    {
        $suppliers = Supplier::query()
            ->where('is_active', true)
            ->when($cabangId, fn ($q) => $q->where('cabang_id', $cabangId))
            ->get();

        $poPerSupplier = $this->poPeriodeGrouped($periode, $cabangId, $suppliers->modelKeys());

        $diproses = 0;
        foreach ($suppliers as $supplier) {
            $this->hitungSkorDari($supplier, $periode, $cabangId, $poPerSupplier[(int) $supplier->id] ?? collect());
            $diproses++;
        }

        return ['diproses' => $diproses, 'periode' => $periode];
    }

    /**
     * Hitung skor untuk satu supplier dalam periode.
     *
     * @return array{on_time_percent: float, quality_return_percent: float, avg_harga: float, total_score: float}
     */
    public function hitungSkor(int $supplierId, string $periode, ?int $cabangId = null): array
    {
        // Kontrak publik: supplier tidak ada → tetap 404 (tidak diubah).
        $supplier = Supplier::findOrFail($supplierId);

        $po = $this->poPeriodeGrouped($periode, $cabangId, [$supplierId])[$supplierId] ?? collect();

        return $this->hitungSkorDari($supplier, $periode, $cabangId, $po);
    }

    /**
     * Hitung + upsert skor dari sekumpulan PO (sudah ter-scope supplier+periode).
     * Ekstraksi ini yang dipakai hitungSkor() maupun hitungSemua() supaya
     * angka keduanya dijamin identik.
     *
     * @param  Collection<int, PurchaseOrder>  $po
     * @return array{on_time_percent: float, quality_return_percent: float, avg_harga: float, total_score: float}
     */
    protected function hitungSkorDari(Supplier $supplier, string $periode, ?int $cabangId, Collection $po): array
    {
        $supplierId = (int) $supplier->id;

        // On-time: PO yang diterima tepat waktu vs jatuh tempo
        $onTimeStats = $this->hitungOnTimeDari($po);

        // Quality return: % PO dengan return
        $qualityStats = $this->hitungQualityReturnDari($po);

        // Average price
        $avgHarga = $this->hitungAvgHargaDari($po);

        // Normalisasi harga: perlu rata-rata harga semua supplier
        $normalisasiHarga = 0;

        // Hitung total score
        $onTimePercent = $onTimeStats['total'] > 0
            ? round(($onTimeStats['on_time'] / $onTimeStats['total']) * 100, 2)
            : 0;

        $qualityPercent = $qualityStats['total'] > 0
            ? round(($qualityStats['returned'] / $qualityStats['total']) * 100, 2)
            : 0;

        // Normalisasi harga akan dihitung setelah semua supplier diproses
        // Untuk single supplier, gunakan avg_harga secara langsung
        $totalScore = round(
            ($onTimePercent * 0.4) +
            ((100 - $qualityPercent) * 0.3) +
            ($normalisasiHarga * 0.3),
            2
        );

        // Upsert supplier score
        SupplierScore::updateOrCreate(
            [
                'supplier_id' => $supplierId,
                'periode' => $periode,
                'cabang_id' => $cabangId,
            ],
            [
                'on_time_percent' => $onTimePercent,
                'quality_return_percent' => $qualityPercent,
                'avg_harga' => $avgHarga,
                'total_score' => $totalScore,
                'cabang_id' => $cabangId,
            ]
        );

        return [
            'on_time_percent' => $onTimePercent,
            'quality_return_percent' => $qualityPercent,
            'avg_harga' => $avgHarga,
            'total_score' => $totalScore,
        ];
    }

    /**
     * [B-15d] PO satu periode untuk N supplier, di-group per supplier_id.
     *
     * Jendela tanggal SAMA persis dgn tiga helper lama (awal bulan 00:00:00 →
     * akhir bulan 23:59:59) dan `with('items')` eager-load supaya tidak ada
     * lazy-load per PO. Tanpa filter status/status di SQL: ketiga metrik
     * (on-time, quality, harga) punya filter berbeda dan digabung di PHP —
     * hasil identik (lihat catatan paritas di tiap helper "Dari").
     *
     * @param  array<int, int>  $supplierIds
     * @return Collection<int, Collection<int, PurchaseOrder>>
     */
    protected function poPeriodeGrouped(string $periode, ?int $cabangId, array $supplierIds): Collection
    {
        if ($supplierIds === []) {
            return collect();
        }

        /** @var Collection<int, PurchaseOrder> $po */
        $po = $this->poPeriodeQuery($periode, $cabangId, $supplierIds)->get();

        /** @var Collection<int, Collection<int, PurchaseOrder>> $grouped */
        $grouped = $po->groupBy(fn (PurchaseOrder $p): int => (int) $p->supplier_id);

        return $grouped;
    }

    /**
     * Query PO periode (created_at) utk daftar supplier. `with('items')` eager-load.
     *
     * @param  array<int, int>  $supplierIds
     */
    protected function poPeriodeQuery(string $periode, ?int $cabangId, array $supplierIds): Builder
    {
        $startDate = $periode.'-01 00:00:00';
        $endDate = date('Y-m-t 23:59:59', strtotime($startDate));

        return PurchaseOrder::query()
            ->whereIn('supplier_id', $supplierIds)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->when($cabangId, fn ($q) => $q->where('cabang_id', $cabangId))
            ->with('items');
    }

    /**
     * Hitung on-time percentage dari PO yg SUDAH ter-scope (varian in-memory).
     *
     * PARITAS: SQL lama `where('status','selesai')` → `=== 'selesai'` (kolom
     * enum NOT NULL, jadi NULL tidak mungkin). Filter jatuh tempo tetap
     * operator native `>=` atas dua objek Carbon persis seperti
     * `Collection::where('jatuh_tempo', '>=', now()->subDays(30))` — batasnya
     * dihitung SEKALI di luar loop, sama seperti argumen Collection::where.
     *
     * @param  Collection<int, PurchaseOrder>  $po
     * @return array{total: int, on_time: int}
     */
    protected function hitungOnTimeDari(Collection $po): array
    {
        $selesai = $po->filter(fn (PurchaseOrder $p): bool => $p->getAttribute('status') === 'selesai');
        $batas = now()->subDays(30);

        return [
            'total' => $selesai->count(),
            'on_time' => $selesai->filter(fn (PurchaseOrder $p): bool => $p->jatuh_tempo >= $batas)->count(),
        ];
    }

    /**
     * Hitung quality return percentage dari PO yg SUDAH ter-scope.
     *
     * PARITAS: SQL lama `where('status','!=','draft')` → PHP `!== null && !== 'draft'`
     * (SQL `!=` juga membuang NULL, kolom enum NOT NULL). `ditolak` = `=== 'ditolak'`.
     *
     * @param  Collection<int, PurchaseOrder>  $po
     * @return array{total: int, returned: int}
     */
    protected function hitungQualityReturnDari(Collection $po): array
    {
        // `getAttribute()` (bukan properti) supaya guard NULL tetap dievaluasi:
        // SQL `!=` membuang NULL, jadi NULL harus ikut terbuang di PHP.
        $bukanDraft = $po->filter(function (PurchaseOrder $p): bool {
            $status = $p->getAttribute('status');

            return $status !== null && $status !== 'draft';
        });

        return [
            'total' => $bukanDraft->count(),
            'returned' => $bukanDraft->filter(fn (PurchaseOrder $p): bool => $p->getAttribute('status') === 'ditolak')->count(),
        ];
    }

    /**
     * Rata-rata harga_beli item dari PO yg SUDAH ter-scope.
     *
     * PARITAS: SQL lama `whereHas('items')` → filter PHP `$po->items->isNotEmpty()`
     * (ekuivalen: relasi items kosong = tidak lolos EXISTS), `->filter()` buang
     * nilai falsy, `round(avg(), 2)` sama.
     *
     * @param  Collection<int, PurchaseOrder>  $po
     */
    protected function hitungAvgHargaDari(Collection $po): float
    {
        $hargas = $po
            ->filter(fn (PurchaseOrder $p) => $p->items->isNotEmpty())
            ->flatMap(fn (PurchaseOrder $p) => $p->items->pluck('harga_beli'))
            ->filter();

        return $hargas->count() > 0 ? round($hargas->avg(), 2) : 0;
    }

    /**
     * Dapatkan skor supplier untuk ditampilkan di form PO.
     */
    public function getSkorSupplier(int $supplierId, ?int $cabangId = null): ?SupplierScore
    {
        return SupplierScore::where('supplier_id', $supplierId)
            ->when($cabangId, fn ($q) => $q->where('cabang_id', $cabangId))
            ->orderBy('periode', 'desc')
            ->first();
    }
}
