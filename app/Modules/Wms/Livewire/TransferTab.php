<?php

namespace App\Modules\Wms\Livewire;

use App\Modules\Wms\Models\Gudang;
use App\Modules\Wms\Models\Produk;
use App\Modules\Wms\Models\Rak;
use App\Modules\Wms\Models\StockMutationLog;
use App\Modules\Wms\Models\StokItem;
use App\Modules\Wms\Models\StokLog;
use App\Modules\Wms\Models\StokTransfer;
use App\Modules\Wms\Models\StokTransferItem;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * [F1-8 / S-01] Tab "Transfer Antar Gudang" — dipecah dari WmsDashboard (paritas perilaku).
 */
class TransferTab extends Component
{
    /** [B-15c] Pengaman jumlah gudang per cabang (normally < 10). */
    public const GUDANG_DROPDOWN_LIMIT = 100;

    public ?int $filterGudangId = null;

    // New Transfer Modal state
    public bool $showTransferModal = false;

    // Detail Transfer Modal state
    public ?int $selectedTransferId = null;

    public bool $showDetailModal = false;

    public ?int $transferGudangAsalId = null;

    public ?int $transferGudangTujuanId = null;

    public string $transferCatatan = '';

    public array $transferItems = []; // [['produk_id' => ..., 'sku_variant_id' => ..., 'jumlah' => ...]]

    public function mount()
    {
        $cabangId = session('cabang_id');
        if ($cabangId) {
            $this->filterGudangId = Gudang::where('cabang_id', $cabangId)->value('id');
        }
    }

    // Quick action header "+ Buat Transfer Baru" (dari shell WmsDashboard via $dispatch)
    #[On('wms-transfer-baru')]
    public function openNewTransferModal()
    {
        if (! $this->boleh('wms.transfer', 'Anda tidak memiliki izin membuat transfer stok.')) {
            return;
        }

        $this->transferGudangAsalId = $this->filterGudangId;
        $this->transferGudangTujuanId = null;
        $this->transferCatatan = '';
        $this->transferItems = [
            ['produk_id' => null, 'produk_nama' => '', 'sku_variant_id' => null, 'rak_id' => null, 'jumlah' => 1],
        ];
        $this->showTransferModal = true;
    }

    /**
     * [B-15c] Scope cabang untuk query produk: produk yang punya stok di gudang cabang
     * aktif, atau produk yang belum punya stok sama sekali (produk baru — supaya PO
     * & transfer tidak menyembunyikan kandidat restock). Produk yang HANYA ada di gudang
     * cabang lain tidak boleh bocor ke dropdown.
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

    public function addTransferRow()
    {
        $this->transferItems[] = ['produk_id' => null, 'produk_nama' => '', 'sku_variant_id' => null, 'rak_id' => null, 'jumlah' => 1];
    }

    public function removeTransferRow(int $index)
    {
        unset($this->transferItems[$index]);
        $this->transferItems = array_values($this->transferItems);
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

        if ($context !== 'transfer') {
            return;
        }

        if ($idx !== null && isset($this->transferItems[$idx]) && $produkId) {
            $produk = Produk::find($produkId);
            if ($produk) {
                $this->transferItems[$idx]['produk_id'] = $produkId;
                $this->transferItems[$idx]['produk_nama'] = $produk->nama;
                $this->transferProdukDipilih($idx);
            }
        }
    }

    #[On('buka-pencarian-produk')]
    public function bukaPencarianProduk($targetIndex = null, $context = 'transfer'): void
    {
        if (is_array($targetIndex)) {
            $context = $targetIndex['context'] ?? $context;
            $targetIndex = $targetIndex['targetIndex'] ?? null;
        }

        if ($context !== 'transfer') {
            return;
        }

        $this->dispatch('open-global-product-search', [
            'targetIndex' => $targetIndex,
            'context' => $context,
        ])->to(ProductSearchModal::class);
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
     *
     * [B-15c] 1 query batch untuk SEMUA baris (sebelumnya 1-2 query `stok_items` per
     * baris draft → 2M query per render, M = 1-30 baris). `stok_items` punya unique
     * (produk_id, sku_variant_id, gudang_id) jadi map "gudang:produk:varian" bersifat
     * 1:1 — angka & tampilan identik dengan `->first()` per baris.
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

        $stokMap = $this->petaStokBatch($this->transferItems, $gudangAsalId, $gudangTujuanId);

        foreach ($this->transferItems as $idx => $row) {
            $produkId = (int) ($row['produk_id'] ?? 0);
            $variantId = $row['sku_variant_id'] ?? null;
            $key = $produkId.':'.($variantId ?? 'null');

            $stokSumber = $produkId ? $stokMap[$gudangAsalId.':'.$key] ?? null : null;
            $stokTujuan = ($gudangTujuanId && $produkId) ? $stokMap[$gudangTujuanId.':'.$key] ?? null : null;

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

    /**
     * [B-15c] 1 query `stok_items` untuk seluruh baris form transfer.
     *
     * @param  array<int, array<string, mixed>>  $items  baris draft transfer
     * @return array<string, StokItem> map "gudang:produk:varian" => model
     */
    private function petaStokBatch(array $items, int $gudangAsalId, int $gudangTujuanId): array
    {
        $produkIds = [];
        foreach ($items as $row) {
            $produkId = (int) ($row['produk_id'] ?? 0);
            if ($produkId) {
                $produkIds[$produkId] = $produkId;
            }
        }

        if ($produkIds === []) {
            return [];
        }

        $gudangIds = array_values(array_unique(array_filter([$gudangAsalId, $gudangTujuanId])));

        return StokItem::whereIn('produk_id', array_values($produkIds))
            ->whereIn('gudang_id', $gudangIds)
            ->orderBy('id') // stabil: `->first()` lama = baris id terkecil
            ->get(['gudang_id', 'produk_id', 'sku_variant_id', 'jumlah'])
            ->keyBy(fn (StokItem $s) => $s->gudang_id.':'.$s->produk_id.':'.($s->sku_variant_id ?? 'null'))
            ->all();
    }

    public function saveTransfer()
    {
        if (! $this->boleh('wms.transfer', 'Anda tidak memiliki izin menyimpan transfer stok.')) {
            return;
        }

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
        // [B-15c] 1 query batch (sebelumnya 1 query stok_items per item).
        $lockedByKey = StokTransfer::pendingLockedByGudang((int) $this->transferGudangAsalId);
        $stokMap = $this->petaStokBatch($this->transferItems, (int) $this->transferGudangAsalId, 0);

        $adaError = false;
        foreach ($this->transferItems as $idx => $row) {
            $produkId = (int) ($row['produk_id'] ?? 0);
            $key = $produkId.':'.($row['sku_variant_id'] ?? 'null');
            $stok = $produkId ? $stokMap[(int) $this->transferGudangAsalId.':'.$key] ?? null : null;
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
        if (! $this->boleh('wms.transfer', 'Anda tidak memiliki izin mengirim transfer stok.')) {
            return;
        }

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
                    // [B-10i] user_id = pelaku kirim (sama dgn StokLog & approved_by).
                    StockMutationLog::create([
                        'produk_id' => $item->produk_id,
                        'sku_variant_id' => $item->sku_variant_id,
                        'gudang_id' => $transfer->gudang_asal_id,
                        'user_id' => auth()->id(),
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
        if (! $this->boleh('wms.transfer', 'Anda tidak memiliki izin menerima transfer stok.')) {
            return;
        }

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
                    // [B-10i] user_id = penerima transfer (sama dgn StokLog & user_penerima_id).
                    StockMutationLog::create([
                        'produk_id' => $item->produk_id,
                        'sku_variant_id' => $item->sku_variant_id,
                        'gudang_id' => $transfer->gudang_tujuan_id,
                        'user_id' => auth()->id(),
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

    public function bukaDetail(int $transferId): void
    {
        $this->selectedTransferId = $transferId;
        $this->showDetailModal = true;
    }

    public function tutupDetail(): void
    {
        $this->selectedTransferId = null;
        $this->showDetailModal = false;
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
        // gudang cabang lain bocor ke dropdown asal/tujuan) + batas pengaman.
        $gudangs = Gudang::where('is_active', true)
            ->when($cabangId, fn ($q) => $q->where('cabang_id', $cabangId))
            ->orderBy('nama')
            ->limit(self::GUDANG_DROPDOWN_LIMIT)
            ->get();

        // Produk: tidak perlu lagi load semua produk ke dropdown.
        // Pencarian produk global via ProductSearchModal.
        $allProducts = collect();

        // Transfer Query
        $transfers = StokTransfer::with(['gudangAsal', 'gudangTujuan', 'pengirim', 'penerima', 'items.produk'])
            ->latest()
            ->take(20)
            ->get();

        $selectedTransfer = null;
        $mutasiLogs = collect();
        if ($this->showDetailModal && $this->selectedTransferId) {
            $selectedTransfer = StokTransfer::with([
                'gudangAsal',
                'gudangTujuan',
                'pengirim',
                'penerima',
                'approver',
                'items.produk',
                'items.skuVariant',
                'items.rak',
            ])->find($this->selectedTransferId);

            $mutasiLogs = StokLog::with(['gudang', 'produk', 'skuVariant', 'user'])
                ->where('referensi_tipe', StokTransfer::class)
                ->where('referensi_id', $this->selectedTransferId)
                ->orderBy('created_at')
                ->get();
        }

        return view('modules.wms.livewire.transfer-tab', [
            'gudangs' => $gudangs,
            'allProducts' => $allProducts,
            'transfers' => $transfers,
            'raks' => Rak::with('gudang')->get(),
            'transferStokRows' => $this->transferStokRows(), // [T-41] info stok per baris form transfer
            'selectedTransfer' => $selectedTransfer,
            'mutasiLogs' => $mutasiLogs,
        ]);
    }
}
