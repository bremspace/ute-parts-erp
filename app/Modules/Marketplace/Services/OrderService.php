<?php

namespace App\Modules\Marketplace\Services;

use App\Modules\Akunting\Services\PajakService;
use App\Modules\Crm\Models\Pelanggan;
use App\Modules\Marketplace\Models\Pengiriman;
use App\Modules\Notifikasi\Services\NotificationService;
use App\Modules\Pos\Models\Transaksi;
use App\Modules\Pos\Models\TransaksiItem;
use App\Modules\Pos\Services\PricingService;
use App\Modules\Rbac\Models\Cabang;
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
        protected PajakService $pajakService,
        protected NotificationService $notifService
    ) {}

    public function buatOrder(
        Pelanggan $pelanggan,
        array $items,
        int $cabangId,
        string $metodeAmbil,
        ?string $catatan = null,
        array $shippingData = []
    ): Transaksi {
        return DB::transaction(function () use ($pelanggan, $items, $cabangId, $metodeAmbil, $catatan, $shippingData) {
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
                    'satuan' => $row['satuan'] ?? ($row['produk']->satuan ?: 'pcs'),
                    'konversi' => $row['konversi'] ?? 1,
                    'harga_satuan' => $row['harga_satuan'],
                    'diskon_nominal' => 0,
                    'subtotal' => $row['subtotal'],
                    'hpp' => $row['hpp'],
                ]);
            }

            // Buat record pengiriman jika metode ambil adalah pengiriman
            if ($metodeAmbil === 'pengiriman') {
                $cabang = Cabang::find($cabangId);
                Pengiriman::create([
                    'transaksi_id' => $transaksi->id,
                    'kurir' => $shippingData['kurir'] ?? 'Ekspedisi / Biteship',
                    'layanan' => $shippingData['layanan'] ?? 'Reguler',
                    'ongkir' => (float) ($shippingData['ongkir'] ?? 0),
                    'asal_cabang' => $cabang?->nama ?? 'Cabang Pusat',
                    'nama_penerima' => $shippingData['nama_penerima'] ?? $pelanggan->nama,
                    'alamat_tujuan' => $shippingData['alamat_tujuan'] ?? $pelanggan->alamat ?? '-',
                    'telepon_tujuan' => $shippingData['telepon_tujuan'] ?? $pelanggan->telepon ?? '-',
                    'status' => 'pending',
                ]);
            }

            // 1. Notifikasi ke Backoffice (kasir & admin-toko)
            $this->notifService->kirim(
                'inapp',
                null,
                "Pesanan Online Baru ({$noTransaksi})",
                "Pesanan baru dari {$pelanggan->nama} senilai Rp ".number_format($transaksi->total_akhir, 0, ',', '.')." [{$metodeAmbil}] menunggu pembayaran.",
                [
                    'transaksi_id' => $transaksi->id,
                    'cabang_id' => $cabangId,
                    'target_role' => 'kasir',
                    'sumber' => 'marketplace',
                    'kategori' => 'transaksi',
                    'action_url' => '/app/pos/online-orders',
                    'audience' => 'staf',
                ]
            );

            // 2. Notifikasi ke Konsumen (In-App)
            $this->notifService->kirim(
                'inapp',
                $pelanggan->email,
                'Pesanan Berhasil Didaftarkan',
                "Pesanan {$noTransaksi} telah terdaftar. Silakan selesaikan pembayaran agar barang segera disiapkan.",
                [
                    'transaksi_id' => $transaksi->id,
                    'pelanggan_id' => $pelanggan->id,
                    'kategori' => 'transaksi',
                    'sumber' => 'marketplace',
                    'action_url' => '/account',
                    'audience' => 'pelanggan',
                ]
            );

            // 3. Notifikasi via WhatsApp jika nomor telepon tersedia
            if (! empty($pelanggan->telepon)) {
                $this->notifService->kirim(
                    'wa',
                    $pelanggan->telepon,
                    'Invoice Pesanan Ute Parts',
                    "Halo {$pelanggan->nama}, pesanan {$noTransaksi} telah kami terima dengan total Rp ".number_format($transaksi->total_akhir, 0, ',', '.').'. Silakan selesaikan pembayaran untuk diproses toko.',
                    [
                        'transaksi_id' => $transaksi->id,
                        'pelanggan_id' => $pelanggan->id,
                        'audience' => 'pelanggan',
                    ]
                );
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
