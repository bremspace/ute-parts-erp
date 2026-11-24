<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Akunting\Models\AkunCOA;
use App\Modules\Akunting\Models\JurnalHeader;
use App\Modules\Akunting\Services\JurnalService;
use App\Modules\Crm\Models\Pelanggan;
use App\Modules\Crm\Models\TierMembership;
use App\Modules\Hr\Models\Karyawan;
use App\Modules\Hr\Models\PayrollPeriode;
use App\Modules\Pos\Models\HargaTier;
use App\Modules\Pos\Models\Transaksi;
use App\Modules\Pos\Models\TransaksiItem;
use App\Modules\Rbac\Livewire\SettingsRbac;
use App\Modules\Rbac\Models\Cabang;
use App\Modules\Sync\Jobs\BatchPushHybridSyncJob;
use App\Modules\Sync\Jobs\PushHybridSyncJob;
use App\Modules\Sync\Models\HybridSyncInbox;
use App\Modules\Sync\Models\HybridSyncOutbox;
use App\Modules\Sync\Services\HybridSyncOutboxService;
use App\Modules\Sync\Services\HybridSyncProcessorService;
use App\Modules\Wms\Models\Brand;
use App\Modules\Wms\Models\Gudang;
use App\Modules\Wms\Models\Produk;
use App\Modules\Wms\Models\ProdukBarcode;
use App\Modules\Wms\Models\ProdukSatuanKonversi;
use App\Modules\Wms\Models\SkuVariant;
use App\Modules\Wms\Models\StokItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class HybridSyncAuditRegressionTest extends TestCase
{
    use RefreshDatabase;

    private Cabang $cabang;

    private Gudang $gudang;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'sync.enabled' => true,
            'sync.node_role' => 'primary_store',
            'sync.node_id' => 'STORE-01',
            'sync.remote_url' => 'https://cloud.bremspace.test',
            'sync.api_secret' => 'audit-secret-key-999',
        ]);

        $this->cabang = Cabang::create([
            'nama' => 'Cabang Audit',
            'kode' => 'AUD01',
            'alamat' => 'Jl. Audit 1',
            'telepon' => '0812000001',
            'tipe' => 'pusat',
            'is_active' => true,
        ]);

        $this->gudang = Gudang::create([
            'cabang_id' => $this->cabang->id,
            'nama' => 'Gudang Audit',
            'kode' => 'GDG-AUD',
            'is_default' => true,
            'is_active' => true,
        ]);

        $this->user = User::factory()->create([
            'email' => 'kasir.audit@bremspace.id',
        ]);
        $this->user->cabangs()->attach($this->cabang->id, ['is_default' => true]);

        foreach ([
            ['110-02', 'Bank BCA', 'aset', 'kas', 'debit'],
            ['110-04', 'Kas Kasir', 'aset', 'kas', 'debit'],
            ['120-01', 'Piutang Dagang', 'aset', 'piutang', 'debit'],
            ['130-01', 'Persediaan Barang', 'aset', 'persediaan', 'debit'],
            ['210-02', 'Hutang Gaji', 'kewajiban', 'hutang', 'kredit'],
            ['220-01', 'PPN Keluaran', 'kewajiban', 'pajak', 'kredit'],
            ['410-01', 'Pendapatan POS', 'pendapatan', 'pendapatan_penjualan', 'kredit'],
            ['510-02', 'HPP Penjualan', 'beban', 'hpp', 'debit'],
            ['520-01', 'Beban Gaji', 'beban', 'beban_operasional', 'debit'],
            ['610-01', 'Biaya Operasional', 'beban', 'beban_operasional', 'debit'],
        ] as [$kode, $nama, $tipe, $kelompok, $saldo]) {
            AkunCOA::firstOrCreate(
                ['kode' => $kode],
                ['nama' => $nama, 'tipe' => $tipe, 'kelompok' => $kelompok, 'saldo_normal' => $saldo, 'is_active' => true]
            );
        }
    }

    public function test_failed_inbox_event_is_retried_and_not_skipped_as_idempotent(): void
    {
        $uuid = (string) Str::uuid();
        HybridSyncInbox::create([
            'sync_uuid' => $uuid,
            'source_node' => 'CLOUD-01',
            'event_type' => 'brand.upsert',
            'payload_hash' => hash('sha256', '{}'),
            'status' => 'failed',
            'error_message' => 'Simulated prior transient failure',
        ]);

        $result = app(HybridSyncProcessorService::class)->process([
            'sync_uuid' => $uuid,
            'event_type' => 'brand.upsert',
            'source_node' => 'CLOUD-01',
            'payload' => [
                'sync_uuid' => (string) Str::uuid(),
                'nama' => 'Brand Retry Sukses',
                'is_active' => true,
            ],
        ]);

        $this->assertTrue($result['success']);
        $this->assertDatabaseHas('hybrid_sync_inbox', [
            'sync_uuid' => $uuid,
            'status' => 'processed',
            'error_message' => null,
        ]);
        $this->assertDatabaseHas('brands', ['nama' => 'Brand Retry Sukses']);
    }

    public function test_produk_observer_and_upsert_work_without_cabang_id_and_sync_soft_delete(): void
    {
        Queue::fake();
        config(['sync.node_role' => 'primary_store']);

        $produk = Produk::create([
            'nama' => 'LCD Audit Test',
            'slug' => 'lcd-audit-test',
            'barcode' => 'BC-AUD-01',
            'kategori' => 'LCD',
            'kondisi' => 'baru',
            'satuan' => 'pcs',
            'harga_beli' => 120000,
            'harga_jual_retail' => 180000,
            'is_active' => true,
        ]);

        $outbox = HybridSyncOutbox::where('event_type', 'produk.upsert')
            ->where('entity_id', $produk->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($outbox);
        $this->assertNotEmpty($outbox->payload['sync_uuid'] ?? null);
        $this->assertNull($outbox->payload['deleted_at']);

        // Soft delete triggers produk.upsert with deleted_at
        $produk->delete();

        $deleteOutbox = HybridSyncOutbox::where('event_type', 'produk.upsert')
            ->where('entity_id', $produk->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($deleteOutbox->payload['deleted_at']);

        // Process soft-delete payload for another cloud product
        $remoteUuid = (string) Str::uuid();
        $res = app(HybridSyncProcessorService::class)->process([
            'sync_uuid' => (string) Str::uuid(),
            'event_type' => 'produk.upsert',
            'source_node' => 'CLOUD-01',
            'payload' => [
                'sync_uuid' => $remoteUuid,
                'nama' => 'Baterai Deleted Remote',
                'slug' => 'baterai-deleted-remote',
                'barcode' => 'BC-REM-DEL',
                'satuan' => 'pcs',
                'harga_beli' => 50000,
                'harga_jual_retail' => 80000,
                'deleted_at' => now()->toIso8601String(),
            ],
        ]);

        $this->assertTrue($res['success']);
        $this->assertSoftDeleted('produk', ['sync_uuid' => $remoteUuid]);
    }

    public function test_order_online_paid_observer_enqueues_once_only_on_unpaid_to_paid_transition(): void
    {
        Queue::fake();
        config(['sync.node_role' => 'cloud_storefront']);

        $produk = Produk::create([
            'nama' => 'Flexible Audit',
            'slug' => 'flexible-audit',
            'kategori' => 'Sparepart',
            'kondisi' => 'baru',
            'satuan' => 'pcs',
            'harga_beli' => 10000,
            'harga_jual_retail' => 25000,
            'is_active' => true,
        ]);

        $tx = Transaksi::create([
            'no_transaksi' => 'ONL-OBS-001',
            'cabang_id' => $this->cabang->id,
            'gudang_id' => $this->gudang->id,
            'sumber' => 'marketplace',
            'subtotal' => 50000,
            'dpp' => 50000,
            'ppn_nominal' => 0,
            'total_akhir' => 50000,
            'metode_bayar' => 'transfer',
            'status' => 'menunggu_pembayaran',
        ]);

        TransaksiItem::create([
            'transaksi_id' => $tx->id,
            'produk_id' => $produk->id,
            'jumlah' => 2,
            'satuan' => 'pcs',
            'konversi' => 1,
            'harga_satuan' => 25000,
            'subtotal' => 50000,
            'hpp' => 10000,
        ]);

        $this->assertEquals(0, HybridSyncOutbox::where('event_type', 'order.online_paid')->count());

        // unpaid -> lunas must fire 1 event with C4 fields
        $tx->update(['status' => 'lunas', 'paid_at' => now()]);
        $this->assertEquals(1, HybridSyncOutbox::where('event_type', 'order.online_paid')->count());

        $outbox = HybridSyncOutbox::where('event_type', 'order.online_paid')->first();
        $this->assertSame('pcs', $outbox->payload['items'][0]['satuan']);
        $this->assertEquals(10000, $outbox->payload['items'][0]['hpp']);

        // lunas -> selesai must NOT emit duplicate order.online_paid
        $tx->update(['status' => 'selesai']);
        $this->assertEquals(1, HybridSyncOutbox::where('event_type', 'order.online_paid')->count());
    }

    public function test_transaksi_pos_observer_ignores_held_allows_servis_and_syncs_void(): void
    {
        Queue::fake();
        config(['sync.node_role' => 'primary_store']);

        $held = Transaksi::create([
            'no_transaksi' => 'HOLD-001',
            'cabang_id' => $this->cabang->id,
            'gudang_id' => $this->gudang->id,
            'sumber' => 'pos',
            'subtotal' => 50000,
            'total_akhir' => 50000,
            'metode_bayar' => 'tunai',
            'jumlah_bayar' => 0,
            'status' => 'ditahan',
        ]);

        $this->assertEquals(0, HybridSyncOutbox::where('event_type', 'transaksi.pos')->count());

        $held->update(['status' => 'selesai', 'jumlah_bayar' => 50000]);
        $this->assertEquals(1, HybridSyncOutbox::where('event_type', 'transaksi.pos')->count());

        $held->update(['status' => 'void', 'void_alasan' => 'Batal beli']);
        $this->assertEquals(1, HybridSyncOutbox::where('event_type', 'transaksi.void')->count());

        // Servis payment synced via transaksi.pos must NOT post duplicate 410-01 revenue journal on Cloud
        config(['sync.node_role' => 'cloud_storefront']);
        $res = app(HybridSyncProcessorService::class)->process([
            'sync_uuid' => (string) Str::uuid(),
            'event_type' => 'transaksi.pos',
            'source_node' => 'STORE-01',
            'payload' => [
                'no_transaksi' => 'POS-SRV-001',
                'offline_uuid' => (string) Str::uuid(),
                'cabang_id' => $this->cabang->id,
                'sumber' => 'servis',
                'status' => 'selesai',
                'metode_bayar' => 'tunai',
                'total_akhir' => 150000,
                'poin_handled_at_source' => true,
                'items' => [],
            ],
        ]);

        $this->assertTrue($res['success']);
        $srvTx = Transaksi::where('no_transaksi', 'POS-SRV-001')->firstOrFail();
        $this->assertEquals(
            0,
            JurnalHeader::where('referensi_tipe', Transaksi::class)
                ->where('referensi_id', $srvTx->id)
                ->count()
        );
    }

    public function test_cloud_pos_failover_kasbon_ppn_warranty_variant_stock_and_void_reversal(): void
    {
        config(['sync.node_role' => 'primary_store']);

        $pelanggan = Pelanggan::create([
            'nama' => 'Mitra Kasbon',
            'telepon' => '081234567800',
            'poin_loyalty' => 50,
            'saldo_piutang' => 0,
            'status' => 'aktif',
        ]);

        $produk = Produk::create([
            'nama' => 'IC Power Audit',
            'slug' => 'ic-power-audit',
            'kategori' => 'IC',
            'kondisi' => 'baru',
            'satuan' => 'pcs',
            'harga_beli' => 30000,
            'harga_jual_retail' => 50000,
            'is_active' => true,
        ]);

        $variant = SkuVariant::create([
            'produk_id' => $produk->id,
            'sku' => 'IC-PWR-AUD-V1',
            'nama_varian' => 'Rev B',
            'harga_beli' => 40000,
            'harga_jual_retail' => 60000,
            'is_active' => true,
        ]);

        $stok = StokItem::create([
            'gudang_id' => $this->gudang->id,
            'produk_id' => $produk->id,
            'sku_variant_id' => $variant->id,
            'jumlah' => 20,
        ]);

        $offlineUuid = (string) Str::uuid();
        $res = app(HybridSyncProcessorService::class)->process([
            'sync_uuid' => (string) Str::uuid(),
            'event_type' => 'transaksi.pos_cloud',
            'source_node' => 'VPS-01',
            'payload' => [
                'no_transaksi' => 'VPS-KASBON-001',
                'offline_uuid' => $offlineUuid,
                'cabang_id' => $this->cabang->id,
                'gudang_id' => $this->gudang->id,
                'pelanggan_id' => $pelanggan->id,
                'sumber' => 'pos_cloud',
                'status' => 'selesai',
                'metode_bayar' => 'piutang',
                'subtotal' => 200000,
                'dpp' => 200000,
                'ppn_nominal' => 22000,
                'total_akhir' => 222000,
                'durasi_garansi_hari' => 30,
                'garansi_teks' => 'Garansi 30 Hari',
                'poin_handled_at_source' => true,
                'items' => [
                    [
                        'produk_id' => $produk->id,
                        'sku_variant_id' => $variant->id,
                        'jumlah' => 2,
                        'konversi' => 2,
                        'satuan' => 'pack',
                        'harga_satuan' => 100000,
                        'subtotal' => 200000,
                        'hpp' => 40000,
                    ],
                ],
            ],
        ]);

        $this->assertTrue($res['success']);
        $stok->refresh();
        $pelanggan->refresh();

        // 20 - (2 * 2) = 16
        $this->assertEquals(16, (int) $stok->jumlah);
        // Tidak ada poin ganda
        $this->assertEquals(50, (int) $pelanggan->poin_loyalty);
        // Piutang & saldo_piutang tercatat penuh
        $this->assertEquals(222000, (float) $pelanggan->saldo_piutang);
        $this->assertDatabaseHas('transaksi', [
            'no_transaksi' => 'VPS-KASBON-001',
            'durasi_garansi_hari' => 30,
            'garansi_teks' => 'Garansi 30 Hari',
        ]);
        $this->assertDatabaseHas('piutang', [
            'pelanggan_id' => $pelanggan->id,
            'jumlah' => 222000,
            'status' => 'belum_lunas',
        ]);

        // Void synced dari Cloud -> kembalikan stok, batalkan piutang, dan posting reversal jurnal
        $voidRes = app(HybridSyncProcessorService::class)->process([
            'sync_uuid' => (string) Str::uuid(),
            'event_type' => 'transaksi.void',
            'source_node' => 'VPS-01',
            'payload' => [
                'no_transaksi' => 'VPS-KASBON-001',
                'offline_uuid' => $offlineUuid,
                'cabang_id' => $this->cabang->id,
                'void_alasan' => 'Order kasbon dibatalkan',
            ],
        ]);

        $this->assertTrue($voidRes['success']);
        $stok->refresh();
        $pelanggan->refresh();
        $this->assertEquals(20, (int) $stok->jumlah);
        $this->assertEquals(0, (float) $pelanggan->saldo_piutang);
        $this->assertDatabaseHas('transaksi', ['no_transaksi' => 'VPS-KASBON-001', 'status' => 'void']);
        $this->assertDatabaseHas('piutang', ['pelanggan_id' => $pelanggan->id, 'status' => 'batal']);
        $this->assertDatabaseHas('jurnal_header', ['sumber' => 'reversal']);
    }

    public function test_master_pk_collision_with_distinct_sync_uuid_does_not_overwrite(): void
    {
        $localBrand = Brand::create([
            'nama' => 'Local Exclusive Brand',
            'is_active' => true,
        ]);
        $localUuid = (string) Str::uuid();
        $localBrand->sync_uuid = $localUuid;
        $localBrand->saveQuietly();

        $cloudUuid = (string) Str::uuid();
        $res = app(HybridSyncProcessorService::class)->process([
            'sync_uuid' => (string) Str::uuid(),
            'event_type' => 'brand.upsert',
            'source_node' => 'CLOUD-01',
            'payload' => [
                'id' => $localBrand->id, // Kolisi PK persis!
                'sync_uuid' => $cloudUuid,
                'nama' => 'Cloud New Brand',
                'is_active' => true,
            ],
        ]);

        $this->assertTrue($res['success']);
        $this->assertDatabaseHas('brands', ['id' => $localBrand->id, 'nama' => 'Local Exclusive Brand']);
        $this->assertDatabaseHas('brands', ['sync_uuid' => $cloudUuid, 'nama' => 'Cloud New Brand']);
    }

    public function test_verify_sync_signature_enforces_replay_querystring_and_disabled_state(): void
    {
        $secret = config('sync.api_secret');

        // Stale timestamp (>300s) -> 401
        $staleTs = (string) (time() - 600);
        $resStale = $this->withHeaders([
            'X-Sync-Node' => 'STORE-01',
            'X-Sync-Timestamp' => $staleTs,
            'X-Sync-Signature' => hash_hmac('sha256', $staleTs.'.', $secret),
        ])->getJson('/api/internal/sync/pull');
        $resStale->assertStatus(401);

        // Tampered query string vs GET signature -> 401, valid query string -> 200
        $ts = (string) time();
        $resTamper = $this->withHeaders([
            'X-Sync-Node' => 'STORE-01',
            'X-Sync-Timestamp' => $ts,
            'X-Sync-Signature' => hash_hmac('sha256', $ts.'.limit=1', $secret),
        ])->getJson('/api/internal/sync/pull?limit=50');
        $resTamper->assertStatus(401);

        $resValidPull = $this->withHeaders([
            'X-Sync-Node' => 'STORE-01',
            'X-Sync-Timestamp' => $ts,
            'X-Sync-Signature' => hash_hmac('sha256', $ts.'.limit=5', $secret),
        ])->getJson('/api/internal/sync/pull?limit=5');
        $resValidPull->assertStatus(200);

        // sync.enabled=false blocks /pull (503) while /health stays 200
        config(['sync.enabled' => false]);
        $resDisabledPull = $this->withHeaders([
            'X-Sync-Node' => 'STORE-01',
            'X-Sync-Timestamp' => $ts,
            'X-Sync-Signature' => hash_hmac('sha256', $ts.'.', $secret),
        ])->getJson('/api/internal/sync/pull');
        $resDisabledPull->assertStatus(503);

        $resHealth = $this->withHeaders([
            'X-Sync-Node' => 'STORE-01',
            'X-Sync-Timestamp' => $ts,
            'X-Sync-Signature' => hash_hmac('sha256', $ts.'.', $secret),
        ])->getJson('/api/internal/sync/health');
        $resHealth->assertStatus(200);
    }

    public function test_sync_status_and_trigger_enforce_rbac_permission(): void
    {
        $this->actingAs($this->user)
            ->withSession(['cabang_id' => $this->cabang->id])
            ->getJson('/app/sync/status')
            ->assertStatus(403);

        $this->actingAs($this->user)
            ->withSession(['cabang_id' => $this->cabang->id])
            ->postJson('/app/sync/trigger')
            ->assertStatus(403);
    }

    public function test_cloud_storefront_never_pushes_outbox_and_stuck_processing_recovers(): void
    {
        Queue::fake();
        config(['sync.node_role' => 'cloud_storefront']);

        $brand = Brand::create(['nama' => 'Brand Cloud Side', 'is_active' => true]);
        app(HybridSyncOutboxService::class)->enqueue('brand.upsert', $brand, ['nama' => 'Brand Cloud Side']);
        Queue::assertNotPushed(PushHybridSyncJob::class);

        $this->artisan('sync:flush')
            ->expectsOutputToContain('hanya node primary_store')
            ->assertExitCode(0);

        // Stuck processing recovery & attempts >= 10 exclusion on primary_store
        config(['sync.node_role' => 'primary_store']);
        $stuck = HybridSyncOutbox::create([
            'sync_uuid' => (string) Str::uuid(),
            'event_type' => 'brand.upsert',
            'entity_type' => Brand::class,
            'entity_id' => $brand->id,
            'payload' => ['nama' => 'Stuck Recovery'],
            'status' => 'processing',
            'attempts' => 2,
        ]);
        DB::table('hybrid_sync_outbox')
            ->where('id', $stuck->id)
            ->update(['updated_at' => now()->subMinutes(10)]);

        $maxed = HybridSyncOutbox::create([
            'sync_uuid' => (string) Str::uuid(),
            'event_type' => 'brand.upsert',
            'entity_type' => Brand::class,
            'entity_id' => $brand->id,
            'payload' => ['nama' => 'Maxed Attempts'],
            'status' => 'failed',
            'attempts' => 10,
        ]);

        $this->artisan('sync:flush --batch')->assertExitCode(0);
        Queue::assertPushed(BatchPushHybridSyncJob::class, function ($job) use ($stuck, $maxed) {
            return in_array($stuck->id, $job->outboxIds, true)
                && ! in_array($maxed->id, $job->outboxIds, true);
        });

        // BatchPushHybridSyncJob must mark omitted outbox items as failed (not left processing)
        $omittedItem = HybridSyncOutbox::create([
            'sync_uuid' => (string) Str::uuid(),
            'event_type' => 'brand.upsert',
            'entity_type' => Brand::class,
            'entity_id' => $brand->id,
            'payload' => ['nama' => 'Omitted Batch Item'],
            'status' => 'pending',
            'attempts' => 0,
        ]);

        Http::fake([
            'https://cloud.bremspace.test/api/internal/sync/push-batch' => Http::response([
                'success' => true,
                'results' => [], // Response omits omittedItem uuid
            ], 200),
        ]);

        (new BatchPushHybridSyncJob([$omittedItem->id]))->handle();
        $omittedItem->refresh();
        $this->assertSame('failed', $omittedItem->status);
        $this->assertSame(1, (int) $omittedItem->attempts);
    }

    public function test_backoffice_harga_tier_konversi_barcode_jurnal_and_payroll_sync(): void
    {
        Queue::fake();

        $tier = TierMembership::create([
            'nama' => 'Gold',
            'kode' => 'GOLD',
            'min_belanja_12bulan' => 1000000,
            'diskon_persen' => 5,
            'urutan' => 2,
            'is_active' => true,
        ]);

        $produk = Produk::create([
            'nama' => 'Flex Backoffice',
            'slug' => 'flex-backoffice',
            'barcode' => 'BC-BO-01',
            'kategori' => 'Flexible',
            'kondisi' => 'baru',
            'satuan' => 'pcs',
            'harga_beli' => 15000,
            'harga_jual_retail' => 30000,
            'is_active' => true,
        ]);

        // 1. HargaTier observer & processor
        HargaTier::create([
            'produk_id' => $produk->id,
            'tier_membership_id' => $tier->id,
            'tipe_konsumen' => 'reseller',
            'is_reseller' => true,
            'harga' => 22000,
            'nominal_tetap' => 22000,
        ]);
        $this->assertDatabaseHas('hybrid_sync_outbox', ['event_type' => 'harga_tier.upsert']);

        // 2. ProdukSatuanKonversi observer & processor
        ProdukSatuanKonversi::create([
            'produk_id' => $produk->id,
            'satuan_kode' => 'box',
            'satuan_asal' => 'pcs',
            'pengali' => 10,
            'faktor_konversi' => 10,
            'harga_jual' => 250000,
            'is_satuan_jual' => true,
            'urutan' => 1,
        ]);
        $this->assertDatabaseHas('hybrid_sync_outbox', ['event_type' => 'produk_satuan_konversi.upsert']);

        // 3. ProdukBarcode observer & processor
        ProdukBarcode::create([
            'produk_id' => $produk->id,
            'barcode' => 'BC-ALT-BO-02',
            'label' => 'barcode_2',
            'urutan' => 2,
            'is_utama' => false,
        ]);
        $this->assertDatabaseHas('hybrid_sync_outbox', ['event_type' => 'produk_barcode.sync']);

        // 4. Standalone JurnalHeader manual -> proses di cloud
        $noJurnal = app(JurnalService::class)->generateNoJurnal('manual', $this->cabang->id);
        app(JurnalService::class)->post(
            noJurnal: $noJurnal,
            tanggal: now(),
            sumber: 'manual',
            lines: [
                ['akun_kode' => '610-01', 'debit' => 75000, 'kredit' => 0],
                ['akun_kode' => '110-04', 'debit' => 0, 'kredit' => 75000],
            ],
            deskripsi: 'Biaya listrik cabang',
            cabangId: $this->cabang->id
        );

        $jurnalOutbox = HybridSyncOutbox::where('event_type', 'jurnal.upsert')->first();
        $this->assertNotNull($jurnalOutbox);

        // Simulate receiving synced manual journal under fresh no_jurnal
        $resJurnal = app(HybridSyncProcessorService::class)->process([
            'sync_uuid' => (string) Str::uuid(),
            'event_type' => 'jurnal.upsert',
            'source_node' => 'STORE-01',
            'payload' => array_merge($jurnalOutbox->payload, [
                'sync_uuid' => (string) Str::uuid(),
                'no_jurnal' => 'JRL-MAN-SYNC-001',
                'idempotency_key' => 'sync-test-jrl-001',
            ]),
        ]);
        $this->assertTrue($resJurnal['success']);
        $this->assertDatabaseHas('jurnal_header', ['no_jurnal' => 'JRL-MAN-SYNC-001', 'sumber' => 'manual']);

        // 5. PayrollPeriode status selesai + slips -> sync to receiver
        $karyawan = Karyawan::create([
            'nik' => 'NIK-AUD-001',
            'nama' => 'Teknisi Audit',
            'email' => 'teknisi.audit@bremspace.id',
            'jabatan' => 'Teknisi',
            'cabang_id' => $this->cabang->id,
            'tgl_masuk' => '2026-01-01',
            'gaji_pokok' => 4000000,
            'status_aktif' => true,
        ]);

        $resPayroll = app(HybridSyncProcessorService::class)->process([
            'sync_uuid' => (string) Str::uuid(),
            'event_type' => 'payroll.upsert',
            'source_node' => 'STORE-01',
            'payload' => [
                'sync_uuid' => (string) Str::uuid(),
                'cabang_id' => $this->cabang->id,
                'periode' => '2026-11',
                'tanggal_mulai' => '2026-11-01',
                'tanggal_selesai' => '2026-11-30',
                'status' => 'selesai',
                'slips' => [
                    [
                        'karyawan_nik' => $karyawan->nik,
                        'gaji_pokok' => 4000000,
                        'total_tunjangan' => 500000,
                        'total_potongan' => 0,
                        'total_komisi' => 250000,
                        'total_gaji' => 4750000,
                        'rincian' => ['tunjangan' => 500000],
                        'status' => 'approved',
                    ],
                ],
            ],
        ]);
        $this->assertTrue($resPayroll['success']);
        $this->assertDatabaseHas('payroll_periode', ['periode' => '2026-11', 'status' => 'selesai']);
        $this->assertDatabaseHas('payroll_slip', ['karyawan_id' => $karyawan->id, 'total_gaji' => 4750000]);
    }

    public function test_frontend_sync_badge_and_settings_rbac_shared_probe_work(): void
    {
        $htmlDesktop = Blade::render('<x-prism.sync-badge />@stack(\'scripts\')');
        $htmlMobile = Blade::render('<x-prism.sync-badge :compact="true" class="shrink-0" />');

        $this->assertStringContainsString('hybridSyncBadge', $htmlDesktop);
        $this->assertStringContainsString('remoteConfigured: true', $htmlDesktop);
        $this->assertStringContainsString('window.showToast', $htmlDesktop);
        $this->assertStringContainsString('compact: true', $htmlMobile);

        Permission::firstOrCreate(['name' => 'pengaturan.manage', 'guard_name' => 'web']);
        $this->user->givePermissionTo('pengaturan.manage');
        $this->actingAs($this->user);

        Http::fake([
            'https://cloud.bremspace.test/api/internal/sync/health' => Http::response([
                'success' => true,
                'data' => [
                    'node_id' => 'CLOUD-VPS-01',
                    'node_role' => 'cloud_storefront',
                    'pending_count' => 3,
                ],
                'message' => 'Healthy',
            ], 200),
        ]);

        $component = app(SettingsRbac::class);
        $component->integrasiForm['sync_enabled'] = true;
        $component->integrasiForm['sync_node_role'] = 'primary_store';
        $component->integrasiForm['sync_node_id'] = 'STORE-01';
        $component->integrasiForm['sync_remote_url'] = 'https://cloud.bremspace.test';
        $component->integrasiForm['sync_api_secret'] = 'audit-secret-key-999';

        $component->testSyncConnection();

        $this->assertSame('success', $component->syncTestStatus);
        $this->assertTrue($component->syncStatus['connected']);
        $this->assertTrue($component->syncStatusData['cloud_connected']);
        $this->assertSame('CLOUD-VPS-01', $component->syncStatusData['cloud_node']);
    }
}
