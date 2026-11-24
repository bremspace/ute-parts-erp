<?php

namespace App\Modules\Marketplace\Support;

use App\Modules\Pos\Models\TransaksiItem;

/**
 * Data tampilan harga yang konsisten untuk cart, checkout, dan rincian pesanan.
 *
 * Prinsip tampilan: quantity utama SELALU base unit (pcs). Kemasan/box hanya
 * informasi tambahan, bukan label quantity utama.
 *
 * Aturan harga:
 * - Box penuh memakai harga box; sisa pcs memakai harga retail.
 * - Potongan kemasan = (harga retail × pcs) - total setelah harga box.
 */
final class HargaTampilan
{
    /**
     * @param  array<string, mixed>  $item
     * @return array<string, float|string|bool|int>
     */
    public static function dariCart(array $item): array
    {
        $faktor = max(1.0, (float) ($item['faktor_konversi'] ?? 1));
        $satuan = (string) ($item['satuan'] ?? 'pcs');
        $qtyPcs = max(0, (int) ($item['qty'] ?? 0));
        $qtySatuan = max(0, (int) ($item['qty_satuan'] ?? 0));
        $sisaPcs = max(0, (int) ($item['sisa_pcs'] ?? 0));
        $retail = (float) ($item['harga_retail'] ?? $item['harga'] ?? 0);
        $hargaEfektif = (float) ($item['harga'] ?? $retail);
        $hargaSatuan = (float) ($item['harga_satuan'] ?? ($hargaEfektif * $faktor));
        $isKemasan = $faktor > 1 && strcasecmp($satuan, 'pcs') !== 0;

        $asliPerPcs = round($retail, 2);
        $akhirPerPcs = round($hargaEfektif, 2);
        $potonganPerPcs = max(0, round($asliPerPcs - $akhirPerPcs, 2));

        $asliTotal = round($retail * $qtyPcs, 2);
        $akhirTotal = round($hargaEfektif * $qtyPcs, 2);
        $potonganTotal = max(0, round($asliTotal - $akhirTotal, 2));

        $boxFaktor = max(0, (int) ($item['box_faktor'] ?? 0));
        $boxHarga = (float) ($item['box_harga'] ?? 0);
        if ($boxFaktor <= 1 && $isKemasan) {
            $boxFaktor = (int) round($faktor);
            $boxHarga = $hargaSatuan;
        }
        $potonganBox = ($boxFaktor > 1 && $boxHarga > 0)
            ? max(0, round(($asliPerPcs * $boxFaktor) - $boxHarga, 2))
            : 0.0;
        $hargaPerBox = $isKemasan ? round($hargaSatuan, 2) : 0.0;

        return [
            'is_kemasan' => $isKemasan,
            'faktor' => $faktor,
            'satuan' => $satuan,
            'qty_pcs' => $qtyPcs,
            'qty_satuan' => $qtySatuan,
            'sisa_pcs' => $sisaPcs,
            'asli_per_pcs' => $asliPerPcs,
            'akhir_per_pcs' => $akhirPerPcs,
            'potongan_per_pcs' => $potonganPerPcs,
            'asli_per_satuan' => $isKemasan ? round($retail * $faktor, 2) : $asliPerPcs,
            'akhir_per_satuan' => $isKemasan ? $hargaPerBox : $akhirPerPcs,
            'potongan_per_satuan' => $isKemasan ? max(0, round(($retail * $faktor) - $hargaPerBox, 2)) : $potonganPerPcs,
            'harga_per_box' => $hargaPerBox,
            'box_faktor' => $boxFaktor,
            'box_harga' => round($boxHarga, 2),
            'box_harga_per_pcs' => $boxFaktor > 1 ? round($boxHarga / $boxFaktor, 2) : 0.0,
            'potongan_box' => $potonganBox,
            'asli_total' => $asliTotal,
            'akhir_total' => $akhirTotal,
            'potongan_total' => $potonganTotal,
            'label' => $potonganTotal > 0 ? 'Potongan Kemasan' : '',
        ];
    }

    /**
     * @return array<string, float|string|bool|int>
     */
    public static function dariOrderItem(TransaksiItem $item): array
    {
        $faktor = max(1.0, (float) ($item->konversi ?? 1));
        $satuan = (string) ($item->satuan ?? 'pcs');
        $jumlahSatuan = max(0, (int) ($item->jumlah ?? 0));
        $qtyPcs = max(0, (int) round($jumlahSatuan * $faktor));
        $retail = (float) ($item->harga_retail ?? $item->produk?->harga_jual_retail ?? $item->harga_satuan ?? 0);
        $hargaSatuan = (float) ($item->harga_satuan ?? 0);
        $subtotal = (float) ($item->subtotal ?? 0);
        $isKemasan = $faktor > 1 && strcasecmp($satuan, 'pcs') !== 0;

        $asliPerPcs = round($retail, 2);
        $akhirPerPcs = $qtyPcs > 0 ? round($subtotal / $qtyPcs, 2) : round($hargaSatuan / max(1, $faktor), 2);
        $potonganPerPcs = max(0, round($asliPerPcs - $akhirPerPcs, 2));

        $asliTotal = round($retail * $qtyPcs, 2);
        $akhirTotal = round($subtotal > 0 ? $subtotal : $hargaSatuan * $jumlahSatuan, 2);
        $potonganTotal = max(0, round($asliTotal - $akhirTotal, 2));

        return [
            'is_kemasan' => $isKemasan,
            'faktor' => $faktor,
            'satuan' => $satuan,
            'qty_pcs' => $qtyPcs,
            'qty_satuan' => $jumlahSatuan,
            'sisa_pcs' => 0,
            'asli_per_pcs' => $asliPerPcs,
            'akhir_per_pcs' => $akhirPerPcs,
            'potongan_per_pcs' => $potonganPerPcs,
            'asli_per_satuan' => $isKemasan ? round($retail * $faktor, 2) : $asliPerPcs,
            'akhir_per_satuan' => $isKemasan ? round($hargaSatuan, 2) : $akhirPerPcs,
            'potongan_per_satuan' => $isKemasan ? max(0, round(($retail * $faktor) - $hargaSatuan, 2)) : $potonganPerPcs,
            'harga_per_box' => $isKemasan ? round($hargaSatuan, 2) : 0.0,
            'asli_total' => $asliTotal,
            'akhir_total' => $akhirTotal,
            'potongan_total' => $potonganTotal,
            'label' => $potonganTotal > 0 ? 'Potongan Kemasan' : '',
        ];
    }
}
