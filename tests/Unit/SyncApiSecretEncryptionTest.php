<?php

namespace Tests\Unit;

use App\Models\User;
use App\Modules\Crm\Services\KonfigurasiService;
use App\Modules\Rbac\Livewire\SettingsRbac;
use App\Modules\Rbac\Models\Cabang;
use App\Modules\Sync\Providers\SyncServiceProvider;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class SyncApiSecretEncryptionTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;

    private Cabang $cabang;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->cabang = Cabang::create([
            'nama' => 'Cabang Secret Test',
            'kode' => 'CBG-SEC-01',
            'alamat' => 'Jl. Test',
            'telepon' => '081200011122',
            'is_active' => true,
        ]);

        $this->superAdmin = User::factory()->create(['email' => 'admin.secret@uteparts.id']);
        $this->superAdmin->assignRole('super-admin');
        $this->superAdmin->cabangs()->attach($this->cabang->id, ['is_default' => true]);
    }

    public function test_secret_encrypted_at_rest_hidden_in_form_and_preserved_on_resave(): void
    {
        $this->actingAs($this->superAdmin);
        session(['cabang_id' => $this->cabang->id]);

        $plainSecret = 'super-secret-hybrid-sync-key-123456';

        $component = Livewire::withQueryParams(['tab' => 'integrasi'])
            ->test(SettingsRbac::class)
            ->set('integrasiForm.sync_enabled', true)
            ->set('integrasiForm.sync_node_role', 'primary_store')
            ->set('integrasiForm.sync_node_id', 'STORE-SEC-01')
            ->set('integrasiForm.sync_remote_url', 'https://vps.example.com')
            ->set('integrasiForm.sync_api_secret', $plainSecret)
            ->set('integrasiForm.sync_pull_interval_seconds', 30)
            ->call('simpanIntegrasi')
            ->assertHasNoErrors();

        $saved = app(KonfigurasiService::class)->get('hybrid_sync_config');
        $this->assertIsArray($saved);
        $this->assertNotSame($plainSecret, $saved['api_secret']);
        $this->assertSame($plainSecret, Crypt::decryptString($saved['api_secret']));

        // Form UI tidak pernah menyimpan secret asli setelah disimpan.
        $component->assertSet('integrasiForm.sync_api_secret', '')
            ->assertSet('syncSecretConfigured', true);

        // Mount ulang tidak membocorkan secret ke browser.
        $reloaded = Livewire::withQueryParams(['tab' => 'integrasi'])
            ->test(SettingsRbac::class)
            ->assertSet('integrasiForm.sync_api_secret', '')
            ->assertSet('syncSecretConfigured', true);

        // Simpan ulang tanpa mengisi secret mempertahankan secret terenkripsi yang ada.
        $reloaded->set('integrasiForm.sync_node_id', 'STORE-SEC-02')
            ->call('simpanIntegrasi')
            ->assertHasNoErrors();

        $afterResave = app(KonfigurasiService::class)->get('hybrid_sync_config');
        $this->assertSame($plainSecret, Crypt::decryptString($afterResave['api_secret']));
    }

    public function test_legacy_plaintext_secret_works_and_auto_migrates_to_encrypted(): void
    {
        $this->actingAs($this->superAdmin);
        session(['cabang_id' => $this->cabang->id]);

        $legacyPlain = 'legacy-plaintext-secret-999';
        app(KonfigurasiService::class)->set('hybrid_sync_config', [
            'enabled' => true,
            'node_role' => 'primary_store',
            'node_id' => 'STORE-LEGACY',
            'remote_url' => 'https://vps.example.com',
            'api_secret' => $legacyPlain,
            'pull_interval_seconds' => 30,
        ]);

        // Boot/applyDatabaseConfig SyncServiceProvider tetap membaca plaintext legacy
        $provider = new class(app()) extends SyncServiceProvider
        {
            public function triggerApply(): void
            {
                $this->applyDatabaseConfig();
            }
        };
        $provider->triggerApply();
        $this->assertSame($legacyPlain, config('sync.api_secret'));

        // Re-save lewat UI tanpa mengubah secret otomatis mengenkripsi plaintext legacy
        Livewire::withQueryParams(['tab' => 'integrasi'])
            ->test(SettingsRbac::class)
            ->assertSet('syncSecretConfigured', true)
            ->call('simpanIntegrasi')
            ->assertHasNoErrors();

        $migrated = app(KonfigurasiService::class)->get('hybrid_sync_config');
        $this->assertNotSame($legacyPlain, $migrated['api_secret']);
        $this->assertSame($legacyPlain, Crypt::decryptString($migrated['api_secret']));
    }

    public function test_generate_sync_secret_and_test_connection_uses_stored_secret(): void
    {
        $this->actingAs($this->superAdmin);
        session(['cabang_id' => $this->cabang->id]);

        $component = Livewire::withQueryParams(['tab' => 'integrasi'])
            ->test(SettingsRbac::class)
            ->set('integrasiForm.sync_enabled', true)
            ->set('integrasiForm.sync_node_role', 'primary_store')
            ->set('integrasiForm.sync_remote_url', 'https://vps.example.com')
            ->call('generateSyncSecret')
            ->assertDispatched('sync-secret-generated');

        $generated = $component->get('integrasiForm.sync_api_secret');
        $this->assertSame(64, strlen($generated));

        // Simpan lalu reload (input kosong), testSyncConnection tetap berhasil pakai secret terenkripsi dari DB.
        $component->call('simpanIntegrasi')->assertSet('integrasiForm.sync_api_secret', '');

        Http::fake([
            'https://vps.example.com/api/internal/sync/health' => Http::response([
                'success' => true,
                'data' => ['node_id' => 'VPS-01', 'node_role' => 'cloud_storefront', 'pending_count' => 0],
                'message' => 'OK',
            ], 200),
        ]);

        Livewire::withQueryParams(['tab' => 'integrasi'])
            ->test(SettingsRbac::class)
            ->call('testSyncConnection')
            ->assertSet('syncTestStatus', 'success');

        Http::assertSent(function ($request) use ($generated) {
            $ts = $request->header('X-Sync-Timestamp')[0] ?? '';
            $sig = $request->header('X-Sync-Signature')[0] ?? '';

            return hash_equals(hash_hmac('sha256', $ts.'.', $generated), $sig);
        });
    }
}
