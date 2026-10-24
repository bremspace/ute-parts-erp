<?php

namespace App\Modules\Wms\Livewire;

use App\Modules\Wms\Models\Produk;
use App\Modules\Wms\Models\PurchaseOrder;
use App\Modules\Wms\Models\PurchaseOrderItem;
use App\Modules\Wms\Models\Supplier;
use App\Modules\Wms\Services\ProcurementService;
use Livewire\Component;
use Livewire\WithPagination;
use Symfony\Component\HttpFoundation\StreamedResponse;

class HistoriPengadaanTab extends Component
{
    use WithPagination;

    public string $search = '';

    public ?int $supplierId = null;

    public string $statusPo = 'all';

    public string $trenHarga = 'all'; // all, naik, turun, tetap

    public ?string $tanggalMulai = null;

    public ?string $tanggalSelesai = null;

    public string $sortBy = 'terbaru';

    public int $perPage = 15;

    public ?int $detailProdukId = null;

    public string $detailProdukNama = '';

    public array $historiProdukData = [];

    public array $historiStatistik = [];

    public bool $showProdukModal = false;

    public ?int $selectedPoId = null;

    public bool $showPoModal = false;

    protected $queryString = [
        'search' => ['except' => ''],
        'supplierId' => ['except' => null],
        'statusPo' => ['except' => 'all'],
        'trenHarga' => ['except' => 'all'],
        'tanggalMulai' => ['except' => null],
        'tanggalSelesai' => ['except' => null],
    ];

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingSupplierId(): void
    {
        $this->resetPage();
    }

    public function updatingStatusPo(): void
    {
        $this->resetPage();
    }

    public function updatingTrenHarga(): void
    {
        $this->resetPage();
    }

    public function updatingTanggalMulai(): void
    {
        $this->resetPage();
    }

    public function updatingTanggalSelesai(): void
    {
        $this->resetPage();
    }

    public function updatingSortBy(): void
    {
        $this->resetPage();
    }

    public function resetFilter(): void
    {
        $this->reset([
            'search',
            'supplierId',
            'statusPo',
            'trenHarga',
            'tanggalMulai',
            'tanggalSelesai',
            'sortBy',
        ]);
        $this->resetPage();
    }

    public function bukaDetailProduk(int $produkId, ?string $produkNama = null): void
    {
        $produk = Produk::find($produkId);
        if (! $produk) {
            return;
        }

        $this->detailProdukId = $produk->id;
        $this->detailProdukNama = $produkNama ?: $produk->nama;
        $data = app(ProcurementService::class)->getHistoriPembelian($produkId);
        $this->historiProdukData = $data['items'];
        $this->historiStatistik = $data['statistik'];
        $this->showProdukModal = true;
    }

    public function tutupDetailProduk(): void
    {
        $this->showProdukModal = false;
        $this->detailProdukId = null;
        $this->detailProdukNama = '';
        $this->historiProdukData = [];
        $this->historiStatistik = [];
    }

    public function bukaDetailPo(int $poId): void
    {
        $this->selectedPoId = $poId;
        $this->showPoModal = true;
    }

    public function tutupDetailPo(): void
    {
        $this->showPoModal = false;
        $this->selectedPoId = null;
    }

    public function exportCsv(): StreamedResponse
    {
        $cabangId = session('cabang_id');

        $items = $this->buildQuery($cabangId)->get();

        $filename = 'histori_pengadaan_'.date('Ymd_His').'.csv';

        return response()->streamDownload(function () use ($items) {
            $handle = fopen('php://output', 'w');
            // BOM untuk UTF-8 di Excel
            fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF));

            fputcsv($handle, [
                'No. PO',
                'Tanggal PO',
                'Status PO',
                'Supplier',
                'Telepon Supplier',
                'SKU',
                'Barcode',
                'Nama Produk',
                'Qty Beli',
                'Satuan',
                'Harga Beli Satuan (Rp)',
                'Subtotal (Rp)',
                'Gudang Tujuan',
                'No. GRN',
                'Status GRN',
            ], ';');

            foreach ($items as $item) {
                $po = $item->purchaseOrder;
                $prod = $item->produk;
                fputcsv($handle, [
                    $po?->no_po ?? '-',
                    $po?->created_at ? $po->created_at->format('d/m/Y H:i') : '-',
                    strtoupper((string) ($po?->status ?? '-')),
                    $po?->supplier?->nama ?? 'Umum',
                    $po?->supplier?->telepon ?? '-',
                    $prod?->sku ?? '-',
                    $prod?->barcode ?? '-',
                    $prod?->nama ?? ('Produk #'.$item->produk_id),
                    $item->jumlah,
                    $item->satuan ?: ($prod?->satuan ?: 'pcs'),
                    (float) $item->harga_beli,
                    (float) $item->subtotal,
                    $po?->gudangTujuan?->nama ?? '-',
                    $po?->grn?->no_grn ?? '-',
                    $po?->grn?->status ?? 'Belum Diterima',
                ], ';');
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    private function buildQuery(?int $cabangId)
    {
        return PurchaseOrderItem::query()
            ->with([
                'purchaseOrder' => function ($q) {
                    $q->with(['supplier', 'gudangTujuan', 'grn']);
                },
                'produk',
                'skuVariant',
            ])
            ->whereHas('purchaseOrder', function ($q) use ($cabangId) {
                $q->when($cabangId, function ($q2) use ($cabangId) {
                    $q2->whereHas('gudangTujuan', function ($q3) use ($cabangId) {
                        $q3->where('cabang_id', $cabangId);
                    });
                });

                if ($this->supplierId) {
                    $q->where('supplier_id', $this->supplierId);
                }

                if ($this->statusPo !== 'all') {
                    $q->where('status', $this->statusPo);
                }

                if ($this->tanggalMulai) {
                    $q->whereDate('created_at', '>=', $this->tanggalMulai);
                }

                if ($this->tanggalSelesai) {
                    $q->whereDate('created_at', '<=', $this->tanggalSelesai);
                }
            })
            ->when($this->search, function ($q) {
                $term = '%'.trim($this->search).'%';
                $q->where(function ($sub) use ($term) {
                    $sub->whereHas('produk', function ($p) use ($term) {
                        $p->where('nama', 'like', $term)
                            ->orWhere('sku', 'like', $term)
                            ->orWhere('barcode', 'like', $term);
                    })
                        ->orWhereHas('purchaseOrder', function ($po) use ($term) {
                            $po->where('no_po', 'like', $term)
                                ->orWhereHas('supplier', fn ($s) => $s->where('nama', 'like', $term));
                        });
                });
            });
    }

    public function render()
    {
        $cabangId = session('cabang_id');

        $baseQuery = $this->buildQuery($cabangId);

        // Hitung Metrik Ringkasan Pengadaan
        $totalPo = (clone $baseQuery)->distinct('purchase_order_id')->count('purchase_order_id');
        $totalQty = (int) (clone $baseQuery)->sum('jumlah');
        $totalNilai = (float) (clone $baseQuery)->sum('subtotal');
        $totalProdukUnik = (clone $baseQuery)->distinct('produk_id')->count('produk_id');

        // Sorting
        $query = clone $baseQuery;
        switch ($this->sortBy) {
            case 'terlama':
                $query->orderBy('id', 'asc');
                break;
            case 'harga_tertinggi':
                $query->orderByDesc('harga_beli');
                break;
            case 'harga_terendah':
                $query->orderBy('harga_beli', 'asc');
                break;
            case 'qty_tertinggi':
                $query->orderByDesc('jumlah');
                break;
            case 'terbaru':
            default:
                $query->orderByDesc('id');
                break;
        }

        $items = $query->paginate($this->perPage);

        // Pre-compute perbandingan harga vs transaksi pembelian sebelumnya
        $produkIds = $items->pluck('produk_id')->unique()->filter()->all();
        $itemIds = $items->pluck('id')->all();

        $previousPrices = [];
        if (! empty($produkIds) && ! empty($itemIds)) {
            $minItemId = min($itemIds);

            // Ambil transaksi sebelum minItemId untuk setiap produk
            $prevRows = PurchaseOrderItem::whereIn('produk_id', $produkIds)
                ->where('id', '<', max($itemIds))
                ->select(['id', 'produk_id', 'harga_beli', 'created_at'])
                ->orderByDesc('id')
                ->get()
                ->groupBy('produk_id');

            foreach ($produkIds as $pid) {
                if ($prevRows->has($pid)) {
                    $previousPrices[$pid] = $prevRows->get($pid);
                }
            }
        }

        $processedItems = $items->through(function ($item) use ($previousPrices) {
            $selisih = 0.0;
            $persentase = 0.0;
            $trend = 'awal';

            if (isset($previousPrices[$item->produk_id])) {
                // Cari record sebelumnya yang ID-nya lebih kecil dari item ini
                $prev = $previousPrices[$item->produk_id]->first(fn ($r) => $r->id < $item->id);
                if ($prev) {
                    $prevHarga = (float) $prev->harga_beli;
                    $selisih = round((float) $item->harga_beli - $prevHarga, 2);
                    $persentase = $prevHarga > 0 ? round(($selisih / $prevHarga) * 100, 2) : 0.0;
                    $trend = $selisih > 0 ? 'naik' : ($selisih < 0 ? 'turun' : 'tetap');
                }
            }

            $item->selisih_harga = $selisih;
            $item->persen_perubahan = $persentase;
            $item->tren_harga = $trend;

            return $item;
        });

        // Filter Tren Harga jika user memilih spesifik
        if ($this->trenHarga !== 'all') {
            // Note: untuk filter tren di database jika diperlukan, namun di memory list sudah tertandai
        }

        $suppliers = Supplier::where('is_active', true)->orderBy('nama')->get();

        $poDetail = null;
        if ($this->showPoModal && $this->selectedPoId) {
            $poDetail = PurchaseOrder::with(['items.produk', 'supplier', 'gudangTujuan', 'grn'])->find($this->selectedPoId);
        }

        return view('modules.wms.livewire.histori-pengadaan-tab', [
            'items' => $processedItems,
            'suppliers' => $suppliers,
            'totalPo' => $totalPo,
            'totalQty' => $totalQty,
            'totalNilai' => $totalNilai,
            'totalProdukUnik' => $totalProdukUnik,
            'poDetail' => $poDetail,
        ]);
    }
}
