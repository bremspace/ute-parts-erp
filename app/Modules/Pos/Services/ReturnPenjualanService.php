<?php

namespace App\Modules\Pos\Services;

use App\Modules\Akunting\Models\Piutang;
use App\Modules\Akunting\Services\JurnalService;
use App\Modules\Pos\Models\ReturnPenjualan;
use App\Modules\Pos\Models\ReturnPenjualanItem;
use App\Modules\Pos\Models\Transaksi;
use App\Modules\Pos\Models\TransaksiItem;
use App\Modules\Wms\Models\StockMutationLog;
use App\Modules\Wms\Models\StokItem;
use App\Modules\Wms\Models\StokLog;
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
     * @param  array<int, array{transaksi_item_id: int, jumlah: int|float, sn?: array<int, string>}>  $items
     */
    public function buatRetur(
        Transaksi $transaksi,
        array $items,
        string $alasan,
        string $metodePengembalian = 'kas',
        ?int $userId = null
    ): ReturnPenjualan {
        $cabangId = (int) $transaksi->cabang_id;
        $gudangId = (int) $transaksi->gudang_id;

        if (empty($items)) {
            throw ValidationException::withMessages(['items' => 'Item retur tidak boleh kosong']);
        }

        return DB::transaction(function () use ($transaksi, $items, $alasan, $metodePengembalian, $cabangId, $gudangId, $userId) {
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

                $hargaSatuan = (float) ($trxItem->harga_final ?? $trxItem->harga_satuan ?? 0);
                $subtotal = round($hargaSatuan * $qtyRetur, 2);
                $hppSatuan = (float) ($trxItem->hpp ?? $trxItem->produk?->harga_beli ?? 0);
                $subtotalHpp = round($hppSatuan * $qtyRetur, 2);

                $preparedItems[] = [
                    'trx_item' => $trxItem,
                    'qty' => $qtyRetur,
                    'harga_satuan' => $hargaSatuan,
                    'subtotal' => $subtotal,
                    'hpp' => $hppSatuan,
                    'sn' => $itemData['sn'] ?? [],
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
                'alasan' => $alasan,
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
                ]);

                // Lepas nomor seri jika ada
                if (! empty($prep['sn'])) {
                    $this->nomorSeriService->returPenjualan($prep['sn'], (int) $prep['trx_item']->produk_id, $cabangId);
                }
            }

            // Cek apakah memerlukan persetujuan workflow (misal rule > threshold)
            $needsApproval = $this->approvalService->adaPending('retur', $retur->id);

            // Jika tidak ada approval pending (atau nominal di bawah threshold approval)
            if (! $needsApproval) {
                $this->eksekusiRetur($retur, $totalHpp, $userId);
            }

            return $retur->fresh(['items.produk', 'transaksi', 'pelanggan']);
        });
    }

    /**
     * Finalisasi eksekusi retur: kembalikan stok & catat jurnal pembukuan.
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

                // Tambahkan stok fisik kembali ke gudang
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
                    'catatan' => "Retur Penjualan {$retur->no_return}",
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
            }

            $hppFinal = $totalHpp ?? $hitungHpp;

            // Jurnal Retur Penjualan:
            // 1. Akun 410-02 (Retur Penjualan) Debit / Akun Kas/Piutang Kredit (Nilai Jual)
            // 2. Akun 130-01 (Persediaan) Debit / Akun 510-02 (HPP) Kredit (Nilai HPP)
            $akunKreditPengembalian = $retur->metode_pengembalian === 'piutang' ? '120-01' : '110-01';

            // Jika metode pengembalian adalah piutang, potong saldo piutang jika ada
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

            $retur->update(['status' => 'selesai']);
        });
    }

    public function tolakRetur(ReturnPenjualan $retur, ?string $alasan = null): void
    {
        $retur->update([
            'status' => 'ditolak',
            'alasan' => trim(($retur->alasan ? $retur->alasan.' | ' : '').($alasan ?? 'Ditolak via approval')),
        ]);
    }
}
