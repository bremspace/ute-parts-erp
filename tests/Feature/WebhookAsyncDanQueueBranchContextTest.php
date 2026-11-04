<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Akunting\Jobs\ExportLaporanJob;
use App\Modules\Akunting\Services\ExportLaporanService;
use App\Modules\Notifikasi\Services\NotificationService;
use App\Modules\Omnichannel\Jobs\ProcessChannelOrderJob;
use App\Modules\Omnichannel\Models\Channel;
use App\Modules\Omnichannel\Models\ChannelOrder;
use App\Modules\Omnichannel\Models\ChannelProductMapping;
use App\Modules\Omnichannel\Models\ChannelWebhookInbound;
use App\Modules\Omnichannel\Services\ChannelSyncService;
use App\Modules\Pos\Models\Transaksi;
use App\Modules\Rbac\Models\Cabang;
use App\Modules\Wms\Models\Gudang;
use App\Modules\Wms\Models\Produk;
use App\Modules\Wms\Models\StokItem;
use App\Services\BranchContextManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\TestCase;

class WebhookAsyncDanQueueBranchContextTest extends TestCase
{
    use RefreshDatabase;

    protected Cabang $cabangA;

    protected Cabang $cabangB;

    protected Channel $channel;

    protected Gudang $gudang;

    protected Produk $produk;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cabangA = Cabang::create([
            'nama' => 'Cabang Utama',
            'kode' => 'C01',
            'is_aktif' => true,
        ]);

        $this->cabangB = Cabang::create([
            'nama' => 'Cabang Surabaya',
            'kode' => 'C02',
            'is_aktif' => true,
        ]);

        $this->gudang = Gudang::create([
            'nama' => 'Gudang Retail SBY',
            'kode' => 'G-SBY',
            'cabang_id' => $this->cabangB->id,
            'is_aktif' => true,
        ]);

        $this->produk = Produk::create([
            'nama' => 'LCD Touchscreen OLED S23',
            'sku' => 'LCD-S23-01',
            'kategori' => 'Sparepart',
            'harga_beli' => 200000,
            'harga_jual' => 350000,
            'is_active' => true,
        ]);

        StokItem::create([
            'produk_id' => $this->produk->id,
            'gudang_id' => $this->gudang->id,
            'jumlah' => 15,
            'jumlah_minimum' => 2,
        ]);

        $this->channel = Channel::create([
            'nama' => 'Shopee Official Store',
            'platform' => 'shopee',
            'status' => 'terhubung',
            'kredensial' => [
                'partner_id' => 12345,
                'partner_key' => 'secret_partner_key',
                'shop_id' => 88888,
                'access_token' => 'mock_token',
                'cabang_id' => $this->cabangB->id,
            ],
            'is_active' => true,
        ]);
        ChannelProductMapping::create([
            'channel_id' => $this->channel->id,
            'produk_id' => $this->produk->id,
            'gudang_id' => $this->gudang->id,
            'channel_item_id' => 'ITEM-SHOPEE-1',
            'channel_sku' => 'LCD-S23-01',
            'status' => 'tersinkron',
        ]);
    }

    public function test_webhook_omnichannel_async_ingestion_and_staging(): void
    {
        $payload = [
            'order_sn' => 'ORDER-SHOPEE-ASYNC-100',
            'order_status' => 'READY_TO_SHIP',
            'total_amount' => 350000,
            'item_list' => [
                [
                    'item_id' => 'ITEM-SHOPEE-1',
                    'item_sku' => 'LCD-S23-01',
                    'quantity_purchased' => 1,
                    'item_price' => 350000,
                ],
            ],
        ];
        $json = json_encode($payload);
        $url = url('/webhook/channel/'.$this->channel->id);
        $sig = hash_hmac('sha256', $url.'|'.$json, 'secret_partner_key');

        $response = $this->withHeaders([
            'Authorization' => $sig,
        ])->postJson('/webhook/channel/'.$this->channel->id, $payload);

        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'order_id' => 'ORDER-SHOPEE-ASYNC-100',
        ]);

        // Verifikasi tabel staging inbound webhook terisi dan terproses
        $this->assertDatabaseHas('channel_webhook_inbound', [
            'channel_id' => $this->channel->id,
            'channel_order_id' => 'ORDER-SHOPEE-ASYNC-100',
            'status' => 'processed',
        ]);

        // Verifikasi ChannelOrder internal terbuat dengan status 'diproses'
        $this->assertDatabaseHas('channel_orders', [
            'channel_id' => $this->channel->id,
            'channel_order_id' => 'ORDER-SHOPEE-ASYNC-100',
            'status' => 'diproses',
        ]);

        // Verifikasi transaksi lokal terikat pada Cabang B (sesuai kredensial channel)
        $this->assertDatabaseHas('transaksi', [
            'no_transaksi' => 'TRX-CH-'.$this->channel->id.'-ORDER-SHOPEE-ASYNC-100',
            'cabang_id' => $this->cabangB->id,
        ]);
    }

    public function test_webhook_dispatches_process_channel_order_job(): void
    {
        Queue::fake([ProcessChannelOrderJob::class]);

        $payload = [
            'order_sn' => 'ORDER-SHOPEE-QUEUE-200',
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

        // Staging inbound berstatus 'queued' saat job belum dieksekusi worker
        $this->assertDatabaseHas('channel_webhook_inbound', [
            'channel_id' => $this->channel->id,
            'channel_order_id' => 'ORDER-SHOPEE-QUEUE-200',
            'status' => 'queued',
        ]);

        // Job di-dispatch dengan channelId, payload, cabangId eksplisit
        Queue::assertPushed(ProcessChannelOrderJob::class, function ($job) {
            return $job->channelId === $this->channel->id
                && $job->payload['order_sn'] === 'ORDER-SHOPEE-QUEUE-200'
                && $job->cabangId === $this->cabangB->id
                && $job->inboundDeliveryId !== null;
        });
    }

    public function test_staging_records_failure_on_invalid_data(): void
    {
        $inbound = ChannelWebhookInbound::create([
            'channel_id' => $this->channel->id,
            'channel_order_id' => 'ORDER-INVALID',
            'event' => 'order',
            'payload' => [],
            'status' => 'queued',
        ]);

        $job = new ProcessChannelOrderJob(
            channelId: $this->channel->id,
            payload: ['bad_payload' => true], // tanpa order_sn / order_id
            cabangId: $this->cabangB->id,
            inboundDeliveryId: $inbound->id
        );

        try {
            $job->handle(app(ChannelSyncService::class), app(BranchContextManager::class));
            $this->fail('Expected exception was not thrown.');
        } catch (\Throwable $e) {
            $this->assertStringContainsString('order_sn atau order_id wajib ada', $e->getMessage());
        }

        $inbound->refresh();
        $this->assertSame('failed', $inbound->status);
        $this->assertNotNull($inbound->error);
        $this->assertStringContainsString('order_sn atau order_id wajib ada', $inbound->error);
    }

    public function test_queue_job_executes_within_isolated_branch_context(): void
    {
        $branchContext = app(BranchContextManager::class);
        $this->assertNull(currentCabangId());

        $user = User::factory()->create();

        $mockExportService = Mockery::mock(ExportLaporanService::class);
        $mockExportService->shouldReceive('export')
            ->once()
            ->andReturnUsing(function ($jenis, $dari, $sampai, $cabangId, $akunId, $format, $userId) {
                // Verifikasi bahwa di dalam execution callback, currentCabangId() aktif sesuai cabangId job
                $this->assertSame($this->cabangB->id, currentCabangId());
                $this->assertSame($this->cabangB->id, $cabangId);

                return 'exports/test_laporan.xlsx';
            });

        $mockNotif = Mockery::mock(NotificationService::class);
        $mockNotif->shouldReceive('kirim')->once();

        $job = new ExportLaporanJob(
            jenis: 'jurnal_umum',
            periodeDari: '2026-01-01',
            periodeSampai: '2026-01-31',
            cabangId: $this->cabangB->id,
            akunId: null,
            userId: $user->id,
            format: 'xlsx'
        );

        $job->handle($mockExportService, $mockNotif);

        // Pastikan setelah job selesai, context cabang direset kembali
        $this->assertNull(currentCabangId());
    }
}
