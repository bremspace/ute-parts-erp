<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Akunting\Livewire\AkuntingDashboard;
use App\Modules\Akunting\Models\AkunCOA;
use App\Modules\Akunting\Services\JurnalService;
use App\Modules\Rbac\Models\Cabang;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Audit-1 F-15 — jalur manual API/UI harus membungkus generateNoJurnal + post
 * dalam SATU transaksi sehingga lock counter (lockForUpdate) bertahan sampai
 * header+lines commit (pola POS/Servis/TutupBuku). Sebelumnya lock lepas saat
 * transaksi pendek generate commit → dua posting manual concurrent bisa
 * mendapat nomor sama dan gagal dengan pesan idempotency yang menyesatkan.
 *
 * Uji: spy JurnalService mencatat DB::transactionLevel() saat post() dipanggil;
 * level >= 1 membuktikan post berjalan di dalam transaksi pembungkus yang sama
 * dengan generate (lock masih dipegang).
 */
class JurnalManualGeneratePostLockTest extends TestCase
{
    use RefreshDatabase;

    private Cabang $cabang;

    private User $user;

    /** Level transaksi dasar test (RefreshDatabase membungkus test dlm transaksi). */
    private int $levelDasar = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->levelDasar = DB::transactionLevel();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->cabang = Cabang::create(['kode' => 'CBG-F15', 'nama' => 'Cabang F15', 'is_active' => true]);
        $this->user = User::create([
            'name' => 'Akuntan F15',
            'email' => 'akuntan-f15@test.com',
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);
        $this->user->cabangs()->attach($this->cabang->id);
        $this->user->assignRole('finance');
        session(['cabang_id' => $this->cabang->id]);
        $this->actingAs($this->user, 'web');

        AkunCOA::create(['kode' => '110-01', 'nama' => 'Kas', 'tipe' => 'aset', 'kelompok' => 'kas', 'saldo_normal' => 'debit', 'is_active' => true]);
        AkunCOA::create(['kode' => '410-01', 'nama' => 'Pendapatan Penjualan', 'tipe' => 'pendapatan', 'kelompok' => 'pendapatan_penjualan', 'saldo_normal' => 'kredit', 'is_active' => true]);
    }

    private function pasangSpy(): JurnalServiceSpyTransaksi
    {
        $spy = new JurnalServiceSpyTransaksi;
        $this->app->instance(JurnalService::class, $spy);

        return $spy;
    }

    public function test_api_manual_generate_dan_post_dalam_satu_transaksi(): void
    {
        $spy = $this->pasangSpy();

        $this->postJson('/api/akunting/jurnal-manual', [
            'tanggal' => now()->toDateString(),
            'deskripsi' => 'Uji lock generate+post API',
            'lines' => [
                ['akun_kode' => '110-01', 'debit' => 100000, 'kredit' => 0],
                ['akun_kode' => '410-01', 'debit' => 0, 'kredit' => 100000],
            ],
        ])->assertStatus(201);

        $this->assertGreaterThan($this->levelDasar, $spy->levelSaatGenerate, 'generateNoJurnal harus berjalan di dalam transaksi pembungkus');
        $this->assertSame($spy->levelSaatGenerate, $spy->levelSaatPost, 'post() harus berjalan di dalam transaksi yang sama dengan generate (lock counter belum lepas)');
        $this->assertDatabaseCount('jurnal_akuntansi', 2);
    }

    public function test_ui_manual_generate_dan_post_dalam_satu_transaksi(): void
    {
        $spy = $this->pasangSpy();

        Livewire::test(AkuntingDashboard::class)
            ->call('openJurnalManualModal')
            ->set('manualDeskripsi', 'Uji lock generate+post UI')
            ->set('manualLines', [
                ['akun_kode' => '110-01', 'sisi' => 'debit', 'jumlah' => 100000],
                ['akun_kode' => '410-01', 'sisi' => 'kredit', 'jumlah' => 100000],
            ])
            ->call('tinjauJurnalManual')
            ->assertSet('showJurnalConfirmation', true)
            ->call('simpanJurnalManual')
            ->assertSet('showJurnalManual', false);

        $this->assertGreaterThan($this->levelDasar, $spy->levelSaatGenerate, 'generateNoJurnal harus berjalan di dalam transaksi pembungkus');
        $this->assertSame($spy->levelSaatGenerate, $spy->levelSaatPost, 'post() harus berjalan di dalam transaksi yang sama dengan generate (lock counter belum lepas)');
        $this->assertDatabaseCount('jurnal_akuntansi', 2);
    }
}

/**
 * Spy pencatat level transaksi saat generate/post dipanggil (bukan mock —
 * perilaku asli tetap dijalankan; hanya level transaksi yang diamati).
 */
class JurnalServiceSpyTransaksi extends JurnalService
{
    public int $levelSaatPost = -1;

    public int $levelSaatGenerate = -1;

    public function post(
        string $noJurnal,
        $tanggal,
        string $sumber,
        array $lines,
        ?string $deskripsi = null,
        ?int $cabangId = null,
        ?int $userId = null,
        ?string $referensiTipe = null,
        ?int $referensiId = null,
        ?string $idempotencyKey = null,
        string $statusApproval = 'disetujui',
        ?string $lampiranPath = null,
        ?string $lampiranNama = null
    ): array {
        $this->levelSaatPost = DB::transactionLevel();

        return parent::post($noJurnal, $tanggal, $sumber, $lines, $deskripsi, $cabangId, $userId, $referensiTipe, $referensiId, $idempotencyKey, $statusApproval, $lampiranPath, $lampiranNama);
    }

    public function generateNoJurnal(string $sumber, ?int $cabangId = null): string
    {
        $this->levelSaatGenerate = DB::transactionLevel();

        return parent::generateNoJurnal($sumber, $cabangId);
    }
}
