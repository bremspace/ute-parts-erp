<?php

namespace Tests\Feature;

use App\Modules\Omnichannel\Adapters\ShopeeAdapter;
use App\Modules\Omnichannel\Models\Channel;
use App\Modules\Omnichannel\Models\ChannelProductMapping;
use App\Modules\Omnichannel\Services\ChannelSyncService;
use App\Modules\Pos\Models\Transaksi;
use App\Modules\Rbac\Models\Cabang;
use App\Modules\Wms\Models\Gudang;
use App\Modules\Wms\Models\Produk;
use App\Modules\Wms\Models\SkuVariant;
use App\Modules\Wms\Models\StockMutationLog;
use App\Modules\Wms\Models\StokItem;
use App\Modules\Wms\Services\StokDeductionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OmnichannelShopeeIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected Cabang $cabang;

    protected Gudang $gudang;

    protected Produk $produk;

    protected Channel $channel;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cabang = Cabang::create([
            'nama' => 'Cabang Utama Shopee',
            'kode' => 'CU-01',
            'alamat' => 'Jl. Uji No. 1',
            'is_active' => true,
        ]);

        $this->gudang = Gudang::create([
            'cabang_id' => $this->cabang->id,
            'nama' => 'Gudang Shopee',
            'kode' => 'GDG-SHP',
            'is_active' => true,
        ]);

        $this->produk = Produk::create([
            'kode_produk' => 'PRD-SHP-001',
            'nama' => 'LCD Touchscreen OLED',
            'kategori' => 'Sparepart',
            'harga_beli' => 100000,
            'harga_jual' => 150000,
            'is_active' => true,
        ]);

        $this->channel = Channel::create([
            'nama' => 'Ute Parts Shopee Official',
            'platform' => 'shopee',
            'status' => 'terhubung',
            'kredensial' => [
                'partner_id' => 12345,
                'partner_key' => 'secret_partner_key',
                'shop_id' => 67890,
                'access_token' => 'test_access_token',
                'refresh_token' => 'test_refresh_token',
                'token_expires_at' => time() + 7200,
                'biaya_persen' => 5,
                'cabang_id' => $this->cabang->id,
            ],
            'is_active' => true,
        ]);
    }

    public function test_shopee_adapter_signature_and_auth_url(): void
    {
        $adapter = new ShopeeAdapter;

        $authUrl = $adapter->getAuthUrl($this->channel->kredensial, 'https://test.uteparts.id/callback');
        $this->assertStringContainsString('partner_id=12345', $authUrl);
        $this->assertStringContainsString('/api/v2/shop/auth_partner', $authUrl);
        $this->assertStringContainsString('sign=', $authUrl);
    }

    public function test_shopee_adapter_token_exchange(): void
    {
        Http::fake([
            '*/api/v2/auth/token/get*' => Http::response([
                'error' => '',
                'response' => [
                    'access_token' => 'new_access_token_123',
                    'refresh_token' => 'new_refresh_token_456',
                    'expire_in' => 14400,
                    'shop_id' => 67890,
                ],
            ], 200),
        ]);

        $adapter = new ShopeeAdapter;
        $res = $adapter->handleAuthCallback($this->channel->kredensial, 'auth_code_xyz', 67890);

        $this->assertSame('new_access_token_123', $res['access_token']);
        $this->assertSame('new_refresh_token_456', $res['refresh_token']);
        $this->assertGreaterThan(time(), $res['token_expires_at']);
    }

    public function test_shopee_adapter_webhook_signature_verification(): void
    {
        $adapter = new ShopeeAdapter;
        $url = 'https://test.uteparts.id/webhook/channel/shopee';
        $body = json_encode(['data' => ['order_sn' => 'SN-999', 'status' => 'READY_TO_SHIP']]);
        $key = 'secret_partner_key';

        $validSignature = hash_hmac('sha256', $url.'|'.$body, $key);

        $this->assertTrue($adapter->verifyWebhookSignature($url, $body, $validSignature, $key));
        $this->assertFalse($adapter->verifyWebhookSignature($url, $body, 'invalid_sig', $key));
    }

    public function test_stock_push_to_shopee_with_variant_model(): void
    {
        $variant = SkuVariant::create([
            'produk_id' => $this->produk->id,
            'sku' => 'PRD-SHP-001-BLK',
            'nama_varian' => 'Black Original',
            'is_active' => true,
        ]);

        StokItem::create([
            'produk_id' => $this->produk->id,
            'sku_variant_id' => $variant->id,
            'gudang_id' => $this->gudang->id,
            'jumlah' => 25,
            'jumlah_minimum' => 5,
        ]);

        ChannelProductMapping::create([
            'channel_id' => $this->channel->id,
            'produk_id' => $this->produk->id,
            'sku_variant_id' => $variant->id,
            'gudang_id' => $this->gudang->id,
            'channel_sku' => 'PRD-SHP-001-BLK',
            'channel_item_id' => '111222',
            'channel_model_id' => '333444',
            'status' => 'tersinkron',
        ]);

        Http::fake([
            '*/api/v2/product/update_stock*' => function ($request) {
                $body = json_decode($request->body(), true);
                $this->assertSame(111222, $body['item_id']);
                $this->assertSame(333444, $body['stock_list'][0]['model_id']);
                $this->assertSame(25, $body['stock_list'][0]['seller_stock'][0]['stock']);

                return Http::response(['error' => '', 'message' => 'success'], 200);
            },
        ]);

        $svc = app(ChannelSyncService::class);
        $svc->syncStokSemuaChannel($this->produk->id, $variant->id);

        $this->assertDatabaseHas('channel_product_mapping', [
            'channel_id' => $this->channel->id,
            'produk_id' => $this->produk->id,
            'status' => 'tersinkron',
        ]);
    }

    public function test_order_ingestion_deducts_stock_and_is_idempotent(): void
    {
        // Siapkan stok awal di gudang
        StokItem::create([
            'produk_id' => $this->produk->id,
            'sku_variant_id' => null,
            'gudang_id' => $this->gudang->id,
            'jumlah' => 10,
            'jumlah_minimum' => 2,
        ]);

        ChannelProductMapping::create([
            'channel_id' => $this->channel->id,
            'produk_id' => $this->produk->id,
            'sku_variant_id' => null,
            'gudang_id' => $this->gudang->id,
            'channel_sku' => 'PRD-SHP-001',
            'channel_item_id' => '111222',
            'status' => 'tersinkron',
        ]);

        $orderPayload = [
            'order_sn' => 'SHOPEE-ORDER-888',
            'order_status' => 'READY_TO_SHIP',
            'total_amount' => 300000,
            'item_list' => [
                [
                    'item_id' => '111222',
                    'model_id' => 0,
                    'item_sku' => 'PRD-SHP-001',
                    'model_quantity_purchased' => 2,
                ],
            ],
        ];

        $svc = app(ChannelSyncService::class);

        // Call 1: Proses order pertama kali
        $order = $svc->prosesOrderMasuk($this->channel, $orderPayload);

        $this->assertSame('diproses', $order->status);
        $this->assertNotNull($order->transaksi_id);

        // Stok awal 10 - 2 = 8
        $stokTersisa = StokItem::where('produk_id', $this->produk->id)
            ->where('gudang_id', $this->gudang->id)
            ->value('jumlah');
        $this->assertSame(8, $stokTersisa);

        // Call 2: Webhook / polling ulang payload yang sama (Idempotency test)
        $order2 = $svc->prosesOrderMasuk($this->channel, $orderPayload);

        $this->assertSame($order->id, $order2->id);

        // Stok TIDAK boleh terpotong dua kali!
        $stokSetelahRepeat = StokItem::where('produk_id', $this->produk->id)
            ->where('gudang_id', $this->gudang->id)
            ->value('jumlah');
        $this->assertSame(8, $stokSetelahRepeat);
        $this->assertSame(1, Transaksi::where('sumber', 'channel:shopee')->count());
    }

    public function test_webhook_endpoint_authenticates_and_processes_order(): void
    {
        $payload = [
            'order_sn' => 'WEBHOOK-ORDER-001',
            'order_status' => 'READY_TO_SHIP',
            'total_amount' => 150000,
            'item_list' => [],
        ];
        $json = json_encode($payload);
        $url = url('/webhook/channel/'.$this->channel->id);
        $sig = hash_hmac('sha256', $url.'|'.$json, 'secret_partner_key');

        $response = $this->withHeaders([
            'Authorization' => $sig,
        ])->postJson('/webhook/channel/'.$this->channel->id, $payload);

        $response->assertOk();
        $this->assertDatabaseHas('channel_orders', [
            'channel_id' => $this->channel->id,
            'channel_order_id' => 'WEBHOOK-ORDER-001',
            'status' => 'diproses',
        ]);
    }

    public function test_token_auto_refresh(): void
    {
        $expiringChannel = Channel::create([
            'nama' => 'Shopee Toko Expiring',
            'platform' => 'shopee',
            'status' => 'terhubung',
            'kredensial' => [
                'partner_id' => 12345,
                'partner_key' => 'secret_partner_key',
                'shop_id' => 99999,
                'access_token' => 'expiring_token',
                'refresh_token' => 'valid_refresh_token',
                'token_expires_at' => time() + 1800, // sisa 30 menit -> harus direfresh
            ],
            'is_active' => true,
        ]);

        Http::fake([
            '*/api/v2/auth/access_token/get*' => Http::response([
                'error' => '',
                'response' => [
                    'access_token' => 'refreshed_access_token_777',
                    'refresh_token' => 'refreshed_refresh_token_888',
                    'expire_in' => 14400,
                ],
            ], 200),
        ]);

        $svc = app(ChannelSyncService::class);
        $refreshedCount = $svc->refreshExpiredTokens();

        $this->assertGreaterThanOrEqual(1, $refreshedCount);

        $expiringChannel->refresh();
        $this->assertSame('refreshed_access_token_777', $expiringChannel->kredensial['access_token']);
    }

    public function test_warehouse_stock_deduction_triggers_channel_sync(): void
    {
        ChannelProductMapping::create([
            'channel_id' => $this->channel->id,
            'produk_id' => $this->produk->id,
            'sku_variant_id' => null,
            'gudang_id' => $this->gudang->id,
            'channel_sku' => 'PRD-SHP-001',
            'channel_item_id' => '998877',
            'status' => 'tersinkron',
        ]);

        $pushedStock = null;
        Http::fake([
            '*/api/v2/product/update_stock*' => function ($request) use (&$pushedStock) {
                $body = json_decode($request->body(), true);
                $pushedStock = $body['stock_list'][0]['seller_stock'][0]['stock'] ?? null;

                return Http::response(['error' => '', 'message' => 'success'], 200);
            },
        ]);

        // Stok awal masuk via WMS penerimaan barang / PO
        StokItem::create([
            'produk_id' => $this->produk->id,
            'sku_variant_id' => null,
            'gudang_id' => $this->gudang->id,
            'jumlah' => 50,
            'jumlah_minimum' => 5,
        ]);

        StockMutationLog::create([
            'produk_id' => $this->produk->id,
            'sku_variant_id' => null,
            'gudang_id' => $this->gudang->id,
            'delta' => 50,
            'sumber' => 'po',
            'terjadi_at' => now()->subMinute(),
        ]);

        // Simulasikan penjualan kasir fisik POS
        app(StokDeductionService::class)->kurangi(
            produkId: $this->produk->id,
            skuVariantId: null,
            gudangId: $this->gudang->id,
            qty: 5,
            jenis: 'penjualan',
            referensiTipe: 'pos_transaksi',
            referensiId: 1,
            userId: null
        );

        // Stok lokal berkurang 50 - 5 = 45, dan otomatis ter-push ke Shopee
        $this->assertSame(45, $pushedStock);
    }
}
