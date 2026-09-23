<?php

namespace App\Modules\Wms\Livewire;

use App\Modules\Rbac\Traits\PunyaRiwayatAktivitas;
use App\Modules\Wms\Models\Gudang;
use App\Modules\Wms\Models\Produk;
use App\Modules\Wms\Models\PurchaseOrder;
use App\Modules\Wms\Models\PurchaseOrderItem;
use App\Modules\Wms\Models\Supplier;
use App\Modules\Wms\Services\PurchaseOrderService;
use App\Modules\Workflow\Services\ApprovalService;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * [F1-8 / S-01] Tab "PO & Supplier" — dipecah dari WmsDashboard (paritas perilaku).
 */
class PoTab extends Component
{
    use PunyaRiwayatAktivitas;
    use WithPagination;

    // [T-10] PO dan Supplier
    public bool $showPoModal = false;

    public array $poForm = [
        'supplier_id' => null, 'gudang_tujuan_id' => null, 'metode_bayar' => 'kredit', 'jatuh_tempo' => '',
        'items' => [],
    ];

    public bool $showSupplierModal = false;

    public array $supplierForm = ['nama' => '', 'kontak' => '', 'telepon' => '', 'alamat' => '', 'termin_hari' => 30];

    public ?int $bayarPoId = null;

    public float $bayarPoJumlah = 0;

    // Quick action header "+ Buat PO" (dari shell WmsDashboard via $dispatch)
    #[On('wms-po-baru')]
    public function openPoModal()
    {
        $this->poForm = [
            'supplier_id' => null, 'gudang_tujuan_id' => null, 'metode_bayar' => 'kredit', 'jatuh_tempo' => '',
            'items' => [['produk_id' => null, 'sku_variant_id' => null, 'harga_beli' => 0, 'jumlah' => 1]],
        ];
        $this->showPoModal = true;
    }

    // Quick action header "+ Supplier" (dari shell WmsDashboard via $dispatch)
    #[On('wms-supplier-baru')]
    public function openSupplierModal(): void
    {
        $this->showSupplierModal = true;
    }

    public function addPoItem()
    {
        $this->poForm['items'][] = ['produk_id' => null, 'sku_variant_id' => null, 'harga_beli' => 0, 'jumlah' => 1];
    }

    public function removePoItem(int $idx)
    {
        unset($this->poForm['items'][$idx]);
        $this->poForm['items'] = array_values($this->poForm['items']);
    }

    public function poProdukDipilih(int $idx)
    {
        $produk = Produk::find($this->poForm['items'][$idx]['produk_id']);
        if ($produk) {
            $this->poForm['items'][$idx]['harga_beli'] = (float) $produk->harga_beli;
            $this->poForm['items'][$idx]['sku_variant_id'] = $produk->skuVariants()->first()?->id;
        }
    }

    public function simpanPo()
    {
        $this->validate([
            'poForm.supplier_id' => 'required|exists:supplier,id',
            'poForm.gudang_tujuan_id' => 'required|exists:gudang,id',
            'poForm.metode_bayar' => 'required|in:tunai,kredit',
            'poForm.items' => 'required|array|min:1',
        ]);

        $today = now()->format('Ymd');
        $count = PurchaseOrder::whereDate('created_at', now()->toDateString())->count() + 1;
        $noPo = sprintf('PO-%s-%03d', $today, $count);

        $total = 0;
        foreach ($this->poForm['items'] as $i) {
            $total += (float) ($i['harga_beli'] ?? 0) * (int) ($i['jumlah'] ?? 1);
        }

        $po = PurchaseOrder::create([
            'no_po' => $noPo,
            'supplier_id' => $this->poForm['supplier_id'],
            'gudang_tujuan_id' => $this->poForm['gudang_tujuan_id'],
            'status' => 'draft',
            'metode_bayar' => $this->poForm['metode_bayar'],
            'jatuh_tempo' => $this->poForm['jatuh_tempo'] ?: now()->addDays((int) Supplier::find($this->poForm['supplier_id'])?->termin_hari ?? 30)->toDateString(),
            'total' => $total,
            'total_dibayar' => 0,
        ]);

        foreach ($this->poForm['items'] as $i) {
            PurchaseOrderItem::create([
                'purchase_order_id' => $po->id,
                'produk_id' => $i['produk_id'],
                'sku_variant_id' => $i['sku_variant_id'] ?? null,
                'harga_beli' => (float) ($i['harga_beli'] ?? 0),
                'jumlah' => (int) ($i['jumlah'] ?? 1),
                'subtotal' => (float) ($i['harga_beli'] ?? 0) * (int) ($i['jumlah'] ?? 1),
            ]);
        }

        $this->showPoModal = false;
        $this->dispatch('alert', ['type' => 'success', 'message' => "PO {$noPo} dibuat (draft)"]);
    }

    /**
     * [F1-5] Konfirmasi 1 klik: usulan → draft (alur PO normal F1-1 dst).
     * Observer tidak fire utk perubahan status saja → ajukan approval eksplisit
     * (idempoten; di bawah threshold ajukan() return null).
     */
    public function konfirmasiUsulan(int $id)
    {
        $po = PurchaseOrder::findOrFail($id);

        if ($po->status !== 'usulan') {
            $this->dispatch('alert', ['type' => 'error', 'message' => 'Hanya PO berstatus usulan yang dapat dikonfirmasi']);

            return;
        }

        $po->update(['status' => 'draft']);

        $cabangId = $po->gudangTujuan()->value('cabang_id');
        $userId = auth()->id() ?? ApprovalService::pemohon();

        if ($userId && (float) $po->total > 0) {
            app(ApprovalService::class)->ajukan('po', $po->id, $cabangId, [
                'amount' => (float) $po->total,
                'no_po' => $po->no_po,
                'status' => 'draft',
                'supplier_id' => $po->supplier_id,
            ], $userId);
        }

        $this->dispatch('alert', ['type' => 'success', 'message' => "PO {$po->no_po} dikonfirmasi (draft) — ikuti alur PO normal"]);
    }

    public function kirimPo(int $id)
    {
        $po = PurchaseOrder::findOrFail($id);

        // Cek apakah PO menunggu approval (rule match)
        $cabangId = session('cabang_id');
        $approvalService = app(ApprovalService::class);
        if ($approvalService->adaPending('po', $po->id)) {
            $this->dispatch('alert', ['type' => 'error', 'message' => 'PO menunggu approval dan tidak dapat dikirim']);

            return;
        }

        $po->update(['status' => 'dikirim']);
        $this->dispatch('alert', ['type' => 'success', 'message' => 'PO dikirim ke supplier']);
    }

    public function terimaPo(int $id): void
    {
        // [F2-2] Penerimaan langsung DITUTUP — wajib lewat GRN agar tidak ada
        // double stok / double jurnal (PO diterima ganda via tab + GRN).
        unset($id);
        $this->dispatch('alert', [
            'type' => 'error',
            'message' => 'Penerimaan barang wajib lewat GRN — buka tab GRN untuk menerima PO ini.',
        ]);
    }

    public function bukaBayarPo(int $id)
    {
        $this->bayarPoId = $id;
        $this->bayarPoJumlah = (float) PurchaseOrder::find($id)?->sisa ?? 0;
        $this->dispatch('alert-open-bayar-po', ['id' => $id]);
    }

    public function bayarPo()
    {
        try {
            app(PurchaseOrderService::class)->bayarPO(
                PurchaseOrder::findOrFail($this->bayarPoId),
                (float) $this->bayarPoJumlah,
                auth()->id()
            );
            $this->dispatch('alert', ['type' => 'success', 'message' => 'Pembayaran PO tercatat — sisa utang updated']);
        } catch (\Exception $e) {
            $this->dispatch('alert', ['type' => 'error', 'message' => $e->getMessage()]);
        }
    }

    public function simpanSupplier()
    {
        $this->validate([
            'supplierForm.nama' => 'required|string|max:255',
        ]);

        Supplier::create($this->supplierForm);
        $this->showSupplierModal = false;
        $this->supplierForm = ['nama' => '', 'kontak' => '', 'telepon' => '', 'alamat' => '', 'termin_hari' => 30];
        $this->dispatch('alert', ['type' => 'success', 'message' => 'Supplier disimpan']);
    }

    public function render()
    {
        $gudangs = Gudang::where('is_active', true)->get();
        $allProducts = Produk::where('is_active', true)->orderBy('nama')->get();

        return view('modules.wms.livewire.po-tab', [
            'gudangs' => $gudangs,
            'allProducts' => $allProducts,
            'suppliers' => Supplier::orderBy('nama')->get(),
            'poList' => PurchaseOrder::with(['supplier', 'gudangTujuan', 'items.produk'])->latest()->paginate(15, pageName: 'po'),
        ]);
    }
}
