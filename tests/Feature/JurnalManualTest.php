<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Akunting\Livewire\AkuntingDashboard;
use App\Modules\Akunting\Models\AkunCOA;
use App\Modules\Akunting\Models\JurnalAkuntansi;
use App\Modules\Rbac\Models\Cabang;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * B-11 — dropdown COA dan verifikasi jurnal manual.
 */
class JurnalManualTest extends TestCase
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

        $this->cabang = Cabang::create([
            'kode' => 'CBG-B11',
            'nama' => 'Cabang Jurnal Manual',
            'is_active' => true,
        ]);

        $this->user = User::create([
            'name' => 'Akuntan B11',
            'email' => 'akuntan-b11@test.com',
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);
        $this->user->cabangs()->attach($this->cabang->id);
        session(['cabang_id' => $this->cabang->id]);
        $this->actingAs($this->user, 'web');

        // [B-10a / P0-2] `simpanJurnalManual` kini diguard `akunting.create`
        // (permission yang sudah ada di RolesAndPermissionsSeeder — role finance).
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->user->assignRole('finance');

        $this->kas = AkunCOA::create([
            'kode' => '110-01',
            'nama' => 'Kas',
            'tipe' => 'aset',
            'kelompok' => 'kas',
            'saldo_normal' => 'debit',
            'is_active' => true,
        ]);
        $this->utang = AkunCOA::create([
            'kode' => '210-01',
            'nama' => 'Utang Usaha',
            'tipe' => 'kewajiban',
            'kelompok' => 'utang_usaha',
            'saldo_normal' => 'kredit',
            'is_active' => true,
        ]);
        $this->pendapatan = AkunCOA::create([
            'kode' => '410-01',
            'nama' => 'Pendapatan Penjualan',
            'tipe' => 'pendapatan',
            'kelompok' => 'pendapatan_penjualan',
            'saldo_normal' => 'kredit',
            'is_active' => true,
        ]);
    }

    public function test_jurnal_tidak_balance_tidak_bisa_disimpan(): void
    {
        Livewire::test(AkuntingDashboard::class)
            ->call('openJurnalManualModal')
            ->set('manualDeskripsi', 'Jurnal uji tidak balance')
            ->set('manualLines', [
                ['akun_kode' => $this->kas->kode, 'sisi' => 'debit', 'jumlah' => 100000],
                ['akun_kode' => $this->utang->kode, 'sisi' => 'kredit', 'jumlah' => 90000],
            ])
            ->call('tinjauJurnalManual')
            ->assertSet('showJurnalConfirmation', false)
            ->call('simpanJurnalManual')
            ->assertSet('showJurnalConfirmation', false)
            ->assertDispatched('alert');

        $this->assertDatabaseCount('jurnal_akuntansi', 0);
    }

    public function test_jurnal_balance_dapat_disimpan_lewat_jurnal_service(): void
    {
        Livewire::test(AkuntingDashboard::class)
            ->call('openJurnalManualModal')
            ->set('manualDeskripsi', 'Jurnal uji balance')
            // String berformat dari Alpine ADR 0011 harus diterima backend sebagai angka bersih.
            ->set('manualLines', [
                ['akun_kode' => $this->kas->kode, 'sisi' => 'debit', 'jumlah' => '100.000'],
                ['akun_kode' => $this->utang->kode, 'sisi' => 'kredit', 'jumlah' => '100.000'],
            ])
            ->call('tinjauJurnalManual')
            ->assertSet('showJurnalConfirmation', true)
            ->assertSee('Siap Diposting')
            ->call('simpanJurnalManual')
            ->assertSet('showJurnalManual', false)
            ->assertSet('showJurnalConfirmation', false);

        $this->assertDatabaseCount('jurnal_akuntansi', 2);
        $this->assertDatabaseHas('jurnal_akuntansi', [
            'cabang_id' => $this->cabang->id,
            'akun_coa_id' => $this->kas->id,
            'sumber' => 'manual',
            'deskripsi' => 'Jurnal uji balance',
            'debit' => 100000,
            'kredit' => 0,
            'user_id' => $this->user->id,
        ]);
        $this->assertDatabaseHas('jurnal_akuntansi', [
            'cabang_id' => $this->cabang->id,
            'akun_coa_id' => $this->utang->id,
            'sumber' => 'manual',
            'deskripsi' => 'Jurnal uji balance',
            'debit' => 0,
            'kredit' => 100000,
            'user_id' => $this->user->id,
        ]);

        $rows = JurnalAkuntansi::where('sumber', 'manual')->get();
        $this->assertCount(1, $rows->pluck('no_jurnal')->unique());
        $this->assertSame(100000.0, (float) $rows->sum('debit'));
        $this->assertSame(100000.0, (float) $rows->sum('kredit'));
    }

    public function test_akun_dengan_saldo_normal_kredit_tidak_boleh_didebit(): void
    {
        Livewire::test(AkuntingDashboard::class)
            ->call('openJurnalManualModal')
            ->set('manualDeskripsi', 'Percobaan akun pendapatan di debit')
            ->set('manualLines', [
                ['akun_kode' => $this->pendapatan->kode, 'sisi' => 'debit', 'jumlah' => 100000],
                ['akun_kode' => $this->utang->kode, 'sisi' => 'kredit', 'jumlah' => 100000],
            ])
            ->call('tinjauJurnalManual')
            ->assertSet('showJurnalConfirmation', false)
            ->assertSee('tidak dapat dipilih pada sisi debit');

        $this->assertDatabaseCount('jurnal_akuntansi', 0);
    }

    public function test_dropdown_akun_manual_hanya_berisi_akun_aktif(): void
    {
        $akunNonaktif = AkunCOA::create([
            'kode' => '110-99',
            'nama' => 'Kas Legacy Nonaktif',
            'tipe' => 'aset',
            'kelompok' => 'kas',
            'saldo_normal' => 'debit',
            'is_active' => false,
        ]);

        Livewire::test(AkuntingDashboard::class)
            ->call('openJurnalManualModal')
            ->assertViewHas('akunJurnalOptions', fn (Collection $accounts): bool => $accounts->contains('id', $this->kas->id)
                && $accounts->contains('id', $this->utang->id)
                && $accounts->contains('id', $this->pendapatan->id)
                && ! $accounts->contains('id', $akunNonaktif->id))
            ->assertSee('110-01 — Kas (Aset)')
            ->assertSee('210-01 — Utang Usaha (Kewajiban)')
            ->assertDontSee('110-99')
            ->assertDontSee('Kas Legacy Nonaktif');
    }
}
