<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Akunting\Livewire\AkuntingDashboard;
use App\Modules\Akunting\Models\AkunCOA;
use App\Modules\Akunting\Models\JurnalAkuntansi;
use App\Modules\Akunting\Services\JurnalService;
use App\Modules\Akunting\Services\TutupBukuService;
use App\Modules\Rbac\Models\Cabang;
use Carbon\Carbon;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class TutupBukuTest extends TestCase
{
    use RefreshDatabase;

    private Cabang $cabang;

    private User $superAdmin;

    private JurnalService $jurnalService;

    private TutupBukuService $tutupBukuService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seedCoa();

        $this->cabang = Cabang::create([
            'kode' => 'CB-TEST',
            'nama' => 'Cabang Test Tutup Buku',
            'is_active' => true,
        ]);

        $this->superAdmin = User::factory()->create([
            'name' => 'Super Admin Test',
            'email' => 'admin-tutupbuku@test.com',
        ]);
        $this->superAdmin->assignRole('super-admin');
        $this->superAdmin->cabangs()->attach($this->cabang->id);

        session(['cabang_id' => $this->cabang->id]);
        $this->actingAs($this->superAdmin, 'web');

        $this->jurnalService = app(JurnalService::class);
        $this->tutupBukuService = app(TutupBukuService::class);
    }

    private function seedCoa(): void
    {
        $accounts = [
            ['kode' => '110-01', 'nama' => 'Kas', 'tipe' => 'aset', 'kelompok' => 'kas', 'saldo_normal' => 'debit'],
            ['kode' => '120-01', 'nama' => 'Piutang Usaha', 'tipe' => 'aset', 'kelompok' => 'piutang', 'saldo_normal' => 'debit'],
            ['kode' => '130-01', 'nama' => 'Persediaan', 'tipe' => 'aset', 'kelompok' => 'persediaan', 'saldo_normal' => 'debit'],
            ['kode' => '210-01', 'nama' => 'Utang Usaha', 'tipe' => 'kewajiban', 'kelompok' => 'utang_usaha', 'saldo_normal' => 'kredit'],
            ['kode' => '310-01', 'nama' => 'Modal Pemilik', 'tipe' => 'ekuitas', 'kelompok' => 'modal', 'saldo_normal' => 'kredit'],
            ['kode' => '310-02', 'nama' => 'Laba Ditahan', 'tipe' => 'ekuitas', 'kelompok' => 'laba_ditahan', 'saldo_normal' => 'kredit'],
            ['kode' => '410-01', 'nama' => 'Pendapatan Penjualan', 'tipe' => 'pendapatan', 'kelompok' => 'pendapatan_penjualan', 'saldo_normal' => 'kredit'],
            ['kode' => '420-01', 'nama' => 'Pendapatan Jasa Servis', 'tipe' => 'pendapatan', 'kelompok' => 'pendapatan_jasa', 'saldo_normal' => 'kredit'],
            ['kode' => '510-01', 'nama' => 'Beban Komisi', 'tipe' => 'beban', 'kelompok' => 'beban_komisi', 'saldo_normal' => 'debit'],
            ['kode' => '510-02', 'nama' => 'HPP', 'tipe' => 'beban', 'kelompok' => 'hpp', 'saldo_normal' => 'debit'],
        ];

        foreach ($accounts as $a) {
            AkunCOA::create(array_merge($a, ['is_active' => true]));
        }
    }

    public function test_pra_cek_tutup_buku_menghitung_mutasi_pendapatan_dan_beban(): void
    {
        $tahun = 2026;
        $bulan = 8;
        $tanggalTransaksi = Carbon::create($tahun, $bulan, 15, 10, 0, 0);

        // Posting transaksi penjualan: Kas D 500.000 / Pendapatan K 500.000
        $this->jurnalService->post(
            noJurnal: 'JRL-TEST-001',
            tanggal: $tanggalTransaksi,
            sumber: 'manual',
            lines: [
                ['akun_kode' => '110-01', 'debit' => 500000, 'kredit' => 0],
                ['akun_kode' => '410-01', 'debit' => 0, 'kredit' => 500000],
            ],
            cabangId: $this->cabang->id,
            userId: $this->superAdmin->id
        );

        // Posting transaksi beban: HPP D 200.000 / Kas K 200.000
        $this->jurnalService->post(
            noJurnal: 'JRL-TEST-002',
            tanggal: $tanggalTransaksi,
            sumber: 'manual',
            lines: [
                ['akun_kode' => '510-02', 'debit' => 200000, 'kredit' => 0],
                ['akun_kode' => '110-01', 'debit' => 0, 'kredit' => 200000],
            ],
            cabangId: $this->cabang->id,
            userId: $this->superAdmin->id
        );

        $praCek = $this->tutupBukuService->praCek($this->cabang->id, $tahun, $bulan);

        $this->assertTrue($praCek['siap_tutup']);
        $this->assertTrue($praCek['neraca_balance']);
        $this->assertEquals(500000.0, $praCek['total_pendapatan']);
        $this->assertEquals(200000.0, $praCek['total_beban']);
        $this->assertEquals(300000.0, $praCek['laba_rugi_bersih']);
        $this->assertCount(3, $praCek['baris_jurnal_rencana']); // 1 pendapatan, 1 beban, 1 laba ditahan
    }

    public function test_tutup_buku_berhasil_membuat_jurnal_penutup_dan_mengunci_periode(): void
    {
        $tahun = 2026;
        $bulan = 8;
        $tanggalTransaksi = Carbon::create($tahun, $bulan, 10, 10, 0, 0);

        // Posting transaksi
        $this->jurnalService->post(
            noJurnal: 'JRL-TEST-003',
            tanggal: $tanggalTransaksi,
            sumber: 'manual',
            lines: [
                ['akun_kode' => '110-01', 'debit' => 1000000, 'kredit' => 0],
                ['akun_kode' => '410-01', 'debit' => 0, 'kredit' => 1000000],
            ],
            cabangId: $this->cabang->id,
            userId: $this->superAdmin->id
        );

        $periode = $this->tutupBukuService->tutupBuku(
            cabangId: $this->cabang->id,
            tahun: $tahun,
            bulan: $bulan,
            userId: $this->superAdmin->id,
            catatan: 'Tutup buku audit Q3 2026'
        );

        $this->assertTrue($periode->isClosed());
        $this->assertNotNull($periode->closed_at);
        $this->assertEquals($this->superAdmin->id, $periode->closed_by);
        $this->assertNotNull($periode->no_jurnal_penutup);

        // Verifikasi Jurnal Penutup
        $penutupLines = JurnalAkuntansi::where('no_jurnal', $periode->no_jurnal_penutup)
            ->where('cabang_id', $this->cabang->id)
            ->with('akun')
            ->get();

        $this->assertNotEmpty($penutupLines);

        // Pendapatan ditutup di DEBIT
        $linePendapatan = $penutupLines->firstWhere('akun.kode', '410-01');
        $this->assertNotNull($linePendapatan);
        $this->assertEquals(1000000.0, (float) $linePendapatan->debit);
        $this->assertEquals(0.0, (float) $linePendapatan->kredit);

        // Laba Ditahan dikredit sebesar laba
        $lineLaba = $penutupLines->firstWhere('akun.kode', '310-02');
        $this->assertNotNull($lineLaba);
        $this->assertEquals(0.0, (float) $lineLaba->debit);
        $this->assertEquals(1000000.0, (float) $lineLaba->kredit);

        // Total Debit == Total Kredit
        $this->assertEquals($penutupLines->sum('debit'), $penutupLines->sum('kredit'));
    }

    public function test_jurnal_service_menolak_posting_ke_periode_yang_sudah_ditutup(): void
    {
        $tahun = 2026;
        $bulan = 7;

        // Tutup periode bulan 7
        $this->tutupBukuService->tutupBuku(
            cabangId: $this->cabang->id,
            tahun: $tahun,
            bulan: $bulan,
            userId: $this->superAdmin->id,
            catatan: 'Periode telah ditutup'
        );

        // Mencoba posting transaksi pada tanggal di bulan 7
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Periode akuntansi Juli 2026 sudah ditutup (tutup buku). Transaksi tidak dapat diposting.');

        $this->jurnalService->post(
            noJurnal: 'JRL-TEST-FAIL',
            tanggal: Carbon::create(2026, 7, 20),
            sumber: 'manual',
            lines: [
                ['akun_kode' => '110-01', 'debit' => 50000, 'kredit' => 0],
                ['akun_kode' => '410-01', 'debit' => 0, 'kredit' => 50000],
            ],
            cabangId: $this->cabang->id,
            userId: $this->superAdmin->id
        );
    }

    public function test_super_admin_dapat_membuka_kembali_periode_dengan_jurnal_pembalik(): void
    {
        $tahun = 2026;
        $bulan = 6;
        $tanggalTransaksi = Carbon::create($tahun, $bulan, 5);

        // Transaksi awal
        $this->jurnalService->post(
            noJurnal: 'JRL-TEST-005',
            tanggal: $tanggalTransaksi,
            sumber: 'manual',
            lines: [
                ['akun_kode' => '110-01', 'debit' => 100000, 'kredit' => 0],
                ['akun_kode' => '410-01', 'debit' => 0, 'kredit' => 100000],
            ],
            cabangId: $this->cabang->id,
            userId: $this->superAdmin->id
        );

        // Tutup periode
        $this->tutupBukuService->tutupBuku($this->cabang->id, $tahun, $bulan, $this->superAdmin->id);

        // Buka kembali periode
        $periodeDibuka = $this->tutupBukuService->bukaBukuKembali(
            cabangId: $this->cabang->id,
            tahun: $tahun,
            bulan: $bulan,
            userId: $this->superAdmin->id,
            alasan: 'Koreksi audit PSAK'
        );

        $this->assertTrue($periodeDibuka->isOpen());
        $this->assertNull($periodeDibuka->closed_at);
        $this->assertNull($periodeDibuka->closed_by);
        $this->assertStringContainsString('Koreksi audit PSAK', $periodeDibuka->catatan);

        // Sekarang transaksi baru pada periode tsb harus BERHASIL diposting
        $created = $this->jurnalService->post(
            noJurnal: 'JRL-TEST-006',
            tanggal: Carbon::create($tahun, $bulan, 25),
            sumber: 'manual',
            lines: [
                ['akun_kode' => '110-01', 'debit' => 50000, 'kredit' => 0],
                ['akun_kode' => '410-01', 'debit' => 0, 'kredit' => 50000],
            ],
            cabangId: $this->cabang->id,
            userId: $this->superAdmin->id
        );

        $this->assertNotEmpty($created);
    }

    public function test_livewire_tab_tutup_buku_dapat_diakses_dan_menampilkan_status(): void
    {
        Livewire::test(AkuntingDashboard::class)
            ->set('activeTab', 'tutup-buku')
            ->assertSee('Pengendalian Periode Akuntansi & Tutup Buku')
            ->assertSee('PERIODE TERBUKA (OPEN)')
            ->call('bukaModalTutupBuku')
            ->assertSet('showTutupBukuModal', true)
            ->call('eksekusiTutupBuku')
            ->assertSet('showTutupBukuModal', false)
            ->assertSee('PERIODE TERKUNCI (CLOSED)');
    }
}
