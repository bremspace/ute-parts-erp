<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Akunting\Livewire\AkuntingDashboard;
use App\Modules\Akunting\Models\AkunCOA;
use App\Modules\Akunting\Models\JurnalAkuntansi;
use App\Modules\Rbac\Models\Cabang;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class AkuntingBukuBesarTest extends TestCase
{
    use RefreshDatabase;

    private Cabang $cabang;

    private User $user;

    private AkunCOA $kas;

    private AkunCOA $modal;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->cabang = Cabang::create([
            'kode' => 'CBG-TEST-BB',
            'nama' => 'Cabang Test Buku Besar',
            'is_active' => true,
        ]);

        $this->user = User::create([
            'name' => 'Finance Tester',
            'email' => 'finance-test@uteparts.id',
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);
        $this->user->cabangs()->attach($this->cabang->id);
        $this->user->assignRole('finance');
        session(['cabang_id' => $this->cabang->id]);
        $this->actingAs($this->user, 'web');

        $this->kas = AkunCOA::create([
            'kode' => '110-01',
            'nama' => 'Kas',
            'tipe' => 'aset',
            'kelompok' => 'kas',
            'saldo_normal' => 'debit',
            'is_active' => true,
        ]);

        $this->modal = AkunCOA::create([
            'kode' => '310-01',
            'nama' => 'Modal Pemilik',
            'tipe' => 'ekuitas',
            'kelompok' => 'modal',
            'saldo_normal' => 'kredit',
            'is_active' => true,
        ]);
    }

    public function test_buku_besar_menghitung_mutasi_dan_saldo_berjalan_dengan_benar(): void
    {
        // 1. Buat mutasi tanggal lampau (45 hari lalu) sebagai saldo awal
        JurnalAkuntansi::create([
            'no_jurnal' => 'JRN-AWAL-01',
            'tanggal' => now()->subDays(45)->toDateString(),
            'cabang_id' => $this->cabang->id,
            'akun_coa_id' => $this->kas->id,
            'sumber' => 'manual',
            'deskripsi' => 'Setoran Modal Awal',
            'debit' => 500000,
            'kredit' => 0,
            'user_id' => $this->user->id,
        ]);

        // 2. Buat mutasi periode ini
        JurnalAkuntansi::create([
            'no_jurnal' => 'JRN-BERJALAN-01',
            'tanggal' => now()->toDateString(),
            'cabang_id' => $this->cabang->id,
            'akun_coa_id' => $this->kas->id,
            'sumber' => 'manual',
            'deskripsi' => 'Pemasukan Tambahan',
            'debit' => 200000,
            'kredit' => 0,
            'user_id' => $this->user->id,
        ]);

        $component = Livewire::test(AkuntingDashboard::class)
            ->call('lihatMutasiAkun', $this->kas->id)
            ->assertSet('activeTab', 'buku-besar')
            ->assertSet('selectedBukuBesarAkunId', $this->kas->id);

        $data = $component->get('bukuBesarData');
        $this->assertNotNull($data);
        $this->assertEquals(500000, $data['saldo_awal']);
        $this->assertEquals(200000, $data['total_debit']);
        $this->assertEquals(700000, $data['saldo_akhir']);
        $this->assertCount(1, $data['mutasi']);
        $this->assertEquals(700000, $data['mutasi'][0]['saldo']);
    }

    public function test_posting_jurnal_manual_otomatis_tercatat_dan_sinkron(): void
    {
        Livewire::test(AkuntingDashboard::class)
            ->call('openJurnalManualModal')
            ->set('manualDeskripsi', 'Injeksi modal kas')
            ->set('manualLines', [
                ['akun_kode' => $this->kas->kode, 'sisi' => 'debit', 'jumlah' => 1000000],
                ['akun_kode' => $this->modal->kode, 'sisi' => 'kredit', 'jumlah' => 1000000],
            ])
            ->call('simpanJurnalManual')
            ->assertSet('showJurnalManual', false)
            ->assertSet('activeTab', 'jurnal')
            ->assertDispatched('alert');

        $this->assertDatabaseHas('jurnal_akuntansi', [
            'cabang_id' => $this->cabang->id,
            'akun_coa_id' => $this->kas->id,
            'debit' => 1000000,
            'sumber' => 'manual',
        ]);

        $this->assertDatabaseHas('jurnal_akuntansi', [
            'cabang_id' => $this->cabang->id,
            'akun_coa_id' => $this->modal->id,
            'kredit' => 1000000,
            'sumber' => 'manual',
        ]);
    }
}
