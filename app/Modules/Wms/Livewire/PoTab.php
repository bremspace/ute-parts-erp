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

    /**
     * [B-15c] Batas dropdown produk PO. Master produk global bisa 500-2.000 baris;
     * dropdown tanpa paginasi menarik semuanya tiap render. Dipotong → user diberi
     * tahu (lihat peringatanProdukDipotong()).
     */
    public const PRODUK_DROPDOWN_LIMIT = 300;

    /** [B-15c] Pengaman jumlah gudang per cabang (normally < 10). */
    public const GUDANG_DROPDOWN_LIMIT = 100;

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

        // [B-15c] Beri tahu user kalau daftar produk dipotong (bukan diam-diam).
        $this->peringatanProdukDipotong();
    }

    /** [B-15c] Toast sekali-buka-modal bila daftar produk di dropdown memang dipotong. */
    private function peringatanProdukDipotong(): void
    {
        if ($this->totalProdukCabang() > self::PRODUK_DROPDOWN_LIMIT) {
            $this->dispatch('alert', [
                'type' => 'info',
                'message' => 'Daftar produk pada dropdown dibatasi '.self::PRODUK_DROPDOWN_LIMIT.
                    ' produk (urutan nama). Produk lain masih bisa dipilih lewat pencarian produk di halaman Master Produk.',
            ]);
        }
    }

    /** [B-15c] Jumlah produk yang lolos filter dropdown — 1 query, hanya saat modal dibuka. */
    private function totalProdukCabang(): int
    {
        return Produk::where('is_active', true)
            ->where(fn ($q) => $this->scopeStokCabangAktif($q))
            ->count();
    }

    /**
     * [B-15c] Scope cabang untuk query produk: produk yang punya stok di gudang cabang
     * aktif, atau produk yang belum punya stok sama sekali (produk baru — supaya PO
     * tidak menyembunyikan kandidat restock). Produk yang HANYA ada di gudang cabang
     * lain tidak boleh bocor ke dropdown.
     */
    private function scopeStokCabangAktif($query)
    {
        $cabangId = session('cabang_id');

        return $query->where(function ($q) use ($cabangId) {
            $q->whereDoesntHave('stokItems')
                ->when($cabangId, fn ($q2) => $q2->orWhereHas(
                    'stokItems.gudang',
                    fn ($q3) => $q3->where('cabang_id', $cabangId)
                ));
        });
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
        $cabangId = session('cabang_id');

        // [B-15c] Gudang: WAJIB scope cabang (sebelumnya `where is_active` saja →
        // gudang cabang lain bocor ke dropdown tujuan PO) + batas pengaman.
        $gudangs = Gudang::where('is_active', true)
            ->when($cabangId, fn ($q) => $q->where('cabang_id', $cabangId))
            ->orderBy('nama')
            ->limit(self::GUDANG_DROPDOWN_LIMIT)
            ->get();

        // [B-15c] Produk: scope stok cabang aktif + limit dropdown (bukan 500-2.000 baris).
        $allProducts = Produk::where('is_active', true)
            ->where(fn ($q) => $this->scopeStokCabangAktif($q))
            ->orderBy('nama')
            ->limit(self::PRODUK_DROPDOWN_LIMIT)
            ->get(['id', 'nama']); // blade cuma pakai id + nama (harga_beli di-set poProdukDipilih)

        return view('modules.wms.livewire.po-tab', [
            'gudangs' => $gudangs,
            'allProducts' => $allProducts,
            'suppliers' => Supplier::orderBy('nama')->get(),
            'poList' => PurchaseOrder::with(['supplier', 'gudangTujuan', 'items.produk'])->latest()->paginate(15, pageName: 'po'),
        ]);
    }
}
