<?php

namespace App\Modules\Pos\Services;

use App\Modules\Akunting\Models\Piutang;
use App\Modules\Akunting\Services\JurnalService;
use App\Modules\Pos\Models\ReturnPenjualan;
use App\Modules\Pos\Models\ReturnPenjualanItem;
use App\Modules\Pos\Models\Transaksi;
use App\Modules\Pos\Models\TransaksiItem;
use App\Modules\Rbac\Models\Cabang;
use App\Modules\Rbac\Services\TransactionActivityLogService;
use App\Modules\Wms\Models\Gudang;
use App\Modules\Wms\Models\NomorSeri;
use App\Modules\Wms\Models\Produk;
use App\Modules\Wms\Models\PurchaseOrderItem;
use App\Modules\Wms\Models\StockMutationLog;
use App\Modules\Wms\Models\StokItem;
use App\Modules\Wms\Models\Supplier;
use App\Modules\Wms\Services\NomorSeriService;
use App\Modules\Wms\Services\StokDeductionService;
use App\Modules\Workflow\Services\ApprovalService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReturnPenjualanService
{
    public function __construct(
        protected JurnalService $jurnalService,
        protected NomorSeriService $nomorSeriService,
        protected ApprovalService $approvalService
    ) {}

    /**
     * Resolusi ID gudang yang valid dan terdaftar di database untuk cabang terkait.
     */
    public function resolveGudangId(?int $gudangId, int $cabangId): int
    {
        if ($gudangId && $gudangId > 0 && Gudang::where('id', $gudangId)->exists()) {
            return $gudangId;
        }

        $resolved = Gudang::where('cabang_id', $cabangId)->where('is_active', true)->value('id')
            ?? Gudang::where('cabang_id', $cabangId)->value('id')
            ?? (session('gudang_id') && Gudang::where('id', session('gudang_id'))->exists() ? (int) session('gudang_id') : null)
            ?? Gudang::where('is_active', true)->value('id')
            ?? Gudang::value('id');

        if ($resolved) {
            return (int) $resolved;
        }

        $gudang = Gudang::firstOrCreate(
            ['cabang_id' => $cabangId > 0 ? $cabangId : 1, 'nama' => 'Gudang Utama'],
            ['kode' => 'GDG-'.($cabangId > 0 ? $cabangId : 1).'-DEF', 'is_active' => true]
        );

        return (int) $gudang->id;
    }

    /**
     * Buat transaksi retur penjualan dari transaksi yang valid.
     *
     * @param  array<int, array{
     *     transaksi_item_id: int,
     *     jumlah: int|float,
     *     sn?: array<int, string>,
     *     kondisi_barang?: string,
     *     supplier_id?: int|null
     * }>  $items
     * @param  array<int, array{
     *     produk_id: int,
     *     sku_variant_id?: int|null,
     *     jumlah: int|float,
     *     harga_satuan?: float
     * }>  $tukarItems
     */
    public function buatRetur(
        Transaksi $transaksi,
        array $items,
        string $alasan = '',
        string $metodePengembalian = 'kas',
        ?int $userId = null,
        string $tipePenyelesaian = 'refund',
        array $tukarItems = [],
        string $channel = 'pos',
        array $fotoBukti = [],
        ?string $catatanQc = null,
        string $metodeBayarSelisih = 'tunai',
        float $jumlahBayarSelisih = 0,
        float $kembalianSelisih = 0
    ): ReturnPenjualan {
        $cabangId = (int) ($transaksi->cabang_id ?: (session('cabang_id') ?: Cabang::value('id') ?: 1));
        $gudangId = $this->resolveGudangId($transaksi->gudang_id ? (int) $transaksi->gudang_id : null, $cabangId);

        if (empty($items)) {
            throw ValidationException::withMessages(['items' => 'Item retur tidak boleh kosong']);
        }

        return DB::transaction(function () use (
            $transaksi, $items, $alasan, $metodePengembalian, $cabangId,
            $gudangId, $userId, $tipePenyelesaian, $tukarItems, $channel,
            $fotoBukti, $catatanQc, $metodeBayarSelisih, $jumlahBayarSelisih, $kembalianSelisih
        ) {
            $transaksiLocked = Transaksi::whereKey($transaksi->id)->lockForUpdate()->firstOrFail();

            // [ANTI-FRAUD] Deteksi Cerdas Masa Garansi
            $tglTransaksi = $transaksiLocked->created_at ?: now();
            $durasiGaransi = (int) ($transaksiLocked->durasi_garansi_hari ?? 3);
            if ($durasiGaransi <= 0) {
                $durasiGaransi = 3;
            }
            $garansiBerakhir = $transaksiLocked->garansi_berakhir_pada
                ? Carbon::parse($transaksiLocked->garansi_berakhir_pada)->endOfDay()
                : $tglTransaksi->copy()->addDays($durasiGaransi)->endOfDay();

            if (now()->greaterThan($garansiBerakhir)) {
                $lewatHari = max(1, (int) now()->diffInDays($garansiBerakhir));
                throw ValidationException::withMessages([
                    'garansi' => "Retur ditolak otomatis oleh sistem: Masa garansi transaksi telah habis sejak {$garansiBerakhir->format('d/m/Y')} ({$lewatHari} hari yang lalu). Garansi resmi toko berlaku {$durasiGaransi} hari sejak transaksi tanggal {$tglTransaksi->format('d/m/Y')}.",
                ]);
            }

            if (! $transaksiLocked->gudang_id || ! Gudang::where('id', $transaksiLocked->gudang_id)->exists() || ! $transaksiLocked->cabang_id) {
                $transaksiLocked->update([
                    'cabang_id' => $transaksiLocked->cabang_id ?: $cabangId,
                    'gudang_id' => $gudangId,
                ]);
            }

            $totalRetur = 0;
            $totalHpp = 0;
            $preparedItems = [];
            $allTukarItems = [];
            $tipeCount = ['refund' => 0, 'tukar_produk' => 0];

            foreach ($items as $itemData) {
                $trxItem = TransaksiItem::with('produk')->where('transaksi_id', $transaksiLocked->id)
                    ->whereKey($itemData['transaksi_item_id'])
                    ->lockForUpdate()
                    ->firstOrFail();

                $qtyRetur = (float) $itemData['jumlah'];
                if ($qtyRetur <= 0) {
                    continue;
                }

                // Hitung riwayat retur sebelumnya untuk item ini
                $sudahDiretur = (float) ReturnPenjualanItem::whereHas('returnPenjualan', function ($q) {
                    $q->whereIn('status', ['draft', 'disetujui', 'selesai']);
                })->where('produk_id', $trxItem->produk_id)
                    ->where('sku_variant_id', $trxItem->sku_variant_id)
                    ->whereHas('returnPenjualan', fn ($q) => $q->where('transaksi_id', $transaksiLocked->id))
                    ->sum('jumlah');

                $sisaBisaDiretur = (float) $trxItem->jumlah - $sudahDiretur;
                if ($qtyRetur > $sisaBisaDiretur + 0.001) {
                    throw ValidationException::withMessages([
                        'items' => "Jumlah retur untuk produk {$trxItem->produk?->nama} ({$qtyRetur}) melebihi sisa yang dapat diretur ({$sisaBisaDiretur})",
                    ]);
                }

                // [ANTI-FRAUD] Validasi Nomor Seri (SN) jika produk memiliki has_sn atau SN disertakan
                $snList = $itemData['sn'] ?? [];
                if (! empty($snList)) {
                    foreach ($snList as $sn) {
                        $snRow = NomorSeri::where('nomor_seri', $sn)
                            ->where('produk_id', $trxItem->produk_id)
                            ->first();

                        if (! $snRow) {
                            throw ValidationException::withMessages([
                                'items' => "Nomor seri [{$sn}] tidak ditemukan dalam sistem untuk produk ini.",
                            ]);
                        }

                        // Verifikasi SN harus pernah tercatat pada transaksi item ini atau berstatus terjual di cabang ini
                        if ($snRow->transaksi_item_id && (int) $snRow->transaksi_item_id !== (int) $trxItem->id) {
                            throw ValidationException::withMessages([
                                'items' => "Nomor seri [{$sn}] tercatat dari transaksi lain (bukan nomor faktur ini). Retur ditolak.",
                            ]);
                        }
                    }
                }

                $hargaSatuan = (float) ($trxItem->harga_final ?? $trxItem->harga_satuan ?? 0);
                $subtotal = round($hargaSatuan * $qtyRetur, 2);
                $hppSatuan = (float) ($trxItem->hpp ?? $trxItem->produk?->harga_beli ?? 0);
                $subtotalHpp = round($hppSatuan * $qtyRetur, 2);

                // Resolusi Supplier asal barang (dari input, atau PO terakhir, atau supplier aktif)
                $supplierId = $itemData['supplier_id'] ?? null;
                if (! $supplierId && $trxItem->produk_id) {
                    $poItem = PurchaseOrderItem::where('produk_id', $trxItem->produk_id)
                        ->whereHas('purchaseOrder', fn ($q) => $q->where('status', 'diterima'))
                        ->latest('id')
                        ->first();
                    $supplierId = $poItem?->purchaseOrder?->supplier_id;
                    if (! $supplierId) {
                        $supplierId = Supplier::where('is_active', true)->value('id');
                    }
                }

                $kondisi = ($itemData['kondisi_barang'] ?? 'bagus') === 'rusak' ? 'rusak' : 'bagus';
                $itemTipe = in_array($itemData['tipe_penyelesaian'] ?? $tipePenyelesaian, ['refund', 'tukar_produk'], true)
                    ? ($itemData['tipe_penyelesaian'] ?? $tipePenyelesaian)
                    : $tipePenyelesaian;
                $itemMetodePengembalian = in_array($itemData['metode_pengembalian'] ?? $metodePengembalian, ['kas', 'piutang', 'saldo'], true)
                    ? ($itemData['metode_pengembalian'] ?? $metodePengembalian)
                    : $metodePengembalian;
                $itemAlasan = ! empty($itemData['alasan']) ? (string) $itemData['alasan'] : $alasan;
                $itemFoto = ! empty($itemData['foto_bukti']) && is_array($itemData['foto_bukti']) ? $itemData['foto_bukti'] : [];
                $itemTukar = ! empty($itemData['tukar_items']) && is_array($itemData['tukar_items']) ? $itemData['tukar_items'] : [];

                if ($itemTipe === 'tukar_produk' && ! empty($itemTukar)) {
                    foreach ($itemTukar as $it) {
                        $allTukarItems[] = $it;
                    }
                }

                $tipeCount[$itemTipe] = ($tipeCount[$itemTipe] ?? 0) + 1;

                $preparedItems[] = [
                    'trx_item' => $trxItem,
                    'qty' => $qtyRetur,
                    'harga_satuan' => $hargaSatuan,
                    'subtotal' => $subtotal,
                    'hpp' => $hppSatuan,
                    'sn' => $snList,
                    'kondisi_barang' => $kondisi,
                    'supplier_id' => $supplierId,
                    'alasan' => $itemAlasan,
                    'tipe_penyelesaian' => $itemTipe,
                    'metode_pengembalian' => $itemMetodePengembalian,
                    'foto_bukti' => $itemFoto,
                    'tukar_items' => $itemTukar,
                ];

                $totalRetur += $subtotal;
                $totalHpp += $subtotalHpp;
            }

            if (empty($preparedItems)) {
                throw ValidationException::withMessages(['items' => 'Minimal satu item harus diretur dengan jumlah > 0']);
            }

            // Tentukan status tipe penyelesaian agregat (refund, tukar_produk, atau campur)
            $finalTipePenyelesaian = 'refund';
            if ($tipeCount['refund'] > 0 && $tipeCount['tukar_produk'] > 0) {
                $finalTipePenyelesaian = 'campur';
            } elseif ($tipeCount['tukar_produk'] > 0) {
                $finalTipePenyelesaian = 'tukar_produk';
            }

            // Validasi tukar barang jika ada item exchange
            if (empty($allTukarItems) && ! empty($tukarItems)) {
                $allTukarItems = $tukarItems;
            }

            if (! empty($allTukarItems)) {
                $returProdukIds = array_unique(array_map(fn ($p) => (int) $p['trx_item']->produk_id, $preparedItems));
                $returProduks = Produk::whereIn('id', $returProdukIds)->get()->keyBy('id');

                $tukarProdukIds = array_unique(array_map(fn ($t) => (int) $t['produk_id'], $allTukarItems));
                $tukarProduks = Produk::whereIn('id', $tukarProdukIds)->get()->keyBy('id');

                $minHargaUnit = 0;
                foreach ($preparedItems as $prep) {
                    if ($prep['tipe_penyelesaian'] === 'tukar_produk' && $prep['harga_satuan'] > $minHargaUnit) {
                        $minHargaUnit = (float) $prep['harga_satuan'];
                    }
                }
                if ($minHargaUnit <= 0) {
                    $minHargaUnit = (float) max(array_column($preparedItems, 'harga_satuan'));
                }

                foreach ($allTukarItems as $tItem) {
                    $tProduk = $tukarProduks->get($tItem['produk_id']);
                    if (! $tProduk) {
                        throw ValidationException::withMessages([
                            'tukar_items' => "Produk pengganti #{$tItem['produk_id']} tidak ditemukan.",
                        ]);
                    }

                    // Validasi harga: harga unit produk pengganti >= harga unit barang yang diretur (barang lebih murah DITOLAK)
                    $hargaUnitPengganti = isset($tItem['harga_satuan'])
                        ? (float) $tItem['harga_satuan']
                        : (float) ($tProduk->harga_jual_retail ?? $tProduk->harga_jual ?? 0);

                    if ($hargaUnitPengganti < $minHargaUnit - 0.01) {
                        throw ValidationException::withMessages([
                            'tukar_items' => "Harga satuan produk pengganti [{$tProduk->nama}] (Rp ".number_format($hargaUnitPengganti, 0, ',', '.').') tidak boleh lebih rendah dari harga barang yang diretur (Rp '.number_format($minHargaUnit, 0, ',', '.').').',
                        ]);
                    }
                }
            }

            $today = now()->format('Ymd');
            $count = ReturnPenjualan::whereDate('created_at', now()->toDateString())->count() + 1;
            $noReturn = sprintf('RTJ-%s-%04d', $today, $count);

            $retur = ReturnPenjualan::create([
                'no_return' => $noReturn,
                'transaksi_id' => $transaksiLocked->id,
                'pelanggan_id' => $transaksiLocked->pelanggan_id,
                'cabang_id' => $cabangId,
                'gudang_id' => $gudangId,
                'user_id' => $userId,
                'tanggal' => now()->toDateString(),
                'jumlah' => $totalRetur,
                'status' => 'draft',
                'metode_pengembalian' => in_array($metodePengembalian, ['kas', 'piutang', 'saldo'], true) ? $metodePengembalian : 'kas',
                'tipe_penyelesaian' => $finalTipePenyelesaian,
                'alasan' => $alasan,
                'foto_bukti' => $fotoBukti,
                'catatan_qc' => $catatanQc,
                'channel' => $channel,
                'metode_bayar_selisih' => $metodeBayarSelisih,
                'jumlah_bayar_selisih' => $jumlahBayarSelisih,
                'kembalian_selisih' => $kembalianSelisih,
            ]);

            foreach ($preparedItems as $prep) {
                ReturnPenjualanItem::create([
                    'return_penjualan_id' => $retur->id,
                    'produk_id' => $prep['trx_item']->produk_id,
                    'sku_variant_id' => $prep['trx_item']->sku_variant_id,
                    'jumlah' => $prep['qty'],
                    'harga_satuan' => $prep['harga_satuan'],
                    'subtotal' => $prep['subtotal'],
                    'hpp' => $prep['hpp'],
                    'kondisi_barang' => $prep['kondisi_barang'],
                    'nomor_seri' => $prep['sn'],
                    'supplier_id' => $prep['supplier_id'],
                    'status_karantina' => $prep['kondisi_barang'] === 'bagus' ? 'siap_jual' : 'karantina',
                    'alasan' => $prep['alasan'],
                    'tipe_penyelesaian' => $prep['tipe_penyelesaian'],
                    'metode_pengembalian' => $prep['metode_pengembalian'],
                    'tukar_items_payload' => ! empty($prep['tukar_items']) ? $prep['tukar_items'] : null,
                    'foto_bukti' => ! empty($prep['foto_bukti']) ? $prep['foto_bukti'] : null,
                ]);

                // Update status nomor seri
                if (! empty($prep['sn'])) {
                    if ($prep['kondisi_barang'] === 'bagus') {
                        $this->nomorSeriService->returPenjualan($prep['sn'], (int) $prep['trx_item']->produk_id, $cabangId);
                    } else {
                        NomorSeri::whereIn('nomor_seri', $prep['sn'])
                            ->where('produk_id', $prep['trx_item']->produk_id)
                            ->update(['status' => NomorSeri::STATUS_GARANSI]);
                    }
                }
            }

            // Cek apakah memerlukan persetujuan workflow (misal rule > threshold)
            $needsApproval = $this->approvalService->butuhApproval('retur', $cabangId, (float) $totalRetur);

            // Simpan payload produk pengganti pada retur
            if (! empty($allTukarItems)) {
                $retur->update(['tukar_items_payload' => $allTukarItems]);

                // Eksekusi pemotongan stok & pembuatan transaksi tukar sekarang HANYA jika tidak butuh approval
                if (! $needsApproval) {
                    $this->prosesTukarProduk($retur, $allTukarItems, $userId);
                }
            }

            // Jika tidak ada approval yang dibutuhkan, langsung eksekusi selesai
            if (! $needsApproval) {
                $this->eksekusiRetur($retur, $totalHpp, $userId);
            }

            $returResult = $retur->fresh(['items.produk', 'transaksi', 'pelanggan', 'tukarTransaksi.items.produk']);

            // [F1-4] Catat log aktivitas retur berbasis Transaction-Centric + Dual-Indexing di metadata
            app(TransactionActivityLogService::class)->catatReturPenjualan($returResult);

            return $returResult;
        });
    }

    /**
     * Proses transaksi tukar barang di POS.
     *
     * @param  array<int, array{produk_id: int, sku_variant_id?: int|null, jumlah: int|float, harga_satuan?: float}>  $tukarItems
     */
    protected function prosesTukarProduk(ReturnPenjualan $retur, array $tukarItems, ?int $userId = null): void
    {
        $cabangId = (int) ($retur->cabang_id ?: ($retur->transaksi?->cabang_id ?: (session('cabang_id') ?: Cabang::value('id') ?: 1)));
        $gudangId = $this->resolveGudangId($retur->gudang_id ? (int) $retur->gudang_id : null, $cabangId);

        if ($retur->gudang_id !== $gudangId || ! $retur->cabang_id) {
            $retur->update([
                'cabang_id' => $cabangId,
                'gudang_id' => $gudangId,
            ]);
        }
        $pelangganId = $retur->pelanggan_id;

        $totalTukar = 0;
        $preparedTukar = [];

        $stokDeductionService = app(StokDeductionService::class);

        foreach ($tukarItems as $tItem) {
            $qty = (float) $tItem['jumlah'];
            if ($qty <= 0) {
                continue;
            }

            $stok = $stokDeductionService->cariBarisStok($tItem['produk_id'], $tItem['sku_variant_id'] ?? null, $gudangId, false);

            $stokTersedia = $stok ? (int) $stok->jumlah : 0;
            if ($qty > $stokTersedia) {
                $namaProduk = $stok?->produk?->nama ?? "ID #{$tItem['produk_id']}";
                throw ValidationException::withMessages([
                    'tukar_items' => "Stok produk pengganti {$namaProduk} tidak mencukupi (hanya {$stokTersedia} tersedia).",
                ]);
            }

            $harga = isset($tItem['harga_satuan']) ? (float) $tItem['harga_satuan'] : (float) ($stok?->produk?->harga_jual_retail ?? $stok?->produk?->harga_jual ?? 0);
            $subtotal = round($harga * $qty, 2);

            $preparedTukar[] = [
                'produk_id' => $tItem['produk_id'],
                'sku_variant_id' => $tItem['sku_variant_id'] ?? null,
                'jumlah' => $qty,
                'harga_satuan' => $harga,
                'subtotal' => $subtotal,
            ];

            $totalTukar += $subtotal;
        }

        $returNilaiTukar = (float) $retur->items()
            ->where('tipe_penyelesaian', 'tukar_produk')
            ->sum('subtotal');
        if ($returNilaiTukar <= 0) {
            $returNilaiTukar = (float) $retur->jumlah;
        }

        $selisih = max(0, round($totalTukar - $returNilaiTukar, 2));

        // Buat transaksi POS pengganti
        $today = now()->format('Ymd');
        $count = Transaksi::whereDate('created_at', now()->toDateString())->count() + 1;
        $noTransaksiTukar = sprintf('TRX-TUKAR-%s-%04d', $today, $count);

        $metodeBayarSelisih = $retur->metode_bayar_selisih ?? 'tunai';
        $catatanBayar = $selisih > 0
            ? ' (Selisih Rp '.number_format($selisih, 0, ',', '.').' via '.strtoupper($metodeBayarSelisih).')'
            : ' (Tukar Impas Rp 0)';

        $transaksiTukar = Transaksi::create([
            'no_transaksi' => $noTransaksiTukar,
            'cabang_id' => $cabangId,
            'gudang_id' => $gudangId,
            'kasir_id' => $userId,
            'pelanggan_id' => $pelangganId,
            'sumber' => $retur->channel ?? 'pos',
            'subtotal' => $totalTukar,
            'total_akhir' => $totalTukar,
            'metode_bayar' => 'tukar_barang',
            'jumlah_bayar' => $totalTukar,
            'kembalian' => 0,
            'status' => 'selesai',
            'catatan' => "Tukar Produk dari Retur {$retur->no_return}{$catatanBayar}",
        ]);

        foreach ($preparedTukar as $p) {
            $pProduk = Produk::find($p['produk_id']);
            $hppItem = (float) ($pProduk?->harga_beli ?? 0);

            TransaksiItem::create([
                'transaksi_id' => $transaksiTukar->id,
                'produk_id' => $p['produk_id'],
                'sku_variant_id' => $p['sku_variant_id'],
                'jumlah' => $p['jumlah'],
                'harga_satuan' => $p['harga_satuan'],
                'harga_final' => $p['harga_satuan'],
                'subtotal' => $p['subtotal'],
                'hpp' => $hppItem,
            ]);

            // Potong stok barang pengganti via StokDeductionService kanonik
            $stokDeductionService->kurangi(
                produkId: (int) $p['produk_id'],
                skuVariantId: $p['sku_variant_id'] ? (int) $p['sku_variant_id'] : null,
                gudangId: $gudangId,
                qty: (int) $p['jumlah'],
                jenis: 'penjualan',
                referensiTipe: Transaksi::class,
                referensiId: $transaksiTukar->id,
                userId: $userId,
                catatan: "Tukar Produk Retur {$retur->no_return} TRX {$transaksiTukar->no_transaksi}"
            );
        }

        $retur->update([
            'tukar_transaksi_id' => $transaksiTukar->id,
            'selisih_nominal' => $selisih,
        ]);
    }

    /**
     * Finalisasi eksekusi retur: kembalikan stok fisik & catat jurnal pembukuan.
     */
    public function eksekusiRetur(ReturnPenjualan $retur, ?float $totalHpp = null, ?int $userId = null): void
    {
        if ($retur->status === 'selesai') {
            return;
        }

        DB::transaction(function () use ($retur, $totalHpp, $userId) {
            $cabangId = (int) ($retur->cabang_id ?: ($retur->transaksi?->cabang_id ?: (session('cabang_id') ?: Cabang::value('id') ?: 1)));
            $gudangId = $this->resolveGudangId($retur->gudang_id ? (int) $retur->gudang_id : null, $cabangId);

            if ($retur->gudang_id !== $gudangId || ! $retur->cabang_id) {
                $retur->update([
                    'cabang_id' => $cabangId,
                    'gudang_id' => $gudangId,
                ]);
            }

            $userId = $userId ?: ($retur->user_id ?: auth()->id());
            $totalNilaiRetur = (float) $retur->jumlah;
            $stokDeductionService = app(StokDeductionService::class);

            $hitungHpp = 0;

            foreach ($retur->items as $item) {
                $qty = (int) $item->jumlah;
                $produkId = (int) $item->produk_id;
                $skuVariantId = $item->sku_variant_id ? (int) $item->sku_variant_id : null;
                $hppSatuan = (float) ($item->hpp ?? $item->produk?->harga_beli ?? 0);
                $hitungHpp += ($hppSatuan * $qty);

                // HANYA barang berstatus BAGUS yang dimasukkan kembali ke stok aktif siap jual
                if ($item->kondisi_barang === 'bagus') {
                    // Pastikan baris stok ada sebelum dikembalikan
                    StokItem::firstOrCreate([
                        'gudang_id' => $gudangId,
                        'produk_id' => $produkId,
                        'sku_variant_id' => $skuVariantId,
                    ], [
                        'jumlah' => 0,
                    ]);

                    $stokDeductionService->kembalikan(
                        produkId: $produkId,
                        skuVariantId: $skuVariantId,
                        gudangId: $gudangId,
                        qty: $qty,
                        jenis: 'retur_penjualan',
                        referensiTipe: ReturnPenjualan::class,
                        referensiId: $retur->id,
                        userId: $userId,
                        catatan: "Retur Penjualan {$retur->no_return} (Layak Jual)"
                    );
                } else {
                    // Barang Rusak: masuk karantina retur supplier, tidak dijual ke pelanggan lain
                    $item->update(['status_karantina' => 'karantina']);

                    StockMutationLog::create([
                        'produk_id' => $produkId,
                        'sku_variant_id' => $skuVariantId,
                        'gudang_id' => $gudangId,
                        'user_id' => $userId,
                        'delta' => 0,
                        'sumber' => 'retur_karantina',
                        'referensi_tipe' => ReturnPenjualan::class,
                        'referensi_id' => $retur->id,
                        'terjadi_at' => now(),
                    ]);
                }
            }

            $hppFinal = $totalHpp ?? $hitungHpp;

            // Jika tukar_produk dan belum diproses transaksi penggantinya (misal dari deferred approval)
            if ($retur->tipe_penyelesaian === 'tukar_produk' && ! $retur->tukar_transaksi_id && ! empty($retur->tukar_items_payload)) {
                $this->prosesTukarProduk($retur, $retur->tukar_items_payload, $userId);
                $retur->refresh();
            }

            // Jurnal Pembukuan Retur:
            // Jika refund:
            // 1. Akun 410-02 (Retur Penjualan) Debit / Akun Kas/Piutang Kredit (Nilai Jual)
            // 2. Akun 130-01 (Persediaan) Debit / Akun 510-02 (HPP) Kredit (Nilai HPP)
            if ($retur->tipe_penyelesaian === 'refund') {
                $akunKreditPengembalian = $retur->metode_pengembalian === 'piutang' ? '120-01' : '110-01';

                if ($retur->metode_pengembalian === 'piutang') {
                    $piutang = Piutang::where('referensi_tipe', Transaksi::class)
                        ->where('referensi_id', $retur->transaksi_id)
                        ->first();
                    if ($piutang) {
                        $piutang->increment('jumlah_dibayar', $totalNilaiRetur);
                        if ((float) $piutang->jumlah_dibayar >= (float) $piutang->jumlah - 0.01) {
                            $piutang->update(['status' => 'lunas']);
                        }
                    }
                }

                $lines = [
                    ['akun_kode' => '410-02', 'debit' => $totalNilaiRetur, 'kredit' => 0],
                    ['akun_kode' => $akunKreditPengembalian, 'debit' => 0, 'kredit' => $totalNilaiRetur],
                ];

                if ($hppFinal > 0) {
                    $lines[] = ['akun_kode' => '130-01', 'debit' => $hppFinal, 'kredit' => 0];
                    $lines[] = ['akun_kode' => '510-02', 'debit' => 0, 'kredit' => $hppFinal];
                }

                $this->jurnalService->post(
                    $retur->no_return,
                    now(),
                    'manual',
                    $lines,
                    "Retur Penjualan {$retur->no_return} (Nota: {$retur->transaksi?->no_transaksi})",
                    $cabangId,
                    $userId,
                    ReturnPenjualan::class,
                    $retur->id
                );
            } elseif ($retur->tipe_penyelesaian === 'tukar_produk') {
                // Jurnal Pembukuan Retur Tukar Produk (Exchange)
                // 1. Pembalikan penjualan item retur:
                //    Debit 410-02 (Retur Penjualan) = totalNilaiRetur
                //    Debit 130-01 (Persediaan) = HPP item retur
                //    Kredit 510-02 (HPP) = HPP item retur
                // 2. Pengakuan penjualan barang pengganti:
                //    Kredit 410-01 (Pendapatan Penjualan) = totalNilaiPengganti
                //    Debit 510-02 (HPP) = HPP barang pengganti
                //    Kredit 130-01 (Persediaan) = HPP barang pengganti
                // 3. Selisih kas tambahan (jika ada selisih lebih mahal):
                //    Debit 110-01 (Kas Toko) = selisih
                //
                // Keseimbangan Jurnal:
                // Debit: 410-02 ($totalNilaiRetur) + [110-01 ($selisih)] + 130-01 ($hppFinal) + 510-02 ($hppPengganti)
                // Kredit: 410-01 ($totalNilaiPengganti) + 510-02 ($hppFinal) + 130-01 ($hppPengganti)
                // Karena $totalNilaiRetur + $selisih == $totalNilaiPengganti, sisi nominal pendapatan seimbang.

                $tukarTrx = $retur->tukarTransaksi;
                $totalNilaiPengganti = $tukarTrx ? (float) $tukarTrx->total_akhir : $totalNilaiRetur;
                $selisihNominal = max(0, round((float) ($retur->selisih_nominal ?? ($totalNilaiPengganti - $totalNilaiRetur)), 2));

                $hppPengganti = 0;
                if ($tukarTrx) {
                    $tukarTrx->loadMissing('items.produk');
                    foreach ($tukarTrx->items as $tItem) {
                        $hppSatuanPengganti = (float) ($tItem->hpp ?? $tItem->produk?->harga_beli ?? 0);
                        $hppPengganti += ($hppSatuanPengganti * (float) $tItem->jumlah);
                    }
                }

                $lines = [
                    ['akun_kode' => '410-02', 'debit' => $totalNilaiRetur, 'kredit' => 0],
                    ['akun_kode' => '410-01', 'debit' => 0, 'kredit' => $totalNilaiPengganti],
                ];

                if ($selisihNominal > 0) {
                    $lines[] = ['akun_kode' => '110-01', 'debit' => $selisihNominal, 'kredit' => 0];
                }

                if ($hppFinal > 0) {
                    $lines[] = ['akun_kode' => '130-01', 'debit' => $hppFinal, 'kredit' => 0];
                    $lines[] = ['akun_kode' => '510-02', 'debit' => 0, 'kredit' => $hppFinal];
                }

                if ($hppPengganti > 0) {
                    $lines[] = ['akun_kode' => '510-02', 'debit' => $hppPengganti, 'kredit' => 0];
                    $lines[] = ['akun_kode' => '130-01', 'debit' => 0, 'kredit' => $hppPengganti];
                }

                $this->jurnalService->post(
                    $retur->no_return,
                    now(),
                    'manual',
                    $lines,
                    "Retur Tukar Produk {$retur->no_return} (Nota: {$retur->transaksi?->no_transaksi})",
                    $cabangId,
                    $userId,
                    ReturnPenjualan::class,
                    $retur->id
                );
            }

            $retur->update(['status' => 'selesai']);
        });
    }

    public function tolakRetur(ReturnPenjualan $retur, ?string $alasan = null): void
    {
        $retur->update([
            'status' => 'ditolak',
            'alasan' => trim(($retur->alasan ? $retur->alasan.' | ' : '').($alasan ?? 'Ditolak via approval/QC')),
        ]);
    }
}
