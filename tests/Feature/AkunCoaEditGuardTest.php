<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Akunting\Livewire\AkuntingDashboard;
use App\Modules\Akunting\Models\AkunCOA;
use App\Modules\Akunting\Services\JurnalService;
use App\Modules\Rbac\Models\Cabang;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * F-09 (HIGH) — Edit COA tidak boleh mengubah kode/tipe/saldo_normal akun
 * yang sudah memiliki baris jurnal (reklasifikasi retroaktif laporan historis).
 *
 * Guard hidup di model AkunCOA (semua jalur Eloquent terlindungi), dengan
 * pesan jelas di API updateCoa (422) dan UI simpanCoa (alert error).
 * Nama/kelompok/status aktif tetap boleh diubah; akun tanpa jurnal bebas.
 */
class AkunCoaEditGuardTest extends TestCase
{
    use RefreshDatabase;

    private Cabang $cabang;

    private User $superAdmin;

    private JurnalService $jurnalService;

    private AkunCOA $akunBerjurnal;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        foreach ([
            ['kode' => '110-01', 'nama' => 'Kas', 'tipe' => 'aset', 'kelompok' => 'kas', 'saldo_normal' => 'debit'],
            ['kode' => '410-01', 'nama' => 'Pendapatan Penjualan', 'tipe' => 'pendapatan', 'kelompok' => 'pendapatan_penjualan', 'saldo_normal' => 'kredit'],
            ['kode' => '110-90', 'nama' => 'Bank Uji', 'tipe' => 'aset', 'kelompok' => 'kas', 'saldo_normal' => 'debit'],
        ] as $a) {
            AkunCOA::create(array_merge($a, ['is_active' => true]));
        }

        $this->cabang = Cabang::create([
            'kode' => 'CB-COAEDIT',
            'nama' => 'Cabang Test Edit COA',
            'is_active' => true,
        ]);
        $this->superAdmin = User::factory()->create([
            'name' => 'Admin Edit COA',
            'email' => 'admin-coaedit@test.com',
        ]);
        $this->superAdmin->assignRole('super-admin');
        $this->superAdmin->cabangs()->attach($this->cabang->id);
        session(['cabang_id' => $this->cabang->id]);
        $this->actingAs($this->superAdmin, 'web');

        $this->jurnalService = app(JurnalService::class);
        $this->akunBerjurnal = AkunCOA::where('kode', '110-01')->firstOrFail();

        // Posting jurnal sehingga 110-01 (Kas) memiliki riwayat jurnal.
        $this->jurnalService->post(
            noJurnal: 'JRL-COAEDIT-01',
            tanggal: now(),
            sumber: 'manual',
            lines: [
                ['akun_kode' => '110-01', 'debit' => 250000, 'kredit' => 0],
                ['akun_kode' => '410-01', 'debit' => 0, 'kredit' => 250000],
            ],
            cabangId: $this->cabang->id,
            userId: $this->superAdmin->id
        );
    }

    // =====================================================================
    // API updateCoa — klasifikasi terkunci bila akun ber-jurnal
    // =====================================================================

    public function test_api_menolak_ubah_tipe_akun_berjurnal(): void
    {
        $response = $this->putJson("/api/akunting/coa/{$this->akunBerjurnal->id}", [
            'tipe' => 'beban',
        ]);

        $response->assertStatus(422)->assertJsonPath('success', false);
        $this->assertStringContainsString('jurnal', strtolower($response->json('message')));
        $this->assertSame('aset', $this->akunBerjurnal->fresh()->tipe);
    }

    public function test_api_menolak_ubah_kode_akun_berjurnal(): void
    {
        $response = $this->putJson("/api/akunting/coa/{$this->akunBerjurnal->id}", [
            'kode' => '510-99',
        ]);

        $response->assertStatus(422)->assertJsonPath('success', false);
        $this->assertSame('110-01', $this->akunBerjurnal->fresh()->kode);
    }

    public function test_api_menolak_ubah_saldo_normal_akun_berjurnal(): void
    {
        $response = $this->putJson("/api/akunting/coa/{$this->akunBerjurnal->id}", [
            'saldo_normal' => 'kredit',
        ]);

        $response->assertStatus(422)->assertJsonPath('success', false);
        $this->assertSame('debit', $this->akunBerjurnal->fresh()->saldo_normal);
    }

    public function test_api_tetap_boleh_ubah_nama_dan_nonaktifkan_akun_berjurnal(): void
    {
        // UI/API selalu mengirim payload lengkap; nilai klasifikasi yang TIDAK
        // berubah tidak boleh memicu guard.
        $response = $this->putJson("/api/akunting/coa/{$this->akunBerjurnal->id}", [
            'kode' => '110-01',
            'nama' => 'Kas Utama (rename)',
            'tipe' => 'aset',
            'kelompok' => 'kas',
            'saldo_normal' => 'debit',
            'is_active' => false,
        ]);

        $response->assertStatus(200)->assertJsonPath('success', true);
        $fresh = $this->akunBerjurnal->fresh();
        $this->assertSame('Kas Utama (rename)', $fresh->nama);
        $this->assertFalse($fresh->is_active);
        $this->assertSame('aset', $fresh->tipe);
    }

    public function test_api_bebas_ubah_klasifikasi_akun_tanpa_jurnal(): void
    {
        $akun = AkunCOA::where('kode', '110-90')->firstOrFail();

        $response = $this->putJson("/api/akunting/coa/{$akun->id}", [
            'tipe' => 'beban',
            'saldo_normal' => 'debit',
        ]);

        $response->assertStatus(200)->assertJsonPath('success', true);
        $this->assertSame('beban', $akun->fresh()->tipe);
    }

    // =====================================================================
    // UI Livewire simpanCoa — pesan jelas, akun tidak berubah
    // =====================================================================

    public function test_livewire_menolak_ubah_tipe_akun_berjurnal_dengan_alert_error(): void
    {
        Livewire::test(AkuntingDashboard::class)
            ->set('showCoaModal', true)
            ->set('coaForm', [
                'id' => $this->akunBerjurnal->id,
                'kode' => '110-01',
                'nama' => 'Kas',
                'tipe' => 'beban',
                'kelompok' => 'kas',
                'saldo_normal' => 'debit',
            ])
            ->call('simpanCoa')
            ->assertDispatched('alert', fn ($event, $params) => ($params[0]['type'] ?? $params['type'] ?? '') === 'error')
            ->assertSet('showCoaModal', true);

        $this->assertSame('aset', $this->akunBerjurnal->fresh()->tipe);
    }

    public function test_livewire_tetap_boleh_ubah_nama_akun_berjurnal(): void
    {
        Livewire::test(AkuntingDashboard::class)
            ->set('coaForm', [
                'id' => $this->akunBerjurnal->id,
                'kode' => '110-01',
                'nama' => 'Kas Besar (rename UI)',
                'tipe' => 'aset',
                'kelompok' => 'kas',
                'saldo_normal' => 'debit',
            ])
            ->call('simpanCoa')
            ->assertDispatched('alert', fn ($event, $params) => ($params[0]['type'] ?? $params['type'] ?? '') === 'success')
            ->assertSet('showCoaModal', false);

        $this->assertSame('Kas Besar (rename UI)', $this->akunBerjurnal->fresh()->nama);
    }

    // =====================================================================
    // Guard model — melindungi semua jalur Eloquent, bukan hanya UI/API
    // =====================================================================

    public function test_model_guard_menolak_ubah_klasifikasi_akun_berjurnal(): void
    {
        try {
            $this->akunBerjurnal->update(['tipe' => 'beban']);
            $this->fail('Update tipe akun ber-jurnal seharusnya ditolak guard model.');
        } catch (\DomainException $e) {
            $this->assertStringContainsString('110-01', $e->getMessage());
            $this->assertStringContainsString('jurnal', $e->getMessage());
        }

        $this->assertSame('aset', $this->akunBerjurnal->fresh()->tipe);
    }

    public function test_model_guard_tidak_memicu_bila_klasifikasi_tidak_berubah(): void
    {
        $this->akunBerjurnal->update([
            'kode' => '110-01',
            'nama' => 'Kas (tetap)',
            'tipe' => 'aset',
            'saldo_normal' => 'debit',
        ]);

        $this->assertSame('Kas (tetap)', $this->akunBerjurnal->fresh()->nama);
    }
}
