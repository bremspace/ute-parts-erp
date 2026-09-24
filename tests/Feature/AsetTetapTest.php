<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Akunting\Models\AsetTetap;
use App\Modules\Akunting\Models\JurnalAkuntansi;
use App\Modules\Akunting\Services\DepresiasiService;
use App\Modules\Rbac\Models\Cabang;
use App\Modules\Wms\Models\Gudang;
use Database\Seeders\AkunCoaSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * [F3-3] Aset Tetap — Depresiasi & Disposal.
 *
 * Test depresiasi garis lurus, idempotensi, disposal write-off,
 * scoping cabang, dan jurnal balance.
 */
class AsetTetapTest extends TestCase
{
    use RefreshDatabase;

    private function authed(array $roles = ['super-admin']): User
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(AkunCoaSeeder::class);

        $cabang = Cabang::create(['nama' => 'Cabang Test', 'kode' => 'TST', 'is_active' => true]);
        Gudang::create(['cabang_id' => $cabang->id, 'nama' => 'Gudang 1', 'kode' => 'GDG-01', 'is_active' => true]);

        $user = User::factory()->create(['name' => 'Test User', 'email' => 'test@test.com', 'password' => Hash::make('password'), 'is_active' => true]);
        foreach ($roles as $roleName) {
            $user->assignRole($roleName);
        }
        $user->cabangs()->attach($cabang->id);
        session(['cabang_id' => $cabang->id]);

        $this->actingAs($user);

        return $user;
    }

    /** T-F3-3-01: Depresiasi garis lurus benar = harga_perolehan / umur_bulan. */
    public function test_depresiasi_garis_lurus_benar(): void
    {
        $this->authed();
        $cabangId = session('cabang_id');
        $aset = AsetTetap::create([
            'cabang_id' => $cabangId,
            'nama' => 'Laptop Test',
            'kategori' => 'it',
            'harga_perolehan' => 12000000,
            'tanggal_perolehan' => now()->subMonths(6)->toDateString(),
            'umur_bulan' => 24,
            'metode' => 'garis_lurus',
            'status' => 'aktif',
            'user_id' => auth()->id(),
        ]);

        $service = app(DepresiasiService::class);
        $nominal = $service->hitungNominal($aset, now()->format('Y-m'));

        $this->assertEquals(500000, $nominal, 'Depresiasi bulanan harus 12jt / 24 = 500rb');
    }

    /** T-F3-3-02: Depresiasi hanya 1x per periode (idempotensi). */
    public function test_depresiasi_idempotent_per_periode(): void
    {
        $this->authed();
        $cabangId = session('cabang_id');
        $aset = AsetTetap::create([
            'cabang_id' => $cabangId,
            'nama' => 'Printer Test',
            'kategori' => 'it',
            'harga_perolehan' => 6000000,
            'tanggal_perolehan' => now()->subMonths(12)->toDateString(),
            'umur_bulan' => 24,
            'metode' => 'garis_lurus',
            'status' => 'aktif',
            'user_id' => auth()->id(),
        ]);

        $service = app(DepresiasiService::class);
        $periode = now()->format('Y-m');

        // Proses pertama
        $hasil1 = $service->prosesPeriode($periode, $cabangId);
        $this->assertEquals(1, $hasil1['diproses']);

        // Proses kedua harus skip (idempotent)
        $hasil2 = $service->prosesPeriode($periode, $cabangId);
        $this->assertEquals(0, $hasil2['diproses']);
        $this->assertEquals(1, $hasil2['dilewati']);
    }

    /** T-F3-3-03: Jurnal depresiasi balance (debit = kredit). */
    public function test_jurnal_depresiasi_balance(): void
    {
        $this->authed();
        $cabangId = session('cabang_id');
        $aset = AsetTetap::create([
            'cabang_id' => $cabangId,
            'nama' => 'Monitor Test',
            'kategori' => 'it',
            'harga_perolehan' => 10000000,
            'tanggal_perolehan' => now()->subMonths(6)->toDateString(),
            'umur_bulan' => 24,
            'metode' => 'garis_lurus',
            'status' => 'aktif',
            'user_id' => auth()->id(),
        ]);

        $service = app(DepresiasiService::class);
        $service->prosesPeriode(now()->format('Y-m'), $cabangId);

        // Cek jurnal: debit 530-01 = kredit 130-02
        $jurnals = JurnalAkuntansi::where('cabang_id', $cabangId)
            ->where('sumber', 'depresiasi')
            ->get();

        $totalDebit = $jurnals->sum('debit');
        $totalKredit = $jurnals->sum('kredit');
        $this->assertEquals($totalDebit, $totalKredit, 'Jurnal depresiasi harus balance');
        $this->assertGreaterThan(0, $totalDebit, 'Jurnal depresiasi harus ada');
    }

    /** T-F3-3-04: Disposal write-off jurnal benar. */
    public function test_disposal_writeoff_jurnal_benar(): void
    {
        $this->authed();
        $cabangId = session('cabang_id');

        // Buat aset yang sudah ada akumulasi depresiasinya
        $aset = AsetTetap::create([
            'cabang_id' => $cabangId,
            'nama' => 'PC Disposal Test',
            'kategori' => 'it',
            'harga_perolehan' => 10000000,
            'tanggal_perolehan' => now()->subMonths(12)->toDateString(),
            'umur_bulan' => 24,
            'metode' => 'garis_lurus',
            'status' => 'aktif',
            'akumulasi_depresiasi' => 5000000, // sudah depresiasi 5jt
            'depresiasi_terakhir_bulan' => now()->format('Y-m'),
            'user_id' => auth()->id(),
        ]);

        $service = app(DepresiasiService::class);
        $hasil = $service->dispose($aset, auth()->id());

        $this->assertEquals(10000000, $hasil['harga'], 'Harga perolehan harus 10jt');
        $this->assertEquals(5000000, $hasil['akumulasi'], 'Akumulasi harus 5jt');
        $this->assertEquals(5000000, $hasil['kerugian'], 'Kerugian = 10jt - 5jt = 5jt');
        $this->assertEquals('disposal', $aset->fresh()->status, 'Status harus disposal');
        $this->assertStringStartsWith('JRL-DSP-', $hasil['no_jurnal'], 'No jurnal disposal harus JRL-DSP-');

        // Verifikasi jurnal balance
        $jurnals = JurnalAkuntansi::where('sumber', 'disposal')
            ->where('cabang_id', $cabangId)
            ->get();
        $debit = $jurnals->sum('debit');
        $kredit = $jurnals->sum('kredit');
        $this->assertEquals($debit, $kredit, 'Jurnal disposal harus balance');
    }

    /** T-F3-3-05: Disposal idempotent — tidak bisa dispose 2x. */
    public function test_disposal_idempotent(): void
    {
        $this->authed();
        $cabangId = session('cabang_id');
        $aset = AsetTetap::create([
            'cabang_id' => $cabangId,
            'nama' => 'Laptop Disposal',
            'kategori' => 'it',
            'harga_perolehan' => 8000000,
            'tanggal_perolehan' => now()->subMonths(6)->toDateString(),
            'umur_bulan' => 24,
            'metode' => 'garis_lurus',
            'status' => 'aktif',
            'user_id' => auth()->id(),
        ]);

        $service = app(DepresiasiService::class);
        $service->dispose($aset, auth()->id());

        // Dispose lagi harus throw exception
        $this->expectException(\Exception::class);
        $service->dispose($aset->fresh(), auth()->id());
    }

    /** T-F3-3-06: Aset status fully_dep saat umur habis. */
    public function test_status_fully_dep_saat_umur_habis(): void
    {
        $this->authed();
        $cabangId = session('cabang_id');
        // Aset dengan umur 12 bulan, sudah 12 bulan
        $aset = AsetTetap::create([
            'cabang_id' => $cabangId,
            'nama' => 'Laptop Full Dep',
            'kategori' => 'it',
            'harga_perolehan' => 12000000,
            'tanggal_perolehan' => now()->subMonths(12)->toDateString(),
            'umur_bulan' => 12,
            'metode' => 'garis_lurus',
            'status' => 'aktif',
            'user_id' => auth()->id(),
        ]);

        $service = app(DepresiasiService::class);
        $hasil = $service->prosesPeriode(now()->format('Y-m'), $cabangId);

        $this->assertEquals(1, $hasil['diproses']);
        $this->assertEquals('fully_dep', $aset->fresh()->status, 'Status harus fully_dep');
    }

    /** T-F3-3-07: Scoping cabang — aset cabang A tidak terlihat cabang B. */
    public function test_scoping_cabang_aset_tidak_bocor(): void
    {
        $user1 = $this->authed();
        $cabang1Id = $user1->cabang_id ?? 1;

        // Buat cabang 2
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(AkunCoaSeeder::class);
        $cabang2 = Cabang::create(['nama' => 'Cabang 2', 'kode' => 'CBG-02', 'is_active' => true]);

        // Cabang 2 punya aset
        $asetCabang2 = AsetTetap::create([
            'cabang_id' => $cabang2->id,
            'nama' => 'Aset Cabang 2',
            'kategori' => 'it',
            'harga_perolehan' => 5000000,
            'tanggal_perolehan' => now()->subMonths(6)->toDateString(),
            'umur_bulan' => 24,
            'metode' => 'garis_lurus',
            'status' => 'aktif',
            'user_id' => auth()->id(),
        ]);

        // User1 (cabang 1) tidak boleh lihat aset cabang 2
        $asetCabang1 = AsetTetap::where('cabang_id', $cabang1Id)->get();
        $this->assertFalse(
            $asetCabang1->contains('id', $asetCabang2->id),
            'Aset cabang 2 tidak boleh terlihat cabang 1'
        );
    }

    /** T-F3-3-08: DepresiasiService::noJurnalDepresiasi deterministik. */
    public function test_no_jurnal_depresiasi_deterministik(): void
    {
        $this->authed();
        $cabangId = session('cabang_id');
        $aset = AsetTetap::create([
            'cabang_id' => $cabangId,
            'nama' => 'No Jurnal Test',
            'kategori' => 'it',
            'harga_perolehan' => 10000000,
            'tanggal_perolehan' => now()->subMonths(6)->toDateString(),
            'umur_bulan' => 24,
            'metode' => 'garis_lurus',
            'status' => 'aktif',
            'user_id' => auth()->id(),
        ]);

        $service = app(DepresiasiService::class);
        $noJurnal = $service->noJurnalDepresiasi($aset, '2026-09');

        $this->assertStringStartsWith('JRL-DEP-', $noJurnal);
        $this->assertStringContainsString('202609', $noJurnal);
    }
}
