<?php

namespace App\Modules\Wms\Services;

use App\Modules\Akunting\Models\Utang;
use App\Modules\Akunting\Services\JurnalService;
use App\Modules\Pos\Models\ReturnPenjualanItem;
use App\Modules\Rbac\Services\TransactionActivityLogService;
use App\Modules\Wms\Models\Gudang;
use App\Modules\Wms\Models\NomorSeri;
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
        mixed $arg5 = null,
        mixed $arg6 = null,
        ?int $arg7 = null
    ): ReturnPembelian {
        if ($po->status !== 'diterima') {
            throw ValidationException::withMessages(['po' => 'Hanya PO berstatus diterima yang dapat diretur ke supplier']);
        }

        // Tangani pemanggilan fleksibel (5 argumen atau 7 argumen)
        $userId = $arg7 !== null ? $arg7 : (is_int($arg5) && $arg6 === null ? $arg5 : auth()->id());
        $cabangId = is_int($arg5) && $arg6 !== null ? $arg5 : (int) $po->gudangTujuan?->cabang_id;
        $gudangId = is_int($arg6) ? $arg6 : (int) $po->gudang_tujuan_id;

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

            // Buat draft retur pembelian (fase 1: draft)
            $returResult = $retur->fresh(['items.produk', 'purchaseOrder', 'supplier', 'gudang']);
            app(TransactionActivityLogService::class)->catatReturnPembelian($returResult);

            return $returResult;
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
        ?int $userId = null,
        bool $langsungEksekusi = true
    ): ReturnPembelian {
        if (empty($poolItemIds)) {
            throw ValidationException::withMessages(['items' => 'Pilih minimal satu barang dari pool karantina untuk diretur']);
        }

        $cabangId = $cabangId ?? session('cabang_id') ?? 1;
        if (! $gudangId) {
            $gudangId = Gudang::where('cabang_id', $cabangId)->where('is_active', true)->value('id')
                ?? Gudang::value('id');
        }

        return DB::transaction(function () use ($supplier, $poolItemIds, $alasan, $metodePengembalian, $cabangId, $gudangId, $userId, $langsungEksekusi) {
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

                // Update status di pool penjualan (alokasikan ke draft)
                $pItem->update([
                    'status_karantina' => $langsungEksekusi ? 'sudah_diretur_supplier' : 'draft_retur_supplier',
                    'return_pembelian_id' => $retur->id,
                ]);

                // Nomor seri
                if (! empty($pItem->nomor_seri)) {
                    $this->nomorSeriService->returPembelian($pItem->nomor_seri, (int) $pItem->produk_id, $cabangId);
                }

                $totalRetur += $subtotal;
            }

            $retur->update(['jumlah' => $totalRetur]);

            if ($langsungEksekusi) {
                // Cek persetujuan workflow
                $needsApproval = $this->approvalService->adaPending('retur_pembelian', $retur->id);
                if (! $needsApproval) {
                    $this->eksekusiRetur($retur, $userId);
                }

                $returResult = $retur->fresh(['items.produk', 'supplier', 'gudang']);
                app(TransactionActivityLogService::class)->catatReturnPembelian($returResult);

                return $returResult;
            }

            return $retur->fresh(['items.produk', 'supplier', 'gudang']);
        });
    }

    /**
     * Tahap 1 -> Tahap 2: Konfirmasi pengiriman fisik barang ke supplier (In-Transit / Proses RMA).
     */
    public function kirimKeSupplier(
        ReturnPembelian $retur,
        array $dataPengiriman = [],
        ?int $userId = null
    ): ReturnPembelian {
        if ($retur->status !== 'draft') {
            return $retur;
        }

        return DB::transaction(function () use ($retur, $dataPengiriman, $userId) {
            // 1. Potong stok fisik gudang jika berasal dari PO (barang fisik keluar gudang)
            $this->potongStokFisik($retur, $userId);

            // 2. Jika dari pool karantina konsumen, update status item jadi 'dikirim_ke_supplier'
            ReturnPenjualanItem::where('return_pembelian_id', $retur->id)
                ->where('status_karantina', '!=', 'dikirim_ke_supplier')
                ->update(['status_karantina' => 'dikirim_ke_supplier']);

            // 3. Update status retur menjadi 'dikirim'
            $retur->update([
                'status' => 'dikirim',
                'ekspedisi' => $dataPengiriman['ekspedisi'] ?? $retur->ekspedisi,
                'no_resi' => $dataPengiriman['no_resi'] ?? $retur->no_resi,
                'penerima_vendor' => $dataPengiriman['penerima_vendor'] ?? $retur->penerima_vendor,
                'tanggal_kirim' => $dataPengiriman['tanggal_kirim'] ?? now(),
                'catatan_penyelesaian' => $dataPengiriman['catatan'] ?? $retur->catatan_penyelesaian,
                'kebijakan_retur' => $dataPengiriman['kebijakan_retur'] ?? $retur->kebijakan_retur ?? 'faktur_asli',
            ]);

            $returResult = $retur->fresh(['items.produk', 'supplier', 'gudang']);
            app(TransactionActivityLogService::class)->catatReturnPembelian($returResult);

            return $returResult;
        });
    }

    /**
     * Tahap 2 -> Tahap 3 (Opsi A): Penerimaan Unit Fisik Baru Pengganti dari Vendor (Tukar Unit / Replacement).
     */
    public function terimaUnitPengganti(
        ReturnPembelian $retur,
        array $detailPenerimaan = [],
        ?int $userId = null
    ): ReturnPembelian {
        if ($retur->status === 'selesai') {
            return $retur;
        }

        return DB::transaction(function () use ($retur, $detailPenerimaan, $userId) {
            $cabangId = (int) $retur->cabang_id;
            $gudangId = (int) ($detailPenerimaan['gudang_id'] ?? $retur->gudang_id);

            // 1. Jika retur masih draft (langsung tukar), potong fisik awal dulu
            if ($retur->status === 'draft') {
                $this->potongStokFisik($retur, $userId);
            }

            // 2. Tambah unit fisik baru ke StokItem aktif & catat mutasi masuk
            foreach ($retur->items as $item) {
                $qty = (int) $item->jumlah;
                $produkId = (int) $item->produk_id;
                $skuVariantId = $item->sku_variant_id ? (int) $item->sku_variant_id : null;

                $stokItem = StokItem::firstOrCreate(
                    [
                        'gudang_id' => $gudangId,
                        'produk_id' => $produkId,
                        'sku_variant_id' => $skuVariantId,
                    ],
                    ['jumlah' => 0]
                );

                $saldoAwal = (int) $stokItem->jumlah;
                $stokItem->increment('jumlah', $qty);
                $saldoAkhir = $saldoAwal + $qty;

                StokLog::create([
                    'gudang_id' => $gudangId,
                    'produk_id' => $produkId,
                    'sku_variant_id' => $skuVariantId,
                    'user_id' => $userId,
                    'jenis' => 'masuk',
                    'referensi_tipe' => ReturnPembelian::class,
                    'referensi_id' => $retur->id,
                    'jumlah_sebelum' => $saldoAwal,
                    'perubahan' => $qty,
                    'jumlah_setelah' => $saldoAkhir,
                    'catatan' => "Penerimaan Unit Pengganti Retur Supplier {$retur->no_return}",
                ]);

                StockMutationLog::create([
                    'produk_id' => $produkId,
                    'sku_variant_id' => $skuVariantId,
                    'gudang_id' => $gudangId,
                    'user_id' => $userId,
                    'delta' => $qty,
                    'sumber' => 'retur_replacement',
                    'referensi_tipe' => ReturnPembelian::class,
                    'referensi_id' => $retur->id,
                    'terjadi_at' => now(),
                ]);

                // Simpan nomor seri baru jika ada
                $snList = $detailPenerimaan['sn'][$produkId] ?? [];
                if (! empty($snList) && is_array($snList)) {
                    foreach ($snList as $sn) {
                        $cleanSn = trim((string) $sn);
                        if (! empty($cleanSn)) {
                            NomorSeri::create([
                                'produk_id' => $produkId,
                                'sku_variant_id' => $skuVariantId,
                                'cabang_id' => $cabangId,
                                'nomor_seri' => $cleanSn,
                                'status' => NomorSeri::STATUS_TERSEDIA,
                                'keterangan' => "Unit pengganti retur {$retur->no_return}",
                            ]);
                        }
                    }
                }
            }

            // 3. Update item karantina yang terikat menjadi sudah_diganti_supplier
            ReturnPenjualanItem::where('return_pembelian_id', $retur->id)
                ->update(['status_karantina' => 'sudah_diganti_supplier']);

            // 4. Update status retur selesai
            $retur->update([
                'status' => 'selesai',
                'metode_pengembalian' => 'tukar_unit',
                'tanggal_selesai' => now(),
                'unit_pengganti_detail' => $detailPenerimaan,
                'catatan_penyelesaian' => $detailPenerimaan['catatan'] ?? $retur->catatan_penyelesaian ?? 'Unit pengganti fisik diterima dari supplier',
            ]);

            $returResult = $retur->fresh(['items.produk', 'supplier', 'gudang']);
            app(TransactionActivityLogService::class)->catatReturnPembelian($returResult);

            return $returResult;
        });
    }

    /**
     * Tahap 2 -> Tahap 3 (Opsi B): Penerimaan Kompensasi Finansial dari Vendor (Saldo Deposit / Utang / Kas).
     */
    public function selesaikanKompensasiFinansial(
        ReturnPembelian $retur,
        float $nilaiRealisasi,
        string $opsiPenyelesaian = 'saldo',
        ?string $catatan = null,
        ?int $userId = null
    ): ReturnPembelian {
        if ($retur->status === 'selesai') {
            return $retur;
        }

        return DB::transaction(function () use ($retur, $nilaiRealisasi, $opsiPenyelesaian, $catatan, $userId) {
            // Pastikan stok fisik telah terpotong (jika langsung diselesaikan dari draft)
            if ($retur->status === 'draft') {
                $this->potongStokFisik($retur, $userId);
            }

            $nilaiBuku = round((float) $retur->jumlah, 2);
            $realisasi = round($nilaiRealisasi, 2);
            $selisih = round($realisasi - $nilaiBuku, 2);

            $retur->update([
                'status' => 'selesai',
                'metode_pengembalian' => in_array($opsiPenyelesaian, ['saldo', 'utang', 'kas'], true) ? $opsiPenyelesaian : 'saldo',
                'nilai_realisasi' => $realisasi,
                'selisih_klaim' => $selisih,
                'tanggal_selesai' => now(),
                'catatan_penyelesaian' => $catatan ?? $retur->catatan_penyelesaian,
            ]);

            // Eksekusi mutasi finansial & pembukuan jurnal
            $this->jurnalDanFinansial($retur, $userId);

            // Update item karantina yang terikat menjadi sudah_diretur_supplier
            ReturnPenjualanItem::where('return_pembelian_id', $retur->id)
                ->where('status_karantina', '!=', 'sudah_diretur_supplier')
                ->update(['status_karantina' => 'sudah_diretur_supplier']);

            $returResult = $retur->fresh(['items.produk', 'supplier', 'gudang']);
            app(TransactionActivityLogService::class)->catatReturnPembelian($returResult);

            return $returResult;
        });
    }

    /**
     * Tahap 2 -> Tahap 3 (Opsi C): Penolakan Klaim oleh Vendor (Reject).
     */
    public function tolakReturSupplier(
        ReturnPembelian $retur,
        string $alasanTolak,
        string $opsiBarang = 'kembali_ke_toko',
        ?int $userId = null
    ): ReturnPembelian {
        if ($retur->status === 'ditolak') {
            return $retur;
        }

        return DB::transaction(function () use ($retur, $alasanTolak, $opsiBarang, $userId) {
            $cabangId = (int) $retur->cabang_id;
            $gudangId = (int) $retur->gudang_id;

            if ($opsiBarang === 'kembali_ke_toko') {
                // Barang kembali ke toko
                if ($retur->purchase_order_id && $retur->status === 'dikirim') {
                    // Kembalikan ke stok gudang
                    foreach ($retur->items as $item) {
                        $stokItem = StokItem::firstOrCreate([
                            'gudang_id' => $gudangId,
                            'produk_id' => $item->produk_id,
                            'sku_variant_id' => $item->sku_variant_id,
                        ], ['jumlah' => 0]);
                        $stokItem->increment('jumlah', (int) $item->jumlah);
                    }
                }
                ReturnPenjualanItem::where('return_pembelian_id', $retur->id)
                    ->update(['status_karantina' => 'karantina']);
            } else {
                // Barang discrap/dimusnahkan di vendor -> Jurnal kerugian persediaan
                $nilaiBuku = round((float) $retur->jumlah, 2);
                if ($nilaiBuku > 0) {
                    $this->jurnalService->post(
                        $retur->no_return,
                        now(),
                        'manual',
                        [
                            ['akun_kode' => '520-08', 'debit' => $nilaiBuku, 'kredit' => 0],
                            ['akun_kode' => '130-01', 'debit' => 0, 'kredit' => $nilaiBuku],
                        ],
                        "Scrap/Penolakan Retur Supplier {$retur->no_return} ({$alasanTolak})",
                        $cabangId,
                        $userId,
                        ReturnPembelian::class,
                        $retur->id
                    );
                }
                ReturnPenjualanItem::where('return_pembelian_id', $retur->id)
                    ->update(['status_karantina' => 'scrap_vendor']);
            }

            $retur->update([
                'status' => 'ditolak',
                'tanggal_selesai' => now(),
                'catatan_penyelesaian' => $alasanTolak,
            ]);

            $returResult = $retur->fresh(['items.produk', 'supplier', 'gudang']);
            app(TransactionActivityLogService::class)->catatReturnPembelian($returResult);

            return $returResult;
        });
    }

    /**
     * Konfirmasi pengiriman dan selesaikan retur pembelian ke supplier (All-in-One backward compatibility).
     */
    public function kirimDanSelesaikanRetur(
        ReturnPembelian $retur,
        string $opsiPenyelesaian = 'saldo',
        ?string $catatanPengiriman = null,
        ?int $userId = null,
        ?float $nilaiRealisasi = null,
        ?string $kebijakanRetur = null
    ): ReturnPembelian {
        if ($retur->status === 'selesai') {
            return $retur;
        }

        if ($opsiPenyelesaian === 'tukar_unit') {
            if ($retur->status === 'draft') {
                $this->kirimKeSupplier($retur, ['catatan' => $catatanPengiriman, 'kebijakan_retur' => $kebijakanRetur], $userId);
            }

            return $this->terimaUnitPengganti($retur, ['catatan' => $catatanPengiriman], $userId);
        }

        if ($retur->status === 'draft') {
            $this->kirimKeSupplier($retur, ['catatan' => $catatanPengiriman, 'kebijakan_retur' => $kebijakanRetur], $userId);
        }

        $realisasi = $nilaiRealisasi !== null ? (float) $nilaiRealisasi : (float) $retur->jumlah;

        return $this->selesaikanKompensasiFinansial($retur, $realisasi, $opsiPenyelesaian, $catatanPengiriman, $userId);
    }

    /**
     * Batalkan draft retur pembelian dan kembalikan item ke pool karantina.
     */
    public function batalkanDraftRetur(ReturnPembelian $retur, ?string $alasan = null): void
    {
        if ($retur->status === 'selesai') {
            throw ValidationException::withMessages(['status' => 'Retur yang sudah selesai tidak dapat dibatalkan']);
        }

        DB::transaction(function () use ($retur, $alasan) {
            ReturnPenjualanItem::where('return_pembelian_id', $retur->id)
                ->update([
                    'status_karantina' => 'karantina',
                    'return_pembelian_id' => null,
                ]);

            $retur->update([
                'status' => 'dibatalkan',
                'catatan_penyelesaian' => $alasan ?: 'Draft dibatalkan oleh pengguna',
            ]);
        });
    }

    /**
     * Potong stok fisik gudang jika berasal dari PO (bukan pool karantina).
     */
    protected function potongStokFisik(ReturnPembelian $retur, ?int $userId = null): void
    {
        if (! $retur->purchase_order_id) {
            return;
        }

        $gudangId = (int) $retur->gudang_id;

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

    /**
     * Eksekusi mutasi finansial & pembukuan jurnal akuntansi retur pembelian.
     */
    protected function jurnalDanFinansial(ReturnPembelian $retur, ?int $userId = null): void
    {
        $cabangId = (int) $retur->cabang_id;
        $nilaiBuku = round((float) $retur->jumlah, 2);
        $nilaiKlaim = round((float) ($retur->nilai_realisasi ?? $nilaiBuku), 2);
        $selisihKlaim = round($nilaiKlaim - $nilaiBuku, 2);

        if ($nilaiBuku > 0 && $retur->metode_pengembalian !== 'tukar_unit') {
            $akunDebit = $retur->metode_pengembalian === 'kas' ? '110-01' : '210-01';

            $jurnalLines = [
                ['akun_kode' => $akunDebit, 'debit' => $nilaiKlaim, 'kredit' => 0],
                ['akun_kode' => '130-01', 'debit' => 0, 'kredit' => $nilaiBuku],
            ];

            if ($selisihKlaim < 0) {
                // Realisasi lebih rendah dari nilai buku persediaan -> Beban Selisih Klaim Retur
                $jurnalLines[] = [
                    'akun_kode' => '520-08',
                    'debit' => abs($selisihKlaim),
                    'kredit' => 0,
                ];
            } elseif ($selisihKlaim > 0) {
                // Realisasi lebih tinggi dari nilai buku persediaan -> Pendapatan Lain-lain
                $jurnalLines[] = [
                    'akun_kode' => '430-01',
                    'debit' => 0,
                    'kredit' => $selisihKlaim,
                ];
            }

            $this->jurnalService->post(
                $retur->no_return,
                now(),
                'manual',
                $jurnalLines,
                "Retur Pembelian {$retur->no_return}".($retur->purchaseOrder ? " (PO: {$retur->purchaseOrder->no_po})" : " (Supplier: {$retur->supplier?->nama})").($selisihKlaim != 0 ? ' [Selisih Klaim: Rp '.number_format($selisihKlaim, 0, ',', '.').']' : ''),
                $cabangId,
                $userId,
                ReturnPembelian::class,
                $retur->id
            );
        }

        // Penyelesaian Finansial Supplier (berdasarkan hak klaim riil $nilaiKlaim)
        if ($retur->metode_pengembalian === 'saldo') {
            $sisaAlokasi = $nilaiKlaim;
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
                $supplier->tambahSaldoDeposit(
                    $sisaAlokasi,
                    "Deposit hasil Retur Pembelian {$retur->no_return}".($selisihKlaim != 0 ? ' (Realisasi Rp '.number_format($nilaiKlaim, 0, ',', '.').')' : ''),
                    $retur,
                    $cabangId,
                    $userId
                );
            }
        } elseif ($retur->metode_pengembalian === 'utang' && $retur->purchase_order_id) {
            $utang = Utang::where('referensi_tipe', PurchaseOrder::class)
                ->where('referensi_id', $retur->purchase_order_id)
                ->first();

            if ($utang) {
                $baruJumlah = max(0, (float) $utang->jumlah - $nilaiKlaim);
                $utang->update([
                    'jumlah' => $baruJumlah,
                    'status' => $baruJumlah <= (float) $utang->jumlah_dibayar + 0.01 ? 'lunas' : 'sebagian',
                ]);
            }

            if ($retur->purchaseOrder) {
                $retur->purchaseOrder->decrement('total', $nilaiKlaim);
            }
        }
    }

    /**
     * Finalisasi eksekusi retur pembelian: potong stok fisik & sesuaikan saldo/utang/kas.
     */
    public function eksekusiRetur(ReturnPembelian $retur, ?int $userId = null): void
    {
        if ($retur->status === 'selesai') {
            return;
        }

        DB::transaction(function () use ($retur, $userId) {
            $this->potongStokFisik($retur, $userId);
            $this->jurnalDanFinansial($retur, $userId);
            $retur->update(['status' => 'selesai']);
        });
    }

    public function setujuiRetur(ReturnPembelian $retur, ?int $userId = null): void
    {
        $this->eksekusiRetur($retur, $userId);
    }

    public function tolakRetur(ReturnPembelian $retur, ?string $alasan = null): void
    {
        $this->tolakReturSupplier($retur, $alasan ?? 'Ditolak via approval');
    }
}
