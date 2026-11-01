<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Akunting\Livewire\AkuntingDashboard;
use App\Modules\Akunting\Models\AkunCOA;
use App\Modules\Akunting\Models\JurnalAkuntansi;
use App\Modules\Akunting\Models\JurnalHeader;
use App\Modules\Akunting\Services\JurnalService;
use App\Modules\Rbac\Models\Cabang;
use Carbon\Carbon;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class JurnalImmutabilityAndReversalTest extends TestCase
{
    use RefreshDatabase;

    private Cabang $cabang;

    private User $superAdmin;

    private JurnalService $jurnalService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seedCoa();

        $this->cabang = Cabang::create([
            'kode' => 'CB-IMMUTABLE',
            'nama' => 'Cabang Test Immutabilitas',
            'is_active' => true,
        ]);

        $this->superAdmin = User::factory()->create([
            'name' => 'Admin Akuntansi ISO',
            'email' => 'admin-iso@test.com',
        ]);
        $this->superAdmin->assignRole('super-admin');
        $this->superAdmin->cabangs()->attach($this->cabang->id);

        session(['cabang_id' => $this->cabang->id]);
        $this->actingAs($this->superAdmin, 'web');

        $this->jurnalService = app(JurnalService::class);
    }

    private function seedCoa(): void
    {
        $accounts = [
            ['kode' => '110-01', 'nama' => 'Kas', 'tipe' => 'aset', 'kelompok' => 'kas', 'saldo_normal' => 'debit'],
            ['kode' => '410-01', 'nama' => 'Pendapatan Penjualan', 'tipe' => 'pendapatan', 'kelompok' => 'pendapatan_penjualan', 'saldo_normal' => 'kredit'],
            ['kode' => '510-02', 'nama' => 'HPP', 'tipe' => 'beban', 'kelompok' => 'hpp', 'saldo_normal' => 'debit'],
            ['kode' => '310-02', 'nama' => 'Laba Ditahan', 'tipe' => 'ekuitas', 'kelompok' => 'laba_ditahan', 'saldo_normal' => 'kredit'],
        ];

        foreach ($accounts as $a) {
            AkunCOA::create(array_merge($a, ['is_active' => true]));
        }
    }

    public function test_model_jurnal_akuntansi_menolak_update_dan_delete(): void
    {
        $created = $this->jurnalService->post(
            noJurnal: 'JRL-IMMUTABLE-01',
            tanggal: now(),
            sumber: 'manual',
            lines: [
                ['akun_kode' => '110-01', 'debit' => 100000, 'kredit' => 0],
                ['akun_kode' => '410-01', 'debit' => 0, 'kredit' => 100000],
            ],
            cabangId: $this->cabang->id,
            userId: $this->superAdmin->id
        );

        $line = $created[0];

        // Update harus melempar DomainException
        try {
            $line->update(['debit' => 999999]);
            $this->fail('Harusnya melempar exception saat update baris jurnal');
        } catch (\DomainException $e) {
            $this->assertStringContainsString('immutable', $e->getMessage());
        }

        // Delete harus melempar DomainException
        try {
            $line->delete();
            $this->fail('Harusnya melempar exception saat delete baris jurnal');
        } catch (\DomainException $e) {
            $this->assertStringContainsString('immutable', $e->getMessage());
        }
    }

    public function test_model_jurnal_header_menolak_update_atribut_inti_dan_delete(): void
    {
        $this->jurnalService->post(
            noJurnal: 'JRL-IMMUTABLE-02',
            tanggal: now(),
            sumber: 'manual',
            lines: [
                ['akun_kode' => '110-01', 'debit' => 200000, 'kredit' => 0],
                ['akun_kode' => '410-01', 'debit' => 0, 'kredit' => 200000],
            ],
            cabangId: $this->cabang->id,
            userId: $this->superAdmin->id
        );

        $header = JurnalHeader::where('no_jurnal', 'JRL-IMMUTABLE-02')->firstOrFail();

        // Coba manipulasi nominal finansial
        try {
            $header->update(['total_debit' => 999999]);
            $this->fail('Harusnya melempar exception saat mengubah nominal header jurnal');
        } catch (\DomainException $e) {
            $this->assertStringContainsString('immutable', $e->getMessage());
        }

        // Coba delete header
        try {
            $header->delete();
            $this->fail('Harusnya melempar exception saat menghapus header jurnal');
        } catch (\DomainException $e) {
            $this->assertStringContainsString('immutable', $e->getMessage());
        }

        // Refresh instance setelah exception agar atribut kotor di memori dibersihkan
        $header->refresh();

        // Update status reversal (audit metadata) diizinkan
        $header->update([
            'is_reversed' => true,
            'reversed_by_no_jurnal' => 'JRL-REV-001',
        ]);

        $this->assertTrue($header->fresh()->is_reversed);
        $this->assertEquals('JRL-REV-001', $header->fresh()->reversed_by_no_jurnal);
    }

    public function test_reversal_jurnal_berhasil_membalik_posisi_dan_menandai_jurnal_asal(): void
    {
        $noJurnalAsal = 'JRL-SALAH-INPUT';

        $this->jurnalService->post(
            noJurnal: $noJurnalAsal,
            tanggal: Carbon::create(2026, 8, 10),
            sumber: 'manual',
            lines: [
                ['akun_kode' => '110-01', 'debit' => 750000, 'kredit' => 0],
                ['akun_kode' => '410-01', 'debit' => 0, 'kredit' => 750000],
            ],
            cabangId: $this->cabang->id,
            userId: $this->superAdmin->id
        );

        $hasil = $this->jurnalService->reversalJurnal(
            noJurnalAsal: $noJurnalAsal,
            cabangId: $this->cabang->id,
            userId: $this->superAdmin->id,
            alasan: 'Kesalahan penginputan akun pendapatan'
        );

        $this->assertNotEmpty($hasil['no_jurnal_reversal']);
        $this->assertEquals($noJurnalAsal, $hasil['jurnal_asal']);

        // Verifikasi baris pembalik (Kas jadi KREDIT, Pendapatan jadi DEBIT)
        $linesRev = JurnalAkuntansi::where('no_jurnal', $hasil['no_jurnal_reversal'])
            ->with('akun')
            ->get();

        $this->assertCount(2, $linesRev);

        $lineKas = $linesRev->firstWhere('akun.kode', '110-01');
        $this->assertEquals(0.0, (float) $lineKas->debit);
        $this->assertEquals(750000.0, (float) $lineKas->kredit);

        $linePendapatan = $linesRev->firstWhere('akun.kode', '410-01');
        $this->assertEquals(750000.0, (float) $linePendapatan->debit);
        $this->assertEquals(0.0, (float) $linePendapatan->kredit);

        // Header asal ditandai is_reversed
        $headerAsal = JurnalHeader::where('no_jurnal', $noJurnalAsal)->first();
        $this->assertTrue($headerAsal->is_reversed);
        $this->assertEquals($hasil['no_jurnal_reversal'], $headerAsal->reversed_by_no_jurnal);

        // Header reversal menautkan reversal_of_no_jurnal
        $headerRev = JurnalHeader::where('no_jurnal', $hasil['no_jurnal_reversal'])->first();
        $this->assertEquals($noJurnalAsal, $headerRev->reversal_of_no_jurnal);
    }

    public function test_reversal_jurnal_ganda_ditolak(): void
    {
        $noJurnalAsal = 'JRL-DOUBLE-REV';

        $this->jurnalService->post(
            noJurnal: $noJurnalAsal,
            tanggal: now(),
            sumber: 'manual',
            lines: [
                ['akun_kode' => '110-01', 'debit' => 50000, 'kredit' => 0],
                ['akun_kode' => '410-01', 'debit' => 0, 'kredit' => 50000],
            ],
            cabangId: $this->cabang->id,
            userId: $this->superAdmin->id
        );

        // Reversal pertama berhasil
        $this->jurnalService->reversalJurnal(
            noJurnalAsal: $noJurnalAsal,
            cabangId: $this->cabang->id,
            userId: $this->superAdmin->id,
            alasan: 'Reversal pertama'
        );

        // Reversal kedua harus ditolak
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('sudah pernah dibalik');

        $this->jurnalService->reversalJurnal(
            noJurnalAsal: $noJurnalAsal,
            cabangId: $this->cabang->id,
            userId: $this->superAdmin->id,
            alasan: 'Reversal kedua yang dilarang'
        );
    }

    public function test_api_reversal_jurnal_endpoint(): void
    {
        $noJurnal = 'JRL-API-REV';

        $this->jurnalService->post(
            noJurnal: $noJurnal,
            tanggal: now(),
            sumber: 'manual',
            lines: [
                ['akun_kode' => '110-01', 'debit' => 125000, 'kredit' => 0],
                ['akun_kode' => '410-01', 'debit' => 0, 'kredit' => 125000],
            ],
            cabangId: $this->cabang->id,
            userId: $this->superAdmin->id
        );

        $response = $this->postJson("/api/akunting/jurnal/{$noJurnal}/reversal", [
            'alasan' => 'Dibatalkan oleh pihak finance via API',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.jurnal_asal', $noJurnal);
    }

    public function test_livewire_dapat_mengeksekusi_reversal_jurnal(): void
    {
        $noJurnal = 'JRL-LIVEWIRE-REV';

        $this->jurnalService->post(
            noJurnal: $noJurnal,
            tanggal: now(),
            sumber: 'manual',
            lines: [
                ['akun_kode' => '110-01', 'debit' => 88000, 'kredit' => 0],
                ['akun_kode' => '410-01', 'debit' => 0, 'kredit' => 88000],
            ],
            cabangId: $this->cabang->id,
            userId: $this->superAdmin->id
        );

        Livewire::test(AkuntingDashboard::class)
            ->set('activeTab', 'jurnal')
            ->call('bukaModalReversal', $noJurnal)
            ->assertSet('showReversalModal', true)
            ->set('reversalAlasan', 'Koreksi lewat UI Livewire')
            ->call('eksekusiReversal')
            ->assertSet('showReversalModal', false);

        $header = JurnalHeader::where('no_jurnal', $noJurnal)->first();
        $this->assertTrue($header->is_reversed);
    }
}
