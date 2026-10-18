<?php

namespace App\Modules\Pos\Services;

use App\Modules\Akunting\Models\Piutang;
use App\Modules\Akunting\Services\JurnalService;
use App\Modules\Pos\Models\ReturnPenjualan;
use App\Modules\Pos\Models\ReturnPenjualanItem;
use App\Modules\Pos\Models\Transaksi;
use App\Modules\Pos\Models\TransaksiItem;
use App\Modules\Wms\Models\NomorSeri;
use App\Modules\Wms\Models\PurchaseOrderItem;
use App\Modules\Wms\Models\StockMutationLog;
use App\Modules\Wms\Models\StokItem;
use App\Modules\Wms\Models\StokLog;
use App\Modules\Wms\Models\Supplier;
use App\Modules\Wms\Services\NomorSeriService;
use App\Modules\Workflow\Services\ApprovalService;
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
        string $alasan,
        string $metodePengembalian = 'kas',
        ?int $userId = null,
        string $tipePenyelesaian = 'refund',
        array $tukarItems = [],
        string $channel = 'pos',
        array $fotoBukti = [],
        ?string $catatanQc = null
    ): ReturnPenjualan {
        $cabangId = (int) $transaksi->cabang_id;
        $gudangId = (int) $transaksi->gudang_id;

        if (empty($items)) {
            throw ValidationException::withMessages(['items' => 'Item retur tidak boleh kosong']);
        }

        return DB::transaction(function () use (
            $transaksi, $items, $alasan, $metodePengembalian, $cabangId,
            $gudangId, $userId, $tipePenyelesaian, $tukarItems, $channel,
            $fotoBukti, $catatanQc
        ) {
            $transaksiLocked = Transaksi::whereKey($transaksi->id)->lockForUpdate()->firstOrFail();

            $totalRetur = 0;
            $totalHpp = 0;
            $preparedItems = [];

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
                                'items' => "Nomor seri [{$sn}] tidak ditemukan dalam sistem Ute Parts untuk produk ini.",
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

                $preparedItems[] = [
                    'trx_item' => $trxItem,
                    'qty' => $qtyRetur,
                    'harga_satuan' => $hargaSatuan,
                    'subtotal' => $subtotal,
                    'hpp' => $hppSatuan,
                    'sn' => $snList,
                    'kondisi_barang' => $kondisi,
                    'supplier_id' => $supplierId,
                ];

                $totalRetur += $subtotal;
                $totalHpp += $subtotalHpp;
            }

            if (empty($preparedItems)) {
                throw ValidationException::withMessages(['items' => 'Minimal satu item harus diretur dengan jumlah > 0']);
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
                'tipe_penyelesaian' => in_array($tipePenyelesaian, ['refund', 'tukar_produk'], true) ? $tipePenyelesaian : 'refund',
                'alasan' => $alasan,
                'foto_bukti' => $fotoBukti,
                'catatan_qc' => $catatanQc,
                'channel' => $channel,
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

            // Jika tipe tukar_produk, proses transaksi pengganti
            if ($tipePenyelesaian === 'tukar_produk' && ! empty($tukarItems)) {
                $this->prosesTukarProduk($retur, $tukarItems, $userId);
            }

            // Cek apakah memerlukan persetujuan workflow (misal rule > threshold)
            $needsApproval = $this->approvalService->adaPending('retur', $retur->id);

            // Jika tidak ada approval pending
            if (! $needsApproval) {
                $this->eksekusiRetur($retur, $totalHpp, $userId);
            }

            return $retur->fresh(['items.produk', 'transaksi', 'pelanggan', 'tukarTransaksi']);
        });
    }

    /**
     * Proses transaksi tukar barang di POS.
     *
     * @param  array<int, array{produk_id: int, sku_variant_id?: int|null, jumlah: int|float, harga_satuan?: float}>  $tukarItems
     */
    protected function prosesTukarProduk(ReturnPenjualan $retur, array $tukarItems, ?int $userId = null): void
    {
        $cabangId = (int) $retur->cabang_id;
        $gudangId = (int) $retur->gudang_id;
        $pelangganId = $retur->pelanggan_id;

        $totalTukar = 0;
        $preparedTukar = [];

        foreach ($tukarItems as $tItem) {
            $qty = (float) $tItem['jumlah'];
            if ($qty <= 0) {
                continue;
            }

            $stok = StokItem::where('gudang_id', $gudangId)
                ->where('produk_id', $tItem['produk_id'])
                ->where('sku_variant_id', $tItem['sku_variant_id'] ?? null)
                ->first();

            $stokTersedia = $stok ? (int) $stok->jumlah : 0;
            if ($qty > $stokTersedia) {
                $namaProduk = $stok?->produk?->nama ?? "ID #{$tItem['produk_id']}";
                throw ValidationException::withMessages([
                    'tukar_items' => "Stok produk pengganti {$namaProduk} tidak mencukupi (hanya {$stokTersedia} tersedia).",
                ]);
            }

            $harga = isset($tItem['harga_satuan']) ? (float) $tItem['harga_satuan'] : (float) ($stok?->produk?->harga_jual ?? 0);
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

        $selisih = round($totalTukar - (float) $retur->jumlah, 2);

        // Buat transaksi POS pengganti
        $today = now()->format('Ymd');
        $count = Transaksi::whereDate('created_at', now()->toDateString())->count() + 1;
        $noTransaksiTukar = sprintf('TRX-TUKAR-%s-%04d', $today, $count);

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
            'catatan' => "Tukar Produk dari Retur {$retur->no_return} (Selisih: Rp ".number_format($selisih, 0, ',', '.').')',
        ]);

        foreach ($preparedTukar as $p) {
            TransaksiItem::create([
                'transaksi_id' => $transaksiTukar->id,
                'produk_id' => $p['produk_id'],
                'sku_variant_id' => $p['sku_variant_id'],
                'jumlah' => $p['jumlah'],
                'harga_satuan' => $p['harga_satuan'],
                'harga_final' => $p['harga_satuan'],
                'subtotal' => $p['subtotal'],
            ]);

            // Potong stok barang pengganti
            $stokItem = StokItem::where('gudang_id', $gudangId)
                ->where('produk_id', $p['produk_id'])
                ->where('sku_variant_id', $p['sku_variant_id'])
                ->first();

            if ($stokItem) {
                $stokItem->decrement('jumlah', (int) $p['jumlah']);
            }
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
            $cabangId = (int) $retur->cabang_id;
            $gudangId = (int) $retur->gudang_id;
            $totalNilaiRetur = (float) $retur->jumlah;

            $hitungHpp = 0;

            foreach ($retur->items as $item) {
                $qty = (int) $item->jumlah;
                $produkId = (int) $item->produk_id;
                $skuVariantId = $item->sku_variant_id ? (int) $item->sku_variant_id : null;
                $hppSatuan = (float) ($item->hpp ?? $item->produk?->harga_beli ?? 0);
                $hitungHpp += ($hppSatuan * $qty);

                // HANYA barang berstatus BAGUS yang dimasukkan kembali ke stok aktif siap jual
                if ($item->kondisi_barang === 'bagus') {
                    $stokItem = StokItem::firstOrCreate([
                        'gudang_id' => $gudangId,
                        'produk_id' => $produkId,
                        'sku_variant_id' => $skuVariantId,
                    ], [
                        'jumlah' => 0,
                    ]);

                    $saldoAwal = (int) $stokItem->jumlah;
                    $stokItem->increment('jumlah', $qty);
                    $saldoAkhir = $saldoAwal + $qty;

                    // Stok Log
                    StokLog::create([
                        'gudang_id' => $gudangId,
                        'produk_id' => $produkId,
                        'sku_variant_id' => $skuVariantId,
                        'user_id' => $userId,
                        'jenis' => 'masuk',
                        'referensi_tipe' => ReturnPenjualan::class,
                        'referensi_id' => $retur->id,
                        'jumlah_sebelum' => $saldoAwal,
                        'perubahan' => $qty,
                        'jumlah_setelah' => $saldoAkhir,
                        'catatan' => "Retur Penjualan {$retur->no_return} (Layak Jual)",
                    ]);

                    // Stock Mutation Log
                    StockMutationLog::create([
                        'produk_id' => $produkId,
                        'sku_variant_id' => $skuVariantId,
                        'gudang_id' => $gudangId,
                        'user_id' => $userId,
                        'delta' => $qty,
                        'sumber' => 'retur_penjualan',
                        'referensi_tipe' => ReturnPenjualan::class,
                        'referensi_id' => $retur->id,
                        'terjadi_at' => now(),
                    ]);
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
