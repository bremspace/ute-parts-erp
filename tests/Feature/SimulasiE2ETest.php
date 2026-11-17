<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Akunting\Models\JurnalAkuntansi;
use App\Modules\Hr\Livewire\KomisiSkemaPage;
use App\Modules\Hr\Models\BagiHasil;
use App\Modules\Hr\Models\BagiHasilPeriode;
use App\Modules\Hr\Models\BagiHasilRule;
use App\Modules\Hr\Models\Karyawan;
use App\Modules\Hr\Models\PayrollSlip;
use App\Modules\Hr\Services\BagiHasilService;
use App\Modules\Hr\Services\PayrollService;
use App\Modules\Reseller\Models\Komisi;
use App\Modules\Reseller\Models\KomisiSkema;
use App\Modules\Servis\Models\TiketServis;
use App\Modules\Servis\Models\TiketServisItem;
use App\Modules\Servis\Services\ServisService;
use App\Modules\Workflow\Models\ApprovalRequest;
use App\Modules\Workflow\Models\ApprovalRule;
use App\Modules\Workflow\Services\ApprovalService;
use App\Services\BranchContextManager;
use Database\Seeders\AkunCoaSeeder;
use Database\Seeders\CabangSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * [E2E] Simulasi end-to-end modul kompensasi teknisi.
 *
 * Dua persona teknisi dipisah tegas:
 *  - TEKNISI GAJI   → payroll + komisi servis (engine komisi_skema, basis jasa aktual)
 *  - TEKNISI BAGI HASIL → payout bagi hasil (bukan payroll)
 *
 * Plus persona regulator (owner/finance/admin) dan kontrol akses peran
 * (minimum privilege standar industri: teknisi hanya servis + portal sendiri).
 *
 * Alur diuji: login → terima servis → status machine → selesai → hitung
 * komisi/bagi hasil → payroll/payout → portal teknisi.
 */
class SimulasiE2ETest extends TestCase
{
    use RefreshDatabase;

    private string $periode;

    private User $owner;

    private User $finance;

    private User $admin;

    private User $teknisiGaji;

    private User $teknisiBagiHasil;

    private int $karyawanGajiId;

    private int $karyawanBagiHasilId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(CabangSeeder::class);
        $this->seed(AkunCoaSeeder::class);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        app(BranchContextManager::class)->setActiveCabangId(1);

        $this->periode = now()->format('Y-m');

        $this->owner = $this->buatUser('owner', 'owner@e2e.test');
        $this->finance = $this->buatUser('finance', 'finance@e2e.test');
        $this->admin = $this->buatUser('admin-toko', 'admin@e2e.test');
        $this->teknisiGaji = $this->buatUser('teknisi', 'teknisi-gaji@e2e.test');
        $this->teknisiBagiHasil = $this->buatUser('teknisi', 'teknisi-bh@e2e.test');

        $this->karyawanGajiId = Karyawan::create([
            'user_id' => $this->teknisiGaji->id,
            'nik' => 'TEK-Gaji-001',
            'nama' => 'Teknisi Gaji',
            'jabatan' => 'teknisi',
            'metode_penghasilan' => 'gaji',
            'cabang_id' => 1,
            'tgl_masuk' => '2024-01-01',
            'gaji_pokok' => 5000000,
            'status_aktif' => true,
            'rekening_bank' => 'BCA 1111',
        ])->id;

        $this->karyawanBagiHasilId = Karyawan::create([
            'user_id' => $this->teknisiBagiHasil->id,
            'nik' => 'TEK-BH-001',
            'nama' => 'Teknisi Bagi Hasil',
            'jabatan' => 'teknisi',
            'metode_penghasilan' => 'bagi_hasil',
            'cabang_id' => 1,
            'tgl_masuk' => '2024-01-01',
            'gaji_pokok' => 0,
            'status_aktif' => true,
            'rekening_bank' => 'BCA 2222',
        ])->id;

        // Approval rule produksi untuk payout bagi hasil (ambang Rp 10.000.000)
        ApprovalRule::updateOrCreate(
            ['entity_type' => 'bagi_hasil', 'level' => 1],
            ['cabang_id' => null, 'min_amount' => 10000000, 'max_amount' => null, 'approver_role' => 'finance', 'is_aktif' => true]
        );
    }

    private function buatUser(string $role, string $email): User
    {
        $user = User::create([
            'name' => $email,
            'email' => $email,
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);
        $user->assignRole($role);

        return $user;
    }

    /**
     * Tiket servis masuk (intake) dengan rincian jasa + part.
     */
    private function buatTiket(User $teknisi, float $jasa, float $part = 0): TiketServis
    {
        $tiket = TiketServis::create([
            'no_tiket' => 'SRV-E2E-'.rand(10000, 99999),
            'cabang_id' => 1,
            'teknisi_id' => $teknisi->id,
            'jenis_hp' => 'iPhone 14',
            'tipe_kunci' => 'tidak_ada',
            'keluhan' => 'Layar retak',
            'kondisi_fisik' => ['kerusakan' => 'layar retak'],
            'foto_unit' => [],
            'status' => 'diterima',
            'sumber' => 'walk_in',
            'estimasi_biaya' => $jasa + $part,
        ]);

        if ($jasa > 0) {
            TiketServisItem::create([
                'tiket_servis_id' => $tiket->id,
                'tipe' => 'jasa',
                'nama_item' => 'Jasa ganti layar',
                'qty' => 1,
                'harga' => $jasa,
                'hpp' => 0,
                'dibatalkan_at' => null,
            ]);
        }

        if ($part > 0) {
            TiketServisItem::create([
                'tiket_servis_id' => $tiket->id,
                'tipe' => 'part',
                'nama_item' => 'LCD iPhone 14',
                'qty' => 1,
                'harga' => $part,
                'hpp' => 0,
                'dibatalkan_at' => null,
            ]);
        }

        return $tiket;
    }

    /**
     * Jalan status machine sampai 'selesai' (transisi asli, bukan override).
     */
    private function jalurServis(TiketServis $tiket, array $statuses, ?User $aktor = null): void
    {
        $service = app(ServisService::class);
        foreach ($statuses as $status) {
            $service->updateStatus($tiket->fresh(), $status, $aktor ?? $this->admin);
        }
    }

    private function buatRuleKomisi(float $persen): void
    {
        KomisiSkema::create([
            'nama' => 'Teknisi servis '.$persen.'% jasa',
            'aktor_tipe' => 'karyawan',
            'aktor_id' => null,
            'trigger_tipe' => 'tiket_servis',
            'kategori' => null,
            'tipe' => 'persen',
            'nilai' => $persen,
            'min_amount' => 0,
            'cabang_id' => null,
            'is_aktif' => true,
        ]);
    }

    private function buatRuleBagiHasil(float $persen): void
    {
        BagiHasilRule::create([
            'nama' => 'Teknisi bagi hasil '.$persen.'% jasa',
            'cabang_id' => 1,
            'karyawan_id' => $this->karyawanBagiHasilId,
            'persen' => $persen,
            'min_jasa' => 0,
            'is_aktif' => true,
        ]);
    }

    // ================================================================
    // 1. KONTROL AKSES PERAN (minimum privilege)
    // ================================================================

    /** @test */
    public function test_1_role_teknisi_hanya_akses_yang_diizinkan(): void
    {
        $this->actingAs($this->teknisiGaji);

        // Diizinkan: papan servis + portal karyawan sendiri
        $this->get('/app/servis')->assertOk();
        $this->get('/app/hr/saya')->assertOk();

        // Dilarang: manajemen kompensasi, HR, akunting, gudang, laporan, audit
        foreach ([
            '/app/hr/payroll',
            '/app/hr/karyawan',
            '/app/hr/komisi-skema',
            '/app/hr/bagi-hasil',
            '/app/audit-log',
            '/app/approvals',
            '/app/akunting',
            '/app/wms',
            '/app/laporan',
        ] as $url) {
            $this->get($url)->assertStatus(403, "Teknisi tak boleh mengakses {$url}");
        }
    }

    /** @test */
    public function test_2_role_finance_minimum_privilege_tanpa_servis(): void
    {
        $this->actingAs($this->finance);

        // Finance fokus keuangan/kompensasi
        $this->get('/app/hr/payroll')->assertOk();
        $this->get('/app/hr/bagi-hasil')->assertOk();
        $this->get('/app/audit-log')->assertOk();
        $this->get('/app/approvals')->assertOk();

        // Minimum privilege: finance TIDAK diberi servis.view (data unit pelanggan)
        $this->get('/app/servis')->assertStatus(403);
    }

    /** @test */
    public function test_3_role_owner_regulator_akses_penuh(): void
    {
        $this->actingAs($this->owner);

        foreach ([
            '/app/servis',
            '/app/hr/payroll',
            '/app/hr/karyawan',
            '/app/hr/komisi-skema',
            '/app/hr/bagi-hasil',
            '/app/audit-log',
            '/app/approvals',
        ] as $url) {
            $this->get($url)->assertOk("Owner/regulator harus membuka {$url}");
        }
    }

    // ================================================================
    // 2. E2E TEKNISI GAJI → KOMISI SERVIS (payroll)
    // ================================================================

    /** @test */
    public function test_4_e2e_teknisi_gaji_menerima_komisi_servis(): void
    {
        $this->buatRuleKomisi(20);

        // Login teknisi: portal & papan servis terbuka, slip belum ada
        $this->actingAs($this->teknisiGaji);
        $this->get('/app/hr/saya')->assertOk()->assertSee('Slip Gaji Saya');
        $this->get('/app/servis')->assertOk();

        // Teknisi memajukan tiket dari 'diterima' → 'diagnosa' (workflow asli)
        $tiket = $this->buatTiket($this->teknisiGaji, 150000, 50000);
        $this->jalurServis($tiket, ['diagnosa'], $this->teknisiGaji);
        $this->assertSame('diagnosa', $tiket->fresh()->status);

        // Admin melanjutkan sampai selesai (transisi state machine asli)
        $this->jalurServis($tiket, ['menunggu_approval', 'disetujui', 'dikerjakan', 'qc', 'selesai']);
        $this->assertSame('selesai', $tiket->fresh()->status);

        // Engine komisi: 20% x jasa aktual 150.000 = 30.000 (estimasi 200.000 TIDAK dipakai)
        $this->assertSame(1, Komisi::count());
        $komisi = Komisi::first();
        $this->assertEquals(30000, (float) $komisi->nominal_komisi);
        $this->assertSame('karyawan', $komisi->aktor_tipe);
        $this->assertSame($this->karyawanGajiId, (int) $komisi->aktor_id);

        // Teknisi gaji TIDAK pernah menghasilkan baris bagi hasil
        $this->assertSame(0, BagiHasil::count());

        // Payroll bulanan: komisi masuk slip
        app(PayrollService::class)->hitungDraft($this->periode);

        $slip = PayrollSlip::where('karyawan_id', $this->karyawanGajiId)->first();
        $this->assertNotNull($slip);
        $this->assertEquals(30000, (float) $slip->total_komisi);
        $this->assertEquals(5030000, (float) $slip->total_gaji);

        $rincian = json_decode($slip->rincian, true);
        $this->assertEquals(30000, (float) $rincian['komisi_teknisi']);
        $this->assertEquals(0, (float) $rincian['komisi_internal']);

        // Portal teknisi menampilkan slip gaji beserta komisinya
        $this->actingAs($this->teknisiGaji);
        $this->get('/app/hr/saya')->assertOk()->assertSee('Rp 5.030.000');
    }

    // ================================================================
    // 3. E2E TEKNISI BAGI HASIL → PAYOUT (bukan payroll)
    // ================================================================

    /** @test */
    public function test_5_e2e_teknisi_bagi_hasil_menerima_payout(): void
    {
        $this->buatRuleBagiHasil(50);

        // Login teknisi non-gaji
        $this->actingAs($this->teknisiBagiHasil);
        $this->get('/app/hr/saya')->assertOk()->assertSee('Bagi Hasil Jasa Saya');
        $this->get('/app/servis')->assertOk();

        // Servis masuk → selesai
        $tiket = $this->buatTiket($this->teknisiBagiHasil, 200000);
        $this->jalurServis($tiket, ['diagnosa', 'menunggu_approval', 'disetujui', 'dikerjakan', 'qc', 'selesai']);
        $this->assertSame('selesai', $tiket->fresh()->status);

        // Bagi hasil: 50% x 200.000 = 100.000, status pending
        $this->assertSame(1, BagiHasil::count());
        $bh = BagiHasil::first();
        $this->assertEquals(100000, (float) $bh->nominal);
        $this->assertEquals(200000, (float) $bh->total_jasa);
        $this->assertSame(BagiHasil::STATUS_PENDING, $bh->status);
        $this->assertSame($this->periode, $bh->periode);

        // Anti double-pay: tidak ada komisi untuk teknisi bagi hasil
        $this->assertSame(0, Komisi::count());

        // Teknisi bagi hasil TIDAK masuk payroll (payroll hanya karyawan bergaji)
        app(PayrollService::class)->hitungDraft($this->periode);
        $this->assertSame(0, PayrollSlip::where('karyawan_id', $this->karyawanBagiHasilId)->count());

        // Payout periode: hitung → proses (jurnal accrual) → bayar (jurnal kas)
        $service = app(BagiHasilService::class);
        $service->hitungPeriode($this->periode);
        $proses = $service->prosesPeriode($this->periode);
        $this->assertEquals(100000, $proses['total']);

        $accrue = JurnalAkuntansi::where('no_jurnal', 'JRL-BH-'.$this->periode)->get();
        $this->assertSame(2, $accrue->count());
        $this->assertEquals($accrue->sum('debit'), $accrue->sum('kredit'));
        $this->assertEquals(100000, (float) $accrue->sum('debit'));

        $bayar = $service->bayarPeriode($this->periode, '110-01');
        $this->assertSame(BagiHasilPeriode::STATUS_DIBAYAR, BagiHasilPeriode::first()->status);
        $this->assertSame(BagiHasil::STATUS_CAIR, BagiHasil::first()->status);
        $this->assertNotNull(BagiHasil::first()->paid_at);

        $bayarLines = JurnalAkuntansi::where('no_jurnal', $bayar['no_jurnal'])->get();
        $this->assertSame(2, $bayarLines->count());
        $this->assertEquals($bayarLines->sum('debit'), $bayarLines->sum('kredit'));

        // Audit trail tercatat untuk baris bagi hasil
        $this->assertDatabaseHas('activity_log', [
            'subject_type' => BagiHasil::class,
            'subject_id' => $bh->id,
        ]);

        // Portal teknisi menampilkan saldo & status cair
        $this->actingAs($this->teknisiBagiHasil);
        $this->get('/app/hr/saya')
            ->assertOk()
            ->assertSee('Bagi Hasil Jasa Saya')
            ->assertSee('Rp 100.000');

        // Teknisi non-gaji tetap tidak bisa membuka halaman admin bagi hasil
        $this->get('/app/hr/bagi-hasil')->assertStatus(403);
    }

    // ================================================================
    // 4. E2E REGULATOR: owner atur rule dari UI + audit
    // ================================================================

    /** @test */
    public function test_6_e2e_owner_mengatur_rule_komisi_dari_ui(): void
    {
        $this->actingAs($this->owner);

        // Owner/regulator membuat rule komisi teknisi dari UI (pola komisi-skema)
        Livewire::test(KomisiSkemaPage::class)
            ->set('nama', 'Komisi servis owner 10%')
            ->set('aktorTipe', 'karyawan')
            ->set('triggerTipe', 'tiket_servis')
            ->set('tipe', 'persen')
            ->set('nilai', '10')
            ->set('minAmount', '0')
            ->call('simpan')
            ->assertSee('Skema komisi baru dibuat.');

        $this->assertDatabaseHas('komisi_skema', [
            'nama' => 'Komisi servis owner 10%',
            'aktor_tipe' => 'karyawan',
            'trigger_tipe' => 'tiket_servis',
            'tipe' => 'persen',
            'nilai' => 10,
        ]);

        // Rule itu dipakai engine pada tiket berikutnya
        $tiket = $this->buatTiket($this->teknisiGaji, 100000);
        $this->jalurServis($tiket, ['diagnosa', 'menunggu_approval', 'disetujui', 'dikerjakan', 'qc', 'selesai']);

        $this->assertEquals(10000, (float) Komisi::first()->nominal_komisi);

        // Owner bisa membuka audit log aturan & aktivitas
        $this->get('/app/audit-log')->assertOk();
        $this->assertDatabaseHas('activity_log', [
            'subject_type' => KomisiSkema::class,
        ]);
    }

    // ================================================================
    // 5. E2E APPROVAL: payout di atas ambang wajib persetujuan finance
    // ================================================================

    /** @test */
    public function test_7_e2e_payout_di_atas_ambang_wajib_approval(): void
    {
        $this->buatRuleBagiHasil(50);

        // Jasa 25.000.000 → bagi hasil 12.500.000 (> ambang 10.000.000)
        $tiket = $this->buatTiket($this->teknisiBagiHasil, 25000000);
        $this->jalurServis($tiket, ['diagnosa', 'menunggu_approval', 'disetujui', 'dikerjakan', 'qc', 'selesai']);
        $this->assertEquals(12500000, (float) BagiHasil::first()->nominal);

        $service = app(BagiHasilService::class);
        $service->hitungPeriode($this->periode);

        // Diajukan oleh admin (pemohon), finance sebagai approver (four-eyes)
        $ajuan = $service->ajukanApproval($this->periode, $this->admin->id, '', 1);
        $this->assertTrue($ajuan['needs_approval']);
        $this->assertNotNull($ajuan['approval_request_id']);

        // Proses sebelum disetujui → ditolak
        $tertangkap = null;
        try {
            $service->prosesPeriode($this->periode);
        } catch (\DomainException $e) {
            $tertangkap = $e;
        }
        $this->assertNotNull($tertangkap, 'Payout > ambang harus menunggu approval');

        // Finance menyetujui lewat ApprovalService
        app(ApprovalService::class)->proses($ajuan['approval_request_id'], 'setujui', $this->finance->id, 'Payout bagi hasil disetujui');

        $request = ApprovalRequest::find($ajuan['approval_request_id']);
        $this->assertSame('disetujui', $request->status);

        // Kini proses berhasil + jurnal accrual terposting
        $proses = $service->prosesPeriode($this->periode);
        $this->assertEquals(12500000, $proses['total']);
        $this->assertDatabaseHas('jurnal_akuntansi', ['no_jurnal' => $proses['no_jurnal']]);
    }
}
