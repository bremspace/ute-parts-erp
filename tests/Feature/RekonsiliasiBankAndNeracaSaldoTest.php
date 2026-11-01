<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Akunting\Livewire\AkuntingDashboard;
use App\Modules\Akunting\Models\AkunCOA;
use App\Modules\Akunting\Models\JurnalAkuntansi;
use App\Modules\Akunting\Models\RekonsiliasiBank;
use App\Modules\Akunting\Models\RekonsiliasiBankItem;
use App\Modules\Akunting\Services\ExportLaporanService;
use App\Modules\Akunting\Services\JurnalService;
use App\Modules\Akunting\Services\RekonsiliasiBankService;
use App\Modules\Rbac\Models\Cabang;
use Carbon\Carbon;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class RekonsiliasiBankAndNeracaSaldoTest extends TestCase
{
    use RefreshDatabase;

    private Cabang $cabang;

    private User $superAdmin;

    private JurnalService $jurnalService;

    private RekonsiliasiBankService $rekonService;

    private ExportLaporanService $exportService;

    private AkunCOA $akunBank;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seedCoa();

        $this->cabang = Cabang::create([
            'kode' => 'CB-REKON-TEST',
            'nama' => 'Cabang Test Rekonsiliasi',
            'is_active' => true,
        ]);

        $this->superAdmin = User::factory()->create([
            'name' => 'Admin Rekonsiliasi ISO',
            'email' => 'admin-rekon@test.com',
        ]);
        $this->superAdmin->assignRole('super-admin');
        $this->superAdmin->cabangs()->attach($this->cabang->id);

        session(['cabang_id' => $this->cabang->id]);
        $this->actingAs($this->superAdmin, 'web');

        $this->jurnalService = app(JurnalService::class);
        $this->rekonService = app(RekonsiliasiBankService::class);
        $this->exportService = app(ExportLaporanService::class);

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
            ['kode' => '310-02', 'nama' => 'Laba Ditahan', 'tipe' => 'ekuitas', 'kelompok' => 'laba_ditahan', 'saldo_normal' => 'kredit'],
        ];

        foreach ($accounts as $a) {
            AkunCOA::updateOrCreate(
                ['kode' => $a['kode']],
                array_merge($a, ['is_active' => true])
            );
        }
    }

    public function test_neraca_saldo_4_kolom_menghitung_saldo_awal_mutasi_dan_saldo_akhir_seimbang(): void
    {
        // 1. Transaksi periode awal (Bulan Juli 2026) -> Jadi saldo awal di Agustus
        $this->jurnalService->post(
            noJurnal: 'JRL-JULI-01',
            tanggal: Carbon::create(2026, 7, 15),
            sumber: 'manual',
            lines: [
                ['akun_kode' => '110-02', 'debit' => 5000000, 'kredit' => 0],
                ['akun_kode' => '410-01', 'debit' => 0, 'kredit' => 5000000],
            ],
            cabangId: $this->cabang->id,
            userId: $this->superAdmin->id
        );

        // 2. Transaksi periode berjalan (Bulan Agustus 2026) -> Jadi mutasi periode
        $this->jurnalService->post(
            noJurnal: 'JRL-AGUSTUS-01',
            tanggal: Carbon::create(2026, 8, 5),
            sumber: 'manual',
            lines: [
                ['akun_kode' => '110-02', 'debit' => 2000000, 'kredit' => 0],
                ['akun_kode' => '410-01', 'debit' => 0, 'kredit' => 2000000],
            ],
            cabangId: $this->cabang->id,
            userId: $this->superAdmin->id
        );

        $this->jurnalService->post(
            noJurnal: 'JRL-AGUSTUS-02',
            tanggal: Carbon::create(2026, 8, 10),
            sumber: 'manual',
            lines: [
                ['akun_kode' => '520-05', 'debit' => 25000, 'kredit' => 0],
                ['akun_kode' => '110-02', 'debit' => 0, 'kredit' => 25000],
            ],
            cabangId: $this->cabang->id,
            userId: $this->superAdmin->id
        );

        // Hitung Neraca Saldo untuk periode 2026-08-01 s/d 2026-08-31
        $ns = $this->exportService->neracaSaldo4Kolom(
            cabangId: $this->cabang->id,
            dari: '2026-08-01',
            sampai: '2026-08-31'
        );

        $this->assertTrue($ns['is_balanced'], 'Neraca saldo wajib balance');
        $this->assertEquals(2025000.0, $ns['total']['mutasi_debit']);
        $this->assertEquals(2025000.0, $ns['total']['mutasi_kredit']);

        // Akun Bank BCA (110-02):
        // Saldo Awal: 5.000.000
        // Mutasi Debit: 2.000.000, Mutasi Kredit: 25.000
        // Saldo Akhir: 6.975.000
        $bankRow = collect($ns['akun'])->firstWhere('kode', '110-02');
        $this->assertNotNull($bankRow);
        $this->assertEquals(5000000.0, (float) $bankRow['saldo_awal_debit']);
        $this->assertEquals(2000000.0, (float) $bankRow['mutasi_debit']);
        $this->assertEquals(25000.0, (float) $bankRow['mutasi_kredit']);
        $this->assertEquals(6975000.0, (float) $bankRow['saldo_akhir_debit']);
    }

    public function test_parser_csv_mutasi_bank_dan_proses_auto_matching(): void
    {
        // Siapkan jurnal di buku besar
        $this->jurnalService->post(
            noJurnal: 'JRL-MATCH-01',
            tanggal: Carbon::create(2026, 8, 2),
            sumber: 'pos',
            lines: [
                ['akun_kode' => '110-02', 'debit' => 1250000, 'kredit' => 0],
                ['akun_kode' => '410-01', 'debit' => 0, 'kredit' => 1250000],
            ],
            cabangId: $this->cabang->id,
            userId: $this->superAdmin->id
        );

        $csvContent = <<<'CSV'
Tgl,Keterangan,Cabang,Nominal,Tipe
"02/08/2026","TRSF E-BANKING CR DARI PELANGGAN","0000","1.250.000,00","CR"
"05/08/2026","BIAYA ADM REK","0000","15.000,00","DB"
CSV;

        $tempPath = tempnam(sys_get_temp_dir(), 'rekon_csv_');
        file_put_contents($tempPath, $csvContent);

        $parsed = $this->rekonService->parseCsv($tempPath);
        $this->assertCount(2, $parsed);
        $this->assertEquals('2026-08-02', $parsed[0]['tanggal']);
        $this->assertEquals(1250000.0, $parsed[0]['nominal']);
        $this->assertEquals('CR', $parsed[0]['tipe']);

        $this->assertEquals('2026-08-05', $parsed[1]['tanggal']);
        $this->assertEquals(15000.0, $parsed[1]['nominal']);
        $this->assertEquals('DB', $parsed[1]['tipe']);

        // Jalankan import & auto matching
        $rekon = $this->rekonService->prosesImport(
            cabangId: $this->cabang->id,
            akunCoaId: $this->akunBank->id,
            namaBank: 'BCA',
            namaFile: 'mutasi_agustus.csv',
            formatFile: 'csv',
            items: $parsed,
            userId: $this->superAdmin->id
        );

        $this->assertEquals(2, $rekon->total_baris_bank);
        $this->assertEquals(1, $rekon->total_matched); // 1 cocok otomatis, 1 belum cocok

        $matchedItem = $rekon->items()->where('nominal', 1250000)->first();
        $this->assertEquals('matched', $matchedItem->status);
        $this->assertEquals('JRL-MATCH-01', $matchedItem->no_jurnal_matched);

        $unmatchedItem = $rekon->items()->where('nominal', 15000)->first();
        $this->assertEquals('unmatched', $unmatchedItem->status);

        @unlink($tempPath);
    }

    public function test_parser_pdf_estatement_bca_mandiri_layout(): void
    {
        // Contoh teks hasil layout PDF e-Statement BCA
        $pdfLayoutText = <<<'TEXT'
PT BANK CENTRAL ASIA Tbk
REKENING TAHAPAN
PERIODE: AGUSTUS 2026
NO REKENING: 8010293841

TGL   KETERANGAN                              MUTASI (RP)            SALDO (RP)
SALDO AWAL                                                      25.000.000,00
02/08 TRSF E-BANKING CR DARI BAPAK BUDI         1.500.000,00 CR 26.500.000,00
05/08 BIAYA ADM BULANAN                            15.000,00 DB 26.485.000,00
10/08 BUNGA BANK                                   22.500,00 CR 26.507.500,00
TEXT;

        // Buat file PDF minimal yang valid dengan stream teks
        $tempPdf = tempnam(sys_get_temp_dir(), 'bca_stmt_').'.pdf';
        $stream = "BT /F1 12 Tf 50 700 Td (02/08 TRSF E-BANKING CR 1.500.000,00 CR) Tj ET\n";
        $stream .= "BT /F1 12 Tf 50 680 Td (05/08 BIAYA ADM DB 15.000,00 DB) Tj ET\n";
        $pdfData = "%PDF-1.4\n1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj\n2 0 obj<</Type/Pages/Kids[3 0 R]/Count 1>>endobj\n3 0 obj<</Type/Page/Parent 2 0 R/MediaBox[0 0 612 792]/Contents 4 0 R>>endobj\n4 0 obj<</Length ".strlen($stream).">>stream\n".$stream."endstream\nendobj\nxref\n0 5\n0000000000 65535 f \n0000000009 00000 n \n0000000056 00000 n \n0000000111 00000 n \n0000000212 00000 n \ntrailer<</Size 5/Root 1 0 R>>\nstartxref\n".(300 + strlen($stream))."\n%%EOF";

        file_put_contents($tempPdf, $pdfData);

        $parsed = $this->rekonService->parsePdf($tempPdf);

        $this->assertNotEmpty($parsed, 'Harus dapat mengekstrak mutasi dari berkas PDF');
        $this->assertEquals(1500000.0, $parsed[0]['nominal']);
        $this->assertEquals('CR', $parsed[0]['tipe']);

        $this->assertEquals(15000.0, $parsed[1]['nominal']);
        $this->assertEquals('DB', $parsed[1]['tipe']);

        @unlink($tempPdf);
    }

    public function test_buat_jurnal_penyesuaian_1_klik_untuk_biaya_admin_bank(): void
    {
        $rekon = RekonsiliasiBank::create([
            'cabang_id' => $this->cabang->id,
            'akun_coa_id' => $this->akunBank->id,
            'nama_bank' => 'BCA',
            'periode_dari' => '2026-08-01',
            'periode_sampai' => '2026-08-31',
            'nama_file' => 'bca_test.pdf',
            'format_file' => 'pdf',
            'status' => 'draft',
            'total_baris_bank' => 1,
            'total_matched' => 0,
            'user_id' => $this->superAdmin->id,
        ]);

        $item = RekonsiliasiBankItem::create([
            'rekonsiliasi_bank_id' => $rekon->id,
            'tanggal' => '2026-08-15',
            'deskripsi' => 'BIAYA ADM BULANAN AGUSTUS',
            'tipe' => 'DB',
            'nominal' => 17500,
            'status' => 'unmatched',
        ]);

        $hasil = $this->rekonService->buatJurnalPenyesuaian(
            itemId: $item->id,
            jenisPenyesuaian: 'biaya_admin',
            userId: $this->superAdmin->id,
            keterangan: 'Koreksi biaya admin dari rekening koran'
        );

        $this->assertNotEmpty($hasil['no_jurnal']);

        $item->refresh();
        $this->assertEquals('adjusted', $item->status);
        $this->assertEquals($hasil['no_jurnal'], $item->no_jurnal_matched);

        // Verifikasi entri jurnal penyesuaian: Debit Biaya Admin (520-05), Kredit Bank (110-02)
        $lines = JurnalAkuntansi::where('no_jurnal', $hasil['no_jurnal'])->with('akun')->get();
        $this->assertCount(2, $lines);

        $lineBeban = $lines->firstWhere('akun.kode', '520-05');
        $this->assertEquals(17500.0, (float) $lineBeban->debit);

        $lineBank = $lines->firstWhere('akun.kode', '110-02');
        $this->assertEquals(17500.0, (float) $lineBank->kredit);
    }

    public function test_api_neraca_saldo_endpoint(): void
    {
        $response = $this->getJson('/api/akunting/laporan/neraca-saldo?dari=2026-08-01&sampai=2026-08-31');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'periode',
                    'akun',
                    'total' => [
                        'saldo_awal_debit',
                        'saldo_awal_kredit',
                        'mutasi_debit',
                        'mutasi_kredit',
                        'saldo_akhir_debit',
                        'saldo_akhir_kredit',
                    ],
                    'is_balanced',
                ],
            ]);
    }

    public function test_livewire_neraca_saldo_dan_rekonsiliasi_bank_tab_render(): void
    {
        Livewire::test(AkuntingDashboard::class)
            ->set('activeTab', 'neraca-saldo')
            ->assertSee('Kertas Kerja Neraca Saldo 4 Kolom')
            ->set('activeTab', 'rekonsiliasi-bank')
            ->assertSee('Rekonsiliasi Rekening Koran Bank')
            ->assertSee('Import Rekening Koran (PDF / CSV)');
    }
}
