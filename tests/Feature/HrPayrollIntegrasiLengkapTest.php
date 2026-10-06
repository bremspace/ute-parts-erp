<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Hr\Livewire\HrSayaPage;
use App\Modules\Hr\Livewire\PayrollPage;
use App\Modules\Hr\Models\AbsensiLog;
use App\Modules\Hr\Models\Karyawan;
use App\Modules\Hr\Models\PayrollPeriode;
use App\Modules\Hr\Models\PayrollSlip;
use App\Modules\Hr\Services\PayrollService;
use App\Modules\Pos\Models\Transaksi;
use App\Modules\Pos\Models\TransaksiItem;
use App\Modules\Rbac\Models\Cabang;
use App\Modules\Reseller\Models\Komisi;
use App\Modules\Reseller\Models\KomisiSkema;
use App\Modules\Reseller\Services\KomisiService;
use App\Modules\Wms\Models\Produk;
use Database\Seeders\AkunCoaSeeder;
use Database\Seeders\CabangSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class HrPayrollIntegrasiLengkapTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Karyawan $karyawan;

    protected Cabang $cabang;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->seed(AkunCoaSeeder::class);
        $this->seed(CabangSeeder::class);

        Permission::firstOrCreate(['name' => 'kelola-payroll', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'kelola-hr', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'hr.lihat-sendiri', 'guard_name' => 'web']);

        $this->cabang = Cabang::first();

        $this->user = User::factory()->create();
        $this->user->givePermissionTo(['kelola-payroll', 'kelola-hr', 'hr.lihat-sendiri']);

        $this->karyawan = Karyawan::create([
            'user_id' => $this->user->id,
            'nik' => 'EMP-TEST-001',
            'nama' => 'Karyawan Test',
            'jabatan' => 'teknisi',
            'cabang_id' => $this->cabang->id,
            'tgl_masuk' => now()->subMonths(6)->toDateString(),
            'gaji_pokok' => 4000000,
            'status_aktif' => true,
        ]);

        session(['cabang_id' => $this->cabang->id]);
    }

    public function test_hr_saya_page_clock_in_clock_out_dan_slip(): void
    {
        $this->actingAs($this->user);

        Livewire::test(HrSayaPage::class)
            ->call('clockIn')
            ->assertDispatched('alert');

        $log = AbsensiLog::where('karyawan_id', $this->karyawan->id)
            ->where('tanggal', now()->toDateString())
            ->first();

        $this->assertNotNull($log);
        $this->assertNotNull($log->jam_masuk);
        $this->assertNull($log->jam_keluar);

        Livewire::test(HrSayaPage::class)
            ->call('clockOut')
            ->assertDispatched('alert');

        $log->refresh();
        $this->assertNotNull($log->jam_keluar);
    }

    public function test_komisi_kasir_tercatat_dan_terintegrasi_ke_payroll(): void
    {
        $kasirUser = User::factory()->create();
        $kasirKaryawan = Karyawan::create([
            'user_id' => $kasirUser->id,
            'nik' => 'KSR-001',
            'nama' => 'Kasir Teladan',
            'jabatan' => 'kasir',
            'cabang_id' => $this->cabang->id,
            'tgl_masuk' => now()->subMonths(3)->toDateString(),
            'gaji_pokok' => 3000000,
            'status_aktif' => true,
        ]);

        KomisiSkema::create([
            'nama' => 'Komisi Kasir 2%',
            'aktor_tipe' => 'karyawan',
            'trigger_tipe' => 'penjualan',
            'tipe' => 'persen',
            'nilai' => 2.0,
            'min_amount' => 0,
            'is_aktif' => true,
        ]);

        $transaksi = Transaksi::create([
            'no_transaksi' => 'TRX-TEST-001',
            'cabang_id' => $this->cabang->id,
            'kasir_id' => $kasirUser->id,
            'subtotal' => 1000000,
            'total_akhir' => 1000000,
            'status' => 'selesai',
            'metode_pembayaran' => 'tunai',
        ]);

        $produk = Produk::create([
            'nama' => 'LCD Test',
            'kategori' => 'umum',
            'harga_jual_retail' => 1000000,
            'is_active' => true,
        ]);

        TransaksiItem::create([
            'transaksi_id' => $transaksi->id,
            'produk_id' => $produk->id,
            'harga_satuan' => 1000000,
            'jumlah' => 1,
            'subtotal' => 1000000,
        ]);

        $komisiService = app(KomisiService::class);
        $hasil = $komisiService->hitungKomisiMultiAktor('penjualan', ['transaksi_id' => $transaksi->id]);

        $this->assertNotEmpty($hasil);
        $komisiRecord = Komisi::where('aktor_tipe', 'karyawan')
            ->where('aktor_id', $kasirKaryawan->id)
            ->first();

        $this->assertNotNull($komisiRecord);
        $this->assertEquals(20000, (float) $komisiRecord->nominal_komisi);

        $periode = now()->format('Y-m');
        $payrollService = app(PayrollService::class);
        $result = $payrollService->hitungDraft($periode);

        $this->assertGreaterThan(0, $result['total_gaji']);

        $slipKasir = PayrollSlip::where('karyawan_id', $kasirKaryawan->id)->first();
        $this->assertNotNull($slipKasir);
        $this->assertEquals(20000, (float) $slipKasir->total_komisi);
        $this->assertEquals(3020000, (float) $slipKasir->total_gaji);
    }

    public function test_payroll_page_tambah_karyawan_dan_eksekusi_bayar(): void
    {
        $this->actingAs($this->user);

        Livewire::test(PayrollPage::class)
            ->set('inputNama', 'Budi Santoso')
            ->set('inputNik', 'EMP-BUDI-001')
            ->set('inputJabatan', 'staff')
            ->set('inputKaryawanGajiPokok', '3500000')
            ->set('inputTglMasuk', now()->toDateString())
            ->call('simpanKaryawan')
            ->assertDispatched('alert');

        $karyawanBaru = Karyawan::where('nik', 'EMP-BUDI-001')->first();
        $this->assertNotNull($karyawanBaru);
        $this->assertEquals('Budi Santoso', $karyawanBaru->nama);
        $this->assertEquals(3500000, (float) $karyawanBaru->gaji_pokok);

        $periode = now()->format('Y-m');

        Livewire::test(PayrollPage::class)
            ->set('periode', $periode)
            ->call('hitungDraft')
            ->assertDispatched('alert')
            ->call('finalisasiDisetujui')
            ->call('bayarPayroll')
            ->assertDispatched('alert');

        $periodeRecord = PayrollPeriode::where('periode', $periode)->first();
        $this->assertNotNull($periodeRecord);
        $this->assertEquals('dibayar', $periodeRecord->status);
    }
}
