<?php

namespace App\Modules\Marketplace\Services;

use App\Modules\Akunting\Services\PajakService;
use App\Modules\Crm\Models\Pelanggan;
use App\Modules\Crm\Services\PartaiPricingService;
use App\Modules\Marketplace\Models\Pengiriman;
use App\Modules\Notifikasi\Services\NotificationService;
use App\Modules\Pos\Models\Transaksi;
use App\Modules\Pos\Models\TransaksiItem;
use App\Modules\Pos\Services\PricingService;
use App\Modules\Pos\Services\TransaksiSequenceService;
use App\Modules\Rbac\Models\Cabang;
use App\Modules\Wms\Models\Gudang;
use App\Modules\Wms\Models\Produk;
use App\Modules\Wms\Models\SkuVariant;
use App\Modules\Wms\Services\StockReservationService;
use App\Support\NomorTeleponIndonesia;
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
            $noTransaksi = app(TransaksiSequenceService::class)->generateNoTransaksi($cabangId, 'MP');

            $subtotal = 0.0;
            $totalKomisiMitra = 0.0;
            $modeRewardMitra = 'diskon_langsung';
            $rows = [];

            foreach ($items as $item) {
                $produk = Produk::findOrFail($item['produk_id']);
                // Semantik input cart/API: 'jumlah' dalam BASE unit (pcs) —
                // CartService.qty & ShopPage.addToCart (rawQty x faktor) sudah
                // mengonversi qty satuan terpilih ke base sebelum checkout.
                $qtyDasar = (int) $item['jumlah'];
                $variant = isset($item['sku_variant_id']) ? SkuVariant::find($item['sku_variant_id']) : null;
                $hargaRetailSatuan = $variant && $variant->harga_jual_retail !== null
                    ? (float) $variant->harga_jual_retail
                    : (float) $produk->harga_jual_retail;

                // Checkout harus memakai stok cabang order, bukan agregat semua cabang.
                // Stok dihitung dengan mengunci baris stok, dikurangi pending transfer lock dan active online reservation.
                $reservationService = app(StockReservationService::class);
                $stokTersedia = $reservationService->getAvailableStockForOrder(
                    $cabangId,
                    $produk->id,
                    $variant?->id,
                    lock: true
                );

                // [C1] Cek stok SELALU base unit: stokTersedia vs qtyDasar
                // (= jumlah satuan x konversi), bukan qty satuan jual — oversell box.
                if ($qtyDasar <= 0 || $stokTersedia < $qtyDasar) {
                    throw new \Exception("Stok {$produk->nama} tidak mencukupi di cabang yang dipilih (tersedia: {$stokTersedia})");
                }

                // [SECURITY] Harga dihitung server-side dari satuan terpilih (pcs/box).
                // ABAIKAN $item['harga'] dari client — itu vektor manipulasi harga checkout.
                // Nominal dijamin identik dengan cart karena memakai resolver yang sama.
                $satuanInfo = $this->pricingService->resolveHargaSatuan($produk, $pelanggan, $variant, $item['satuan'] ?? null);
                $pricing = $satuanInfo['pricing'];
                $harga = (float) $satuanInfo['harga']; // per BASE unit
                $diskonPerUnit = (float) ($pricing['diskon_nominal'] ?? 0);
                $potensiKomisiPerUnit = (float) ($pricing['potensi_komisi'] ?? 0);
                $modeRewardMitra = $pricing['mode_reward'] ?? 'diskon_langsung';

                // [C1] Kontrak satuan POS-native (PosCheckoutService/TransaksiVoidService):
                // TransaksiItem.jumlah = qty SATUAN jual (box count), konversi = faktor
                // ke base, harga_satuan & hpp = per SATUAN. qtyDasar = jumlah x konversi;
                // processor (Duitku/pickup) memotong stok & menjurnal HPP dari qtyDasar.
                $konversi = (float) $satuanInfo['faktor_konversi'];
                if ($konversi <= 0) {
                    $konversi = 1.0;
                }
                $satuanLabel = $satuanInfo['satuan'];
                $baseSatuan = $produk->satuan ?: 'pcs';
                $isSatuanKonversi = $konversi > 1 && strcasecmp($satuanLabel, $baseSatuan) !== 0;
                $hppBase = (float) $produk->harga_beli;
                $catatan = $item['catatan'] ?? null;

                $diskonItem = round($diskonPerUnit * $qtyDasar, 2);
                $komisiItem = round($potensiKomisiPerUnit * $qtyDasar, 2);
                $totalKomisiMitra += $komisiItem;

                if (! $isSatuanKonversi) {
                    // Satuan dasar: 1 baris, konversi 1, hpp per base = per satuan.
                    $lineSubtotal = round($harga * $qtyDasar, 2);
                    $subtotal += $lineSubtotal;
                    $rows[] = [
                        'produk' => $produk,
                        'sku_variant_id' => $variant?->id,
                        'qty' => $qtyDasar, // base qty utk tier partai (semantik pcs)
                        'jumlah' => $qtyDasar,
                        'satuan' => $satuanLabel,
                        'konversi' => 1.0,
                        'harga_satuan' => $harga,
                        'diskon_nominal' => $diskonItem,
                        'potensi_komisi' => $komisiItem,
                        'harga_retail' => $hargaRetailSatuan,
                        'hpp' => $hppBase,
                        'subtotal' => $lineSubtotal,
                        'catatan' => $catatan,
                    ];

                    continue;
                }

                $faktorInt = max(1, (int) round($konversi));
                $boxCount = intdiv($qtyDasar, $faktorInt);
                $rem = $qtyDasar - ($boxCount * $faktorInt);
                // Harga per satuan terpilih = harga base x faktor (= harga_jual
                // master satuan bila ada, karena resolver menurunkan harga base
                // dari harga_jual/faktor).
                $hargaPerSatuan = round($harga * $konversi, 2);
                $sisaPcs = (int) ($item['sisa_pcs'] ?? 0);

                if ($sisaPcs > 0 && $boxCount > 0) {
                    // Campuran box + pcs (cart updateQty): box dihargai harga satuan
                    // master, sisa pcs dihargai retail. Sisa pcs diturunkan dari
                    // qtyDasar agar jumlah baris x konversi selalu = qtyDasar.
                    $pcsPrice = (float) $produk->harga_jual_retail;
                    $lineSubtotal = round(($boxCount * $hargaPerSatuan) + ($rem * $pcsPrice), 2);
                    $subtotal += $lineSubtotal;
                    $boxSubtotal = round($boxCount * $hargaPerSatuan, 2);
                    $boxQtyDasar = $boxCount * $faktorInt;
                    $boxDiskon = round($diskonPerUnit * $boxQtyDasar, 2);
                    $boxKomisi = round($potensiKomisiPerUnit * $boxQtyDasar, 2);
                    $rows[] = [
                        'produk' => $produk,
                        'sku_variant_id' => $variant?->id,
                        'qty' => $boxQtyDasar,
                        'jumlah' => $boxCount,
                        'satuan' => $satuanLabel,
                        'konversi' => $konversi,
                        'harga_satuan' => $hargaPerSatuan,
                        'diskon_nominal' => 0.0,
                        'potensi_komisi' => $boxKomisi,
                        'harga_retail' => $hargaRetailSatuan,
                        'hpp' => $hppBase * $konversi,
                        'subtotal' => $boxSubtotal,
                        'catatan' => $catatan,
                    ];
                    if ($rem > 0) {
                        $rows[] = [
                            'produk' => $produk,
                            'sku_variant_id' => $variant?->id,
                            'qty' => $rem,
                            'jumlah' => $rem,
                            'satuan' => $baseSatuan,
                            'konversi' => 1.0,
                            'harga_satuan' => $pcsPrice,
                            'diskon_nominal' => round($diskonPerUnit * $rem, 2),
                            'potensi_komisi' => round($komisiItem - $boxKomisi, 2),
                            'harga_retail' => $hargaRetailSatuan,
                            'hpp' => $hppBase,
                            'subtotal' => round($lineSubtotal - $boxSubtotal, 2),
                            'catatan' => $catatan,
                        ];
                    }

                    continue;
                }

                // Satuan konversi tanpa sisa eksplisit: nominal tetap harga base x
                // qtyDasar (identik dgn perhitungan lama & tampilan cart).
                $lineSubtotal = round($harga * $qtyDasar, 2);
                $subtotal += $lineSubtotal;

                if ($rem === 0 && $boxCount > 0) {
                    // Box murni: 1 baris, jumlah = box count, hpp per box.
                    $rows[] = [
                        'produk' => $produk,
                        'sku_variant_id' => $variant?->id,
                        'qty' => $qtyDasar,
                        'jumlah' => $boxCount,
                        'satuan' => $satuanLabel,
                        'konversi' => $konversi,
                        'harga_satuan' => $hargaPerSatuan,
                        'diskon_nominal' => 0.0,
                        'potensi_komisi' => $komisiItem,
                        'harga_retail' => $hargaRetailSatuan,
                        'hpp' => $hppBase * $konversi,
                        'subtotal' => $lineSubtotal,
                        'catatan' => $catatan,
                    ];

                    continue;
                }

                // Tidak habis dibagi (cart lama tanpa sisa_pcs / API): pecah menjadi
                // baris box + pcs dengan harga per-base yang sama agar nominal dan
                // qtyDasar identik; jumlah per baris tetap integer (kontrak model).
                $boxQtyDasar = $boxCount * $faktorInt;
                $boxSubtotal = round($harga * $boxQtyDasar, 2);
                $boxDiskon = round($diskonPerUnit * $boxQtyDasar, 2);
                $boxKomisi = round($potensiKomisiPerUnit * $boxQtyDasar, 2);
                if ($boxCount > 0) {
                    $rows[] = [
                        'produk' => $produk,
                        'sku_variant_id' => $variant?->id,
                        'qty' => $boxQtyDasar,
                        'jumlah' => $boxCount,
                        'satuan' => $satuanLabel,
                        'konversi' => $konversi,
                        'harga_satuan' => $hargaPerSatuan,
                        'diskon_nominal' => 0.0,
                        'potensi_komisi' => $boxKomisi,
                        'harga_retail' => $hargaRetailSatuan,
                        'hpp' => $hppBase * $konversi,
                        'subtotal' => $boxSubtotal,
                        'catatan' => $catatan,
                    ];
                }
                $rows[] = [
                    'produk' => $produk,
                    'sku_variant_id' => $variant?->id,
                    'qty' => $rem,
                    'jumlah' => $rem,
                    'satuan' => $baseSatuan,
                    'konversi' => 1.0,
                    'harga_satuan' => round($harga, 2),
                    'diskon_nominal' => round($diskonPerUnit * $rem, 2),
                    'potensi_komisi' => round($komisiItem - $boxKomisi, 2),
                    'harga_retail' => $hargaRetailSatuan,
                    'hpp' => $hppBase,
                    'subtotal' => round($lineSubtotal - $boxSubtotal, 2),
                    'catatan' => $catatan,
                ];
            }

            // Standarisasi diskon partai global untuk marketplace order
            $partaiService = app(PartaiPricingService::class);
            $partaiInfo = $partaiService->hitung($rows, $pelanggan);
            $diskonPartai = (float) ($partaiInfo['diskon_nominal'] ?? 0.0);

            // [B-14] PPN per cabang — definisi identik POS: DPP = subtotal - diskon,
            // PPN = DPP × persen, total_akhir = DPP + PPN + ongkir.
            $diskon = 0.0;
            $diskonHeaderTotal = $diskon + $diskonPartai;
            $pajak = $this->pajakService->hitungPenjualan($cabangId, $subtotal, $diskonHeaderTotal);
            $ongkir = ($metodeAmbil === 'pengiriman') ? (float) ($shippingData['ongkir'] ?? 0) : 0.0;
            $totalAkhirDenganOngkir = round((float) $pajak['total_akhir'] + $ongkir, 2);

            $gudangId = Gudang::where('cabang_id', $cabangId)->where('is_active', true)->value('id')
                ?? Gudang::where('cabang_id', $cabangId)->value('id')
                ?? Gudang::value('id');

            $transaksi = Transaksi::create([
                'no_transaksi' => $noTransaksi,
                'cabang_id' => $cabangId,
                'gudang_id' => $gudangId,
                'kasir_id' => null, // bukan staf kasir
                'pelanggan_id' => $pelanggan->id,
                'sumber' => 'marketplace',
                'subtotal' => $subtotal,
                'diskon_persen' => 0,
                'diskon_nominal' => $diskon,
                'diskon_partai' => $diskonPartai,
                'mode_reward_mitra' => $modeRewardMitra,
                'komisi_mitra_nominal' => $totalKomisiMitra,
                'dpp' => $pajak['dpp'],
                'pajak_nominal' => $pajak['ppn_nominal'],
                'ppn_nominal' => $pajak['ppn_nominal'],
                'total_akhir' => $totalAkhirDenganOngkir,
                'metode_bayar' => 'menunggu', // payment Duitku Hari 6
                'jumlah_bayar' => 0,
                'kembalian' => 0,
                'status' => 'menunggu_pembayaran',
                'expires_at' => now()->addMinutes((int) config('marketplace.reservation_minutes', 60)),
                'catatan' => "Order marketplace [ambil: {$metodeAmbil}] ".($catatan ?? ''),
            ]);

            foreach ($rows as $row) {
                // [C1] jumlah = qty SATUAN jual; konversi = faktor ke base;
                // harga_satuan & hpp per SATUAN (kontrak POS-native). Processor
                // menghitung qtyDasar = jumlah x konversi utk stok & HPP.
                TransaksiItem::create([
                    'transaksi_id' => $transaksi->id,
                    'produk_id' => $row['produk']->id,
                    'sku_variant_id' => $row['sku_variant_id'],
                    'jumlah' => $row['jumlah'],
                    'satuan' => $row['satuan'],
                    'konversi' => $row['konversi'],
                    'harga_satuan' => $row['harga_satuan'],
                    'harga_retail' => $row['harga_retail'],
                    'diskon_nominal' => $row['diskon_nominal'],
                    'subtotal' => $row['subtotal'],
                    'hpp' => $row['hpp'],
                    'catatan' => $row['catatan'] ?? null,
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
                    'telepon_tujuan' => NomorTeleponIndonesia::standar(
                        $shippingData['telepon_tujuan'] ?? $pelanggan->telepon ?? ''
                    ) ?: '-',
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
                    'Invoice Pesanan '.config('app.name', 'Bremspace'),
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
    public function previewPajak(int $cabangId, float $subtotal, float $diskon = 0.0): array
    {
        return $this->pajakService->hitungPenjualan($cabangId, $subtotal, $diskon);
    }
}
