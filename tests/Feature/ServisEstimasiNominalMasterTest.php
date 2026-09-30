<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Crm\Models\Pelanggan;
use App\Modules\Rbac\Livewire\SettingsRbac;
use App\Modules\Rbac\Models\Cabang;
use App\Modules\Servis\Livewire\ServisBoard;
use App\Modules\Servis\Models\JenisServis;
use App\Modules\Servis\Models\TiketServis;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ServisEstimasiNominalMasterTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Cabang $cabang;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cabang = Cabang::create([
            'nama' => 'Cabang Test Estimasi',
            'kode' => 'CTE-01',
            'is_pusat' => true,
            'is_active' => true,
        ]);

        $roleAdmin = Role::firstOrCreate(['name' => 'super-admin']);
        Role::firstOrCreate(['name' => 'teknisi']);
        Role::firstOrCreate(['name' => 'admin-toko']);
        $perms = [
            'servis.view', 'servis.create', 'servis.update-status',
            'servis.approve-estimasi', 'servis.input-sparepart', 'settings.manage',
        ];
        foreach ($perms as $p) {
            Permission::firstOrCreate(['name' => $p]);
        }
        $roleAdmin->syncPermissions(Permission::all());

        $this->user = User::factory()->create();
        $this->user->assignRole($roleAdmin);
        $this->user->cabangs()->attach($this->cabang->id, ['is_default' => true]);

        session(['cabang_id' => $this->cabang->id]);
        $this->actingAs($this->user);
    }

    public function test_master_data_jenis_servis_dapat_set_biaya_jasa(): void
    {
        Livewire::test(SettingsRbac::class)
            ->call('openJenisServisModal')
            ->set('jenisServisForm.nama', 'Ganti Port Charger')
            ->set('jenisServisForm.kode', 'SVC-CHG')
            ->set('jenisServisForm.biaya_jasa', 65000)
            ->set('jenisServisForm.kategori', 'hardware')
            ->call('simpanJenisServis')
            ->assertDispatched('alert', fn ($event, $params) => ($params[0]['type'] ?? $params['type']) === 'success');

        $jenis = JenisServis::where('kode', 'SVC-CHG')->first();
        $this->assertNotNull($jenis);
        $this->assertEquals(65000.0, (float) $jenis->biaya_jasa);

        // Edit kembali tarifnya
        Livewire::test(SettingsRbac::class)
            ->call('openJenisServisModal', $jenis->id)
            ->assertSet('jenisServisForm.biaya_jasa', 65000.0)
            ->set('jenisServisForm.biaya_jasa', 80000)
            ->call('simpanJenisServis')
            ->assertDispatched('alert');

        $this->assertEquals(80000.0, (float) $jenis->fresh()->biaya_jasa);
    }

    public function test_estimasi_modal_mengambil_tarif_master_dan_menerima_format_nominal_ribuan(): void
    {
        $jenis = JenisServis::create([
            'nama' => 'Ganti Kamera Belakang',
            'kode' => 'SVC-CAM',
            'biaya_jasa' => 125000,
            'is_active' => true,
        ]);

        $pelanggan = Pelanggan::create([
            'nama' => 'Budi Santoso',
            'telepon' => '081234567890',
            'cabang_id' => $this->cabang->id,
        ]);

        $tiket = TiketServis::create([
            'cabang_id' => $this->cabang->id,
            'no_tiket' => 'SRV-TEST-NOMINAL',
            'pelanggan_id' => $pelanggan->id,
            'nama_pelanggan' => $pelanggan->nama,
            'telepon_pelanggan' => $pelanggan->telepon,
            'jenis_hp' => 'Samsung S22',
            'keluhan' => 'Kamera buram',
            'status' => 'diagnosa',
            'tanggal_terima' => now(),
            'jenis_servis_id' => $jenis->id,
        ]);

        $component = Livewire::test(ServisBoard::class)
            ->call('openEstimasiModal', $tiket->id)
            ->assertSet('showEstimasiModal', true)
            // Pre-select tarif default dari JenisServis master data
            ->assertSet('estimasiItems.0.harga', 125000.0)
            ->assertSet('estimasiBiaya', 125000.0);

        // Input manual dengan format ribuan bertitik khas Indonesia "175.000"
        $component->set('estimasiItems.0.harga', '175.000')
            ->assertSet('estimasiBiaya', 175000.0);

        // Input transisi saat mengetik "50000" dengan format debounced "5.0000"
        $component->set('estimasiItems.0.harga', '5.0000')
            ->assertSet('estimasiBiaya', 50000.0);

        // Set kembali ke 175.000 untuk submit
        $component->set('estimasiItems.0.harga', '175.000')
            ->assertSet('estimasiBiaya', 175000.0);

        // Kosongkan alasan (tetap boleh submit, tidak silent error)
        $component->set('estimasiAlasan', '')
            ->call('simpanEstimasi')
            ->assertDispatched('alert', fn ($event, $params) => ($params[0]['type'] ?? $params['type']) === 'success')
            ->assertSet('showEstimasiModal', false);

        $tiket->refresh();
        $this->assertSame('menunggu_approval', $tiket->status);
        $this->assertEquals(175000.0, (float) $tiket->estimasi_biaya);
        $this->assertSame('Estimasi perbaikan unit', $tiket->alasan_estimasi);
        $this->assertCount(1, $tiket->estimasiItems);
        $this->assertEquals(175000.0, (float) $tiket->estimasiItems->first()->harga);
    }

    public function test_re_estimasi_pada_tiket_menunggu_approval_berhasil(): void
    {
        $jenis = JenisServis::create([
            'nama' => 'Servis Speaker',
            'kode' => 'SVC-SPK',
            'biaya_jasa' => 50000,
            'is_active' => true,
        ]);

        $tiket = TiketServis::create([
            'cabang_id' => $this->cabang->id,
            'no_tiket' => 'SRV-RE-ESTIMASI',
            'nama_pelanggan' => 'Siti',
            'telepon_pelanggan' => '0899887766',
            'jenis_hp' => 'Redmi Note 10',
            'keluhan' => 'Suara kresek',
            'status' => 'menunggu_approval',
            'estimasi_biaya' => 50000,
            'alasan_estimasi' => 'Estimasi awal',
            'token_approval' => 'token-test-1234567890',
            'tanggal_terima' => now(),
            'jenis_servis_id' => $jenis->id,
        ]);

        Livewire::test(ServisBoard::class)
            ->call('openEstimasiModal', $tiket->id)
            ->assertSet('showEstimasiModal', true)
            ->set('estimasiItems.0.harga', '75000')
            ->set('estimasiAlasan', 'Revisi setelah pengecekan modul IC audio')
            ->call('simpanEstimasi')
            ->assertDispatched('alert', fn ($event, $params) => ($params[0]['type'] ?? $params['type']) === 'success');

        $tiket->refresh();
        $this->assertSame('menunggu_approval', $tiket->status);
        $this->assertEquals(75000.0, (float) $tiket->estimasi_biaya);
        $this->assertSame('token-test-1234567890', $tiket->token_approval);
    }
}
