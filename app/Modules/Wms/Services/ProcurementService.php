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

    /**
     * Ambil histori pembelian supplier per produk untuk tracking tren harga beli.
     *
     * @return array{items: array, statistik: array}
     */
    public function getHistoriPembelian(int $produkId, ?int $supplierId = null): array
    {
        $query = DB::table('purchase_order_item')
            ->join('purchase_order', 'purchase_order.id', '=', 'purchase_order_item.purchase_order_id')
            ->leftJoin('supplier', 'supplier.id', '=', 'purchase_order.supplier_id')
            ->where('purchase_order_item.produk_id', $produkId)
            ->when($supplierId, fn ($q) => $q->where('purchase_order.supplier_id', $supplierId))
            ->select([
                'purchase_order.id as po_id',
                'purchase_order.no_po',
                'purchase_order.created_at',
                'purchase_order.status',
                'purchase_order.metode_bayar',
                'supplier.id as supplier_id',
                'supplier.nama as supplier_nama',
                'supplier.telepon as supplier_telepon',
                'purchase_order_item.harga_beli',
                'purchase_order_item.jumlah',
                'purchase_order_item.subtotal',
            ])
            ->orderByDesc('purchase_order.created_at')
            ->orderByDesc('purchase_order.id');

        $rows = $query->limit(50)->get();

        if ($rows->isEmpty()) {
            return [
                'items' => [],
                'statistik' => [
                    'total_transaksi' => 0,
                    'total_qty' => 0,
                    'harga_terakhir' => 0,
                    'harga_terendah' => 0,
                    'harga_tertinggi' => 0,
                    'harga_rata_rata' => 0,
                    'supplier_terakhir' => null,
                ],
            ];
        }

        $totalQty = (int) $rows->sum('jumlah');
        $totalNilai = (float) $rows->sum('subtotal');
        $hargaTerakhir = (float) $rows->first()->harga_beli;
        $hargaTerendah = (float) $rows->min('harga_beli');
        $hargaTertinggi = (float) $rows->max('harga_beli');
        $weightedAvg = $totalQty > 0 ? round($totalNilai / $totalQty, 2) : $hargaTerakhir;

        return [
            'items' => $rows->map(function ($r) {
                return [
                    'po_id' => $r->po_id,
                    'no_po' => $r->no_po,
                    'tanggal' => $r->created_at ? date('d/m/Y H:i', strtotime($r->created_at)) : '-',
                    'status' => $r->status,
                    'metode_bayar' => $r->metode_bayar,
                    'supplier_id' => $r->supplier_id,
                    'supplier_nama' => $r->supplier_nama ?: 'Umum / Tanpa Nama',
                    'supplier_telepon' => $r->supplier_telepon ?: '-',
                    'harga_beli' => (float) $r->harga_beli,
                    'jumlah' => (int) $r->jumlah,
                    'subtotal' => (float) $r->subtotal,
                ];
            })->all(),
            'statistik' => [
                'total_transaksi' => $rows->count(),
                'total_qty' => $totalQty,
                'harga_terakhir' => $hargaTerakhir,
                'harga_terendah' => $hargaTerendah,
                'harga_tertinggi' => $hargaTertinggi,
                'harga_rata_rata' => $weightedAvg,
                'supplier_terakhir' => $rows->first()->supplier_nama,
            ],
        ];
    }

    /**
     * Hitung analisis ABC (Pareto 80/20) dan rekomendasi ROP / Min-Max berdasarkan data pemakaian riil.
     *
     * @param  int  $periodeHari  Default 90 hari
     */
    public function hitungAnalisisAbc(?int $cabangId = null, int $periodeHari = 90): array
    {
        $since = now()->subDays($periodeHari);

        // Agregasi mutasi keluar penjualan dan servis dari stock_mutation_log
        $outflow = DB::table('stock_mutation_log')
            ->join('gudang', 'gudang.id', '=', 'stock_mutation_log.gudang_id')
            ->where('stock_mutation_log.terjadi_at', '>=', $since)
            ->where('stock_mutation_log.delta', '<', 0)
            ->when($cabangId, fn ($q) => $q->where('gudang.cabang_id', $cabangId))
            ->groupBy('stock_mutation_log.produk_id')
            ->select([
                'stock_mutation_log.produk_id',
                DB::raw('ABS(SUM(stock_mutation_log.delta)) as total_outflow_qty'),
            ])
            ->pluck('total_outflow_qty', 'produk_id');

        $produks = DB::table('produk')
            ->where('is_active', true)
            ->select(['id', 'nama', 'barcode', 'kategori', 'satuan', 'harga_beli', 'harga_jual_retail', 'abc_class', 'reorder_point', 'min_stock', 'max_stock', 'is_ondemand'])
            ->get();

        $items = [];
        $totalNilaiSemua = 0;

        foreach ($produks as $p) {
            $qtyKeluar = (int) ($outflow[$p->id] ?? 0);
            $harga = (float) ($p->harga_jual_retail > 0 ? $p->harga_jual_retail : $p->harga_beli);
            $nilaiOmzet = $qtyKeluar * $harga;
            $totalNilaiSemua += $nilaiOmzet;

            $items[] = [
                'produk_id' => $p->id,
                'nama' => $p->nama,
                'barcode' => $p->barcode,
                'kategori' => $p->kategori,
                'satuan' => $p->satuan ?: 'pcs',
                'harga_beli' => (float) $p->harga_beli,
                'harga_jual_retail' => (float) $p->harga_jual_retail,
                'current_abc' => $p->abc_class ?: 'B',
                'current_rop' => $p->reorder_point,
                'current_min' => $p->min_stock,
                'current_max' => $p->max_stock,
                'is_ondemand' => (bool) $p->is_ondemand,
                'qty_keluar' => $qtyKeluar,
                'nilai_omzet' => $nilaiOmzet,
            ];
        }

        // Urutkan omzet menurun (Pareto analysis)
        usort($items, fn ($a, $b) => $b['nilai_omzet'] <=> $a['nilai_omzet']);

        $kumulatifNilai = 0;
        $hasil = [];

        foreach ($items as $item) {
            $omzetSebelum = $kumulatifNilai;
            $kumulatifNilai += $item['nilai_omzet'];
            $persenKumulatif = $totalNilaiSemua > 0 ? round(($kumulatifNilai / $totalNilaiSemua) * 100, 2) : 100;
            $persenSebelum = $totalNilaiSemua > 0 ? ($omzetSebelum / $totalNilaiSemua) * 100 : 0;

            // Klasifikasi ABC Pareto:
            // Kelas A: Kontributor omzet utama hingga mencapai threshold 80% (Fast Moving)
            // Kelas B: Kontributor berikutnya antara 80% - 95% (Medium Moving)
            // Kelas C: Kontributor ekor panjang (Long-tail / Slow Moving) atau 0 pergerakan
            if ($item['qty_keluar'] <= 0) {
                $rekomendasiAbc = 'C';
            } elseif ($persenSebelum < 80.0) {
                $rekomendasiAbc = 'A';
            } elseif ($persenSebelum < 95.0) {
                $rekomendasiAbc = 'B';
            } else {
                $rekomendasiAbc = 'C';
            }

            // Hitung ADU (Average Daily Usage)
            $adu = $item['qty_keluar'] / max(1, $periodeHari);
            $leadTimeHari = 7; // Standar lead time restock

            // Rekomendasi WMS:
            if ($rekomendasiAbc === 'A') {
                $safetyStock = max(2, (int) ceil($adu * 7));
                $rekRop = (int) ceil(($adu * $leadTimeHari) + $safetyStock);
                $rekMin = $rekRop;
                $rekMax = max($rekRop * 2, (int) ceil($rekRop + ($adu * 14)));
            } elseif ($rekomendasiAbc === 'B') {
                $safetyStock = max(1, (int) ceil($adu * 4));
                $rekRop = (int) ceil(($adu * $leadTimeHari) + $safetyStock);
                $rekMin = max(1, $rekRop);
                $rekMax = max($rekMin * 2, (int) ceil($rekMin + ($adu * 10)));
            } else {
                $safetyStock = max(1, (int) ceil($adu * 2));
                $rekRop = max(1, (int) ceil(($adu * $leadTimeHari) + $safetyStock));
                $rekMin = max(1, (int) ceil($adu * 3));
                $rekMax = max(2, (int) ceil($rekMin * 2));
            }

            $item['rekomendasi_abc'] = $rekomendasiAbc;
            $item['rekomendasi_rop'] = $rekRop;
            $item['rekomendasi_min'] = $rekMin;
            $item['rekomendasi_max'] = $rekMax;
            $item['adu'] = round($adu, 2);
            $item['persen_kumulatif'] = $persenKumulatif;

            $hasil[] = $item;
        }

        return [
            'periode_hari' => $periodeHari,
            'total_nilai' => $totalNilaiSemua,
            'items' => $hasil,
        ];
    }

    /**
     * Terapkan rekomendasi analisis ABC ke database produk.
     */
    public function terapkanAnalisisAbc(array $produkIds = [], ?int $cabangId = null, int $periodeHari = 90): int
    {
        $analisis = $this->hitungAnalisisAbc($cabangId, $periodeHari);
        $count = 0;

        foreach ($analisis['items'] as $item) {
            if (! empty($produkIds) && ! in_array($item['produk_id'], $produkIds, true)) {
                continue;
            }

            if ($item['is_ondemand']) {
                continue; // Jangan override parameter on-demand
            }

            Produk::where('id', $item['produk_id'])->update([
                'abc_class' => $item['rekomendasi_abc'],
                'reorder_point' => $item['rekomendasi_rop'],
                'min_stock' => $item['rekomendasi_min'],
                'max_stock' => $item['rekomendasi_max'],
            ]);

            $count++;
        }

        return $count;
    }
}
