<?php

namespace App\Modules\Wms\Livewire;

use App\Modules\Akunting\Models\AkunCOA;
use App\Modules\Rbac\Traits\PunyaRiwayatAktivitas;
use App\Modules\Wms\Models\Gudang;
use App\Modules\Wms\Models\Produk;
use App\Modules\Wms\Models\PurchaseOrder;
use App\Modules\Wms\Models\PurchaseOrderItem;
use App\Modules\Wms\Models\Supplier;
use App\Modules\Wms\Services\ProcurementService;
use App\Modules\Wms\Services\PurchaseOrderService;
use App\Modules\Workflow\Services\ApprovalService;
use App\Traits\ParsesNominal;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * [F1-8 / S-01] Tab "PO & Supplier" — dipecah dari WmsDashboard (paritas perilaku).
 */
class PoTab extends Component
{
    use ParsesNominal;
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
        'supplier_id' => null, 'gudang_tujuan_id' => null, 'metode_bayar' => 'kredit', 'akun_kas_bank' => '110-01', 'jatuh_tempo' => '',
        'items' => [],
    ];

    public bool $showSupplierModal = false;

    public array $supplierForm = ['nama' => '', 'kontak' => '', 'telepon' => '', 'alamat' => '', 'termin_hari' => 30];

    public ?int $bayarPoId = null;

    public float|string $bayarPoJumlah = 0;

    public string $bayarPoAkun = '110-01';

    // Detail PO Modal
    public ?int $detailPoId = null;

    // Edit Supplier Modal
    public ?int $editSupplierId = null;

    public array $editSupplierForm = [
        'nama' => '', 'kontak' => '', 'telepon' => '', 'alamat' => '', 'termin_hari' => 30, 'is_active' => true,
    ];

    // [PROCUREMENT MODAL: ABC, ROP, Min-Max, JIT]
    public bool $showProcurementModal = false;

    public array $procurementFilter = [
        'abc_class' => '',
        'only_reorder' => true,
        'is_ondemand' => '',
        'search' => '',
    ];

    #[On('wms-procurement-modal')]
    public function openProcurementModal(): void
    {
        $this->showProcurementModal = true;
    }

    public function closeProcurementModal(): void
    {
        $this->showProcurementModal = false;
    }

    public function terapkanKePo(int $produkId, int $qty): void
    {
        $produk = Produk::find($produkId);
        if (! $produk) {
            return;
        }

        $this->poForm = [
            'supplier_id' => null,
            'gudang_tujuan_id' => null,
            'metode_bayar' => 'kredit',
            'akun_kas_bank' => '110-01',
            'jatuh_tempo' => '',
            'items' => [
                [
                    'produk_id' => $produk->id,
                    'sku_variant_id' => $produk->skuVariants()->first()?->id,
                    'harga_beli' => (float) $produk->harga_beli,
                    'jumlah' => max(1, $qty),
                ],
            ],
        ];

        $this->showProcurementModal = false;
        $this->showPoModal = true;
        $this->dispatch('alert', [
            'type' => 'info',
            'message' => "Produk {$produk->nama} ({$qty} unit) dimasukkan ke usulan PO",
        ]);
    }

    #[On('order-po-produk')]
    public function orderPoProduk(int $produkId): void
    {
        $this->terapkanKePo($produkId, 1);
        $this->dispatch('wms-pindah-tab', tab: 'po');
    }

    // Quick action header "+ Buat PO" (dari shell WmsDashboard via $dispatch)
    #[On('wms-po-baru')]
    public function openPoModal()
    {
        $this->poForm = [
            'supplier_id' => null, 'gudang_tujuan_id' => null, 'metode_bayar' => 'kredit', 'akun_kas_bank' => '110-01', 'jatuh_tempo' => '',
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
        if (! $this->boleh('wms.create', 'Anda tidak memiliki izin membuat PO.')) {
            return;
        }

        $this->validate([
            'poForm.supplier_id' => 'required|exists:supplier,id',
            'poForm.gudang_tujuan_id' => 'required|exists:gudang,id',
            'poForm.metode_bayar' => 'required|in:tunai,kredit',
            'poForm.akun_kas_bank' => 'nullable|string|max:20',
            'poForm.items' => 'required|array|min:1',
        ]);

        $today = now()->format('Ymd');
        $count = PurchaseOrder::whereDate('created_at', now()->toDateString())->count() + 1;
        $noPo = sprintf('PO-%s-%03d', $today, $count);

        $total = 0;
        foreach ($this->poForm['items'] as $idx => $i) {
            $harga = $this->parseNominal($i['harga_beli'] ?? 0);
            $this->poForm['items'][$idx]['harga_beli'] = $harga;
            $total += $harga * (int) ($i['jumlah'] ?? 1);
        }

        $po = PurchaseOrder::create([
            'no_po' => $noPo,
            'supplier_id' => $this->poForm['supplier_id'],
            'gudang_tujuan_id' => $this->poForm['gudang_tujuan_id'],
            'status' => 'draft',
            'metode_bayar' => $this->poForm['metode_bayar'],
            'akun_kas_bank' => $this->poForm['metode_bayar'] === 'tunai' ? ($this->poForm['akun_kas_bank'] ?? '110-01') : null,
            'jatuh_tempo' => $this->poForm['jatuh_tempo'] ?: now()->addDays((int) Supplier::find($this->poForm['supplier_id'])?->termin_hari ?? 30)->toDateString(),
            'total' => $total,
            'total_dibayar' => 0,
        ]);

        foreach ($this->poForm['items'] as $i) {
            $harga = $this->parseNominal($i['harga_beli'] ?? 0);
            PurchaseOrderItem::create([
                'purchase_order_id' => $po->id,
                'produk_id' => $i['produk_id'],
                'sku_variant_id' => $i['sku_variant_id'] ?? null,
                'harga_beli' => $harga,
                'jumlah' => (int) ($i['jumlah'] ?? 1),
                'subtotal' => $harga * (int) ($i['jumlah'] ?? 1),
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

    public function bukaDetailPo(int $id): void
    {
        $this->detailPoId = $id;
    }

    public function tutupDetailPo(): void
    {
        $this->detailPoId = null;
    }

    public function bukaBayarPo(int $id)
    {
        if (! $this->bolehBayarPo()) {
            return;
        }

        $po = PurchaseOrder::find($id);
        $this->bayarPoId = $id;
        $this->bayarPoJumlah = (float) $po?->sisa ?? 0;
        $this->bayarPoAkun = $po?->akun_kas_bank ?: '110-01';
        $this->dispatch('alert-open-bayar-po', ['id' => $id]);
    }

    public function bayarPo()
    {
        if (! $this->bolehBayarPo()) {
            return;
        }

        $nominal = $this->parseNominal($this->bayarPoJumlah);
        if ($nominal <= 0) {
            $this->dispatch('alert', ['type' => 'error', 'message' => 'Nominal pembayaran harus lebih besar dari 0']);

            return;
        }

        try {
            app(PurchaseOrderService::class)->bayarPO(
                PurchaseOrder::findOrFail($this->bayarPoId),
                $nominal,
                auth()->id(),
                $this->bayarPoAkun
            );
            $this->dispatch('alert', ['type' => 'success', 'message' => 'Pembayaran PO tercatat — sisa utang dan pembukuan terupdate']);
            $this->bayarPoId = null;
        } catch (\Exception $e) {
            $this->dispatch('alert', ['type' => 'error', 'message' => $e->getMessage()]);
        }
    }

    protected function bolehBayarPo(): bool
    {
        $user = auth()->user();
        if ($user && ($user->hasRole('super-admin') || $user->can('utang.manage') || $user->can('akunting.create') || $user->hasRole('akuntan') || $user->hasRole('keuangan'))) {
            return true;
        }

        $this->dispatch('alert', [
            'type' => 'error',
            'message' => 'Anda tidak memiliki hak akses pembayaran PO (khusus superadmin dan bagian akuntansi / keuangan).',
        ]);

        return false;
    }

    public function bukaEditSupplier(int $id): void
    {
        if (! $this->boleh('wms.create', 'Anda tidak memiliki izin mengedit supplier.')) {
            return;
        }

        $supplier = Supplier::findOrFail($id);
        $this->editSupplierId = $supplier->id;
        $this->editSupplierForm = [
            'nama' => $supplier->nama,
            'kontak' => $supplier->kontak ?? '',
            'telepon' => $supplier->telepon ?? '',
            'alamat' => $supplier->alamat ?? '',
            'termin_hari' => (int) $supplier->termin_hari,
            'is_active' => (bool) $supplier->is_active,
        ];
    }

    public function perbaruiSupplier(): void
    {
        if (! $this->boleh('wms.create', 'Anda tidak memiliki izin mengedit supplier.')) {
            return;
        }

        $this->validate([
            'editSupplierForm.nama' => 'required|string|max:255',
            'editSupplierForm.termin_hari' => 'nullable|integer|min:0',
        ]);

        $supplier = Supplier::findOrFail($this->editSupplierId);
        $supplier->update($this->editSupplierForm);
        $this->editSupplierId = null;
        $this->dispatch('alert', ['type' => 'success', 'message' => 'Supplier berhasil diperbarui']);
    }

    public function simpanSupplier()
    {
        if (! $this->boleh('wms.create', 'Anda tidak memiliki izin membuat supplier.')) {
            return;
        }

        $this->validate([
            'supplierForm.nama' => 'required|string|max:255',
        ]);

        Supplier::create($this->supplierForm);
        $this->showSupplierModal = false;
        $this->supplierForm = ['nama' => '', 'kontak' => '', 'telepon' => '', 'alamat' => '', 'termin_hari' => 30];
        $this->dispatch('alert', ['type' => 'success', 'message' => 'Supplier disimpan']);
    }

    protected function boleh(string $permission, string $pesan = 'Anda tidak memiliki hak akses untuk tindakan ini.'): bool
    {
        if (auth()->user()?->can($permission)) {
            return true;
        }

        $this->dispatch('alert', ['type' => 'error', 'message' => $pesan]);

        return false;
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

        $procurementData = null;
        $procurementSummary = null;

        if ($this->showProcurementModal) {
            $service = app(ProcurementService::class);
            $filterPayload = [
                'abc_class' => $this->procurementFilter['abc_class'],
                'only_reorder' => $this->procurementFilter['only_reorder'],
                'is_ondemand' => $this->procurementFilter['is_ondemand'] !== '' ? filter_var($this->procurementFilter['is_ondemand'], FILTER_VALIDATE_BOOLEAN) : null,
                'search' => $this->procurementFilter['search'],
            ];
            $procurementData = $service->getProcurementRecommendations($cabangId, $filterPayload);
            $procurementSummary = $service->getProcurementSummary($cabangId);
        }

        $detailPo = null;
        if ($this->detailPoId) {
            $detailPo = PurchaseOrder::with(['supplier', 'gudangTujuan', 'items.produk', 'pembayaran.user'])
                ->find($this->detailPoId);
        }

        return view('modules.wms.livewire.po-tab', [
            'gudangs' => $gudangs,
            'allProducts' => $allProducts,
            'suppliers' => Supplier::orderBy('nama')->get(),
            'poList' => PurchaseOrder::with(['supplier', 'gudangTujuan', 'items.produk'])->latest()->paginate(15, pageName: 'po'),
            'detailPo' => $detailPo,
            'procurementData' => $procurementData,
            'procurementSummary' => $procurementSummary,
            'akunKasBankList' => AkunCOA::whereIn('kelompok', ['kas', 'bank'])->where('is_active', true)->orderBy('kode')->get(),
        ]);
    }
}
