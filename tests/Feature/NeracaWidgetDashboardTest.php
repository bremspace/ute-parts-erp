<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Akunting\Livewire\AkuntingDashboard;
use App\Modules\Akunting\Models\AkunCOA;
use App\Modules\Akunting\Models\JurnalAkuntansi;
use App\Modules\Akunting\Services\ExportLaporanService;
use App\Modules\Rbac\Models\Cabang;
use Database\Seeders\AkunCoaSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * [B-10g] Widget Neraca di dashboard WAJIB sama dengan laporan Neraca
 * (canonical `ExportLaporanService::neracaSaldo()`, dipakai export Excel dan
 * API [API: ACC-06]).
 *
 * Versi lama menjumlah `total_ekuitas` sendiri TANPA laba periode berjalan,
 * sehingga indikator "balance" di kartu dashboard bisa salah (laba positif →
 * dashboard menulis "tidak balance" padahal jurnal double-entry sudah benar).
 * Test ini mengunci angka, status SEIMBANG/TIDAK SEIMBANG, dan selisih.
 *
 * Data sama seperti NeracaKumulatifTest (jurnal bulan lalu + bulan ini):
 *   Aset 7.900.000 = Kewajiban 2.000.000 + Ekuitas 5.000.000 + Laba 900.000
 */
class NeracaWidgetDashboardTest extends TestCase
{
    use RefreshDatabase;

    private Cabang $cabang;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(AkunCoaSeeder::class);

        $this->cabang = Cabang::create([
            'kode' => 'CBG-NWD',
            'nama' => 'Cabang Neraca Widget',
            'is_active' => true,
        ]);

        $this->user = User::create([
            'name' => 'Akuntan Neraca Widget',
            'email' => 'akuntan-neraca-widget@test.com',
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);
        $this->user->assignRole('finance');
        $this->user->cabangs()->attach($this->cabang->id);
        session(['cabang_id' => $this->cabang->id]);
        $this->actingAs($this->user, 'web');

        $this->jurnalKumulatifNormal();
    }

    private function jurnalKumulatifNormal(): void
    {
        $lalu = now()->subMonth()->toDateString();
        $ini = now()->toDateString();

        $this->jurnal('JRL-NWD-0001', $lalu, [
            ['akun_kode' => '110-01', 'debit' => 5000000, 'kredit' => 0],
            ['akun_kode' => '310-01', 'debit' => 0, 'kredit' => 5000000],
        ], 'Setoran modal pemilik');

        $this->jurnal('JRL-NWD-0002', $lalu, [
            ['akun_kode' => '130-01', 'debit' => 2000000, 'kredit' => 0],
            ['akun_kode' => '210-01', 'debit' => 0, 'kredit' => 2000000],
        ], 'Pembelian persediaan');

        $this->jurnal('JRL-NWD-0003', $lalu, [
            ['akun_kode' => '110-01', 'debit' => 3000000, 'kredit' => 0],
            ['akun_kode' => '410-01', 'debit' => 0, 'kredit' => 3000000],
        ], 'Penjualan bulan lalu');

        $this->jurnal('JRL-NWD-0004', $lalu, [
            ['akun_kode' => '510-02', 'debit' => 2000000, 'kredit' => 0],
            ['akun_kode' => '130-01', 'debit' => 0, 'kredit' => 2000000],
        ], 'HPP penjualan bulan lalu');

        $this->jurnal('JRL-NWD-0005', $ini, [
            ['akun_kode' => '520-03', 'debit' => 100000, 'kredit' => 0],
            ['akun_kode' => '110-01', 'debit' => 0, 'kredit' => 100000],
        ], 'Listrik & air bulan ini');
    }

    /**
     * @param  array<int,array{akun_kode:string,debit:float,kredit:float}>  $lines
     */
    private function jurnal(string $noJurnal, string $tanggal, array $lines, string $deskripsi, ?Cabang $cabang = null): void
    {
        foreach ($lines as $line) {
            JurnalAkuntansi::create([
                'no_jurnal' => $noJurnal,
                'tanggal' => $tanggal,
                'cabang_id' => ($cabang ?? $this->cabang)->id,
                'akun_coa_id' => AkunCOA::where('kode', $line['akun_kode'])->firstOrFail()->id,
                'sumber' => 'manual',
                'deskripsi' => $deskripsi,
                'debit' => $line['debit'],
                'kredit' => $line['kredit'],
                'user_id' => $this->user->id,
            ]);
        }
    }

    private function laporan(): array
    {
        return app(ExportLaporanService::class)->neracaSaldo($this->cabang->id, now()->toDateString());
    }

    /**
     * Widget neraca yang dirender dashboard.
     *
     * @return array<string,mixed>
     */
    private function widget(): array
    {
        return Livewire::test(AkuntingDashboard::class)->viewData('neraca');
    }

    public function test_angka_widget_neraca_sama_dengan_laporan_neraca(): void
    {
        $laporan = $this->laporan();
        $widget = $this->widget();

        $this->assertSame('saldo_kumulatif', $widget['basis']);
        $this->assertSame($laporan['sampai_tanggal'], $widget['sampai_tanggal']);

        foreach (['total_aset', 'total_kewajiban', 'total_ekuitas', 'laba_periode_berjalan', 'total_ekuitas_bersama_laba', 'selisih'] as $key) {
            $this->assertEqualsWithDelta(
                $laporan[$key],
                (float) $widget[$key],
                0.01,
                "Widget neraca tidak boleh berbeda dari laporan pada key {$key}"
            );
        }

        $this->assertSame($laporan['balance'], $widget['balance']);
        $this->assertSame($laporan['selisih'], $widget['selisih']);

        // Nilai absolut (bukan cuma "sama dgn laporan"): laba periode berjalan
        // wajib ikut terhitung di sisi ekuitas.
        $this->assertSame(7900000.0, (float) $widget['total_aset']);
        $this->assertSame(2000000.0, (float) $widget['total_kewajiban']);
        $this->assertSame(5000000.0, (float) $widget['total_ekuitas']);
        $this->assertSame(900000.0, (float) $widget['laba_periode_berjalan']);
        $this->assertSame(5900000.0, (float) $widget['total_ekuitas_bersama_laba']);
        $this->assertTrue($widget['balance'], 'Laba positif tidak boleh membuat widget neraca tidak balance');
        $this->assertSame(0.0, (float) $widget['selisih']);

        // Rumus LAMA (ekuitas tanpa laba) memang salah untuk data ini.
        $this->assertNotEqualsWithDelta(
            (float) $widget['total_aset'],
            (float) $widget['total_kewajiban'] + (float) $widget['total_ekuitas'],
            0.01,
            'Data uji harus benar-benar membuktikan rumus lama salah'
        );
    }

    public function test_kartu_neraca_menampilkan_status_seimbang_selisih_dan_label_kumulatif(): void
    {
        Livewire::test(AkuntingDashboard::class)
            ->assertSee('Saldo kumulatif s/d '.now()->toDateString())
            ->assertSee('Laba Periode Berjalan')
            ->assertSee('SEIMBANG — Aset = Kewajiban + Ekuitas + Laba Periode Berjalan')
            ->assertDontSee('TIDAK SEIMBANG')
            ->assertSee('Selisih:')
            // Angka ribuan titik (ADR 0011) + tabular-nums di view.
            ->assertSee('Rp 7.900.000')
            ->assertSee('Rp 5.900.000')
            ->assertSeeHtml('tabular-nums');
    }

    public function test_status_tidak_seimbang_widget_ama_dengan_laporan(): void
    {
        // Jurnal rusak (hanya satu sisi) → selisih neraca tidak nol.
        $this->jurnal('JRL-NWD-0006', now()->toDateString(), [
            ['akun_kode' => '110-01', 'debit' => 500000, 'kredit' => 0],
        ], 'Jurnal rusak (one-sided)');

        $laporan = $this->laporan();
        $widget = $this->widget();

        $this->assertFalse($widget['balance'], 'Widget harus menandai neraca tidak seimbang');
        $this->assertSame($laporan['balance'], $widget['balance']);
        $this->assertSame($laporan['selisih'], $widget['selisih']);
        $this->assertEqualsWithDelta(500000.0, (float) $widget['selisih'], 0.01);

        Livewire::test(AkuntingDashboard::class)
            ->assertSee('TIDAK SEIMBANG — Aset ≠ Kewajiban + Ekuitas + Laba Periode Berjalan')
            ->assertDontSee('Aset = Kewajiban')
            ->assertSee('Selisih:')
            ->assertSee('Rp 500.000');
    }

    public function test_widget_neraca_hanya_menghitung_jurnal_cabang_aktif(): void
    {
        $cabangLain = Cabang::create(['kode' => 'CBG-NWD-2', 'nama' => 'Cabang Lain', 'is_active' => true]);

        $this->jurnal('JRL-NWD-LAIN', now()->toDateString(), [
            ['akun_kode' => '110-01', 'debit' => 9000000, 'kredit' => 0],
            ['akun_kode' => '210-01', 'debit' => 0, 'kredit' => 9000000],
        ], 'Jurnal cabang lain', $cabangLain);

        $widget = $this->widget();

        $this->assertSame(7900000.0, (float) $widget['total_aset'], 'Jurnal cabang lain tidak boleh masuk widget');
        $this->assertSame(2000000.0, (float) $widget['total_kewajiban']);
        $this->assertEqualsWithDelta(0.0, (float) $widget['selisih'], 0.01);
        $this->assertTrue($widget['balance']);
    }
}
