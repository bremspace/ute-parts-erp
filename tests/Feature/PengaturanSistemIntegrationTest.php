<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Crm\Services\KonfigurasiService;
use App\Modules\Marketplace\Livewire\WebKonsumenSettings;
use App\Modules\Marketplace\Services\WebKonsumenService;
use App\Modules\Rbac\Livewire\SessionManagementPage;
use App\Modules\Rbac\Livewire\SettingsRbac;
use App\Modules\Rbac\Models\Cabang;
use App\Modules\Rbac\Models\DeviceSession;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PengaturanSistemIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;

    private User $kasir;

    private Cabang $cabang;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->cabang = Cabang::firstOrCreate(
            ['kode' => 'CBG-01'],
            ['nama' => 'Cabang Utama', 'is_active' => true]
        );

        $this->superAdmin = User::factory()->create([
            'name' => 'Super Administrator',
            'email' => 'superadmin@uteparts.id',
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);
        $this->superAdmin->assignRole('super-admin');
        $this->superAdmin->cabangs()->attach($this->cabang->id, ['is_default' => true]);

        $this->kasir = User::factory()->create([
            'name' => 'Kasir Toko',
            'email' => 'kasir@uteparts.id',
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);
        $this->kasir->assignRole('kasir');
        $this->kasir->cabangs()->attach($this->cabang->id, ['is_default' => true]);
    }

    /**
     * Test akses route 4 menu Pengaturan & Sistem sesuai RBAC.
     */
    public function test_rbac_routes_pengaturan_dan_sistem(): void
    {
        // 1. Pengaturan RBAC
        $this->actingAs($this->superAdmin);
        session(['cabang_id' => $this->cabang->id]);
        $response = $this->get('/app/pengaturan');
        $response->assertStatus(200);

        // 2. Keamanan 2FA
        $response = $this->get('/app/keamanan/dua-faktor');
        $response->assertStatus(200);

        // 3. Sesi Perangkat
        $response = $this->get('/app/keamanan/sesi');
        $response->assertStatus(200);

        // 4. Audit Log
        $response = $this->get('/app/audit-log');
        $response->assertStatus(200);

        // Kasir tanpa izin kelola-sesi dan lihat-audit-log harus 403
        $this->actingAs($this->kasir);
        $this->get('/app/keamanan/sesi')->assertStatus(403);
        $this->get('/app/audit-log')->assertStatus(403);
    }

    /**
     * Test login web mencatat DeviceSession dan logout menandainya nonaktif.
     */
    public function test_login_and_logout_tracks_device_session(): void
    {
        // Login via POST /app/login
        $response = $this->post('/app/login', [
            'email' => 'superadmin@uteparts.id',
            'password' => 'password123',
        ], ['User-Agent' => 'TestBrowser/1.0']);

        $response->assertRedirect('/app/dashboard');

        $session = DeviceSession::where('user_id', $this->superAdmin->id)->latest()->first();
        $this->assertNotNull($session);
        $this->assertTrue($session->is_active);
        $this->assertStringContainsString('TestBrowser', $session->device_name);

        $deviceToken = session('device_token');
        $this->assertEquals($session->device_token, $deviceToken);

        // Logout
        $this->post('/logout');
        $session->refresh();
        $this->assertFalse($session->is_active);
    }

    /**
     * Test guardrail anti self-lockout pada SettingsRbac.
     */
    public function test_anti_self_lockout_guardrails(): void
    {
        $this->actingAs($this->superAdmin);
        session(['cabang_id' => $this->cabang->id]);

        // 1. Toggle nonaktif akun sendiri harus ditolak
        Livewire::test(SettingsRbac::class)
            ->call('toggleUserActive', $this->superAdmin->id)
            ->assertDispatched('alert');

        $this->superAdmin->refresh();
        $this->assertTrue($this->superAdmin->is_active);

        // 2. Uncheck is_active saat edit akun sendiri harus ditolak
        Livewire::test(SettingsRbac::class)
            ->call('openUserModal', $this->superAdmin->id)
            ->set('userForm.is_active', false)
            ->call('saveUser')
            ->assertDispatched('alert');

        $this->superAdmin->refresh();
        $this->assertTrue($this->superAdmin->is_active);

        // 3. Mengosongkan permission super-admin harus ditolak
        $role = Role::findByName('super-admin');
        Livewire::test(SettingsRbac::class)
            ->call('openEditRole', $role->id)
            ->set('editRolePermissions', [])
            ->call('saveEditRolePermissions')
            ->assertDispatched('alert');
    }

    /**
     * Test SessionManagementPage actions (force logout & logout all).
     */
    public function test_session_management_page_actions(): void
    {
        $this->actingAs($this->superAdmin);
        session(['cabang_id' => $this->cabang->id, 'device_token' => 'current-token-123']);

        DeviceSession::create([
            'user_id' => $this->superAdmin->id,
            'device_name' => 'Device Lain',
            'device_token' => 'other-token-456',
            'is_active' => true,
            'last_activity' => now(),
            'expires_at' => now()->addMinutes(30),
        ]);

        DeviceSession::create([
            'user_id' => $this->superAdmin->id,
            'device_name' => 'Device Saat Ini',
            'device_token' => 'current-token-123',
            'is_active' => true,
            'last_activity' => now(),
            'expires_at' => now()->addMinutes(30),
        ]);

        Livewire::test(SessionManagementPage::class)
            ->call('logoutDevice', 'other-token-456')
            ->assertDispatched('alert');

        $this->assertFalse(DeviceSession::where('device_token', 'other-token-456')->first()->is_active);

        // Test logout all (hanya device selain current-token-123)
        DeviceSession::where('device_token', 'other-token-456')->update(['is_active' => true]);

        Livewire::test(SessionManagementPage::class)
            ->call('logoutAll')
            ->assertDispatched('alert');

        $this->assertFalse(DeviceSession::where('device_token', 'other-token-456')->first()->is_active);
        $this->assertTrue(DeviceSession::where('device_token', 'current-token-123')->first()->is_active);
    }

    /**
     * Test submenu sidebar pengaturan menampilkan link kategori terstruktur.
     */
    public function test_sidebar_pengaturan_links_render_properly(): void
    {
        $this->actingAs($this->superAdmin);
        session(['cabang_id' => $this->cabang->id]);

        $response = $this->get('/app/pengaturan');
        $response->assertStatus(200);
        $response->assertSee('Pusat Pengaturan');
        $response->assertSee('/app/pengaturan?tab=users');
        $response->assertSee('/app/pengaturan?tab=master_produk');
        $response->assertSee('/app/pengaturan?tab=loyalitas');
        $response->assertSee('/app/pengaturan?tab=printer');
        $response->assertSee('/app/pengaturan?tab=integrasi');
        $response->assertSee('/app/pengaturan?tab=database');
    }

    /**
     * Test integrasi tab mount dan penyimpanan konfigurasi gateway.
     */
    public function test_tab_integrasi_and_configuration_saving(): void
    {
        $this->actingAs($this->superAdmin);
        session(['cabang_id' => $this->cabang->id]);

        $component = Livewire::withQueryParams(['tab' => 'integrasi'])
            ->test(SettingsRbac::class)
            ->assertSet('activeTab', 'integrasi')
            ->set('integrasiForm.wa_provider', 'fonnte')
            ->set('integrasiForm.wa_url', 'https://api.fonnte.com/send')
            ->set('integrasiForm.wa_token', 'token-wa-secret-123')
            ->set('integrasiForm.duitku_mode', 'production')
            ->set('integrasiForm.duitku_merchant_code', 'D1234')
            ->set('integrasiForm.duitku_api_key', 'duitku-api-key-test')
            ->set('integrasiForm.duitku_merchant_key', 'duitku-mkey-test')
            ->set('integrasiForm.biteship_api_key', 'biteship-api-key-test')
            ->call('simpanIntegrasi')
            ->assertDispatched('alert');

        $config = app(KonfigurasiService::class);
        $wa = $config->get('wa_gateway_config');
        $this->assertIsArray($wa);
        $this->assertNotEquals('token-wa-secret-123', $wa['token']);
        $this->assertEquals('token-wa-secret-123', Crypt::decryptString($wa['token']));
        $component->assertSet('integrasiForm.wa_token', '');
        $component->assertSet('waTokenConfigured', true);

        $duitku = $config->get('duitku_gateway_config');
        $this->assertIsArray($duitku);
        $this->assertEquals('D1234', $duitku['merchant_code']);
        $this->assertEquals('production', $duitku['mode']);

        $biteship = $config->get('biteship_gateway_config');
        $this->assertIsArray($biteship);
        $this->assertEquals('biteship-api-key-test', $biteship['api_key']);

        // Menyimpan ulang tanpa mengisi ulang wa_token wajib mempertahankan token tersimpan.
        $component->set('integrasiForm.duitku_merchant_code', 'D5678')
            ->call('simpanIntegrasi')
            ->assertDispatched('alert');
        $waAfter = $config->get('wa_gateway_config');
        $this->assertEquals('token-wa-secret-123', Crypt::decryptString($waAfter['token']));
    }

    public function test_toggle_duitku_di_settings_rbac_tersinkron_ke_web_konsumen_settings(): void
    {
        $this->actingAs($this->superAdmin);
        session(['cabang_id' => $this->cabang->id]);

        Livewire::withQueryParams(['tab' => 'integrasi'])
            ->test(SettingsRbac::class)
            ->set('integrasiForm.payment_mp_duitku_enabled', false)
            ->assertDispatched('alert')
            ->set('integrasiForm.payment_mp_duitku_enabled', true)
            ->assertDispatched('alert');

        $config = app(KonfigurasiService::class);
        $webKonsumen = app(WebKonsumenService::class);

        $this->assertTrue((bool) $config->get('payment_mp_duitku_enabled'));
        $this->assertTrue((bool) $webKonsumen->get('payment_mp_duitku_enabled'));

        // Pastikan toggle di WebKonsumenSettings ikut menyinkronkan ke KonfigurasiService
        Livewire::actingAs($this->superAdmin)
            ->test(WebKonsumenSettings::class, ['tab' => 'pembayaran'])
            ->assertSet('paymentForm.payment_mp_duitku_enabled', true)
            ->set('paymentForm.payment_mp_duitku_enabled', false)
            ->assertDispatched('alert');

        $this->assertFalse((bool) $config->get('payment_mp_duitku_enabled'));
        $this->assertFalse((bool) $webKonsumen->get('payment_mp_duitku_enabled'));
    }

    public function test_openwa_validasi_sesi_dan_setup_otomatis_dari_ui(): void
    {
        $this->actingAs($this->superAdmin);
        session(['cabang_id' => $this->cabang->id]);

        // 1. Memilih openwa tanpa ID sesi gagal validasi saat simpan
        Livewire::withQueryParams(['tab' => 'integrasi'])
            ->test(SettingsRbac::class)
            ->set('integrasiForm.wa_provider', 'openwa')
            ->assertSee('Manajemen Gateway OpenWA')
            ->set('integrasiForm.wa_session', '')
            ->call('simpanIntegrasi')
            ->assertHasErrors(['integrasiForm.wa_session']);

        // 2. Setup otomatis 1-klik membuat sesi, key operator, QR & menyimpan terenkripsi
        $uuid = '550e8400-e29b-41d4-a716-446655440000';
        Http::fake([
            'http://127.0.0.1:2785/api/health/ready' => Http::response(['status' => 'ok'], 200),
            'http://127.0.0.1:2785/api/sessions' => fn ($req) => $req->method() === 'POST'
                ? Http::response(['id' => $uuid, 'name' => 'bremspace-erp', 'status' => 'created'], 201)
                : Http::response([], 200),
            'http://127.0.0.1:2785/api/sessions/'.$uuid.'/start' => Http::response(['status' => 'starting'], 200),
            'http://127.0.0.1:2785/api/auth/api-keys' => Http::response(['apiKey' => 'owa_op_auto_999'], 201),
            'http://127.0.0.1:2785/api/sessions/'.$uuid => Http::sequence()
                ->push(['id' => $uuid, 'name' => 'bremspace-erp', 'status' => 'qr_ready'], 200)
                ->push(['id' => $uuid, 'name' => 'bremspace-erp', 'status' => 'ready', 'me' => ['id' => '628111222333@c.us']], 200),
            'http://127.0.0.1:2785/api/sessions/'.$uuid.'/qr' => Http::response([
                'qrCode' => 'data:image/png;base64,iVBORw0KGgo=',
                'status' => 'qr_ready',
            ], 200),
            'http://127.0.0.1:2785/api/sessions/'.$uuid.'/pairing-code' => Http::response([
                'code' => '1234-5678',
            ], 200),
            'http://127.0.0.1:2785/api/sessions/'.$uuid.'/messages/send-text' => Http::response([
                'ok' => true,
            ], 200),
        ]);

        $component = Livewire::withQueryParams(['tab' => 'integrasi'])
            ->test(SettingsRbac::class)
            ->set('integrasiForm.wa_provider', 'openwa')
            ->set('integrasiForm.wa_url', 'http://127.0.0.1:2785')
            ->set('openwaAdminKey', 'owa_k1_admin_master')
            ->call('setupOpenWaOtomatis')
            ->assertSet('integrasiForm.wa_session', $uuid)
            ->assertSet('integrasiForm.wa_token', '')
            ->assertSet('openwaAdminKey', '')
            ->assertSet('waTokenConfigured', true)
            ->assertSet('openwaQrData', 'data:image/png;base64,iVBORw0KGgo=');

        $saved = app(KonfigurasiService::class)->get('wa_gateway_config');
        $this->assertSame('openwa', $saved['provider']);
        $this->assertSame('http://127.0.0.1:2785/api', $saved['url']);
        $this->assertSame($uuid, $saved['session']);
        $this->assertSame('owa_op_auto_999', Crypt::decryptString($saved['token']));

        // Pairing code menormalisasi nomor & menyimpan kode
        $component->set('openwaPairingPhone', '08111222333')
            ->call('getPairingCodeOpenWa')
            ->assertSet('openwaPairingCode', '1234-5678');

        // Refresh status saat sesi ready otomatis membersihkan QR & pairing code
        $component->call('refreshOpenWaSessionStatus')
            ->assertSet('openwaQrData', '')
            ->assertSet('openwaPairingCode', '')
            ->assertSet('openwaSessionStatus.phone', '628111222333');

        // Tes kirim WA langsung pakai token operator tersimpan & catat log ke NotifikasiKeluar.
        $component->set('integrasiForm.wa_test_number', '081234567890')
            ->call('testKirimWa')
            ->assertSet('waTestStatus', 'success');

        $this->assertDatabaseHas('notifikasi_keluar', [
            'tipe' => 'wa',
            'tujuan' => '6281234567890@c.us',
            'status' => 'terkirim',
        ]);

        // Submenu integrasi ?sub=wa dan ?sub=duitku tetap merender HTML valid
        $this->get('/app/pengaturan?tab=integrasi&sub=wa')
            ->assertStatus(200)
            ->assertSee('WhatsApp Gateway');
        $this->get('/app/pengaturan?tab=integrasi&sub=duitku')
            ->assertStatus(200)
            ->assertSee('Payment Gateway Duitku');
    }

    public function test_pengguna_tanpa_izin_dilarang_mengelola_openwa(): void
    {
        $this->actingAs($this->kasir);
        session(['cabang_id' => $this->cabang->id]);

        Http::fake();

        Livewire::withQueryParams(['tab' => 'integrasi'])
            ->test(SettingsRbac::class)
            ->set('integrasiForm.wa_url', 'http://127.0.0.1:2785')
            ->set('openwaAdminKey', 'owa_k1_admin_master')
            ->call('setupOpenWaOtomatis')
            ->call('getQrOpenWa')
            ->call('cekHealthOpenWa');

        Http::assertNothingSent();
    }
}
