<?php

namespace Tests\Feature;

use App\Console\Commands\SyncFlushCommand;
use App\Modules\Akunting\Models\AkunCOA;
use App\Modules\Rbac\Models\Cabang;
use App\Modules\Servis\Models\TiketServis;
use App\Modules\Sync\Jobs\PushHybridSyncJob;
use App\Modules\Sync\Models\HybridSyncInbox;
use App\Modules\Sync\Models\HybridSyncOutbox;
use App\Modules\Sync\Services\HybridSyncOutboxService;
use App\Modules\Wms\Models\Gudang;
use App\Modules\Wms\Models\Produk;
use App\Modules\Wms\Models\StokItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

        $this->cabang = Cabang::firstOrCreate(
            ['kode' => 'CBG-TEST-01'],
            ['nama' => 'Cabang Test', 'is_active' => true]
        );
    }

    /**
     * Helper to sign request.
     */
    protected function postSigned(string $uri, array $data, ?string $secret = null, ?string $node = null)
    {
        $secret = $secret ?? config('sync.api_secret');
        $node = $node ?? config('sync.node_id');
        $rawBody = json_encode($data);
        $signature = hash_hmac('sha256', $rawBody, $secret);

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
        $signature = hash_hmac('sha256', '', config('sync.api_secret'));

        $response = $this->withHeaders([
            'X-Sync-Node' => config('sync.node_id'),
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

        $signature = hash_hmac('sha256', '', config('sync.api_secret'));

        $resAuthorized = $this->withHeaders([
            'X-Sync-Node' => config('sync.node_id'),
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
}
