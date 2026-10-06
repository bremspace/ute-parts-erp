<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Hr\Livewire\PayrollPage;
use App\Modules\Hr\Models\Karyawan;
use App\Modules\Hr\Models\KaryawanKomponenGaji;
use App\Modules\Hr\Models\PayrollPeriode;
use App\Modules\Hr\Models\PayrollSlip;
use Database\Seeders\AkunCoaSeeder;
use Database\Seeders\CabangSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class PayrollPageTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected Karyawan $karyawan;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CabangSeeder::class);
        $this->seed(AkunCoaSeeder::class);

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
        Permission::firstOrCreate(['name' => 'kelola-payroll']);
        Permission::firstOrCreate(['name' => 'kelola-hr']);

        $this->admin = User::create([
            'name' => 'Finance Admin',
            'email' => 'finance@uteparts.test',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);
        $this->admin->givePermissionTo('kelola-payroll');

        $userKaryawan = User::create([
            'name' => 'Staff Test',
            'email' => 'staff@uteparts.test',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);

        $this->karyawan = Karyawan::create([
            'user_id' => $userKaryawan->id,
            'nik' => 'STF001',
            'nama' => 'Staff Test',
            'jabatan' => 'admin',
            'cabang_id' => 1,
            'tgl_masuk' => '2024-01-01',
            'gaji_pokok' => 4000000,
            'status_aktif' => true,
            'rekening_bank' => 'BCA 1234567890',
        ]);
    }

    public function test_can_view_payroll_page_and_switch_tab(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(PayrollPage::class)
            ->assertStatus(200)
            ->assertSee('HR & Payroll Management')
            ->call('setTab', 'karyawan')
            ->assertSet('activeTab', 'karyawan')
            ->assertSee('Staff Test')
            ->assertSee('Rp 4.000.000');
    }

    public function test_can_manage_karyawan_compensation_and_components(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(PayrollPage::class)
            ->call('bukaKompensasi', $this->karyawan->id)
            ->assertSet('showKompensasiModal', true)
            ->assertSet('selectedKaryawanId', $this->karyawan->id)
            // Edit gaji pokok
            ->set('inputGajiPokok', '5.500.000')
            ->call('simpanGajiPokok')
            ->assertDispatched('alert');

        $this->assertEquals(5500000, $this->karyawan->fresh()->gaji_pokok);

        // Tambah komponen tunjangan
        Livewire::test(PayrollPage::class)
            ->call('bukaKompensasi', $this->karyawan->id)
            ->set('newKomponenTipe', 'tunjangan')
            ->set('newKomponenNama', 'Uang Makan & Transport')
            ->set('newKomponenNominal', '750.000')
            ->call('tambahKomponen')
            ->assertDispatched('alert');

        $komponen = KaryawanKomponenGaji::where('karyawan_id', $this->karyawan->id)->first();
        $this->assertNotNull($komponen);
        $this->assertEquals('tunjangan', $komponen->tipe);
        $this->assertEquals('Uang Makan & Transport', $komponen->nama);
        $this->assertEquals(750000, $komponen->nominal_bulanan);
        $this->assertTrue($komponen->is_aktif);

        // Toggle status aktif komponen
        Livewire::test(PayrollPage::class)
            ->call('bukaKompensasi', $this->karyawan->id)
            ->call('toggleKomponen', $komponen->id);

        $this->assertFalse($komponen->fresh()->is_aktif);

        // Hapus komponen
        Livewire::test(PayrollPage::class)
            ->call('bukaKompensasi', $this->karyawan->id)
            ->call('hapusKomponen', $komponen->id);

        $this->assertDatabaseMissing('karyawan_komponen_gaji', ['id' => $komponen->id]);
    }

    public function test_can_open_slip_detail_modal(): void
    {
        $this->actingAs($this->admin);

        $periode = PayrollPeriode::create([
            'periode' => '2026-03',
            'tanggal_mulai' => '2026-03-01',
            'tanggal_selesai' => '2026-03-31',
            'status' => 'draft',
        ]);

        $slip = PayrollSlip::create([
            'payroll_periode_id' => $periode->id,
            'karyawan_id' => $this->karyawan->id,
            'gaji_pokok' => 4000000,
            'total_tunjangan' => 500000,
            'total_potongan' => 200000,
            'total_komisi' => 300000,
            'total_gaji' => 4600000,
            'status' => 'draft',
            'rincian' => json_encode([
                'pokok' => 4000000,
                'tunjangan' => 500000,
                'potongan' => 200000,
                'potongan_absen' => 0,
                'komisi' => 300000,
                'komisi_teknisi' => 300000,
                'komisi_internal' => 0,
            ]),
        ]);

        Livewire::test(PayrollPage::class)
            ->set('periode', '2026-03')
            ->call('bukaDetailSlip', $slip->id)
            ->assertSet('showDetailSlipModal', true)
            ->assertSee('Rincian Slip Gaji Karyawan')
            ->assertSee('Rp 4.600.000');
    }
}
