<?php

namespace App\Modules\Wms\Livewire;

use App\Modules\Wms\Models\Gudang;
use App\Modules\Wms\Models\ImportLog;
use App\Modules\Wms\Models\KualitasProduk;
use App\Modules\Wms\Models\Brand;
use App\Modules\Wms\Models\Produk;
use App\Modules\Wms\Models\PurchaseOrder;
use App\Modules\Wms\Models\PurchaseOrderItem;
use App\Modules\Wms\Models\Rak;
use App\Modules\Wms\Models\SatuanUnit;
use App\Modules\Wms\Models\StockMutationLog;
use App\Modules\Wms\Models\StokItem;
use App\Modules\Wms\Models\StokLog;
use App\Modules\Wms\Models\StokOpname;
use App\Modules\Wms\Models\StokOpnameItem;
use App\Modules\Wms\Models\StokTransfer;
use App\Modules\Wms\Models\StokTransferItem;
use App\Modules\Wms\Models\Supplier;
use App\Modules\Wms\Models\TipeHp;
use App\Modules\Wms\Services\ImportProdukService;
use App\Modules\Wms\Services\ProdukService;
use App\Modules\Wms\Services\PurchaseOrderService;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

class WmsDashboard extends Component
{
    use WithPagination;
    use WithFileUploads;

    public string $activeTab = 'stok'; // stok, produk, transfer, opname, po

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

    // Stok Tab filters
    public string $search = '';

    public string $kategori = '';

    public ?int $filterGudangId = null;

    // Produk tab filters
    public string $produkSearch = '';

    // New Produk Modal (Tambah Produk — sinkron Akunting)
    public bool $showProdukModal = false;

    public array $produkForm = [
        'nama' => '', 'kategori' => '', 'brand_kompatibel' => '', 'model_kompatibel' => '',
        'kondisi' => 'baru', 'harga_beli' => 0, 'harga_jual_retail' => 0,
        'sku' => '', 'gudang_id' => null, 'stok_awal' => 0, 'stok_minimum' => 0,
        // [T-44]
        'brand_id' => null, 'kualitas_id' => null, 'satuan_kode' => 'pcs',
        'tipe_hp_ids' => [], 'harga_tier' => [
            'retail' => ['nominal_tetap' => 0, 'persen_diskon' => null],
            'reseller' => ['nominal_tetap' => null, 'persen_diskon' => null],
            'agen' => ['nominal_tetap' => null, 'persen_diskon' => null],
        ],
        'harga_tier_tier_id' => null,
        // [HARGA FLEKSIBEL]
        'harga_fleksibel' => false,
    ];

    // Tambah Stok Modal (pembelian — sinkron Akunting)
    public bool $showTambahStokModal = false;

    public ?int $stokProdukId = null;

    public array $tambahStokForm = [
        'gudang_id' => null, 'rak_id' => null, 'qty' => 1, 'harga_beli' => 0, 'keterangan' => 'Pembelian dari supplier',
    ];

    // [T-12] Rak management
    public bool $showRakModal = false;

    public array $rakForm = ['gudang_id' => null, 'nama' => '', 'kode' => '', 'zona' => ''];

    // [T-43] Import master produk Excel
    public bool $showImportModal = false;

    public $importFile = null;

    public string $importStep = 'upload'; // upload → preview → selesai

    public array $importPreview = [];

    public string $importFilePath = '';

    public int $importLogId = 0;

    // New Transfer Modal state
    public bool $showTransferModal = false;

    public ?int $transferGudangAsalId = null;

    public ?int $transferGudangTujuanId = null;

    public string $transferCatatan = '';

    public array $transferItems = []; // [['produk_id' => ..., 'sku_variant_id' => ..., 'jumlah' => ...]]

    // New Opname Modal state
    public bool $showOpnameModal = false;

    public ?int $opnameGudangId = null;

    public ?int $opnameRakId = null; // [T-14] scope opname per rak

    public string $opnameCatatan = '';

    public array $opnameRows = []; // [['produk_id' => ..., 'nama' => ..., 'stok_sistem' => ..., 'stok_fisik' => ...]]

    // Active Opname View / Review
    public ?int $selectedOpnameId = null;

    public function mount()
    {
        $cabangId = session('cabang_id');
        if ($cabangId) {
            $this->filterGudangId = Gudang::where('cabang_id', $cabangId)->value('id');
        }
    }

    public function updatingSearch()
    {
        $this->resetPage();
    }

    /**
     * [T-40] Guard role: tambah stok manual & stok awal produk hanya super-admin.
     * Role lain wajib lewat PO Supplier (single source of truth stok masuk).
     */
    protected function isSuperAdmin(): bool
    {
        return (bool) (auth()->user()?->hasRole('super-admin'));
    }

    // --- TRANSFER ACTIONS ---
    public function openNewTransferModal()
    {
        $this->transferGudangAsalId = $this->filterGudangId;
        $this->transferGudangTujuanId = null;
        $this->transferCatatan = '';
        $this->transferItems = [
            ['produk_id' => null, 'sku_variant_id' => null, 'rak_id' => null, 'jumlah' => 1],
        ];
        $this->showTransferModal = true;
    }

    public function addTransferRow()
    {
        $this->transferItems[] = ['produk_id' => null, 'sku_variant_id' => null, 'rak_id' => null, 'jumlah' => 1];
    }

    public function removeTransferRow(int $index)
    {
        unset($this->transferItems[$index]);
        $this->transferItems = array_values($this->transferItems);
    }

    /**
     * [T-41] Saat produk dipilih: set varian default + refresh kolom stok
     * (Stok Sumber real-time & Estimasi Stok Tujuan di blade).
     */
    public function transferProdukDipilih(int $index)
    {
        $produkId = $this->transferItems[$index]['produk_id'] ?? null;
        if (! $produkId) {
            return;
        }
        $produk = Produk::with('skuVariants')->find($produkId);
        $this->transferItems[$index]['sku_variant_id'] = $produk?->skuVariants()->first()?->id;
    }

    /**
     * [T-41] Info stok per baris form transfer: stok sumber (fisik + terkunci draft
     * pending) & estimasi stok tujuan setelah transfer — dipakai kolom UI + validasi
     * client (Alpine) + server.
     */
    protected function transferStokRows(): array
    {
        $rows = [];
        if (! $this->showTransferModal || empty($this->transferItems)) {
            return $rows;
        }

        $gudangAsalId = (int) $this->transferGudangAsalId;
        $gudangTujuanId = (int) $this->transferGudangTujuanId;
        if (! $gudangAsalId) {
            return $rows;
        }

        // [T-13] pola pendingLockedByGudang: qty draft transfer lain yg mengunci stok asal
        $lockedByKey = StokTransfer::pendingLockedByGudang($gudangAsalId);

        foreach ($this->transferItems as $idx => $row) {
            $produkId = (int) ($row['produk_id'] ?? 0);
            $variantId = $row['sku_variant_id'] ?? null;
            $key = $produkId.':'.($variantId ?? 'null');

            $stokSumber = StokItem::where('gudang_id', $gudangAsalId)
                ->where('produk_id', $produkId)
                ->where('sku_variant_id', $variantId)
                ->first();

            $stokTujuan = $gudangTujuanId
                ? StokItem::where('gudang_id', $gudangTujuanId)
                    ->where('produk_id', $produkId)
                    ->where('sku_variant_id', $variantId)
                    ->first()
                : null;

            $stokSumberQty = $stokSumber?->jumlah ?? 0;
            $stokDikunci = ($lockedByKey[$key] ?? 0);
            $stokTujuanQty = $stokTujuan?->jumlah ?? 0;
            $qty = (int) ($row['jumlah'] ?? 0);

            $rows[$idx] = [
                'stok_sumber' => $stokSumberQty,
                'stok_dikunci' => $stokDikunci,
                'stok_tersedia' => max(0, $stokSumberQty - $stokDikunci),
                'stok_tujuan' => $stokTujuanQty,
                'estimasi_tujuan' => $stokTujuanQty + $qty,
            ];
        }

        return $rows;
    }

    public function saveTransfer()
    {
        $this->validate([
            'transferGudangAsalId' => 'required|exists:gudang,id',
            'transferGudangTujuanId' => 'required|exists:gudang,id|different:transferGudangAsalId',
            'transferItems' => 'required|array|min:1',
            'transferItems.*.produk_id' => 'required|exists:produk,id',
            'transferItems.*.rak_id' => 'nullable|exists:rak,id',
            'transferItems.*.jumlah' => 'required|integer|min:1',
        ]);

        // [T-41] Validasi server per item: qty ≤ stok tersedia gudang sumber
        // (stok_fisik − stok_dikunci transfer draft pending).
        $lockedByKey = StokTransfer::pendingLockedByGudang((int) $this->transferGudangAsalId);
        $adaError = false;
        foreach ($this->transferItems as $idx => $row) {
            $stok = StokItem::where('gudang_id', $this->transferGudangAsalId)
                ->where('produk_id', $row['produk_id'])
                ->where('sku_variant_id', $row['sku_variant_id'] ?? null)
                ->first();
            $key = $row['produk_id'].':'.($row['sku_variant_id'] ?? 'null');
            $tersedia = ($stok?->jumlah ?? 0) - (int) ($lockedByKey[$key] ?? 0);
            if ($tersedia < (int) $row['jumlah']) {
                $this->addError(
                    'transferItems.'.$idx.'.jumlah',
                    'Qty melebihi stok tersedia di gudang sumber (tersedia: '.max(0, $tersedia).' unit)'
                );
                $adaError = true;
            }
        }
        if ($adaError) {
            return;
        }

        DB::transaction(function () {
            $today = now()->format('Ymd');
            $count = StokTransfer::whereDate('created_at', now()->toDateString())->count() + 1;
            $noTransfer = sprintf('TRF-%s-%04d', $today, $count);

            $transfer = StokTransfer::create([
                'no_transfer' => $noTransfer,
                'gudang_asal_id' => $this->transferGudangAsalId,
                'gudang_tujuan_id' => $this->transferGudangTujuanId,
                'user_pengirim_id' => auth()->id() ?? 1, // created_by
                'status' => 'draft',
                'catatan' => $this->transferCatatan,
            ]);

            foreach ($this->transferItems as $item) {
                StokTransferItem::create([
                    'stok_transfer_id' => $transfer->id,
                    'produk_id' => $item['produk_id'],
                    'sku_variant_id' => $item['sku_variant_id'] ?? null,
                    'rak_id' => $item['rak_id'] ?? null,
                    'jumlah' => $item['jumlah'],
                    'created_by' => auth()->id() ?? 1, // [T-41] audit trail
                ]);
            }
        });

        $this->showTransferModal = false;
        $this->dispatch('alert', ['type' => 'success', 'message' => 'Draft transfer berhasil dibuat']);
    }

    public function kirimTransfer(int $transferId)
    {
        $transfer = StokTransfer::with('items')->findOrFail($transferId);

        try {
            DB::transaction(function () use ($transfer) {
                foreach ($transfer->items as $item) {
                    $stokAsal = StokItem::where('gudang_id', $transfer->gudang_asal_id)
                        ->where('produk_id', $item->produk_id)
                        ->where('sku_variant_id', $item->sku_variant_id)
                        ->lockForUpdate() // anti race (T-13)
                        ->first();

                    $sebelum = $stokAsal ? $stokAsal->jumlah : 0;
                    // [T-13] stok terkunci transfer draft lain tidak boleh dipakai
                    $locked = $stokAsal ? StokTransfer::pendingLockedFor($stokAsal, $transfer->id) : 0;
                    if ($sebelum - $locked < $item->jumlah) {
                        throw new \Exception('Qty melebihi stok tersedia di gudang sumber (tersedia: '.max(0, $sebelum - $locked).' unit) untuk item ID '.$item->produk_id);
                    }

                    $setelah = $sebelum - $item->jumlah;
                    $stokAsal->update(['jumlah' => $setelah]);

                    // [T-41] SOT mutasi ke luar — wajib utk kirim transfer
                    StockMutationLog::create([
                        'produk_id' => $item->produk_id,
                        'sku_variant_id' => $item->sku_variant_id,
                        'gudang_id' => $transfer->gudang_asal_id,
                        'delta' => -$item->jumlah,
                        'sumber' => 'transfer:out',
                        'referensi_tipe' => StokTransfer::class,
                        'referensi_id' => $transfer->id,
                        'terjadi_at' => now(),
                    ]);

                    StokLog::create([
                        'gudang_id' => $transfer->gudang_asal_id,
                        'produk_id' => $item->produk_id,
                        'sku_variant_id' => $item->sku_variant_id,
                        'user_id' => auth()->id(),
                        'jenis' => 'transfer_keluar',
                        'referensi_tipe' => StokTransfer::class,
                        'referensi_id' => $transfer->id,
                        'jumlah_sebelum' => $sebelum,
                        'perubahan' => -$item->jumlah,
                        'jumlah_setelah' => $setelah,
                        'catatan' => "Kirim transfer {$transfer->no_transfer}",
                    ]);
                }

                $transfer->update([
                    'status' => 'dikirim',
                    'tanggal_kirim' => now(),
                    'approved_by' => auth()->id(), // [T-41] audit trail — yang menyetujui kirim
                    'approved_at' => now(),
                ]);
            });

            $this->dispatch('alert', ['type' => 'success', 'message' => 'Transfer berhasil dikirim']);
        } catch (\Exception $e) {
            $this->dispatch('alert', ['type' => 'error', 'message' => $e->getMessage()]);
        }
    }

    public function terimaTransfer(int $transferId)
    {
        $transfer = StokTransfer::with('items')->findOrFail($transferId);

        try {
            DB::transaction(function () use ($transfer) {
                foreach ($transfer->items as $item) {
                    $stokTujuan = StokItem::firstOrCreate(
                        [
                            'gudang_id' => $transfer->gudang_tujuan_id,
                            'produk_id' => $item->produk_id,
                            'sku_variant_id' => $item->sku_variant_id,
                        ],
                        ['jumlah' => 0, 'jumlah_minimum' => 0]
                    );

                    $sebelum = $stokTujuan->jumlah;
                    $setelah = $sebelum + $item->jumlah;
                    $stokTujuan->update(['jumlah' => $setelah]);

                    // [T-41] SOT mutasi ke dalam — wajib utk terima transfer
                    StockMutationLog::create([
                        'produk_id' => $item->produk_id,
                        'sku_variant_id' => $item->sku_variant_id,
                        'gudang_id' => $transfer->gudang_tujuan_id,
                        'delta' => $item->jumlah,
                        'sumber' => 'transfer:in',
                        'referensi_tipe' => StokTransfer::class,
                        'referensi_id' => $transfer->id,
                        'terjadi_at' => now(),
                    ]);

                    StokLog::create([
                        'gudang_id' => $transfer->gudang_tujuan_id,
                        'produk_id' => $item->produk_id,
                        'sku_variant_id' => $item->sku_variant_id,
                        'user_id' => auth()->id(),
                        'jenis' => 'transfer_masuk',
                        'referensi_tipe' => StokTransfer::class,
                        'referensi_id' => $transfer->id,
                        'jumlah_sebelum' => $sebelum,
                        'perubahan' => $item->jumlah,
                        'jumlah_setelah' => $setelah,
                        'catatan' => "Terima transfer {$transfer->no_transfer}",
                    ]);
                }

                $transfer->update([
                    'status' => 'diterima',
                    'tanggal_terima' => now(),
                    'user_penerima_id' => auth()->id(),
                ]);
            });

            $this->dispatch('alert', ['type' => 'success', 'message' => 'Transfer berhasil diterima dan stok ditambahkan']);
        } catch (\Exception $e) {
            $this->dispatch('alert', ['type' => 'error', 'message' => $e->getMessage()]);
        }
    }

    // --- OPNAME ACTIONS ---
    public function openNewOpnameModal()
    {
        $this->opnameGudangId = $this->filterGudangId;
        $this->opnameRakId = null;
        $this->opnameCatatan = '';

        // Pre-fill with all products in warehouse (optionally filtered by rak)
        $stokQuery = StokItem::where('gudang_id', $this->filterGudangId);
        if ($this->opnameRakId) {
            $stokQuery->where('rak_id', $this->opnameRakId);
        }
        $products = Produk::where('is_active', true)
            ->whereHas('stokItems', fn ($q) => $q->where('gudang_id', $this->filterGudangId)
                ->when($this->opnameRakId, fn ($q2) => $q2->where('rak_id', $this->opnameRakId)))
            ->take(20)->get();
        $this->opnameRows = [];

        foreach ($products as $prod) {
            $stok = $this->opnameGudangId
                ? StokItem::where('produk_id', $prod->id)->where('gudang_id', $this->opnameGudangId)
                    ->when($this->opnameRakId, fn ($q) => $q->where('rak_id', $this->opnameRakId))
                    ->value('jumlah') ?? 0
                : 0;

            $this->opnameRows[] = [
                'produk_id' => $prod->id,
                'nama' => $prod->nama,
                'stok_sistem' => $stok,
                'stok_fisik' => $stok,
                'selisih' => 0,
            ];
        }

        $this->showOpnameModal = true;
    }

    public function updateOpnameFisik(int $index, $value)
    {
        $val = (int) $value;
        $this->opnameRows[$index]['stok_fisik'] = $val;
        $this->opnameRows[$index]['selisih'] = $val - $this->opnameRows[$index]['stok_sistem'];
    }

    public function saveOpname()
    {
        $this->validate([
            'opnameGudangId' => 'required|exists:gudang,id',
            'opnameRakId' => 'nullable|exists:rak,id',
            'opnameRows' => 'required|array|min:1',
        ]);

        DB::transaction(function () {
            $today = now()->format('Ymd');
            $count = StokOpname::whereDate('created_at', now()->toDateString())->count() + 1;
            $noOpname = sprintf('OPN-%s-%04d', $today, $count);

            $opname = StokOpname::create([
                'no_opname' => $noOpname,
                'gudang_id' => $this->opnameGudangId,
                'rak_id' => $this->opnameRakId,
                'user_id' => auth()->id() ?? 1,
                'status' => 'menunggu_approval',
                'catatan' => $this->opnameCatatan,
            ]);

            foreach ($this->opnameRows as $row) {
                StokOpnameItem::create([
                    'stok_opname_id' => $opname->id,
                    'produk_id' => $row['produk_id'],
                    'sku_variant_id' => null,
                    'rak_id' => $this->opnameRakId, // [T-14] scope per rak
                    'stok_sistem' => $row['stok_sistem'],
                    'stok_fisik' => $row['stok_fisik'],
                    'selisih' => $row['selisih'],
                ]);
            }
        });

        $this->showOpnameModal = false;
        $this->dispatch('alert', ['type' => 'success', 'message' => 'Stock opname tersimpan & menunggu approval']);
    }

    public function approveOpname(int $opnameId)
    {
        $opname = StokOpname::with('items')->findOrFail($opnameId);

        DB::transaction(function () use ($opname) {
            foreach ($opname->items as $item) {
                $stok = StokItem::firstOrCreate(
                    [
                        'gudang_id' => $opname->gudang_id,
                        'produk_id' => $item->produk_id,
                        'sku_variant_id' => $item->sku_variant_id,
                        'rak_id' => $item->rak_id ?? $opname->rak_id, // [T-14] scope rak
                    ],
                    ['jumlah' => 0, 'jumlah_minimum' => 0]
                );

                $sebelum = $stok->jumlah;
                $stok->update(['jumlah' => $item->stok_fisik]);

                if ($item->selisih !== 0) {
                    StockMutationLog::create([
                        'produk_id' => $item->produk_id,
                        'sku_variant_id' => $item->sku_variant_id,
                        'gudang_id' => $opname->gudang_id,
                        'delta' => $item->selisih,
                        'sumber' => 'opname',
                        'referensi_tipe' => StokOpname::class,
                        'referensi_id' => $opname->id,
                        'terjadi_at' => now(),
                    ]);

                    StokLog::create([
                        'gudang_id' => $opname->gudang_id,
                        'produk_id' => $item->produk_id,
                        'sku_variant_id' => $item->sku_variant_id,
                        'user_id' => auth()->id(),
                        'jenis' => 'opname',
                        'referensi_tipe' => StokOpname::class,
                        'referensi_id' => $opname->id,
                        'jumlah_sebelum' => $sebelum,
                        'perubahan' => $item->selisih,
                        'jumlah_setelah' => $item->stok_fisik,
                        'catatan' => "Approval Opname {$opname->no_opname}",
                    ]);
                }
            }

            $opname->update([
                'status' => 'disetujui',
                'approver_id' => auth()->id() ?? 1,
                'tanggal_approval' => now(),
            ]);
        });

        $this->dispatch('alert', ['type' => 'success', 'message' => 'Stock opname disetujui & stok sistem berhasil diselaraskan']);
    }

    // ===== PRODUK (master + pembelian, sinkron Akunting) =====

    public function openProdukModal()
    {
        $this->produkForm = [
            'nama' => '', 'kategori' => '', 'brand_kompatibel' => '', 'model_kompatibel' => '',
            'kondisi' => 'baru', 'harga_beli' => 0, 'harga_jual_retail' => 0,
            'sku' => '', 'gudang_id' => null, 'stok_awal' => 0, 'stok_minimum' => 0,
            // [T-44]
            'brand_id' => null, 'kualitas_id' => null, 'satuan_kode' => 'pcs',
            'tipe_hp_ids' => [], 'harga_tier' => [
                'retail' => ['nominal_tetap' => 0, 'persen_diskon' => null],
                'reseller' => ['nominal_tetap' => null, 'persen_diskon' => null],
                'agen' => ['nominal_tetap' => null, 'persen_diskon' => null],
            ],
            'harga_tier_tier_id' => null,
            // [HARGA FLEKSIBEL]
            'harga_fleksibel' => false,
        ];
        $this->showProdukModal = true;
    }

    public function produkHargaJualBerubah()
    {
        // Isi otomatis harga tier retail bila belum di-set manual
        if (! $this->produkForm['harga_tier']['retail']['nominal_tetap']
            && $this->produkForm['harga_jual_retail'] > 0) {
            $this->produkForm['harga_tier']['retail']['nominal_tetap'] = (float) $this->produkForm['harga_jual_retail'];
        }
    }

    public function simpanProduk()
    {
        $this->validate([
            'produkForm.nama' => 'required|string|max:255',
            'produkForm.kategori' => 'required|string|max:255',
            'produkForm.satuan_kode' => 'required|exists:satuan_unit,kode',
            'produkForm.harga_beli' => 'required|numeric|min:0',
            'produkForm.harga_jual_retail' => 'required|numeric|min:0',
            // [T-44] minimal 1 harga tier (retail wajib isi)
            'produkForm.harga_tier.retail.nominal_tetap' => 'nullable|numeric|min:0',
            // [HARGA FLEKSIBEL]
            'produkForm.harga_fleksibel' => 'boolean',
        ]);

        // [T-44] minimal 1 harga tier: retail (fallback ke harga_jual) atau salah satu tipe lain
        $adaHargaTier = collect($this->produkForm['harga_tier'])
            ->contains(fn ($t) => ($t['nominal_tetap'] ?? null) !== null || ($t['persen_diskon'] ?? null) !== null);
        if (! $adaHargaTier) {
            $this->dispatch('alert', ['type' => 'error', 'message' => 'Minimal 1 harga tier wajib diisi (retail / reseller / agen)']);

            return;
        }

        // [T-40] Stok awal (pembelian manual) hanya boleh diinput super-admin —
        // stok masuk wajib via PO Supplier utk role lain.
        if (! $this->isSuperAdmin() && ((int) $this->produkForm['stok_awal'] > 0 || $this->produkForm['gudang_id'])) {
            $this->dispatch('alert', ['type' => 'error', 'message' => 'Stok masuk hanya via PO Supplier — stok awal hanya boleh diatur super-admin']);

            return;
        }

        // [HARGA FLEKSIBEL] Guard: hanya superadmin boleh set harga_fleksibel = true
        if ((bool) $this->produkForm['harga_fleksibel'] && ! auth()->user()?->hasPermissionTo('atur-harga-fleksibel')) {
            $this->dispatch('alert', ['type' => 'error', 'message' => 'Hanya superadmin yang boleh mengaktifkan harga fleksibel']);

            return;
        }

        try {
            app(ProdukService::class)->buatProduk(
                nama: $this->produkForm['nama'],
                kategori: $this->produkForm['kategori'],
                brand: $this->produkForm['brand_kompatibel'] ?: null,
                model: $this->produkForm['model_kompatibel'] ?: null,
                kondisi: $this->produkForm['kondisi'],
                hargaBeli: (float) $this->produkForm['harga_beli'],
                hargaJual: (float) $this->produkForm['harga_jual_retail'],
                sku: $this->produkForm['sku'] ?: null,
                gudangId: $this->produkForm['gudang_id'] ?: null,
                stokAwal: (int) $this->produkForm['stok_awal'],
                stokMinimum: (int) $this->produkForm['stok_minimum'],
                userId: auth()->id(),
                brandId: $this->produkForm['brand_id'] ?: null,
                kualitasId: $this->produkForm['kualitas_id'] ?: null,
                satuanKode: $this->produkForm['satuan_kode'],
                tipeHpIds: $this->produkForm['tipe_hp_ids'] ?: [],
                hargaTier: $this->produkForm['harga_tier'],
                hargaFleksibel: (bool) $this->produkForm['harga_fleksibel'],
            );

            $this->showProdukModal = false;
            $this->dispatch('alert', [
                'type' => 'success',
                'message' => 'Produk tersimpan'.((int) $this->produkForm['stok_awal'] > 0 ? ' + jurnal pembelian dibuat' : ''),
            ]);
        } catch (\Exception $e) {
            $this->dispatch('alert', ['type' => 'error', 'message' => $e->getMessage()]);
        }
    }

    public function openTambahStokModal(int $produkId)
    {
        // [T-40] Stok masuk wajib via PO Supplier — tambah stok manual hanya super-admin
        if (! $this->isSuperAdmin()) {
            $this->dispatch('alert', ['type' => 'error', 'message' => 'Stok masuk hanya via PO Supplier']);

            return;
        }

        $produk = Produk::with('skuVariants')->findOrFail($produkId);
        $this->stokProdukId = $produkId;
        $variant = $produk->skuVariants->first();
        $this->tambahStokForm = [
            'gudang_id' => $this->filterGudangId,
            'qty' => 1,
            'harga_beli' => $variant?->harga_beli ?? $produk->harga_beli ?? 0,
            'keterangan' => 'Pembelian stok '.$produk->nama,
        ];
        $this->showTambahStokModal = true;
    }

    public function simpanTambahStok()
    {
        // [T-40] Guard server-side: jalur tambah stok manual diblokir selain super-admin
        if (! $this->isSuperAdmin()) {
            $this->dispatch('alert', ['type' => 'error', 'message' => 'Stok masuk hanya via PO Supplier']);

            return;
        }

        $this->validate([
            'tambahStokForm.gudang_id' => 'required|exists:gudang,id',
            'tambahStokForm.rak_id' => 'nullable|exists:rak,id', // [T-12]
            'tambahStokForm.qty' => 'required|integer|min:1',
            'tambahStokForm.harga_beli' => 'required|numeric|min:0',
        ]);

        try {
            $produk = Produk::with('skuVariants')->findOrFail($this->stokProdukId);
            $variant = $produk->skuVariants->first();

            app(ProdukService::class)->tambahStokPembelian(
                produkId: $produk->id,
                variantId: $variant?->id,
                gudangId: (int) $this->tambahStokForm['gudang_id'],
                qty: (int) $this->tambahStokForm['qty'],
                hargaBeli: (float) $this->tambahStokForm['harga_beli'],
                keterangan: $this->tambahStokForm['keterangan'] ?: 'Pembelian dari supplier',
                userId: auth()->id(),
                rakId: $this->tambahStokForm['rak_id'] ?? null // [T-12]
            );

            $this->showTambahStokModal = false;
            $this->dispatch('alert', ['type' => 'success', 'message' => 'Stok ditambahkan + jurnal pembelian dibuat']);
        } catch (\Exception $e) {
            $this->dispatch('alert', ['type' => 'error', 'message' => $e->getMessage()]);
        }
    }

    public function openPoModal()
    {
        $this->poForm = [
            'supplier_id' => null, 'gudang_tujuan_id' => null, 'metode_bayar' => 'kredit', 'jatuh_tempo' => '',
            'items' => [['produk_id' => null, 'sku_variant_id' => null, 'harga_beli' => 0, 'jumlah' => 1]],
        ];
        $this->showPoModal = true;
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

    public function kirimPo(int $id)
    {
        $po = PurchaseOrder::findOrFail($id);

        // Cek apakah PO menunggu approval (rule match)
        $cabangId = session('cabang_id');
        $approvalService = app(\App\Modules\Workflow\Services\ApprovalService::class);
        if ($approvalService->adaPending('po', $po->id)) {
            $this->dispatch('alert', ['type' => 'error', 'message' => 'PO menunggu approval dan tidak dapat dikirim']);
            return;
        }

        $po->update(['status' => 'dikirim']);
        $this->dispatch('alert', ['type' => 'success', 'message' => 'PO dikirim ke supplier']);
    }

    public function terimaPo(int $id)
    {
        try {
            app(PurchaseOrderService::class)->terimaBarang(
                PurchaseOrder::findOrFail($id),
                auth()->id()
            );
            $this->dispatch('alert', ['type' => 'success', 'message' => 'PO diterima — stok & jurnal akunting dibuat']);
        } catch (\Exception $e) {
            $this->dispatch('alert', ['type' => 'error', 'message' => $e->getMessage()]);
        }
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

    // [T-15] Generate barcode utk produk (api paralel ke WMS-14)
    public function generateBarcodeProduk(int $id)
    {
        $produk = Produk::findOrFail($id);

        if (empty($produk->barcode)) {
            $checksum = substr(hash('crc32b', (string) $produk->id), 0, 4);
            $produk->update(['barcode' => sprintf('UTP-%05d-%s', $produk->id, strtoupper($checksum))]);
        }

        $variant = $produk->skuVariants()->first();
        if ($variant && empty($variant->barcode)) {
            $variant->update(['barcode' => $produk->barcode.'-'.$variant->id]);
        }

        $this->dispatch('alert', ['type' => 'success', 'message' => 'Barcode: '.$produk->fresh()->barcode]);
    }

    // [T-12] Rak
    public function openImportModal()
    {
        $this->importStep = 'upload';
        $this->importFile = null;
        $this->importPreview = [];
        $this->importFilePath = '';
        $this->showImportModal = true;
    }

    public function tutupImportModal()
    {
        $this->showImportModal = false;
        if ($this->importFilePath) {
            @unlink(storage_path('app/'.$this->importFilePath));
        }
        $this->importFile = null;
        $this->importPreview = [];
        $this->importFilePath = '';
    }

    /**
     * [T-43] Preview (dry-run): validasi seluruh baris, tampilkan 5 baris pertama + error per baris.
     * Wajib sebelum commit. File sementara disimpan sampai commit.
     */
    public function previewImport()
    {
        $this->validate([
            'importFile' => 'required|file|mimes:xlsx,xls,csv|max:5120',
        ]);

        try {
            $path = $this->importFile->store('import-tmp');
            $hasil = app(ImportProdukService::class)->preview(storage_path('app/'.$path));
            $this->importFilePath = $path;
            $this->importPreview = $hasil;
            $this->importStep = 'preview';

            if ($hasil['invalid'] > 0) {
                $this->dispatch('alert', [
                    'type' => 'warning',
                    'message' => "Preview: {$hasil['valid']} baris valid, {$hasil['invalid']} baris error — perbaiki file lalu upload ulang, atau import hanya baris valid",
                ]);
            }
        } catch (\Throwable $e) {
            $this->dispatch('alert', ['type' => 'error', 'message' => $e->getMessage()]);
        }
    }

    /**
     * [T-43] Commit: antrikan ImportProdukExcelJob (queue database) + catat import_log.
     * Import = inisialisasi master (bukan stok masuk harian — tetap lewat PO).
     */
    public function commitImport()
    {
        if (! $this->importFilePath) {
            $this->dispatch('alert', ['type' => 'error', 'message' => 'Lakukan preview terlebih dahulu']);

            return;
        }

        try {
            $log = ImportLog::create([
                'tipe' => 'produk_excel',
                'nama_file' => $this->importFile?->getClientOriginalName() ?? 'produk-import.xlsx',
                'status' => 'proses',
                'user_id' => auth()->id(),
            ]);
            $this->importLogId = $log->id;

            \App\Modules\Wms\Jobs\ImportProdukExcelJob::dispatch($log->id, $this->importFilePath, auth()->id());

            $this->importStep = 'selesai';
            $this->dispatch('alert', [
                'type' => 'success',
                'message' => 'Import diproses via antrian — hasil akan masuk notifikasi dalam aplikasi',
            ]);
        } catch (\Throwable $e) {
            $this->dispatch('alert', ['type' => 'error', 'message' => $e->getMessage()]);
        }
    }

    public function simpanRak()
    {
        $this->validate([
            'rakForm.gudang_id' => 'required|exists:gudang,id',
            'rakForm.nama' => 'required|string|max:255',
            'rakForm.kode' => 'required|string|max:20|unique:rak,kode',
        ]);

        Rak::create($this->rakForm);
        $this->showRakModal = false;
        $this->rakForm = ['gudang_id' => null, 'nama' => '', 'kode' => '', 'zona' => ''];
        $this->dispatch('alert', ['type' => 'success', 'message' => 'Rak ditambahkan']);
    }

    public function render()
    {
        $gudangs = Gudang::where('is_active', true)->get();
        $allProducts = Produk::where('is_active', true)->orderBy('nama')->get();

        // Stok Query
        $stokQuery = StokItem::with(['produk', 'skuVariant', 'gudang.cabang']);
        if ($this->filterGudangId) {
            $stokQuery->where('gudang_id', $this->filterGudangId);
        }
        if (! empty($this->search)) {
            $s = $this->search;
            $stokQuery->whereHas('produk', function ($q) use ($s) {
                $q->where('nama', 'like', "%{$s}%")
                    ->orWhere('brand_kompatibel', 'like', "%{$s}%")
                    ->orWhere('model_kompatibel', 'like', "%{$s}%");
            });
        }
        $stokItems = $stokQuery->paginate(15);

        // Transfer Query
        $transfers = StokTransfer::with(['gudangAsal', 'gudangTujuan', 'pengirim', 'penerima', 'items.produk'])
            ->latest()
            ->take(20)
            ->get();

        // Opname Query
        $opnames = StokOpname::with(['gudang', 'pembuat', 'approver', 'items.produk'])
            ->latest()
            ->take(20)
            ->get();

        return view('modules.wms.livewire.wms-dashboard', [
            'gudangs' => $gudangs,
            'allProducts' => $allProducts,
            'stokItems' => $stokItems,
            'transfers' => $transfers,
            'opnames' => $opnames,
            'suppliers' => Supplier::orderBy('nama')->get(),
            'raks' => Rak::with('gudang')->get(),
            'transferStokRows' => $this->transferStokRows(), // [T-41] info stok per baris form transfer
            'poList' => PurchaseOrder::with(['supplier', 'gudangTujuan', 'items.produk'])->latest()->paginate(15, pageName: 'po'),
            'produks' => Produk::with(['skuVariants', 'stokItems.gudang', 'brand', 'kualitas', 'tipeHps'])
                ->when($this->produkSearch, fn ($q) => $q->where(function ($q2) {
                    $q2->where('nama', 'like', "%{$this->produkSearch}%")
                        ->orWhere('kategori', 'like', "%{$this->produkSearch}%")
                        ->orWhere('brand_kompatibel', 'like', "%{$this->produkSearch}%");
                }))
                ->orderBy('nama')
                ->paginate(12),
            // [T-44] master data pendukung
            'brands' => Brand::orderBy('nama')->get(),
            'kualitasList' => KualitasProduk::orderBy('nama')->get(),
            'tipeHpList' => TipeHp::orderBy('merk')->orderBy('model')->get(),
            'satuanUnits' => SatuanUnit::where('is_active', true)->orderBy('kode')->get(),
        ])->layout('layouts.backoffice', ['header' => 'Gudang & Manajemen Stok (WMS)']);
    }
}
