<?php

namespace App\Modules\Wms\Services;

use App\Modules\Akunting\Models\Utang;
use App\Modules\Akunting\Services\JurnalService;
use App\Modules\Pos\Models\ReturnPenjualanItem;
use App\Modules\Rbac\Services\TransactionActivityLogService;
use App\Modules\Wms\Models\Grn;
use App\Modules\Wms\Models\Gudang;
use App\Modules\Wms\Models\NomorSeri;
use App\Modules\Wms\Models\Produk;
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
        protected ApprovalService $approvalService,
        protected StokDeductionService $stokDeductionService
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
        $isLegacyDirect = ($arg7 === null && $arg6 === null);

        if (empty($items)) {
            throw ValidationException::withMessages(['items' => 'Item retur pembelian tidak boleh kosong']);
        }

        return DB::transaction(function () use ($po, $items, $alasan, $metodePengembalian, $cabangId, $gudangId, $userId, $isLegacyDirect) {
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

                // [C3] Kontrak satuan POS-native: jumlah dalam SATUAN PO (mis. box),
                // stok selalu bergerak dalam base unit (qtyDasar = jumlah x konversi).
                $konversi = max(0.0001, (float) ($poItem->konversi ?? 1));
                $satuan = ($poItem->satuan ?? null) ?: 'pcs';
                $qtyDasar = (int) round($qtyRetur * $konversi);

                // Cek stok fisik yang tersedia di gudang sebelum retur.
                // [C3] Resolver kanonik (sama dgn POS/GRN): PO varian null tetap
                // menemukan baris stok ber-varian yang dibuat GRN.
                $stokItem = $this->stokDeductionService->cariBarisStok(
                    (int) $poItem->produk_id,
                    $poItem->sku_variant_id ? (int) $poItem->sku_variant_id : null,
                    $gudangId,
                    lock: false
                );

                $stokTersedia = $stokItem ? (int) $stokItem->jumlah : 0;
                if ($qtyDasar > $stokTersedia) {
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

                // [C3] Nilai retur at biaya GRN aktual (bukan harga PO) agar kredit
                // 130-01 sama dengan debit awal GRN — tanpa residu GL persediaan.
                $hargaBeli = (float) $poItem->harga_beli;
                $hargaGrn = $this->hargaGrnAktual($poLocked->id, $poItem);
                $subtotal = round(($hargaGrn ?? $hargaBeli) * $qtyRetur, 2);

                $preparedItems[] = [
                    'po_item' => $poItem,
                    'qty' => $qtyRetur,
                    'satuan' => $satuan,
                    'konversi' => $konversi,
                    'harga_beli' => $hargaBeli,
                    'harga_grn' => $hargaGrn,
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
                    'satuan' => $prep['satuan'],
                    'konversi' => $prep['konversi'],
                    'jumlah' => $prep['qty'],
                    'harga_beli' => $prep['harga_beli'],
                    'harga_grn' => $prep['harga_grn'],
                    'subtotal' => $prep['subtotal'],
                ]);

                // Update nomor seri jika ada
                if (! empty($prep['sn'])) {
                    $this->nomorSeriService->returPembelian($prep['sn'], (int) $prep['po_item']->produk_id, $cabangId);
                }
            }

            // Buat draft retur pembelian (fase 1: draft) atau langsung eksekusi bila legacy 5-arg caller
            if ($isLegacyDirect) {
                $needsApproval = $this->approvalService->adaPending('retur_pembelian', $retur->id);
                if (! $needsApproval) {
                    $this->eksekusiRetur($retur, $userId);
                }
            }

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

        $cabangId = $cabangId ?? currentCabangId() ?? auth()->user()?->cabangs()->first()?->id ?? Cabang::value('id');
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
                // [C3] Pool item mengikuti kontrak satuan C2: hpp per base unit,
                // jumlah dalam satuan jual. Nilai klaim = hpp x qtyDasar.
                $konversi = max(0.0001, (float) ($pItem->konversi ?? 1));
                $satuan = $pItem->satuan ?: 'pcs';
                $qtyDasar = (int) round($qty * $konversi);
                $hppPerBase = (float) ($pItem->hpp ?? $pItem->produk?->harga_beli ?? 0);
                $hargaBeli = round($hppPerBase * $konversi, 2);
                $subtotal = round($hppPerBase * $qtyDasar, 2);

                ReturnPembelianItem::create([
                    'return_pembelian_id' => $retur->id,
                    'produk_id' => $pItem->produk_id,
                    'sku_variant_id' => $pItem->sku_variant_id,
                    'satuan' => $satuan,
                    'konversi' => $konversi,
                    'jumlah' => $qty,
                    'harga_beli' => $hargaBeli,
                    'harga_grn' => null,
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
                // [C3] Restore simetris dengan potongan: qtyDasar = jumlah x konversi.
                $konversi = max(0.0001, (float) ($item->konversi ?? 1));
                $qty = (int) round((float) $item->jumlah * $konversi);
                $produkId = (int) $item->produk_id;
                $skuVariantId = $item->sku_variant_id ? (int) $item->sku_variant_id : null;

                // Resolver kanonik dulu (baris varian hasil GRN utk PO varian null);
                // fallback firstOrCreate utk pool karantina yang belum punya baris stok.
                $stokItem = $this->stokDeductionService->cariBarisStok($produkId, $skuVariantId, $gudangId, lock: true)
                    ?? StokItem::firstOrCreate(
                        [
                            'gudang_id' => $gudangId,
                            'produk_id' => $produkId,
                            'sku_variant_id' => $skuVariantId,
                        ],
                        ['jumlah' => 0]
                    );
                $skuVariantId = $stokItem->sku_variant_id ? (int) $stokItem->sku_variant_id : null;

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
            // [H13/M5] Lock + re-check status — penyelesaian ganda tidak dobel jurnal/deposit.
            $locked = ReturnPembelian::whereKey($retur->id)->lockForUpdate()->firstOrFail();
            if ($locked->status === 'selesai') {
                return $locked;
            }

            // Pastikan stok fisik telah terpotong (jika langsung diselesaikan dari draft)
            if ($locked->status === 'draft') {
                $this->potongStokFisik($locked, $userId);
            }

            $nilaiBuku = round((float) $locked->jumlah, 2);
            $realisasi = round($nilaiRealisasi, 2);
            $selisih = round($realisasi - $nilaiBuku, 2);

            $locked->update([
                'status' => 'selesai',
                'metode_pengembalian' => in_array($opsiPenyelesaian, ['saldo', 'utang', 'kas'], true) ? $opsiPenyelesaian : 'saldo',
                'nilai_realisasi' => $realisasi,
                'selisih_klaim' => $selisih,
                'tanggal_selesai' => now(),
                'catatan_penyelesaian' => $catatan ?? $locked->catatan_penyelesaian,
            ]);

            // Eksekusi mutasi finansial & pembukuan jurnal
            $this->jurnalDanFinansial($locked, $userId);

            // Update item karantina yang terikat menjadi sudah_diretur_supplier
            ReturnPenjualanItem::where('return_pembelian_id', $locked->id)
                ->where('status_karantina', '!=', 'sudah_diretur_supplier')
                ->update(['status_karantina' => 'sudah_diretur_supplier']);

            $returResult = $locked->fresh(['items.produk', 'supplier', 'gudang']);
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
                    // Kembalikan ke stok gudang via mutasi terdata
                    $deductionService = app(StokDeductionService::class);
                    foreach ($retur->items as $item) {
                        // [C3] Restore qtyDasar (base unit), simetris dengan potongan.
                        $konversi = max(0.0001, (float) ($item->konversi ?? 1));
                        $qtyDasar = (int) round((float) $item->jumlah * $konversi);
                        $deductionService->mutasi(
                            (int) $item->produk_id,
                            $item->sku_variant_id,
                            $gudangId,
                            $qtyDasar,
                            'penyesuaian',
                            'retur_pembelian_ditolak',
                            ReturnPembelian::class,
                            $retur->id,
                            $userId,
                            "Penolakan retur supplier {$retur->no_return} (opsi: kembali ke toko — {$alasanTolak})"
                        );
                    }
                }
                ReturnPenjualanItem::where('return_pembelian_id', $retur->id)
                    ->update(['status_karantina' => 'karantina']);
            } else {
                // Barang discrap/dimusnahkan di vendor -> Jurnal kerugian persediaan
                $nilaiBuku = round((float) $retur->jumlah, 2);
                if ($nilaiBuku > 0) {
                    $noJurnalTolak = $this->jurnalService->generateNoJurnal('retur', $cabangId);
                    $this->jurnalService->post(
                        $noJurnalTolak,
                        now(),
                        'retur',
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
     *
     * [C3] Kontrak satuan POS-native: potong SELALU qtyDasar = jumlah x konversi
     * (base unit); lookup resolver kanonik (kompatibel GRN varian-null); GAGAL
     * bila baris stok tidak ada / kurang — tidak ada silent skip sementara
     * jurnal tetap kredit persediaan.
     */
    protected function potongStokFisik(ReturnPembelian $retur, ?int $userId = null): void
    {
        if (! $retur->purchase_order_id) {
            return;
        }

        $gudangId = (int) $retur->gudang_id;

        foreach ($retur->items as $item) {
            $konversi = max(0.0001, (float) ($item->konversi ?? 1));
            $qtyDasar = (int) round((float) $item->jumlah * $konversi);
            if ($qtyDasar <= 0) {
                continue;
            }
            $produkId = (int) $item->produk_id;
            $skuVariantId = $item->sku_variant_id ? (int) $item->sku_variant_id : null;
            $namaProduk = Produk::whereKey($produkId)->value('nama') ?? "produk #{$produkId}";

            $stokItem = $this->stokDeductionService->cariBarisStok($produkId, $skuVariantId, $gudangId, lock: true);

            if (! $stokItem) {
                throw ValidationException::withMessages([
                    'items' => "Baris stok gudang untuk {$namaProduk} tidak ditemukan — retur tidak dapat dieksekusi",
                ]);
            }
            if ((int) $stokItem->jumlah < $qtyDasar) {
                throw ValidationException::withMessages([
                    'items' => "Stok di gudang untuk {$namaProduk} tidak mencukupi untuk diretur ({$stokItem->jumlah} tersedia)",
                ]);
            }

            $saldoAwal = (int) $stokItem->jumlah;
            $stokItem->decrement('jumlah', $qtyDasar);
            $saldoAkhir = $saldoAwal - $qtyDasar;
            // Log memakai varian baris stok aktual (bisa berbeda dari varian PO null).
            $varianAktual = $stokItem->sku_variant_id ? (int) $stokItem->sku_variant_id : null;

            // Stok Log
            StokLog::create([
                'gudang_id' => $gudangId,
                'produk_id' => $produkId,
                'sku_variant_id' => $varianAktual,
                'user_id' => $userId,
                'jenis' => 'keluar',
                'referensi_tipe' => ReturnPembelian::class,
                'referensi_id' => $retur->id,
                'jumlah_sebelum' => $saldoAwal,
                'perubahan' => -$qtyDasar,
                'jumlah_setelah' => $saldoAkhir,
                'catatan' => "Retur Pembelian {$retur->no_return}",
            ]);

            // Stock Mutation Log
            StockMutationLog::create([
                'produk_id' => $produkId,
                'sku_variant_id' => $varianAktual,
                'gudang_id' => $gudangId,
                'user_id' => $userId,
                'delta' => -$qtyDasar,
                'sumber' => 'retur_pembelian',
                'referensi_tipe' => ReturnPembelian::class,
                'referensi_id' => $retur->id,
                'terjadi_at' => now(),
            ]);

            // [C3/M7] Recompute weighted average setelah retur bila metode average.
            $hargaReturPerSatuan = (float) ($item->harga_grn ?? $item->harga_beli);
            $biayaReturPerBase = round($hargaReturPerSatuan / $konversi, 2);
            $this->sesuaikanAverageSetelahRetur($produkId, $qtyDasar, $biayaReturPerBase, (int) $retur->cabang_id);
        }
    }

    /**
     * [C3] Biaya GRN aktual per satuan PO untuk item retur (harga_beli pada
     * item_qty_received GRN = harga aktual saat terima, bukan harga PO).
     * [H8] Prefer harga_net (biaya neto PPN masukan yang dikapitalisasi ke
     * persediaan) agar kredit 130-01 sama dengan debit awal GRN — tanpa residu.
     * Null bila PO diterima tanpa GRN (jalur legacy) — fallback harga PO.
     */
    protected function hargaGrnAktual(int $poId, PurchaseOrderItem $poItem): ?float
    {
        $grn = Grn::where('po_id', $poId)->where('status', 'terima')->first();
        if (! $grn) {
            return null;
        }

        $varianPo = $poItem->sku_variant_id ? (int) $poItem->sku_variant_id : null;
        foreach ($grn->item_qty_received ?? [] as $row) {
            $varianRow = isset($row['sku_variant_id']) && $row['sku_variant_id'] ? (int) $row['sku_variant_id'] : null;
            if ((int) ($row['produk_id'] ?? 0) === (int) $poItem->produk_id && $varianRow === $varianPo) {
                $harga = (float) ($row['harga_net'] ?? $row['harga_beli'] ?? 0);

                return $harga > 0 ? $harga : null;
            }
        }

        return null;
    }

    /**
     * [C3/M7/H9] Hitung ulang weighted average setelah retur (metode 'average').
     * Delegasi ke ProdukService::sesuaikanAverageRetur — satu definisi kanonik
     * per cabang (biaya cabang + master global konsisten, semua varian di-update).
     */
    protected function sesuaikanAverageSetelahRetur(int $produkId, int $qtyDasar, float $biayaReturPerBase, int $cabangId): void
    {
        app(ProdukService::class)->sesuaikanAverageRetur($produkId, $qtyDasar, $biayaReturPerBase, $cabangId);
    }

    /**
     * Eksekusi mutasi finansial & pembukuan jurnal akuntansi retur pembelian.
     *
     * [H8] Kredit persediaan at biaya neto (harga_net GRN); PPN masukan yang
     * terkait porsi retur dibalik (Cr 110-03) — bukan pendapatan lain-lain.
     * [H13] Satu semantik AP: klaim dialokasikan sebagai credit-note
     * (jumlah_dibayar), pokok utang/total PO tidak diubah; kelebihan klaim
     * menjadi deposit supplier yang DIJURNAL (Dr 120-05 / Cr 210-01) sehingga
     * AP tidak pernah negatif tanpa liabilitas lawan dan deposit tidak off-books.
     */
    protected function jurnalDanFinansial(ReturnPembelian $retur, ?int $userId = null): void
    {
        $cabangId = (int) $retur->cabang_id;
        $nilaiBuku = round((float) $retur->jumlah, 2);

        // [H8] PPN masukan dibalik proporsional terhadap nilai buku neto yang diretur.
        $ppnRetur = $this->ppnReturDariGrn($retur, $nilaiBuku);

        // Klaim default = buku neto + PPN dapat dipulihkan (supplier owes bruto).
        $nilaiKlaim = round((float) ($retur->nilai_realisasi ?? ($nilaiBuku + $ppnRetur)), 2);
        $selisihKlaim = round($nilaiKlaim - $nilaiBuku - $ppnRetur, 2);

        if (($nilaiBuku > 0 || $nilaiKlaim > 0) && $retur->metode_pengembalian !== 'tukar_unit') {
            $akunDebit = $retur->metode_pengembalian === 'kas' ? '110-01' : '210-01';

            $jurnalLines = [
                ['akun_kode' => $akunDebit, 'debit' => $nilaiKlaim, 'kredit' => 0],
            ];
            if ($nilaiBuku > 0) {
                $jurnalLines[] = ['akun_kode' => '130-01', 'debit' => 0, 'kredit' => $nilaiBuku];
            }
            if ($ppnRetur > 0) {
                // [H8/F-32] Balik PPN masukan atas barang yang diretur.
                $jurnalLines[] = ['akun_kode' => '110-03', 'debit' => 0, 'kredit' => $ppnRetur];
            }

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

            $noJurnalSelesai = $this->jurnalService->generateNoJurnal('retur', $cabangId);
            $this->jurnalService->post(
                $noJurnalSelesai,
                now(),
                'retur',
                $jurnalLines,
                "Retur Pembelian {$retur->no_return}".($retur->purchaseOrder ? " (PO: {$retur->purchaseOrder->no_po})" : " (Supplier: {$retur->supplier?->nama})").($selisihKlaim != 0 ? ' [Selisih Klaim: Rp '.number_format($selisihKlaim, 0, ',', '.').']' : ''),
                $cabangId,
                $userId,
                ReturnPembelian::class,
                $retur->id
            );
        }

        // [H13] Penyelesaian finansial supplier (berdasarkan hak klaim riil $nilaiKlaim)
        if (in_array($retur->metode_pengembalian, ['saldo', 'utang'], true)) {
            $this->alokasikanKlaimSupplier($retur, $nilaiKlaim, $cabangId, $userId, $selisihKlaim);
        }
    }

    /**
     * [H8] PPN masukan yang terkait retur: proporsional nilai buku neto x tarif
     * PPN snapshot GRN asal. Tanpa PO/GRN ber-PPN → 0 (tidak ada reversal).
     */
    protected function ppnReturDariGrn(ReturnPembelian $retur, float $nilaiBuku): float
    {
        if ($nilaiBuku <= 0 || ! $retur->purchase_order_id) {
            return 0.0;
        }

        $grn = Grn::where('po_id', $retur->purchase_order_id)->where('status', 'terima')->first();
        if (! $grn || (float) $grn->ppn_nominal <= 0 || (float) $grn->ppn_percent <= 0) {
            return 0.0;
        }

        return round($nilaiBuku * (float) $grn->ppn_percent / 100, 2);
    }

    /**
     * [H13] Alokasi klaim retur dengan SATU semantik AP (credit-note):
     * 1. Potong utang terbuka via jumlah_dibayar (pokok jumlah tidak diubah);
     *    'utang' menarget Utang PO retur ini, 'saldo' semua utang supplier.
     *    Credit-note juga menaikkan total_dibayar PO terkait agar sisa konsisten.
     * 2. Kelebihan klaim → deposit supplier + jurnal Dr 120-05 / Cr 210-01
     *    (on-books; menutup debit AP porsi deposit sehingga AP tidak negatif).
     * Fail-closed: klaim deposit tanpa supplier → ValidationException (rollback).
     */
    protected function alokasikanKlaimSupplier(ReturnPembelian $retur, float $nilaiKlaim, int $cabangId, ?int $userId, float $selisihKlaim): void
    {
        $sisaAlokasi = $nilaiKlaim;
        $supplier = $retur->supplier;

        if ($retur->metode_pengembalian === 'utang' && $retur->purchase_order_id) {
            $utangPo = Utang::where('referensi_tipe', PurchaseOrder::class)
                ->where('referensi_id', $retur->purchase_order_id)
                ->whereIn('status', ['belum_lunas', 'sebagian'])
                ->first();
            $targets = $utangPo ? collect([$utangPo]) : collect();
        } else {
            $targets = Utang::where('kreditor_nama', $supplier?->nama)
                ->whereIn('status', ['belum_lunas', 'sebagian'])
                ->orderBy('id')
                ->get();
        }

        foreach ($targets as $utang) {
            if ($sisaAlokasi <= 0) {
                break;
            }
            $sisaTagihan = max(0, (float) $utang->jumlah - (float) $utang->jumlah_dibayar);
            $potong = min($sisaAlokasi, $sisaTagihan);
            if ($potong <= 0) {
                continue;
            }
            $this->catatCreditNoteUtang($utang, $potong);
            $sisaAlokasi = round($sisaAlokasi - $potong, 2);
        }

        if ($sisaAlokasi > 0) {
            if (! $supplier) {
                throw ValidationException::withMessages([
                    'supplier' => 'Supplier retur tidak ditemukan — klaim deposit tidak dapat dicatat on-books, retur dibatalkan',
                ]);
            }

            $supplier->tambahSaldoDeposit(
                $sisaAlokasi,
                "Deposit hasil Retur Pembelian {$retur->no_return}".($selisihKlaim != 0 ? ' (Realisasi Rp '.number_format($nilaiKlaim, 0, ',', '.').')' : ''),
                $retur,
                $cabangId,
                $userId
            );

            // [H13] Deposit supplier ON-BOOKS: Dr 120-05 Deposit Supplier / Cr 210-01.
            // Jurnal klaim sudah mendebit 210-01 penuh; kredit balik porsi deposit
            // membuat AP neto hanya turun sebesar utang yang benar-benar dikompensasi.
            $this->jurnalService->post(
                $this->jurnalService->generateNoJurnal('retur', $cabangId),
                now(),
                'retur',
                [
                    ['akun_kode' => '120-05', 'debit' => $sisaAlokasi, 'kredit' => 0],
                    ['akun_kode' => '210-01', 'debit' => 0, 'kredit' => $sisaAlokasi],
                ],
                "Deposit supplier Retur Pembelian {$retur->no_return}",
                $cabangId,
                $userId,
                ReturnPembelian::class,
                $retur->id
            );
        }
    }

    /**
     * [H13] Credit-note AP: tambah jumlah_dibayar (bukan potong pokok jumlah),
     * sinkronkan total_dibayar PO terkait agar sisa tagihan konsisten antar-jalur.
     */
    protected function catatCreditNoteUtang(Utang $utang, float $potong): void
    {
        $baruDibayar = round((float) $utang->jumlah_dibayar + $potong, 2);
        $utang->update([
            'jumlah_dibayar' => $baruDibayar,
            'status' => $baruDibayar >= (float) $utang->jumlah - 0.01 ? 'lunas' : 'sebagian',
        ]);

        if ($utang->referensi_tipe === PurchaseOrder::class) {
            PurchaseOrder::whereKey($utang->referensi_id)->increment('total_dibayar', $potong);
        }
    }

    /**
     * Finalisasi eksekusi retur pembelian: potong stok fisik & sesuaikan saldo/utang/kas.
     * [H13/M5] Lock baris retur + re-check status di dalam transaksi — eksekusi
     * bersamaan tidak menggandakan stok keluar, jurnal, maupun deposit supplier.
     */
    public function eksekusiRetur(ReturnPembelian $retur, ?int $userId = null): void
    {
        if ($retur->status === 'selesai') {
            return;
        }

        DB::transaction(function () use ($retur, $userId) {
            $locked = ReturnPembelian::whereKey($retur->id)->lockForUpdate()->firstOrFail();
            if ($locked->status === 'selesai') {
                $retur->setRawAttributes($locked->getAttributes(), true);

                return;
            }

            $this->potongStokFisik($locked, $userId);
            $this->jurnalDanFinansial($locked, $userId);
            $locked->update(['status' => 'selesai']);
            $retur->setRawAttributes($locked->getAttributes(), true);
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
