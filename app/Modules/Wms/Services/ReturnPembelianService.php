<?php

namespace App\Modules\Wms\Services;

use App\Modules\Akunting\Models\Utang;
use App\Modules\Akunting\Services\JurnalService;
use App\Modules\Pos\Models\ReturnPenjualanItem;
use App\Modules\Wms\Models\Gudang;
use App\Modules\Wms\Models\PurchaseOrder;
use App\Modules\Wms\Models\PurchaseOrderItem;
use App\Modules\Wms\Models\ReturnPembelian;
use App\Modules\Wms\Models\ReturnPembelianItem;
use App\Modules\Wms\Models\StockMutationLog;
use App\Modules\Wms\Models\StokItem;
use App\Modules\Wms\Models\StokLog;
use App\Modules\Wms\Models\Supplier;
use App\Modules\Workflow\Services\ApprovalService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReturnPembelianService
{
    public function __construct(
        protected JurnalService $jurnalService,
        protected NomorSeriService $nomorSeriService,
        protected ApprovalService $approvalService
    ) {}

    /**
     * Buat retur pembelian ke supplier dari Purchase Order berstatus diterima.
     *
     * @param  array<int, array{purchase_order_item_id: int, jumlah: int|float, sn?: array<int, string>}>  $items
     */
    public function buatRetur(
        PurchaseOrder $po,
        array $items,
        string $alasan,
        string $metodePengembalian = 'utang',
        ?int $userId = null
    ): ReturnPembelian {
        if ($po->status !== 'diterima') {
            throw ValidationException::withMessages(['po' => 'Hanya PO berstatus diterima yang dapat diretur ke supplier']);
        }

        $cabangId = (int) $po->gudangTujuan?->cabang_id;
        $gudangId = (int) $po->gudang_tujuan_id;

        if (empty($items)) {
            throw ValidationException::withMessages(['items' => 'Item retur pembelian tidak boleh kosong']);
        }

        return DB::transaction(function () use ($po, $items, $alasan, $metodePengembalian, $cabangId, $gudangId, $userId) {
            $poLocked = PurchaseOrder::whereKey($po->id)->lockForUpdate()->firstOrFail();

            $totalRetur = 0;
            $preparedItems = [];

            foreach ($items as $itemData) {
                $poItem = PurchaseOrderItem::with('produk')->where('purchase_order_id', $poLocked->id)
                    ->whereKey($itemData['purchase_order_item_id'])
                    ->lockForUpdate()
                    ->firstOrFail();

                $qtyRetur = (float) $itemData['jumlah'];
                if ($qtyRetur <= 0) {
                    continue;
                }

                // Cek stok fisik yang tersedia di gudang sebelum retur
                $stokItem = StokItem::where('gudang_id', $gudangId)
                    ->where('produk_id', $poItem->produk_id)
                    ->where('sku_variant_id', $poItem->sku_variant_id)
                    ->first();

                $stokTersedia = $stokItem ? (int) $stokItem->jumlah : 0;
                if ($qtyRetur > $stokTersedia) {
                    throw ValidationException::withMessages([
                        'items' => "Stok di gudang untuk produk {$poItem->produk?->nama} tidak mencukupi untuk diretur ({$stokTersedia} tersedia)",
                    ]);
                }

                // Cek riwayat retur sebelumnya
                $sudahDiretur = (float) ReturnPembelianItem::whereHas('returnPembelian', function ($q) {
                    $q->whereIn('status', ['draft', 'disetujui', 'selesai']);
                })->where('produk_id', $poItem->produk_id)
                    ->where('sku_variant_id', $poItem->sku_variant_id)
                    ->whereHas('returnPembelian', fn ($q) => $q->where('purchase_order_id', $poLocked->id))
                    ->sum('jumlah');

                $sisaBisaDiretur = (float) $poItem->jumlah - $sudahDiretur;
                if ($qtyRetur > $sisaBisaDiretur + 0.001) {
                    throw ValidationException::withMessages([
                        'items' => "Jumlah retur untuk produk {$poItem->produk?->nama} ({$qtyRetur}) melebihi sisa item PO ({$sisaBisaDiretur})",
                    ]);
                }

                $hargaBeli = (float) $poItem->harga_beli;
                $subtotal = round($hargaBeli * $qtyRetur, 2);

                $preparedItems[] = [
                    'po_item' => $poItem,
                    'qty' => $qtyRetur,
                    'harga_beli' => $hargaBeli,
                    'subtotal' => $subtotal,
                    'sn' => $itemData['sn'] ?? [],
                ];

                $totalRetur += $subtotal;
            }

            if (empty($preparedItems)) {
                throw ValidationException::withMessages(['items' => 'Minimal satu item harus diretur dengan jumlah > 0']);
            }

            $today = now()->format('Ymd');
            $count = ReturnPembelian::whereDate('created_at', now()->toDateString())->count() + 1;
            $noReturn = sprintf('RTB-%s-%04d', $today, $count);

            $retur = ReturnPembelian::create([
                'no_return' => $noReturn,
                'purchase_order_id' => $poLocked->id,
                'supplier_id' => $poLocked->supplier_id,
                'cabang_id' => $cabangId,
                'gudang_id' => $gudangId,
                'user_id' => $userId,
                'tanggal' => now()->toDateString(),
                'jumlah' => $totalRetur,
                'status' => 'draft',
                'metode_pengembalian' => in_array($metodePengembalian, ['utang', 'saldo', 'kas'], true) ? $metodePengembalian : 'utang',
                'alasan' => $alasan,
            ]);

            foreach ($preparedItems as $prep) {
                ReturnPembelianItem::create([
                    'return_pembelian_id' => $retur->id,
                    'produk_id' => $prep['po_item']->produk_id,
                    'sku_variant_id' => $prep['po_item']->sku_variant_id,
                    'jumlah' => $prep['qty'],
                    'harga_beli' => $prep['harga_beli'],
                    'subtotal' => $prep['subtotal'],
                ]);

                // Update nomor seri jika ada
                if (! empty($prep['sn'])) {
                    $this->nomorSeriService->returPembelian($prep['sn'], (int) $prep['po_item']->produk_id, $cabangId);
                }
            }

            // Cek apakah memicu approval workflow (misal rule > threshold)
            $needsApproval = $this->approvalService->adaPending('retur_pembelian', $retur->id);

            if (! $needsApproval) {
                $this->eksekusiRetur($retur, $userId);
            }

            return $retur->fresh(['items.produk', 'purchaseOrder', 'supplier']);
        });
    }

    /**
     * Buat retur pembelian langsung dari pool karantina barang rusak per supplier.
     *
     * @param  array<int, int>  $poolItemIds  Daftar ID dari return_penjualan_item
     */
    public function buatReturDariPoolSupplier(
        Supplier $supplier,
        array $poolItemIds,
        string $alasan,
        string $metodePengembalian = 'saldo',
        ?int $cabangId = null,
        ?int $gudangId = null,
        ?int $userId = null
    ): ReturnPembelian {
        if (empty($poolItemIds)) {
            throw ValidationException::withMessages(['items' => 'Pilih minimal satu barang dari pool karantina untuk diretur']);
        }

        $cabangId = $cabangId ?? session('cabang_id') ?? 1;
        if (! $gudangId) {
            $gudangId = Gudang::where('cabang_id', $cabangId)->where('is_active', true)->value('id')
                ?? Gudang::value('id');
        }

        return DB::transaction(function () use ($supplier, $poolItemIds, $alasan, $metodePengembalian, $cabangId, $gudangId, $userId) {
            $poolItems = ReturnPenjualanItem::with('produk')
                ->whereIn('id', $poolItemIds)
                ->where('status_karantina', 'karantina')
                ->lockForUpdate()
                ->get();

            if ($poolItems->isEmpty()) {
                throw ValidationException::withMessages(['items' => 'Tidak ada barang karantina yang valid untuk diproses']);
            }

            $totalRetur = 0;
            $today = now()->format('Ymd');
            $count = ReturnPembelian::whereDate('created_at', now()->toDateString())->count() + 1;
            $noReturn = sprintf('RTB-%s-%04d', $today, $count);

            $retur = ReturnPembelian::create([
                'no_return' => $noReturn,
                'purchase_order_id' => null,
                'supplier_id' => $supplier->id,
                'cabang_id' => $cabangId,
                'gudang_id' => $gudangId,
                'user_id' => $userId,
                'tanggal' => now()->toDateString(),
                'jumlah' => 0,
                'status' => 'draft',
                'metode_pengembalian' => in_array($metodePengembalian, ['saldo', 'utang', 'kas'], true) ? $metodePengembalian : 'saldo',
                'alasan' => $alasan,
            ]);

            foreach ($poolItems as $pItem) {
                $qty = (float) $pItem->jumlah;
                $hargaBeli = (float) ($pItem->hpp ?? $pItem->produk?->harga_beli ?? 0);
                $subtotal = round($hargaBeli * $qty, 2);

                ReturnPembelianItem::create([
                    'return_pembelian_id' => $retur->id,
                    'produk_id' => $pItem->produk_id,
                    'sku_variant_id' => $pItem->sku_variant_id,
                    'jumlah' => $qty,
                    'harga_beli' => $hargaBeli,
                    'subtotal' => $subtotal,
                ]);

                // Update status di pool penjualan
                $pItem->update([
                    'status_karantina' => 'sudah_diretur_supplier',
                    'return_pembelian_id' => $retur->id,
                ]);

                // Nomor seri
                if (! empty($pItem->nomor_seri)) {
                    $this->nomorSeriService->returPembelian($pItem->nomor_seri, (int) $pItem->produk_id, $cabangId);
                }

                $totalRetur += $subtotal;
            }

            $retur->update(['jumlah' => $totalRetur]);

            // Cek persetujuan workflow
            $needsApproval = $this->approvalService->adaPending('retur_pembelian', $retur->id);
            if (! $needsApproval) {
                $this->eksekusiRetur($retur, $userId);
            }

            return $retur->fresh(['items.produk', 'supplier']);
        });
    }

    /**
     * Finalisasi eksekusi retur pembelian: potong stok fisik (jika dari PO) & sesuaikan saldo/utang/kas.
     */
    public function eksekusiRetur(ReturnPembelian $retur, ?int $userId = null): void
    {
        if ($retur->status === 'selesai') {
            return;
        }

        DB::transaction(function () use ($retur, $userId) {
            $cabangId = (int) $retur->cabang_id;
            $gudangId = (int) $retur->gudang_id;
            $totalNilaiRetur = (float) $retur->jumlah;

            // Jika berasal dari PO (bukan pool karantina rusak), potong stok gudang
            if ($retur->purchase_order_id) {
                foreach ($retur->items as $item) {
                    $qty = (int) $item->jumlah;
                    $produkId = (int) $item->produk_id;
                    $skuVariantId = $item->sku_variant_id ? (int) $item->sku_variant_id : null;

                    $stokItem = StokItem::where('gudang_id', $gudangId)
                        ->where('produk_id', $produkId)
                        ->where('sku_variant_id', $skuVariantId)
                        ->lockForUpdate()
                        ->first();

                    if ($stokItem) {
                        $saldoAwal = (int) $stokItem->jumlah;
                        $stokItem->decrement('jumlah', $qty);
                        $saldoAkhir = $saldoAwal - $qty;

                        // Stok Log
                        StokLog::create([
                            'gudang_id' => $gudangId,
                            'produk_id' => $produkId,
                            'sku_variant_id' => $skuVariantId,
                            'user_id' => $userId,
                            'jenis' => 'keluar',
                            'referensi_tipe' => ReturnPembelian::class,
                            'referensi_id' => $retur->id,
                            'jumlah_sebelum' => $saldoAwal,
                            'perubahan' => -$qty,
                            'jumlah_setelah' => $saldoAkhir,
                            'catatan' => "Retur Pembelian {$retur->no_return}",
                        ]);

                        // Stock Mutation Log
                        StockMutationLog::create([
                            'produk_id' => $produkId,
                            'sku_variant_id' => $skuVariantId,
                            'gudang_id' => $gudangId,
                            'user_id' => $userId,
                            'delta' => -$qty,
                            'sumber' => 'retur_pembelian',
                            'referensi_tipe' => ReturnPembelian::class,
                            'referensi_id' => $retur->id,
                            'terjadi_at' => now(),
                        ]);
                    }
                }
            }

            // Jurnal Akuntansi Retur Pembelian:
            // Debit: Utang Usaha / Deposit Supplier (210-01) atau Kas (110-01)
            // Kredit: Persediaan Barang Dagang (130-01)
            $akunDebit = $retur->metode_pengembalian === 'kas' ? '110-01' : '210-01';

            if ($totalNilaiRetur > 0) {
                $this->jurnalService->post(
                    $retur->no_return,
                    now(),
                    'manual',
                    [
                        ['akun_kode' => $akunDebit, 'debit' => $totalNilaiRetur, 'kredit' => 0],
                        ['akun_kode' => '130-01', 'debit' => 0, 'kredit' => $totalNilaiRetur],
                    ],
                    "Retur Pembelian {$retur->no_return}".($retur->purchaseOrder ? " (PO: {$retur->purchaseOrder->no_po})" : " (Supplier: {$retur->supplier?->nama})"),
                    $cabangId,
                    $userId,
                    ReturnPembelian::class,
                    $retur->id
                );
            }

            // Penyelesaian Finansial Supplier
            if ($retur->metode_pengembalian === 'saldo') {
                $sisaAlokasi = $totalNilaiRetur;
                $supplier = $retur->supplier;

                // 1. Jika ada utang berjalan ke supplier ini, potong utang terlebih dahulu
                $openUtangs = Utang::where('kreditor_nama', $supplier?->nama)
                    ->where('status', '!=', 'lunas')
                    ->orderBy('id')
                    ->get();

                foreach ($openUtangs as $utang) {
                    if ($sisaAlokasi <= 0) {
                        break;
                    }
                    $sisaTagihan = max(0, (float) $utang->jumlah - (float) $utang->jumlah_dibayar);
                    $potong = min($sisaAlokasi, $sisaTagihan);

                    $utang->increment('jumlah_dibayar', $potong);
                    if ((float) $utang->jumlah_dibayar >= (float) $utang->jumlah - 0.01) {
                        $utang->update(['status' => 'lunas']);
                    } else {
                        $utang->update(['status' => 'sebagian']);
                    }
                    $sisaAlokasi -= $potong;
                }

                // 2. Jika masih ada sisa, simpan ke saldo_deposit supplier (kredit belanja PO berikutnya)
                if ($sisaAlokasi > 0 && $supplier) {
                    $supplier->increment('saldo_deposit', $sisaAlokasi);
                }
            } elseif ($retur->metode_pengembalian === 'utang' && $retur->purchase_order_id) {
                $utang = Utang::where('referensi_tipe', PurchaseOrder::class)
                    ->where('referensi_id', $retur->purchase_order_id)
                    ->first();

                if ($utang) {
                    $baruJumlah = max(0, (float) $utang->jumlah - $totalNilaiRetur);
                    $utang->update([
                        'jumlah' => $baruJumlah,
                        'status' => $baruJumlah <= (float) $utang->jumlah_dibayar + 0.01 ? 'lunas' : 'sebagian',
                    ]);
                }

                if ($retur->purchaseOrder) {
                    $retur->purchaseOrder->decrement('total', $totalNilaiRetur);
                }
            }

            $retur->update(['status' => 'selesai']);
        });
    }

    public function tolakRetur(ReturnPembelian $retur, ?string $alasan = null): void
    {
        $retur->update([
            'status' => 'ditolak',
            'alasan' => trim(($retur->alasan ? $retur->alasan.' | ' : '').($alasan ?? 'Ditolak via approval')),
        ]);
    }
}
