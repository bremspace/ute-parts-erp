<?php

namespace Tests\Feature;

use App\Console\Commands\SyncFlushCommand;
use App\Models\User;
use App\Modules\Akunting\Models\AkunCOA;
use App\Modules\Crm\Models\Pelanggan;
use App\Modules\Pos\Models\Transaksi;
use App\Modules\Pos\Models\TransaksiItem;
use App\Modules\Pos\Services\TransaksiSequenceService;
use App\Modules\Rbac\Models\Cabang;
use App\Modules\Servis\Models\TiketServis;
use App\Modules\Sync\Jobs\BatchPushHybridSyncJob;
use App\Modules\Sync\Jobs\PushHybridSyncJob;
use App\Modules\Sync\Models\HybridSyncInbox;
use App\Modules\Sync\Models\HybridSyncOutbox;
use App\Modules\Sync\Services\HybridSyncOutboxService;
use App\Modules\Sync\Services\HybridSyncProcessorService;
use App\Modules\Sync\Services\SyncExecutionGuard;
use App\Modules\Sync\SyncObserverBootstrap;
use App\Modules\Wms\Models\Gudang;
use App\Modules\Wms\Models\Produk;
use App\Modules\Wms\Models\StokItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

class HybridSyncTest extends TestCase
{
    use RefreshDatabase;

    protected $cabang;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'sync.enabled' => true,
            'sync.api_secret' => 'test-secret-key-12345',
            'sync.node_id' => 'STORE-TEST-01',
            'sync.node_role' => 'primary_store',
        ]);

        SyncObserverBootstrap::register();

        $this->cabang = Cabang::firstOrCreate(
            ['kode' => 'CBG-TEST-01'],
            ['nama' => 'Cabang Test', 'is_active' => true]
        );

        $this->user = User::firstOrCreate(
            ['email' => 'kasir@test.com'],
            ['name' => 'Kasir Test', 'password' => bcrypt('password')]
        );

        foreach ([
            ['110-04', 'Kas Toko', 'aset', 'kas', 'debit'],
            ['410-01', 'Pendapatan Penjualan', 'pendapatan', 'pendapatan_penjualan', 'kredit'],
            ['510-02', 'HPP', 'beban', 'hpp', 'debit'],
            ['130-01', 'Persediaan', 'aset', 'persediaan', 'debit'],
        ] as [$kode, $nama, $tipe, $kelompok, $saldoNormal]) {
            AkunCOA::firstOrCreate(
                ['kode' => $kode],
                [
                    'nama' => $nama,
                    'tipe' => $tipe,
                    'kelompok' => $kelompok,
                    'saldo_normal' => $saldoNormal,
                    'is_active' => true,
                ]
            );
        }
    }

    /**
     * Helper to sign request.
     */
    protected function postSigned(string $uri, array $data, ?string $secret = null, ?string $node = null)
    {
        $secret = $secret ?? config('sync.api_secret');
        $node = $node ?? config('sync.node_id');
        $rawBody = json_encode($data);
        $timestamp = (string) time();
        $signature = hash_hmac('sha256', $timestamp.'.'.$rawBody, $secret);

        return $this->call(
            'POST',
            $uri,
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
                'HTTP_X_SYNC_NODE' => $node,
                'HTTP_X_SYNC_TIMESTAMP' => $timestamp,
                'HTTP_X_SYNC_SIGNATURE' => $signature,
            ],
            $rawBody
        );
    }

    /**
     * 1. Validasi HMAC signature menolak tanpa signature (401).
     */
    public function test_rejects_request_without_signature(): void
    {
        $response = $this->postJson('/api/internal/sync/push', [
            'sync_uuid' => (string) Str::uuid(),
            'event_type' => 'stok.mutasi',
        ]);

        $response->assertStatus(401);
        $response->assertJson([
            'success' => false,
            'message' => 'Header autentikasi sync tidak lengkap.',
        ]);
    }

    /**
     * 1b. Validasi HMAC signature menolak jika signature salah (401).
     */
    public function test_rejects_request_with_invalid_signature(): void
    {
        $data = [
            'sync_uuid' => (string) Str::uuid(),
            'event_type' => 'stok.mutasi',
            'source_node' => 'STORE-REMOTE',
            'payload' => ['stok' => 10],
        ];

        $response = $this->call(
            'POST',
            '/api/internal/sync/push',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
                'HTTP_X_SYNC_NODE' => 'STORE-REMOTE',
                'HTTP_X_SYNC_TIMESTAMP' => (string) time(),
                'HTTP_X_SYNC_SIGNATURE' => 'signature-palsu-ngawur',
            ],
            json_encode($data)
        );

        $response->assertStatus(401);
        $response->assertJson([
            'success' => false,
            'message' => 'Tanda tangan tidak valid.',
        ]);
    }

    /**
     * 1c. Menerima request dengan signature yang valid (200).
     */
    public function test_accepts_request_with_valid_signature(): void
    {
        $data = [
            'sync_uuid' => (string) Str::uuid(),
            'event_type' => 'stok.mutasi',
            'source_node' => 'STORE-01',
            'payload' => ['produk_id' => 1, 'cabang_id' => 1, 'stok_akhir' => 100],
        ];

        $response = $this->postSigned('/api/internal/sync/push', $data);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);
    }

    /**
     * 2. Idempotency: sync_uuid yang sama dikirim 2 kali hanya diproses 1 kali.
     */
    public function test_idempotency_same_sync_uuid_processed_only_once(): void
    {
        $uuid = (string) Str::uuid();
        $data = [
            'sync_uuid' => $uuid,
            'event_type' => 'stok.mutasi',
            'source_node' => 'STORE-01',
            'payload' => ['produk_id' => 999, 'cabang_id' => 1, 'stok_akhir' => 50],
        ];

        // Pengiriman pertama
        $res1 = $this->postSigned('/api/internal/sync/push', $data);
        $res1->assertStatus(200);
        $this->assertEquals(1, HybridSyncInbox::where('sync_uuid', $uuid)->count());

        // Pengiriman kedua (koneksi retry)
        $res2 = $this->postSigned('/api/internal/sync/push', $data);
        $res2->assertStatus(200);
        $res2->assertJsonFragment([
            'message' => 'Sudah diproses sebelumnya (idempotent skip).',
        ]);

        // Pastikan record inbox tetap hanya 1
        $this->assertEquals(1, HybridSyncInbox::where('sync_uuid', $uuid)->count());
    }

    /**
     * 3. Simulasi alur primary_store -> cloud_storefront (update stok & status servis).
     */
    public function test_simulation_primary_to_cloud_sync(): void
    {
        // Set mode node sebagai cloud_storefront
        config(['sync.node_role' => 'cloud_storefront']);

        // A. Event produk.upsert & stok.mutasi
        $uuidStok = (string) Str::uuid();
        $dataStok = [
            'sync_uuid' => $uuidStok,
            'event_type' => 'stok.mutasi',
            'source_node' => 'PRIMARY-STORE-01',
            'payload' => [
                'produk_id' => 10,
                'cabang_id' => 1,
                'stok_akhir' => 77,
            ],
        ];

        $resStok = $this->postSigned('/api/internal/sync/push', $dataStok);
        $resStok->assertStatus(200);

        // Verifikasi stok item ter-update di database
        $this->assertDatabaseHas('stok_items', [
            'produk_id' => 10,
            'jumlah' => 77,
        ]);

        // B. Event servis.update
        $tiket = TiketServis::create([
            'cabang_id' => 1,
            'no_tiket' => 'SRV-TEST-001',
            'jenis_hp' => 'iPhone 13',
            'status' => 'diterima',
            'keluhan' => 'Layar retak',
        ]);

        $uuidServis = (string) Str::uuid();
        $dataServis = [
            'sync_uuid' => $uuidServis,
            'event_type' => 'servis.update',
            'source_node' => 'PRIMARY-STORE-01',
            'payload' => [
                'id' => $tiket->id,
                'no_tiket' => 'SRV-TEST-001',
                'status' => 'selesai',
                'catatan_teknisi' => 'LCD berhasil diganti',
            ],
        ];

        $resServis = $this->postSigned('/api/internal/sync/push', $dataServis);
        $resServis->assertStatus(200);

        // Verifikasi tiket servis terupdate
        $this->assertDatabaseHas('tiket_servis', [
            'id' => $tiket->id,
            'status' => 'selesai',
            'catatan_admin' => 'LCD berhasil diganti',
        ]);
    }

    /**
     * 4. Simulasi alur cloud_storefront -> primary_store (order online lunas masuk ke lokal).
     */
    public function test_simulation_cloud_to_primary_store_order_paid(): void
    {
        // Set mode node sebagai primary_store
        config(['sync.node_role' => 'primary_store']);

        // [C4] Produk & stok lokal wajib ada (tidak ada lagi auto-create produk
        // harga_beli=0) + COA untuk jurnal pendapatan/HPP.
        foreach ([
            ['120-02', 'Kliring Gateway', 'aset', 'kas', 'debit'],
            ['410-03', 'Pendapatan Online', 'pendapatan', 'pendapatan_penjualan', 'kredit'],
            ['510-02', 'HPP', 'beban', 'hpp', 'debit'],
            ['130-01', 'Persediaan', 'aset', 'persediaan', 'debit'],
        ] as [$kode, $nama, $tipe, $kelompok, $saldo]) {
            AkunCOA::firstOrCreate(['kode' => $kode], [
                'nama' => $nama, 'tipe' => $tipe, 'kelompok' => $kelompok, 'saldo_normal' => $saldo, 'is_active' => true,
            ]);
        }
        $gudang = Gudang::create([
            'cabang_id' => $this->cabang->id, 'nama' => 'Gudang Utama', 'kode' => 'GDG-SYNC', 'is_active' => true, 'is_default' => true,
        ]);
        $produk = Produk::create([
            'nama' => 'Produk Online Sync', 'slug' => 'produk-online-sync', 'kategori' => 'Umum', 'kondisi' => 'baru',
            'satuan' => 'pcs', 'harga_beli' => 100000, 'harga_jual_retail' => 125000,
        ]);
        StokItem::create([
            'produk_id' => $produk->id, 'gudang_id' => $gudang->id, 'jumlah' => 10, 'jumlah_minimum' => 1,
        ]);

        $uuidOrder = (string) Str::uuid();
        $dataOrder = [
            'sync_uuid' => $uuidOrder,
            'event_type' => 'order.online_paid',
            'source_node' => 'CLOUD-VPS',
            'payload' => [
                'order_id' => 555,
                'nomor_order' => 'ORDER-DUITKU-555',
                'cabang_id' => $this->cabang->id,
                'total' => 250000,
                'items' => [
                    ['produk_id' => $produk->id, 'qty' => 2, 'harga' => 125000],
                ],
            ],
        ];

        $resOrder = $this->postSigned('/api/internal/sync/push', $dataOrder);
        $resOrder->assertStatus(200);
        $resOrder->assertJson(['success' => true]);

        // Verifikasi transaksi tercatat di tabel transaksi lokal dengan status lunas (siap dikemas)
        $this->assertDatabaseHas('transaksi', [
            'no_transaksi' => 'ORDER-DUITKU-555',
            'cabang_id' => $this->cabang->id,
            'total_akhir' => 250000,
            'status' => 'lunas',
        ]);

        // [C4] Item memakai satuan dasar produk + konversi 1 (payload tanpa satuan),
        // hpp fallback harga_beli lokal, stok terpotong, jurnal HPP ada.
        $this->assertDatabaseHas('transaksi_item', [
            'produk_id' => $produk->id, 'jumlah' => 2, 'satuan' => 'pcs', 'konversi' => 1, 'hpp' => 100000,
        ]);
        $this->assertDatabaseHas('stok_items', ['produk_id' => $produk->id, 'jumlah' => 8]);
        $this->assertDatabaseHas('jurnal_akuntansi', ['debit' => 200000]); // Dr 510-02 HPP 2x100.000

        // Verifikasi catatan inbox tersimpan dengan status processed
        $this->assertDatabaseHas('hybrid_sync_inbox', [
            'sync_uuid' => $uuidOrder,
            'status' => 'processed',
        ]);
    }

    /**
     * 5. Outbox enqueue menyimpan record ke database dan mendispatch job.
     */
    public function test_outbox_enqueue_creates_database_record_and_dispatches_job(): void
    {
        Queue::fake();
        config(['sync.remote_url' => 'https://cloud.bremspace.test']);

        $produk = new Produk;
        $produk->id = 88;
        $produk->cabang_id = 1;

        $service = app(HybridSyncOutboxService::class);
        $outbox = $service->enqueue('stok.mutasi', $produk, ['jumlah' => 10], 1);

        $this->assertNotNull($outbox);
        $this->assertDatabaseHas('hybrid_sync_outbox', [
            'id' => $outbox->id,
            'event_type' => 'stok.mutasi',
            'status' => 'pending',
            'cabang_id' => 1,
        ]);

        Queue::assertPushed(PushHybridSyncJob::class);
    }

    /**
     * 6. Health endpoint mengembalikan informasi status node.
     */
    public function test_health_endpoint_returns_node_info(): void
    {
        $timestamp = (string) time();
        $signature = hash_hmac('sha256', $timestamp.'.', config('sync.api_secret'));

        $response = $this->withHeaders([
            'X-Sync-Node' => config('sync.node_id'),
            'X-Sync-Timestamp' => $timestamp,
            'X-Sync-Signature' => $signature,
        ])->getJson('/api/internal/sync/health');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data' => [
                'node_id',
                'node_role',
                'sync_enabled',
                'pending_count',
                'last_synced_at',
                'server_time',
            ],
            'message',
        ]);
    }

    /**
     * 7. Pull endpoint memerlukan signature valid dan mengembalikan data outbox.
     */
    public function test_pull_endpoint_requires_signature_and_returns_data(): void
    {
        // Tanpa signature ditolak 401
        $resUnauthorized = $this->getJson('/api/internal/sync/pull');
        $resUnauthorized->assertStatus(401);

        // Buat dummy outbox pending
        HybridSyncOutbox::create([
            'sync_uuid' => (string) Str::uuid(),
            'event_type' => 'stok.mutasi',
            'entity_type' => Produk::class,
            'entity_id' => 1,
            'cabang_id' => 1,
            'payload' => ['stok' => 5],
            'status' => 'pending',
        ]);

        $timestamp = (string) time();
        $signature = hash_hmac('sha256', $timestamp.'.', config('sync.api_secret'));

        $resAuthorized = $this->withHeaders([
            'X-Sync-Node' => config('sync.node_id'),
            'X-Sync-Timestamp' => $timestamp,
            'X-Sync-Signature' => $signature,
        ])->getJson('/api/internal/sync/pull');

        $resAuthorized->assertStatus(200);
        $resAuthorized->assertJson(['success' => true]);
        $this->assertNotEmpty($resAuthorized->json('data'));
    }

    /**
     * 8. SyncFlushCommand mendispatch job untuk outbox pending/failed.
     */
    public function test_sync_flush_command_dispatches_jobs(): void
    {
        Queue::fake();
        HybridSyncOutbox::query()->delete();
        config(['sync.remote_url' => 'https://cloud.bremspace.test']);

        HybridSyncOutbox::query()->delete();

        $item = HybridSyncOutbox::create([
            'sync_uuid' => (string) Str::uuid(),
            'event_type' => 'produk.upsert',
            'entity_type' => Produk::class,
            'entity_id' => 1,
            'cabang_id' => 1,
            'payload' => ['nama' => 'Ban Motor'],
            'status' => 'failed',
            'attempts' => 1,
        ]);

        $this->artisan('sync:flush')
            ->expectsOutputToContain('Ditemukan 1 item outbox')
            ->assertExitCode(0);

        Queue::assertPushed(PushHybridSyncJob::class);
    }

    /**
     * 9. T-01: SyncExecutionGuard mencegah outbox enqueue (Anti-Echo Loop).
     */
    public function test_sync_execution_guard_suppresses_outbox_enqueue(): void
    {
        Queue::fake();

        SyncExecutionGuard::runWithoutSync(function () {
            $produk = Produk::create([
                'nama' => 'Kampas Rem Anti Echo',
                'slug' => 'kampas-rem-anti-echo',
                'harga_jual_retail' => 50000,
                'harga_beli' => 30000,
                'is_active' => true,
            ]);

            $this->assertDatabaseMissing('hybrid_sync_outbox', [
                'entity_id' => $produk->id,
                'event_type' => 'produk.upsert',
            ]);
        });
    }

    /**
     * 10. T-02: TransaksiPosSyncObserver membuat antrean outbox saat transaksi kasir dibuat.
     */
    public function test_transaksi_pos_sync_observer_enqueues_outbox(): void
    {
        Queue::fake();
        config(['sync.node_role' => 'primary_store']);

        $produk = Produk::create([
            'nama' => 'Oli Gear Test',
            'slug' => 'oli-gear-test',
            'harga_jual_retail' => 50000,
            'harga_beli' => 30000,
            'is_active' => true,
        ]);

        $transaksi = Transaksi::create([
            'no_transaksi' => 'POS-CB01-202611-0001',
            'cabang_id' => $this->cabang->id,
            'kasir_id' => $this->user->id,
            'sumber' => 'pos',
            'subtotal' => 100000,
            'total_akhir' => 100000,
            'metode_bayar' => 'tunai',
            'jumlah_bayar' => 100000,
            'kembalian' => 0,
            'status' => 'selesai',
        ]);

        TransaksiItem::create([
            'transaksi_id' => $transaksi->id,
            'produk_id' => $produk->id,
            'jumlah' => 2,
            'satuan' => 'pcs',
            'konversi' => 1,
            'harga_satuan' => 50000,
            'subtotal' => 100000,
            'hpp' => 30000,
        ]);

        $this->assertDatabaseHas('hybrid_sync_outbox', [
            'event_type' => 'transaksi.pos',
            'entity_id' => $transaksi->id,
        ]);
    }

    /**
     * 11. T-03: Pull endpoint pada node cloud mengembalikan master data dan pesanan online.
     */
    public function test_pull_endpoint_on_cloud_storefront_returns_master_data_and_orders(): void
    {
        config(['sync.node_role' => 'cloud_storefront']);

        HybridSyncOutbox::create([
            'sync_uuid' => (string) Str::uuid(),
            'event_type' => 'produk.upsert',
            'entity_type' => Produk::class,
            'entity_id' => 99,
            'cabang_id' => $this->cabang->id,
            'payload' => ['nama' => 'Oli Mesin Cloud'],
            'status' => 'pending',
        ]);

        HybridSyncOutbox::create([
            'sync_uuid' => (string) Str::uuid(),
            'event_type' => 'order.online_paid',
            'entity_type' => Produk::class,
            'entity_id' => 100,
            'cabang_id' => $this->cabang->id,
            'payload' => ['nomor_order' => 'ONLINE-001'],
            'status' => 'pending',
        ]);

        $timestamp = (string) time();
        $signature = hash_hmac('sha256', $timestamp.'.', config('sync.api_secret'));

        $res = $this->withHeaders([
            'X-Sync-Node' => config('sync.node_id'),
            'X-Sync-Timestamp' => $timestamp,
            'X-Sync-Signature' => $signature,
        ])->getJson('/api/internal/sync/pull');

        $res->assertStatus(200);
        $eventTypes = collect($res->json('data'))->pluck('event_type')->all();
        $this->assertContains('produk.upsert', $eventTypes);
        $this->assertContains('order.online_paid', $eventTypes);
    }

    /**
     * 12. T-04: handleTransaksiPos menyimpan transaksi, item, dan membuat jurnal akuntansi di cloud.
     */
    public function test_handle_transaksi_pos_creates_transaction_items_and_journal_on_cloud(): void
    {
        config(['sync.node_role' => 'cloud_storefront']);

        $produk = Produk::create([
            'nama' => 'Ban Luar Test',
            'slug' => 'ban-luar-test',
            'harga_jual_retail' => 50000,
            'harga_beli' => 30000,
            'is_active' => true,
        ]);

        $uuid = (string) Str::uuid();
        $payload = [
            'no_transaksi' => 'POS-CB01-9999',
            'offline_uuid' => $uuid,
            'cabang_id' => $this->cabang->id,
            'kasir_id' => $this->user->id,
            'sumber' => 'pos',
            'subtotal' => 150000,
            'total_akhir' => 150000,
            'metode_bayar' => 'tunai',
            'jumlah_bayar' => 150000,
            'kembalian' => 0,
            'items' => [
                [
                    'produk_id' => $produk->id,
                    'jumlah' => 3,
                    'satuan' => 'pcs',
                    'konversi' => 1,
                    'harga_satuan' => 50000,
                    'subtotal' => 150000,
                    'hpp' => 30000,
                ],
            ],
            'created_at' => now()->toIso8601String(),
        ];

        $res = $this->postSigned('/api/internal/sync/push', [
            'sync_uuid' => (string) Str::uuid(),
            'event_type' => 'transaksi.pos',
            'source_node' => 'STORE-01',
            'payload' => $payload,
        ]);

        $res->assertStatus(200);

        // Verifikasi transaksi tersimpan
        $this->assertDatabaseHas('transaksi', [
            'no_transaksi' => 'POS-CB01-9999',
            'offline_uuid' => $uuid,
            'total_akhir' => 150000,
        ]);

        // Verifikasi item tersimpan
        $this->assertDatabaseHas('transaksi_item', [
            'harga_satuan' => 50000,
            'jumlah' => 3,
        ]);
    }

    /**
     * 13. T-05: handleStokMutasi menggunakan mutasi delta (increment/decrement), bukan overwrite mutlak.
     */
    public function test_handle_stok_mutasi_uses_delta_increment(): void
    {
        config(['sync.node_role' => 'cloud_storefront']);

        $produk = Produk::create([
            'nama' => 'Busi Iridium Delta',
            'slug' => 'busi-iridium-delta',
            'harga_jual_retail' => 45000,
            'harga_beli' => 25000,
            'is_active' => true,
        ]);

        $gudang = Gudang::create([
            'cabang_id' => $this->cabang->id,
            'nama' => 'Gudang Utama Test',
            'kode' => 'GDG-TEST',
            'is_default' => true,
            'is_active' => true,
        ]);

        // Set stok awal 10 unit
        $stokItem = StokItem::create([
            'produk_id' => $produk->id,
            'gudang_id' => $gudang->id,
            'jumlah' => 10,
        ]);

        // Kirim mutasi delta: penjualan 3 unit (jumlah = -3)
        $res = $this->postSigned('/api/internal/sync/push', [
            'sync_uuid' => (string) Str::uuid(),
            'event_type' => 'stok.mutasi',
            'source_node' => 'STORE-01',
            'payload' => [
                'produk_id' => $produk->id,
                'gudang_id' => $gudang->id,
                'cabang_id' => $this->cabang->id,
                'perubahan' => -3,
                'stok_akhir' => 7,
            ],
        ]);

        $res->assertStatus(200);

        // Stok awal 10 - 3 = 7
        $stokItem->refresh();
        $this->assertEquals(7, (float) $stokItem->jumlah);
    }

    /**
     * 14. T-06: Endpoint push-batch memproses banyak event dalam 1 HTTP request.
     */
    public function test_push_batch_endpoint_processes_multiple_events_in_single_request(): void
    {
        config(['sync.node_role' => 'cloud_storefront']);

        $uuid1 = (string) Str::uuid();
        $uuid2 = (string) Str::uuid();

        $events = [
            [
                'sync_uuid' => $uuid1,
                'event_type' => 'produk.upsert',
                'source_node' => 'STORE-01',
                'payload' => [
                    'nama' => 'Gear Set Batch',
                    'slug' => 'gear-set-batch',
                    'harga_jual_retail' => 120000,
                    'harga_beli' => 80000,
                    'is_active' => true,
                ],
            ],
            [
                'sync_uuid' => $uuid2,
                'event_type' => 'produk.upsert',
                'source_node' => 'STORE-01',
                'payload' => [
                    'nama' => 'Rantai Roda Batch',
                    'slug' => 'rantai-roda-batch',
                    'harga_jual_retail' => 65000,
                    'harga_beli' => 40000,
                    'is_active' => true,
                ],
            ],
        ];

        $res = $this->postSigned('/api/internal/sync/push-batch', ['events' => $events]);

        $res->assertStatus(200);
        $res->assertJson([
            'success' => true,
            'data' => [
                'total' => 2,
                'processed' => 2,
                'failed' => 0,
            ],
        ]);

        $this->assertDatabaseHas('produk', ['nama' => 'Gear Set Batch']);
        $this->assertDatabaseHas('produk', ['nama' => 'Rantai Roda Batch']);
    }

    /**
     * 15. T-07: BatchPushHybridSyncJob membundel outbox pending ke endpoint push-batch.
     */
    public function test_batch_push_hybrid_sync_job_executes_batch_http_request(): void
    {
        config([
            'sync.remote_url' => 'https://vps.bremspace.test',
            'sync.enabled' => true,
        ]);

        $item1 = HybridSyncOutbox::create([
            'sync_uuid' => (string) Str::uuid(),
            'event_type' => 'produk.upsert',
            'entity_type' => Produk::class,
            'entity_id' => 1,
            'cabang_id' => $this->cabang->id,
            'payload' => ['nama' => 'Produk Batch 1'],
            'status' => 'pending',
        ]);

        $item2 = HybridSyncOutbox::create([
            'sync_uuid' => (string) Str::uuid(),
            'event_type' => 'produk.upsert',
            'entity_type' => Produk::class,
            'entity_id' => 2,
            'cabang_id' => $this->cabang->id,
            'payload' => ['nama' => 'Produk Batch 2'],
            'status' => 'pending',
        ]);

        Http::fake([
            'https://vps.bremspace.test/api/internal/sync/push-batch' => Http::response([
                'success' => true,
                'data' => [
                    'total' => 2,
                    'processed' => 2,
                    'failed' => 0,
                    'results' => [
                        ['sync_uuid' => $item1->sync_uuid, 'success' => true],
                        ['sync_uuid' => $item2->sync_uuid, 'success' => true],
                    ],
                ],
            ], 200),
        ]);

        $job = new BatchPushHybridSyncJob([$item1->id, $item2->id]);
        $job->handle();

        $item1->refresh();
        $item2->refresh();

        $this->assertEquals('synced', $item1->status);
        $this->assertEquals('synced', $item2->status);
    }

    /**
     * 16. T-08 & T-09: sync:flush dengan opsi --batch mendispatch BatchPushHybridSyncJob.
     */
    public function test_sync_flush_command_with_batch_option_dispatches_batch_job(): void
    {
        Queue::fake();
        config(['sync.remote_url' => 'https://cloud.bremspace.test']);

        $item = HybridSyncOutbox::create([
            'sync_uuid' => (string) Str::uuid(),
            'event_type' => 'produk.upsert',
            'entity_type' => Produk::class,
            'entity_id' => 10,
            'cabang_id' => $this->cabang->id,
            'payload' => ['nama' => 'Ban Dalam Batch'],
            'status' => 'pending',
        ]);

        $this->artisan('sync:flush --batch')
            ->expectsOutputToContain('BatchPushHybridSyncJob')
            ->assertExitCode(0);

        Queue::assertPushed(BatchPushHybridSyncJob::class);
    }

    /**
     * 17. T-08: Command sync:pull berjalan dengan opsi limit dan cabang_id dinamis.
     */
    public function test_sync_pull_command_with_dynamic_options(): void
    {
        config([
            'sync.remote_url' => 'https://vps.bremspace.test',
            'sync.node_role' => 'primary_store',
        ]);

        $uuid = (string) Str::uuid();

        Http::fake([
            'https://vps.bremspace.test/api/internal/sync/pull*' => Http::response([
                'success' => true,
                'data' => [
                    [
                        'sync_uuid' => $uuid,
                        'event_type' => 'produk.upsert',
                        'source_node' => 'VPS-01',
                        'payload' => [
                            'nama' => 'Oli Dynamic Pull',
                            'slug' => 'oli-dynamic-pull',
                            'harga_jual_retail' => 55000,
                            'harga_beli' => 35000,
                            'is_active' => true,
                        ],
                    ],
                ],
            ], 200),
            'https://vps.bremspace.test/api/internal/sync/ack' => Http::response([
                'success' => true,
            ], 200),
        ]);

        $this->artisan('sync:pull --limit=10 --cabang_id='.$this->cabang->id)
            ->expectsOutputToContain('Sinkronisasi pull selesai: 1 diproses, 0 gagal.')
            ->assertExitCode(0);

        $this->assertDatabaseHas('produk', ['nama' => 'Oli Dynamic Pull']);
    }

    /**
     * 18. T-10 s.d T-13: Simulasi failover penuh saat PC toko mati, transaksi di cloud diserap ke toko lokal.
     */
    public function test_failover_simulation_cloud_pos_to_local_store_reconciliation(): void
    {
        // 1. Fase Bencana: Kasir jualan di Cloud VPS saat PC toko offline
        config(['sync.node_role' => 'cloud_storefront']);
        SyncObserverBootstrap::register();

        $produk = Produk::create([
            'nama' => 'Kampas Ganda Failover',
            'slug' => 'kampas-ganda-failover',
            'harga_jual_retail' => 150000,
            'harga_beli' => 90000,
            'is_active' => true,
        ]);

        $gudang = Gudang::create([
            'cabang_id' => $this->cabang->id,
            'nama' => 'Gudang Cabang Failover',
            'kode' => 'GDG-FO-01',
            'is_default' => true,
            'is_active' => true,
        ]);

        // Stok fisik lokal awal: 10 unit
        $stokLokal = StokItem::create([
            'produk_id' => $produk->id,
            'gudang_id' => $gudang->id,
            'jumlah' => 10,
        ]);

        // Cek penomoran faktur otomatis menghasilkan prefix VPS di cloud
        $sequenceService = new TransaksiSequenceService;
        $noTransaksiCloud = $sequenceService->generateNoTransaksi($this->cabang->id);
        $this->assertStringStartsWith('VPS-', $noTransaksiCloud);

        // Kasir checkout 2 unit di Cloud VPS dalam transaksi database atomik
        $transaksiCloud = DB::transaction(function () use ($noTransaksiCloud, $gudang, $produk) {
            $tx = Transaksi::create([
                'no_transaksi' => $noTransaksiCloud,
                'cabang_id' => $this->cabang->id,
                'kasir_id' => $this->user->id,
                'gudang_id' => $gudang->id,
                'sumber' => 'pos',
                'subtotal' => 300000,
                'total_akhir' => 300000,
                'metode_bayar' => 'tunai',
                'jumlah_bayar' => 300000,
                'kembalian' => 0,
                'status' => 'selesai',
            ]);

            TransaksiItem::create([
                'transaksi_id' => $tx->id,
                'produk_id' => $produk->id,
                'jumlah' => 2,
                'satuan' => 'pcs',
                'konversi' => 1,
                'harga_satuan' => 150000,
                'subtotal' => 300000,
                'hpp' => 90000,
            ]);

            return $tx;
        });

        // Buktikan event transaksi.pos_cloud masuk ke outbox cloud
        $this->assertDatabaseHas('hybrid_sync_outbox', [
            'event_type' => 'transaksi.pos_cloud',
            'entity_id' => $transaksiCloud->id,
        ]);

        // 2. Fase Pemulihan: PC toko hidup kembali, berganti peran ke primary_store dan menarik data (pull)
        config(['sync.node_role' => 'primary_store']);

        $outboxCloud = HybridSyncOutbox::where('event_type', 'transaksi.pos_cloud')->first();

        // Hapus transaksi cloud dari context untuk mensimulasikan database lokal yang belum punya transaksi ini
        $transaksiCloud->items()->delete();
        $transaksiCloud->delete();

        // Proses payload failover di node lokal
        $processor = app(HybridSyncProcessorService::class);
        $result = $processor->process([
            'sync_uuid' => $outboxCloud->sync_uuid,
            'event_type' => 'transaksi.pos_cloud',
            'source_node' => 'VPS-01',
            'payload' => $outboxCloud->payload,
        ]);

        $this->assertTrue($result['success']);

        // 3. Verifikasi Konsistensi Database Lokal
        // a) Transaksi darurat terserap ke database lokal
        $this->assertDatabaseHas('transaksi', [
            'no_transaksi' => $noTransaksiCloud,
            'total_akhir' => 300000,
        ]);

        // b) Stok lokal berkurang dari 10 - 2 = 8 unit
        $stokLokal->refresh();
        $this->assertEquals(8, (float) $stokLokal->jumlah);

        // c) Jurnal POS failover terbentuk di lokal
        $this->assertDatabaseHas('jurnal_header', [
            'sumber' => 'pos',
            'cabang_id' => $this->cabang->id,
        ]);
    }

    /**
     * Regresi: matcher closure pada resolveForeignId() dulu diabaikan karena
     * dianggap array. Akibatnya query tanpa WHERE -> pelanggan pertama terpilih.
     */
    public function test_servis_update_memilih_pelanggan_berdasarkan_telepon_bukan_yang_pertama(): void
    {
        Pelanggan::create(['nama' => 'Pelanggan Pertama', 'telepon' => '6281100000001']);
        $target = Pelanggan::create(['nama' => 'Pelanggan Tujuan', 'telepon' => '6281100000002']);

        $processor = app(HybridSyncProcessorService::class);
        $result = $processor->process([
            'sync_uuid' => (string) Str::uuid(),
            'event_type' => 'servis.update',
            'source_node' => 'CLOUD-VPS',
            'payload' => [
                'no_tiket' => 'SRV-SYNC-TEL-01',
                'cabang_id' => $this->cabang->id,
                'nama_pelanggan' => 'Pelanggan Tujuan',
                // Kunci yang dipakai TiketServisSyncObserver + format lokal,
                // harus tetap ketemu lewat kandidat normalisasi.
                'pelanggan_telepon' => '081100000002',
                'telepon_pelanggan' => '081100000002',
                'jenis_hp' => 'iPhone 13',
                'keluhan' => 'Tes resolusi pelanggan',
                'status' => 'diterima',
                'token_approval' => 'token-sync-tel-01',
            ],
        ]);

        $this->assertTrue($result['success']);
        $this->assertDatabaseHas('tiket_servis', [
            'no_tiket' => 'SRV-SYNC-TEL-01',
            'pelanggan_id' => $target->id,
        ]);
    }
}
