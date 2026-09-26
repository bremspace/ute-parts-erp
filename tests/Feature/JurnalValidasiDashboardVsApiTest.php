<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Akunting\Livewire\AkuntingDashboard;
use App\Modules\Akunting\Models\AkunCOA;
use App\Modules\Akunting\Services\JurnalService;
use App\Modules\Akunting\Services\ValidasiBarisJurnal;
use App\Modules\Rbac\Models\Cabang;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * [B-10g] Validasi jurnal manual punya DUA sumber aturan (drift):
 * form Livewire `AkuntingDashboard` menyalin aturan yang sama dengan
 * `JurnalService::post()` / API [API: ACC-04]. Setelah B-10g, `ValidasiBarisJurnal`
 * adalah SATU-SATUNYA sumber aturan, sehingga PESAN yang dilihat user di
 * dashboard dan PESAN yang dikembalikan API wajib IDENTIK untuk kasus sama.
 *
 * Kasus yang dijaga: akun nonaktif, akun tidak ada, sisi tidak sesuai saldo
 * normal, nominal nol, dan jurnal belum balance.
 */
class JurnalValidasiDashboardVsApiTest extends TestCase
{
    use RefreshDatabase;

    private Cabang $cabang;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->cabang = Cabang::create([
            'kode' => 'CBG-B10G',
            'nama' => 'Cabang Validasi Dashboard',
            'is_active' => true,
        ]);

        $this->user = User::create([
            'name' => 'Akuntan B10g',
            'email' => 'akuntan-b10g@test.com',
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);
        $this->user->cabangs()->attach($this->cabang->id);
        $this->user->assignRole('finance'); // permission akunting.create
        session(['cabang_id' => $this->cabang->id]);
        $this->actingAs($this->user, 'web');

        $this->buatAkun('110-01', 'Kas', 'aset', 'kas', 'debit');
        $this->buatAkun('110-99', 'Kas Legacy Nonaktif', 'aset', 'kas', 'debit', aktif: false);
        $this->buatAkun('210-01', 'Utang Usaha', 'kewajiban', 'utang_usaha', 'kredit');
        $this->buatAkun('410-01', 'Pendapatan Penjualan', 'pendapatan', 'pendapatan_penjualan', 'kredit');
    }

    private function buatAkun(string $kode, string $nama, string $tipe, string $kelompok, string $saldo, bool $aktif = true): void
    {
        AkunCOA::create([
            'kode' => $kode,
            'nama' => $nama,
            'tipe' => $tipe,
            'kelompok' => $kelompok,
            'saldo_normal' => $saldo,
            'is_active' => $aktif,
        ]);
    }

    /**
     * Baris form dashboard (sisi) → baris API (debit/kredit).
     *
     * @param  array<int,array{akun_kode:string,sisi:string,jumlah:mixed}>  $lines
     * @return array<int,array{akun_kode:string,debit:float,kredit:float}>
     */
    private function keFormatApi(array $lines): array
    {
        return array_map(function (array $line): array {
            $jumlah = (float) $line['jumlah'];

            return [
                'akun_kode' => $line['akun_kode'],
                'debit' => $line['sisi'] === 'debit' ? $jumlah : 0.0,
                'kredit' => $line['sisi'] === 'kredit' ? $jumlah : 0.0,
            ];
        }, $lines);
    }

    /**
     * Pesan yang benar-benar tampil di form dashboard (indikator real-time).
     *
     * @param  array<int,array{akun_kode:string,sisi:string,jumlah:mixed}>  $lines
     * @return array<string,mixed> summary `manualValidation`
     */
    private function validasiDashboard(array $lines): array
    {
        return Livewire::test(AkuntingDashboard::class)
            ->call('openJurnalManualModal')
            ->set('manualDeskripsi', 'Jurnal uji parity pesan')
            ->set('manualLines', $lines)
            ->call('tinjauJurnalManual')
            ->assertSet('showJurnalConfirmation', false)
            ->viewData('manualValidation');
    }

    /**
     * @param  array<int,array<string,mixed>>  $lines
     */
    private function postApi(array $lines)
    {
        return $this->postJson('/api/akunting/jurnal-manual', [
            'tanggal' => now()->toDateString(),
            'deskripsi' => 'Jurnal uji parity pesan',
            'lines' => $lines,
        ]);
    }

    public function test_pesan_akun_nonaktif_identik_dashboard_dan_api(): void
    {
        $lines = [
            ['akun_kode' => '110-99', 'sisi' => 'debit', 'jumlah' => 100000],
            ['akun_kode' => '210-01', 'sisi' => 'kredit', 'jumlah' => 100000],
        ];

        $dashboard = $this->validasiDashboard($lines);
        $expected = 'Akun 110-99 — Kas Legacy Nonaktif sedang nonaktif dan tidak dapat dipakai untuk jurnal baru.';

        $this->assertSame([$expected], $dashboard['messages']);
        $this->assertFalse($dashboard['can_submit']);

        $api = $this->postApi($this->keFormatApi($lines))->assertStatus(422);
        $this->assertSame($dashboard['messages'], $api->json('data.errors'), 'Pesan API harus identik dgn pesan dashboard');
        $this->assertStringContainsString($expected, (string) $api->json('message'));
        $this->assertDatabaseCount('jurnal_akuntansi', 0);
    }

    public function test_pesan_akun_tidak_ditemukan_identik_dashboard_dan_api(): void
    {
        $lines = [
            ['akun_kode' => '999-99', 'sisi' => 'debit', 'jumlah' => 100000],
            ['akun_kode' => '210-01', 'sisi' => 'kredit', 'jumlah' => 100000],
        ];

        $dashboard = $this->validasiDashboard($lines);
        $expected = 'Akun COA tidak ditemukan: 999-99';

        $this->assertSame([$expected], $dashboard['messages']);

        $api = $this->postApi($this->keFormatApi($lines))->assertStatus(422);
        $this->assertSame($dashboard['messages'], $api->json('data.errors'), 'Pesan API harus identik dgn pesan dashboard');
        $this->assertStringContainsString($expected, (string) $api->json('message'));
        $this->assertDatabaseCount('jurnal_akuntansi', 0);
    }

    public function test_pesan_sisi_tidak_sesuai_identik_dashboard_dan_api(): void
    {
        $lines = [
            ['akun_kode' => '410-01', 'sisi' => 'debit', 'jumlah' => 100000],
            ['akun_kode' => '210-01', 'sisi' => 'kredit', 'jumlah' => 100000],
        ];

        $dashboard = $this->validasiDashboard($lines);
        $expected = 'Akun 410-01 — Pendapatan Penjualan tidak dapat dipilih pada sisi debit karena saldo normalnya kredit.';

        $this->assertSame([$expected], $dashboard['messages']);
        $this->assertFalse($dashboard['can_submit']);

        $api = $this->postApi($this->keFormatApi($lines))->assertStatus(422);
        $this->assertSame($dashboard['messages'], $api->json('data.errors'), 'Pesan API harus identik dgn pesan dashboard');
        $this->assertStringContainsString($expected, (string) $api->json('message'));
        $this->assertDatabaseCount('jurnal_akuntansi', 0);
    }

    public function test_pesan_nominal_nol_identik_dashboard_dan_api(): void
    {
        $lines = [
            ['akun_kode' => '110-01', 'sisi' => 'debit', 'jumlah' => 0],
            ['akun_kode' => '210-01', 'sisi' => 'kredit', 'jumlah' => 0],
        ];

        $dashboard = $this->validasiDashboard($lines);

        $this->assertSame([
            'Nominal jurnal pada akun 110-01 harus lebih besar dari nol.',
            'Nominal jurnal pada akun 210-01 harus lebih besar dari nol.',
        ], $dashboard['messages']);
        $this->assertFalse($dashboard['can_submit']);
        // Pesan lama yang hanya hidup di form UI tidak boleh muncul lagi (drift).
        $this->assertNotContains('Nominal harus lebih besar dari nol.', $dashboard['messages']);

        $api = $this->postApi($this->keFormatApi($lines))->assertStatus(422);
        $this->assertSame($dashboard['messages'], $api->json('data.errors'), 'Pesan API harus identik dgn pesan dashboard');
        $this->assertDatabaseCount('jurnal_akuntansi', 0);
    }

    /**
     * Kasus "belum balance" tidak di-422-kan API (dicek JurnalService saat post),
     * tapi TEKS pesannya harus sama persis dengan yang tampil di dashboard.
     */
    public function test_pesan_belum_balance_identik_dashboard_api_dan_jurnal_service(): void
    {
        $lines = [
            ['akun_kode' => '110-01', 'sisi' => 'debit', 'jumlah' => 100000],
            ['akun_kode' => '210-01', 'sisi' => 'kredit', 'jumlah' => 90000],
        ];

        $dashboard = $this->validasiDashboard($lines);
        $expected = 'Jurnal tidak balance: debit Rp 100,000.00 ≠ kredit Rp 90,000.00';

        $this->assertSame([$expected], $dashboard['messages'], 'Pesan dashboard wajib dari ValidasiBarisJurnal');
        $this->assertFalse($dashboard['can_submit']);
        $this->assertFalse($dashboard['balanced']);

        $api = $this->postApi($this->keFormatApi($lines))->assertStatus(400);
        $this->assertSame($expected, (string) $api->json('message'), 'Pesan API harus identik dgn pesan dashboard');
        $this->assertDatabaseCount('jurnal_akuntansi', 0);

        // Anti-drift: pesan balance JurnalService (literal di sana) harus sama dgn
        // sumber tunggal ValidasiBarisJurnal.
        try {
            app(JurnalService::class)->post(
                'JRL-B10G-BELUM-BALANCE',
                now(),
                'manual',
                $this->keFormatApi($lines),
                'Jurnal uji parity balance',
                $this->cabang->id,
                $this->user->id
            );
            $this->fail('JurnalService seharusnya menolak jurnal tidak balance');
        } catch (\Exception $e) {
            $this->assertSame($expected, $e->getMessage());
            $this->assertSame($expected, ValidasiBarisJurnal::pesanBelumBalance(100000, 90000));
        }
    }

    /** Jurnal sah tetap bisa diposting dari dashboard (guard validasi tidak berlebihan). */
    public function test_jurnal_valid_tetap_ditinjau_dan_diposting(): void
    {
        Livewire::test(AkuntingDashboard::class)
            ->call('openJurnalManualModal')
            ->set('manualDeskripsi', 'Jurnal uji sah')
            ->set('manualLines', [
                ['akun_kode' => '110-01', 'sisi' => 'debit', 'jumlah' => 100000],
                ['akun_kode' => '410-01', 'sisi' => 'kredit', 'jumlah' => 100000],
            ])
            ->call('tinjauJurnalManual')
            ->assertSet('showJurnalConfirmation', true)
            ->assertSee('Siap Diposting')
            ->call('simpanJurnalManual')
            ->assertSet('showJurnalManual', false);

        $this->assertDatabaseCount('jurnal_akuntansi', 2);
    }
}
