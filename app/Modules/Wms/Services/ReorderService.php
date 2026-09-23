<?php

namespace App\Modules\Wms\Services;

use App\Modules\Notifikasi\Services\NotificationService;
use App\Modules\Wms\Models\PurchaseOrder;
use App\Modules\Wms\Models\PurchaseOrderItem;
use App\Modules\Wms\Models\StokItem;
use App\Modules\Wms\Models\Supplier;
use Illuminate\Support\Collection;

/**
 * [F1-5] Reorder otomatis — job harian (queue) cek stok < minimum,
 * buat usulan PO (status 'usulan') grouped per supplier terakhir + gudang.
 * Notifikasi in-app via NotificationService (queue — dilarang sync).
 */
class ReorderService
{
    public function __construct(
        protected NotificationService $notifikasi
    ) {}

    /**
     * Pemicu: StokItem.jumlah < jumlah_minimum (PRD: stok < minimum).
     * Anti-duplikat: lewati produk yang masih punya PO draft/usulan pending.
     *
     * @return Collection<int, PurchaseOrder> daftar PO usulan yang dibuat
     */
    public function jalankan(): Collection
    {
        $rendah = StokItem::query()
            ->whereColumn('jumlah', '<', 'jumlah_minimum')
            ->where('jumlah_minimum', '>', 0)
            ->with(['produk', 'gudang'])
            ->get()
            ->filter(fn (StokItem $item) => $item->produk && $item->gudang && $item->produk->is_active);

        $kelompok = [];

        foreach ($rendah as $item) {
            if ($this->adaPoPending($item->produk_id)) {
                continue;
            }

            $supplierId = $this->supplierTerakhir($item->produk_id);
            if (! $supplierId) {
                continue; // tanpa riwayat supplier → tidak bisa usulkan PO
            }

            $key = $supplierId.':'.$item->gudang_id;
            $kelompok[$key] ??= [
                'supplier_id' => $supplierId,
                'gudang_id' => $item->gudang_id,
                'items' => [],
            ];
            $kelompok[$key]['items'][] = $item;
        }

        $pos = collect();
        foreach ($kelompok as $grup) {
            $po = $this->buatUsulan($grup['supplier_id'], $grup['gudang_id'], $grup['items']);
            if ($po) {
                $pos->push($po);
            }
        }

        if ($pos->isNotEmpty()) {
            $this->kirimNotifikasi($pos);
        }

        return $pos;
    }

    /**
     * Anti-duplikat: produk sudah tercakup PO draft/usulan yang masih pending.
     */
    protected function adaPoPending(int $produkId): bool
    {
        return PurchaseOrderItem::where('produk_id', $produkId)
            ->whereHas('purchaseOrder', fn ($q) => $q->whereIn('status', ['draft', 'usulan']))
            ->exists();
    }

    /**
     * Supplier terakhir dari riwayat PO produk; fallback supplier tersering.
     */
    protected function supplierTerakhir(int $produkId): ?int
    {
        $terakhir = PurchaseOrderItem::query()
            ->where('produk_id', $produkId)
            ->join('purchase_order', 'purchase_order.id', '=', 'purchase_order_item.purchase_order_id')
            ->orderByDesc('purchase_order.created_at')
            ->orderByDesc('purchase_order.id')
            ->value('purchase_order.supplier_id');

        if ($terakhir) {
            return (int) $terakhir;
        }

        $tersering = PurchaseOrderItem::query()
            ->where('produk_id', $produkId)
            ->join('purchase_order', 'purchase_order.id', '=', 'purchase_order_item.purchase_order_id')
            ->groupBy('purchase_order.supplier_id')
            ->orderByRaw('count(*) desc')
            ->value('purchase_order.supplier_id');

        return $tersering ? (int) $tersering : null;
    }

    /**
     * @param  array<int, StokItem>  $items
     */
    protected function buatUsulan(int $supplierId, int $gudangId, array $items): ?PurchaseOrder
    {
        $total = 0;
        $baris = [];

        foreach ($items as $item) {
            $qty = max(1, (int) $item->jumlah_minimum - (int) $item->jumlah);
            $harga = (float) ($item->produk->harga_beli ?? 0);
            $subtotal = $harga * $qty;
            $total += $subtotal;

            $baris[] = [
                'produk_id' => $item->produk_id,
                'sku_variant_id' => $item->sku_variant_id,
                'harga_beli' => $harga,
                'jumlah' => $qty,
                'subtotal' => $subtotal,
            ];
        }

        if ($baris === []) {
            return null;
        }

        $termin = (int) (Supplier::find($supplierId)?->termin_hari ?? 30);

        $po = PurchaseOrder::create([
            'no_po' => $this->generateNoPo(),
            'supplier_id' => $supplierId,
            'gudang_tujuan_id' => $gudangId,
            'status' => 'usulan',
            'metode_bayar' => 'kredit',
            'jatuh_tempo' => now()->addDays($termin)->toDateString(),
            'total' => $total,
            'total_dibayar' => 0,
            'catatan' => 'Usulan reorder otomatis (stok di bawah minimum)',
        ]);

        foreach ($baris as $b) {
            PurchaseOrderItem::create(['purchase_order_id' => $po->id] + $b);
        }

        return $po;
    }

    protected function generateNoPo(): string
    {
        $today = now()->format('Ymd');
        $count = PurchaseOrder::whereDate('created_at', now()->toDateString())->count() + 1;

        return sprintf('PO-%s-%03d', $today, $count);
    }

    /**
     * @param  Collection<int, PurchaseOrder>  $pos
     */
    protected function kirimNotifikasi(Collection $pos): void
    {
        $daftar = $pos
            ->map(fn (PurchaseOrder $po) => $po->no_po.' ('.$po->supplier?->nama.')')
            ->implode(', ');

        $this->notifikasi->kirim(
            'inapp',
            null,
            'Usulan Reorder Otomatis',
            $pos->count().' usulan PO dibuat dari cek stok di bawah minimum: '.$daftar
                .'. Buka menu WMS → PO & Supplier lalu klik Konfirmasi.',
            [
                'type' => 'warning',
                'po_ids' => $pos->pluck('id')->all(),
            ]
        );
    }
}
