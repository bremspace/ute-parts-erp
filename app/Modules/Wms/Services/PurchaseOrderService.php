<?php

namespace App\Modules\Wms\Services;

use App\Modules\Akunting\Models\Piutang;
use App\Modules\Akunting\Services\JurnalService;
use App\Modules\Wms\Models\PembayaranSupplier;
use App\Modules\Wms\Models\PurchaseOrder;
use App\Modules\Wms\Models\PurchaseOrderItem;
use Illuminate\Support\Facades\DB;

/**
 * [T-10] Manajemen PO + pembayaran (kredit/tunai), sinkron Akunting & Stok.
 * Saat PO diterima → stok bertambah + jurnal (Persediaan debit / Kas atau Utang kredit).
 * Pembayaran PO kredit → utang menurun + jurnal (Utang debit / Kas kredit).
 */
class PurchaseOrderService
{
    public function __construct(
        protected JurnalService $jurnalService,
        protected ProdukService $produkService
    ) {}

    public function terimaBarang(PurchaseOrder $po, ?int $userId): PurchaseOrder
    {
        if (!in_array($po->status, ['draft', 'dikirim'], true)) {
            throw new \Exception('PO ini tidak bisa diterima dalam status saat ini');
        }

        return DB::transaction(function () use ($po, $userId) {
            $totalHpp = 0;

            foreach ($po->items as $item) {
                $harga = (float) $item->harga_beli;
                $qty = (int) $item->jumlah;

                $this->produkService->tambahStokPembelian(
                    $item->produk_id,
                    $item->sku_variant_id,
                    $po->gudang_tujuan_id,
                    $qty,
                    $harga,
                    "Terima PO {$po->no_po} item {$item->produk?->nama}",
                    $userId
                );

                $totalHpp += $harga * $qty;
            }

            // Jurnal
            $totalHpp = round($totalHpp, 2);
            $akunUtang = $po->metode_bayar === 'kredit' ? '210-01' : '110-01';
            $jurnalLines = [
                ['akun_kode' => '130-01', 'debit' => $totalHpp, 'kredit' => 0], // Persediaan
            ];

            if ($po->metode_bayar === 'kredit') {
                $jurnalLines[] = ['akun_kode' => '210-01', 'debit' => 0, 'kredit' => $totalHpp]; // Utang Usaha
            } else {
                $jurnalLines[] = ['akun_kode' => '110-01', 'debit' => 0, 'kredit' => $totalHpp]; // Kas
            }

            $this->jurnalService->post(
                $this->jurnalService->generateNoJurnal('beli', $po->gudangTujuan?->cabang_id),
                now(),
                'pembelian',
                $jurnalLines,
                "PO {$po->no_po} diterima ({$po->metode_bayar})",
                $po->gudangTujuan?->cabang_id,
                $userId
            );

            // Jika kredit → catat piutang/utang (AR/AP)
            if ($po->metode_bayar === 'kredit') {
                // Insert ke utang tabel (menggunakan tabel piutang sebagai meta — alternatif: buat tabel terpisah, tapi agar tidak menambah migrasi, pakai field referensi di piutang untuk utang juga)
                // Simplified: catat di total_dibayar = 0, status berubah setelah pembayaran
            }

            $po->update(['status' => 'diterima', 'total' => $totalHpp]);

            return $po;
        });
    }

    public function bayarPO(PurchaseOrder $po, float $jumlah, ?int $userId): PurchaseOrder
    {
        if ($po->status !== 'diterima' || $po->sisa <= 0.01) {
            throw new \Exception('PO ini tidak memiliki sisa utang untuk dibayar');
        }

        if ($jumlah > $po->sisa) {
            throw new \Exception('Pembayaran melebihi sisa utang PO');
        }

        return DB::transaction(function () use ($po, $jumlah, $userId) {
            PembayaranSupplier::create([
                'po_id' => $po->id,
                'jumlah' => $jumlah,
                'dibayar_at' => now(),
                'user_id' => $userId,
                'keterangan' => "Pembayaran PO {$po->no_po}",
            ]);

            $po->update(['total_dibayar' => $po->total_dibayar + $jumlah]);

            // Jurnal: Utang Usaha (210-01) debit / Kas (110-01) kredit
            $this->jurnalService->post(
                $this->jurnalService->generateNoJurnal('bayar', $po->gudangTujuan?->cabang_id),
                now(),
                'manual',
                [
                    ['akun_kode' => '210-01', 'debit' => $jumlah, 'kredit' => 0],
                    ['akun_kode' => '110-01', 'debit' => 0, 'kredit' => $jumlah],
                ],
                "Bayar PO {$po->no_po} — Rp " . number_format($jumlah, 0, ',', '.'),
                $po->gudangTujuan?->cabang_id,
                $userId
            );

            return $po;
        });
    }
}