<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Akunting\Models\AkunCOA;
use App\Modules\Akunting\Models\JurnalAkuntansi;
use App\Modules\Hr\Livewire\BagiHasilPage;
use App\Modules\Hr\Livewire\HrSayaPage;
use App\Modules\Hr\Models\BagiHasil;
use App\Modules\Hr\Models\BagiHasilPeriode;
use App\Modules\Hr\Models\BagiHasilRule;
use App\Modules\Hr\Models\Karyawan;
use App\Modules\Hr\Models\PayrollSlip;
use App\Modules\Hr\Services\BagiHasilService;
use App\Modules\Hr\Services\PayrollService;
use App\Modules\Reseller\Models\Komisi;
use App\Modules\Reseller\Models\KomisiSkema;
use App\Modules\Reseller\Services\KomisiService;
use App\Modules\Servis\Models\TiketServis;
use App\Modules\Servis\Models\TiketServisItem;
use App\Modules\Servis\Services\ServisService;
use Database\Seeders\AkunCoaSeeder;
use Database\Seeders\CabangSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * [BH] Bagi hasil jasa teknisi non-gaji — accru per tiket, payout periode,
 * integrasi akunting (jurnal balance & idempoten), dan anti double-pay dgn komisi.
 */
class BagiHasilTest extends TestCase
{
    use RefreshDatabase;

    private string $periode;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CabangSeeder::class);
        $this->seed(AkunCoaSeeder::class);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Permission::firstOrCreate(['name' => 'kelola-bagi-hasil']);

        $this->periode = now()->format('Y-m');
        $this->user = User::create([
            'name' => 'Teknisi BH',
            'email' => 'bh@uteparts.test',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);
    }

    private function buatKaryawan(string $metode = 'bagi_hasil', float $gaji = 0): Karyawan
    {
        return Karyawan::create([
            'user_id' => $this->user->id,
            'nama' => 'Teknisi '.$metode,
            'jabatan' => 'teknisi',
            'metode_penghasilan' => $metode,
            'cabang_id' => 1,
            'tgl_masuk' => '2024-01-01',
            'gaji_pokok' => $gaji,
            'status_aktif' => true,
        ]);
    }

    private function buatRule(float $persen, ?int $karyawanId = null, ?int $cabangId = null, float $minJasa = 0): BagiHasilRule
    {
        return BagiHasilRule::create([
            'nama' => "Rule {$persen}%",
            'cabang_id' => $cabangId,
            'karyawan_id' => $karyawanId,
            'persen' => $persen,
            'min_jasa' => $minJasa,
            'is_aktif' => true,
        ]);
    }

    private function buatTiket(float $jasa = 200000, ?string $status = 'selesai'): TiketServis
    {
        return TiketServis::create([
            'no_tiket' => 'TS-BH-'.rand(1000, 9999),
            'cabang_id' => 1,
            'teknisi_id' => $this->user->id,
            'jenis_hp' => 'iPhone 14',
            'tipe_kunci' => 'tidak_ada',
            'keluhan' => 'Kerusakan layar',
            'kondisi_fisik' => ['kerusakan' => 'layar retak'],
            'foto_unit' => [],
            'status' => $status,
            'sumber' => 'walk_in',
            'estimasi_biaya' => $jasa,
            'tanggal_selesai' => $status === 'selesai' ? now()->format('Y-m-d') : null,
        ]);
    }

    /** @test */
    public function test_accrue_menghitung_persen_dari_jasa_tiket(): void
    {
        $karyawan = $this->buatKaryawan();
        $this->buatRule(50, $karyawan->id);

        $tiket = $this->buatTiket(200000);
        $baris = app(BagiHasilService::class)->accru($tiket);

        $this->assertNotNull($baris);
        $this->assertSame(1, BagiHasil::count());
        $this->assertEquals(100000, $baris->nominal);
        $this->assertEquals(200000, $baris->total_jasa);
        $this->assertEquals(50, (float) $baris->persen);
        $this->assertSame(BagiHasil::STATUS_PENDING, $baris->status);
        $this->assertSame($this->periode, $baris->periode);
        $this->assertNull($baris->paid_at);
    }

    /** @test */
    public function test_accrue_idempoten_satu_tiket_satu_baris(): void
    {
        $karyawan = $this->buatKaryawan();
        $this->buatRule(50, $karyawan->id);

        $tiket = $this->buatTiket(200000);
        $service = app(BagiHasilService::class);

        $service->accru($tiket);
        $service->accru($tiket->fresh());

        $this->assertSame(1, BagiHasil::count());
    }

    /** @test */
    public function test_rule_prioritas_per_teknisi_menang(): void
    {
        $karyawan = $this->buatKaryawan();
        $this->buatRule(50);                       // global
        $this->buatRule(40, null, 1);              // cabang
        $this->buatRule(60, $karyawan->id, 1);     // per teknisi

        $rule = app(BagiHasilService::class)->ruleUntuk($karyawan);

        $this->assertNotNull($rule);
        $this->assertEquals(60, (float) $rule->persen);
    }

    /** @test */
    public function test_rule_nonaktif_dan_min_jasa_dihormati(): void
    {
        $karyawan = $this->buatKaryawan();
        BagiHasilRule::create([
            'nama' => 'Nonaktif',
            'cabang_id' => null,
            'karyawan_id' => null,
            'persen' => 80,
            'min_jasa' => 0,
            'is_aktif' => false,
        ]);
        $this->buatRule(30, null, null, 500000); // ambang di atas nilai tiket

        $tiket = $this->buatTiket(100000);

        $this->assertNull(app(BagiHasilService::class)->accru($tiket));
        $this->assertSame(0, BagiHasil::count());
    }

    /** @test */
    public function test_reversal_saat_tiket_dibatalkan(): void
    {
        $karyawan = $this->buatKaryawan();
        $this->buatRule(50, $karyawan->id);

        $tiket = $this->buatTiket(200000);
        app(BagiHasilService::class)->accru($tiket);

        app(BagiHasilService::class)->reversal($tiket->fresh());

        $this->assertSame(BagiHasil::STATUS_DIBATALKAN, BagiHasil::first()->status);
    }

    /** @test */
    public function test_hook_on_selesai_membuat_bagi_hasil(): void
    {
        $karyawan = $this->buatKaryawan();
        $this->buatRule(50, $karyawan->id);

        $tiket = $this->buatTiket(150000, 'qc');

        app(ServisService::class)->updateStatus($tiket->fresh(), 'selesai', $this->user);

        $this->assertSame('selesai', $tiket->fresh()->status);
        $this->assertSame(1, BagiHasil::count());
        $this->assertEquals(75000, (float) BagiHasil::first()->nominal);
    }

    /** @test */
    public function test_proses_dan_bayar_periode_post_jurnal_balance_dan_idempoten(): void
    {
        $karyawan = $this->buatKaryawan();
        $this->buatRule(50, $karyawan->id);

        app(BagiHasilService::class)->accru($this->buatTiket(200000));
        app(BagiHasilService::class)->accru($this->buatTiket(100000));

        $service = app(BagiHasilService::class);

        $proses = $service->prosesPeriode($this->periode, 1);
        $this->assertFalse($proses['sudah_diproses']);
        $this->assertEquals(150000, $proses['total']);

        // Semua baris pending → disetujui; periode selesai
        $this->assertSame(0, BagiHasil::where('status', BagiHasil::STATUS_PENDING)->count());
        $this->assertSame(2, BagiHasil::where('status', BagiHasil::STATUS_DISETUJUI)->count());
        $this->assertSame(BagiHasilPeriode::STATUS_SELESAI, BagiHasilPeriode::first()->status);

        // Jurnal accrual balance: 520-11 debit 150.000 / 210-06 kredit 150.000
        $accrue = JurnalAkuntansi::with('akun')->where('no_jurnal', 'JRL-BH-'.$this->periode)->get();
        $this->assertSame(2, $accrue->count());
        $this->assertEquals($accrue->sum('debit'), $accrue->sum('kredit'));
        $this->assertEquals(150000, (float) $accrue->sum('debit'));
        $kodeAccrue = $accrue->map(fn ($j) => $j->akun?->kode)->sort()->values()->all();
        $this->assertSame(['210-06', '520-11'], $kodeAccrue);

        // Proses ulang → idempoten (tidak ada jurnal baru)
        $ulang = $service->prosesPeriode($this->periode, 1);
        $this->assertTrue($ulang['sudah_diproses']);
        $this->assertSame(2, JurnalAkuntansi::where('no_jurnal', 'JRL-BH-'.$this->periode)->count());

        // Bayar → jurnal kas + status cair
        $bayar = $service->bayarPeriode($this->periode, '110-01', 1);
        $this->assertNotEmpty($bayar['no_jurnal']);

        $this->assertSame(2, BagiHasil::where('status', BagiHasil::STATUS_CAIR)->count());
        $this->assertNotNull(BagiHasil::first()->paid_at);
        $this->assertSame(BagiHasilPeriode::STATUS_DIBAYAR, BagiHasilPeriode::first()->status);

        $bayarLines = JurnalAkuntansi::where('no_jurnal', 'JRL-BH-BAYAR-'.$this->periode)->get();
        $this->assertSame(2, $bayarLines->count());
        $this->assertEquals($bayarLines->sum('debit'), $bayarLines->sum('kredit'));

        // Bayar ulang → ditolak
        $this->expectException(\DomainException::class);
        $service->bayarPeriode($this->periode, '110-01', 1);
    }

    /** @test */
    public function test_payroll_mengecualikan_teknisi_bagi_hasil(): void
    {
        $this->buatKaryawan('gaji', 5000000);
        $this->buatKaryawan('bagi_hasil', 0);

        app(PayrollService::class)->hitungDraft($this->periode);

        $this->assertSame(1, PayrollSlip::count());
        $this->assertSame('gaji', PayrollSlip::first()->karyawan->metode_penghasilan);
    }

    /** @test */
    public function test_komisi_engine_tidak_bayar_teknisi_bagi_hasil(): void
    {
        $karyawan = $this->buatKaryawan('bagi_hasil');
        $this->buatRule(50, $karyawan->id);

        KomisiSkema::create([
            'nama' => 'Teknisi tiket servis 10000',
            'aktor_tipe' => 'karyawan',
            'aktor_id' => null,
            'trigger_tipe' => 'tiket_servis',
            'kategori' => null,
            'tipe' => 'nominal',
            'nilai' => 10000,
            'min_amount' => 0,
            'cabang_id' => null,
            'is_aktif' => true,
        ]);

        $tiket = $this->buatTiket(200000);
        app(KomisiService::class)->hitungKomisiMultiAktor('tiket_servis', ['tiket_servis_id' => $tiket->id]);

        $this->assertSame(0, Komisi::count());

        // Bagi hasil tetap dibuat (1 tiket = 1 penghasilan, bukan komisi)
        app(BagiHasilService::class)->accru($tiket);
        $this->assertSame(1, BagiHasil::count());
        $this->assertSame(0, Komisi::count());
    }

    /** @test */
    public function test_komisi_servis_teknisi_gaji_pakai_jasa_aktual(): void
    {
        $this->buatKaryawan('gaji', 5000000);

        KomisiSkema::create([
            'nama' => 'Teknisi servis 20% jasa',
            'aktor_tipe' => 'karyawan',
            'aktor_id' => null,
            'trigger_tipe' => 'tiket_servis',
            'kategori' => null,
            'tipe' => 'persen',
            'nilai' => 20,
            'min_amount' => 0,
            'cabang_id' => null,
            'is_aktif' => true,
        ]);

        // Estimasi 300.000, tapi jasa aktual 150.000 + part 50.000
        $tiket = $this->buatTiket(300000);
        TiketServisItem::create([
            'tiket_servis_id' => $tiket->id,
            'tipe' => 'jasa',
            'nama_item' => 'Ganti layar',
            'qty' => 1,
            'harga' => 150000,
            'hpp' => 0,
            'dibatalkan_at' => null,
        ]);
        TiketServisItem::create([
            'tiket_servis_id' => $tiket->id,
            'tipe' => 'part',
            'nama_item' => 'LCD',
            'qty' => 1,
            'harga' => 50000,
            'hpp' => 0,
            'dibatalkan_at' => null,
        ]);

        app(KomisiService::class)->hitungKomisiMultiAktor('tiket_servis', ['tiket_servis_id' => $tiket->id]);

        // 20% x 150.000 jasa = 30.000 (bukan 20% x 300.000 estimasi)
        $this->assertEquals(30000, (float) Komisi::first()->nominal_komisi);

        app(PayrollService::class)->hitungDraft($this->periode);

        $slip = PayrollSlip::first();
        $this->assertNotNull($slip);
        $this->assertEquals(30000, (float) $slip->total_komisi);

        $rincian = json_decode($slip->rincian, true);
        $this->assertEquals(30000, (float) $rincian['komisi_teknisi']);
        $this->assertEquals(0, (float) $rincian['komisi_internal']);
    }

    /** @test */
    public function test_coa_bagi_hasil_tersedia(): void
    {
        $this->assertDatabaseHas('akun_coa', ['kode' => '520-11', 'is_active' => true]);
        $this->assertDatabaseHas('akun_coa', ['kode' => '210-06', 'is_active' => true]);

        $beban = AkunCOA::where('kode', '520-11')->first();
        $utang = AkunCOA::where('kode', '210-06')->first();
        $this->assertSame('beban', $beban->tipe);
        $this->assertSame('kewajiban', $utang->tipe);
    }

    /** @test */
    public function test_halaman_bagi_hasil_render_dan_tab_berfungsi(): void
    {
        $admin = User::create([
            'name' => 'Finance BH',
            'email' => 'finance-bh@uteparts.test',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);
        $admin->givePermissionTo('kelola-bagi-hasil');

        $this->actingAs($admin);

        Livewire::test(BagiHasilPage::class)
            ->assertStatus(200)
            ->assertSee('Bagi Hasil Jasa Teknisi')
            ->call('setTab', 'transaksi')
            ->assertSet('activeTab', 'transaksi')
            ->call('setTab', 'payout')
            ->assertSet('activeTab', 'payout');
    }

    /** @test */
    public function test_rule_crud_lewat_ui(): void
    {
        $admin = User::create([
            'name' => 'Finance BH 2',
            'email' => 'finance-bh2@uteparts.test',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);
        $admin->givePermissionTo('kelola-bagi-hasil');

        $this->actingAs($admin);

        Livewire::test(BagiHasilPage::class)
            ->set('nama', 'Rule UI 70%')
            ->set('persen', '70')
            ->set('minJasa', '10000')
            ->call('simpanRule')
            ->assertSee('Aturan bagi hasil baru dibuat.');

        $this->assertDatabaseHas('bagi_hasil_rules', [
            'nama' => 'Rule UI 70%',
            'persen' => 70,
            'min_jasa' => 10000,
            'is_aktif' => true,
        ]);
    }

    /** @test */
    public function test_widget_bagi_hasil_di_portal_karyawan(): void
    {
        $karyawan = $this->buatKaryawan('bagi_hasil');
        $this->buatRule(50, $karyawan->id);
        app(BagiHasilService::class)->accru($this->buatTiket(120000));

        Permission::firstOrCreate(['name' => 'hr.lihat-sendiri']);
        $this->user->givePermissionTo('hr.lihat-sendiri');

        $this->actingAs($this->user);

        Livewire::test(HrSayaPage::class)
            ->assertStatus(200)
            ->assertSee('Bagi Hasil Jasa Saya')
            ->assertSee('Rp 60.000');
    }
}
