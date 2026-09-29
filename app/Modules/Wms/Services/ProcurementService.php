<?php

namespace App\Modules\Wms\Services;

use App\Modules\Wms\Models\Produk;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Service untuk rekomendasi pengadaan stok UteParts.
 * Mengkombinasikan:
 * - Analisis ABC (Kelas A: Fast Moving, Kelas B & C: Slow/Medium Moving)
 * - ROP (Reorder Point untuk Kelas A)
 * - Min-Max (Level stok minimum & maksimum untuk Kelas B & C)
 * - Modified JIT (On-Demand: Abaikan pengecekan stok otomatis, pesan hanya jika ada pesanan konsumen)
 */
class ProcurementService
{
    /**
     * Ambil rekomendasi pengadaan untuk seluruh atau per cabang.
     * Menggunakan optimized aggregate subqueries agar performan untuk ribuan SKU.
     *
     * @param  int|null  $cabangId  Cabang ID untuk membatasi stok gudang dan demand
     * @param  array  $filters  Filter opsional: abc_class, is_ondemand, only_reorder, search, kategori
     * @return Collection<int, array>
     */
    public function getProcurementRecommendations(?int $cabangId = null, array $filters = []): Collection
    {
        // 1. Subquery agregasi stok aktual per produk
        $stockSub = DB::table('stok_items')
            ->join('gudang', 'gudang.id', '=', 'stok_items.gudang_id')
            ->select('stok_items.produk_id', DB::raw('SUM(stok_items.jumlah) as total_stock'))
            ->when($cabangId, fn ($q) => $q->where('gudang.cabang_id', $cabangId))
            ->groupBy('stok_items.produk_id');

        // 2. Subquery demand konsumen aktif (tiket servis dalam pengerjaan/estimasi)
        $activeServisStatuses = [
            'diajukan_online', 'diterima', 'diagnosa', 'menunggu_approval',
            'disetujui', 'dikerjakan',
        ];

        $servisDemandSub = DB::table('servis_sparepart')
            ->join('tiket_servis', 'tiket_servis.id', '=', 'servis_sparepart.tiket_servis_id')
            ->whereIn('tiket_servis.status', $activeServisStatuses)
            ->when($cabangId, fn ($q) => $q->where('tiket_servis.cabang_id', $cabangId))
            ->select('servis_sparepart.produk_id', DB::raw('SUM(servis_sparepart.jumlah) as demand_qty'))
            ->groupBy('servis_sparepart.produk_id');

        // 3. Query produk dengan JOIN efisien
        $query = DB::table('produk')
            ->leftJoinSub($stockSub, 'stok_agg', 'stok_agg.produk_id', '=', 'produk.id')
            ->leftJoinSub($servisDemandSub, 'demand_agg', 'demand_agg.produk_id', '=', 'produk.id')
            ->select([
                'produk.id',
                'produk.nama',
                'produk.slug',
                'produk.barcode',
                'produk.kategori',
                'produk.satuan',
                'produk.harga_beli',
                'produk.harga_jual_retail',
                'produk.abc_class',
                'produk.reorder_point',
                'produk.min_stock',
                'produk.max_stock',
                'produk.is_ondemand',
                DB::raw('COALESCE(stok_agg.total_stock, 0) as current_stock'),
                DB::raw('COALESCE(demand_agg.demand_qty, 0) as pending_demand'),
            ])
            ->where('produk.is_active', true);

        // Filter: abc_class
        if (! empty($filters['abc_class'])) {
            $query->where('produk.abc_class', strtoupper($filters['abc_class']));
        }

        // Filter: is_ondemand
        if (isset($filters['is_ondemand']) && $filters['is_ondemand'] !== '') {
            $query->where('produk.is_ondemand', (bool) $filters['is_ondemand']);
        }

        // Filter: kategori
        if (! empty($filters['kategori'])) {
            $query->where('produk.kategori', $filters['kategori']);
        }

        // Filter: search nama/barcode
        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('produk.nama', 'like', "%{$search}%")
                    ->orWhere('produk.barcode', 'like', "%{$search}%")
                    ->orWhere('produk.slug', 'like', "%{$search}%");
            });
        }

        $items = $query->orderBy('produk.nama')->get();

        // 4. Hitung rekomendasi pengadaan per SKU sesuai formula
        $results = $items->map(function ($item) {
            $currentStock = (int) $item->current_stock;
            $pendingDemand = (int) $item->pending_demand;
            $isOndemand = (bool) $item->is_ondemand;
            $abcClass = $item->abc_class ?: 'B';
            $reorderPoint = $item->reorder_point !== null ? (int) $item->reorder_point : null;
            $minStock = $item->min_stock !== null ? (int) $item->min_stock : null;
            $maxStock = $item->max_stock !== null ? (int) $item->max_stock : null;
            $hargaBeli = (float) $item->harga_beli;

            $recommendedOrder = 0;
            $action = 'HOLD';
            $metode = '';
            $alasan = '';

            if ($isOndemand) {
                // [MODIFIED JIT] Abaikan stok otomatis (ROP & Min-Max). Dipesan hanya bila ada pesanan konsumen.
                $metode = 'Modified JIT';
                if ($pendingDemand > 0) {
                    if ($currentStock < $pendingDemand) {
                        $recommendedOrder = $pendingDemand - $currentStock;
                        $action = 'ORDER';
                        $alasan = "Pesanan konsumen aktif: {$pendingDemand} unit dibutuhkan, stok tersedia {$currentStock} unit.";
                    } else {
                        $recommendedOrder = 0;
                        $action = 'HOLD';
                        $alasan = "Stok saat ini ({$currentStock} unit) mencukupi kebutuhan pesanan konsumen ({$pendingDemand} unit).";
                    }
                } else {
                    $recommendedOrder = 0;
                    $action = 'HOLD';
                    $alasan = 'Komponen On-Demand (JIT): Tidak ada pesanan konsumen aktif saat ini.';
                }
            } elseif ($abcClass === 'A') {
                // [KELAS A: FAST MOVING] Menggunakan ROP (Reorder Point)
                $metode = 'ROP (Fast Moving)';
                $effectiveRop = $reorderPoint ?? $minStock ?? 0;

                if ($currentStock <= $effectiveRop) {
                    // Hitung jumlah order optimal: ke max_stock atau buffer 2x ROP
                    if ($maxStock !== null && $maxStock > $currentStock) {
                        $recommendedOrder = $maxStock - $currentStock;
                    } elseif ($effectiveRop > 0) {
                        $recommendedOrder = max(1, ($effectiveRop * 2) - $currentStock);
                    } else {
                        $recommendedOrder = 1;
                    }

                    $action = 'ORDER';
                    $alasan = "Stok saat ini ({$currentStock}) berada pada atau di bawah Reorder Point ({$effectiveRop}).";
                } else {
                    $recommendedOrder = 0;
                    $action = 'HOLD';
                    $alasan = "Stok aman ({$currentStock}) di atas Reorder Point ({$effectiveRop}).";
                }
            } else {
                // [KELAS B & C: MIN-MAX] Jika current_stock <= min_stock, order = max_stock - current_stock
                $metode = "Min-Max (Kelas {$abcClass})";
                $effectiveMin = $minStock ?? 0;
                $effectiveMax = $maxStock ?? ($effectiveMin > 0 ? $effectiveMin * 2 : 10);

                if ($currentStock <= $effectiveMin) {
                    $recommendedOrder = max(1, $effectiveMax - $currentStock);
                    $action = 'ORDER';
                    $alasan = "Stok saat ini ({$currentStock}) berada pada atau di bawah Min Stock ({$effectiveMin}).";
                } else {
                    $recommendedOrder = 0;
                    $action = 'HOLD';
                    $alasan = "Stok aman ({$currentStock}) di atas Min Stock ({$effectiveMin}).";
                }
            }

            $estimasiBiaya = $recommendedOrder * $hargaBeli;

            return [
                'produk_id' => $item->id,
                'nama' => $item->nama,
                'slug' => $item->slug,
                'barcode' => $item->barcode,
                'kategori' => $item->kategori,
                'satuan' => $item->satuan,
                'harga_beli' => $hargaBeli,
                'harga_jual_retail' => (float) $item->harga_jual_retail,
                'abc_class' => $abcClass,
                'reorder_point' => $reorderPoint,
                'min_stock' => $minStock,
                'max_stock' => $maxStock,
                'is_ondemand' => $isOndemand,
                'current_stock' => $currentStock,
                'pending_demand' => $pendingDemand,
                'recommended_order' => $recommendedOrder,
                'estimasi_biaya' => $estimasiBiaya,
                'action' => $action,
                'metode' => $metode,
                'alasan' => $alasan,
            ];
        });

        // Filter: only_reorder (hanya tampilkan yang butuh order)
        if (! empty($filters['only_reorder'])) {
            $results = $results->filter(fn ($item) => $item['action'] === 'ORDER')->values();
        }

        return $results;
    }

    /**
     * Hitung ringkasan metrik pengadaan untuk widget dashboard.
     */
    public function getProcurementSummary(?int $cabangId = null): array
    {
        $all = $this->getProcurementRecommendations($cabangId);

        $totalSku = $all->count();
        $needsOrder = $all->where('action', 'ORDER');
        $totalOrderSku = $needsOrder->count();
        $totalEstimasiBiaya = $needsOrder->sum('estimasi_biaya');

        $byClass = [
            'A' => [
                'total' => $all->where('abc_class', 'A')->where('is_ondemand', false)->count(),
                'needs_order' => $needsOrder->where('abc_class', 'A')->where('is_ondemand', false)->count(),
            ],
            'B' => [
                'total' => $all->where('abc_class', 'B')->where('is_ondemand', false)->count(),
                'needs_order' => $needsOrder->where('abc_class', 'B')->where('is_ondemand', false)->count(),
            ],
            'C' => [
                'total' => $all->where('abc_class', 'C')->where('is_ondemand', false)->count(),
                'needs_order' => $needsOrder->where('abc_class', 'C')->where('is_ondemand', false)->count(),
            ],
            'JIT' => [
                'total' => $all->where('is_ondemand', true)->count(),
                'needs_order' => $needsOrder->where('is_ondemand', true)->count(),
            ],
        ];

        return [
            'total_sku' => $totalSku,
            'total_needs_order' => $totalOrderSku,
            'total_estimasi_biaya' => $totalEstimasiBiaya,
            'breakdown' => $byClass,
        ];
    }
}
