<?php

namespace App\Modules\Omnichannel\Adapters;

use App\Modules\Omnichannel\Contracts\ChannelAdapterInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Shopee Open API Adapter — Shopee Open API v2 (PRD §4.10).
 * Spesifikasi resmi: https://open.shopee.com/documents/v2/
 *
 * Autentikasi:
 * - Public API: sign = hmac_sha256(partner_id . path . timestamp, partner_key)
 * - Shop API: sign = hmac_sha256(partner_id . path . timestamp . access_token . shop_id, partner_key)
 */
class ShopeeAdapter implements ChannelAdapterInterface
{
    protected string $baseUrl;

    public function __construct()
    {
        $isSandbox = env('SHOPEE_ENV') === 'sandbox' || env('SHOPEE_SANDBOX', false);
        $this->baseUrl = $isSandbox
            ? 'https://partner.test-stable.shopeemobile.com'
            : (env('SHOPEE_BASE_URL') ?: 'https://partner.shopeemobile.com');
    }

    public function platform(): string
    {
        return 'shopee';
    }

    /**
     * Resolusi kredensial gabungan: database (utama) + .env (fallback partner).
     */
    protected function resolveCredentials(array $kredensial): array
    {
        return [
            'partner_id' => (int) ($kredensial['partner_id'] ?? env('SHOPEE_PARTNER_ID', 0)),
            'partner_key' => (string) ($kredensial['partner_key'] ?? env('SHOPEE_PARTNER_KEY', '')),
            'shop_id' => (int) ($kredensial['shop_id'] ?? env('SHOPEE_SHOP_ID', 0)),
            'access_token' => (string) ($kredensial['access_token'] ?? ''),
            'refresh_token' => (string) ($kredensial['refresh_token'] ?? ''),
            'token_expires_at' => (int) ($kredensial['token_expires_at'] ?? 0),
            'refresh_token_expires_at' => (int) ($kredensial['refresh_token_expires_at'] ?? 0),
        ];
    }

    /**
     * Generate Shopee v2 URL dengan signature & query parameters.
     */
    public function buildUrl(string $path, array $cred, bool $isShopApi = true, array $additionalParams = []): string
    {
        $timestamp = time();
        $partnerId = (int) $cred['partner_id'];
        $partnerKey = (string) $cred['partner_key'];
        $shopId = (int) ($cred['shop_id'] ?? 0);
        $accessToken = (string) ($cred['access_token'] ?? '');

        $baseString = $isShopApi
            ? "{$partnerId}{$path}{$timestamp}{$accessToken}{$shopId}"
            : "{$partnerId}{$path}{$timestamp}";

        $sign = hash_hmac('sha256', $baseString, $partnerKey);

        $params = array_merge([
            'partner_id' => $partnerId,
            'timestamp' => $timestamp,
            'sign' => $sign,
        ], $additionalParams);

        if ($isShopApi) {
            $params['access_token'] = $accessToken;
            $params['shop_id'] = $shopId;
        }

        return $this->baseUrl.$path.'?'.http_build_query($params);
    }

    /**
     * URL redirect untuk otorisasi toko oleh seller Shopee.
     */
    public function getAuthUrl(array $cred, string $redirectUrl): string
    {
        $resolved = $this->resolveCredentials($cred);
        $path = '/api/v2/shop/auth_partner';

        return $this->buildUrl($path, $resolved, isShopApi: false, additionalParams: [
            'redirect' => $redirectUrl,
        ]);
    }

    /**
     * Tukar authorization code menjadi access_token & refresh_token.
     */
    public function handleAuthCallback(array $cred, string $code, string|int $shopId): array
    {
        $resolved = $this->resolveCredentials($cred);
        $resolved['shop_id'] = (int) $shopId;
        $path = '/api/v2/auth/token/get';

        $url = $this->buildUrl($path, $resolved, isShopApi: false);

        $response = Http::timeout(20)->post($url, [
            'code' => $code,
            'partner_id' => $resolved['partner_id'],
            'shop_id' => (int) $shopId,
        ]);

        $data = $response->json();
        if (! empty($data['error'])) {
            throw new \Exception('Shopee Auth Gagal: '.($data['message'] ?? $data['error']));
        }

        $res = $data['response'] ?? [];
        $expireIn = (int) ($res['expire_in'] ?? 14400);

        return [
            'access_token' => (string) ($res['access_token'] ?? ''),
            'refresh_token' => (string) ($res['refresh_token'] ?? ''),
            'token_expires_at' => time() + $expireIn,
            'refresh_token_expires_at' => time() + (30 * 86400),
            'shop_id' => (int) ($res['shop_id'] ?? $shopId),
        ];
    }

    /**
     * Refresh access_token sebelum kedaluwarsa (berlaku 4 jam).
     */
    public function refreshAccessToken(array $cred): array
    {
        $resolved = $this->resolveCredentials($cred);
        $path = '/api/v2/auth/access_token/get';

        if (empty($resolved['refresh_token'])) {
            throw new \Exception('Shopee refresh_token kosong, otorisasi ulang diperlukan.');
        }

        $url = $this->buildUrl($path, $resolved, isShopApi: false);

        $response = Http::timeout(20)->post($url, [
            'refresh_token' => $resolved['refresh_token'],
            'partner_id' => $resolved['partner_id'],
            'shop_id' => $resolved['shop_id'],
        ]);

        $data = $response->json();
        if (! empty($data['error'])) {
            throw new \Exception('Shopee Refresh Token Gagal: '.($data['message'] ?? $data['error']));
        }

        $res = $data['response'] ?? [];
        $expireIn = (int) ($res['expire_in'] ?? 14400);

        return [
            'access_token' => (string) ($res['access_token'] ?? ''),
            'refresh_token' => (string) ($res['refresh_token'] ?? $resolved['refresh_token']),
            'token_expires_at' => time() + $expireIn,
            'refresh_token_expires_at' => time() + (30 * 86400),
        ];
    }

    /**
     * Tes koneksi ke Shopee Open API v2 (get_shop_info).
     */
    public function testConnection(array $kredensial): bool
    {
        $cred = $this->resolveCredentials($kredensial);

        if (empty($cred['partner_id']) || empty($cred['partner_key'])) {
            throw new \Exception('Partner ID dan Partner Key Shopee wajib diisi.');
        }

        // Jika belum ada access_token (tahap input kredensial dasar sebelum OAuth)
        if (empty($cred['access_token'])) {
            return true;
        }

        $path = '/api/v2/shop/get_shop_info';
        $url = $this->buildUrl($path, $cred, isShopApi: true);

        $response = Http::timeout(15)->get($url);
        $data = $response->json();

        if (! empty($data['error'])) {
            throw new \Exception('Shopee: '.($data['message'] ?? $data['error']));
        }

        return true;
    }

    /**
     * Tarik order terbaru dari Shopee.
     */
    public function pullOrders(array $kredensial, ?int $timeFrom = null, ?int $timeTo = null): array
    {
        $cred = $this->resolveCredentials($kredensial);
        $path = '/api/v2/order/get_order_list';

        $timeFrom = $timeFrom ?: now()->subDays(2)->timestamp;
        $timeTo = $timeTo ?: now()->timestamp;

        $url = $this->buildUrl($path, $cred, isShopApi: true, additionalParams: [
            'time_range_field' => 'create_time',
            'time_from' => $timeFrom,
            'time_to' => $timeTo,
            'page_size' => 50,
            'response_optional_fields' => 'order_status',
        ]);

        $response = Http::timeout(20)->get($url);
        $data = $response->json();

        if (! empty($data['error'])) {
            throw new \Exception('Shopee Gagal Tarik Order: '.($data['message'] ?? $data['error']));
        }

        $orderList = $data['response']['order_list'] ?? [];
        if (empty($orderList)) {
            return [];
        }

        $orderSns = array_column($orderList, 'order_sn');

        // Tarik rincian item tiap order
        return $this->fetchOrderDetails($cred, $orderSns);
    }

    /**
     * Ambil rincian pesanan (item, harga, customer) per batch order_sn.
     */
    public function fetchOrderDetails(array $cred, array $orderSns): array
    {
        if (empty($orderSns)) {
            return [];
        }

        $path = '/api/v2/order/get_order_detail';
        $details = [];

        foreach (array_chunk($orderSns, 50) as $chunk) {
            $url = $this->buildUrl($path, $cred, isShopApi: true, additionalParams: [
                'order_sn_list' => implode(',', $chunk),
                'response_optional_fields' => 'buyer_user_id,buyer_username,item_list,total_amount,order_status,payment_method,shipping_carrier',
            ]);

            $response = Http::timeout(20)->get($url);
            $data = $response->json();

            if (! empty($data['response']['order_list'])) {
                foreach ($data['response']['order_list'] as $order) {
                    $details[] = $order;
                }
            }
        }

        return $details;
    }

    /**
     * Push stok produk ke Shopee v2.
     *
     * @param  array  $items  [['item_id' => int, 'model_id' => int|null, 'stock' => int], ...]
     */
    public function pushStock(array $kredensial, array $items): bool
    {
        if (empty($items)) {
            return true;
        }

        $cred = $this->resolveCredentials($kredensial);
        $path = '/api/v2/product/update_stock';

        // Kelompokkan per item_id
        $grouped = [];
        foreach ($items as $it) {
            $itemId = (int) ($it['item_id'] ?? 0);
            if (! $itemId) {
                continue;
            }
            $grouped[$itemId][] = [
                'model_id' => (int) ($it['model_id'] ?? 0),
                'seller_stock' => [
                    [
                        'stock' => (int) max(0, $it['stock'] ?? 0),
                    ],
                ],
            ];
        }

        $allOk = true;
        foreach ($grouped as $itemId => $stockList) {
            $url = $this->buildUrl($path, $cred, isShopApi: true);

            $response = Http::timeout(20)->post($url, [
                'item_id' => $itemId,
                'stock_list' => $stockList,
            ]);

            $data = $response->json();
            if (! empty($data['error'])) {
                Log::warning("Shopee pushStock error (item #{$itemId}): ".($data['message'] ?? $data['error']));
                $allOk = false;
            }
        }

        return $allOk;
    }

    /**
     * Push harga produk ke Shopee v2.
     *
     * @param  array  $items  [['item_id' => int, 'model_id' => int|null, 'price' => float], ...]
     */
    public function pushPrice(array $kredensial, array $items): bool
    {
        if (empty($items)) {
            return true;
        }

        $cred = $this->resolveCredentials($kredensial);
        $path = '/api/v2/product/update_price';

        $grouped = [];
        foreach ($items as $it) {
            $itemId = (int) ($it['item_id'] ?? 0);
            if (! $itemId) {
                continue;
            }
            $grouped[$itemId][] = [
                'model_id' => (int) ($it['model_id'] ?? 0),
                'original_price' => (float) ($it['price'] ?? 0),
            ];
        }

        $allOk = true;
        foreach ($grouped as $itemId => $priceList) {
            $url = $this->buildUrl($path, $cred, isShopApi: true);

            $response = Http::timeout(20)->post($url, [
                'item_id' => $itemId,
                'price_list' => $priceList,
            ]);

            $data = $response->json();
            if (! empty($data['error'])) {
                Log::warning("Shopee pushPrice error (item #{$itemId}): ".($data['message'] ?? $data['error']));
                $allOk = false;
            }
        }

        return $allOk;
    }

    /**
     * Ambil daftar produk etalase Shopee v2.
     */
    public function fetchProducts(array $kredensial): array
    {
        $cred = $this->resolveCredentials($kredensial);
        $pathList = '/api/v2/product/get_item_list';

        $urlList = $this->buildUrl($pathList, $cred, isShopApi: true, additionalParams: [
            'offset' => 0,
            'page_size' => 50,
            'item_status' => 'NORMAL',
        ]);

        $responseList = Http::timeout(20)->get($urlList);
        $dataList = $responseList->json();

        if (! empty($dataList['error'])) {
            throw new \Exception('Shopee Gagal Ambil Item List: '.($dataList['message'] ?? $dataList['error']));
        }

        $items = $dataList['response']['item'] ?? [];
        if (empty($items)) {
            return [];
        }

        $itemIds = array_column($items, 'item_id');

        // Ambil info detail produk
        $pathInfo = '/api/v2/product/get_item_base_info';
        $urlInfo = $this->buildUrl($pathInfo, $cred, isShopApi: true, additionalParams: [
            'item_id_list' => implode(',', array_slice($itemIds, 0, 50)),
        ]);

        $responseInfo = Http::timeout(20)->get($urlInfo);
        $dataInfo = $responseInfo->json();

        $result = [];
        foreach ($dataInfo['response']['item_list'] ?? [] as $p) {
            $hasModel = (bool) ($p['has_model'] ?? false);
            $models = [];

            if ($hasModel) {
                // Ambil daftar model/varian
                $models = $this->fetchModels($cred, (int) $p['item_id']);
            }

            $result[] = [
                'item_id' => $p['item_id'],
                'sku' => $p['item_sku'] ?? null,
                'nama' => $p['item_name'] ?? null,
                'stok' => (int) ($p['stock_info_v2']['seller_stock'][0]['stock'] ?? 0),
                'harga' => (float) ($p['price_info'][0]['original_price'] ?? 0),
                'has_model' => $hasModel,
                'models' => $models,
            ];
        }

        return $result;
    }

    /**
     * Ambil varian model untuk produk yang memiliki variasi di Shopee.
     */
    protected function fetchModels(array $cred, int $itemId): array
    {
        $path = '/api/v2/product/get_model_list';
        $url = $this->buildUrl($path, $cred, isShopApi: true, additionalParams: [
            'item_id' => $itemId,
        ]);

        $response = Http::timeout(15)->get($url);
        $data = $response->json();

        $models = [];
        foreach ($data['response']['model'] ?? [] as $m) {
            $models[] = [
                'model_id' => $m['model_id'],
                'sku' => $m['model_sku'] ?? null,
                'nama' => $m['model_name'] ?? null,
                'stok' => (int) ($m['stock_info_v2']['seller_stock'][0]['stock'] ?? 0),
                'harga' => (float) ($m['price_info'][0]['original_price'] ?? 0),
            ];
        }

        return $models;
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

    /**
     * Verifikasi signature webhook notifikasi Shopee (Shopee Push Mechanism).
     * Header Authorization: HMAC-SHA256(full_url . '|' . raw_body, partner_key)
     */
    public function verifyWebhookSignature(string $url, string $rawBody, string $signature, string $partnerKey): bool
    {
        if (empty($signature) || empty($partnerKey)) {
            return false;
        }

        $baseString = $url.'|'.$rawBody;
        $expected = hash_hmac('sha256', $baseString, $partnerKey);

        return hash_equals($expected, $signature);
    }
}
