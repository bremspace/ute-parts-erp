<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Akunting\Models\AkunCOA;
use App\Modules\Akunting\Services\PajakService;
use App\Modules\Rbac\Livewire\SettingsRbac;
use App\Modules\Rbac\Models\Cabang;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PajakOpsiTest extends TestCase
{
    use RefreshDatabase;

    protected Cabang $cabangA;

    protected Cabang $cabangB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cabangA = Cabang::create(['nama' => 'Cabang Utama (PKP)', 'kode' => 'PKP', 'alamat' => 'Jakarta']);
        $this->cabangB = Cabang::create(['nama' => 'Cabang Kecil (Non-PKP)', 'kode' => 'NONPKP', 'alamat' => 'Bogor']);

        AkunCOA::firstOrCreate(['kode' => '220-01'], [
            'nama' => 'PPN Keluaran',
            'tipe' => 'kewajiban',
            'kelompok' => 'pajak',
            'saldo_normal' => 'kredit',
        ]);
    }

    public function test_aplikasi_berjalan_tanpa_pajak_secara_default(): void
    {
        $service = app(PajakService::class);

        $this->assertFalse($service->enabled($this->cabangA->id));

        $hasil = $service->hitungPenjualan($this->cabangA->id, 100000, 0);

        $this->assertFalse($hasil['enabled']);
        $this->assertEquals(0.0, $hasil['ppn_nominal']);
        $this->assertEquals(100000, $hasil['dpp']);
        $this->assertEquals(100000, $hasil['total_akhir']);
    }

    public function test_aplikasi_berjalan_dengan_pajak_exclusive(): void
    {
        $service = app(PajakService::class);
        $service->saveConfig($this->cabangA->id, [
            'enabled' => true,
            'percent' => 11.0,
            'nama' => 'PPN',
            'mode' => 'exclusive',
        ]);

        $this->assertTrue($service->enabled($this->cabangA->id));

        $hasil = $service->hitungPenjualan($this->cabangA->id, 100000, 0);

        $this->assertTrue($hasil['enabled']);
        $this->assertEquals(11000.0, $hasil['ppn_nominal']);
        $this->assertEquals(100000, $hasil['dpp']);
        $this->assertEquals(111000.0, $hasil['total_akhir']);
    }

    public function test_aplikasi_berjalan_dengan_pajak_inclusive(): void
    {
        $service = app(PajakService::class);
        $service->saveConfig($this->cabangA->id, [
            'enabled' => true,
            'percent' => 11.0,
            'nama' => 'PPN',
            'mode' => 'inclusive',
        ]);

        $this->assertTrue($service->enabled($this->cabangA->id));

        $hasil = $service->hitungPenjualan($this->cabangA->id, 111000, 0);

        $this->assertTrue($hasil['enabled']);
        $this->assertEquals('inclusive', $hasil['mode']);
        $this->assertEquals(100000.0, $hasil['dpp']);
        $this->assertEquals(11000.0, $hasil['ppn_nominal']);
        $this->assertEquals(111000.0, $hasil['total_akhir']);
    }

    public function test_aplikasi_berjalan_dengan_nama_pajak_kustom_dan_tarif_baru(): void
    {
        $service = app(PajakService::class);
        // Contoh PPN 12% (UU HPP) atau PB1 10%
        $service->saveConfig($this->cabangA->id, [
            'enabled' => true,
            'percent' => 12.0,
            'nama' => 'PPN 12%',
            'mode' => 'exclusive',
        ]);

        $hasil = $service->hitungPenjualan($this->cabangA->id, 200000, 0);

        $this->assertEquals('PPN 12%', $hasil['nama']);
        $this->assertEquals(12.0, $hasil['ppn_percent']);
        $this->assertEquals(24000.0, $hasil['ppn_nominal']);
        $this->assertEquals(224000.0, $hasil['total_akhir']);
    }

    public function test_cabang_pkp_kena_pajak_dan_cabang_non_pkp_tanpa_pajak(): void
    {
        $service = app(PajakService::class);

        // Cabang A PKP (PPN aktif 11%)
        $service->saveConfig($this->cabangA->id, [
            'enabled' => true,
            'percent' => 11.0,
            'nama' => 'PPN',
            'mode' => 'exclusive',
        ]);

        // Cabang B Non-PKP (Pajak dinonaktifkan)
        $service->saveConfig($this->cabangB->id, [
            'enabled' => false,
            'percent' => 0.0,
            'nama' => 'PPN',
            'mode' => 'exclusive',
        ]);

        $hasilA = $service->hitungPenjualan($this->cabangA->id, 100000);
        $hasilB = $service->hitungPenjualan($this->cabangB->id, 100000);

        $this->assertTrue($hasilA['enabled']);
        $this->assertEquals(11000.0, $hasilA['ppn_nominal']);
        $this->assertEquals(111000.0, $hasilA['total_akhir']);

        $this->assertFalse($hasilB['enabled']);
        $this->assertEquals(0.0, $hasilB['ppn_nominal']);
        $this->assertEquals(100000.0, $hasilB['total_akhir']);
    }

    public function test_livewire_settings_rbac_bisa_mengatur_opsi_pajak(): void
    {
        $role = Role::create(['name' => 'super-admin']);
        $user = User::create([
            'name' => 'Super Admin',
            'email' => 'admin@uteparts.id',
            'password' => bcrypt('secret123'),
        ]);
        $user->assignRole($role);
        $user->cabangs()->attach($this->cabangA->id, ['is_default' => true]);

        Livewire::actingAs($user)
            ->test(SettingsRbac::class)
            ->set('activeTab', 'pajak')
            ->set('pajakForm.target', 'global')
            ->set('pajakForm.enabled', true)
            ->set('pajakForm.nama', 'PPN')
            ->set('pajakForm.percent', 12.0)
            ->set('pajakForm.mode', 'exclusive')
            ->call('simpanPajak')
            ->assertDispatched('alert');

        $service = app(PajakService::class);
        $cfg = $service->getConfig(null);

        $this->assertTrue($cfg['enabled']);
        $this->assertEquals('PPN', $cfg['nama']);
        $this->assertEquals(12.0, $cfg['percent']);
        $this->assertEquals('exclusive', $cfg['mode']);
    }
}
