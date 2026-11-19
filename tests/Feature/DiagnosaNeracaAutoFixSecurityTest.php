<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Akunting\Livewire\AkuntingDashboard;
use App\Modules\Akunting\Models\AkunCOA;
use App\Modules\Akunting\Models\JurnalAkuntansi;
use App\Modules\Akunting\Models\JurnalHeader;
use App\Modules\Akunting\Models\KasMatching;
use App\Modules\Akunting\Models\PeriodeAkuntansi;
use App\Modules\Akunting\Services\DiagnosaNeracaService;
use App\Modules\Akunting\Services\JurnalService;
use App\Modules\Rbac\Models\Cabang;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * [B-1 / D-1 / D-2 / D-3] Keamanan auto-fix DiagnosaNeracaService:
 * - Tidak boleh memodifikasi jurnal existing (immutability); koreksi lewat
 *   JurnalService resmi (balance, akun aktif, periode terbuka, header sinkron).
 * - Nominal tidak boleh client-controlled: hitung ulang dari DB.
 * - Izin server-side (akunting.approve) + scope cabang fail-closed.
 * - Idempoten: tidak bisa diposting dobel (semua jalur auto-fix).
 */
class DiagnosaNeracaAutoFixSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected User $userTerbatas;

    protected User $userTanpaIzin;

    protected Cabang $cabang;

    protected Cabang $cabang2;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->cabang = Cabang::create([
            'nama' => 'Cabang Utama', 'kode' => 'C01',
            'alamat' => 'Jl. Test No. 1', 'telepon' => '081234567890', 'is_aktif' => true,
        ]);
        $this->cabang2 = Cabang::create([
            'nama' => 'Cabang Kedua', 'kode' => 'C02',
            'alamat' => 'Jl. Test No. 2', 'telepon' => '081234567891', 'is_aktif' => true,
        ]);

        $this->user = User::create([
            'name' => 'Finance Tester', 'email' => 'finance-security@uteparts.id',
            'password' => Hash::make('secret123'), 'is_active' => true,
        ]);
        $this->user->assignRole('finance'); // akunting.approve + laporan.konsolidasi
        $this->user->cabangs()->attach([$this->cabang->id, $this->cabang2->id]);

        // Finance tanpa hak konsolidasi: hanya boleh cabang yang di-scope.
        $roleTerbatas = Role::findOrCreate('finance-terbatas', 'web');
        $roleTerbatas->givePermissionTo(['akunting.view', 'akunting.create', 'akunting.approve']);
        $this->userTerbatas = User::create([
            'name' => 'Finance Cabang', 'email' => 'finance-cabang@uteparts.id',
            'password' => Hash::make('secret123'), 'is_active' => true,
        ]);
        $this->userTerbatas->assignRole('finance-terbatas');
        $this->userTerbatas->cabangs()->attach($this->cabang->id);

        // User hanya lihat: tidak boleh eksekusi auto-fix apa pun.
        $roleViewer = Role::findOrCreate('viewer-akunting', 'web');
        $roleViewer->givePermissionTo(['akunting.view']);
        $this->userTanpaIzin = User::create([
            'name' => 'Viewer Akunting', 'email' => 'viewer-akunting@uteparts.id',
            'password' => Hash::make('secret123'), 'is_active' => true,
        ]);
        $this->userTanpaIzin->assignRole('viewer-akunting');
        $this->userTanpaIzin->cabangs()->attach($this->cabang->id);

        session(['cabang_id' => $this->cabang->id]);
        $this->actingAs($this->user, 'web');

        $daftarCoa = [
            ['kode' => '110-01', 'nama' => 'Kas Besar', 'kelompok' => 'kas', 'tipe' => 'aset', 'saldo_normal' => 'debit'],
            ['kode' => '110-04', 'nama' => 'Kas Laci Kasir', 'kelompok' => 'kas', 'tipe' => 'aset', 'saldo_normal' => 'debit'],
            ['kode' => '310-01', 'nama' => 'Modal Pemilik', 'kelompok' => 'modal', 'tipe' => 'ekuitas', 'saldo_normal' => 'kredit'],
            ['kode' => '430-01', 'nama' => 'Pendapatan Lain-lain', 'kelompok' => 'pendapatan_lain', 'tipe' => 'pendapatan', 'saldo_normal' => 'kredit'],
            ['kode' => '520-07', 'nama' => 'Beban Selisih Kas', 'kelompok' => 'beban_lain', 'tipe' => 'beban', 'saldo_normal' => 'debit'],
            ['kode' => '520-05', 'nama' => 'Beban Lain-lain (Suspense)', 'kelompok' => 'beban_lain', 'tipe' => 'beban', 'saldo_normal' => 'debit'],
        ];
        foreach ($daftarCoa as $item) {
            AkunCOA::updateOrCreate(['kode' => $item['kode']], array_merge($item, ['is_active' => true]));
        }
    }

    /** Buat jurnal timpang langsung di DB (simulasi korupsi data legacy). */
    protected function buatJurnalTimpang(string $noJurnal, int $cabangId, float $debit): void
    {
        $akunKas = AkunCOA::where('kode', '110-01')->first();
        DB::table('jurnal_akuntansi')->insert([
            'cabang_id' => $cabangId,
            'akun_coa_id' => $akunKas->id,
            'no_jurnal' => $noJurnal,
            'tanggal' => now(),
            'sumber' => 'manual',
            'debit' => $debit,
            'kredit' => 0,
            'deskripsi' => 'Transaksi timpang tanpa kredit',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    protected function service(): DiagnosaNeracaService
    {
        return app(DiagnosaNeracaService::class);
    }

    // ===== B-1 / D-1: seimbangkan_jurnal =====

    public function test_seimbangkan_jurnal_memposting_koreksi_terpisah_tanpa_mengubah_jurnal_asal(): void
    {
        $this->buatJurnalTimpang('JRL-TIMPANG-01', $this->cabang->id, 500000);

        $res = $this->service()->perbaikiOtomatis('seimbangkan_jurnal', [
            'no_jurnal' => 'JRL-TIMPANG-01',
            'cabang_id' => $this->cabang->id,
            'selisih' => 500000,
            'sisi_kurang' => 'kredit',
        ], $this->user->id, $this->cabang->id);

        $this->assertTrue($res['success'], $res['message']);

        // Jurnal asal TIDAK boleh berubah (immutability): tetap 1 baris D 500rb / K 0.
        $asal = JurnalAkuntansi::where('no_jurnal', 'JRL-TIMPANG-01')->get();
        $this->assertCount(1, $asal);
        $this->assertEquals(500000, (float) $asal->sum('debit'));
        $this->assertEquals(0, (float) $asal->sum('kredit'));

        // Koreksi = jurnal BARU terpisah, balance, lewat mesin resmi (header sinkron).
        $nomorBaru = JurnalAkuntansi::where('no_jurnal', '!=', 'JRL-TIMPANG-01')->pluck('no_jurnal')->unique();
        $this->assertCount(1, $nomorBaru);
        $noKoreksi = $nomorBaru->first();
        $barisKoreksi = JurnalAkuntansi::where('no_jurnal', $noKoreksi)->get();
        $this->assertEquals(round((float) $barisKoreksi->sum('debit'), 2), round((float) $barisKoreksi->sum('kredit'), 2));
        $this->assertEquals(500000, (float) $barisKoreksi->sum('debit'));

        $header = JurnalHeader::where('no_jurnal', $noKoreksi)->where('cabang_key', $this->cabang->id)->first();
        $this->assertNotNull($header, 'Koreksi wajib punya header (mesin JurnalService resmi).');
        $this->assertEquals(500000, (float) $header->total_debit);
        $this->assertEquals(500000, (float) $header->total_kredit);
        $this->assertEquals($barisKoreksi->count(), (int) $header->jumlah_baris);
        $this->assertNotNull($header->idempotency_key, 'Koreksi wajib idempoten.');

        // Selisih diparkir di akun suspense 520-05, bukan akun beban sembarang.
        $suspense = AkunCOA::where('kode', '520-05')->first();
        $this->assertTrue($barisKoreksi->contains('akun_coa_id', $suspense->id));
    }

    public function test_seimbangkan_jurnal_mengabaikan_nominal_dan_sisi_dari_payload(): void
    {
        $this->buatJurnalTimpang('JRL-TIMPANG-02', $this->cabang->id, 500000);

        // Payload client berbohong: nominal arbitrary, sisi terbalik, cabang lain.
        $res = $this->service()->perbaikiOtomatis('seimbangkan_jurnal', [
            'no_jurnal' => 'JRL-TIMPANG-02',
            'cabang_id' => $this->cabang2->id,
            'selisih' => 99999999,
            'sisi_kurang' => 'debit',
        ], $this->user->id, $this->cabang->id);

        $this->assertTrue($res['success'], $res['message']);

        // Tidak boleh ada baris bernominal payload di jurnal mana pun.
        $this->assertEquals(0, JurnalAkuntansi::where('debit', 99999999)->orWhere('kredit', 99999999)->count());

        // Koreksi memakai hitung ulang DB: 500rb, sisi benar (kas dikredit, suspense didebit).
        $koreksi = JurnalAkuntansi::where('no_jurnal', '!=', 'JRL-TIMPANG-02')->get();
        $this->assertEquals(500000, (float) $koreksi->sum('debit'));
        $this->assertEquals(500000, (float) $koreksi->sum('kredit'));
        $akunKas = AkunCOA::where('kode', '110-01')->first();
        $barisKas = $koreksi->firstWhere('akun_coa_id', $akunKas->id);
        $this->assertNotNull($barisKas);
        $this->assertEquals(500000, (float) $barisKas->kredit);
        $this->assertEquals(0, (float) $barisKas->debit);

        // Tidak ada jurnal baru di cabang lain.
        $this->assertEquals(0, JurnalAkuntansi::where('cabang_id', $this->cabang2->id)->count());
    }

    public function test_seimbangkan_jurnal_idempoten_tidak_bisa_diulang(): void
    {
        $this->buatJurnalTimpang('JRL-TIMPANG-03', $this->cabang->id, 500000);
        $payload = ['no_jurnal' => 'JRL-TIMPANG-03', 'cabang_id' => $this->cabang->id, 'selisih' => 500000, 'sisi_kurang' => 'kredit'];

        $res1 = $this->service()->perbaikiOtomatis('seimbangkan_jurnal', $payload, $this->user->id, $this->cabang->id);
        $this->assertTrue($res1['success'], $res1['message']);

        $res2 = $this->service()->perbaikiOtomatis('seimbangkan_jurnal', $payload, $this->user->id, $this->cabang->id);
        $this->assertFalse($res2['success'], 'Panggilan ulang harus ditolak (idempotensi).');

        // Tetap tepat 2 entri: jurnal asal + 1 koreksi.
        $this->assertEquals(2, JurnalAkuntansi::distinct()->count('no_jurnal'));
    }

    public function test_seimbangkan_jurnal_tanpa_izin_akunting_approve_ditolak(): void
    {
        $this->buatJurnalTimpang('JRL-TIMPANG-04', $this->cabang->id, 500000);
        $this->actingAs($this->userTanpaIzin, 'web');

        $res = $this->service()->perbaikiOtomatis('seimbangkan_jurnal', [
            'no_jurnal' => 'JRL-TIMPANG-04', 'selisih' => 500000, 'sisi_kurang' => 'kredit',
        ], $this->userTanpaIzin->id, $this->cabang->id);

        $this->assertFalse($res['success']);
        $this->assertEquals(1, JurnalAkuntansi::distinct()->count('no_jurnal'), 'Tidak boleh ada posting tanpa izin.');
    }

    public function test_seimbangkan_jurnal_scope_cabang_fail_closed(): void
    {
        $this->buatJurnalTimpang('JRL-C2-TIMPANG', $this->cabang2->id, 700000);
        $this->actingAs($this->userTerbatas, 'web'); // tanpa laporan.konsolidasi

        // Jurnal cabang lain, scope cabang sendiri -> tolak.
        $res = $this->service()->perbaikiOtomatis('seimbangkan_jurnal', [
            'no_jurnal' => 'JRL-C2-TIMPANG', 'cabang_id' => $this->cabang2->id, 'selisih' => 700000, 'sisi_kurang' => 'kredit',
        ], $this->userTerbatas->id, $this->cabang->id);
        $this->assertFalse($res['success']);

        // Scope 0 (tanpa cabang aktif) -> tolak.
        $res0 = $this->service()->perbaikiOtomatis('seimbangkan_jurnal', [
            'no_jurnal' => 'JRL-C2-TIMPANG', 'selisih' => 700000, 'sisi_kurang' => 'kredit',
        ], $this->userTerbatas->id, 0);
        $this->assertFalse($res0['success']);

        // Scope null tanpa hak konsolidasi -> tolak (fail-closed).
        $resNull = $this->service()->perbaikiOtomatis('seimbangkan_jurnal', [
            'no_jurnal' => 'JRL-C2-TIMPANG', 'selisih' => 700000, 'sisi_kurang' => 'kredit',
        ], $this->userTerbatas->id, null);
        $this->assertFalse($resNull['success']);

        $this->assertEquals(1, JurnalAkuntansi::distinct()->count('no_jurnal'), 'Tidak boleh ada posting lintas cabang.');
    }

    public function test_seimbangkan_jurnal_konsolidasi_diizinkan_dengan_hak_konsolidasi(): void
    {
        $this->buatJurnalTimpang('JRL-C2-KONSOL', $this->cabang2->id, 700000);
        // finance punya laporan.konsolidasi; scope null = konsolidasi.
        $res = $this->service()->perbaikiOtomatis('seimbangkan_jurnal', [
            'no_jurnal' => 'JRL-C2-KONSOL', 'selisih' => 700000, 'sisi_kurang' => 'kredit',
        ], $this->user->id, null);

        $this->assertTrue($res['success'], $res['message']);
        $this->assertEquals(2, JurnalAkuntansi::distinct()->count('no_jurnal'));
        $this->assertEquals(1, JurnalHeader::where('cabang_key', $this->cabang2->id)->count());
    }

    public function test_seimbangkan_jurnal_periode_tutup_ditolak(): void
    {
        $this->buatJurnalTimpang('JRL-TIMPANG-TUTUP', $this->cabang->id, 500000);
        PeriodeAkuntansi::create([
            'cabang_id' => $this->cabang->id,
            'tahun' => (int) now()->format('Y'),
            'bulan' => (int) now()->format('n'),
            'status' => 'closed',
        ]);

        $res = $this->service()->perbaikiOtomatis('seimbangkan_jurnal', [
            'no_jurnal' => 'JRL-TIMPANG-TUTUP', 'selisih' => 500000, 'sisi_kurang' => 'kredit',
        ], $this->user->id, $this->cabang->id);

        $this->assertFalse($res['success']);
        $this->assertEquals(1, JurnalAkuntansi::distinct()->count('no_jurnal'), 'Periode closed wajib memblokir posting.');
    }

    public function test_seimbangkan_jurnal_sudah_balance_tidak_menambah_baris(): void
    {
        app(JurnalService::class)->post(
            'JRL-BALANCE-01', now(), 'manual',
            [
                ['akun_kode' => '110-01', 'debit' => 100000, 'kredit' => 0],
                ['akun_kode' => '310-01', 'debit' => 0, 'kredit' => 100000],
            ],
            'Jurnal sudah balance', $this->cabang->id, $this->user->id
        );

        // Payload client mengklaim selisih padahal DB balance -> tolak.
        $res = $this->service()->perbaikiOtomatis('seimbangkan_jurnal', [
            'no_jurnal' => 'JRL-BALANCE-01', 'selisih' => 1000, 'sisi_kurang' => 'kredit',
        ], $this->user->id, $this->cabang->id);

        $this->assertFalse($res['success']);
        $this->assertEquals(2, JurnalAkuntansi::where('no_jurnal', 'JRL-BALANCE-01')->count());
        $this->assertEquals(1, JurnalAkuntansi::distinct()->count('no_jurnal'));
    }

    // ===== D-3: posting_selisih_kas_matching =====

    protected function buatMatching(int $cabangId, float $selisih): KasMatching
    {
        $akunKas = AkunCOA::where('kode', '110-01')->first();

        return KasMatching::create([
            'cabang_id' => $cabangId,
            'user_id' => $this->user->id,
            'akun_id' => $akunKas->id,
            'tanggal' => now()->toDateString(),
            'saldo_sistem' => 1000000,
            'saldo_fisik' => 1000000 + $selisih,
            'selisih' => $selisih,
            'status' => 'selisih',
        ]);
    }

    public function test_posting_selisih_kas_matching_idempoten(): void
    {
        $matching = $this->buatMatching($this->cabang->id, 250000);

        $res1 = $this->service()->perbaikiOtomatis('posting_selisih_kas_matching', [
            'kas_matching_id' => $matching->id,
        ], $this->user->id, $this->cabang->id);
        $this->assertTrue($res1['success'], $res1['message']);

        $res2 = $this->service()->perbaikiOtomatis('posting_selisih_kas_matching', [
            'kas_matching_id' => $matching->id,
        ], $this->user->id, $this->cabang->id);
        $this->assertFalse($res2['success'], 'Matching sudah disesuaikan tidak boleh diposting ulang.');

        $this->assertEquals(2, JurnalAkuntansi::where('referensi_tipe', KasMatching::class)->where('referensi_id', $matching->id)->count());
    }

    public function test_posting_selisih_kas_matching_memakai_nominal_db_bukan_payload(): void
    {
        $matching = $this->buatMatching($this->cabang->id, 250000);

        $res = $this->service()->perbaikiOtomatis('posting_selisih_kas_matching', [
            'kas_matching_id' => $matching->id,
            'selisih' => 99999999, // payload client berbohong; harus diabaikan
        ], $this->user->id, $this->cabang->id);

        $this->assertTrue($res['success'], $res['message']);
        $this->assertEquals(0, JurnalAkuntansi::where('debit', 99999999)->orWhere('kredit', 99999999)->count());
        $this->assertEquals(250000, (float) JurnalAkuntansi::where('referensi_tipe', KasMatching::class)->where('referensi_id', $matching->id)->sum('debit'));
    }

    public function test_posting_selisih_kas_matching_lintas_cabang_ditolak(): void
    {
        $matching = $this->buatMatching($this->cabang2->id, 250000);
        $this->actingAs($this->userTerbatas, 'web');

        $res = $this->service()->perbaikiOtomatis('posting_selisih_kas_matching', [
            'kas_matching_id' => $matching->id,
        ], $this->userTerbatas->id, $this->cabang->id);

        $this->assertFalse($res['success']);
        $matching->refresh();
        $this->assertNull($matching->jurnal_id);
        $this->assertEquals(0, JurnalAkuntansi::count());
    }

    public function test_posting_selisih_kas_matching_tanpa_izin_ditolak(): void
    {
        $matching = $this->buatMatching($this->cabang->id, 250000);
        $this->actingAs($this->userTanpaIzin, 'web');

        $res = $this->service()->perbaikiOtomatis('posting_selisih_kas_matching', [
            'kas_matching_id' => $matching->id,
        ], $this->userTanpaIzin->id, $this->cabang->id);

        $this->assertFalse($res['success']);
        $this->assertEquals(0, JurnalAkuntansi::count());
    }

    // ===== D-2: posting_selisih_kas_sesi =====

    protected function buatSesiSelisih(int $cabangId, float $selisih): int
    {
        return (int) DB::table('kas_sesi')->insertGetId([
            'cabang_id' => $cabangId,
            'user_id' => $this->user->id,
            'saldo_awal' => 0,
            'saldo_akhir_sistem' => 1000000,
            'saldo_akhir_fisik' => 1000000 + $selisih,
            'selisih' => $selisih,
            'status' => 'tutup',
            'dibuka_at' => now()->subHours(8),
            'ditutup_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_posting_selisih_kas_sesi_idempoten(): void
    {
        $sesiId = $this->buatSesiSelisih($this->cabang->id, 50000);

        $res1 = $this->service()->perbaikiOtomatis('posting_selisih_kas_sesi', [
            'kas_sesi_id' => $sesiId,
        ], $this->user->id, $this->cabang->id);
        $this->assertTrue($res1['success'], $res1['message']);

        $res2 = $this->service()->perbaikiOtomatis('posting_selisih_kas_sesi', [
            'kas_sesi_id' => $sesiId,
        ], $this->user->id, $this->cabang->id);
        $this->assertFalse($res2['success'], 'Selisih sesi yang sudah dijurnal tidak boleh diposting ulang.');

        $this->assertEquals(2, JurnalAkuntansi::where('referensi_tipe', 'kas_sesi')->where('referensi_id', $sesiId)->count());
    }

    public function test_posting_selisih_kas_sesi_yang_sudah_disetujui_tidak_diposting_ulang(): void
    {
        $sesiId = $this->buatSesiSelisih($this->cabang->id, 50000);

        // Simulasi jalur approval (KasSesiState::postJurnalSelisih): deskripsi
        // huruf kecil "Selisih kas sesi #id (disetujui)" — deteksi tidak boleh
        // bergantung pada LIKE deskripsi.
        app(JurnalService::class)->post(
            app(JurnalService::class)->generateNoJurnal('kas', $this->cabang->id),
            now(), 'manual',
            [
                ['akun_kode' => '110-04', 'debit' => 50000, 'kredit' => 0],
                ['akun_kode' => '520-07', 'debit' => 0, 'kredit' => 50000],
            ],
            "Selisih kas sesi #{$sesiId} (disetujui)",
            $this->cabang->id, $this->user->id, 'kas_sesi', $sesiId
        );

        $res = $this->service()->perbaikiOtomatis('posting_selisih_kas_sesi', [
            'kas_sesi_id' => $sesiId,
        ], $this->user->id, $this->cabang->id);
        $this->assertFalse($res['success'], 'Sesi yang sudah disesuaikan via approval tidak boleh dijurnal lagi.');
        $this->assertEquals(2, JurnalAkuntansi::where('referensi_tipe', 'kas_sesi')->where('referensi_id', $sesiId)->count());

        // Diagnosa juga tidak boleh lagi menawarkan auto-fix untuk sesi ini.
        $hasil = $this->service()->diagnosa($this->cabang->id);
        $temuanSesi = collect($hasil['daftar_temuan'])->firstWhere('id', 'kas-sesi-selisih-'.$sesiId);
        $this->assertNull($temuanSesi, 'Sesi sudah disesuaikan tidak boleh muncul sebagai temuan auto-fix.');
    }

    public function test_posting_selisih_kas_sesi_status_buka_ditolak(): void
    {
        $sesiId = $this->buatSesiSelisih($this->cabang->id, 50000);
        DB::table('kas_sesi')->where('id', $sesiId)->update(['status' => 'buka']);

        $res = $this->service()->perbaikiOtomatis('posting_selisih_kas_sesi', [
            'kas_sesi_id' => $sesiId,
        ], $this->user->id, $this->cabang->id);

        $this->assertFalse($res['success'], 'Sesi belum tutup tidak boleh disesuaikan otomatis.');
        $this->assertEquals(0, JurnalAkuntansi::count());
    }

    public function test_posting_selisih_kas_sesi_lintas_cabang_ditolak(): void
    {
        $sesiId = $this->buatSesiSelisih($this->cabang2->id, 50000);
        $this->actingAs($this->userTerbatas, 'web');

        $res = $this->service()->perbaikiOtomatis('posting_selisih_kas_sesi', [
            'kas_sesi_id' => $sesiId,
        ], $this->userTerbatas->id, $this->cabang->id);

        $this->assertFalse($res['success']);
        $this->assertEquals(0, JurnalAkuntansi::count());
    }

    public function test_posting_selisih_kas_sesi_tanpa_izin_ditolak(): void
    {
        $sesiId = $this->buatSesiSelisih($this->cabang->id, 50000);
        $this->actingAs($this->userTanpaIzin, 'web');

        $res = $this->service()->perbaikiOtomatis('posting_selisih_kas_sesi', [
            'kas_sesi_id' => $sesiId,
        ], $this->userTanpaIzin->id, $this->cabang->id);

        $this->assertFalse($res['success']);
        $this->assertEquals(0, JurnalAkuntansi::count());
    }

    // ===== Pintu masuk Livewire (B-1/D-1/D-3): izin + scope cabang aktif =====

    public function test_livewire_eksekusi_solusi_diagnosa_tanpa_izin_403(): void
    {
        $this->buatJurnalTimpang('JRL-LW-01', $this->cabang->id, 500000);
        $this->actingAs($this->userTanpaIzin, 'web');

        Livewire::test(AkuntingDashboard::class)
            ->call('eksekusiSolusiDiagnosa', 'seimbangkan_jurnal', [
                'no_jurnal' => 'JRL-LW-01', 'selisih' => 500000, 'sisi_kurang' => 'kredit',
            ])
            ->assertForbidden();

        $this->assertEquals(1, JurnalAkuntansi::distinct()->count('no_jurnal'));
    }

    public function test_livewire_posting_penyesuaian_kas_terkunci_ke_cabang_aktif(): void
    {
        // finance punya laporan.konsolidasi, tetapi tampilan cabang (default)
        // wajib tetap scope ke cabang aktif — bukan konsolidasi implisit.
        $matching = $this->buatMatching($this->cabang2->id, 250000);

        Livewire::test(AkuntingDashboard::class)
            ->call('postingPenyesuaianKas', $matching->id)
            ->assertDispatched('alert');

        $matching->refresh();
        $this->assertNull($matching->jurnal_id, 'Matching cabang lain tidak boleh diposting dari scope cabang aktif.');
        $this->assertEquals(0, JurnalAkuntansi::count());
    }
}
