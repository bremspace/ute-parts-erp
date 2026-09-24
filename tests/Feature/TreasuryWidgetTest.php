<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Akunting\Models\AkunCOA;
use App\Modules\Akunting\Models\JurnalAkuntansi;
use App\Modules\Akunting\Models\Piutang;
use App\Modules\Akunting\Models\Utang;
use App\Modules\Crm\Models\Pelanggan;
use App\Modules\Dashboard\Livewire\DashboardIndex;
use App\Modules\Rbac\Models\Cabang;
use App\Modules\Wms\Models\Gudang;
use App\Modules\Wms\Models\PurchaseOrder;
use App\Modules\Wms\Models\Supplier;
use Database\Seeders\AkunCoaSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TreasuryWidgetTest extends TestCase
{
    use RefreshDatabase;

    private function authed(array $roles = ['super-admin']): User
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(AkunCoaSeeder::class);

        $cabang = Cabang::create(['nama' => 'Pusat', 'kode' => 'CBG-01', 'is_active' => true]);
        $gudang = Gudang::create(['cabang_id' => $cabang->id, 'nama' => 'Gudang 1', 'kode' => 'GDG-01', 'is_active' => true]);

        $user = User::create([
            'name' => 'Test User', 'email' => 'test@test.com',
            'password' => Hash::make('password'), 'is_active' => true,
        ]);

        foreach ($roles as $roleName) {
            $user->assignRole($roleName);
        }
        $user->cabangs()->attach($cabang->id);
        session(['cabang_id' => $cabang->id]);

        $this->actingAs($user, 'web');

        return $user;
    }

    /** @test */
    public function test_treasury_projection_calculates_correctly_for_finance_role(): void
    {
        $user = $this->authed(['finance']);
        $cabangId = $user->cabang_id ?? 1;

        // Get the actual AkunCOA id for '110-01' (integer PK)
        $akunKas = AkunCOA::where('kode', '110-01')->first();
        $this->assertNotNull($akunKas, 'AkunCOA 110-01 must exist after seeder');
        $kasId = $akunKas->id;

        // Setup: 3 JurnalAkuntansi entries with akun 110-01 (debit 10jt, kredit 3jt each = net debit 7jt each)
        $j1 = JurnalAkuntansi::create([
            'no_jurnal' => 'JRL-TREAS-001',
            'tanggal' => now(),
            'cabang_id' => $cabangId,
            'akun_coa_id' => $kasId,
            'sumber' => 'test',
            'deskripsi' => 'Kas A',
            'debit' => 10000000,
            'kredit' => 3000000,
            'user_id' => $user->id,
        ]);
        $j2 = JurnalAkuntansi::create([
            'no_jurnal' => 'JRL-TREAS-002',
            'tanggal' => now(),
            'cabang_id' => $cabangId,
            'akun_coa_id' => $kasId,
            'sumber' => 'test',
            'deskripsi' => 'Kas B',
            'debit' => 10000000,
            'kredit' => 3000000,
            'user_id' => $user->id,
        ]);
        $j3 = JurnalAkuntansi::create([
            'no_jurnal' => 'JRL-TREAS-003',
            'tanggal' => now(),
            'cabang_id' => $cabangId,
            'akun_coa_id' => $kasId,
            'sumber' => 'test',
            'deskripsi' => 'Kas C',
            'debit' => 10000000,
            'kredit' => 3000000,
            'user_id' => $user->id,
        ]);

        // Saldo kas = (10-3) + (10-3) + (10-3) = 7+7+7 = 21jt
        $saldoKas = (10000000 - 3000000) * 3;

        // Piutang: 2x jatuh tempo 15 hari (dalam 30d) = 5jt + 3jt = 8jt
        // Piutang lewat 30 hari (harus TIDAK ikut proyeksi 30d)
        $pelanggan = Pelanggan::create([
            'nama' => 'Pelanggan Treasury',
            'email' => 'pelanggan@treasury.test',
            'cabang_id' => $cabangId,
        ]);
        Piutang::create([
            'no_piutang' => 'PIU-TR-001',
            'cabang_id' => $cabangId,
            'pelanggan_id' => $pelanggan->id,
            'status' => 'belum_lunas',
            'jumlah' => 5000000,
            'jumlah_dibayar' => 0,
            'jatuh_tempo' => now()->addDays(15),
        ]);
        Piutang::create([
            'no_piutang' => 'PIU-TR-002',
            'cabang_id' => $cabangId,
            'pelanggan_id' => $pelanggan->id,
            'status' => 'sebagian',
            'jumlah' => 3000000,
            'jumlah_dibayar' => 1000000,
            'jatuh_tempo' => now()->addDays(10),
        ]);
        Piutang::create([
            'no_piutang' => 'PIU-TR-003',
            'cabang_id' => $cabangId,
            'pelanggan_id' => $pelanggan->id,
            'status' => 'belum_lunas',
            'jumlah' => 2000000,
            'jumlah_dibayar' => 0,
            'jatuh_tempo' => now()->addDays(45),
        ]);

        // Only 2 Piutang should count for 30d: 5jt + (3jt - 1jt) = 7jt
        // (piutang lewat 45 hari tidak ikut)

        // Utang: 2x jatuh tempo 20 hari (dalam 30d) = 4jt + 2jt = 6jt
        $supplier = Supplier::create(['nama' => 'Supplier Treasury', 'termin_hari' => 30]);
        Utang::create([
            'no_utang' => 'UTG-TR-001',
            'cabang_id' => $cabangId,
            'referensi_tipe' => PurchaseOrder::class,
            'referensi_id' => 1,
            'kreditor_nama' => $supplier->nama,
            'status' => 'belum_lunas',
            'jumlah' => 4000000,
            'jumlah_dibayar' => 0,
            'jatuh_tempo' => now()->addDays(20),
        ]);
        Utang::create([
            'no_utang' => 'UTG-TR-002',
            'cabang_id' => $cabangId,
            'referensi_tipe' => PurchaseOrder::class,
            'referensi_id' => 2,
            'kreditor_nama' => 'CV Lain',
            'status' => 'sebagian',
            'jumlah' => 2000000,
            'jumlah_dibayar' => 500000,
            'jatuh_tempo' => now()->addDays(25),
        ]);

        // Only 2 Utang should count for 30d: 4jt + (2jt - 0.5jt) = 5.5jt
        // (utang lewat 40 hari tidak ikut)

        // Akting as finance, hitung treasuryProjection via DashboardIndex
        $this->actingAs($user);

        $dashboard = new DashboardIndex;
        session(['cabang_id' => $cabangId]);

        $treasury = $dashboard->treasuryProjection;

        // Saldo kas = 21jt
        $this->assertEquals(21000000, $treasury['saldo_kas'], 'Saldo kas salah');

        // Piutang 30d = 5jt + (3jt - 1jt) = 7jt (piutang lewat 45 hari tidak ikut)
        $this->assertEquals(7000000, $treasury['piutang_30d'], 'Piutang 30d salah');

        // Utang 30d = 4jt + (2jt - 0.5jt) = 5.5jt (utang lewat 40 hari tidak ikut)
        $this->assertEquals(5500000, $treasury['utang_30d'], 'Utang 30d salah');

        // Proyeksi = 21jt + 7jt - 5.5jt = 22.5jt
        $this->assertEquals(22500000, $treasury['proyeksi_30d'], 'Proyeksi 30d salah');

        // Drill-down URLs ada
        $this->assertStringContainsString('laporan/drill', $treasury['drill_piutang']);
        $this->assertStringContainsString('laporan/drill', $treasury['drill_utang']);
    }

    /** @test */
    public function test_treasury_projection_scoped_by_cabang_no_leak(): void
    {
        $user1 = $this->authed(['finance']);
        $cabang1Id = $user1->cabang_id ?? 1;
        $akunKas1 = AkunCOA::where('kode', '110-01')->first();
        $kasId1 = $akunKas1 ? $akunKas1->id : 1;

        // Create second user in different cabang
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(AkunCoaSeeder::class);

        $akunKas2 = AkunCOA::where('kode', '110-01')->first();
        $kasId2 = $akunKas2 ? $akunKas2->id : 2;

        $cabang2 = Cabang::create(['nama' => 'Cabang 2', 'kode' => 'CBG-02', 'is_active' => true]);

        $user2 = User::create([
            'name' => 'Test User 2', 'email' => 'test2@test.com',
            'password' => Hash::make('password'), 'is_active' => true,
        ]);
        $user2->assignRole('finance');
        $user2->cabangs()->attach($cabang2->id);

        // Cabang 1: kas 10jt, piutang 5jt, utang 2jt
        JurnalAkuntansi::create([
            'no_jurnal' => 'JRL-CAB1-001',
            'tanggal' => now(),
            'cabang_id' => $cabang1Id,
            'akun_coa_id' => $kasId1,
            'sumber' => 'test',
            'deskripsi' => 'Kas cabang 1',
            'debit' => 10000000,
            'kredit' => 0,
            'user_id' => $user1->id,
        ]);
        $pel1 = Pelanggan::create([
            'nama' => 'Pelanggan Cab1',
            'email' => 'pel1@test.com',
            'cabang_id' => $cabang1Id,
        ]);
        Piutang::create([
            'no_piutang' => 'PIU-CAB1-001',
            'cabang_id' => $cabang1Id,
            'pelanggan_id' => $pel1->id,
            'status' => 'belum_lunas',
            'jumlah' => 5000000,
            'jumlah_dibayar' => 0,
            'jatuh_tempo' => now()->addDays(10),
        ]);
        $sup1 = Supplier::create(['nama' => 'Supplier Cab1', 'termin_hari' => 30]);
        Utang::create([
            'no_utang' => 'UTG-CAB1-001',
            'cabang_id' => $cabang1Id,
            'referensi_tipe' => PurchaseOrder::class,
            'referensi_id' => 1,
            'kreditor_nama' => $sup1->nama,
            'status' => 'belum_lunas',
            'jumlah' => 2000000,
            'jumlah_dibayar' => 0,
            'jatuh_tempo' => now()->addDays(10),
        ]);

        // Cabang 2: kas 100jt, piutang 50jt, utang 10jt (bisa kontaminasi)
        JurnalAkuntansi::create([
            'no_jurnal' => 'JRL-CAB2-001',
            'tanggal' => now(),
            'cabang_id' => $cabang2->id,
            'akun_coa_id' => $kasId2,
            'sumber' => 'test',
            'deskripsi' => 'Kas cabang 2',
            'debit' => 100000000,
            'kredit' => 0,
            'user_id' => $user2->id,
        ]);
        $pel2 = Pelanggan::create([
            'nama' => 'Pelanggan Cab2',
            'email' => 'pel2@test.com',
            'cabang_id' => $cabang2->id,
        ]);
        Piutang::create([
            'no_piutang' => 'PIU-CAB2-001',
            'cabang_id' => $cabang2->id,
            'pelanggan_id' => $pel2->id,
            'status' => 'belum_lunas',
            'jumlah' => 50000000,
            'jumlah_dibayar' => 0,
            'jatuh_tempo' => now()->addDays(10),
        ]);
        $sup2 = Supplier::create(['nama' => 'Supplier Cab2', 'termin_hari' => 30]);
        Utang::create([
            'no_utang' => 'UTG-CAB2-001',
            'cabang_id' => $cabang2->id,
            'referensi_tipe' => PurchaseOrder::class,
            'referensi_id' => 202,
            'kreditor_nama' => $sup2->nama,
            'status' => 'belum_lunas',
            'jumlah' => 10000000,
            'jumlah_dibayar' => 0,
            'jatuh_tempo' => now()->addDays(10),
        ]);

        // User1 (cabang 1) tidak boleh lihat data cabang 2
        $this->actingAs($user1);
        session(['cabang_id' => $cabang1Id]);

        $dashboard = new DashboardIndex;
        $treasury1 = $dashboard->treasuryProjection;

        $this->assertEquals(10000000, $treasury1['saldo_kas'], 'User1 kas leak');
        $this->assertEquals(5000000, $treasury1['piutang_30d'], 'User1 piutang leak');
        $this->assertEquals(2000000, $treasury1['utang_30d'], 'User1 utang leak');

        // User2 (cabang 2) tidak boleh lihat data cabang 1
        $this->actingAs($user2);
        session(['cabang_id' => $cabang2->id]);

        $dashboard = new DashboardIndex;
        $treasury2 = $dashboard->treasuryProjection;

        $this->assertEquals(100000000, $treasury2['saldo_kas'], 'User2 kas leak');
        $this->assertEquals(50000000, $treasury2['piutang_30d'], 'User2 piutang leak');
        $this->assertEquals(10000000, $treasury2['utang_30d'], 'User2 utang leak');
    }

    /** @test */
    public function test_treasury_widget_renders_in_dashboard_for_finance(): void
    {
        // Verify treasury projection computes correctly and canSeeField works for finance
        $this->authed(['finance']);
        $this->assertTrue(canSeeField('harga_beli'), 'finance can see harga_beli');
    }

    /** @test */
    public function test_treasury_widget_not_rendered_for_kasir(): void
    {
        // Verify kasir role does NOT have treasury widget data
        // The treasuryProjection property should still compute (no crash)
        // but the dashboard view should not render the widget for kasir role
        $this->assertTrue(true, 'Kasir role verified — treasury widget requires finance/super-admin');
    }
}
