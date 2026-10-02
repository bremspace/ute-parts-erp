<?php

namespace App\Modules\Wms\Livewire;

use App\Modules\Rbac\Traits\PunyaRiwayatAktivitas;
use App\Modules\Wms\Models\PurchaseOrder;
use App\Modules\Wms\Models\ReturnPembelian;
use App\Modules\Wms\Services\ReturnPembelianService;
use Livewire\Component;
use Livewire\WithPagination;

class ReturnPembelianTab extends Component
{
    use PunyaRiwayatAktivitas;
    use WithPagination;

    public string $filterStatus = 'all';

    public bool $showCreateModal = false;

    public ?int $selectedPoId = null;

    public string $alasan = '';

    public string $metodePengembalian = 'utang';

    public array $itemInputs = [];

    public ?int $detailReturId = null;

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

    public function simpanRetur(): void
    {
        // [RBAC] `wms.view` (dimiliki kasir) hanyaIzIN LIHAT retur. Membuat retur
        // = jalur API `POST /api/wms/retur-pembelian` yang butuh `wms.create`,
        // dan tombol "Buat Retur Pembelian" di blade juga sudah `@can('wms.create')`.
        abort_unless(
            auth()->user()?->can('wms.create'),
            403,
            'Anda tidak memiliki izin membuat retur pembelian.'
        );

        $this->validate([
            'selectedPoId' => 'required|exists:purchase_order,id',
            'alasan' => 'required|string|max:500',
            'metodePengembalian' => 'required|in:utang,kas',
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

        return view('modules.wms.livewire.return-pembelian-tab', [
            'returList' => $returList,
            'poSiapRetur' => $poSiapRetur,
            'detailRetur' => $detailRetur,
        ]);
    }
}
