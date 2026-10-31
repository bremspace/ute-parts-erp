<?php

namespace App\Modules\Wms\Livewire;

use App\Modules\Akunting\Models\AkunCOA;
use App\Modules\Rbac\Services\AuditService;
use App\Modules\Rbac\Services\TransactionActivityLogService;
use App\Modules\Rbac\Traits\PunyaRiwayatAktivitas;
use App\Modules\Wms\Models\Gudang;
use App\Modules\Wms\Models\Produk;
use App\Modules\Wms\Models\PurchaseOrder;
use App\Modules\Wms\Models\PurchaseOrderItem;
use App\Modules\Wms\Models\Supplier;
use App\Modules\Wms\Services\ProcurementService;
use App\Modules\Wms\Services\PurchaseOrderService;
use App\Modules\Workflow\Models\ApprovalRequest;
use App\Modules\Workflow\Services\ApprovalService;
use App\Traits\ParsesNominal;
use Illuminate\Support\Facades\DB;
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

    /** [B-15c] Pengaman jumlah gudang per cabang (normally < 10). */
    public const GUDANG_DROPDOWN_LIMIT = 100;

    // [T-10] PO dan Supplier
    public bool $showPoModal = false;

    public array $poForm = [
        'supplier_id' => null, 'gudang_tujuan_id' => null, 'metode_bayar' => 'kredit', 'akun_kas_bank' => '110-01', 'jatuh_tempo' => '',
        'items' => [],
    ];

    public bool $showSupplierModal = false;

    public array $supplierForm = [
        'nama' => '', 'kontak' => '', 'telepon' => '', 'email' => '', 'alamat' => '', 'termin_hari' => 30,
        'bank_nama' => '', 'nomor_rekening' => '', 'nama_rekening' => '', 'catatan' => '', 'kebijakan_retur' => 'faktur_asli',
    ];

    public ?int $bayarPoId = null;

    public float|string $bayarPoJumlah = 0;

    public string $bayarPoAkun = '110-01';

    // Detail PO Modal
    public ?int $detailPoId = null;

    // Edit Supplier Modal
    public ?int $editSupplierId = null;

    public array $editSupplierForm = [
        'nama' => '', 'kontak' => '', 'telepon' => '', 'email' => '', 'alamat' => '', 'termin_hari' => 30,
        'bank_nama' => '', 'nomor_rekening' => '', 'nama_rekening' => '', 'catatan' => '', 'kebijakan_retur' => 'faktur_asli', 'is_active' => true,
    ];

    // Detail & Profil Lengkap Supplier
    public ?int $detailSupplierId = null;

    public bool $showSupplierProfileModal = false;

    public string $supplierProfileTab = 'profil'; // profil | saldo | po | retur

    // [PROCUREMENT MODAL: ABC, ROP, Min-Max, JIT]
    public bool $showProcurementModal = false;

    // [HISTORI PEMBELIAN SUPPLIER MODAL]
    public bool $showHistoriModal = false;

    public ?int $historiProdukId = null;

    public ?string $historiProdukNama = '';

    public array $historiPembelianData = [];

    public array $historiStatistik = [];

    public array $procurementFilter = [
        'abc_class' => '',
        'only_reorder' => true,
        'is_ondemand' => '',
        'search' => '',
        'urgensi' => '',
    ];

    public function mount(): void
    {
        if (session('wms_buka_po_modal')) {
            $this->openPoModal();
        }
        if (session('wms_buka_supplier_modal')) {
            $this->openSupplierModal();
        }
        if (session('wms_buka_procurement_modal')) {
            $this->openProcurementModal();
        }
        if ($produkId = session('wms_order_produk_id')) {
            $this->orderPoProduk((int) $produkId);
        }
    }

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
        $produk = Produk::with(['skuVariants', 'satuanKonversi', 'supplier'])->find($produkId);
        if (! $produk) {
            return;
        }

        $cabangId = session('cabang_id');
        $defaultGudangId = Gudang::where('cabang_id', $cabangId)->first()?->id;

        $daftarSatuan = $produk->getDaftarSatuan();
        $satuanBeli = $produk->satuanKonversi->firstWhere('is_satuan_beli', true);
        $satuanKode = $satuanBeli?->satuan_kode ?: ($produk->satuan ?: 'pcs');
        $satuanData = collect($daftarSatuan)->firstWhere('satuan_kode', $satuanKode) ?: $daftarSatuan[0];

        $konversi = max(0.0001, (float) ($satuanData['faktor_konversi'] ?? 1));
        $hargaBeli = ! empty($satuanData['harga_beli']) ? (float) $satuanData['harga_beli'] : (float) $produk->harga_beli;

        $this->poForm = [
            'supplier_id' => $produk->supplier_id,
            'gudang_tujuan_id' => $defaultGudangId,
            'metode_bayar' => 'kredit',
            'akun_kas_bank' => '110-01',
            'jatuh_tempo' => '',
            'items' => [
                [
                    'produk_id' => $produk->id,
                    'produk_nama' => $produk->nama,
                    'sku_variant_id' => $produk->skuVariants()->first()?->id,
                    'satuan' => $satuanKode,
                    'konversi' => $konversi,
                    'jumlah_dasar' => max(1, $qty) * $konversi,
                    'daftar_satuan' => $daftarSatuan,
                    'harga_beli' => $hargaBeli,
                    'jumlah' => max(1, $qty),
                ],
            ],
        ];

        $this->showProcurementModal = false;
        $this->showPoModal = true;
        $this->dispatch('alert', [
            'type' => 'info',
            'message' => "Produk {$produk->nama} ({$qty} {$satuanKode}) dimasukkan ke usulan PO",
        ]);
    }

    public function masukkanSemuaRekomendasiKePo(): void
    {
        if (! $this->boleh('wms.create', 'Anda tidak memiliki izin membuat PO.')) {
            return;
        }

        $cabangId = session('cabang_id');
        $defaultGudang = Gudang::where('cabang_id', $cabangId)->first();
        if (! $defaultGudang) {
            $this->dispatch('alert', ['type' => 'error', 'message' => 'Gudang tujuan belum tersedia di cabang ini.']);

            return;
        }

        $service = app(ProcurementService::class);
        $filterPayload = [
            'abc_class' => $this->procurementFilter['abc_class'],
            'only_reorder' => true,
            'is_ondemand' => $this->procurementFilter['is_ondemand'] !== '' ? filter_var($this->procurementFilter['is_ondemand'], FILTER_VALIDATE_BOOLEAN) : null,
            'search' => $this->procurementFilter['search'],
            'urgensi' => $this->procurementFilter['urgensi'] ?? '',
        ];
        $items = $service->getProcurementRecommendations($cabangId, $filterPayload);

        // Kelompokkan item rekomendasi per supplier
        $itemsPerSupplier = [];
        foreach ($items as $item) {
            if ($item['action'] === 'ORDER' && $item['recommended_order'] > 0) {
                $p = Produk::with(['skuVariants', 'satuanKonversi', 'supplier'])->find($item['produk_id']);
                if ($p) {
                    $supId = $p->supplier_id ?: 0;
                    $itemsPerSupplier[$supId][] = [
                        'produk' => $p,
                        'qty' => (int) $item['recommended_order'],
                    ];
                }
            }
        }

        if (empty($itemsPerSupplier)) {
            $this->dispatch('alert', ['type' => 'warning', 'message' => 'Tidak ada item rekomendasi yang perlu dipesan.']);

            return;
        }

        $createdPoCount = 0;
        $unassignedItems = [];

        DB::transaction(function () use ($itemsPerSupplier, $defaultGudang, &$createdPoCount, &$unassignedItems) {
            foreach ($itemsPerSupplier as $supplierId => $groupedItems) {
                if ($supplierId === 0) {
                    $unassignedItems = array_merge($unassignedItems, $groupedItems);

                    continue;
                }

                $supplier = Supplier::find($supplierId);
                if (! $supplier) {
                    $unassignedItems = array_merge($unassignedItems, $groupedItems);

                    continue;
                }

                $today = now()->format('Ymd');
                $count = PurchaseOrder::whereDate('created_at', now()->toDateString())->count() + 1;
                $noPo = sprintf('PO-%s-%03d', $today, $count);

                $total = 0;
                $poItemsData = [];
                foreach ($groupedItems as $gItem) {
                    $prod = $gItem['produk'];
                    $qty = $gItem['qty'];

                    $daftarSatuan = $prod->getDaftarSatuan();
                    $satuanBeli = $prod->satuanKonversi->firstWhere('is_satuan_beli', true);
                    $satuanKode = $satuanBeli?->satuan_kode ?: ($prod->satuan ?: 'pcs');
                    $satuanData = collect($daftarSatuan)->firstWhere('satuan_kode', $satuanKode) ?: $daftarSatuan[0];

                    $konversi = max(0.0001, (float) ($satuanData['faktor_konversi'] ?? 1));
                    $hargaBeli = ! empty($satuanData['harga_beli']) ? (float) $satuanData['harga_beli'] : (float) $prod->harga_beli;
                    $subtotal = $hargaBeli * $qty;
                    $total += $subtotal;

                    $poItemsData[] = [
                        'produk_id' => $prod->id,
                        'sku_variant_id' => $prod->skuVariants()->first()?->id,
                        'satuan' => $satuanKode,
                        'konversi' => $konversi,
                        'harga_beli' => $hargaBeli,
                        'jumlah' => $qty,
                        'jumlah_dasar' => $qty * $konversi,
                        'subtotal' => $subtotal,
                    ];
                }

                $po = PurchaseOrder::create([
                    'no_po' => $noPo,
                    'supplier_id' => $supplier->id,
                    'gudang_tujuan_id' => $defaultGudang->id,
                    'status' => 'draft',
                    'metode_bayar' => 'kredit',
                    'jatuh_tempo' => now()->addDays((int) ($supplier->termin_hari ?? 30))->toDateString(),
                    'total' => $total,
                    'total_dibayar' => 0,
                    'catatan' => 'Auto-generate rekomendasi pengadaan WMS',
                ]);

                foreach ($poItemsData as $pid) {
                    $pid['purchase_order_id'] = $po->id;
                    PurchaseOrderItem::create($pid);
                }

                // [F1-4] Catat log aktivitas PO berbasis Transaction-Centric + Dual-Indexing di metadata
                app(TransactionActivityLogService::class)->catatPurchaseOrder($po, 'po_dibuat');

                $createdPoCount++;
            }
        });

        $this->showProcurementModal = false;

        if ($createdPoCount > 0 && empty($unassignedItems)) {
            $this->dispatch('alert', [
                'type' => 'success',
                'message' => "Berhasil memisahkan & membuat {$createdPoCount} Draft PO per supplier!",
            ]);
        } elseif (! empty($unassignedItems)) {
            // Siapkan unassigned items ke poForm agar pengguna memilih supplier
            $formItems = [];
            foreach ($unassignedItems as $uItem) {
                $p = $uItem['produk'];
                $qty = $uItem['qty'];
                $daftarSatuan = $p->getDaftarSatuan();
                $satuanKode = $p->satuan ?: 'pcs';
                $formItems[] = [
                    'produk_id' => $p->id,
                    'produk_nama' => $p->nama,
                    'sku_variant_id' => $p->skuVariants()->first()?->id,
                    'satuan' => $satuanKode,
                    'konversi' => 1,
                    'jumlah_dasar' => $qty,
                    'daftar_satuan' => $daftarSatuan,
                    'harga_beli' => (float) $p->harga_beli,
                    'jumlah' => $qty,
                ];
            }

            $this->poForm = [
                'supplier_id' => null,
                'gudang_tujuan_id' => $defaultGudang->id,
                'metode_bayar' => 'kredit',
                'akun_kas_bank' => '110-01',
                'jatuh_tempo' => '',
                'items' => $formItems,
            ];

            $this->showPoModal = true;

            $msg = $createdPoCount > 0
                ? "Dibuat {$createdPoCount} Draft PO per supplier. Terdapat ".count($formItems).' item rekomendasi berhasil dimasukkan ke form PO manual.'
                : count($formItems).' produk rekomendasi berhasil dimasukkan ke form PO!';

            $this->dispatch('alert', [
                'type' => 'success',
                'message' => $msg,
            ]);
        }
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
            'items' => [['produk_id' => null, 'produk_nama' => '', 'sku_variant_id' => null, 'satuan' => 'pcs', 'konversi' => 1, 'jumlah_dasar' => 1, 'daftar_satuan' => [], 'harga_beli' => 0, 'jumlah' => 1]],
        ];
        $this->showPoModal = true;
    }

    public function updatedPoFormSupplierId($val): void
    {
        if (empty($val) || ($this->poForm['metode_bayar'] ?? 'kredit') !== 'kredit') {
            return;
        }

        $supplier = Supplier::find($val);
        if ($supplier) {
            $termin = (int) ($supplier->termin_hari ?: 30);
            $this->poForm['jatuh_tempo'] = now()->addDays($termin)->toDateString();
        }
    }

    public function updatedPoFormMetodeBayar($val): void
    {
        if ($val === 'kredit' && ! empty($this->poForm['supplier_id'])) {
            $supplier = Supplier::find($this->poForm['supplier_id']);
            $termin = (int) ($supplier?->termin_hari ?: 30);
            $this->poForm['jatuh_tempo'] = now()->addDays($termin)->toDateString();
        } elseif ($val === 'tunai') {
            $this->poForm['jatuh_tempo'] = '';
        }
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
        $this->poForm['items'][] = [
            'produk_id' => null,
            'produk_nama' => '',
            'sku_variant_id' => null,
            'satuan' => 'pcs',
            'konversi' => 1,
            'jumlah_dasar' => 1,
            'daftar_satuan' => [],
            'harga_beli' => 0,
            'jumlah' => 1,
        ];
    }

    public function removePoItem(int $idx)
    {
        unset($this->poForm['items'][$idx]);
        $this->poForm['items'] = array_values($this->poForm['items']);
    }

    #[On('produk-dipilih-global')]
    public function onProdukDipilihGlobal($targetIndex = null, $context = null, $produkId = null): void
    {
        $idx = null;
        if (is_array($targetIndex)) {
            $context = $targetIndex['context'] ?? $context;
            $produkId = $targetIndex['produkId'] ?? $produkId;
            $idx = $targetIndex['targetIndex'] ?? null;
        } else {
            $idx = $targetIndex;
        }

        if ($context !== 'po') {
            return;
        }

        if ($idx !== null && isset($this->poForm['items'][$idx]) && $produkId) {
            $produk = Produk::find($produkId);
            if ($produk) {
                $this->poForm['items'][$idx]['produk_id'] = $produkId;
                $this->poForm['items'][$idx]['produk_nama'] = $produk->nama;
                $this->poProdukDipilih($idx);
            }
        }
    }

    #[On('buka-pencarian-produk')]
    public function bukaPencarianProduk($targetIndex = null, $context = 'po'): void
    {
        if (is_array($targetIndex)) {
            $context = $targetIndex['context'] ?? $context;
            $targetIndex = $targetIndex['targetIndex'] ?? null;
        }

        if ($context !== 'po') {
            return;
        }

        $this->dispatch('open-global-product-search', [
            'targetIndex' => $targetIndex,
            'context' => $context,
            'supplierId' => ! empty($this->poForm['supplier_id']) ? (int) $this->poForm['supplier_id'] : null,
        ])->to(ProductSearchModal::class);
    }

    public function poProdukDipilih(int $idx)
    {
        $produk = Produk::with('satuanKonversi')->find($this->poForm['items'][$idx]['produk_id']);
        if ($produk) {
            // Jika supplier PO belum dipilih dan produk memiliki default supplier terdaftar, otomatis gunakan supplier tersebut
            if (empty($this->poForm['supplier_id']) && ! empty($produk->supplier_id)) {
                $this->poForm['supplier_id'] = $produk->supplier_id;
            }

            $supplierId = ! empty($this->poForm['supplier_id']) ? (int) $this->poForm['supplier_id'] : null;
            $histori = app(ProcurementService::class)->getHistoriPembelian($produk->id, $supplierId);

            if (! empty($histori['items'])) {
                // Gunakan harga beli terakhir dari supplier ini (atau supplier sebelumnya)
                $baseHarga = (float) $histori['items'][0]['harga_beli'];
                $this->poForm['items'][$idx]['info_terakhir'] = 'Terakhir: Rp '.number_format($baseHarga, 0, ',', '.').' ('.$histori['items'][0]['supplier_nama'].')';
            } else {
                $baseHarga = (float) $produk->harga_beli;
                $this->poForm['items'][$idx]['info_terakhir'] = null;
            }

            $daftarSatuan = $produk->getDaftarSatuan();
            $this->poForm['items'][$idx]['daftar_satuan'] = $daftarSatuan;

            // Prioritaskan satuan beli jika ada
            $satuanBeli = $produk->satuanKonversi->firstWhere('is_satuan_beli', true);
            $satuanKode = $satuanBeli?->satuan_kode ?: ($produk->satuan ?: 'pcs');
            $satuanData = collect($daftarSatuan)->firstWhere('satuan_kode', $satuanKode) ?: $daftarSatuan[0];

            $konversi = max(0.0001, (float) ($satuanData['faktor_konversi'] ?? 1));
            $this->poForm['items'][$idx]['satuan'] = $satuanData['satuan_kode'];
            $this->poForm['items'][$idx]['konversi'] = $konversi;

            if (! empty($satuanData['harga_beli']) && empty($satuanData['is_dasar'])) {
                $this->poForm['items'][$idx]['harga_beli'] = (float) $satuanData['harga_beli'];
            } else {
                $this->poForm['items'][$idx]['harga_beli'] = round($baseHarga * $konversi, 2);
            }

            $jumlah = (float) ($this->poForm['items'][$idx]['jumlah'] ?? 1);
            $this->poForm['items'][$idx]['jumlah_dasar'] = round($jumlah * $konversi, 2);
            $this->poForm['items'][$idx]['sku_variant_id'] = $produk->skuVariants()->first()?->id;
        }
    }

    public function onPoSatuanChanged(int $idx): void
    {
        if (! isset($this->poForm['items'][$idx])) {
            return;
        }

        $item = &$this->poForm['items'][$idx];
        $produkId = $item['produk_id'] ?? null;
        if (! $produkId) {
            return;
        }

        $produk = Produk::with('satuanKonversi')->find($produkId);
        if (! $produk) {
            return;
        }

        $daftarSatuan = ! empty($item['daftar_satuan']) ? $item['daftar_satuan'] : $produk->getDaftarSatuan();
        $satuanDipilih = $item['satuan'] ?? ($produk->satuan ?: 'pcs');
        $satuanData = collect($daftarSatuan)->firstWhere('satuan_kode', $satuanDipilih)
            ?: collect($daftarSatuan)->firstWhere('satuan_kode', $produk->satuan)
            ?: ['faktor_konversi' => 1, 'harga_beli' => (float) $produk->harga_beli, 'satuan_kode' => $satuanDipilih];

        $konversi = max(0.0001, (float) ($satuanData['faktor_konversi'] ?? 1));
        $item['konversi'] = $konversi;

        if (! empty($satuanData['harga_beli']) && empty($satuanData['is_dasar'])) {
            $item['harga_beli'] = (float) $satuanData['harga_beli'];
        } else {
            $item['harga_beli'] = round((float) $produk->harga_beli * $konversi, 2);
        }

        $jumlah = (float) ($item['jumlah'] ?? 1);
        $item['jumlah_dasar'] = round($jumlah * $konversi, 2);
    }

    public function bukaHistoriPembelian(int $produkId, ?string $produkNama = null): void
    {
        $produk = Produk::find($produkId);
        if (! $produk) {
            return;
        }

        $this->historiProdukId = $produk->id;
        $this->historiProdukNama = $produkNama ?: $produk->nama;
        $data = app(ProcurementService::class)->getHistoriPembelian($produkId);
        $this->historiPembelianData = $data['items'];
        $this->historiStatistik = $data['statistik'];
        $this->showHistoriModal = true;
    }

    public function tutupHistoriPembelian(): void
    {
        $this->showHistoriModal = false;
        $this->historiProdukId = null;
        $this->historiProdukNama = '';
        $this->historiPembelianData = [];
        $this->historiStatistik = [];
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
            'poForm.items.*.produk_id' => 'required|exists:produk,id',
            'poForm.items.*.jumlah' => 'required|numeric|min:1',
            'poForm.items.*.harga_beli' => 'required|numeric|min:0',
        ], [
            'poForm.supplier_id.required' => 'Supplier wajib dipilih.',
            'poForm.supplier_id.exists' => 'Supplier tidak valid.',
            'poForm.gudang_tujuan_id.required' => 'Gudang tujuan wajib dipilih.',
            'poForm.gudang_tujuan_id.exists' => 'Gudang tujuan tidak valid.',
            'poForm.metode_bayar.required' => 'Metode pembayaran wajib dipilih.',
            'poForm.items.required' => 'Minimal 1 item produk harus ditambahkan.',
            'poForm.items.min' => 'Minimal 1 item produk harus ditambahkan.',
            'poForm.items.*.produk_id.required' => 'Produk pada baris PO wajib dipilih.',
            'poForm.items.*.produk_id.exists' => 'Produk tidak ditemukan.',
            'poForm.items.*.jumlah.required' => 'Jumlah (Qty) wajib diisi.',
            'poForm.items.*.jumlah.min' => 'Jumlah (Qty) minimal 1.',
            'poForm.items.*.harga_beli.required' => 'Harga beli wajib diisi.',
            'poForm.items.*.harga_beli.min' => 'Harga beli tidak boleh negatif.',
        ]);

        $gudang = Gudang::find($this->poForm['gudang_tujuan_id']);
        $cabangId = session('cabang_id');
        $user = auth()->user();
        if ($cabangId && $gudang && ! isSuperAdminOrOwner($user) && (int) $gudang->cabang_id !== (int) $cabangId) {
            $this->dispatch('alert', ['type' => 'error', 'message' => 'Gudang tujuan PO bukan milik cabang aktif.']);

            return;
        }

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
            'jatuh_tempo' => $this->poForm['metode_bayar'] === 'kredit'
                ? ($this->poForm['jatuh_tempo'] ?: now()->addDays((int) Supplier::find($this->poForm['supplier_id'])?->termin_hari ?? 30)->toDateString())
                : null,
            'total' => $total,
            'total_dibayar' => 0,
        ]);

        foreach ($this->poForm['items'] as $i) {
            $harga = $this->parseNominal($i['harga_beli'] ?? 0);
            $jumlah = (float) ($i['jumlah'] ?? 1);
            $konversi = max(0.0001, (float) ($i['konversi'] ?? 1));
            $jumlahDasar = round($jumlah * $konversi, 2);

            PurchaseOrderItem::create([
                'purchase_order_id' => $po->id,
                'produk_id' => $i['produk_id'],
                'sku_variant_id' => $i['sku_variant_id'] ?? null,
                'satuan' => $i['satuan'] ?? null,
                'konversi' => $konversi,
                'harga_beli' => $harga,
                'jumlah' => $jumlah,
                'jumlah_dasar' => $jumlahDasar,
                'subtotal' => $harga * $jumlah,
            ]);
        }

        // [F1-4] Catat log aktivitas PO berbasis Transaction-Centric + Dual-Indexing di metadata
        app(TransactionActivityLogService::class)->catatPurchaseOrder($po, 'po_dibuat');

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
        $po = PurchaseOrder::find($id);
        if (! $po) {
            return;
        }

        if ($po->status !== 'dikirim') {
            $this->dispatch('alert', [
                'type' => 'error',
                'message' => "PO {$po->no_po} berstatus '{$po->status}'. Hanya PO berstatus 'dikirim' yang dapat diterima via GRN.",
            ]);

            return;
        }

        // Pindah ke tab GRN dan buka modal input GRN untuk PO ini
        session()->flash('wms_grn_po_id', $id);
        $this->dispatch('wms-pindah-tab', tab: 'grn');
        $this->dispatch('buka-grn-po', poId: $id);
        $this->dispatch('alert', [
            'type' => 'info',
            'message' => "Membuka penerimaan GRN untuk PO {$po->no_po}",
        ]);
    }

    public function batalkanPo(int $id, ?string $alasan = null): void
    {
        $po = PurchaseOrder::with('gudangTujuan')->find($id);
        if (! $po) {
            return;
        }

        $this->tolakJikaBukanCabang($po->gudangTujuan?->cabang_id, 'PO bukan milik cabang aktif');

        if (! in_array($po->status, ['draft', 'dikirim'], true)) {
            $this->dispatch('alert', [
                'type' => 'error',
                'message' => "PO {$po->no_po} tidak dapat dibatalkan karena berstatus {$po->status}.",
            ]);

            return;
        }

        $alasanFinal = trim((string) $alasan) ?: 'Dibatalkan oleh purchasing/gudang';
        $catatanLama = $po->catatan ? $po->catatan.' | ' : '';

        // Batalkan approval request jika ada yang masih pending
        ApprovalRequest::where('entity_type', 'po')
            ->where('entity_id', $po->id)
            ->where('status', 'pending')
            ->update([
                'status' => 'rejected',
                'actioned_by' => auth()->id(),
                'actioned_at' => now(),
                'catatan' => 'PO dibatalkan sebelum disetujui: '.$alasanFinal,
            ]);

        $po->update([
            'status' => 'dibatalkan',
            'catatan' => $catatanLama.'[Dibatalkan: '.$alasanFinal.']',
        ]);

        $this->dispatch('alert', [
            'type' => 'warning',
            'message' => "PO {$po->no_po} berhasil dibatalkan: {$alasanFinal}",
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
            'email' => $supplier->email ?? '',
            'alamat' => $supplier->alamat ?? '',
            'termin_hari' => (int) $supplier->termin_hari,
            'bank_nama' => $supplier->bank_nama ?? '',
            'nomor_rekening' => $supplier->nomor_rekening ?? '',
            'nama_rekening' => $supplier->nama_rekening ?? '',
            'catatan' => $supplier->catatan ?? '',
            'kebijakan_retur' => $supplier->kebijakan_retur ?? 'faktur_asli',
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
            'editSupplierForm.email' => 'nullable|email|max:191',
            'editSupplierForm.termin_hari' => 'nullable|integer|min:0',
            'editSupplierForm.bank_nama' => 'nullable|string|max:100',
            'editSupplierForm.nomor_rekening' => 'nullable|string|max:100',
            'editSupplierForm.nama_rekening' => 'nullable|string|max:191',
            'editSupplierForm.kebijakan_retur' => 'nullable|string|max:50',
        ]);

        $supplier = Supplier::findOrFail($this->editSupplierId);
        $supplier->update($this->editSupplierForm);
        $this->editSupplierId = null;
        $this->dispatch('alert', ['type' => 'success', 'message' => 'Profil supplier berhasil diperbarui']);
    }

    public function simpanSupplier()
    {
        if (! $this->boleh('wms.create', 'Anda tidak memiliki izin membuat supplier.')) {
            return;
        }

        $this->validate([
            'supplierForm.nama' => 'required|string|max:255',
            'supplierForm.kontak' => 'nullable|string|max:255',
            'supplierForm.telepon' => 'nullable|string|max:20',
            'supplierForm.email' => 'nullable|email|max:191',
            'supplierForm.alamat' => 'nullable|string',
            'supplierForm.termin_hari' => 'nullable|integer|min:0',
            'supplierForm.bank_nama' => 'nullable|string|max:100',
            'supplierForm.nomor_rekening' => 'nullable|string|max:100',
            'supplierForm.nama_rekening' => 'nullable|string|max:191',
            'supplierForm.kebijakan_retur' => 'nullable|string|max:50',
        ]);

        $data = $this->supplierForm;
        if (! empty($data['telepon'])) {
            $clean = preg_replace('/[^0-9+]/', '', (string) $data['telepon']);
            if (str_starts_with($clean, '+62')) {
                $clean = '0'.substr($clean, 3);
            } elseif (str_starts_with($clean, '62')) {
                $clean = '0'.substr($clean, 2);
            }
            $data['telepon'] = $clean;
        }

        $supplier = Supplier::create($data);
        app(AuditService::class)->catat('Supplier', 'create', $supplier->id, "Supplier {$supplier->nama} ditambahkan via PO");

        if ($this->showPoModal) {
            $this->poForm['supplier_id'] = $supplier->id;
        }
        $this->showSupplierModal = false;
        $this->supplierForm = [
            'nama' => '', 'kontak' => '', 'telepon' => '', 'email' => '', 'alamat' => '', 'termin_hari' => 30,
            'bank_nama' => '', 'nomor_rekening' => '', 'nama_rekening' => '', 'catatan' => '', 'kebijakan_retur' => 'faktur_asli',
        ];
        $this->dispatch('alert', ['type' => 'success', 'message' => 'Supplier '.$supplier->nama.' berhasil ditambahkan dan dipilih']);
    }

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

    public function hapusSupplier(int $id): void
    {
        $user = auth()->user();
        if (! isSuperAdminOrOwner($user) && ! ($user && ($user->hasRole('finance') || $user->can('wms.create')))) {
            $this->dispatch('alert', ['type' => 'error', 'message' => 'Hanya Super Admin, Owner, atau Finance yang berhak menghapus supplier.']);

            return;
        }

        $supplier = Supplier::find($id);
        if (! $supplier) {
            $this->dispatch('alert', ['type' => 'error', 'message' => 'Supplier tidak ditemukan.']);

            return;
        }

        if ($supplier->purchaseOrders()->exists()) {
            $supplier->update(['is_active' => false]);
            activity()
                ->performedOn($supplier)
                ->causedBy($user)
                ->log("Supplier '{$supplier->nama}' dinonaktifkan karena memiliki riwayat Purchase Order.");

            $this->dispatch('alert', ['type' => 'warning', 'message' => "Supplier '{$supplier->nama}' memiliki riwayat PO terkait, status diubah menjadi Nonaktif."]);

            return;
        }

        $nama = $supplier->nama;
        $supplier->delete();
        $this->dispatch('alert', ['type' => 'success', 'message' => "Supplier '{$nama}' berhasil dihapus."]);
    }

    protected function tolakJikaBukanCabang(int|string|null $recordCabangId, string $pesan): void
    {
        $cabangId = session('cabang_id');

        if ($cabangId !== null && $cabangId !== '' && $recordCabangId !== null && (int) $recordCabangId !== (int) $cabangId) {
            abort(403, $pesan);
        }
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

        // Produk: tidak perlu lagi load semua produk ke dropdown.
        // Pencarian produk global via ProductSearchModal.
        $allProducts = collect();

        $procurementData = null;
        $procurementSummary = null;

        if ($this->showProcurementModal) {
            $service = app(ProcurementService::class);
            $filterPayload = [
                'abc_class' => $this->procurementFilter['abc_class'],
                'only_reorder' => $this->procurementFilter['only_reorder'],
                'is_ondemand' => $this->procurementFilter['is_ondemand'] !== '' ? filter_var($this->procurementFilter['is_ondemand'], FILTER_VALIDATE_BOOLEAN) : null,
                'search' => $this->procurementFilter['search'],
                'urgensi' => $this->procurementFilter['urgensi'] ?? '',
            ];
            $res = $service->getProcurementDataAndSummary($cabangId, $filterPayload);
            $procurementData = $res['recommendations'];
            $procurementSummary = $res['summary'];
        }

        $detailPo = null;
        if ($this->detailPoId) {
            $detailPo = PurchaseOrder::with(['supplier', 'gudangTujuan', 'items.produk', 'pembayaran.user'])
                ->find($this->detailPoId);
        }

        $totalEstimasiPo = 0;
        foreach ($this->poForm['items'] ?? [] as $i) {
            $h = $this->parseNominal($i['harga_beli'] ?? 0);
            $qty = (float) ($i['jumlah'] ?? 0);
            $totalEstimasiPo += ($h * $qty);
        }

        $supplierProfil = null;
        if ($this->detailSupplierId) {
            $supplierProfil = Supplier::with([
                'purchaseOrders.items.produk',
                'returnPembelians.items.produk',
                'saldoMutasi.user',
                'produks',
            ])->find($this->detailSupplierId);
        }

        return view('modules.wms.livewire.po-tab', [
            'gudangs' => $gudangs,
            'allProducts' => $allProducts,
            'suppliers' => Supplier::orderBy('nama')->get(),
            'poList' => PurchaseOrder::whereHas('gudangTujuan', function ($q) use ($cabangId) {
                $q->where('is_active', true)
                    ->when($cabangId, fn ($qq) => $qq->where('cabang_id', $cabangId));
            })
                ->with(['supplier', 'gudangTujuan', 'items.produk'])
                ->latest()
                ->paginate(15, pageName: 'po'),
            'detailPo' => $detailPo,
            'procurementData' => $procurementData,
            'procurementSummary' => $procurementSummary,
            'akunKasBankList' => AkunCOA::whereIn('kelompok', ['kas', 'bank'])->where('is_active', true)->orderBy('kode')->get(),
            'totalEstimasiPo' => $totalEstimasiPo,
            'supplierProfil' => $supplierProfil,
        ]);
    }
}
