<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Akunting\Models\JurnalAkuntansi;
use App\Modules\Akunting\Services\JurnalService;
use App\Modules\Rbac\Models\Cabang;
use App\Modules\Wms\Models\Gudang;
use App\Modules\Wms\Models\ImportLog;
use App\Modules\Wms\Models\Produk;
use App\Modules\Wms\Models\Rak;
use App\Modules\Wms\Models\StockMutationLog;
use App\Modules\Wms\Models\StokItem;
use App\Modules\Wms\Models\StokLog;
use App\Modules\Wms\Services\ImportProdukService;
use Database\Seeders\AkunCoaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Enumerable;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Facades\Excel;
use Mockery;
use Tests\TestCase;

/**
 * B-10f / P1-5 — import stok & jurnal agregat harus ATOMIK + satu cabang.
 *
 * 1. File yang menunjuk gudang dari >1 cabang DITOLAK (pesan Indonesia),
 *    di preview() maupun commit(), SEBELUM ada mutasi apa pun.
 * 2. Import satu cabang: stok + StokLog + StockMutationLog + jurnal 130-01/310-01
 *    yang balance, dan jurnal berada di cabang yang benar.
 * 3. Jurnal gagal → tidak boleh ada mutasi stok yatim (dan sebaliknya).
 */
class ImportProdukAtomikTest extends TestCase
{
    use RefreshDatabase;

    private array $kolom = [
        'sku', 'nama', 'barcode', 'satuan', 'kategori', 'tipe_hp', 'brand',
        'kualitas', 'harga_beli', 'harga_jual', 'harga_reseller', 'harga_agen',
        'stok_awal', 'gudang_id', 'rak_id', 'kompatibilitas_hp', 'foto_url',
    ];

    private Cabang $cabangA;

    private Cabang $cabangB;

    private Gudang $gudangA;

    private Gudang $gudangB;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(AkunCoaSeeder::class);

        $this->cabangA = Cabang::create(['nama' => 'Cabang A', 'kode' => 'CBG-IMA', 'is_active' => true]);
        $this->cabangB = Cabang::create(['nama' => 'Cabang B', 'kode' => 'CBG-IMB', 'is_active' => true]);
        $this->gudangA = Gudang::create([
            'cabang_id' => $this->cabangA->id, 'nama' => 'Gudang A', 'kode' => 'GDG-IMA', 'is_active' => true,
        ]);
        $this->gudangB = Gudang::create([
            'cabang_id' => $this->cabangB->id, 'nama' => 'Gudang B', 'kode' => 'GDG-IMB', 'is_active' => true,
        ]);

        $this->user = User::create([
            'name' => 'Operator Import', 'email' => 'imp@test.local',
            'password' => bcrypt('password'), 'is_active' => true,
        ]);
    }

    private function baris(string $sku, int $gudangId, int $stokAwal = 5, int $hargaBeli = 50000): array
    {
        return [
            'sku' => $sku, 'nama' => "Produk {$sku}", 'barcode' => '8991'.$sku,
            'satuan' => 'pcs', 'kategori' => 'Umum', 'tipe_hp' => '', 'brand' => '',
            'kualitas' => '', 'harga_beli' => $hargaBeli, 'harga_jual' => $hargaBeli * 2,
            'harga_reseller' => '', 'harga_agen' => '',
            'stok_awal' => $stokAwal, 'gudang_id' => $gudangId, 'rak_id' => '',
            'kompatibilitas_hp' => '', 'foto_url' => '',
        ];
    }

    private function tulisCsv(array $baris, string $namaFile): string
    {
        $kolom = $this->kolom;
        $export = new class($kolom, $baris) implements FromCollection, WithHeadings
        {
            public function __construct(private array $kolom, private array $baris) {}

            public function headings(): array
            {
                return $this->kolom;
            }

            public function collection(): Enumerable
            {
                return collect($this->baris);
            }
        };

        Excel::store($export, 'import-tmp/'.$namaFile, null, \Maatwebsite\Excel\Excel::CSV);

        return Storage::disk('local')->path('import-tmp/'.$namaFile);
    }

    private function buatImportLog(): ImportLog
    {
        return ImportLog::create([
            'tipe' => 'produk_excel', 'nama_file' => 'uji.csv',
            'total_baris' => 0, 'sukses' => 0, 'gagal' => 0, 'status' => 'proses',
        ]);
    }

    // =============================================================
    // (3) lintas cabang ditolak
    // =============================================================

    public function test_preview_menolak_file_lintas_cabang(): void
    {
        $path = $this->tulisCsv([
            $this->baris('IMP-X-01', $this->gudangA->id),
            $this->baris('IMP-X-02', $this->gudangB->id),
        ], 'lintas-cabang.csv');

        $this->expectException(\Exception::class);
        $this->expectExceptionMessageMatches('/Import lintas cabang tidak didukung/');

        app(ImportProdukService::class)->preview($path);
    }

    public function test_commit_menolak_file_lintas_cabang_tanpa_mutasi(): void
    {
        $path = $this->tulisCsv([
            $this->baris('IMP-X-11', $this->gudangA->id),
            $this->baris('IMP-X-12', $this->gudangB->id),
        ], 'lintas-cabang-commit.csv');

        $log = $this->buatImportLog();

        $pesan = null;
        try {
            app(ImportProdukService::class)->commit($path, $log->id, $this->user->id);
        } catch (\Throwable $e) {
            $pesan = $e->getMessage();
        }

        $this->assertNotNull($pesan, 'Commit lintas cabang harus ditolak');
        $this->assertStringContainsString('Import lintas cabang tidak didukung', $pesan);
        $this->assertStringContainsString('Cabang A', $pesan);
        $this->assertStringContainsString('Cabang B', $pesan);
        $this->assertStringContainsString('Pisahkan file per cabang', $pesan);

        // Tidak ada efek samping sama sekali
        $this->assertSame(0, Produk::count());
        $this->assertSame(0, StokItem::count());
        $this->assertSame(0, StokLog::count());
        $this->assertSame(0, StockMutationLog::count());
        $this->assertSame(0, JurnalAkuntansi::count());
    }

    public function test_import_tanpa_gudang_diizinkan(): void
    {
        // Baris master tanpa stok → tidak menyentuh cabang → bukan "lintas cabang"
        $baris = $this->baris('IMP-NOG-01', 0, 0);
        $baris['gudang_id'] = '';
        $path = $this->tulisCsv([$baris], 'tanpa-gudang.csv');

        $svc = app(ImportProdukService::class);
        $hasil = $svc->preview($path);

        $this->assertSame(1, $hasil['valid']);
        $this->assertSame(0, $hasil['invalid']);

        // Commit jalan normal (tanpa jurnal — tidak ada stok)
        $log = $this->buatImportLog();
        $out = $svc->commit($path, $log->id, $this->user->id);
        $this->assertSame(1, $out['sukses']);
        $this->assertSame(0.0, (float) $out['jurnal_nilai']);
        $this->assertSame(1, Produk::count());
        $this->assertSame(0, StokItem::count());
        $this->assertSame(0, JurnalAkuntansi::count());
    }

    // =============================================================
    // (4) import satu cabang: stok + jurnal konsisten
    // =============================================================

    public function test_import_satu_cabang_menghasilkan_stok_dan_jurnal_konsisten(): void
    {
        $rak = Rak::create(['gudang_id' => $this->gudangA->id, 'nama' => 'Rak A', 'kode' => 'RAK-IMA', 'zona' => 'A']);

        $path = $this->tulisCsv([
            $this->baris('IMP-A-01', $this->gudangA->id, 5, 50000),  // 250.000
            $this->baris('IMP-A-02', $this->gudangA->id, 4, 30000),  // 120.000
            $this->baris('IMP-A-03', $this->gudangA->id, 2, 10000),  //  20.000
        ], 'satu-cabang.csv');

        $log = $this->buatImportLog();
        $hasil = app(ImportProdukService::class)->commit($path, $log->id, $this->user->id);

        $this->assertSame(3, $hasil['sukses']);
        $this->assertSame(0, $hasil['gagal']);
        $this->assertSame(390000.0, round((float) $hasil['jurnal_nilai'], 2), 'Total = 250rb + 120rb + 20rb');

        // Stok
        $this->assertSame(11, (int) StokItem::where('gudang_id', $this->gudangA->id)->sum('jumlah'));
        $this->assertSame(0, (int) StokItem::where('gudang_id', $this->gudangB->id)->sum('jumlah'));
        $this->assertSame(3, StokLog::where('jenis', 'import')->count());
        $this->assertSame(3, StockMutationLog::where('sumber', 'import:excel')->count());

        // StockMutationLog sekarang punya pelaku
        $this->assertSame(3, StockMutationLog::whereNotNull('user_id')->count());

        // Jurnal agregat: 2 baris, balance, di cabang yang benar
        $noJurnal = 'JRL-IMP-'.now()->format('Ymd').'-IL'.str_pad((string) $log->id, 4, '0', STR_PAD_LEFT);
        $jurnal = JurnalAkuntansi::where('no_jurnal', $noJurnal)->get();
        $this->assertCount(2, $jurnal, 'Jurnal agregat = 2 baris (130-01 / 310-01)');
        $this->assertSame(390000.0, (float) $jurnal->sum('debit'));
        $this->assertSame(390000.0, (float) $jurnal->sum('kredit'), 'Jurnal wajib balance');
        $this->assertSame($this->cabangA->id, (int) $jurnal->first()->cabang_id, 'Jurnal harus di cabang A');
        $this->assertNotNull($rak->id);
    }

    public function test_import_gagal_total_tidak_ada_jurnal_tanpa_mutasi(): void
    {
        // JurnalService disuruh gagal → chunk rollback → tidak ada produk/stok/jurnal
        $mock = Mockery::mock(JurnalService::class);
        $mock->shouldReceive('generateNoJurnal')->andReturn('JRL-IMP-FAIL');
        $mock->shouldReceive('post')->andThrow(new \Exception('Akun COA tidak ditemukan: 130-01'));
        $this->app->instance(JurnalService::class, $mock);

        $path = $this->tulisCsv([
            $this->baris('IMP-F-01', $this->gudangA->id, 5, 50000),
            $this->baris('IMP-F-02', $this->gudangA->id, 3, 10000),
        ], 'jurnal-gagal.csv');

        $log = $this->buatImportLog();

        $gagal = null;
        try {
            app(ImportProdukService::class)->commit($path, $log->id, $this->user->id);
        } catch (\Throwable $e) {
            $gagal = $e;
        }

        $this->assertNotNull($gagal, 'Kegagalan jurnal tidak boleh ditelan');
        $this->assertStringContainsString('Akun COA tidak ditemukan', $gagal->getMessage());

        // Rollback chunk: tidak ada mutasi stok yatim tanpa jurnal
        $this->assertSame(0, StokItem::count());
        $this->assertSame(0, StokLog::count());
        $this->assertSame(0, StockMutationLog::count());
        $this->assertSame(0, Produk::count(), 'Produk baris yang gagal ikut rollback');
        $this->assertSame(0, JurnalAkuntansi::count());
    }

    public function test_baris_gagal_tidak_meninggalkan_produk_setengah_tertulis(): void
    {
        // Baris ke-2 duplikat SKU di dalam file → gagal → produknya tidak boleh ada
        $path = $this->tulisCsv([
            $this->baris('IMP-D-01', $this->gudangA->id, 5, 50000),
            $this->baris('IMP-D-01', $this->gudangA->id, 9, 90000),
        ], 'duplikat.csv');

        $log = $this->buatImportLog();
        $hasil = app(ImportProdukService::class)->commit($path, $log->id, $this->user->id);

        $this->assertSame(1, $hasil['sukses']);
        $this->assertSame(1, $hasil['gagal']);
        $this->assertSame(1, Produk::count(), 'Baris duplikat tidak boleh menyisakan produk');
        $this->assertSame(1, StokItem::count());
        $this->assertSame(5, (int) StokItem::sum('jumlah'));

        // Jurnal hanya mencakup baris yang sukses
        $noJurnal = 'JRL-IMP-'.now()->format('Ymd').'-IL'.str_pad((string) $log->id, 4, '0', STR_PAD_LEFT);
        $this->assertSame(250000.0, (float) JurnalAkuntansi::where('no_jurnal', $noJurnal)->sum('debit'));
    }

    public function test_import_ulang_tidak_membuat_jurnal_kedua(): void
    {
        $path = $this->tulisCsv([
            $this->baris('IMP-I-01', $this->gudangA->id, 5, 50000),
        ], 'idempoten.csv');

        $log1 = $this->buatImportLog();
        $hasil1 = app(ImportProdukService::class)->commit($path, $log1->id, $this->user->id);
        $this->assertSame(1, $hasil1['sukses']);

        $log2 = $this->buatImportLog();
        $hasil2 = app(ImportProdukService::class)->commit($path, $log2->id, $this->user->id);

        $this->assertSame(0, $hasil2['sukses'], 'SKU sudah ada → semua baris error');
        $this->assertSame(1, $hasil2['gagal']);
        $this->assertSame(5, (int) StokItem::sum('jumlah'), 'Stok tidak dobel');
        $this->assertSame(1, JurnalAkuntansi::count() / 2, 'Hanya 1 set jurnal (2 baris)');
    }
}
