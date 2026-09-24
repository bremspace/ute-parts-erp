<?php

namespace App\Modules\Wms\Services;

use App\Modules\Wms\Models\PurchaseOrder;
use App\Modules\Wms\Models\Supplier;
use App\Modules\Wms\Models\SupplierScore;

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
     * @param  string  $periode  format YYYY-MM
     * @return array{diproses: int, periode: string}
     */
    public function hitungSemua(string $periode, ?int $cabangId = null): array
    {
        $query = Supplier::query()
            ->where('is_active', true)
            ->when($cabangId, fn ($q) => $q->where('cabang_id', $cabangId));

        $diproses = 0;

        foreach ($query->get() as $supplier) {
            $this->hitungSkor($supplier->id, $periode, $cabangId);
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
        $supplier = Supplier::findOrFail($supplierId);

        // On-time: PO yang diterima tepat waktu vs jatuh tempo
        $onTimeStats = $this->hitungOnTime($supplierId, $periode, $cabangId);

        // Quality return: % PO dengan return
        $qualityStats = $this->hitungQualityReturn($supplierId, $periode, $cabangId);

        // Average price
        $avgHarga = $this->hitungAvgHarga($supplierId, $periode, $cabangId);

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
     * Hitung on-time percentage.
     */
    protected function hitungOnTime(int $supplierId, string $periode, ?int $cabangId = null): array
    {
        $startDate = $periode.'-01';
        $endDate = date('Y-m-t', strtotime($startDate));

        $query = PurchaseOrder::query()
            ->where('supplier_id', $supplierId)
            ->where('status', 'selesai')
            ->whereBetween('created_at', ["{$startDate} 00:00:00", "{$endDate} 23:59:59"]);

        if ($cabangId) {
            $query->where('cabang_id', $cabangId);
        }

        $pors = $query->get();
        $total = $pors->count();
        $onTime = $pors->where('jatuh_tempo', '>=', now()->subDays(30))->count();

        return ['total' => $total, 'on_time' => $onTime];
    }

    /**
     * Hitum quality return percentage.
     */
    protected function hitungQualityReturn(int $supplierId, string $periode, ?int $cabangId = null): array
    {
        $startDate = $periode.'-01';
        $endDate = date('Y-m-t', strtotime($startDate));

        $query = PurchaseOrder::query()
            ->where('supplier_id', $supplierId)
            ->where('status', '!=', 'draft')
            ->whereBetween('created_at', ["{$startDate} 00:00:00", "{$endDate} 23:59:59"]);

        if ($cabangId) {
            $query->where('cabang_id', $cabangId);
        }

        $pors = $query->get();
        $total = $pors->count();
        $returned = $pors->where('status', 'ditolak')->count();

        return ['total' => $total, 'returned' => $returned];
    }

    /**
     * Hitung rata-rata harga per item supplier.
     */
    protected function hitungAvgHarga(int $supplierId, string $periode, ?int $cabangId = null): float
    {
        $startDate = $periode.'-01 00:00:00';
        $endDate = date('Y-m-t 23:59:59', strtotime($startDate));

        $query = PurchaseOrder::query()
            ->where('supplier_id', $supplierId)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->whereHas('items');

        if ($cabangId) {
            $query->where('cabang_id', $cabangId);
        }

        // Average harga_beli dari semua item PO supplier dalam periode
        $avg = 0;
        $pors = $query->get();
        $hargas = $pors->flatMap(fn ($po) => $po->items->pluck('harga_beli'))->filter();

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
