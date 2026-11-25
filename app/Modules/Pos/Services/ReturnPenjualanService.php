<?php

namespace App\Modules\Pos\Services;

use App\Modules\Akunting\Models\AkunCOA;
use App\Modules\Akunting\Models\Piutang;
use App\Modules\Akunting\Services\JurnalService;
use App\Modules\Crm\Models\Pelanggan;
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
use Illuminate\Support\Facades\Schema;
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

    public function generateNoReturn(string $prefix = 'RTJ', ?int $cabangId = null): string
    {
        $today = now()->format('Ymd');
        $count = ReturnPenjualan::whereDate('created_at', now()->toDateString())->count() + 1;

        if ($cabangId) {
            return sprintf('%s-C%d-%s-%04d', $prefix, $cabangId, $today, $count);
        }

        return sprintf('%s-%s-%04d', $prefix, $today, $count);
    }

    /**
     * Buat transaksi retur penjualan dari transaksi yang valid.
     *
     * @param  array<int, array{
     *     transaksi_item_id: int,
     *     jumlah: int|float,
     *     sn?: array<int, string>,
     *     kondisi_barang?: string,
     *     supplier_id?: int|null,
     *     gudang_retur_id?: int|null,
     *     rak_retur_id?: int|null,
     *     alasan?: string,
     *     tipe_penyelesaian?: string,
     *     metode_pengembalian?: string,
     *     foto_bukti?: array<int, string>,
     *     tukar_items?: array<int, array{produk_id: int, sku_variant_id?: int|null, jumlah: int|float, harga_satuan?: float}>
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
        float $kembalianSelisih = 0,
        ?int $gudangReturId = null
    ): ReturnPenjualan {
        $cabangId = (int) ($transaksi->cabang_id ?: (session('cabang_id') ?: Cabang::value('id') ?: 1));
        $gudangId = $this->resolveGudangId($gudangReturId ?: ($transaksi->gudang_id ? (int) $transaksi->gudang_id : null), $cabangId);

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

            // [H1] Rasio alokasi diskon header & PPN dari snapshot transaksi.
            // Diskon header (nominal/persen/partai/poin) mengurangi DPP secara
            // proporsional ke semua item; PPN retur = DPP retur x (pajak_nominal/DPP).
            $trxSubtotal = (float) $transaksiLocked->subtotal;
            $diskonHeader = (float) ($transaksiLocked->diskon_nominal ?? 0)
                + (float) ($transaksiLocked->diskon_partai ?? 0)
                + (float) ($transaksiLocked->diskon_poin ?? 0);
            if ((float) ($transaksiLocked->diskon_persen ?? 0) > 0) {
                $diskonHeader += round($trxSubtotal * (float) $transaksiLocked->diskon_persen / 100, 2);
            }
            $diskonHeader = min($trxSubtotal, $diskonHeader);
            // Prefer DPP tersimpan (kanonik checkout, termasuk mode PPN inclusive);
            // fallback rekonstruksi diskon utk transaksi legacy tanpa dpp.
            $dppTrx = (float) ($transaksiLocked->dpp ?? 0);
            if ($dppTrx <= 0) {
                $dppTrx = max(0.0, $trxSubtotal - $diskonHeader);
            }
            $rasioNeto = $trxSubtotal > 0 ? $dppTrx / $trxSubtotal : 1.0;
            $rasioPpn = $dppTrx > 0 ? (float) ($transaksiLocked->pajak_nominal ?? 0) / $dppTrx : 0.0;

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

                $konversiTrx = (float) ($trxItem->konversi ?? 1);
                if ($konversiTrx <= 0) {
                    $konversiTrx = 1;
                }

                // [FIX] qtyRetur dalam SATUAN (mis. box), bukan base unit. harga_satuan &
                // hpp trxItem tersimpan per satuan. Refund/HPP = harga per satuan x qtyRetur.
                // [H1] Harga neto diskon item: harga_final (neto, ditulis checkout)
                // fallback subtotal_item / jumlah. Diskon header dialokasikan proporsional.
                $hargaNetoItem = $trxItem->harga_final !== null
                    ? (float) $trxItem->harga_final
                    : ((float) $trxItem->jumlah > 0
                        ? (float) $trxItem->subtotal / (float) $trxItem->jumlah
                        : (float) ($trxItem->harga_satuan ?? 0));
                $subtotalDpp = round(round($hargaNetoItem * $qtyRetur, 2) * $rasioNeto, 2);
                $hargaSatuan = $qtyRetur > 0 ? round($subtotalDpp / $qtyRetur, 2) : 0.0;
                // Kontrak: refund item = harga_satuan (neto teralokasi) x qtyRetur.
                $subtotal = round($hargaSatuan * $qtyRetur, 2);
                $ppnRetur = round($subtotal * $rasioPpn, 2);
                $hppSatuan = (float) ($trxItem->hpp ?? $trxItem->produk?->harga_beli ?? 0);
                $subtotalHpp = round($hppSatuan * $qtyRetur, 2);
                // hpp per base unit untuk disimpan (eksekusiRetur menghitung hpp x qtyBase).
                $hppPerBase = $hppSatuan / $konversiTrx;

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
                $itemFoto = is_array($itemData['foto_bukti'] ?? null) ? $itemData['foto_bukti'] : [];
                $itemTukar = is_array($itemData['tukar_items'] ?? null) ? $itemData['tukar_items'] : [];

                if ($itemTipe === 'tukar_produk' && ! empty($itemTukar)) {
                    foreach ($itemTukar as $it) {
                        $allTukarItems[] = $it;
                    }
                }

                $tipeCount[$itemTipe] = ($tipeCount[$itemTipe] ?? 0) + 1;

                $preparedItems[] = [
                    'trx_item' => $trxItem,
                    'qty' => $qtyRetur,
                    'satuan' => $trxItem->satuan ?? 'pcs',
                    'konversi' => $konversiTrx,
                    'harga_satuan' => $hargaSatuan,
                    'subtotal' => $subtotal,
                    'ppn_nominal' => $ppnRetur,
                    'hpp' => $hppPerBase,
                    'sn' => $snList,
                    'kondisi_barang' => $kondisi,
                    'supplier_id' => $supplierId,
                    'alasan' => $itemAlasan,
                    'tipe_penyelesaian' => $itemTipe,
                    'metode_pengembalian' => $itemMetodePengembalian,
                    'foto_bukti' => $itemFoto,
                    'tukar_items' => $itemTukar,
                    'gudang_retur_id' => $itemData['gudang_retur_id'] ?? null,
                    'rak_retur_id' => $itemData['rak_retur_id'] ?? null,
                ];

                $totalRetur += $subtotal + $ppnRetur;
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

            $noReturn = $this->generateNoReturn();

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
                    'ppn_nominal' => $prep['ppn_nominal'],
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
                    'gudang_id' => $prep['gudang_retur_id'] ?? $gudangId,
                    'rak_id' => $prep['rak_retur_id'] ?? null,
                    'satuan' => $prep['satuan'],
                    'konversi' => $prep['konversi'],
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

            $harga = isset($tItem['harga_satuan']) ? (float) $tItem['harga_satuan'] : (float) ($stok?->produk?->harga_jual_retail ?? 0);
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
        $count = Transaksi::whereDate('created_at', now()->toDateString())
            ->where('cabang_id', $cabangId)->count() + 1;
        $noTransaksiTukar = sprintf('TRX-TUKAR-%s-%04d', $today, $count);
        while (Transaksi::where('no_transaksi', $noTransaksiTukar)->exists()) {
            $count++;
            $noTransaksiTukar = sprintf('TRX-TUKAR-%s-%04d', $today, $count);
        }

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
     *
     * Catatan: $totalHpp dipertahankan utk kompatibilitas pemanggil lama
     * (ApprovalService/buatRetur) tetapi DIABAIKAN — HPP selalu dihitung ulang
     * dari snapshot item (hpp per base x qtyDasar) dan dipisah bagus/rusak [H4].
     */
    public function eksekusiRetur(ReturnPenjualan $retur, ?float $totalHpp = null, ?int $userId = null): void
    {
        if ($retur->status === 'selesai') {
            return;
        }

        DB::transaction(function () use ($retur, $userId) {
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

            // [H4] Pisahkan HPP barang bagus (kembali ke persediaan) vs rusak
            // (write-down NRV; tidak masuk stok siap jual maupun GL 130-01).
            $hppBagus = 0.0;
            $hppRusak = 0.0;

            foreach ($retur->items as $item) {
                $qty = (int) $item->jumlah;
                $konversi = (float) ($item->konversi ?? 1);
                if ($konversi <= 0) {
                    $konversi = 1;
                }
                $qtyBase = (int) round($qty * $konversi);
                if ($qtyBase < 1) {
                    $qtyBase = $qty;
                }
                $produkId = (int) $item->produk_id;
                $skuVariantId = $item->sku_variant_id ? (int) $item->sku_variant_id : null;
                $hppSatuan = (float) ($item->hpp ?? $item->produk?->harga_beli ?? 0);
                $hppItem = $hppSatuan * $qtyBase;
                if ($item->kondisi_barang === 'bagus') {
                    $hppBagus += $hppItem;
                } else {
                    $hppRusak += $hppItem;
                }

                // HANYA barang berstatus BAGUS yang dimasukkan kembali ke stok aktif siap jual
                if ($item->kondisi_barang === 'bagus') {
                    $itemGudangId = $item->gudang_id ? (int) $item->gudang_id : $gudangId;
                    $itemGudangId = $this->resolveGudangId($itemGudangId, $cabangId);
                    $itemRakId = $item->rak_id ? (int) $item->rak_id : null;
                    // Pastikan baris stok ada sebelum dikembalikan
                    StokItem::firstOrCreate([
                        'gudang_id' => $itemGudangId,
                        'produk_id' => $produkId,
                        'sku_variant_id' => $skuVariantId,
                        'rak_id' => $itemRakId,
                    ], [
                        'jumlah' => 0,
                    ]);

                    $stokDeductionService->kembalikan(
                        produkId: $produkId,
                        skuVariantId: $skuVariantId,
                        gudangId: $itemGudangId,
                        qty: $qtyBase,
                        jenis: 'retur_penjualan',
                        referensiTipe: ReturnPenjualan::class,
                        referensiId: $retur->id,
                        userId: $userId,
                        catatan: "Retur Penjualan {$retur->no_return} (Layak Jual)",
                        rakId: $itemRakId
                    );
                } else {
                    // Barang Rusak: masuk karantina retur supplier, tidak dijual ke pelanggan lain
                    $item->update(['status_karantina' => 'karantina']);

                    $itemGudangId = $item->gudang_id ? (int) $item->gudang_id : $gudangId;
                    $itemGudangId = $this->resolveGudangId($itemGudangId, $cabangId);

                    StockMutationLog::create([
                        'produk_id' => $produkId,
                        'sku_variant_id' => $skuVariantId,
                        'gudang_id' => $itemGudangId,
                        'user_id' => $userId,
                        'delta' => 0,
                        'sumber' => 'retur_karantina',
                        'referensi_tipe' => ReturnPenjualan::class,
                        'referensi_id' => $retur->id,
                        'terjadi_at' => now(),
                    ]);
                }
            }

            // [H1] Pisah DPP vs PPN retur. retur->jumlah = DPP + PPN (snapshot
            // ppn_nominal per item dibuat saat buatRetur); retur legacy tanpa
            // ppn_nominal → seluruh nilai dianggap DPP (perilaku lama).
            $ppnRetur = min($totalNilaiRetur, round((float) $retur->items->sum('ppn_nominal'), 2));
            $dppRetur = round($totalNilaiRetur - $ppnRetur, 2);

            // Jika tukar_produk dan belum diproses transaksi penggantinya (misal dari deferred approval)
            if ($retur->tipe_penyelesaian === 'tukar_produk' && ! $retur->tukar_transaksi_id && ! empty($retur->tukar_items_payload)) {
                $this->prosesTukarProduk($retur, $retur->tukar_items_payload, $userId);
                $retur->refresh();
            }

            // Jurnal Pembukuan Retur:
            // Jika refund:
            // 1. Dr 410-02 (Retur Penjualan) = DPP neto retur; Dr 220-01 = porsi
            //    PPN keluaran dibalik [H1]; Kredit kas/piutang/deposit = total.
            // 2. Barang bagus: Dr 130-01 / Cr 510-02 (HPP) + restore stok.
            //    Barang rusak: Dr 520-08 / Cr 510-02 — write-down NRV=0 [H4],
            //    tidak menambah GL persediaan (barang di pool karantina).
            if ($retur->tipe_penyelesaian === 'refund') {
                $noJurnal = $this->jurnalService->generateNoJurnal('retur', $cabangId);
                $lines = [];
                if ($dppRetur > 0) {
                    $lines[] = ['akun_kode' => '410-02', 'debit' => $dppRetur, 'kredit' => 0];
                }
                if ($ppnRetur > 0) {
                    $lines[] = ['akun_kode' => '220-01', 'debit' => $ppnRetur, 'kredit' => 0];
                }

                if ($retur->metode_pengembalian === 'piutang') {
                    // [H3] Lookup referensi_tipe+referensi_id (di-set checkout),
                    // fallback transaksi_id utk piutang POS legacy / servis.
                    $piutang = Piutang::where('referensi_tipe', Transaksi::class)
                        ->where('referensi_id', $retur->transaksi_id)
                        ->orderBy('id')
                        ->first();
                    if (! $piutang) {
                        $piutang = Piutang::where('transaksi_id', $retur->transaksi_id)
                            ->where(fn ($q) => $q->whereNull('referensi_tipe')->orWhere('referensi_tipe', Transaksi::class))
                            ->orderBy('id')
                            ->first();
                    }
                    if ($piutang) {
                        $dibayarBaru = min(
                            (float) $piutang->jumlah,
                            round((float) $piutang->jumlah_dibayar + $totalNilaiRetur, 2)
                        );
                        $piutang->update([
                            'jumlah_dibayar' => $dibayarBaru,
                            'status' => $dibayarBaru >= (float) $piutang->jumlah - 0.01 ? 'lunas' : $piutang->status,
                        ]);
                    }
                    // [H3] Plafon kasbon pelanggan ikut turun (floor 0).
                    if ($retur->pelanggan_id) {
                        $pelangganAr = Pelanggan::find($retur->pelanggan_id);
                        if ($pelangganAr) {
                            $pelangganAr->update([
                                'saldo_piutang' => max(0, round((float) $pelangganAr->saldo_piutang - $totalNilaiRetur, 2)),
                            ]);
                        }
                    }
                    if ($totalNilaiRetur > 0) {
                        $lines[] = ['akun_kode' => '120-01', 'debit' => 0, 'kredit' => $totalNilaiRetur];
                    }
                } elseif ($retur->metode_pengembalian === 'saldo') {
                    // [H2] Refund ke deposit pelanggan: kredit liabilitas 210-07,
                    // bukan kas. Subledger pelanggan.saldo_deposit bertambah.
                    AkunCOA::firstOrCreate(
                        ['kode' => '210-07'],
                        ['nama' => 'Deposit Pelanggan', 'tipe' => 'kewajiban', 'kelompok' => 'utang_lain', 'saldo_normal' => 'kredit', 'is_active' => true]
                    );
                    if ($retur->pelanggan_id && $totalNilaiRetur > 0) {
                        Pelanggan::where('id', $retur->pelanggan_id)->increment('saldo_deposit', $totalNilaiRetur);
                    }
                    if ($totalNilaiRetur > 0) {
                        $lines[] = ['akun_kode' => '210-07', 'debit' => 0, 'kredit' => $totalNilaiRetur];
                    }
                } elseif ($totalNilaiRetur > 0) {
                    // [H2] Refund kas: hormati metode bayar asal. Tunai → Kas Laci
                    // (110-04) + mutasi laci 'keluar' bila ada sesi buka; tanpa
                    // sesi → Kas Toko (110-01). Bank/QRIS → 110-02. Split →
                    // alokasi proporsional porsi tunai & non-tunai.
                    $transaksiAsal = $retur->transaksi;
                    $metodeAsal = strtolower((string) ($transaksiAsal?->metode_bayar ?? 'tunai'));
                    $porsiTunai = $totalNilaiRetur;
                    $porsiBank = 0.0;
                    if ($metodeAsal === 'split') {
                        $detail = (array) ($transaksiAsal?->split_detail ?? []);
                        $totalAsal = (float) ($transaksiAsal?->total_akhir ?? 0);
                        $nonTunaiAsal = min($totalAsal, (float) ($detail['non_tunai'] ?? 0));
                        $tunaiAsal = max(0.0, $totalAsal - $nonTunaiAsal);
                        $rasio = $totalAsal > 0 ? $totalNilaiRetur / $totalAsal : 1.0;
                        $porsiTunai = round(min($totalNilaiRetur, $tunaiAsal * $rasio), 2);
                        $porsiBank = round($totalNilaiRetur - $porsiTunai, 2);
                    } elseif (! in_array($metodeAsal, ['tunai', 'piutang', 'kasbon'], true)) {
                        $porsiTunai = 0.0;
                        $porsiBank = $totalNilaiRetur;
                    }

                    if ($porsiTunai > 0) {
                        $dariLaci = $this->catatRefundKasLaci($retur, $porsiTunai, $cabangId, $userId, $noJurnal);
                        $lines[] = ['akun_kode' => $dariLaci ? '110-04' : '110-01', 'debit' => 0, 'kredit' => $porsiTunai];
                    }
                    if ($porsiBank > 0) {
                        $lines[] = ['akun_kode' => '110-02', 'debit' => 0, 'kredit' => $porsiBank];
                    }
                }

                // [H4] Hanya barang bagus yang kembali ke persediaan (130-01).
                if ($hppBagus > 0) {
                    $lines[] = ['akun_kode' => '130-01', 'debit' => $hppBagus, 'kredit' => 0];
                    $lines[] = ['akun_kode' => '510-02', 'debit' => 0, 'kredit' => $hppBagus];
                }
                // Barang rusak: kerugian write-down NRV (asumsi NRV=0; nilai
                // recovery dari supplier diakui nanti saat klaim retur pembelian).
                if ($hppRusak > 0) {
                    $lines[] = ['akun_kode' => '520-08', 'debit' => $hppRusak, 'kredit' => 0];
                    $lines[] = ['akun_kode' => '510-02', 'debit' => 0, 'kredit' => $hppRusak];
                }

                if (! empty($lines)) {
                    $this->jurnalService->post(
                        $noJurnal,
                        now(),
                        'retur',
                        $lines,
                        "Retur Penjualan {$retur->no_return} (Nota: {$retur->transaksi?->no_transaksi})",
                        $cabangId,
                        $userId,
                        ReturnPenjualan::class,
                        $retur->id
                    );
                }
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
                // Basis DPP (tanpa PPN) — perlakuan PPN tukar produk menyusul (M2);
                // menjaga jurnal tukar tetap balance seperti perilaku sebelumnya.
                $nilaiReturTukar = $dppRetur;
                $totalNilaiPengganti = $tukarTrx ? (float) $tukarTrx->total_akhir : $nilaiReturTukar;
                $selisihNominal = max(0, round((float) ($retur->selisih_nominal ?? ($totalNilaiPengganti - $nilaiReturTukar)), 2));

                $hppPengganti = 0;
                if ($tukarTrx) {
                    $tukarTrx->loadMissing('items.produk');
                    foreach ($tukarTrx->items as $tItem) {
                        $hppSatuanPengganti = (float) ($tItem->hpp ?? $tItem->produk?->harga_beli ?? 0);
                        $hppPengganti += ($hppSatuanPengganti * (float) $tItem->jumlah);
                    }
                }

                $lines = [
                    ['akun_kode' => '410-02', 'debit' => $nilaiReturTukar, 'kredit' => 0],
                    ['akun_kode' => '410-01', 'debit' => 0, 'kredit' => $totalNilaiPengganti],
                ];

                if ($selisihNominal > 0) {
                    $lines[] = ['akun_kode' => '110-01', 'debit' => $selisihNominal, 'kredit' => 0];
                }

                // [H4] Sama seperti refund: hanya barang bagus kembali ke 130-01;
                // barang rusak diakui write-down NRV di 520-08.
                if ($hppBagus > 0) {
                    $lines[] = ['akun_kode' => '130-01', 'debit' => $hppBagus, 'kredit' => 0];
                    $lines[] = ['akun_kode' => '510-02', 'debit' => 0, 'kredit' => $hppBagus];
                }
                if ($hppRusak > 0) {
                    $lines[] = ['akun_kode' => '520-08', 'debit' => $hppRusak, 'kredit' => 0];
                    $lines[] = ['akun_kode' => '510-02', 'debit' => 0, 'kredit' => $hppRusak];
                }

                if ($hppPengganti > 0) {
                    $lines[] = ['akun_kode' => '510-02', 'debit' => $hppPengganti, 'kredit' => 0];
                    $lines[] = ['akun_kode' => '130-01', 'debit' => 0, 'kredit' => $hppPengganti];
                }

                $noJurnalTukar = $this->jurnalService->generateNoJurnal('retur', $cabangId);
                $this->jurnalService->post(
                    $noJurnalTukar,
                    now(),
                    'retur',
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

    /**
     * [H2] Catat pengeluaran refund retur di kas_mutasi_laci (pola
     * GrnService::catatPengeluaranKasLaci): murni baris subledger drawer —
     * jurnal GL (Cr 110-04) sudah diposting oleh eksekusiRetur, jadi TIDAK
     * ada jurnal kedua di sini. Idempoten per (sesi, no_jurnal, jenis).
     *
     * @return bool true bila refund keluar dari laci (sesi buka ditemukan);
     *              false bila tidak ada sesi → caller kredit Kas Toko 110-01.
     */
    protected function catatRefundKasLaci(ReturnPenjualan $retur, float $nominal, int $cabangId, ?int $userId, string $noJurnal): bool
    {
        if ($nominal <= 0 || ! Schema::hasTable('kas_mutasi_laci') || ! Schema::hasTable('kas_sesi')) {
            return false;
        }

        // Prioritas 1: sesi buka milik kasir yang mengeksekusi retur.
        $sesi = DB::table('kas_sesi')
            ->where('cabang_id', $cabangId)
            ->where('status', 'buka')
            ->where('user_id', $userId)
            ->latest('id')
            ->first();

        // Prioritas 2: sesi buka apa pun di cabang (sesi bersama legacy).
        if (! $sesi) {
            $sesi = DB::table('kas_sesi')
                ->where('cabang_id', $cabangId)
                ->where('status', 'buka')
                ->latest('id')
                ->first();
        }

        if (! $sesi) {
            return false;
        }

        // user_id wajib (FK users); tanpa kasir teridentifikasi → bukan dari laci.
        $kasirId = $sesi->user_id ?? $userId;
        if (! $kasirId) {
            return false;
        }

        $exists = DB::table('kas_mutasi_laci')
            ->where('kas_sesi_id', $sesi->id)
            ->where('no_jurnal', $noJurnal)
            ->where('jenis', 'keluar')
            ->exists();

        if (! $exists) {
            DB::table('kas_mutasi_laci')->insert([
                'kas_sesi_id' => $sesi->id,
                'cabang_id' => $cabangId,
                'user_id' => $kasirId,
                'jenis' => 'keluar',
                'nominal' => $nominal,
                'akun_lawan_kode' => '410-02',
                'keterangan' => "Refund Retur Penjualan {$retur->no_return} (Nota: {$retur->transaksi?->no_transaksi})",
                'no_jurnal' => $noJurnal,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return true;
    }

    public function tolakRetur(ReturnPenjualan $retur, ?string $alasan = null): void
    {
        $retur->update([
            'status' => 'ditolak',
            'alasan' => trim(($retur->alasan ? $retur->alasan.' | ' : '').($alasan ?? 'Ditolak via approval/QC')),
        ]);
    }
}
