<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Akunting\Models\AkunCOA;
use App\Modules\Crm\Models\Pelanggan;
use App\Modules\Hr\Models\Karyawan;
use App\Modules\Hr\Services\PayrollService;
use App\Modules\Pos\Models\Transaksi;
use App\Modules\Rbac\Models\Cabang;
use App\Modules\Reseller\Livewire\ResellerDashboard;
use App\Modules\Reseller\Models\Komisi;
use App\Modules\Reseller\Services\KomisiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class KomisiApprovalDualFlowTest extends TestCase
{
    use RefreshDatabase;

    protected Cabang $cabang;

    protected User $financeUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cabang = Cabang::create([
            'nama' => 'Cabang Pusat',
            'kode' => 'CP-01',
            'alamat' => 'Jl. Pusat',
            'is_active' => true,
        ]);

        // COA
        AkunCOA::create(['kode' => '510-01', 'nama' => 'Beban Komisi Reseller', 'tipe' => 'beban', 'kelompok' => 'beban_komisi', 'saldo_normal' => 'debit', 'is_active' => true]);
        AkunCOA::create(['kode' => '210-03', 'nama' => 'Utang Komisi', 'tipe' => 'kewajiban', 'kelompok' => 'utang_komisi', 'saldo_normal' => 'kredit', 'is_active' => true]);
        AkunCOA::create(['kode' => '520-01', 'nama' => 'Beban Gaji', 'tipe' => 'beban', 'kelompok' => 'beban_operasional', 'saldo_normal' => 'debit', 'is_active' => true]);
        AkunCOA::create(['kode' => '520-09', 'nama' => 'Beban Komisi Teknisi', 'tipe' => 'beban', 'kelompok' => 'beban_operasional', 'saldo_normal' => 'debit', 'is_active' => true]);
        AkunCOA::create(['kode' => '210-02', 'nama' => 'Hutang Gaji', 'tipe' => 'kewajiban', 'kelompok' => 'utang_gaji', 'saldo_normal' => 'kredit', 'is_active' => true]);

        Permission::findOrCreate('komisi.approve', 'web');
        Permission::findOrCreate('reseller.view', 'web');

        $this->financeUser = User::factory()->create();
        $this->financeUser->givePermissionTo(['komisi.approve', 'reseller.view']);
    }

    public function test_approval_komisi_reseller_membuat_jurnal_dan_utang(): void
    {
        $this->actingAs($this->financeUser);
        session(['cabang_id' => $this->cabang->id]);

        $pelanggan = Pelanggan::create([
            'nama' => 'Mitra Reseller Surabaya',
            'telepon' => '081234567890',
            'is_reseller' => true,
        ]);

        $trx = Transaksi::create([
            'no_transaksi' => 'TRX-RSL-001',
            'cabang_id' => $this->cabang->id,
            'pelanggan_id' => $pelanggan->id,
            'subtotal' => 1000000,
            'diskon_persen' => 0,
            'diskon_nominal' => 0,
            'total_akhir' => 1000000,
            'metode_bayar' => 'tunai',
            'status' => 'selesai',
        ]);

        $komisi = Komisi::create([
            'no_komisi' => 'KMS-RSL-001',
            'aktor_tipe' => 'reseller',
            'aktor_id' => $pelanggan->id,
            'pelanggan_id' => $pelanggan->id,
            'transaksi_id' => $trx->id,
            'jumlah_transaksi' => 1000000,
            'nominal_komisi' => 50000,
            'status' => 'pending',
        ]);

        $approvedIds = app(KomisiService::class)->prosesApproval([$komisi->id], 'approve', $this->financeUser->id);

        $this->assertContains($komisi->id, $approvedIds);
        $this->assertSame('disetujui', $komisi->fresh()->status);

        // Jurnal 510-01 / 210-03 terbentuk untuk reseller
        $akunBeban = AkunCOA::where('kode', '510-01')->firstOrFail();
        $this->assertDatabaseHas('jurnal_akuntansi', [
            'referensi_tipe' => Komisi::class,
            'referensi_id' => $komisi->id,
            'akun_coa_id' => $akunBeban->id,
            'debit' => 50000,
        ]);

        // Entri Utang dagang terbentuk ke pelanggan
        $this->assertDatabaseHas('utang', [
            'referensi_tipe' => 'komisi',
            'referensi_id' => $komisi->id,
            'pelanggan_id' => $pelanggan->id,
            'kreditor_nama' => 'Mitra Reseller Surabaya',
            'jumlah' => 50000,
        ]);
    }

    public function test_approval_komisi_karyawan_tidak_dobel_jurnal_dan_masuk_payroll(): void
    {
        $this->actingAs($this->financeUser);
        session(['cabang_id' => $this->cabang->id]);

        $userKaryawan = User::factory()->create(['name' => 'Budi Teknisi']);
        $karyawan = Karyawan::create([
            'user_id' => $userKaryawan->id,
            'nik' => 'EMP-001',
            'nama' => 'Budi Teknisi',
            'jabatan' => 'Teknisi Senior',
            'cabang_id' => $this->cabang->id,
            'tgl_masuk' => now()->subYear(),
            'gaji_pokok' => 3000000,
            'status_aktif' => true,
        ]);

        $komisiInternal = Komisi::create([
            'no_komisi' => 'KMS-INT-001',
            'aktor_tipe' => 'karyawan',
            'aktor_id' => $karyawan->id,
            'pelanggan_id' => null,
            'transaksi_id' => null,
            'jumlah_transaksi' => 500000,
            'nominal_komisi' => 100000,
            'status' => 'pending',
            'keterangan' => 'Komisi servis tiket #TS-01',
        ]);

        // Approve via KomisiService
        $approvedIds = app(KomisiService::class)->prosesApproval([$komisiInternal->id], 'approve', $this->financeUser->id);

        $this->assertContains($komisiInternal->id, $approvedIds);
        $this->assertSame('disetujui', $komisiInternal->fresh()->status);

        // Karyawan internal: TIDAK BOLEH ada jurnal 510-01/210-03 terpisah
        $this->assertDatabaseMissing('jurnal_akuntansi', [
            'referensi_tipe' => Komisi::class,
            'referensi_id' => $komisiInternal->id,
        ]);

        // Karyawan internal: TIDAK BOLEH ada entri Utang dagang
        $this->assertDatabaseMissing('utang', [
            'referensi_tipe' => 'komisi',
            'referensi_id' => $komisiInternal->id,
        ]);

        // Verifikasi masuk ke slip Payroll bulanan
        $periode = now()->format('Y-m');
        $payrollResult = app(PayrollService::class)->hitungDraft($periode);

        $this->assertGreaterThan(0, $payrollResult['total_gaji']);

        // Gaji pokok 3.000.000 + komisi 100.000 = 3.100.000
        $this->assertDatabaseHas('payroll_slip', [
            'karyawan_id' => $karyawan->id,
            'total_komisi' => 100000,
            'total_gaji' => 3100000,
        ]);

        // Finalisasi & approval payroll -> dijurnal terpadu 520-09 Beban Komisi Teknisi / 210-02 Hutang Gaji
        app(PayrollService::class)->approvePayroll($periode);

        $akunKomisiTeknisi = AkunCOA::where('kode', '520-09')->firstOrFail();
        $akunHutangGaji = AkunCOA::where('kode', '210-02')->firstOrFail();

        $this->assertDatabaseHas('jurnal_akuntansi', [
            'sumber' => 'payroll',
            'akun_coa_id' => $akunKomisiTeknisi->id,
            'debit' => 100000,
        ]);
        $this->assertDatabaseHas('jurnal_akuntansi', [
            'sumber' => 'payroll',
            'akun_coa_id' => $akunHutangGaji->id,
            'kredit' => 3100000,
        ]);
    }

    public function test_dashboard_komisi_filter_dan_accessor_nama_penerima(): void
    {
        $this->actingAs($this->financeUser);
        session(['cabang_id' => $this->cabang->id]);

        $pelanggan = Pelanggan::create([
            'nama' => 'Reseller Toko Sebelah',
            'telepon' => '089988776655',
            'is_reseller' => true,
        ]);

        $karyawan = Karyawan::create([
            'user_id' => User::factory()->create()->id,
            'nik' => 'EMP-002',
            'nama' => 'Siti Sales',
            'jabatan' => 'Sales CRM',
            'cabang_id' => $this->cabang->id,
            'tgl_masuk' => now()->subMonth(),
            'gaji_pokok' => 2500000,
            'status_aktif' => true,
        ]);

        $komisiRsl = Komisi::create([
            'no_komisi' => 'KMS-F-001',
            'aktor_tipe' => 'reseller',
            'aktor_id' => $pelanggan->id,
            'pelanggan_id' => $pelanggan->id,
            'nominal_komisi' => 20000,
            'status' => 'pending',
        ]);

        $komisiEmp = Komisi::create([
            'no_komisi' => 'KMS-F-002',
            'aktor_tipe' => 'karyawan',
            'aktor_id' => $karyawan->id,
            'nominal_komisi' => 30000,
            'status' => 'pending',
            'keterangan' => 'Lead Won Deal #99',
        ]);

        // Cek accessor
        $this->assertSame('Reseller Toko Sebelah', $komisiRsl->nama_penerima);
        $this->assertSame('Siti Sales', $komisiEmp->nama_penerima);
        $this->assertSame('Lead Won Deal #99', $komisiEmp->referensi_doc);

        // Test Livewire component filtering
        Livewire::test(ResellerDashboard::class)
            ->set('activeTab', 'komisi')
            ->set('filterAktor', 'reseller')
            ->assertSee('Reseller Toko Sebelah')
            ->assertDontSee('Siti Sales')
            ->set('filterAktor', 'karyawan')
            ->assertSee('Siti Sales')
            ->assertDontSee('Reseller Toko Sebelah');
    }
}
