<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Akunting\Models\AkunCOA;
use App\Modules\Akunting\Services\JurnalService;
use App\Modules\Rbac\Models\Cabang;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * [B-10e / P2-1] Validasi jurnal manual di API [API: ACC-04] harus SAMA dengan
 * form UI (Livewire AkuntingDashboard) dan dengan JurnalService::post():
 *  - akun COA nonaktif DITOLAK (pesan Indonesia),
 *  - sisi debit/kredit harus sesuai saldo_normal akun (pesan Indonesia),
 *  - nominal > 0, dan jurnal wajib balance (debit = kredit) sebelum disimpan.
 *
 * Sumber aturan: ValidasiBarisJurnal (dipakai API + JurnalService).
 */
class JurnalManualApiTest extends TestCase
{
    use RefreshDatabase;

    private Cabang $cabang;

    private User $user;

    private AkunCOA $kas;

    private AkunCOA $utang;

    private AkunCOA $pendapatan;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->cabang = Cabang::create([
            'kode' => 'CBG-B10E',
            'nama' => 'Cabang Jurnal Manual API',
            'is_active' => true,
        ]);

        $this->user = User::create([
            'name' => 'Akuntan B10e',
            'email' => 'akuntan-b10e@test.com',
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);
        $this->user->cabangs()->attach($this->cabang->id);
        $this->user->assignRole('finance'); // permission akunting.create
        session(['cabang_id' => $this->cabang->id]);
        $this->actingAs($this->user, 'web');

        $this->kas = $this->buatAkun('110-01', 'Kas', 'aset', 'kas', 'debit');
        $this->utang = $this->buatAkun('210-01', 'Utang Usaha', 'kewajiban', 'utang_usaha', 'kredit');
        $this->pendapatan = $this->buatAkun('410-01', 'Pendapatan Penjualan', 'pendapatan', 'pendapatan_penjualan', 'kredit');
    }

    private function buatAkun(string $kode, string $nama, string $tipe, string $kelompok, string $saldo, bool $aktif = true): AkunCOA
    {
        return AkunCOA::create([
            'kode' => $kode,
            'nama' => $nama,
            'tipe' => $tipe,
            'kelompok' => $kelompok,
            'saldo_normal' => $saldo,
            'is_active' => $aktif,
        ]);
    }

    /**
     * @param  array<int,array<string,mixed>>  $lines
     * @return TestResponse
     */
    private function postJurnal(array $lines)
    {
        return $this->postJson('/api/akunting/jurnal-manual', [
            'tanggal' => now()->toDateString(),
            'deskripsi' => 'Jurnal manual via API',
            'lines' => $lines,
        ]);
    }

    public function test_api_menolak_akun_nonaktif_dengan_pesan_indonesia(): void
    {
        $this->buatAkun('110-99', 'Kas Legacy Nonaktif', 'aset', 'kas', 'debit', aktif: false);

        $resp = $this->postJurnal([
            ['akun_kode' => '110-99', 'debit' => 100000, 'kredit' => 0],
            ['akun_kode' => $this->utang->kode, 'debit' => 0, 'kredit' => 100000],
        ])->assertStatus(422);

        $pesan = (string) $resp->json('message');
        $this->assertStringContainsString('110-99', $pesan);
        $this->assertStringContainsString('nonaktif', $pesan);
        $this->assertFalse($resp->json('success'));

        $this->assertDatabaseCount('jurnal_akuntansi', 0);
    }

    public function test_api_menolak_sisi_berlawanan_dengan_saldo_normal(): void
    {
        $resp = $this->postJurnal([
            ['akun_kode' => $this->pendapatan->kode, 'debit' => 100000, 'kredit' => 0],
            ['akun_kode' => $this->utang->kode, 'debit' => 0, 'kredit' => 100000],
        ])->assertStatus(422);

        $pesan = (string) $resp->json('message');
        $this->assertStringContainsString('410-01', $pesan);
        $this->assertStringContainsString('tidak dapat dipilih pada sisi debit', $pesan);
        $this->assertStringContainsString('saldo normalnya kredit', $pesan);

        $this->assertDatabaseCount('jurnal_akuntansi', 0);
    }

    public function test_api_menolak_nominal_nol(): void
    {
        $resp = $this->postJurnal([
            ['akun_kode' => $this->kas->kode, 'debit' => 0, 'kredit' => 0],
            ['akun_kode' => $this->utang->kode, 'debit' => 0, 'kredit' => 0],
        ])->assertStatus(422);

        $this->assertStringContainsString('lebih besar dari nol', (string) $resp->json('message'));
        $this->assertDatabaseCount('jurnal_akuntansi', 0);
    }

    public function test_api_menolak_akun_tidak_ada_dengan_pesan_indonesia(): void
    {
        $resp = $this->postJurnal([
            ['akun_kode' => '999-99', 'debit' => 100000, 'kredit' => 0],
            ['akun_kode' => $this->utang->kode, 'debit' => 0, 'kredit' => 100000],
        ])->assertStatus(422);

        $this->assertStringContainsString('Akun COA tidak ditemukan: 999-99', (string) $resp->json('message'));
        $this->assertDatabaseCount('jurnal_akuntansi', 0);
    }

    public function test_api_tetap_menolak_jurnal_tidak_balance(): void
    {
        $resp = $this->postJurnal([
            ['akun_kode' => $this->kas->kode, 'debit' => 100000, 'kredit' => 0],
            ['akun_kode' => $this->utang->kode, 'debit' => 0, 'kredit' => 90000],
        ])->assertStatus(400);

        $this->assertStringContainsString('tidak balance', (string) $resp->json('message'));
        $this->assertDatabaseCount('jurnal_akuntansi', 0);
    }

    public function test_api_menerima_jurnal_balance_dengan_akun_aktif(): void
    {
        $resp = $this->postJurnal([
            ['akun_kode' => $this->kas->kode, 'debit' => 100000, 'kredit' => 0],
            ['akun_kode' => $this->pendapatan->kode, 'debit' => 0, 'kredit' => 100000],
        ])->assertStatus(201);

        $this->assertNotEmpty((string) $resp->json('data.no_jurnal'));
        $this->assertDatabaseCount('jurnal_akuntansi', 2);
        $this->assertDatabaseHas('jurnal_akuntansi', [
            'cabang_id' => $this->cabang->id,
            'akun_coa_id' => $this->kas->id,
            'debit' => 100000,
            'sumber' => 'manual',
            'user_id' => $this->user->id,
        ]);
    }

    /** [P2-1] JurnalService::post() juga menolak akun nonaktif (semua sumber). */
    public function test_jurnal_service_menolak_akun_nonaktif(): void
    {
        $nonaktif = $this->buatAkun('510-99', 'Beban Nonaktif', 'beban', 'beban_operasional', 'debit', aktif: false);

        try {
            app(JurnalService::class)->post(
                'JRL-B10E-NONAKTIF',
                now(),
                'pos',
                [
                    ['akun_kode' => $nonaktif->kode, 'debit' => 100000, 'kredit' => 0],
                    ['akun_kode' => $this->utang->kode, 'debit' => 0, 'kredit' => 100000],
                ],
                'Uji akun nonaktif',
                $this->cabang->id
            );
            $this->fail('JurnalService seharusnya menolak akun nonaktif');
        } catch (\Exception $e) {
            $this->assertStringContainsString('510-99', $e->getMessage());
            $this->assertStringContainsString('nonaktif', $e->getMessage());
        }

        $this->assertDatabaseCount('jurnal_akuntansi', 0);
    }

    /**
     * Alur otomatis (servis/POS) tetap boleh entri kontra-sisi: Persediaan
     * (aset, saldo normal debit) di-kredit saat penjualan. Aturan sisi TIDAK
     * dipaksakan di JurnalService karena akan mematikan alur ini.
     */
    public function test_jurnal_service_tetap_menerima_entri_kontra_sisi(): void
    {
        $persediaan = $this->buatAkun('130-01', 'Persediaan', 'aset', 'persediaan', 'debit');
        $hpp = $this->buatAkun('510-02', 'Harga Pokok Penjualan', 'beban', 'hpp', 'debit');

        app(JurnalService::class)->post(
            'JRL-B10E-KONTRASISI',
            now(),
            'pos',
            [
                ['akun_kode' => $hpp->kode, 'debit' => 100000, 'kredit' => 0],
                ['akun_kode' => $persediaan->kode, 'debit' => 0, 'kredit' => 100000],
            ],
            'HPP penjualan (persediaan di sisi kredit)',
            $this->cabang->id
        );

        $this->assertDatabaseCount('jurnal_akuntansi', 2);
        $this->assertDatabaseHas('jurnal_akuntansi', [
            'akun_coa_id' => $persediaan->id,
            'kredit' => 100000,
        ]);
    }
}
