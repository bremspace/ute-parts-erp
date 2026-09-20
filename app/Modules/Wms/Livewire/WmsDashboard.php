<?php

namespace App\Modules\Wms\Livewire;

use App\Modules\Wms\Models\Gudang;
use App\Modules\Wms\Models\Produk;
use App\Modules\Wms\Models\SkuVariant;
use App\Modules\Wms\Models\StokItem;
use App\Modules\Wms\Models\StokLog;
use App\Modules\Wms\Models\StokOpname;
use App\Modules\Wms\Models\StokOpnameItem;
use App\Modules\Wms\Models\StokTransfer;
use App\Modules\Wms\Models\StokTransferItem;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\WithPagination;

class WmsDashboard extends Component
{
    use WithPagination;

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

    // New Transfer Modal state
    public bool $showTransferModal = false;
    public ?int $transferGudangAsalId = null;
    public ?int $transferGudangTujuanId = null;
    public string $transferCatatan = '';
    public array $transferItems = []; // [['produk_id' => ..., 'sku_variant_id' => ..., 'jumlah' => ...]]

    // New Opname Modal state
    public bool $showOpnameModal = false;
    public ?int $opnameGudangId = null;
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

    // --- TRANSFER ACTIONS ---
    public function openNewTransferModal()
    {
        $this->transferGudangAsalId = $this->filterGudangId;
        $this->transferGudangTujuanId = null;
        $this->transferCatatan = '';
        $this->transferItems = [
            ['produk_id' => null, 'sku_variant_id' => null, 'jumlah' => 1],
        ];
        $this->showTransferModal = true;
    }

    public function addTransferRow()
    {
        $this->transferItems[] = ['produk_id' => null, 'sku_variant_id' => null, 'jumlah' => 1];
    }

    public function removeTransferRow(int $index)
    {
        unset($this->transferItems[$index]);
        $this->transferItems = array_values($this->transferItems);
    }

    public function saveTransfer()
    {
        $this->validate([
            'transferGudangAsalId' => 'required|exists:gudang,id',
            'transferGudangTujuanId' => 'required|exists:gudang,id|different:transferGudangAsalId',
            'transferItems' => 'required|array|min:1',
            'transferItems.*.produk_id' => 'required|exists:produk,id',
            'transferItems.*.jumlah' => 'required|integer|min:1',
        ]);

        DB::transaction(function () {
            $today = now()->format('Ymd');
            $count = StokTransfer::whereDate('created_at', now()->toDateString())->count() + 1;
            $noTransfer = sprintf('TRF-%s-%04d', $today, $count);

            $transfer = StokTransfer::create([
                'no_transfer' => $noTransfer,
                'gudang_asal_id' => $this->transferGudangAsalId,
                'gudang_tujuan_id' => $this->transferGudangTujuanId,
                'user_pengirim_id' => auth()->id() ?? 1,
                'status' => 'draft',
                'catatan' => $this->transferCatatan,
            ]);

            foreach ($this->transferItems as $item) {
                StokTransferItem::create([
                    'stok_transfer_id' => $transfer->id,
                    'produk_id' => $item['produk_id'],
                    'sku_variant_id' => $item['sku_variant_id'] ?? null,
                    'jumlah' => $item['jumlah'],
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
                        ->first();

                    $sebelum = $stokAsal ? $stokAsal->jumlah : 0;
                    if ($sebelum < $item->jumlah) {
                        throw new \Exception("Stok gudang asal tidak mencukupi untuk item ID {$item->produk_id}");
                    }

                    $setelah = $sebelum - $item->jumlah;
                    $stokAsal->update(['jumlah' => $setelah]);

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
        $this->opnameCatatan = '';

        // Pre-fill with all products in warehouse
        $products = Produk::where('is_active', true)->take(20)->get();
        $this->opnameRows = [];

        foreach ($products as $prod) {
            $stok = $this->opnameGudangId
                ? StokItem::where('produk_id', $prod->id)->where('gudang_id', $this->opnameGudangId)->value('jumlah') ?? 0
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
            'opnameRows' => 'required|array|min:1',
        ]);

        DB::transaction(function () {
            $today = now()->format('Ymd');
            $count = StokOpname::whereDate('created_at', now()->toDateString())->count() + 1;
            $noOpname = sprintf('OPN-%s-%04d', $today, $count);

            $opname = StokOpname::create([
                'no_opname' => $noOpname,
                'gudang_id' => $this->opnameGudangId,
                'user_id' => auth()->id() ?? 1,
                'status' => 'menunggu_approval',
                'catatan' => $this->opnameCatatan,
            ]);

            foreach ($this->opnameRows as $row) {
                StokOpnameItem::create([
                    'stok_opname_id' => $opname->id,
                    'produk_id' => $row['produk_id'],
                    'sku_variant_id' => null,
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
                    ],
                    ['jumlah' => 0, 'jumlah_minimum' => 0]
                );

                $sebelum = $stok->jumlah;
                $stok->update(['jumlah' => $item->stok_fisik]);

                if ($item->selisih !== 0) {
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
        ];
        $this->showProdukModal = true;
    }

    public function simpanProduk()
    {
        $this->validate([
            'produkForm.nama' => 'required|string|max:255',
            'produkForm.kategori' => 'required|string|max:255',
            'produkForm.harga_beli' => 'required|numeric|min:0',
            'produkForm.harga_jual_retail' => 'required|numeric|min:0',
        ]);

        try {
            app(\App\Modules\Wms\Services\ProdukService::class)->buatProduk(
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
                userId: auth()->id()
            );

            $this->showProdukModal = false;
            $this->dispatch('alert', [
                'type' => 'success',
                'message' => 'Produk tersimpan' . ((int) $this->produkForm['stok_awal'] > 0 ? ' + jurnal pembelian dibuat' : ''),
            ]);
        } catch (\Exception $e) {
            $this->dispatch('alert', ['type' => 'error', 'message' => $e->getMessage()]);
        }
    }

    public function openTambahStokModal(int $produkId)
    {
        $produk = Produk::with('skuVariants')->findOrFail($produkId);
        $this->stokProdukId = $produkId;
        $variant = $produk->skuVariants->first();
        $this->tambahStokForm = [
            'gudang_id' => $this->filterGudangId,
            'qty' => 1,
            'harga_beli' => $variant?->harga_beli ?? $produk->harga_beli ?? 0,
            'keterangan' => 'Pembelian stok ' . $produk->nama,
        ];
        $this->showTambahStokModal = true;
    }

    public function simpanTambahStok()
    {
        $this->validate([
            'tambahStokForm.gudang_id' => 'required|exists:gudang,id',
            'tambahStokForm.qty' => 'required|integer|min:1',
            'tambahStokForm.harga_beli' => 'required|numeric|min:0',
        ]);

        try {
            $produk = Produk::with('skuVariants')->findOrFail($this->stokProdukId);
            $variant = $produk->skuVariants->first();

            app(\App\Modules\Wms\Services\ProdukService::class)->tambahStokPembelian(
                produkId: $produk->id,
                variantId: $variant?->id,
                gudangId: (int) $this->tambahStokForm['gudang_id'],
                qty: (int) $this->tambahStokForm['qty'],
                hargaBeli: (float) $this->tambahStokForm['harga_beli'],
                keterangan: $this->tambahStokForm['keterangan'] ?: 'Pembelian dari supplier',
                userId: auth()->id()
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
        $count = \App\Modules\Wms\Models\PurchaseOrder::whereDate('created_at', now()->toDateString())->count() + 1;
        $noPo = sprintf('PO-%s-%03d', $today, $count);

        $total = 0;
        foreach ($this->poForm['items'] as $i) {
            $total += (float) ($i['harga_beli'] ?? 0) * (int) ($i['jumlah'] ?? 1);
        }

        $po = \App\Modules\Wms\Models\PurchaseOrder::create([
            'no_po' => $noPo,
            'supplier_id' => $this->poForm['supplier_id'],
            'gudang_tujuan_id' => $this->poForm['gudang_tujuan_id'],
            'status' => 'draft',
            'metode_bayar' => $this->poForm['metode_bayar'],
            'jatuh_tempo' => $this->poForm['jatuh_tempo'] ?: now()->addDays((int) \App\Modules\Wms\Models\Supplier::find($this->poForm['supplier_id'])?->termin_hari ?? 30)->toDateString(),
            'total' => $total,
            'total_dibayar' => 0,
        ]);

        foreach ($this->poForm['items'] as $i) {
            \App\Modules\Wms\Models\PurchaseOrderItem::create([
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
        \App\Modules\Wms\Models\PurchaseOrder::findOrFail($id)->update(['status' => 'dikirim']);
        $this->dispatch('alert', ['type' => 'success', 'message' => 'PO dikirim ke supplier']);
    }

    public function terimaPo(int $id)
    {
        try {
            app(\App\Modules\Wms\Services\PurchaseOrderService::class)->terimaBarang(
                \App\Modules\Wms\Models\PurchaseOrder::findOrFail($id),
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
        $this->bayarPoJumlah = (float) \App\Modules\Wms\Models\PurchaseOrder::find($id)?->sisa ?? 0;
        $this->dispatch('alert-open-bayar-po', ['id' => $id]);
    }

    public function bayarPo()
    {
        try {
            app(\App\Modules\Wms\Services\PurchaseOrderService::class)->bayarPO(
                \App\Modules\Wms\Models\PurchaseOrder::findOrFail($this->bayarPoId),
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

        \App\Modules\Wms\Models\Supplier::create($this->supplierForm);
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
            $variant->update(['barcode' => $produk->barcode . '-' . $variant->id]);
        }

        $this->dispatch('alert', ['type' => 'success', 'message' => 'Barcode: ' . $produk->fresh()->barcode]);
    }

    // [T-12] Rak
    public function simpanRak()
    {
        $this->validate([
            'rakForm.gudang_id' => 'required|exists:gudang,id',
            'rakForm.nama' => 'required|string|max:255',
            'rakForm.kode' => 'required|string|max:20|unique:rak,kode',
        ]);

        \App\Modules\Wms\Models\Rak::create($this->rakForm);
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
        if (!empty($this->search)) {
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
            'suppliers' => \App\Modules\Wms\Models\Supplier::orderBy('nama')->get(),
            'raks' => \App\Modules\Wms\Models\Rak::with('gudang')->get(),
            'poList' => \App\Modules\Wms\Models\PurchaseOrder::with(['supplier', 'gudangTujuan', 'items.produk'])->latest()->paginate(15, pageName: 'po'),
            'produks' => Produk::with(['skuVariants', 'stokItems.gudang'])
                ->when($this->produkSearch, fn ($q) => $q->where(function ($q2) {
                    $q2->where('nama', 'like', "%{$this->produkSearch}%")
                        ->orWhere('kategori', 'like', "%{$this->produkSearch}%")
                        ->orWhere('brand_kompatibel', 'like', "%{$this->produkSearch}%");
                }))
                ->orderBy('nama')
                ->paginate(12),
        ])->layout('layouts.backoffice', ['header' => 'Gudang & Manajemen Stok (WMS)']);
    }
}
