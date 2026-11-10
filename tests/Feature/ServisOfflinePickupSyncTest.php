<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Rbac\Models\Cabang;
use App\Modules\Servis\Models\TiketServis;
use App\Modules\Wms\Models\Gudang;
use Database\Seeders\AkunCoaSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ServisOfflinePickupSyncTest extends TestCase
{
    use RefreshDatabase;

    protected Cabang $cabang;

    protected Gudang $gudang;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(AkunCoaSeeder::class);

        $this->cabang = Cabang::create([
            'nama' => 'Cabang Servis Offline',
            'kode' => 'CBG-SRV-OFF',
            'alamat' => 'Jl. Teknisi No. 1',
            'telepon' => '081211112222',
        ]);

        $this->gudang = Gudang::create([
            'kode' => 'GDG-SRV-01',
            'nama' => 'Gudang Servis',
            'cabang_id' => $this->cabang->id,
            'is_utama' => true,
        ]);

        $this->user = User::factory()->create();
        $this->user->assignRole('admin-toko');
        $this->user->cabangs()->attach($this->cabang->id);
    }

    public function test_active_offline_tickets_endpoint_returns_ready_tickets(): void
    {
        $tiket = TiketServis::create([
            'no_tiket' => 'SRV-TEST-001',
            'cabang_id' => $this->cabang->id,
            'nama_pelanggan' => 'Budi Servis',
            'telepon_pelanggan' => '081234567890',
            'jenis_hp' => 'iPhone 13',
            'keluhan' => 'Layar sentuh bergaris',
            'status' => 'selesai',
            'status_pembayaran' => 'belum_lunas',
            'estimasi_biaya' => 150000,
            'tanggal_terima' => now()->subDays(2),
            'tanggal_selesai' => now()->subHours(2),
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->withSession(['cabang_id' => $this->cabang->id])
            ->getJson('/api/servis/active-offline?cabang_id='.$this->cabang->id);

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonFragment([
            'no_tiket' => 'SRV-TEST-001',
            'nama_pelanggan' => 'Budi Servis',
            'status' => 'selesai',
        ]);
    }

    public function test_sync_pickup_offline_processes_and_transitions_to_diambil(): void
    {
        $tiket = TiketServis::create([
            'no_tiket' => 'SRV-TEST-002',
            'cabang_id' => $this->cabang->id,
            'nama_pelanggan' => 'Andi Santoso',
            'telepon_pelanggan' => '081298765432',
            'jenis_hp' => 'Samsung S22',
            'keluhan' => 'Baterai drop cepat panas',
            'status' => 'selesai',
            'status_pembayaran' => 'belum_lunas',
            'estimasi_biaya' => 200000,
            'tanggal_terima' => now()->subDays(1),
            'tanggal_selesai' => now()->subHours(1),
        ]);

        $uuid = (string) Str::uuid();

        $payload = [
            'client_batch_id' => 'BATCH-PICKUP-001',
            'pickups' => [
                [
                    'pickup_uuid' => $uuid,
                    'no_tiket' => $tiket->no_tiket,
                    'nama_pengambil' => 'Andi Sendiri',
                    'metode_pembayaran' => 'tunai',
                    'nominal_pelunasan' => 200000,
                    'client_created_at' => now()->toIso8601String(),
                ],
            ],
        ];

        $response = $this->actingAs($this->user, 'sanctum')
            ->withSession(['cabang_id' => $this->cabang->id])
            ->postJson('/api/servis/sync-pickup?cabang_id='.$this->cabang->id, $payload);

        $response->assertStatus(200);
        $response->assertJsonPath('data.synced_count', 1);
        $response->assertJsonPath('data.failed_count', 0);

        $tiket->refresh();
        $this->assertEquals('diambil', $tiket->status);
        $this->assertEquals('lunas', $tiket->status_pembayaran);
        $this->assertTrue((bool) $tiket->is_offline_pickup);
        $this->assertEquals($uuid, $tiket->offline_pickup_uuid);
        $this->assertNotNull($tiket->pickup_synced_at);
    }

    public function test_sync_pickup_offline_is_idempotent(): void
    {
        $tiket = TiketServis::create([
            'no_tiket' => 'SRV-TEST-003',
            'cabang_id' => $this->cabang->id,
            'nama_pelanggan' => 'Citra Dewi',
            'telepon_pelanggan' => '081200001111',
            'jenis_hp' => 'Xiaomi 12',
            'keluhan' => 'Konektor charger longgar',
            'status' => 'selesai',
            'status_pembayaran' => 'belum_lunas',
            'estimasi_biaya' => 100000,
            'tanggal_terima' => now()->subDays(2),
            'tanggal_selesai' => now()->subHours(3),
        ]);

        $uuid = (string) Str::uuid();
        $payload = [
            'pickups' => [
                [
                    'pickup_uuid' => $uuid,
                    'no_tiket' => $tiket->no_tiket,
                    'nama_pengambil' => 'Citra Dewi',
                    'metode_pembayaran' => 'tunai',
                ],
            ],
        ];

        // Hit pertama
        $res1 = $this->actingAs($this->user, 'sanctum')
            ->withSession(['cabang_id' => $this->cabang->id])
            ->postJson('/api/servis/sync-pickup?cabang_id='.$this->cabang->id, $payload);
        $res1->assertStatus(200);
        $res1->assertJsonPath('data.synced_count', 1);

        // Hit kedua (retry dengan payload dan UUID sama persis)
        $res2 = $this->actingAs($this->user, 'sanctum')
            ->withSession(['cabang_id' => $this->cabang->id])
            ->postJson('/api/servis/sync-pickup?cabang_id='.$this->cabang->id, $payload);
        $res2->assertStatus(200);
        $res2->assertJsonPath('data.synced_count', 1);
        $res2->assertJsonPath('data.results.0.status', 'already_synced');
    }

    public function test_sync_pickup_from_qc_status_auto_advances_and_completes(): void
    {
        $tiket = TiketServis::create([
            'no_tiket' => 'SRV-TEST-004',
            'cabang_id' => $this->cabang->id,
            'nama_pelanggan' => 'Doni Saputra',
            'telepon_pelanggan' => '081233334444',
            'jenis_hp' => 'Oppo Reno 8',
            'keluhan' => 'Mati total kena air',
            'status' => 'qc',
            'status_pembayaran' => 'belum_lunas',
            'estimasi_biaya' => 120000,
            'tanggal_terima' => now()->subDays(1),
        ]);

        $uuid = (string) Str::uuid();
        $payload = [
            'pickups' => [
                [
                    'pickup_uuid' => $uuid,
                    'no_tiket' => $tiket->no_tiket,
                    'nama_pengambil' => 'Doni Saputra',
                ],
            ],
        ];

        $response = $this->actingAs($this->user, 'sanctum')
            ->withSession(['cabang_id' => $this->cabang->id])
            ->postJson('/api/servis/sync-pickup?cabang_id='.$this->cabang->id, $payload);

        $response->assertStatus(200);
        $response->assertJsonPath('data.synced_count', 1);

        $tiket->refresh();
        $this->assertEquals('diambil', $tiket->status);
        $this->assertEquals('lunas', $tiket->status_pembayaran);
    }
}
