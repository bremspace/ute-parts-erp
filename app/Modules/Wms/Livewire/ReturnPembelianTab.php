<?php

namespace App\Modules\Wms\Livewire;

use App\Modules\Pos\Models\ReturnPenjualanItem;
use App\Modules\Rbac\Traits\PunyaRiwayatAktivitas;
use App\Modules\Wms\Models\Gudang;
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

    // State Pengiriman & Tracking (Tahap 2)
    public string $kirimEkspedisi = '';

    public string $kirimNoResi = '';

    public string $kirimPenerimaVendor = '';

    public string $kirimCatatan = '';

    public float|string $kirimNilaiRealisasi = 0;

    public string $kirimOpsiPenyelesaian = 'saldo';

    public string $kirimKebijakanRetur = 'faktur_asli'; // faktur_asli | harga_pasar | tukar_unit

    // State Penyelesaian / Penerimaan Hasil Vendor (Tahap 3)
    public string $hasilTipe = 'tukar_unit'; // tukar_unit | finansial | tolak

    public string $hasilFinansialMetode = 'saldo'; // saldo | utang | kas

    public float|string $hasilFinansialRealisasi = 0;

    public string $hasilCatatan = '';

    public array $hasilReplacementSn = []; // [produkId => 'SN1, SN2']

    public string $tolakOpsiBarang = 'kembali_ke_toko'; // kembali_ke_toko | scrap_vendor

    // State untuk Retur Pool Karantina
    public array $selectedPoolItemIds = [];

    public bool $showPoolReturModal = false;

    public ?int $selectedPoolSupplierId = null;

    public string $poolAlasan = 'Retur akumulasi barang rusak dari garansi toko';

    public string $poolMetodePengembalian = 'saldo'; // saldo | utang | kas

    // State Profil & Riwayat Lengkap Supplier
    public ?int $detailSupplierId = null;

    public bool $showSupplierProfileModal = false;

    public string $supplierProfileTab = 'profil'; // profil | saldo | po | retur

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
        $this->kirimEkspedisi = '';
        $this->kirimNoResi = '';
        $this->kirimPenerimaVendor = '';
        $this->kirimCatatan = '';
        $this->hasilTipe = 'tukar_unit';
        $this->hasilFinansialMetode = 'saldo';
        $this->hasilFinansialRealisasi = 0;
        $this->hasilCatatan = '';
        $this->hasilReplacementSn = [];
        $this->tolakOpsiBarang = 'kembali_ke_toko';
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
        $retur = ReturnPembelian::with('items')->find($id);
        if ($retur) {
            $this->kirimEkspedisi = $retur->ekspedisi ?: '';
            $this->kirimNoResi = $retur->no_resi ?: '';
            $this->kirimPenerimaVendor = $retur->penerima_vendor ?: '';
            $this->kirimCatatan = $retur->catatan_penyelesaian ?: '';
            $this->kirimKebijakanRetur = $retur->kebijakan_retur ?: ($retur->supplier?->kebijakan_retur ?: 'faktur_asli');
            $this->kirimNilaiRealisasi = (float) ($retur->nilai_realisasi ?? $retur->jumlah);
            $this->kirimOpsiPenyelesaian = $retur->metode_pengembalian ?: 'saldo';

            $this->hasilFinansialMetode = in_array($retur->metode_pengembalian, ['saldo', 'utang', 'kas'], true) ? $retur->metode_pengembalian : 'saldo';
            $this->hasilFinansialRealisasi = (float) ($retur->nilai_realisasi ?? $retur->jumlah);
            $this->hasilCatatan = $retur->catatan_penyelesaian ?: '';

            $this->hasilReplacementSn = [];
            foreach ($retur->items as $item) {
                $this->hasilReplacementSn[$item->produk_id] = '';
            }
        }
    }

    public function tutupDetail(): void
    {
        $this->detailReturId = null;
    }

    public function setujuiRetur(int $id): void
    {
        abort_unless(
            auth()->user()?->can('approve-workflow') || auth()->user()?->can('wms.create') || auth()->user()?->can('wms.approve') || auth()->user()?->can('wms.update'),
            403,
            'Anda tidak memiliki izin menyetujui retur pembelian.'
        );

        $retur = ReturnPembelian::findOrFail($id);

        try {
            app(ReturnPembelianService::class)->setujuiRetur($retur, auth()->id());
            $this->dispatch('alert', ['type' => 'success', 'message' => "Retur {$retur->no_return} berhasil disetujui."]);
        } catch (\Exception $e) {
            $this->dispatch('alert', ['type' => 'error', 'message' => $e->getMessage()]);
        }
    }

    public function tolakRetur(int $id): void
    {
        abort_unless(
            auth()->user()?->can('approve-workflow') || auth()->user()?->can('wms.create') || auth()->user()?->can('wms.approve') || auth()->user()?->can('wms.update'),
            403,
            'Anda tidak memiliki izin menolak retur pembelian.'
        );

        $retur = ReturnPembelian::findOrFail($id);

        try {
            app(ReturnPembelianService::class)->tolakRetur($retur, 'Ditolak via approval panel');
            $this->dispatch('alert', ['type' => 'info', 'message' => "Retur {$retur->no_return} telah ditolak."]);
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

        $po = PurchaseOrder::with(['items', 'supplier', 'gudangTujuan'])->findOrFail($this->selectedPoId);

        $items = [];
        foreach ($this->itemInputs as $key => $input) {
            $qty = (float) ($input['jumlah'] ?? 0);
            if ($qty > 0) {
                $poItemId = $input['purchase_order_item_id'] ?? $key;
                $poItem = $po->items->firstWhere('id', $poItemId);
                if (! $poItem) {
                    continue;
                }

                $maxQty = (float) $poItem->jumlah;
                if ($qty > $maxQty) {
                    $this->dispatch('alert', [
                        'type' => 'error',
                        'message' => "Jumlah retur untuk item #{$poItemId} melebihi jumlah PO ({$maxQty}).",
                    ]);

                    return;
                }

                $snList = [];
                if (! empty($input['sn_raw'])) {
                    $snList = preg_split('/[\r\n,;]+/', (string) $input['sn_raw']) ?: [];
                    $snList = array_values(array_filter(array_map('trim', $snList)));
                }

                $items[] = [
                    'purchase_order_item_id' => $poItem->id,
                    'jumlah' => $qty,
                    'harga_beli' => (float) $poItem->harga_beli,
                    'sn' => $snList,
                ];
            }
        }

        if (empty($items)) {
            $this->dispatch('alert', ['type' => 'error', 'message' => 'Pilih minimal satu item untuk diretur dengan jumlah > 0.']);

            return;
        }

        try {
            $cabangId = session('cabang_id');
            $gudangId = $po->gudang_tujuan_id ?? Gudang::where('cabang_id', $cabangId)->value('id');

            $retur = app(ReturnPembelianService::class)->buatRetur(
                $po,
                $items,
                $this->alasan,
                $this->metodePengembalian,
                $cabangId,
                $gudangId,
                auth()->id()
            );

            $this->dispatch('alert', [
                'type' => 'success',
                'message' => "Draft Retur {$retur->no_return} berhasil dibuat. Silakan cetak surat pengantar atau konfirmasi pengiriman.",
            ]);

            $this->resetForm();
            $this->bukaDetail($retur->id);
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
            // Buat Draft Retur Pembelian
            $retur = app(ReturnPembelianService::class)->buatReturDariPoolSupplier(
                $supplier,
                $itemIds,
                $this->poolAlasan,
                $this->poolMetodePengembalian,
                session('cabang_id'),
                null,
                auth()->id(),
                false // langsungEksekusi = false -> buat Draft dahulu
            );

            $this->dispatch('alert', [
                'type' => 'success',
                'message' => "Draft Retur {$retur->no_return} berhasil dibuat (Estimasi nilai: Rp ".number_format($retur->jumlah, 0, ',', '.').'). Silakan cetak surat jalan / konfirmasi pengiriman ke supplier.',
            ]);

            $this->selectedPoolItemIds = [];
            $this->tutupModalPoolRetur();
            $this->bukaDetail($retur->id);
        } catch (\Exception $e) {
            $this->dispatch('alert', ['type' => 'error', 'message' => $e->getMessage()]);
        }
    }

    /**
     * Tahap 1 -> Tahap 2: Konfirmasi Pengiriman Barang Fisik ke Supplier (In-Transit)
     */
    public function prosesKirimKeSupplier(int $id): void
    {
        abort_unless(auth()->user()?->can('wms.create'), 403, 'Anda tidak memiliki izin memproses pengiriman retur.');
        $retur = ReturnPembelian::findOrFail($id);

        try {
            app(ReturnPembelianService::class)->kirimKeSupplier($retur, [
                'ekspedisi' => $this->kirimEkspedisi,
                'no_resi' => $this->kirimNoResi,
                'penerima_vendor' => $this->kirimPenerimaVendor,
                'catatan' => $this->kirimCatatan,
                'kebijakan_retur' => $this->kirimKebijakanRetur,
            ], auth()->id());

            $this->dispatch('alert', [
                'type' => 'success',
                'message' => "Retur {$retur->no_return} berhasil dikirim ke supplier. Status kini In-Transit.",
            ]);

            $this->bukaDetail($retur->id);
        } catch (\Exception $e) {
            $this->dispatch('alert', ['type' => 'error', 'message' => $e->getMessage()]);
        }
    }

    /**
     * Tahap 2 -> Tahap 3: Selesaikan Penerimaan Hasil dari Supplier (Unit Baru / Kompensasi / Tolak)
     */
    public function prosesHasilVendor(int $id): void
    {
        abort_unless(auth()->user()?->can('wms.create'), 403, 'Anda tidak memiliki izin memproses penerimaan hasil retur.');
        $retur = ReturnPembelian::with('items')->findOrFail($id);

        try {
            $service = app(ReturnPembelianService::class);

            if ($this->hasilTipe === 'tukar_unit') {
                $detailSn = [];
                foreach ($this->hasilReplacementSn as $pId => $snString) {
                    if (! empty($snString)) {
                        $arr = preg_split('/[\r\n,;]+/', (string) $snString) ?: [];
                        $detailSn[$pId] = array_values(array_filter(array_map('trim', $arr)));
                    }
                }

                $service->terimaUnitPengganti($retur, [
                    'sn' => $detailSn,
                    'catatan' => $this->hasilCatatan,
                    'gudang_id' => $retur->gudang_id,
                ], auth()->id());

                $this->dispatch('alert', [
                    'type' => 'success',
                    'message' => 'Unit pengganti baru dari supplier berhasil diterima dan ditambahkan ke stok aktif.',
                ]);
            } elseif ($this->hasilTipe === 'finansial') {
                $realisasiClean = is_numeric($this->hasilFinansialRealisasi)
                    ? (float) $this->hasilFinansialRealisasi
                    : (float) preg_replace('/[^0-9.]/', '', str_replace(',', '.', (string) $this->hasilFinansialRealisasi));

                $service->selesaikanKompensasiFinansial(
                    $retur,
                    $realisasiClean,
                    $this->hasilFinansialMetode,
                    $this->hasilCatatan,
                    auth()->id()
                );

                $this->dispatch('alert', [
                    'type' => 'success',
                    'message' => "Kompensasi finansial retur {$retur->no_return} berhasil dibukukan.",
                ]);
            } elseif ($this->hasilTipe === 'tolak') {
                $service->tolakReturSupplier(
                    $retur,
                    $this->hasilCatatan ?: 'Klaim retur ditolak oleh supplier',
                    $this->tolakOpsiBarang,
                    auth()->id()
                );

                $this->dispatch('alert', [
                    'type' => 'info',
                    'message' => 'Klaim retur telah ditandai ditolak supplier.',
                ]);
            }

            $this->bukaDetail($retur->id);
        } catch (\Exception $e) {
            $this->dispatch('alert', ['type' => 'error', 'message' => $e->getMessage()]);
        }
    }

    /**
     * Konfirmasi Kirim & Selesaikan ke Supplier
     */
    public function konfirmasiKirimKeSupplier(int $id): void
    {
        abort_unless(auth()->user()?->can('wms.create'), 403, 'Anda tidak memiliki izin memproses retur.');
        $retur = ReturnPembelian::findOrFail($id);

        try {
            $realisasiClean = is_numeric($this->kirimNilaiRealisasi)
                ? (float) $this->kirimNilaiRealisasi
                : (float) preg_replace('/[^0-9.]/', '', str_replace(',', '.', (string) $this->kirimNilaiRealisasi));

            app(ReturnPembelianService::class)->kirimDanSelesaikanRetur(
                $retur,
                $this->kirimOpsiPenyelesaian,
                $this->kirimCatatan,
                auth()->id(),
                $realisasiClean,
                $this->kirimKebijakanRetur
            );

            $this->dispatch('alert', [
                'type' => 'success',
                'message' => "Retur {$retur->no_return} berhasil dikirim dan diselesaikan ke supplier {$retur->supplier?->nama}.",
            ]);

            $this->bukaDetail($retur->id);
        } catch (\Exception $e) {
            $this->dispatch('alert', ['type' => 'error', 'message' => $e->getMessage()]);
        }
    }

    /**
     * Batalkan Draft Retur
     */
    public function batalkanDraft(int $id): void
    {
        abort_unless(auth()->user()?->can('wms.create'), 403, 'Anda tidak memiliki izin membatalkan draft.');
        $retur = ReturnPembelian::findOrFail($id);

        try {
            app(ReturnPembelianService::class)->batalkanDraftRetur($retur, 'Dibatalkan oleh staf WMS');

            $this->dispatch('alert', [
                'type' => 'info',
                'message' => "Draft Retur {$retur->no_return} dibatalkan. Seluruh barang dikembalikan ke pool karantina.",
            ]);

            $this->tutupDetail();
        } catch (\Exception $e) {
            $this->dispatch('alert', ['type' => 'error', 'message' => $e->getMessage()]);
        }
    }

    // Modal Profil & Riwayat Lengkap Supplier
    public function bukaProfilSupplier(int $supplierId): void
    {
        $this->detailSupplierId = $supplierId;
        $this->supplierProfileTab = 'profil';
        $this->showSupplierProfileModal = true;
    }

    public function tutupProfilSupplier(): void
    {
        $this->showSupplierProfileModal = false;
        $this->detailSupplierId = null;
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
            $detailRetur = ReturnPembelian::with(['purchaseOrder.items.produk', 'supplier', 'items.produk', 'user', 'gudang.cabang'])
                ->find($this->detailReturId);
        }

        // Data Pool Karantina Barang Rusak
        $karantinaQuery = ReturnPenjualanItem::with(['produk', 'supplier', 'returnPenjualan'])
            ->where('status_karantina', 'karantina')
            ->when($this->filterSupplierId, fn ($q) => $q->where('supplier_id', $this->filterSupplierId));

        $karantinaItems = $karantinaQuery->latest()->get();

        $karantinaGrouped = $karantinaItems->groupBy('supplier_id');

        $suppliers = Supplier::where('is_active', true)->orderBy('nama')->get();

        $supplierProfil = null;
        if ($this->detailSupplierId) {
            $supplierProfil = Supplier::with([
                'purchaseOrders.items.produk',
                'returnPembelians.items.produk',
                'saldoMutasi.user',
                'produks',
            ])->find($this->detailSupplierId);
        }

        return view('modules.wms.livewire.return-pembelian-tab', [
            'returList' => $returList,
            'poSiapRetur' => $poSiapRetur,
            'detailRetur' => $detailRetur,
            'karantinaItems' => $karantinaItems,
            'karantinaGrouped' => $karantinaGrouped,
            'suppliers' => $suppliers,
            'supplierProfil' => $supplierProfil,
        ]);
    }
}
