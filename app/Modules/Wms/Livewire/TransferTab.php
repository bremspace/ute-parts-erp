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
    public ?int $filterGudangId = null;

    // New Transfer Modal state
    public bool $showTransferModal = false;

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

    public function render()
    {
        $gudangs = Gudang::where('is_active', true)->get();
        $allProducts = Produk::where('is_active', true)->orderBy('nama')->get();

        // Transfer Query
        $transfers = StokTransfer::with(['gudangAsal', 'gudangTujuan', 'pengirim', 'penerima', 'items.produk'])
            ->latest()
            ->take(20)
            ->get();

        return view('modules.wms.livewire.transfer-tab', [
            'gudangs' => $gudangs,
            'allProducts' => $allProducts,
            'transfers' => $transfers,
            'raks' => Rak::with('gudang')->get(),
            'transferStokRows' => $this->transferStokRows(), // [T-41] info stok per baris form transfer
        ]);
    }
}
