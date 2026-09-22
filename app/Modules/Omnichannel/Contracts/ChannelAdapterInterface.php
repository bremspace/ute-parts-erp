<?php

namespace App\Modules\Omnichannel\Contracts;

/**
 * Kontrak adapter channel marketplace (PRD §4.10).
 * Tambah channel baru = implement interface ini, tanpa menyentuh modul inti.
 */
interface ChannelAdapterInterface
{
    /**
     * Nama platform (shopee, tokopedia, blibli, ...).
     */
    public function platform(): string;

    /**
     * Tes koneksi / refresh token. Throws exception bila token bermasalah.
     */
    public function testConnection(array $kredensial): bool;

    /**
     * Tarik order terbaru dari channel.
     *
     * @return array daftar order mentah (dari channel)
     */
    public function pullOrders(array $kredensial): array;

    /**
     * Push stok produk terpilih ke channel.
     *
     * @param  array  $items  [['channel_item_id' => ..., 'stok' => int], ...]
     */
    public function pushStock(array $kredensial, array $items): bool;

    /**
     * Push harga produk terpilih ke channel.
     *
     * @param  array  $items  [['channel_item_id' => ..., 'harga' => float], ...]
     */
    public function pushPrice(array $kredensial, array $items): bool;

    /**
     * Ambil daftar produk terdaftar di channel.
     *
     * @return array [['item_id' => ..., 'sku' => ..., 'nama' => ..., 'stok' => int, 'harga' => float], ...]
     */
    public function fetchProducts(array $kredensial): array;

    /**
     * Petakan payload produk channel → data lokal (untuk import produk dari channel).
     */
    public function mapProduct(array $channelProduct): array;
}
