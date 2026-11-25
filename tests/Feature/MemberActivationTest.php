<?php

namespace Tests\Feature;

use App\Modules\Crm\Models\Pelanggan;
use App\Modules\Crm\Models\PelangganKodeAktivasi;
use App\Modules\Crm\Services\PelangganService;
use App\Modules\Marketplace\Livewire\CustomerAccount;
use App\Modules\Rbac\Models\AktivitasLog;
use App\Modules\Rbac\Models\Cabang;
use App\Modules\Rbac\Services\SessionManagementService;
use App\Modules\Servis\Models\TiketServis;
use App\Modules\Servis\Services\ServisService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Regresi untuk celah "member tidak bisa login":
 * akun tanpa kata sandi, format telepon tak kanonik, dan registrasi ulang
 * yang mentok karena nomor sudah terdaftar.
 */
class MemberActivationTest extends TestCase
{
    use RefreshDatabase;

    public function test_kandidat_telepon_menutup_semua_varian_prefix(): void
    {
        $service = app(PelangganService::class);

        $kandidat = $service->kandidatTelepon('081234567890');

        $this->assertContains('081234567890', $kandidat);
        $this->assertContains('81234567890', $kandidat);
        $this->assertContains('6281234567890', $kandidat);

        $this->assertContains('081234567890', $service->kandidatTelepon('6281234567890'));
        $this->assertContains('081234567890', $service->kandidatTelepon('81234567890'));
        $this->assertContains('081234567890', $service->kandidatTelepon('+62 812-3456-7890'));
        $this->assertSame([], $service->kandidatTelepon('budi_parts'));
    }

    public function test_login_masuk_walau_telepon_tersimpan_tanpa_prefix_nol(): void
    {
        Pelanggan::create([
            'nama' => 'Budi Santoso',
            'telepon' => '81234567890',
            'password' => Hash::make('secret123'),
            'status' => 'aktif',
        ]);

        $response = $this->post('/login-pelanggan', [
            'telepon' => '081234567890',
            'password' => 'secret123',
        ]);

        $response->assertRedirect('/account');
        $this->assertAuthenticated('customer');
    }

    public function test_login_akun_tanpa_password_diberi_pesan_aktivasi(): void
    {
        Pelanggan::create([
            'nama' => 'Budi Santoso',
            'telepon' => '081234567890',
            'status' => 'aktif',
        ]);

        $response = $this->from('/login-pelanggan')->post('/login-pelanggan', [
            'telepon' => '081234567890',
            'password' => 'apapun',
        ]);

        $response->assertRedirect('/login-pelanggan');
        $response->assertSessionHasErrors('telepon');
        $this->assertGuest('customer');
        $this->assertStringContainsString('belum memiliki kata sandi', session('errors')->first('telepon'));
    }

    public function test_member_aktivasi_akun_dengan_kode_lalu_bisa_login(): void
    {
        $pelanggan = Pelanggan::create([
            'nama' => 'Siti Aisyah',
            'telepon' => '081987654321',
            'status' => 'aktif',
        ]);

        $kode = app(PelangganService::class)->buatKodeAktivasi($pelanggan);
        $this->assertSame(6, strlen($kode));

        $response = $this->from('/aktivasi-akun')->post('/aktivasi-akun', [
            'telepon' => '081987654321',
            'kode' => $kode,
            'password' => 'sandiBaru123',
            'password_confirmation' => 'sandiBaru123',
        ]);

        $response->assertRedirect('/login-pelanggan');
        $pelanggan->refresh();
        $this->assertTrue(Hash::check('sandiBaru123', $pelanggan->password));

        $login = $this->post('/login-pelanggan', [
            'telepon' => '081987654321',
            'password' => 'sandiBaru123',
        ]);
        $login->assertRedirect('/account');
        $this->assertAuthenticated('customer');
    }

    public function test_aktivasi_gagal_dengan_kode_salah(): void
    {
        $pelanggan = Pelanggan::create([
            'nama' => 'Siti Aisyah',
            'telepon' => '081987654321',
            'status' => 'aktif',
        ]);
        app(PelangganService::class)->buatKodeAktivasi($pelanggan);

        $response = $this->from('/aktivasi-akun')->post('/aktivasi-akun', [
            'telepon' => '081987654321',
            'kode' => '000000',
            'password' => 'sandiBaru123',
            'password_confirmation' => 'sandiBaru123',
        ]);

        $response->assertSessionHasErrors('kode');
        $pelanggan->refresh();
        $this->assertNull($pelanggan->password);
    }

    public function test_aktivasi_gagal_karena_kode_kedaluwarsa(): void
    {
        $pelanggan = Pelanggan::create([
            'nama' => 'Siti Aisyah',
            'telepon' => '081987654321',
            'status' => 'aktif',
        ]);
        $kode = app(PelangganService::class)->buatKodeAktivasi($pelanggan);

        PelangganKodeAktivasi::where('pelanggan_id', $pelanggan->id)
            ->update(['kedaluwarsa_at' => now()->subMinute()]);

        $response = $this->from('/aktivasi-akun')->post('/aktivasi-akun', [
            'telepon' => '081987654321',
            'kode' => $kode,
            'password' => 'sandiBaru123',
            'password_confirmation' => 'sandiBaru123',
        ]);

        $response->assertSessionHasErrors('kode');
        $pelanggan->refresh();
        $this->assertNull($pelanggan->password);
    }

    public function test_aktivasi_ditolak_jika_akun_sudah_punya_password(): void
    {
        $pelanggan = Pelanggan::create([
            'nama' => 'Siti Aisyah',
            'telepon' => '081987654321',
            'password' => Hash::make('secret123'),
            'status' => 'aktif',
        ]);
        $kode = app(PelangganService::class)->buatKodeAktivasi($pelanggan);

        $response = $this->from('/aktivasi-akun')->post('/aktivasi-akun', [
            'telepon' => '081987654321',
            'kode' => $kode,
            'password' => 'sandiBaru123',
            'password_confirmation' => 'sandiBaru123',
        ]);

        $response->assertSessionHasErrors('telepon');
        $pelanggan->refresh();
        $this->assertTrue(Hash::check('secret123', $pelanggan->password));
    }

    public function test_registrasi_ulang_ditolak_dengan_pesan_aktivasi_bila_akun_lama_tanpa_password(): void
    {
        Pelanggan::create([
            'nama' => 'Budi Santoso',
            'telepon' => '081234567890',
            'status' => 'aktif',
        ]);

        $response = $this->from('/daftar-pelanggan')->post('/daftar-pelanggan', [
            'nama' => 'Budi Santoso',
            'telepon' => '081234567890',
            'password' => 'sandiBaru123',
        ]);

        $response->assertSessionHasErrors('telepon');
        $this->assertStringContainsString('belum memiliki kata sandi', session('errors')->first('telepon'));
    }

    public function test_profil_member_bisa_mengubah_telepon(): void
    {
        $pelanggan = Pelanggan::create([
            'nama' => 'Budi Santoso',
            'telepon' => '081234567890',
            'password' => Hash::make('secret123'),
            'status' => 'aktif',
        ]);

        $this->actingAs($pelanggan, 'customer');

        Livewire::test(CustomerAccount::class)
            ->set('telepon', '81999888777')
            ->set('identitasPassword', 'secret123')
            ->call('simpanProfil')
            ->assertHasNoErrors();

        $pelanggan->refresh();
        $this->assertSame('081999888777', $pelanggan->telepon);

        $login = $this->post('/login-pelanggan', [
            'telepon' => '081999888777',
            'password' => 'secret123',
        ]);
        $login->assertRedirect('/account');
        $this->assertAuthenticated('customer');
    }

    public function test_cari_by_telepon_menemukan_varian_format(): void
    {
        $pelanggan = Pelanggan::create([
            'nama' => 'Budi Santoso',
            'telepon' => '081234567890',
            'status' => 'aktif',
        ]);

        $service = app(PelangganService::class);

        $this->assertSame($pelanggan->id, $service->cariByTelepon('6281234567890')?->id);
        $this->assertSame($pelanggan->id, $service->cariByTelepon('81234567890')?->id);
        $this->assertNull($service->cariByTelepon('081111222333'));
    }

    public function test_perubahan_telepon_wajib_reautentikasi(): void
    {
        $pelanggan = Pelanggan::create([
            'nama' => 'Budi Santoso',
            'telepon' => '081234567890',
            'password' => Hash::make('secret123'),
            'status' => 'aktif',
        ]);

        $this->actingAs($pelanggan, 'customer');

        // Tanpa kata sandi konfirmasi → ditolak
        Livewire::test(CustomerAccount::class)
            ->set('telepon', '081999888777')
            ->call('simpanProfil')
            ->assertHasErrors(['identitasPassword']);

        // Kata sandi konfirmasi salah → ditolak
        Livewire::test(CustomerAccount::class)
            ->set('telepon', '081999888777')
            ->set('identitasPassword', 'salah')
            ->call('simpanProfil')
            ->assertHasErrors(['identitasPassword']);

        $pelanggan->refresh();
        $this->assertSame('081234567890', $pelanggan->telepon);
    }

    public function test_perubahan_profil_tercatat_dengan_causer_member(): void
    {
        $pelanggan = Pelanggan::create([
            'nama' => 'Budi Santoso',
            'telepon' => '081234567890',
            'password' => Hash::make('secret123'),
            'status' => 'aktif',
        ]);

        $this->actingAs($pelanggan, 'customer');

        Livewire::test(CustomerAccount::class)
            ->set('nama', 'Budi Santoso Updated')
            ->call('simpanProfil')
            ->assertHasNoErrors();

        $log = AktivitasLog::query()
            ->where('subject_type', Pelanggan::class)
            ->where('subject_id', $pelanggan->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($log);
        $this->assertSame($pelanggan->id, $log->causer_id);
        $this->assertSame(Pelanggan::class, $log->causer_type);
        $this->assertSame('Budi Santoso Updated', $log->causerNama);
    }

    public function test_login_mendaftarkan_perangkat_member(): void
    {
        $pelanggan = Pelanggan::create([
            'nama' => 'Budi Santoso',
            'telepon' => '081234567890',
            'password' => Hash::make('secret123'),
            'status' => 'aktif',
        ]);

        $this->post('/login-pelanggan', [
            'telepon' => '081234567890',
            'password' => 'secret123',
        ]);

        $this->assertAuthenticated('customer');
        $this->assertNotNull(session('customer_device_token'));
        $this->assertDatabaseHas('pelanggan_device_sessions', [
            'pelanggan_id' => $pelanggan->id,
            'is_active' => true,
        ]);
    }

    public function test_sesi_yang_diakhiri_ditegakkan_middleware(): void
    {
        $pelanggan = Pelanggan::create([
            'nama' => 'Budi Santoso',
            'telepon' => '081234567890',
            'password' => Hash::make('secret123'),
            'status' => 'aktif',
        ]);

        $this->actingAs($pelanggan, 'customer');

        // Sesi lama (tanpa token) otomatis diberi penanda perangkat.
        $this->get('/account')->assertOk();
        $token = session('customer_device_token');
        $this->assertNotNull($token);

        app(SessionManagementService::class)->forceLogoutPelangganDevice($token, $pelanggan->id);

        // Request berikutnya diarahkan ke login dan sesi member dicabut.
        $this->get('/account')->assertRedirect(route('customer.login'));
        $this->assertGuest('customer');
    }

    public function test_member_bisa_mengakhiri_sesi_perangkat_lain(): void
    {
        $pelanggan = Pelanggan::create([
            'nama' => 'Budi Santoso',
            'telepon' => '081234567890',
            'password' => Hash::make('secret123'),
            'status' => 'aktif',
        ]);

        $service = app(SessionManagementService::class);
        $service->registerPelangganDevice($pelanggan->id, 'Chrome Android', 'token-a');
        $service->registerPelangganDevice($pelanggan->id, 'Safari iPhone', 'token-b');

        $this->actingAs($pelanggan, 'customer');
        $this->withSession(['customer_device_token' => 'token-a']);

        Livewire::test(CustomerAccount::class, ['tab' => 'sesi'])
            ->assertSee('Perangkat Ini')
            ->call('akhiriSesi', 'token-b')
            ->assertDispatched('alert');

        $this->assertTrue($service->pelangganDeviceAktif($pelanggan->id, 'token-a'));
        $this->assertFalse($service->pelangganDeviceAktif($pelanggan->id, 'token-b'));
    }

    public function test_ganti_password_mengakhiri_sesi_perangkat_lain(): void
    {
        $pelanggan = Pelanggan::create([
            'nama' => 'Budi Santoso',
            'telepon' => '081234567890',
            'password' => Hash::make('secret123'),
            'status' => 'aktif',
        ]);

        $service = app(SessionManagementService::class);
        $service->registerPelangganDevice($pelanggan->id, 'Chrome Android', 'token-a');
        $service->registerPelangganDevice($pelanggan->id, 'Safari iPhone', 'token-b');

        $this->actingAs($pelanggan, 'customer');
        $this->withSession(['customer_device_token' => 'token-a']);

        Livewire::test(CustomerAccount::class)
            ->set('currentPassword', 'secret123')
            ->set('newPassword', 'sandiBaru123')
            ->set('newPassword_confirmation', 'sandiBaru123')
            ->call('updatePassword')
            ->assertHasNoErrors();

        $this->assertTrue($service->pelangganDeviceAktif($pelanggan->id, 'token-a'));
        $this->assertFalse($service->pelangganDeviceAktif($pelanggan->id, 'token-b'));
    }

    public function test_booking_servis_menormalisasi_telepon_dan_tak_buat_akun_ganda(): void
    {
        $cabang = Cabang::create([
            'nama' => 'Cabang Uji',
            'kode' => 'CBG-UJI',
            'is_active' => true,
        ]);

        $service = app(ServisService::class);

        $service->bookingOnline([
            'cabang_id' => $cabang->id,
            'nama' => 'Budi Santoso',
            'telepon' => '81234567890',
            'jenis_hp' => 'iPhone 13',
            'keluhan' => 'Layar blank setelah jatuh',
        ]);

        $service->bookingOnline([
            'cabang_id' => $cabang->id,
            'nama' => 'Budi Santoso',
            'telepon' => '081234567890',
            'jenis_hp' => 'iPhone 13',
            'keluhan' => 'Layar blank setelah jatuh',
        ]);

        $this->assertSame(1, Pelanggan::count());
        $this->assertSame('081234567890', Pelanggan::first()->telepon);
        $this->assertSame(2, TiketServis::count());
    }
}
