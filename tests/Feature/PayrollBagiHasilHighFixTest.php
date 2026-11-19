<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Akunting\Models\JurnalAkuntansi;
use App\Modules\Hr\Livewire\PayrollPage;
use App\Modules\Hr\Models\BagiHasil;
use App\Modules\Hr\Models\BagiHasilPeriode;
use App\Modules\Hr\Models\BagiHasilRule;
use App\Modules\Hr\Models\Karyawan;
use App\Modules\Hr\Models\PayrollKomisiDetail;
use App\Modules\Hr\Models\PayrollPeriode;
use App\Modules\Hr\Models\PayrollSlip;
use App\Modules\Hr\Services\BagiHasilService;
use App\Modules\Hr\Services\PayrollService;
use App\Modules\Rbac\Models\Cabang;
use App\Modules\Reseller\Models\Komisi;
use App\Modules\Servis\Models\TiketServis;
use Database\Seeders\AkunCoaSeeder;
use Database\Seeders\CabangSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * [AUDIT-FIX] HIGH payroll & bagi hasil (audit #3):
 * - F-01: bayarPayroll wajib periode 'selesai' (sudah accrual) — fail-closed.
 * - F-02: hitungDraft terkunci untuk periode 'selesai' (GL tidak boleh drift dari slip).
 * - F-03: nomor jurnal payroll/bagi hasil menyertakan cabang (multi-cabang periode sama).
 * - F-04: komisi ditandai cair HANYA yang tercantum di slip periode+cabang yang dibayar.
 * - F-15: pembatalan baris bagi hasil setelah accrual → jurnal pembalik proporsional,
 *         total periode direcompute, bayar hanya baris yang masih disetujui.
 */
class PayrollBagiHasilHighFixTest extends TestCase
{
    use RefreshDatabase;

    private string $periode;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CabangSeeder::class);
        $this->seed(AkunCoaSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->periode = now()->format('Y-m');
    }

    private function buatKaryawan(int $cabangId, string $metode = 'gaji', float $gaji = 5000000): Karyawan
    {
        // Satu user per karyawan: payroll_komisi_detail.teknisi_id FK ke users.
        $user = User::create([
            'name' => 'Karyawan '.$metode.' '.$cabangId.' '.rand(1000, 9999),
            'email' => 'k'.rand(100000, 999999).'@fix.test',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);

        return Karyawan::create([
            'user_id' => $user->id,
            'nama' => 'Karyawan '.$metode,
            'jabatan' => 'teknisi',
            'metode_penghasilan' => $metode,
            'cabang_id' => $cabangId,
            'tgl_masuk' => '2024-01-01',
            'gaji_pokok' => $gaji,
            'status_aktif' => true,
        ]);
    }

    private function buatCabang2(): Cabang
    {
        return Cabang::create([
            'nama' => 'Cabang Dua',
            'kode' => 'CDA',
            'alamat' => 'Jl. Dua No. 2',
            'telepon' => '0315550002',
            'is_active' => true,
        ]);
    }

    private function buatTiket(int $cabangId, Karyawan $karyawan, float $jasa): TiketServis
    {
        return TiketServis::create([
            'no_tiket' => 'TS-FIX-'.rand(10000, 99999),
            'cabang_id' => $cabangId,
            'teknisi_id' => $karyawan->user_id,
            'jenis_hp' => 'iPhone 14',
            'tipe_kunci' => 'tidak_ada',
            'keluhan' => 'Kerusakan layar',
            'kondisi_fisik' => ['kerusakan' => 'layar retak'],
            'foto_unit' => [],
            'status' => 'selesai',
            'sumber' => 'walk_in',
            'estimasi_biaya' => $jasa,
            'tanggal_selesai' => now()->format('Y-m-d'),
        ]);
    }

    private function buatRule(Karyawan $karyawan, float $persen = 50): BagiHasilRule
    {
        return BagiHasilRule::create([
            'nama' => 'Rule fix '.$persen.'%',
            'cabang_id' => null,
            'karyawan_id' => $karyawan->id,
            'persen' => $persen,
            'min_jasa' => 0,
            'is_aktif' => true,
        ]);
    }

    private function buatKomisi(Karyawan $karyawan, float $nominal, string $no): Komisi
    {
        return Komisi::create([
            'no_komisi' => $no,
            'aktor_tipe' => 'karyawan',
            'aktor_id' => $karyawan->id,
            'nominal_komisi' => $nominal,
            'status' => Komisi::STATUS_PENDING,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    // ================================================================
    // F-01 — bayar hanya periode 'selesai'
    // ================================================================

    public function test_bayar_payroll_periode_draft_ditolak_tanpa_jurnal(): void
    {
        $this->buatKaryawan(1);
        $service = app(PayrollService::class);
        $service->hitungDraft($this->periode, 1);

        try {
            $service->bayarPayroll($this->periode, '110-01', 1);
            $this->fail('bayarPayroll pada periode draft harus ditolak (belum accrual)');
        } catch (\DomainException $e) {
            $this->assertStringContainsString('accrual', $e->getMessage());
        }

        // Fail-closed: tidak ada jurnal, status tidak berubah.
        $this->assertSame(0, JurnalAkuntansi::count());
        $this->assertSame(
            PayrollPeriode::STATUS_DRAFT,
            PayrollPeriode::withoutCabangScope()->where('periode', $this->periode)->where('cabang_id', 1)->value('status')
        );
        $this->assertSame('draft', PayrollSlip::first()->status);
    }

    public function test_bayar_payroll_periode_selesai_tetap_berhasil(): void
    {
        $this->buatKaryawan(1);
        $service = app(PayrollService::class);
        $service->hitungDraft($this->periode, 1);
        $service->finalisasiDisetujui($this->periode, 1);

        $hasil = $service->bayarPayroll($this->periode, '110-01', 1);

        $this->assertEquals(5000000, $hasil['total']);
        $this->assertSame(
            PayrollPeriode::STATUS_DIBAYAR,
            PayrollPeriode::withoutCabangScope()->where('periode', $this->periode)->where('cabang_id', 1)->value('status')
        );
        $this->assertSame('dibayar', PayrollSlip::first()->status);
    }

    // ================================================================
    // F-02 — kunci hitung ulang periode 'selesai'
    // ================================================================

    public function test_hitung_draft_periode_selesai_ditolak_slip_tidak_berubah(): void
    {
        $karyawan = $this->buatKaryawan(1);
        $service = app(PayrollService::class);
        $service->hitungDraft($this->periode, 1);
        $service->finalisasiDisetujui($this->periode, 1);

        $jurnalSebelum = JurnalAkuntansi::count();
        $karyawan->update(['gaji_pokok' => 9000000]);

        try {
            $service->hitungDraft($this->periode, 1);
            $this->fail('hitungDraft pada periode selesai harus ditolak (jurnal accrual sudah ada)');
        } catch (\DomainException $e) {
            $this->assertStringContainsString('difinalisasi', $e->getMessage());
        }

        // Slip & jurnal tidak berubah — GL tetap sinkron dengan slip.
        $this->assertEquals(5000000, (float) PayrollSlip::first()->total_gaji);
        $this->assertSame($jurnalSebelum, JurnalAkuntansi::count());
        $this->assertSame(
            PayrollPeriode::STATUS_SELESAI,
            PayrollPeriode::withoutCabangScope()->where('periode', $this->periode)->where('cabang_id', 1)->value('status')
        );
    }

    // ================================================================
    // F-03 — nomor jurnal per cabang (multi-cabang periode sama)
    // ================================================================

    public function test_payroll_dua_cabang_periode_sama_semua_terposting(): void
    {
        $cabang2 = $this->buatCabang2();
        $this->buatKaryawan(1, 'gaji', 5000000);
        $this->buatKaryawan($cabang2->id, 'gaji', 4000000);
        $service = app(PayrollService::class);

        $service->hitungDraft($this->periode, 1);
        $service->hitungDraft($this->periode, $cabang2->id);

        $final1 = $service->finalisasiDisetujui($this->periode, 1);
        $final2 = $service->finalisasiDisetujui($this->periode, $cabang2->id);

        $this->assertSame('JRL-PR-1-'.$this->periode, $final1['no_jurnal']);
        $this->assertSame('JRL-PR-'.$cabang2->id.'-'.$this->periode, $final2['no_jurnal']);

        $bayar1 = $service->bayarPayroll($this->periode, '110-01', 1);
        $bayar2 = $service->bayarPayroll($this->periode, '110-01', $cabang2->id);

        $this->assertSame('JRL-PR-BAYAR-1-'.$this->periode, $bayar1['no_jurnal']);
        $this->assertSame('JRL-PR-BAYAR-'.$cabang2->id.'-'.$this->periode, $bayar2['no_jurnal']);

        // Kedua cabang punya accrual (3 baris: 520-01, 520-09, 210-02) +
        // pembayaran sendiri, terisolasi per cabang.
        $this->assertSame(3, JurnalAkuntansi::where('no_jurnal', $final1['no_jurnal'])->where('cabang_id', 1)->count());
        $this->assertSame(3, JurnalAkuntansi::where('no_jurnal', $final2['no_jurnal'])->where('cabang_id', $cabang2->id)->count());
        $this->assertEquals(5000000, (float) JurnalAkuntansi::where('no_jurnal', $bayar1['no_jurnal'])->sum('debit'));
        $this->assertEquals(4000000, (float) JurnalAkuntansi::where('no_jurnal', $bayar2['no_jurnal'])->sum('debit'));
    }

    public function test_bagi_hasil_dua_cabang_periode_sama_semua_terposting(): void
    {
        $cabang2 = $this->buatCabang2();
        $karyawan1 = $this->buatKaryawan(1, 'bagi_hasil', 0);
        $karyawan2 = $this->buatKaryawan($cabang2->id, 'bagi_hasil', 0);
        $this->buatRule($karyawan1);
        $this->buatRule($karyawan2);
        $service = app(BagiHasilService::class);
        $service->accru($this->buatTiket(1, $karyawan1, 200000));
        $service->accru($this->buatTiket($cabang2->id, $karyawan2, 100000));

        $proses1 = $service->prosesPeriode($this->periode, 1);
        $proses2 = $service->prosesPeriode($this->periode, $cabang2->id);

        $this->assertSame('JRL-BH-1-'.$this->periode, $proses1['no_jurnal']);
        $this->assertSame('JRL-BH-'.$cabang2->id.'-'.$this->periode, $proses2['no_jurnal']);
        $this->assertEquals(100000, $proses1['total']);
        $this->assertEquals(50000, $proses2['total']);

        $bayar1 = $service->bayarPeriode($this->periode, '110-01', 1);
        $bayar2 = $service->bayarPeriode($this->periode, '110-01', $cabang2->id);

        $this->assertSame('JRL-BH-BAYAR-1-'.$this->periode, $bayar1['no_jurnal']);
        $this->assertSame('JRL-BH-BAYAR-'.$cabang2->id.'-'.$this->periode, $bayar2['no_jurnal']);
        $this->assertSame(2, BagiHasil::where('status', BagiHasil::STATUS_CAIR)->count());
    }

    // ================================================================
    // F-04 — komisi cair hanya yang ada di slip periode+cabang ini
    // ================================================================

    public function test_bayar_payroll_hanya_mencairkan_komisi_pada_slip_periode_ini(): void
    {
        $cabang2 = $this->buatCabang2();
        $karyawan1 = $this->buatKaryawan(1);
        $karyawan2 = $this->buatKaryawan($cabang2->id);

        // K1 masuk slip cabang 1; K2 milik karyawan cabang 2 (window created_at sama).
        $k1 = $this->buatKomisi($karyawan1, 50000, 'KM-FIX-1');
        $k2 = $this->buatKomisi($karyawan2, 70000, 'KM-FIX-2');

        $service = app(PayrollService::class);
        $service->hitungDraft($this->periode, 1);

        // K3 dibuat SETELAH draft dihitung — tidak masuk slip mana pun.
        $k3 = $this->buatKomisi($karyawan1, 90000, 'KM-FIX-3');

        // Detail slip harus menautkan komisi sumber (untuk scope pencairan).
        $detail = PayrollKomisiDetail::first();
        $this->assertNotNull($detail);
        $this->assertSame($k1->id, $detail->komisi_id);

        $service->finalisasiDisetujui($this->periode, 1);
        $service->bayarPayroll($this->periode, '110-01', 1);

        // Hanya K1 (tercantum di slip yang dibayar) yang cair.
        $this->assertSame(Komisi::STATUS_CAIR, $k1->fresh()->status);
        $this->assertNotNull($k1->fresh()->paid_at);
        $this->assertSame(Komisi::STATUS_PENDING, $k2->fresh()->status, 'Komisi cabang lain tidak boleh ikut cair');
        $this->assertNull($k2->fresh()->paid_at);
        $this->assertSame(Komisi::STATUS_PENDING, $k3->fresh()->status, 'Komisi di luar slip tidak boleh ikut cair');
        $this->assertNull($k3->fresh()->paid_at);
    }

    // ================================================================
    // F-15 — reversal bagi hasil setelah accrual + bayar hanya disetujui
    // ================================================================

    public function test_reversal_setelah_accrual_post_jurnal_pembalik_dan_bayar_sisanya(): void
    {
        $karyawan = $this->buatKaryawan(1, 'bagi_hasil', 0);
        $this->buatRule($karyawan);
        $service = app(BagiHasilService::class);

        $tiketA = $this->buatTiket(1, $karyawan, 200000); // nominal 100.000
        $tiketB = $this->buatTiket(1, $karyawan, 100000); // nominal 50.000
        $barisA = $service->accru($tiketA);
        $barisB = $service->accru($tiketB);

        $proses = $service->prosesPeriode($this->periode, 1);
        $this->assertEquals(150000, $proses['total']);

        // Tiket B batal setelah accrual → baris dibatalkan + jurnal pembalik proporsional.
        $service->reversal($tiketB->fresh());

        $this->assertSame(BagiHasil::STATUS_DIBATALKAN, $barisB->fresh()->status);
        $this->assertSame(BagiHasil::STATUS_DISETUJUI, $barisA->fresh()->status);

        $reversal = JurnalAkuntansi::with('akun')
            ->where('referensi_tipe', BagiHasil::class)
            ->where('referensi_id', $barisB->id)
            ->get();
        $this->assertSame(2, $reversal->count(), 'Jurnal pembalik proporsional harus diposting');
        $this->assertEquals(50000, (float) $reversal->sum('debit'));
        $this->assertEquals($reversal->sum('debit'), $reversal->sum('kredit'));
        $perAkun = $reversal->mapWithKeys(fn ($j) => [$j->akun?->kode => $j]);
        $this->assertEquals(50000, (float) $perAkun['210-06']->debit, 'Pembalik: D Utang Bagi Hasil');
        $this->assertEquals(50000, (float) $perAkun['520-11']->kredit, 'Pembalik: K Beban Bagi Hasil');

        // Total periode direcompute — tidak lagi memuat baris batal.
        $periodeRecord = BagiHasilPeriode::withoutCabangScope()
            ->where('periode', $this->periode)->where('cabang_id', 1)->first();
        $this->assertEquals(100000, (float) $periodeRecord->total);
        $this->assertSame(1, (int) $periodeRecord->jumlah_tiket);
        $this->assertSame(BagiHasilPeriode::STATUS_SELESAI, $periodeRecord->status);

        // Bayar hanya sisa yang masih disetujui (100.000, bukan 150.000).
        $bayar = $service->bayarPeriode($this->periode, '110-01', 1);
        $this->assertEquals(100000, $bayar['total']);
        $this->assertSame(BagiHasil::STATUS_CAIR, $barisA->fresh()->status);
        $this->assertSame(BagiHasil::STATUS_DIBATALKAN, $barisB->fresh()->status);

        $bayarLines = JurnalAkuntansi::where('no_jurnal', $bayar['no_jurnal'])->get();
        $this->assertEquals(100000, (float) $bayarLines->sum('debit'));

        // GL utang bagi hasil impas: accrual 150.000 K = pembalik 50.000 D + bayar 100.000 D.
        $utang = JurnalAkuntansi::whereHas('akun', fn ($q) => $q->where('kode', '210-06'))->get();
        $this->assertEquals($utang->sum('kredit'), $utang->sum('debit'));
    }

    public function test_reversal_baris_pending_tidak_memposting_jurnal(): void
    {
        $karyawan = $this->buatKaryawan(1, 'bagi_hasil', 0);
        $this->buatRule($karyawan);
        $service = app(BagiHasilService::class);
        $tiket = $this->buatTiket(1, $karyawan, 200000);
        $service->accru($tiket);

        // Belum diproses (belum accrual) → cukup flip status, tanpa jurnal.
        $service->reversal($tiket->fresh());

        $this->assertSame(BagiHasil::STATUS_DIBATALKAN, BagiHasil::first()->status);
        $this->assertSame(0, JurnalAkuntansi::count());
    }

    public function test_bayar_periode_bagi_hasil_semua_baris_batal_ditolak(): void
    {
        $karyawan = $this->buatKaryawan(1, 'bagi_hasil', 0);
        $this->buatRule($karyawan);
        $service = app(BagiHasilService::class);
        $tiket = $this->buatTiket(1, $karyawan, 200000);
        $service->accru($tiket);
        $service->prosesPeriode($this->periode, 1);
        $service->reversal($tiket->fresh());

        try {
            $service->bayarPeriode($this->periode, '110-01', 1);
            $this->fail('Tidak ada baris disetujui tersisa — pembayaran harus ditolak');
        } catch (\DomainException $e) {
            $this->assertStringContainsString('disetujui', $e->getMessage());
        }

        $this->assertSame(0, JurnalAkuntansi::where('no_jurnal', 'like', 'JRL-BH-BAYAR-%')->count());
        $this->assertSame(
            BagiHasilPeriode::STATUS_SELESAI,
            BagiHasilPeriode::withoutCabangScope()->where('periode', $this->periode)->where('cabang_id', 1)->value('status')
        );
    }

    public function test_hitung_periode_bagi_hasil_selesai_ditolak(): void
    {
        $karyawan = $this->buatKaryawan(1, 'bagi_hasil', 0);
        $this->buatRule($karyawan);
        $service = app(BagiHasilService::class);
        $service->accru($this->buatTiket(1, $karyawan, 200000));
        $service->prosesPeriode($this->periode, 1);

        $this->expectException(\DomainException::class);
        $service->hitungPeriode($this->periode, 1);
    }

    // ================================================================
    // F-01/F-02 — UI: tombol bayar & draft terkunci sesuai status
    // ================================================================

    private function siapkanUiPayroll(string $status): PayrollPeriode
    {
        Permission::firstOrCreate(['name' => 'kelola-payroll']);
        $admin = User::create([
            'name' => 'Finance UI',
            'email' => 'finance-ui@fix.test',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);
        $admin->givePermissionTo('kelola-payroll');
        $this->actingAs($admin);
        session(['cabang_id' => 1]);

        return PayrollPeriode::create([
            'cabang_id' => 1,
            'periode' => $this->periode,
            'tanggal_mulai' => $this->periode.'-01',
            'tanggal_selesai' => date('Y-m-t', strtotime($this->periode.'-01')),
            'status' => $status,
        ]);
    }

    public function test_buka_modal_bayar_ditolak_untuk_periode_draft(): void
    {
        $this->siapkanUiPayroll(PayrollPeriode::STATUS_DRAFT);

        Livewire::test(PayrollPage::class)
            ->set('periode', $this->periode)
            ->call('bukaBayarModal')
            ->assertSet('showBayarModal', false)
            ->assertDispatched('alert');
    }

    public function test_tombol_bayar_disabled_untuk_periode_draft(): void
    {
        $this->siapkanUiPayroll(PayrollPeriode::STATUS_DRAFT);

        $html = Livewire::test(PayrollPage::class)
            ->set('periode', $this->periode)
            ->html();
        $this->assertMatchesRegularExpression('/wire:click="bukaBayarModal"\s+disabled/', $html);
    }

    public function test_tombol_draft_disabled_untuk_periode_selesai(): void
    {
        $this->siapkanUiPayroll(PayrollPeriode::STATUS_SELESAI);

        $html = Livewire::test(PayrollPage::class)
            ->set('periode', $this->periode)
            ->html();
        $this->assertMatchesRegularExpression('/wire:click="hitungDraft"\s+disabled/', $html);
    }
}
