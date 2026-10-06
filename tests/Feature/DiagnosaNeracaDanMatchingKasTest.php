<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Akunting\Livewire\AkuntingDashboard;
use App\Modules\Akunting\Models\AkunCOA;
use App\Modules\Akunting\Models\KasMatching;
use App\Modules\Akunting\Services\DiagnosaNeracaService;
use App\Modules\Akunting\Services\JurnalService;
use App\Modules\Rbac\Models\Cabang;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class DiagnosaNeracaDanMatchingKasTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Cabang $cabang;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->cabang = Cabang::create([
            'nama' => 'Cabang Utama',
            'kode' => 'C01',
            'alamat' => 'Jl. Test No. 1',
            'telepon' => '081234567890',
            'is_aktif' => true,
        ]);

        $this->user = User::create([
            'name' => 'Finance Tester',
            'email' => 'finance-test@uteparts.id',
            'password' => Hash::make('secret123'),
            'is_active' => true,
        ]);
        $this->user->assignRole('finance');
        $this->user->cabangs()->attach($this->cabang->id);

        session(['cabang_id' => $this->cabang->id]);
        $this->actingAs($this->user, 'web');

        // Pastikan akun COA tersedia (updateOrCreate agar tidak duplicate jika seeder sudah membuat)
        $daftarCoa = [
            ['kode' => '110-01', 'nama' => 'Kas Besar', 'kelompok' => 'kas', 'tipe' => 'aset', 'saldo_normal' => 'debit'],
            ['kode' => '110-04', 'nama' => 'Kas Laci Kasir', 'kelompok' => 'kas', 'tipe' => 'aset', 'saldo_normal' => 'debit'],
            ['kode' => '310-01', 'nama' => 'Modal Pemilik', 'kelompok' => 'modal', 'tipe' => 'ekuitas', 'saldo_normal' => 'kredit'],
            ['kode' => '310-02', 'nama' => 'Laba Ditahan', 'kelompok' => 'modal', 'tipe' => 'ekuitas', 'saldo_normal' => 'kredit'],
            ['kode' => '430-01', 'nama' => 'Pendapatan Lain-lain', 'kelompok' => 'pendapatan_lain', 'tipe' => 'pendapatan', 'saldo_normal' => 'kredit'],
            ['kode' => '520-07', 'nama' => 'Beban Selisih Kas', 'kelompok' => 'beban_lain', 'tipe' => 'beban', 'saldo_normal' => 'debit'],
            ['kode' => '520-05', 'nama' => 'Beban Lain-lain (Suspense)', 'kelompok' => 'beban_lain', 'tipe' => 'beban', 'saldo_normal' => 'debit'],
        ];

        foreach ($daftarCoa as $item) {
            AkunCOA::updateOrCreate(['kode' => $item['kode']], array_merge($item, ['is_active' => true]));
        }
    }

    public function test_diagnosa_neraca_mendeteksi_neraca_seimbang_sempurna(): void
    {
        // Beri modal awal double-entry seimbang
        $jurnalService = app(JurnalService::class);
        $jurnalService->post(
            'JRL-MODAL-001',
            now(),
            'manual',
            [
                ['akun_kode' => '110-01', 'debit' => 10000000, 'kredit' => 0],
                ['akun_kode' => '310-01', 'debit' => 0, 'kredit' => 10000000],
            ],
            'Modal Awal Pemilik',
            $this->cabang->id,
            $this->user->id
        );

        $service = app(DiagnosaNeracaService::class);
        $hasil = $service->diagnosa($this->cabang->id);

        $this->assertTrue($hasil['is_balance']);
        $this->assertEquals(0, $hasil['selisih']);
        $this->assertGreaterThanOrEqual(90, $hasil['skor_kesehatan']);
        $this->assertStringContainsString('SEIMBANG', $hasil['kesimpulan_ai']);
    }

    public function test_diagnosa_neraca_mendeteksi_jurnal_tidak_balance(): void
    {
        // Sengaja buat baris jurnal timpang langsung di DB (bypass service validation)
        $akunKas = AkunCOA::where('kode', '110-01')->first();
        \DB::table('jurnal_akuntansi')->insert([
            'cabang_id' => $this->cabang->id,
            'akun_coa_id' => $akunKas->id,
            'no_jurnal' => 'JRL-TIMPANG-01',
            'tanggal' => now(),
            'sumber' => 'manual',
            'debit' => 500000,
            'kredit' => 0,
            'deskripsi' => 'Transaksi timpang tanpa kredit',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $service = app(DiagnosaNeracaService::class);
        $hasil = $service->diagnosa($this->cabang->id);

        $this->assertFalse($hasil['is_balance']);
        $this->assertGreaterThan(0, $hasil['total_temuan']);
        $this->assertGreaterThan(0, $hasil['jumlah_kritis']);

        $temuanJurnal = collect($hasil['daftar_temuan'])->firstWhere('kategori', 'jurnal');
        $this->assertNotNull($temuanJurnal);
        $this->assertEquals('Finance / Accounting', $temuanJurnal['bagian']);
        $this->assertTrue($temuanJurnal['solusi_otomatis_tersedia']);

        // Uji coba fitur auto-remediasi (perbaiki otomatis)
        $perbaikan = $service->perbaikiOtomatis('seimbangkan_jurnal', $temuanJurnal['payload'], $this->user->id);
        $this->assertTrue($perbaikan['success']);

        // Setelah perbaikan di-post, diagnosa ulang harus seimbang
        $hasilUlang = $service->diagnosa($this->cabang->id);
        $this->assertTrue($hasilUlang['is_balance']);
    }

    public function test_matching_kas_real_berhasil_disimpan_dan_dijurnal_penyesuaian(): void
    {
        $akunKas = AkunCOA::where('kode', '110-01')->first();

        // 1. Livewire matching kas
        Livewire::test(AkuntingDashboard::class)
            ->set('activeTab', 'matching-kas')
            ->call('bukaFormMatching', $akunKas->id)
            ->assertSet('selectedAkunKasId', $akunKas->id)
            ->set('saldoFisikKasInput', '1250000') // Saldo fisik 1.250.000 (sistem 0 -> selisih +1.250.000)
            ->set('catatanMatchingKas', 'Opname uang fisik laci kas')
            ->call('simpanMatchingKas')
            ->assertDispatched('alert');

        $matching = KasMatching::where('akun_id', $akunKas->id)->first();
        $this->assertNotNull($matching);
        $this->assertEquals(1250000, (float) $matching->saldo_fisik);
        $this->assertEquals('selisih', $matching->status);

        // 2. Eksekusi posting jurnal penyesuaian selisih kas
        Livewire::test(AkuntingDashboard::class)
            ->call('postingPenyesuaianKas', $matching->id)
            ->assertDispatched('alert');

        $matching->refresh();
        $this->assertEquals('disesuaikan', $matching->status);
        $this->assertNotNull($matching->jurnal_id);

        // Saldo akun kas di buku besar sekarang harus bertambah 1.250.000
        $service = app(DiagnosaNeracaService::class);
        $neraca = $service->diagnosa($this->cabang->id)['neraca'];
        $this->assertEquals(1250000, (float) ($neraca['total_aset'] ?? 0));
    }

    public function test_diagnosa_dan_matching_multi_cabang_dan_konsolidasi(): void
    {
        $cabang2 = Cabang::create([
            'nama' => 'Cabang Kedua',
            'kode' => 'C02',
            'alamat' => 'Jl. Cabang 2',
            'telepon' => '0899999999',
            'is_aktif' => true,
        ]);
        $this->user->cabangs()->attach($cabang2->id);

        $jurnalService = app(JurnalService::class);
        // Cabang 1: Kas 5.000.000 vs Modal 5.000.000
        $jurnalService->post(
            'JRL-C1-001',
            now(),
            'manual',
            [
                ['akun_kode' => '110-01', 'debit' => 5000000, 'kredit' => 0],
                ['akun_kode' => '310-01', 'debit' => 0, 'kredit' => 5000000],
            ],
            'Setoran Modal C1',
            $this->cabang->id,
            $this->user->id
        );

        // Cabang 2: Kas 3.000.000 vs Modal 3.000.000
        $jurnalService->post(
            'JRL-C2-001',
            now(),
            'manual',
            [
                ['akun_kode' => '110-01', 'debit' => 3000000, 'kredit' => 0],
                ['akun_kode' => '310-01', 'debit' => 0, 'kredit' => 3000000],
            ],
            'Setoran Modal C2',
            $cabang2->id,
            $this->user->id
        );

        $diagnosaService = app(DiagnosaNeracaService::class);

        // 1. Diagnosa Cabang 1
        $diagC1 = $diagnosaService->diagnosa($this->cabang->id);
        $this->assertTrue($diagC1['is_balance']);
        $this->assertEquals(5000000, (float) $diagC1['neraca']['total_aset']);

        // 2. Diagnosa Cabang 2
        $diagC2 = $diagnosaService->diagnosa($cabang2->id);
        $this->assertTrue($diagC2['is_balance']);
        $this->assertEquals(3000000, (float) $diagC2['neraca']['total_aset']);

        // 3. Diagnosa Konsolidasi (seluruh cabang)
        $diagKonsol = $diagnosaService->diagnosa(null);
        $this->assertTrue($diagKonsol['is_balance']);
        $this->assertEquals(8000000, (float) $diagKonsol['neraca']['total_aset']);

        // 4. Uji isolasi matching kas per cabang
        $akunKas = AkunCOA::where('kode', '110-01')->first();
        Livewire::test(AkuntingDashboard::class)
            ->call('bukaFormMatching', $akunKas->id, $cabang2->id)
            ->assertSet('matchingCabangId', $cabang2->id)
            ->assertSet('saldoSistemKas', 3000000.0) // Harus membaca saldo cabang 2 (3jt, bukan 8jt atau 5jt)
            ->set('saldoFisikKasInput', '3000000')
            ->call('simpanMatchingKas');

        $matchingC2 = KasMatching::where('cabang_id', $cabang2->id)->first();
        $this->assertNotNull($matchingC2);
        $this->assertEquals('cocok', $matchingC2->status);

        // Matching kas di cabang 1 harus tetap 0 record
        $this->assertEquals(0, KasMatching::where('cabang_id', $this->cabang->id)->count());
    }
}
