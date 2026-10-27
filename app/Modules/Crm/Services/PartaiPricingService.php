<?php

namespace App\Modules\Crm\Services;

use App\Modules\Crm\Models\Pelanggan;

/**
 * Service Standarisasi Global Pembelian Partai (Grosir / Volume Discount).
 * Digunakan secara seragam di:
 * 1. POS Kasir (Backoffice)
 * 2. Marketplace Cart (Web Konsumen)
 * 3. Marketplace Checkout & OrderService (Web Konsumen & API)
 */
class PartaiPricingService
{
    public function __construct(
        protected KonfigurasiService $config
    ) {}

    /**
     * Hitung diskon partai berdasarkan daftar item keranjang & data pelanggan.
     *
     * @param  array<int|string, array<string, mixed>>  $items
     * @return array{
     *     aktif: bool,
     *     persen: float,
     *     diskon_nominal: float,
     *     alasan: string,
     *     min_qty: int,
     *     min_nominal: float,
     *     total_qty: int,
     *     subtotal: float
     * }
     */
    public function hitung(array $items, ?Pelanggan $pelanggan = null, string $modeReward = 'diskon_langsung'): array
    {
        $minQty = $this->config->hargaPartaiMinQty();
        $minNominal = $this->config->hargaPartaiMinNominal();

        $default = [
            'aktif' => false,
            'persen' => 0.0,
            'diskon_nominal' => 0.0,
            'alasan' => '',
            'min_qty' => $minQty,
            'min_nominal' => $minNominal,
            'total_qty' => 0,
            'subtotal' => 0.0,
        ];

        if (! $this->config->hargaPartaiAktif()) {
            return $default;
        }

        // Mode komisi akumulasi pada reseller/agen tidak menerapkan diskon langsung
        if ($modeReward === 'komisi_akumulasi') {
            return $default;
        }

        $berlakuUntuk = $this->config->hargaPartaiBerlakuUntuk();
        if (in_array($berlakuUntuk, ['member', 'member_saja'], true)) {
            // Khusus pelanggan terdaftar (member)
            if (! $pelanggan) {
                return $default;
            }
        } elseif (in_array($berlakuUntuk, ['umum', 'umum_saja'], true)) {
            // Khusus pembeli umum / non-member (tanpa akun/kontak pelanggan)
            if ($pelanggan) {
                return $default;
            }
        }
        // Opsi 'semua' / 'keduanya' berlaku untuk umum maupun member.

        $totalQty = 0;
        $subtotal = 0.0;

        foreach ($items as $item) {
            $qty = (int) ($item['qty'] ?? $item['jumlah'] ?? 1);
            $harga = (float) ($item['harga'] ?? $item['harga_satuan'] ?? 0);
            $lineSubtotal = isset($item['subtotal']) && (float) $item['subtotal'] > 0
                ? (float) $item['subtotal']
                : ($harga * $qty);

            $totalQty += $qty;
            $subtotal += $lineSubtotal;
        }

        if ($totalQty <= 0 || $subtotal <= 0) {
            return $default;
        }

        $diskonQtyPersen = $this->config->hargaPartaiQtyDiskonPersen();
        $diskonNominalPersen = $this->config->hargaPartaiNominalDiskonPersen();

        $persenTerpilih = 0.0;
        $alasan = '';

        if ($minQty > 0 && $diskonQtyPersen > 0 && $totalQty >= $minQty) {
            $persenTerpilih = $diskonQtyPersen;
            $alasan = "Harga Partai (Qty >= {$minQty} pcs)";
        }

        if ($minNominal > 0 && $diskonNominalPersen > 0 && $subtotal >= $minNominal) {
            if ($diskonNominalPersen > $persenTerpilih) {
                $persenTerpilih = $diskonNominalPersen;
                $alasan = 'Harga Partai (Belanja >= Rp '.number_format($minNominal, 0, ',', '.').')';
            } elseif ($persenTerpilih > 0) {
                $alasan .= ' + Belanja Besar';
            }
        }

        if ($persenTerpilih > 0) {
            $diskonNominal = round(($subtotal * $persenTerpilih) / 100, 2);

            return [
                'aktif' => true,
                'persen' => $persenTerpilih,
                'diskon_nominal' => $diskonNominal,
                'alasan' => $alasan." ({$persenTerpilih}%)",
                'min_qty' => $minQty,
                'min_nominal' => $minNominal,
                'total_qty' => $totalQty,
                'subtotal' => $subtotal,
            ];
        }

        return [
            'aktif' => false,
            'persen' => 0.0,
            'diskon_nominal' => 0.0,
            'alasan' => '',
            'min_qty' => $minQty,
            'min_nominal' => $minNominal,
            'total_qty' => $totalQty,
            'subtotal' => $subtotal,
        ];
    }
}
