<?php

namespace App\Modules\Wms\Services;

use App\Modules\Akunting\Models\Utang;
use App\Modules\Akunting\Services\JurnalService;
use App\Modules\Wms\Models\PembayaranSupplier;
use App\Modules\Wms\Models\PurchaseOrder;
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
        if (! in_array($po->status, ['draft', 'dikirim'], true)) {
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
                    $userId,
                    postJurnal: false, // jurnal agregat diposting di bawah (hindari dobel posting)
                    smlSumber: 'po:receive', // [T-40] SOT: mutasi masuk via penerimaan PO
                    smlReferensiTipe: PurchaseOrder::class,
                    smlReferensiId: $po->id
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

            // Jika kredit → catat Utang (AP) modul Akunting (T-10 point 3)
            if ($po->metode_bayar === 'kredit') {
                $existing = Utang::where('referensi_tipe', PurchaseOrder::class)
                    ->where('referensi_id', $po->id)
                    ->first();

                if (! $existing) {
                    $count = Utang::whereDate('created_at', now()->toDateString())->count() + 1;
                    Utang::create([
                        'no_utang' => sprintf('UTG-%s-%04d', now()->format('Ymd'), $count),
                        'referensi_tipe' => PurchaseOrder::class,
                        'referensi_id' => $po->id,
                        'kreditor_nama' => $po->supplier?->nama,
                        'jumlah' => $totalHpp,
                        'jumlah_dibayar' => 0,
                        'jatuh_tempo' => $po->jatuh_tempo,
                        'status' => 'belum_lunas',
                        'keterangan' => "Utang pembelian PO {$po->no_po}",
                    ]);
                }
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

            // [T-10] Sinkron record Utang (AP): jumlah_dibayar + status
            $utang = Utang::where('referensi_tipe', PurchaseOrder::class)
                ->where('referensi_id', $po->id)
                ->first();
            if ($utang) {
                $baruDibayar = (float) $utang->jumlah_dibayar + $jumlah;
                $utang->update([
                    'jumlah_dibayar' => $baruDibayar,
                    'status' => $baruDibayar >= (float) $utang->jumlah - 0.01 ? 'lunas' : 'sebagian',
                ]);
            }

            // Jurnal: Utang Usaha (210-01) debit / Kas (110-01) kredit
            $this->jurnalService->post(
                $this->jurnalService->generateNoJurnal('bayar', $po->gudangTujuan?->cabang_id),
                now(),
                'manual',
                [
                    ['akun_kode' => '210-01', 'debit' => $jumlah, 'kredit' => 0],
                    ['akun_kode' => '110-01', 'debit' => 0, 'kredit' => $jumlah],
                ],
                "Bayar PO {$po->no_po} — Rp ".number_format($jumlah, 0, ',', '.'),
                $po->gudangTujuan?->cabang_id,
                $userId
            );

            return $po;
        });
    }
}
