<?php

namespace Tests\Feature;

use App\Modules\Akunting\Models\JurnalAkuntansi;
use App\Modules\Crm\Models\Pelanggan;
use App\Modules\Crm\Services\PelangganService;
use App\Modules\Notifikasi\Models\NotifikasiKeluar;
use App\Modules\Pos\Models\HargaTier;
use App\Modules\Rbac\Models\Cabang;
use App\Modules\Wms\Jobs\ImportProdukExcelJob;
use App\Modules\Wms\Models\Brand;
use App\Modules\Wms\Models\Gudang;
use App\Modules\Wms\Models\ImportLog;
use App\Modules\Wms\Models\KualitasProduk;
use App\Modules\Wms\Models\Produk;
use App\Modules\Wms\Models\Rak;
use App\Modules\Wms\Models\SkuVariant;
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
use Tests\TestCase;

/**
 * VERIFIKASI TEMPORER [T-44 + T-43] — dihapus setelah lulus.
 * 1. resolveHarga retail/reseller/agen dari HargaTier baris seed.
 * 2. Import xlsx: preview (dry-run) → commit via job/queue sync → data lengkap
 *    (produk+sku_variant+harga_tier+stok_items+StokLog+SML import:excel),
 *    jurnal stok awal 130-01/310-01 balance, notifikasi_keluar terkirim.
 * 3. Idempoten: commit ulang → semua baris error (SKU sudah ada), tidak dobel.
 */
class WmsImportVerifyTest extends TestCase
{
    use RefreshDatabase;

    private array $kolom = [
        'sku', 'nama', 'barcode', 'satuan', 'kategori', 'tipe_hp', 'brand',
        'kualitas', 'harga_beli', 'harga_jual', 'harga_reseller', 'harga_agen',
        'stok_awal', 'gudang_id', 'rak_id', 'kompatibilitas_hp', 'foto_url',
    ];

    private function rowsUji(int $gudangId, int $rakId): array
    {
        $rows = [];
        // 10 baris VALID
        for ($i = 1; $i <= 10; $i++) {
            $rows[] = [
                'sku' => "VER-{$i}-SKU", 'nama' => "Produk Verifikasi {$i}", 'barcode' => "89990000{$i}001",
                'satuan' => 'pcs', 'kategori' => 'Verifikasi', 'tipe_hp' => 'Apple iPhone 13',
                'brand' => 'TestBrand', 'kualitas' => 'Grade A',
                'harga_beli' => 50000 + $i * 1000, 'harga_jual' => 100000 + $i * 1000,
                'harga_reseller' => 80000 + $i * 1000, 'harga_agen' => 75000 + $i * 1000,
                'stok_awal' => $i, 'gudang_id' => $gudangId, 'rak_id' => $rakId,
                'kompatibilitas_hp' => json_encode([['merk' => 'Apple', 'model' => 'iPhone 13']]),
                'foto_url' => '',
            ];
        }
        // 4 baris INVALID: duplikat SKU, satuan tak dikenal, harga negatif, stok>0 tanpa gudang.
        // NB: Excel::store CSV menulis array POSITIONAL — baris sparse harus dipadding
        // sepanjang $kolom supaya tiap nilai jatuh di kolom heading yang benar.
        $pad = fn (array $over): array => array_merge(array_fill_keys($this->kolom, ''), $over);
        $rows[] = $pad(['sku' => 'VER-1-SKU', 'nama' => 'Duplikat SKU', 'barcode' => 'X1', 'satuan' => 'pcs', 'harga_beli' => 100, 'harga_jual' => 200, 'stok_awal' => 0, 'gudang_id' => $gudangId]);
        $rows[] = $pad(['sku' => 'VER-BAD-SATUAN', 'nama' => 'Satuan Salah', 'barcode' => 'X2', 'satuan' => 'galon-xyz', 'harga_beli' => 100, 'harga_jual' => 200, 'stok_awal' => 0]);
        $rows[] = $pad(['sku' => 'VER-NEGATIF', 'nama' => 'Harga Negatif', 'barcode' => 'X3', 'satuan' => 'pcs', 'harga_beli' => -50, 'harga_jual' => 200, 'stok_awal' => 0]);
        $rows[] = $pad(['sku' => 'VER-TANPA-GUDANG', 'nama' => 'Stok Tanpa Gudang', 'barcode' => 'X4', 'satuan' => 'pcs', 'harga_beli' => 100, 'harga_jual' => 200, 'stok_awal' => 5]);

        return $rows;
    }

    public function test_resolve_harga_dan_import_excel_lengkap_dan_idempoten(): void
    {
        $this->seed(AkunCoaSeeder::class);

        $cabang = Cabang::create(['nama' => 'Pusat', 'kode' => 'CBG-01', 'is_active' => true]);
        $gudang = Gudang::create(['cabang_id' => $cabang->id, 'nama' => 'Gudang 1', 'kode' => 'GDG-01', 'is_active' => true]);
        $rak = Rak::create(['gudang_id' => $gudang->id, 'nama' => 'Rak A', 'kode' => 'RAK-A', 'zona' => 'A']);

        // ===== [T-44] resolveHarga terhadap baris seed harga_tier =====
        $produkSeed = Produk::create([
            'nama' => 'LCD Seed', 'slug' => 'lcd-seed', 'kategori' => 'LCD',
            'harga_beli' => 500000, 'harga_jual_retail' => 800000,
        ]);
        // Seed baris harga_tier per tipe (simulasi migrasi)
        foreach (['retail', 'reseller', 'agen'] as $tipe) {
            HargaTier::create([
                'produk_id' => $produkSeed->id, 'tipe_konsumen' => $tipe,
                'nominal_tetap' => 800000, 'harga' => 800000,
                'is_reseller' => $tipe === 'reseller',
            ]);
        }

        $svc = app(PelangganService::class);

        $retail = Pelanggan::create(['nama' => 'R', 'telepon' => '0810001', 'tipe_konsumen' => 'retail', 'total_belanja_12bulan' => 0, 'poin_loyalty' => 0]);
        $this->assertSame(800000.0, (float) $svc->resolveHarga($produkSeed, $retail)['harga']);

        $reseller = Pelanggan::create(['nama' => 'RS', 'telepon' => '0810002', 'tipe_konsumen' => 'reseller', 'is_reseller' => true, 'total_belanja_12bulan' => 0, 'poin_loyalty' => 0]);
        $this->assertSame(800000.0, (float) $svc->resolveHarga($produkSeed, $reseller)['harga']);

        $agen = Pelanggan::create(['nama' => 'AG', 'telepon' => '0810003', 'tipe_konsumen' => 'agen', 'total_belanja_12bulan' => 0, 'poin_loyalty' => 0]);
        $this->assertSame(800000.0, (float) $svc->resolveHarga($produkSeed, $agen)['harga']);

        // Harga tier kustom reseller (nominal di bawah retail) harus menang
        HargaTier::updateOrCreate(
            ['produk_id' => $produkSeed->id, 'sku_variant_id' => null, 'tier_membership_id' => null, 'tipe_konsumen' => 'reseller'],
            ['nominal_tetap' => 650000, 'harga' => 650000, 'is_reseller' => true]
        );
        $this->assertSame(650000.0, (float) $svc->resolveHarga($produkSeed, $reseller)['harga']);

        // ===== [T-43] Preview + commit =====
        $kolom = $this->kolom;
        $baris = $this->rowsUji($gudang->id, $rak->id);

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

        $file = 'import-tmp/verify-import.csv';
        Excel::store($export, $file, null, \Maatwebsite\Excel\Excel::CSV);
        // Excel::store memakai default disk ('local' → root storage/app/private) — baca dari lokasi yg sama
        $path = Storage::disk('local')->path($file);

        $importSvc = app(ImportProdukService::class);

        // Preview: 10 valid, 4 invalid, pesan error jelas
        $preview = $importSvc->preview($path);
        $this->assertSame(14, $preview['total_baris']);
        $this->assertSame(10, $preview['valid']);
        $this->assertSame(4, $preview['invalid']);
        $pesanSemua = collect($preview['error_rows'])->pluck('errors')->flatten()->implode(' ');
        $this->assertStringContainsString('duplikat', $pesanSemua);
        $this->assertStringContainsString('tidak terdaftar di satuan_unit', $pesanSemua);
        $this->assertStringContainsString('tidak boleh negatif', $pesanSemua);
        $this->assertStringContainsString('gudang_id wajib diisi', $pesanSemua);

        // Commit lewat JOB (QUEUE_CONNECTION=sync) — notifikasi "Import Produk Selesai"
        // hanya dikirim dari ImportProdukExcelJob, bukan dari service.
        // Salin file dulu: job meng-unlink file setelah sukses, run-2 butuh file kedua.
        $pathUlang = storage_path('app/import-tmp/verify-rerun.csv');
        if (! is_dir(dirname($pathUlang))) {
            mkdir(dirname($pathUlang), 0775, true);
        }
        copy($path, $pathUlang);
        $logImp = ImportLog::create([
            'tipe' => 'produk_excel', 'nama_file' => 'verify-import.csv',
            'total_baris' => 0, 'sukses' => 0, 'gagal' => 0, 'status' => 'proses',
        ]);
        ImportProdukExcelJob::dispatch($logImp->id, $path, null);
        $logImp->refresh();
        $this->assertSame('selesai', $logImp->status);
        $this->assertSame(10, $logImp->sukses);
        $this->assertSame(4, $logImp->gagal);

        // Data lengkap: produk + sku_variant + harga_tier + tipe_hp pivot + stok
        $this->assertSame(10, Produk::where('nama', 'like', 'Produk Verifikasi%')->count());
        $this->assertSame(10, SkuVariant::where('sku', 'like', 'VER-%')->count());
        $this->assertSame(30, HargaTier::whereHas('produk', fn ($q) => $q->where('nama', 'like', 'Produk Verifikasi%'))->count()); // 10 × 3 tipe
        $p1 = Produk::where('nama', 'Produk Verifikasi 1')->first();
        $this->assertNotNull($p1);
        $this->assertSame((float) (80000 + 1 * 1000), (float) HargaTier::where('produk_id', $p1->id)->where('tipe_konsumen', 'reseller')->value('nominal_tetap'));
        $this->assertTrue($p1->tipeHps()->exists());
        $this->assertSame('TestBrand', Brand::where('nama', 'TestBrand')->value('nama'));
        $this->assertSame('Grade A', KualitasProduk::where('nama', 'Grade A')->value('nama'));

        // Stok: 1+2+...+10 = 55 di gudang; StokLog + SML import:excel
        $this->assertSame(55, StokItem::where('gudang_id', $gudang->id)->sum('jumlah'));
        $this->assertSame(10, StokLog::where('jenis', 'import')->count());
        $this->assertSame(10, StockMutationLog::where('sumber', 'import:excel')->count());
        $sml = StockMutationLog::where('sumber', 'import:excel')->where('delta', 1)->first();
        $this->assertNotNull($sml);

        // Jurnal stok awal: balance (SUM debit === SUM kredit) pada no_jurnal import
        $noJurnal = 'JRL-IMP-'.now()->format('Ymd').'-IL0001';
        $jurnal = JurnalAkuntansi::where('no_jurnal', $noJurnal)->get();
        $this->assertSame(2, $jurnal->count());
        $debit = (float) $jurnal->sum('debit');
        $kredit = (float) $jurnal->sum('kredit');
        $this->assertGreaterThan(0, $debit);
        $this->assertSame($debit, $kredit, 'Jurnal import harus balance (debit = kredit)');

        // Notifikasi terkirim (jalur queue sync)
        $notif = NotifikasiKeluar::where('judul', 'Import Produk Selesai')->latest()->first();
        $this->assertNotNull($notif);
        $this->assertSame(10, $notif->payload['sukses']);
        $this->assertSame(4, $notif->payload['gagal']);

        // ===== Idempotensi: run ulang → 0 sukses, 14 gagal, tidak dobel =====
        $hasil2 = $importSvc->commit($pathUlang, 2, null);
        $this->assertSame(0, $hasil2['sukses']);
        $this->assertSame(14, $hasil2['gagal']);
        $this->assertSame(10, Produk::where('nama', 'like', 'Produk Verifikasi%')->count());
        $this->assertSame(55, StokItem::where('gudang_id', $gudang->id)->sum('jumlah'));
        $this->assertSame(10, StockMutationLog::where('sumber', 'import:excel')->count());
        // Jurnal kedua (IL0002) tidak dibuat karena tidak ada stok baru
        $this->assertSame(0, JurnalAkuntansi::where('no_jurnal', 'LIKE', '%IL0002')->count());

        // ImportLog rows dibuat di controller/livewire — verifikasi model + cast
        $log = ImportLog::create([
            'tipe' => 'produk_excel', 'nama_file' => 'verify.xlsx',
            'total_baris' => 14, 'sukses' => 10, 'gagal' => 4, 'status' => 'selesai',
            'detail' => [['baris' => 2, 'sku' => 'VER-1-SKU', 'status' => 'ok']],
        ]);
        $this->assertSame('selesai', $log->refresh()->status);
        $this->assertCount(1, $log->detail);
    }
}
