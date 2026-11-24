<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Rbac\Models\Cabang;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class SyncStatusControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Cabang $cabang;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cabang = Cabang::create([
            'nama' => 'Cabang Utama Test',
            'kode' => 'CB01',
            'alamat' => 'Jl. Test No. 1',
            'telepon' => '08123456789',
            'tipe' => 'pusat',
            'is_active' => true,
        ]);

        $this->user = User::factory()->create([
            'email' => 'kasir.test@bremspace.id',
        ]);
        $this->user->cabangs()->attach($this->cabang->id, ['is_default' => true]);

        session([
            'cabang_id' => $this->cabang->id,
            'cabang_nama' => $this->cabang->nama,
        ]);
    }

    public function test_sync_status_returns_outbox_counts(): void
    {
        config([
            'sync.enabled' => true,
            'sync.node_role' => 'primary_store',
            'sync.cloud_url' => 'https://vps.bremspace.id',
        ]);

        DB::table('hybrid_sync_outbox')->insert([
            'sync_uuid' => (string) Str::uuid(),
            'event_type' => 'transaksi.pos',
            'entity_type' => 'Transaksi',
            'entity_id' => 999,
            'cabang_id' => $this->cabang->id,
            'payload' => json_encode(['foo' => 'bar']),
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($this->user)
            ->withSession(['cabang_id' => $this->cabang->id])
            ->getJson('/app/sync/status');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.enabled', true)
            ->assertJsonPath('data.node_role', 'primary_store')
            ->assertJsonPath('data.pending_count', 1)
            ->assertJsonPath('data.failed_count', 0)
            ->assertJsonPath('data.cloud_host', 'vps.bremspace.id');
    }

    public function test_sync_trigger_returns_rejected_when_sync_disabled(): void
    {
        config(['sync.enabled' => false]);

        $response = $this->actingAs($this->user)
            ->withSession(['cabang_id' => $this->cabang->id])
            ->postJson('/app/sync/trigger');

        $response->assertStatus(400)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Sinkronisasi hybrid tidak aktif pada node ini.');
    }

    public function test_sync_trigger_executes_successfully(): void
    {
        config([
            'sync.enabled' => true,
            'sync.node_role' => 'primary_store',
            'sync.cloud_url' => 'https://mock.bremspace.id',
            'sync.shared_secret' => 'test-secret',
        ]);

        $response = $this->actingAs($this->user)
            ->withSession(['cabang_id' => $this->cabang->id])
            ->postJson('/app/sync/trigger');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Sinkronisasi berhasil dijalankan.');
    }
}
