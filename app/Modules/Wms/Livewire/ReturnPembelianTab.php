<?php

namespace App\Modules\Wms\Livewire;

use App\Modules\Pos\Models\ReturnPenjualanItem;
use App\Modules\Rbac\Traits\PunyaRiwayatAktivitas;
use App\Modules\Wms\Models\PurchaseOrder;
use App\Modules\Wms\Models\ReturnPembelian;
use App\Modules\Wms\Models\Supplier;
use App\Modules\Wms\Services\ReturnPembelianService;
use Livewire\Component;
use Livewire\WithPagination;

class ReturnPembelianTab extends Component
{
    use PunyaRiwayatAktivitas;
    use WithPagination;

    public string $subTab = 'riwayat'; // riwayat | karantina

    public string $filterStatus = 'all';

    public ?int $filterSupplierId = null;

    public bool $showCreateModal = false;

    public ?int $selectedPoId = null;

    public string $alasan = '';

    public string $metodePengembalian = 'utang';

    public array $itemInputs = [];

    public ?int $detailReturId = null;

    // State untuk Retur Pool Karantina
    public array $selectedPoolItemIds = [];

    public bool $showPoolReturModal = false;

    public ?int $selectedPoolSupplierId = null;

    public string $poolAlasan = 'Retur akumulasi barang rusak dari garansi toko';

    public string $poolMetodePengembalian = 'saldo'; // saldo | utang | kas

    public function mount(): void
    {
        $this->resetForm();
    }

    public function resetForm(): void
    {
        $this->selectedPoId = null;
        $this->alasan = '';
        $this->metodePengembalian = 'utang';
        $this->itemInputs = [];
        $this->showCreateModal = false;
        $this->showPoolReturModal = false;
        $this->selectedPoolSupplierId = null;
        $this->poolAlasan = 'Retur akumulasi barang rusak dari garansi toko';
        $this->poolMetodePengembalian = 'saldo';
    }

    public function updatedSelectedPoId($value): void
    {
        $this->itemInputs = [];
        if (! $value) {
            return;
        }

        $po = PurchaseOrder::with(['items.produk'])->find($value);
        if (! $po) {
            return;
        }

        foreach ($po->items as $item) {
            $this->itemInputs[$item->id] = [
                'purchase_order_item_id' => $item->id,
                'produk_nama' => $item->produk?->nama ?? '-',
                'qty_po' => (float) $item->jumlah,
                'harga_beli' => (float) $item->harga_beli,
                'jumlah' => 0,
                'sn_raw' => '',
            ];
        }
    }

    public function bukaModalTambah(): void
    {
        $this->resetForm();
        $this->showCreateModal = true;
    }

    public function tutupModalTambah(): void
    {
        $this->resetForm();
    }

    public function bukaDetail(int $id): void
    {
        $this->detailReturId = $id;
    }

    public function tutupDetail(): void
    {
        $this->detailReturId = null;
    }

    public function setujuiRetur(int $id): void
    {
        abort_unless(
            auth()->user()?->can('wms.approve') || auth()->user()?->can('wms.update'),
            403,
            'Anda tidak memiliki izin menyetujui retur pembelian.'
        );

        $retur = ReturnPembelian::findOrFail($id);
        if ($retur->status !== 'menunggu') {
            $this->dispatch('alert', ['type' => 'error', 'message' => 'Hanya retur berstatus menunggu yang dapat disetujui.']);

            return;
        }

        try {
            app(ReturnPembelianService::class)->eksekusiRetur($retur, auth()->id());
            $this->dispatch('alert', [
                'type' => 'success',
                'message' => "Retur {$retur->no_return} berhasil disetujui dan stok telah disesuaikan.",
            ]);
            $this->tutupDetail();
        } catch (\Exception $e) {
            $this->dispatch('alert', ['type' => 'error', 'message' => $e->getMessage()]);
        }
    }

    public function tolakRetur(int $id, string $alasan = 'Ditolak oleh admin'): void
    {
        abort_unless(
            auth()->user()?->can('wms.approve') || auth()->user()?->can('wms.update'),
            403,
            'Anda tidak memiliki izin menolak retur pembelian.'
        );

        $retur = ReturnPembelian::findOrFail($id);
        if ($retur->status !== 'menunggu') {
            $this->dispatch('alert', ['type' => 'error', 'message' => 'Hanya retur berstatus menunggu yang dapat ditolak.']);

            return;
        }

        try {
            app(ReturnPembelianService::class)->tolakRetur($retur, $alasan);
            $this->dispatch('alert', [
                'type' => 'info',
                'message' => "Retur {$retur->no_return} telah ditolak.",
            ]);
            $this->tutupDetail();
        } catch (\Exception $e) {
            $this->dispatch('alert', ['type' => 'error', 'message' => $e->getMessage()]);
        }
    }

    public function simpanRetur(): void
    {
        abort_unless(
            auth()->user()?->can('wms.create'),
            403,
            'Anda tidak memiliki izin membuat retur pembelian.'
        );

        $this->validate([
            'selectedPoId' => 'required|exists:purchase_order,id',
            'alasan' => 'required|string|max:500',
            'metodePengembalian' => 'required|in:utang,kas,saldo',
        ]);

        $items = [];
        foreach ($this->itemInputs as $item) {
            $qty = (float) ($item['jumlah'] ?? 0);
            if ($qty > 0) {
                $snList = [];
                if (! empty($item['sn_raw'])) {
                    $snList = preg_split('/[\r\n,;]+/', (string) $item['sn_raw']) ?: [];
                    $snList = array_values(array_filter(array_map('trim', $snList)));
                }

                $items[] = [
                    'purchase_order_item_id' => (int) $item['purchase_order_item_id'],
                    'jumlah' => $qty,
                    'sn' => $snList,
                ];
            }
        }

        if (empty($items)) {
            $this->dispatch('alert', ['type' => 'error', 'message' => 'Pilih minimal 1 barang dengan jumlah > 0 untuk diretur.']);

            return;
        }

        $po = PurchaseOrder::findOrFail($this->selectedPoId);

        try {
            $retur = app(ReturnPembelianService::class)->buatRetur(
                $po,
                $items,
                $this->alasan,
                $this->metodePengembalian,
                auth()->id()
            );

            $this->dispatch('alert', [
                'type' => 'success',
                'message' => $retur->status === 'selesai'
                    ? "Retur {$retur->no_return} berhasil diproses — stok terpotong & utang disesuaikan."
                    : "Retur {$retur->no_return} dibuat — menunggu approval manajemen.",
            ]);

            $this->resetForm();
        } catch (\Exception $e) {
            $this->dispatch('alert', ['type' => 'error', 'message' => $e->getMessage()]);
        }
    }

    public function bukaModalPoolRetur(?int $supplierId = null): void
    {
        $this->selectedPoolSupplierId = $supplierId;
        $this->showPoolReturModal = true;
    }

    public function tutupModalPoolRetur(): void
    {
        $this->showPoolReturModal = false;
        $this->selectedPoolSupplierId = null;
    }

    public function simpanReturPoolSupplier(): void
    {
        abort_unless(
            auth()->user()?->can('wms.create'),
            403,
            'Anda tidak memiliki izin membuat retur pembelian.'
        );

        $this->validate([
            'selectedPoolSupplierId' => 'required|exists:supplier,id',
            'poolAlasan' => 'required|string|max:500',
            'poolMetodePengembalian' => 'required|in:saldo,utang,kas',
        ]);

        $supplier = Supplier::findOrFail($this->selectedPoolSupplierId);

        // Ambil item karantina yang dipilih (atau semua item milik supplier ini jika belum diceklis)
        $itemsQuery = ReturnPenjualanItem::where('status_karantina', 'karantina')
            ->where('supplier_id', $supplier->id);

        if (! empty($this->selectedPoolItemIds)) {
            $itemsQuery->whereIn('id', $this->selectedPoolItemIds);
        }

        $itemIds = $itemsQuery->pluck('id')->all();

        if (empty($itemIds)) {
            $this->dispatch('alert', ['type' => 'error', 'message' => 'Tidak ada barang karantina yang dipilih untuk supplier ini.']);

            return;
        }

        try {
            $retur = app(ReturnPembelianService::class)->buatReturDariPoolSupplier(
                $supplier,
                $itemIds,
                $this->poolAlasan,
                $this->poolMetodePengembalian,
                session('cabang_id'),
                null,
                auth()->id()
            );

            $this->dispatch('alert', [
                'type' => 'success',
                'message' => "Retur {$retur->no_return} berhasil diproses ke supplier {$supplier->nama} — nilai: Rp ".number_format($retur->jumlah, 0, ',', '.'),
            ]);

            $this->selectedPoolItemIds = [];
            $this->tutupModalPoolRetur();
        } catch (\Exception $e) {
            $this->dispatch('alert', ['type' => 'error', 'message' => $e->getMessage()]);
        }
    }

    public function render()
    {
        $cabangId = session('cabang_id');

        $query = ReturnPembelian::with(['purchaseOrder', 'supplier', 'items.produk', 'user'])
            ->when($cabangId, fn ($q) => $q->where('cabang_id', $cabangId))
            ->when($this->filterStatus !== 'all', fn ($q) => $q->where('status', $this->filterStatus));

        $returList = $query->latest()->paginate(15, pageName: 'retur_pembelian');

        $poSiapRetur = PurchaseOrder::with(['supplier', 'items.produk'])
            ->where('status', 'diterima')
            ->when($cabangId, fn ($q) => $q->whereHas('gudangTujuan', fn ($q2) => $q2->where('cabang_id', $cabangId)))
            ->latest()
            ->limit(30)
            ->get();

        $detailRetur = null;
        if ($this->detailReturId) {
            $detailRetur = ReturnPembelian::with(['purchaseOrder.items.produk', 'supplier', 'items.produk', 'user', 'gudang'])
                ->find($this->detailReturId);
        }

        // Data Pool Karantina Barang Rusak
        $karantinaQuery = ReturnPenjualanItem::with(['produk', 'supplier', 'returnPenjualan'])
            ->where('status_karantina', 'karantina')
            ->when($this->filterSupplierId, fn ($q) => $q->where('supplier_id', $this->filterSupplierId));

        $karantinaItems = $karantinaQuery->latest()->get();

        $karantinaGrouped = $karantinaItems->groupBy('supplier_id');

        $suppliers = Supplier::where('is_active', true)->orderBy('nama')->get();

        return view('modules.wms.livewire.return-pembelian-tab', [
            'returList' => $returList,
            'poSiapRetur' => $poSiapRetur,
            'detailRetur' => $detailRetur,
            'karantinaItems' => $karantinaItems,
            'karantinaGrouped' => $karantinaGrouped,
            'suppliers' => $suppliers,
        ]);
    }
}
