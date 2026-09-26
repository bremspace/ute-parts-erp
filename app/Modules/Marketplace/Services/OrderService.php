<?php

namespace App\Modules\Marketplace\Services;

use App\Modules\Akunting\Services\PajakService;
use App\Modules\Crm\Models\Pelanggan;
use App\Modules\Pos\Models\Transaksi;
use App\Modules\Pos\Models\TransaksiItem;
use App\Modules\Pos\Services\PricingService;
use App\Modules\Wms\Models\Produk;
use App\Modules\Wms\Models\SkuVariant;
use Illuminate\Support\Facades\DB;

/**
 * OrderService — checkout marketplace.
 * Alur PRD §4.7 (tanpa payment dulu, payment Duitku di Hari 6):
 * order dibuat status 'menunggu_pembayaran', stok TIDAK dikurangi sampai lunas.
 *
 * [B-14] PPN: definisi total & pemisahan DPP/PPN SAMA dengan POS (lihat
 * `PajakService::hitungPenjualan()`): DPP = subtotal - diskon, PPN = DPP × %,
 * `total_akhir` = DPP + PPN. Nominal inilah yang ditagihkan ke pelanggan
 * (relevan dengan Duitku) dan yang jadi dasar jurnal di webhook.
 */
class OrderService
{
    public function __construct(
        protected PricingService $pricingService,
        protected PajakService $pajakService
    ) {}

    public function buatOrder(
        Pelanggan $pelanggan,
        array $items,
        int $cabangId,
        string $metodeAmbil,
        ?string $catatan = null
    ): Transaksi {
        return DB::transaction(function () use ($pelanggan, $items, $cabangId, $metodeAmbil, $catatan) {
            $today = now()->format('Ymd');
            $count = Transaksi::whereDate('created_at', now()->toDateString())
                ->where('sumber', 'marketplace')
                ->count() + 1;
            $noTransaksi = sprintf('MP-%s-%04d', $today, $count);

            $subtotal = 0.0;
            $rows = [];

            foreach ($items as $item) {
                $produk = Produk::findOrFail($item['produk_id']);
                $qty = (int) $item['jumlah'];
                $variant = isset($item['sku_variant_id']) ? SkuVariant::find($item['sku_variant_id']) : null;

                $pricing = $this->pricingService->resolve($produk, $pelanggan, $variant);
                $harga = $pricing['harga'];
                $lineSubtotal = round($harga * $qty, 2);
                $subtotal += $lineSubtotal;

                $rows[] = [
                    'produk' => $produk,
                    'sku_variant_id' => $variant?->id,
                    'jumlah' => $qty,
                    'harga_satuan' => $harga,
                    'hpp' => (float) $produk->harga_beli,
                    'subtotal' => $lineSubtotal,
                ];
            }

            // [B-14] PPN per cabang — definisi identik POS: DPP = subtotal - diskon,
            // PPN = DPP × persen, total_akhir = DPP + PPN. Nonaktif → PPN 0 dan
            // total_akhir = subtotal (perilaku lama, tidak berubah).
            $diskon = 0.0; // marketplace belum mendukung diskon header
            $pajak = $this->pajakService->hitungPenjualan($cabangId, $subtotal, $diskon);

            $transaksi = Transaksi::create([
                'no_transaksi' => $noTransaksi,
                'cabang_id' => $cabangId,
                'kasir_id' => null, // bukan staf kasir
                'pelanggan_id' => $pelanggan->id,
                'sumber' => 'marketplace',
                'subtotal' => $subtotal,
                'diskon_persen' => 0,
                'diskon_nominal' => $diskon,
                'dpp' => $pajak['dpp'],
                'pajak_nominal' => $pajak['ppn_nominal'],
                'ppn_nominal' => $pajak['ppn_nominal'],
                'total_akhir' => $pajak['total_akhir'],
                'metode_bayar' => 'menunggu', // payment Duitku Hari 6
                'jumlah_bayar' => 0,
                'kembalian' => 0,
                'status' => 'menunggu_pembayaran',
                'catatan' => "Order marketplace [ambil: {$metodeAmbil}] ".($catatan ?? ''),
            ]);

            foreach ($rows as $row) {
                TransaksiItem::create([
                    'transaksi_id' => $transaksi->id,
                    'produk_id' => $row['produk']->id,
                    'sku_variant_id' => $row['sku_variant_id'],
                    'jumlah' => $row['jumlah'],
                    'harga_satuan' => $row['harga_satuan'],
                    'diskon_nominal' => 0,
                    'subtotal' => $row['subtotal'],
                    'hpp' => $row['hpp'],
                ]);
            }

            return $transaksi;
        });
    }

    /**
     * [B-14] Pratinjau DPP/PPN/total utk halaman cart & checkout (belum ada order).
     * Memakai helper yang SAMA dgn `buatOrder()` supaya nominal yang ditampilkan
     * tidak pernah berbeda dari nominal yang benar-benar ditagihkan.
     *
     * @return array{dpp:float, ppn_nominal:float, ppn_percent:float, enabled:bool, total_akhir:float}
     */
    public function previewPajak(int $cabangId, float $subtotal): array
    {
        return $this->pajakService->hitungPenjualan($cabangId, $subtotal);
    }
}
