<?php

namespace App\Modules\Omnichannel\Adapters;

use App\Modules\Omnichannel\Contracts\ChannelAdapterInterface;
use Illuminate\Support\Facades\Http;

/**
 * Shopee Open API Adapter — channel MVP (PRD §4.10).
 * Endpoint: api.shopee.co.id/api/v2/...
 * Konfigurasi di .env: SHOPEE_PARTNER_ID, SHOPEE_PARTNER_KEY, SHOPEE_SHOP_ID
 *
 * Signature request: sha256(partner_key + timestamp + path + body) → Authorization: {partner_id};{timestamp};{sign}
 */
class ShopeeAdapter implements ChannelAdapterInterface
{
    private string $baseUrl = 'https://partner.shopeemobile.com/api/v2';

    public function platform(): string
    {
        return 'shopee';
    }

    private function credentials(): array
    {
        return [
            'partner_id' => (int) env('SHOPEE_PARTNER_ID', 0),
            'partner_key' => (string) env('SHOPEE_PARTNER_KEY', ''),
            'shop_id' => (int) env('SHOPEE_SHOP_ID', 0),
        ];
    }

    private function signedHeaders(array $cred, string $path, string $body = ''): array
    {
        $timestamp = time();
        $baseString = (string) $cred['partner_id'] . $timestamp . $path . $body;
        $sign = hash_hmac('sha256', $baseString, $cred['partner_key']);

        return [
            'Content-Type' => 'application/json',
            'Authorization' => sprintf('%s;%s;%s', $cred['partner_id'], $timestamp, $sign),
        ];
    }

    public function testConnection(array $kredensial): bool
    {
        $cred = array_merge($this->credentials(), $kredensial);
        $path = '/shop/get_shop_detail';

        $response = Http::withHeaders($this->signedHeaders($cred, $path))
            ->timeout(15)
            ->post($this->baseUrl . $path, [
                'partner_id' => $cred['partner_id'],
                'shopid' => $cred['shop_id'],
                'timestamp' => time(),
            ]);

        $data = $response->json();

        if (($data['error'] ?? '') !== '' && ($data['error'] ?? null) !== null && ($data['error'] ?? null) !== '0') {
            throw new \Exception('Shopee: ' . ($data['message'] ?? 'Koneksi gagal'));
        }

        return true;
    }

    public function pullOrders(array $kredensial): array
    {
        $cred = array_merge($this->credentials(), $kredensial);
        $path = '/order/get_order_list';

        $response = Http::withHeaders($this->signedHeaders($cred, $path))
            ->timeout(20)
            ->post($this->baseUrl . $path, [
                'partner_id' => $cred['partner_id'],
                'shopid' => $cred['shop_id'],
                'timestamp' => time(),
                'time_range_field' => 'create_time',
                'time_from' => now()->subDay()->timestamp,
                'time_to' => now()->timestamp,
                'page_size' => 20,
                'response_optional_fields' => 'order_status,total_amount,item_list,buyer_user_name',
            ]);

        $data = $response->json();

        if (isset($data['error']) && $data['error'] !== '') {
            throw new \Exception('Shopee: ' . ($data['message'] ?? 'Gagal tarik order'));
        }

        return $data['response']['order_list'] ?? [];
    }

    public function pushStock(array $kredensial, array $items): bool
    {
        $cred = array_merge($this->credentials(), $kredensial);
        $path = '/product/update_stock';

        $response = Http::withHeaders($this->signedHeaders($cred, $path, json_encode(['_item_list' => $items])))
            ->timeout(20)
            ->post($this->baseUrl . $path, [
                'partner_id' => $cred['partner_id'],
                'shopid' => $cred['shop_id'],
                'timestamp' => time(),
                '_item_list' => $items,
            ]);

        $data = $response->json();

        return (($data['error'] ?? '') === '' || ($data['error'] ?? null) === '0');
    }

    public function pushPrice(array $kredensial, array $items): bool
    {
        $cred = array_merge($this->credentials(), $kredensial);
        $path = '/product/update_price';

        $response = Http::withHeaders($this->signedHeaders($cred, $path))
            ->timeout(20)
            ->post($this->baseUrl . $path, [
                'partner_id' => $cred['partner_id'],
                'shopid' => $cred['shop_id'],
                'timestamp' => time(),
                'item_id' => $items[0]['item_id'] ?? null,
                'price' => $items[0]['price'] ?? 0,
            ]);

        $data = $response->json();

        return (($data['error'] ?? '') === '' || ($data['error'] ?? null) === '0');
    }

    public function fetchProducts(array $kredensial): array
    {
        $cred = array_merge($this->credentials(), $kredensial);
        $path = '/product/get_item_list';

        $response = Http::withHeaders($this->signedHeaders($cred, $path))
            ->timeout(20)
            ->post($this->baseUrl . $path, [
                'partner_id' => $cred['partner_id'],
                'shopid' => $cred['shop_id'],
                'timestamp' => time(),
                'pagination_offset' => 0,
                'pagination_entries_per_page' => 50,
            ]);

        $data = $response->json();

        if (isset($data['error']) && $data['error'] !== '') {
            throw new \Exception('Shopee: ' . ($data['message'] ?? 'Gagal ambil produk'));
        }

        return array_map(fn ($p) => [
            'item_id' => $p['item_id'] ?? null,
            'sku' => $p['item_sku'] ?? null,
            'nama' => $p['item_name'] ?? null,
            'stok' => $p['stock'] ?? 0,
            'harga' => (float) (($p['price'] ?? 0) / 100000), // Shopee price dalam centi rupiah
        ], $data['response']['item'] ?? []);
    }

    public function mapProduct(array $channelProduct): array
    {
        return [
            'nama' => $channelProduct['nama'] ?? null,
            'kode_channel' => $channelProduct['sku'] ?? $channelProduct['item_id'] ?? null,
            'harga' => $channelProduct['harga'] ?? 0,
            'stok' => $channelProduct['stok'] ?? 0,
        ];
    }
}