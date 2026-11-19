<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Akunting\Models\AkunCOA;
use App\Modules\Akunting\Models\JurnalAkuntansi;
use App\Modules\Akunting\Models\JurnalHeader;
use App\Modules\Akunting\Models\PeriodeAkuntansi;
use App\Modules\Akunting\Models\RekonsiliasiBank;
use App\Modules\Akunting\Models\RekonsiliasiBankItem;
use App\Modules\Akunting\Services\RekonsiliasiBankService;
use App\Modules\Rbac\Models\Cabang;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Regresi audit E-1 & E-2 (RekonsiliasiBankService).
 *
 * E-1: penyesuaian bank 1-klik wajib idempoten (item 'adjusted' tidak bisa
 *      diposting ulang), arah jurnal sesuai tipe mutasi DB/CR, scope cabang
 *      fail-closed, posting lewat JurnalService resmi (periode & akun aktif).
 * E-2: cleanNominal wajib benar untuk ribuan bertitik tanpa koma ('500.000'
 *      = 500000, bukan 500.0) dan format ID/US lainnya.
 */
class RekonsiliasiBankIntegritasTest extends TestCase
{
    use RefreshDatabase;

    private Cabang $cabang;

    private Cabang $cabangLain;

    private User $admin;

    private RekonsiliasiBankService $service;

    private AkunCOA $akunBank;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seedCoa();

        $this->cabang = Cabang::create([
            'kode' => 'CB-REKON-E1',
            'nama' => 'Cabang Rekonsiliasi E1',
            'is_active' => true,
        ]);
        $this->cabangLain = Cabang::create([
            'kode' => 'CB-REKON-E1-LAIN',
            'nama' => 'Cabang Lain E1',
            'is_active' => true,
        ]);

        $this->admin = User::factory()->create(['email' => 'admin-rekon-e1@test.com']);
        $this->admin->assignRole('super-admin');
        $this->admin->cabangs()->attach($this->cabang->id);

        session(['cabang_id' => $this->cabang->id]);
        $this->actingAs($this->admin, 'web');

        $this->service = app(RekonsiliasiBankService::class);
        $this->akunBank = AkunCOA::where('kode', '110-02')->firstOrFail();
    }

    private function seedCoa(): void
    {
        $accounts = [
            ['kode' => '110-01', 'nama' => 'Kas', 'tipe' => 'aset', 'kelompok' => 'kas', 'saldo_normal' => 'debit'],
            ['kode' => '110-02', 'nama' => 'Bank BCA', 'tipe' => 'aset', 'kelompok' => 'bank', 'saldo_normal' => 'debit'],
            ['kode' => '410-01', 'nama' => 'Pendapatan Penjualan', 'tipe' => 'pendapatan', 'kelompok' => 'pendapatan_penjualan', 'saldo_normal' => 'kredit'],
            ['kode' => '430-01', 'nama' => 'Pendapatan Bunga Bank', 'tipe' => 'pendapatan', 'kelompok' => 'pendapatan_lain', 'saldo_normal' => 'kredit'],
            ['kode' => '520-05', 'nama' => 'Biaya Administrasi Bank', 'tipe' => 'beban', 'kelompok' => 'beban_operasional', 'saldo_normal' => 'debit'],
        ];
        foreach ($accounts as $a) {
            AkunCOA::updateOrCreate(['kode' => $a['kode']], array_merge($a, ['is_active' => true]));
        }
    }

    private function buatRekonDanItem(string $tipe = 'DB', float $nominal = 17500, string $status = 'unmatched', ?int $cabangId = null): RekonsiliasiBankItem
    {
        $rekon = RekonsiliasiBank::create([
            'cabang_id' => $cabangId ?? $this->cabang->id,
            'akun_coa_id' => $this->akunBank->id,
            'nama_bank' => 'BCA',
            'periode_dari' => '2026-08-01',
            'periode_sampai' => '2026-08-31',
            'nama_file' => 'bca_e1.pdf',
            'format_file' => 'pdf',
            'status' => 'draft',
            'total_baris_bank' => 1,
            'total_matched' => $status === 'unmatched' ? 0 : 1,
            'user_id' => $this->admin->id,
        ]);

        return RekonsiliasiBankItem::create([
            'rekonsiliasi_bank_id' => $rekon->id,
            'tanggal' => '2026-08-15',
            'deskripsi' => 'BIAYA ADM BULANAN AGUSTUS',
            'tipe' => $tipe,
            'nominal' => $nominal,
            'status' => $status,
        ]);
    }

    // ===== E-1: idempotensi =====

    public function test_item_adjusted_tidak_bisa_diposting_ulang_double_posting(): void
    {
        $item = $this->buatRekonDanItem('DB', 17500);

        $hasil = $this->service->buatJurnalPenyesuaian(
            itemId: $item->id,
            jenisPenyesuaian: 'biaya_admin',
            userId: $this->admin->id,
        );

        // Klik kedua pada item yang sama (sudah 'adjusted') wajib ditolak.
        try {
            $this->service->buatJurnalPenyesuaian(
                itemId: $item->id,
                jenisPenyesuaian: 'biaya_admin',
                userId: $this->admin->id,
            );
            $this->fail('Penyesuaian ulang item adjusted seharusnya ditolak (double-posting).');
        } catch (\Exception $e) {
            $this->assertStringContainsStringIgnoringCase('sudah disesuaikan', $e->getMessage());
        }

        // Hanya SATU entri jurnal (2 baris) yang ter-posting.
        $this->assertSame(2, JurnalAkuntansi::count());
        $this->assertSame(1, JurnalHeader::count());
        $item->refresh();
        $this->assertSame('adjusted', $item->status);
        $this->assertSame($hasil['no_jurnal'], $item->no_jurnal_matched);
        $this->assertSame(1, (int) $item->rekonsiliasi->total_matched);
    }

    public function test_item_matched_tetap_ditolak(): void
    {
        $item = $this->buatRekonDanItem('DB', 17500, 'matched');

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('sudah tercocokkan');

        $this->service->buatJurnalPenyesuaian(
            itemId: $item->id,
            jenisPenyesuaian: 'biaya_admin',
            userId: $this->admin->id,
        );
    }

    // ===== E-1: arah jurnal sesuai tipe mutasi DB/CR =====

    public function test_biaya_admin_pada_mutasi_cr_uang_masuk_ditolak(): void
    {
        $item = $this->buatRekonDanItem('CR', 22500);

        try {
            $this->service->buatJurnalPenyesuaian(
                itemId: $item->id,
                jenisPenyesuaian: 'biaya_admin',
                userId: $this->admin->id,
            );
            $this->fail('biaya_admin pada mutasi CR seharusnya ditolak (arah jurnal terbalik).');
        } catch (\Exception $e) {
            $this->assertStringContainsStringIgnoringCase('DB', $e->getMessage());
        }

        $this->assertSame(0, JurnalAkuntansi::count());
        $this->assertSame('unmatched', $item->refresh()->status);
    }

    public function test_pendapatan_bunga_pada_mutasi_db_uang_keluar_ditolak(): void
    {
        $item = $this->buatRekonDanItem('DB', 22500);

        try {
            $this->service->buatJurnalPenyesuaian(
                itemId: $item->id,
                jenisPenyesuaian: 'pendapatan_bunga',
                userId: $this->admin->id,
            );
            $this->fail('pendapatan_bunga pada mutasi DB seharusnya ditolak (arah jurnal terbalik).');
        } catch (\Exception $e) {
            $this->assertStringContainsStringIgnoringCase('CR', $e->getMessage());
        }

        $this->assertSame(0, JurnalAkuntansi::count());
        $this->assertSame('unmatched', $item->refresh()->status);
    }

    public function test_bunga_mutasi_cr_memposting_debit_bank_kredit_pendapatan(): void
    {
        $item = $this->buatRekonDanItem('CR', 22500);

        $hasil = $this->service->buatJurnalPenyesuaian(
            itemId: $item->id,
            jenisPenyesuaian: 'pendapatan_bunga',
            userId: $this->admin->id,
        );

        $lines = JurnalAkuntansi::where('no_jurnal', $hasil['no_jurnal'])->with('akun')->get();
        $this->assertCount(2, $lines);
        $this->assertSame(22500.0, (float) $lines->firstWhere('akun.kode', '110-02')->debit);
        $this->assertSame(0.0, (float) $lines->firstWhere('akun.kode', '110-02')->kredit);
        $this->assertSame(22500.0, (float) $lines->firstWhere('akun.kode', '430-01')->kredit);
        $this->assertSame('adjusted', $item->refresh()->status);
    }

    public function test_jenis_penyesuaian_tidak_valid_ditolak(): void
    {
        $item = $this->buatRekonDanItem('CR', 22500);

        $this->expectException(\InvalidArgumentException::class);

        $this->service->buatJurnalPenyesuaian(
            itemId: $item->id,
            jenisPenyesuaian: 'asal_asalan',
            userId: $this->admin->id,
        );
    }

    // ===== E-1: scope cabang fail-closed =====

    public function test_penyesuaian_item_cabang_lain_ditolak(): void
    {
        $item = $this->buatRekonDanItem('DB', 17500, 'unmatched', $this->cabangLain->id);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('cabang');

        $this->service->buatJurnalPenyesuaian(
            itemId: $item->id,
            jenisPenyesuaian: 'biaya_admin',
            userId: $this->admin->id,
        );
    }

    public function test_penyesuaian_tanpa_cabang_aktif_ditolak_fail_closed(): void
    {
        $item = $this->buatRekonDanItem('DB', 17500);
        session()->forget('cabang_id');

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Cabang aktif');

        $this->service->buatJurnalPenyesuaian(
            itemId: $item->id,
            jenisPenyesuaian: 'biaya_admin',
            userId: $this->admin->id,
        );
    }

    public function test_scope_cabang_eksplisit_cocok_diperbolehkan(): void
    {
        $item = $this->buatRekonDanItem('DB', 17500, 'unmatched', $this->cabangLain->id);

        $hasil = $this->service->buatJurnalPenyesuaian(
            itemId: $item->id,
            jenisPenyesuaian: 'biaya_admin',
            userId: $this->admin->id,
            cabangId: $this->cabangLain->id,
        );

        $this->assertNotEmpty($hasil['no_jurnal']);
        $this->assertSame(
            $this->cabangLain->id,
            (int) JurnalAkuntansi::where('no_jurnal', $hasil['no_jurnal'])->value('cabang_id')
        );
    }

    public function test_scope_cabang_eksplisit_tidak_cocok_ditolak(): void
    {
        $item = $this->buatRekonDanItem('DB', 17500, 'unmatched', $this->cabangLain->id);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('cabang');

        $this->service->buatJurnalPenyesuaian(
            itemId: $item->id,
            jenisPenyesuaian: 'biaya_admin',
            userId: $this->admin->id,
            cabangId: $this->cabang->id,
        );
    }

    // ===== E-1: posting lewat JurnalService resmi (periode & akun aktif) =====

    public function test_penyesuaian_pada_periode_tutup_ditolak(): void
    {
        $item = $this->buatRekonDanItem('DB', 17500);
        PeriodeAkuntansi::create([
            'cabang_id' => $this->cabang->id,
            'tahun' => 2026,
            'bulan' => 8,
            'status' => 'closed',
        ]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('sudah ditutup');

        $this->service->buatJurnalPenyesuaian(
            itemId: $item->id,
            jenisPenyesuaian: 'biaya_admin',
            userId: $this->admin->id,
        );
    }

    public function test_penyesuaian_ke_akun_lawan_nonaktif_ditolak(): void
    {
        $item = $this->buatRekonDanItem('DB', 17500);
        AkunCOA::where('kode', '520-05')->update(['is_active' => false]);

        try {
            $this->service->buatJurnalPenyesuaian(
                itemId: $item->id,
                jenisPenyesuaian: 'biaya_admin',
                userId: $this->admin->id,
            );
            $this->fail('Posting ke akun nonaktif seharusnya ditolak JurnalService.');
        } catch (\Exception $e) {
            // pesan validasi akun nonaktif dari ValidasiBarisJurnal
        }

        $this->assertSame(0, JurnalAkuntansi::count());
        $this->assertSame('unmatched', $item->refresh()->status);
    }

    // ===== E-1: wiring API ACC-13 (controller memakai session cabang) =====

    public function test_api_penyesuaian_item_cabang_lain_ditolak_422(): void
    {
        $item = $this->buatRekonDanItem('DB', 17500, 'unmatched', $this->cabangLain->id);

        $response = $this->postJson("/api/akunting/rekonsiliasi-bank/item/{$item->id}/penyesuaian", [
            'jenis' => 'biaya_admin',
        ]);

        $response->assertStatus(422);
        $this->assertSame(0, JurnalAkuntansi::count());
        $this->assertSame('unmatched', $item->refresh()->status);
    }

    public function test_api_penyesuaian_sukses_dan_klik_ulang_ditolak_422(): void
    {
        $item = $this->buatRekonDanItem('DB', 17500);
        $url = "/api/akunting/rekonsiliasi-bank/item/{$item->id}/penyesuaian";

        $response = $this->postJson($url, ['jenis' => 'biaya_admin']);
        $response->assertStatus(200)->assertJsonPath('success', true);
        $noJurnal = $response->json('data.no_jurnal');
        $this->assertNotEmpty($noJurnal);

        // Klik ulang via API pada item yang sudah adjusted wajib ditolak.
        $ulang = $this->postJson($url, ['jenis' => 'biaya_admin']);
        $ulang->assertStatus(422);

        $this->assertSame(2, JurnalAkuntansi::count());
        $this->assertSame($noJurnal, $item->refresh()->no_jurnal_matched);
    }

    // ===== E-2: parser nominal Indonesia / US =====

    public static function nominalProvider(): array
    {
        return [
            'ribuan titik tanpa koma (E-2 inti)' => ['500.000', 500000.0],
            'ribuan titik kecil (E-2 inti)' => ['1.250', 1250.0],
            'ribuan titik ganda' => ['1.250.000', 1250000.0],
            'ID lengkap desimal' => ['500.000,50', 500000.5],
            'ID lengkap ,00' => ['1.250.000,00', 1250000.0],
            'ID biaya admin' => ['15.000,00', 15000.0],
            'polos' => ['500000', 500000.0],
            'koma desimal' => ['500,50', 500.5],
            'koma desimal kecil' => ['0,50', 0.5],
            'US ribuan koma' => ['1,250,000', 1250000.0],
            'US ribuan koma + desimal titik' => ['1,250,000.50', 1250000.5],
            'US desimal titik' => ['500.50', 500.5],
            'simbol mata uang' => ['Rp 500.000', 500000.0],
        ];
    }

    #[DataProvider('nominalProvider')]
    public function test_parser_nominal_rekening_koran_semua_format(string $cell, float $expected): void
    {
        $csv = "Tgl,Keterangan,Nominal,Tipe\n\"15/08/2026\",\"UJI NOMINAL\",\"{$cell}\",\"DB\"\n";
        $path = tempnam(sys_get_temp_dir(), 'rekon_e2_');
        file_put_contents($path, $csv);

        try {
            $parsed = $this->service->parseCsv($path);
        } finally {
            @unlink($path);
        }

        $this->assertCount(1, $parsed);
        $this->assertSame($expected, $parsed[0]['nominal'], "Nominal '{$cell}' salah parse.");
    }

    public function test_nominal_ribuan_bertitik_mengalir_benar_sampai_jurnal_penyesuaian(): void
    {
        // E-2 end-to-end: '500.000' (tanpa ',00') harus menjadi 500000 di item
        // rekonsiliasi DAN di jurnal penyesuaian (bukan 500 = salah 1000x).
        $csv = "Tgl,Keterangan,Nominal,Tipe\n\"05/08/2026\",\"BIAYA ADM REK\",\"500.000\",\"DB\"\n";
        $path = tempnam(sys_get_temp_dir(), 'rekon_e2e_');
        file_put_contents($path, $csv);

        try {
            $parsed = $this->service->parseCsv($path);
        } finally {
            @unlink($path);
        }

        $this->assertSame(500000.0, $parsed[0]['nominal']);

        $rekon = $this->service->prosesImport(
            cabangId: $this->cabang->id,
            akunCoaId: $this->akunBank->id,
            namaBank: 'BCA',
            namaFile: 'mutasi_e2.csv',
            formatFile: 'csv',
            items: $parsed,
            userId: $this->admin->id,
        );

        $item = $rekon->items()->firstOrFail();
        $this->assertSame('unmatched', $item->status);
        $this->assertSame(500000.0, (float) $item->nominal);

        $hasil = $this->service->buatJurnalPenyesuaian(
            itemId: $item->id,
            jenisPenyesuaian: 'biaya_admin',
            userId: $this->admin->id,
        );

        $lines = JurnalAkuntansi::where('no_jurnal', $hasil['no_jurnal'])->get();
        $this->assertSame(500000.0, (float) $lines->sum('debit'));
        $this->assertSame(500000.0, (float) $lines->sum('kredit'));
        $this->assertSame(
            500000.0,
            (float) JurnalHeader::where('no_jurnal', $hasil['no_jurnal'])->value('total_debit')
        );
    }
}
