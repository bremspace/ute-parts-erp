<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Akunting\Models\AkunCOA;
use App\Modules\Akunting\Models\JurnalAkuntansi;
use App\Modules\Akunting\Services\ExportLaporanService;
use App\Modules\Rbac\Models\Cabang;
use Database\Seeders\AkunCoaSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * [B-10e / P1-7] Laporan Neraca wajib:
 *  (a) memakai SALDO KUMULATIF s/d tanggal_sampai, bukan perubahan periode —
 *      jurnal dari bulan sebelumnya wajib ikut terhitung (versi lama export
 *      mulai dari awal bulan berjalan sehingga neraca tidak balance);
 *  (b) tetap balance: Total Aset = Total Kewajiban + Ekuitas + Laba Periode
 *      Berjalan, walau jurnal utama terjadi di bulan sebelumnya;
 *  (c) API [API: ACC-06] dan export Excel memakai ANGKA YANG SAMA (sumber
 *      tunggal `ExportLaporanService::neracaSaldo()`).
 *
 * Catatan: akun ekuitas penyeimbang sudah ada di COA standar (AkunCoaSeeder):
 * 310-01 Modal Pemilik + 310-02 Laba Ditahan — tidak perlu akun hardcode baru.
 */
class NeracaKumulatifTest extends TestCase
{
    use RefreshDatabase;

    private Cabang $cabang;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(AkunCoaSeeder::class);

        $this->cabang = Cabang::create([
            'kode' => 'CBG-NRC',
            'nama' => 'Cabang Neraca',
            'is_active' => true,
        ]);

        $this->user = User::create([
            'name' => 'Akuntan Neraca',
            'email' => 'akuntan-neraca@test.com',
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);
        $this->user->assignRole('finance'); // punya permission laporan.cabang
        $this->user->cabangs()->attach($this->cabang->id);
        session(['cabang_id' => $this->cabang->id]);
        $this->actingAs($this->user, 'web');

        $this->buatJurnalBulanLalu();
    }

    /**
     * Jurnal 4 buah di bulan sebelumnya + 1 buah di bulan berjalan.
     *
     * Saldo kumulatif s/d hari ini:
     *   Aset      : Kas 5.000.000 + 3.000.000 - 100.000 = 7.900.000
     *               Persediaan 2.000.000 - 2.000.000     =         0
     *   Kewajiban: Utang Usaha                          = 2.000.000
     *   Ekuitas   : Modal Pemilik                        = 5.000.000
     *   Laba      : 3.000.000 - (2.000.000 + 100.000)    =   900.000
     *   → 7.900.000 = 2.000.000 + 5.000.000 + 900.000  ✅
     */
    private function buatJurnalBulanLalu(): void
    {
        $lalu = now()->subMonth()->toDateString();
        $ini = now()->toDateString();

        // Modal awal: Dr Kas / Cr Modal Pemilik
        $this->jurnal('JRL-NRC-0001', $lalu, [
            ['akun_kode' => '110-01', 'debit' => 5000000, 'kredit' => 0],
            ['akun_kode' => '310-01', 'debit' => 0, 'kredit' => 5000000],
        ], 'Setoran modal pemilik');

        // Pembelian persediaan: Dr Persediaan / Cr Utang Usaha
        $this->jurnal('JRL-NRC-0002', $lalu, [
            ['akun_kode' => '130-01', 'debit' => 2000000, 'kredit' => 0],
            ['akun_kode' => '210-01', 'debit' => 0, 'kredit' => 2000000],
        ], 'Pembelian persediaan');

        // Penjualan: Dr Kas / Cr Pendapatan Penjualan
        $this->jurnal('JRL-NRC-0003', $lalu, [
            ['akun_kode' => '110-01', 'debit' => 3000000, 'kredit' => 0],
            ['akun_kode' => '410-01', 'debit' => 0, 'kredit' => 3000000],
        ], 'Penjualan bulan lalu');

        // HPP: Dr HPP / Cr Persediaan
        $this->jurnal('JRL-NRC-0004', $lalu, [
            ['akun_kode' => '510-02', 'debit' => 2000000, 'kredit' => 0],
            ['akun_kode' => '130-01', 'debit' => 0, 'kredit' => 2000000],
        ], 'HPP penjualan bulan lalu');

        // Beban listrik bulan BERJALAN (hanya ada di bulan ini)
        $this->jurnal('JRL-NRC-0005', $ini, [
            ['akun_kode' => '520-03', 'debit' => 100000, 'kredit' => 0],
            ['akun_kode' => '110-01', 'debit' => 0, 'kredit' => 100000],
        ], 'Listrik & air bulan ini');
    }

    /**
     * @param  array<int,array{akun_kode:string,debit:float,kredit:float}>  $lines
     */
    private function jurnal(string $noJurnal, string $tanggal, array $lines, string $deskripsi): void
    {
        foreach ($lines as $line) {
            JurnalAkuntansi::create([
                'no_jurnal' => $noJurnal,
                'tanggal' => $tanggal,
                'cabang_id' => $this->cabang->id,
                'akun_coa_id' => AkunCOA::where('kode', $line['akun_kode'])->firstOrFail()->id,
                'sumber' => 'manual',
                'deskripsi' => $deskripsi,
                'debit' => $line['debit'],
                'kredit' => $line['kredit'],
                'user_id' => $this->user->id,
            ]);
        }
    }

    private function service(): ExportLaporanService
    {
        return app(ExportLaporanService::class);
    }

    /** Saldo akun pertama yang cocok di dalam seksi neraca. */
    private function saldo(mixed $seksi, string $kode): ?float
    {
        $baris = collect($seksi)->firstWhere('kode', $kode);

        return $baris ? (float) $baris['saldo'] : null;
    }

    public function test_neraca_menjumlah_jurnal_bulan_sebelumnya_dan_tetap_balance(): void
    {
        $neraca = $this->service()->neracaSaldo($this->cabang->id, now()->toDateString());

        // Saldo historis ikut terhitung (kas & modal hanya bergerak bulan lalu)
        $this->assertSame(7900000.0, $this->saldo($neraca['aset'], '110-01'));
        $this->assertSame(5000000.0, $this->saldo($neraca['ekuitas'], '310-01'));
        $this->assertSame(2000000.0, $this->saldo($neraca['kewajiban'], '210-01'));

        $this->assertSame(7900000.0, $neraca['total_aset']);
        $this->assertSame(2000000.0, $neraca['total_kewajiban']);
        $this->assertSame(5000000.0, $neraca['total_ekuitas']);
        $this->assertSame(900000.0, $neraca['laba_periode_berjalan']);
        $this->assertSame(5900000.0, $neraca['total_ekuitas_bersama_laba']);

        // Aset = Kewajiban + Ekuitas + Laba Periode Berjalan
        $this->assertEqualsWithDelta(
            $neraca['total_kewajiban'] + $neraca['total_ekuitas'] + $neraca['laba_periode_berjalan'],
            $neraca['total_aset'],
            0.01,
            'Neraca harus balance walau jurnal utama di bulan sebelumnya'
        );
        $this->assertTrue($neraca['balance'], 'Penanda balance harus true');
        $this->assertSame(0.0, $neraca['selisih']);
        $this->assertSame('saldo_kumulatif', $neraca['basis']);
    }

    public function test_export_neraca_cukup_saldo_kumulatif_bukan_perubahan_periode(): void
    {
        $path = $this->service()->export('neraca', null, null, $this->cabang->id, null, 'csv');
        $baris = $this->barisCsv($path);

        $judul = (string) ($baris[0][0] ?? '');
        $this->assertStringContainsString('NERACA', $judul);
        $this->assertStringContainsString('SALDO KUMULATIF', $judul, 'Judul harus menyebut dasar kumulatif');
        $this->assertStringContainsString(now()->toDateString(), $judul);

        // Akun yang HANYA bergerak di bulan sebelumnya tetap muncul di neraca
        $nama = array_map(fn ($r) => (string) ($r[0] ?? ''), $baris);
        $this->assertContains('Modal Pemilik', $nama, 'Akun ekuitas bulan lalu wajib ikut terhitung');
        $this->assertContains('Utang Usaha', $nama, 'Akun kewajiban bulan lalu wajib ikut terhitung');
        $this->assertContains('Kas', $nama);

        $this->assertSame('7900000', $this->nilaiBaris($baris, 'TOTAL ASET'));
        $this->assertSame('2000000', $this->nilaiBaris($baris, 'TOTAL KEWAJIBAN'));
        $this->assertSame('5900000', $this->nilaiBaris($baris, 'TOTAL EKUITAS + LABA BERJALAN'));
        $this->assertSame('900000', $this->nilaiBaris($baris, 'LABA PERIODE BERJALAN (kumulatif s/d '.now()->toDateString().')'));
        $this->assertSame('0', $this->nilaiBaris($baris, 'SELISIH (ASET - KEWAJIBAN - EKUITAS)'));
        $this->assertSame('SEIMBANG', $this->nilaiBaris($baris, 'STATUS'));
    }

    public function test_api_neraca_sama_dengan_export(): void
    {
        $api = $this->getJson('/api/akunting/laporan/neraca?sampai='.now()->toDateString())
            ->assertSuccessful()
            ->json('data');

        $svc = $this->service()->neracaSaldo($this->cabang->id, now()->toDateString());

        $this->assertEqualsWithDelta($svc['total_aset'], (float) $api['total_aset'], 0.01);
        $this->assertEqualsWithDelta($svc['total_kewajiban'], (float) $api['total_kewajiban'], 0.01);
        $this->assertEqualsWithDelta($svc['total_ekuitas'], (float) $api['total_ekuitas'], 0.01);
        $this->assertEqualsWithDelta($svc['laba_periode_berjalan'], (float) $api['laba_periode_berjalan'], 0.01);

        $this->assertEqualsWithDelta(
            $api['total_kewajiban'] + $api['total_ekuitas'] + $api['laba_periode_berjalan'],
            $api['total_aset'],
            0.01,
            'API neraca harus balance (jurnal dari bulan sebelumnya)'
        );
        $this->assertTrue($api['balance']);
    }

    /** Laporan PERUBAHAN periode tetap berbasis rentang — tidak ikut jadi kumulatif. */
    public function test_laba_rugi_tetap_perubahan_periode_bukan_kumulatif(): void
    {
        $dari = now()->startOfMonth()->toDateString();

        $path = $this->service()->export('laba_rugi', $dari, now()->toDateString(), $this->cabang->id, null, 'csv');
        $baris = $this->barisCsv($path);

        $nama = array_map(fn ($r) => trim((string) ($r[0] ?? '')), $baris);
        $this->assertContains('Beban Listrik & Air', $nama, 'Jurnal bulan ini wajib masuk laba rugi');
        $this->assertNotContains(
            'Pendapatan Penjualan',
            $nama,
            'Laba rugi periode tidak boleh ikut memuat jurnal bulan sebelumnya (beda dgn neraca)'
        );

        $this->assertSame('100000', $this->nilaiBaris($baris, 'Beban Listrik & Air'));
        // Hanya jurnal bulan ini (Dr 100.000 / Cr 100.000); pendapatan 3 juta
        // bulan lalu TIDAK ikut (kalau ikut, LABA BERSIH = 900.000).
        $this->assertSame('100000', $this->nilaiBaris($baris, 'TOTAL DEBIT'));
        $this->assertSame('100000', $this->nilaiBaris($baris, 'TOTAL KREDIT'));
        $this->assertSame('0', $this->nilaiBaris($baris, 'LABA BERSIH (k- d)'));
    }

    /**
     * @return array<int,array<int,string>>
     */
    private function barisCsv(string $path): array
    {
        $isi = Storage::disk('local')->get($path);
        $baris = [];
        foreach (preg_split('/\r\n|\r|\n/', $isi) ?: [] as $line) {
            if ($line === '') {
                continue;
            }
            $baris[] = str_getcsv($line);
        }

        return $baris;
    }

    /** Nilai kolom terakhir dari baris ber label persis. */
    private function nilaiBaris(array $baris, string $label): ?string
    {
        foreach ($baris as $row) {
            if (trim((string) ($row[0] ?? '')) === $label) {
                return trim((string) ($row[2] ?? $row[1] ?? ''));
            }
        }

        return null;
    }
}
