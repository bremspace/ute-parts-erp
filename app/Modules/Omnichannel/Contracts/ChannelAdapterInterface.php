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
     * Dapatkan URL otorisasi OAuth untuk mengarahkan pengguna/seller.
     */
    public function getAuthUrl(array $cred, string $redirectUrl): string;

    /**
     * Tukar authorization code menjadi access token & refresh token.
     *
     * @return array ['access_token' => string, 'refresh_token' => string, 'token_expires_at' => int, 'refresh_token_expires_at' => int, 'shop_id' => int|string]
     */
    public function handleAuthCallback(array $cred, string $code, string|int $shopId): array;

    /**
     * Refresh access token sebelum kedaluwarsa.
     *
     * @return array ['access_token' => string, 'refresh_token' => string, 'token_expires_at' => int, 'refresh_token_expires_at' => int]
     */
    public function refreshAccessToken(array $cred): array;

    /**
     * Tes koneksi / get shop info. Throws exception bila token bermasalah.
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
     * @param  array  $items  [['item_id' => int|string, 'model_id' => int|string|null, 'stock' => int], ...]
     */
    public function pushStock(array $kredensial, array $items): bool;

    /**
     * Push harga produk terpilih ke channel.
     *
     * @param  array  $items  [['item_id' => int|string, 'model_id' => int|string|null, 'price' => float], ...]
     */
    public function pushPrice(array $kredensial, array $items): bool;

    /**
     * Ambil daftar produk terdaftar di channel.
     *
     * @return array [['item_id' => ..., 'sku' => ..., 'nama' => ..., 'stok' => int, 'harga' => float, 'models' => [...]], ...]
     */
    public function fetchProducts(array $kredensial): array;

    /**
     * Petakan payload produk channel → data lokal (untuk import produk dari channel).
     */
    public function mapProduct(array $channelProduct): array;

    /**
     * Verifikasi signature webhook yang dikirimkan oleh marketplace.
     */
    public function verifyWebhookSignature(string $url, string $rawBody, string $signature, string $partnerKey): bool;
}
