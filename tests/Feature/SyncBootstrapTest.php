<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Rbac\Models\Cabang;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class SyncBootstrapTest extends TestCase
{
    use RefreshDatabase;

    public function test_cloud_endpoint_mengembalikan_daftar_tabel_bootstrap(): void
    {
        config([
            'sync.enabled' => true,
            'sync.node_role' => 'cloud_storefront',
            'sync.node_id' => 'CLOUD-TEST',
            'sync.api_secret' => 'rahasia-bersama-yang-cukup-panjang',
        ]);

        $query = '';
        $timestamp = (string) time();
        $signature = hash_hmac('sha256', $timestamp.'.'.$query, (string) config('sync.api_secret'));

        $response = $this->withHeaders([
            'X-Sync-Node' => 'STORE-TEST',
            'X-Sync-Timestamp' => $timestamp,
            'X-Sync-Signature' => $signature,
        ])->getJson('/api/internal/sync/bootstrap');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['tables']]);

        $this->assertContains(
            'kategori_produk',
            collect($response->json('data.tables'))->pluck('table')->all()
        );
    }

    public function test_bootstrap_ditolak_dari_node_client(): void
    {
        config([
            'sync.enabled' => true,
            'sync.node_role' => 'primary_store',
            'sync.node_id' => 'STORE-TEST',
            'sync.api_secret' => 'rahasia-bersama-yang-cukup-panjang',
        ]);

        $timestamp = (string) time();
        $signature = hash_hmac('sha256', $timestamp.'.', (string) config('sync.api_secret'));

        $this->withHeaders([
            'X-Sync-Node' => 'CLOUD-TEST',
            'X-Sync-Timestamp' => $timestamp,
            'X-Sync-Signature' => $signature,
        ])->getJson('/api/internal/sync/bootstrap')
            ->assertStatus(403);
    }

    public function test_halaman_indikator_sync_dapat_diakses(): void
    {
        $permission = Permission::firstOrCreate(['name' => 'pengaturan.manage', 'guard_name' => 'web']);
        $cabang = Cabang::create([
            'nama' => 'Cabang Sync Test',
            'kode' => 'CBG-SYNC',
            'is_active' => true,
        ]);
        $user = User::factory()->create();
        $user->givePermissionTo($permission);
        $user->cabangs()->attach($cabang->id, ['is_default' => true]);

        $this->actingAs($user)
            ->withSession(['cabang_id' => $cabang->id, 'cabang_nama' => $cabang->nama])
            ->get('/app/sync')
            ->assertOk()
            ->assertSee('Peran Sinkronisasi');
    }
}
