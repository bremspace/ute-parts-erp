<?php

namespace App\Modules\Marketplace\Services;

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
 */
class OrderService
{
    public function __construct(
        protected PricingService $pricingService
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

            $transaksi = Transaksi::create([
                'no_transaksi' => $noTransaksi,
                'cabang_id' => $cabangId,
                'kasir_id' => null, // bukan staf kasir
                'pelanggan_id' => $pelanggan->id,
                'sumber' => 'marketplace',
                'subtotal' => $subtotal,
                'diskon_persen' => 0,
                'diskon_nominal' => 0,
                'pajak_nominal' => 0,
                'total_akhir' => $subtotal,
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
}
