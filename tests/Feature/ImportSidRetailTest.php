<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Akunting\Models\AkunCOA;
use App\Modules\Akunting\Models\JurnalAkuntansi;
use App\Modules\Pos\Models\HargaTier;
use App\Modules\Rbac\Models\Cabang;
use App\Modules\Wms\Jobs\ImportProdukExcelJob;
use App\Modules\Wms\Models\Brand;
use App\Modules\Wms\Models\Gudang;
use App\Modules\Wms\Models\ImportLog;
use App\Modules\Wms\Models\KategoriProduk;
use App\Modules\Wms\Models\Produk;
use App\Modules\Wms\Models\Rak;
use App\Modules\Wms\Models\SatuanUnit;
use App\Modules\Wms\Models\SidImportMap;
use App\Modules\Wms\Models\SkuVariant;
use App\Modules\Wms\Models\StockMutationLog;
use App\Modules\Wms\Models\StokItem;
use App\Modules\Wms\Models\Supplier;
use App\Modules\Wms\Services\ImportSidRetailService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use ZipArchive;

class ImportSidRetailTest extends TestCase
{
    use RefreshDatabase;

    private Cabang $cabang;

    private Gudang $gudangToko;

    private Gudang $gudangPusat;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cabang = Cabang::create([
            'kode' => 'CAB-TEST',
            'nama' => 'Cabang Test',
            'is_pusat' => true,
            'is_active' => true,
        ]);

        $this->gudangToko = Gudang::create([
            'cabang_id' => $this->cabang->id,
            'kode' => 'GD-TOKO',
            'nama' => 'Gudang Toko',
            'is_active' => true,
        ]);

        $this->gudangPusat = Gudang::create([
            'cabang_id' => $this->cabang->id,
            'kode' => 'GD-PUSAT',
            'nama' => 'Gudang Pusat',
            'is_active' => true,
        ]);

        $this->user = User::create([
            'name' => 'Admin Test',
            'email' => 'admin@test.local',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);

        // Pastikan akun COA 130-01 dan 310-01 ada
        AkunCOA::firstOrCreate(
            ['kode' => '130-01'],
            ['nama' => 'Persediaan Barang Dagang', 'tipe' => 'aset', 'kelompok' => 'persediaan', 'saldo_normal' => 'debit', 'is_active' => true]
        );
        AkunCOA::firstOrCreate(
            ['kode' => '310-01'],
            ['nama' => 'Modal Pemilik', 'tipe' => 'ekuitas', 'kelompok' => 'modal', 'saldo_normal' => 'kredit', 'is_active' => true]
        );
    }

    /**
     * Helper membuat dummy .xlsx menggunakan ZipArchive dan XML
     */
    private function createDummyXlsx(array $headers, array $rows, string $fileName): string
    {
        $dir = storage_path('app/testing');
        if (! is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        $filePath = $dir.'/'.$fileName;
        if (file_exists($filePath)) {
            unlink($filePath);
        }

        $zip = new ZipArchive;
        $zip->open($filePath, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        // [Content_Types].xml
        $contentTypes = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            .'<Default Extension="xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            .'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            .'<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            .'<Override PartName="/xl/sharedStrings.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sharedStrings+xml"/>'
            .'</Types>';
        $zip->addFromString('[Content_Types].xml', $contentTypes);

        // _rels/.rels
        $rels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            .'</Relationships>';
        $zip->addFromString('_rels/.rels', $rels);

        // xl/_rels/workbook.xml.rels
        $wbRels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            .'<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/sharedStrings" Target="sharedStrings.xml"/>'
            .'</Relationships>';
        $zip->addFromString('xl/_rels/workbook.xml.rels', $wbRels);

        // xl/workbook.xml
        $wb = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            .'<sheets><sheet name="Sheet1" sheetId="1" r:id="rId1"/></sheets>'
            .'</workbook>';
        $zip->addFromString('xl/workbook.xml', $wb);

        // Bangun sharedStrings dan sheet1.xml
        $sharedStrings = [];
        $stringLookup = [];

        $getStringIdx = function ($str) use (&$sharedStrings, &$stringLookup) {
            $str = (string) $str;
            if (! isset($stringLookup[$str])) {
                $stringLookup[$str] = count($sharedStrings);
                $sharedStrings[] = $str;
            }

            return $stringLookup[$str];
        };

        // Bangun baris XML
        $sheetData = '';
        $r = 1;

        // Header row
        $sheetData .= "<row r=\"{$r}\">";
        foreach ($headers as $c => $h) {
            $colLetter = chr(65 + $c);
            $idx = $getStringIdx($h);
            $sheetData .= "<c r=\"{$colLetter}{$r}\" t=\"s\"><v>{$idx}</v></c>";
        }
        $sheetData .= '</row>';

        // Data rows
        foreach ($rows as $row) {
            $r++;
            $sheetData .= "<row r=\"{$r}\">";
            foreach ($headers as $c => $h) {
                $colLetter = chr(65 + $c);
                $val = $row[$h] ?? '';
                if (is_numeric($val) && ! in_array($h, ['KODE_BARANG', 'KODE_BARCODE'], true)) {
                    $sheetData .= "<c r=\"{$colLetter}{$r}\"><v>{$val}</v></c>";
                } else {
                    $idx = $getStringIdx($val);
                    $sheetData .= "<c r=\"{$colLetter}{$r}\" t=\"s\"><v>{$idx}</v></c>";
                }
            }
            $sheetData .= '</row>';
        }

        $sheetXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            ."<sheetData>{$sheetData}</sheetData>"
            .'</worksheet>';
        $zip->addFromString('xl/worksheets/sheet1.xml', $sheetXml);

        // Shared strings xml
        $ssXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" count="'.count($sharedStrings).'" uniqueCount="'.count($sharedStrings).'">';
        foreach ($sharedStrings as $str) {
            $ssXml .= '<si><t>'.htmlspecialchars($str, ENT_XML1, 'UTF-8').'</t></si>';
        }
        $ssXml .= '</sst>';
        $zip->addFromString('xl/sharedStrings.xml', $ssXml);

        $zip->close();

        return $filePath;
    }

    public function test_preview_membaca_header_sid_retail_dengan_benar(): void
    {
        $headers = [
            'KODE_BARANG', 'KODE_BARCODE', 'NAMA', 'KATEGORI', 'SUB_KATEGORI',
            'SUPPLIER', 'SATUAN_1', 'HPP', 'HARGA_TOKO_1', 'HARGA_PARTAI_1',
            'HARGA_TOKO_2', 'TOKO', 'GUDANG', 'LOKASI', 'STOK_MIN', 'STOK_MAX',
        ];

        $rows = [
            [
                'KODE_BARANG' => 'BRG001',
                'KODE_BARCODE' => '8990001',
                'NAMA' => 'LCD Samsung A51',
                'KATEGORI' => 'Samsung',
                'SUB_KATEGORI' => 'LCD',
                'SUPPLIER' => 'Supplier Jaya',
                'SATUAN_1' => 'PCS',
                'HPP' => 250000,
                'HARGA_TOKO_1' => 350000,
                'HARGA_PARTAI_1' => 320000,
                'HARGA_TOKO_2' => 300000,
                'TOKO' => 5,
                'GUDANG' => 10,
                'LOKASI' => 'Rak A1',
                'STOK_MIN' => 2,
                'STOK_MAX' => 20,
            ],
        ];

        $filePath = $this->createDummyXlsx($headers, $rows, 'test_preview.xlsx');

        $service = app(ImportSidRetailService::class);
        $preview = $service->preview($filePath, $this->cabang->id, $this->gudangToko->id, $this->gudangPusat->id);

        $this->assertEquals(1, $preview['total_baris']);
        $this->assertEquals(1, $preview['valid']);
        $this->assertEquals(0, $preview['invalid']);
        $this->assertEquals(15, $preview['total_stok']); // 5 + 10
        $this->assertEquals(3750000, $preview['total_nilai_stok']); // 15 * 250.000
        $this->assertCount(1, $preview['items']);

        $item = $preview['items'][0];
        $this->assertEquals('BRG001', $item['kode_barang']);
        $this->assertEquals('LCD Samsung A51', $item['nama']);
        $this->assertEquals('Samsung', $item['kategori']);
        $this->assertEquals('LCD', $item['sub_kategori']);
        $this->assertTrue($item['valid']);
    }

    public function test_commit_membuat_master_data_stok_dan_jurnal_akuntansi(): void
    {
        $headers = [
            'KODE_BARANG', 'KODE_BARCODE', 'NAMA', 'KATEGORI', 'SUB_KATEGORI',
            'SUPPLIER', 'SATUAN_1', 'HPP', 'HARGA_TOKO_1', 'HARGA_PARTAI_1',
            'HARGA_TOKO_2', 'TOKO', 'GUDANG', 'LOKASI', 'STOK_MIN', 'STOK_MAX',
        ];

        $rows = [
            [
                'KODE_BARANG' => 'BRG-IP11',
                'KODE_BARCODE' => '8991111',
                'NAMA' => 'Baterai iPhone 11',
                'KATEGORI' => 'Apple',
                'SUB_KATEGORI' => 'Baterai',
                'SUPPLIER' => 'PT Sumber Rejeki',
                'SATUAN_1' => 'PCS',
                'HPP' => 120000,
                'HARGA_TOKO_1' => 200000,
                'HARGA_PARTAI_1' => 180000,
                'HARGA_TOKO_2' => 170000,
                'TOKO' => 4,
                'GUDANG' => 6,
                'LOKASI' => 'Rak B2',
                'STOK_MIN' => 3,
                'STOK_MAX' => 15,
            ],
        ];

        $filePath = $this->createDummyXlsx($headers, $rows, 'test_commit.xlsx');

        $importLog = ImportLog::create([
            'tipe' => 'sid_retail',
            'nama_file' => 'test_commit.xlsx',
            'total_baris' => 0,
            'sukses' => 0,
            'gagal' => 0,
            'status' => 'proses',
        ]);

        $service = app(ImportSidRetailService::class);
        $result = $service->commit(
            $filePath,
            $importLog->id,
            $this->cabang->id,
            $this->gudangToko->id,
            $this->gudangPusat->id,
            $this->user->id
        );

        $this->assertEquals(1, $result['total_baris']);
        $this->assertEquals(1, $result['sukses']);
        $this->assertEquals(0, $result['gagal']);
        $this->assertEquals(1200000, $result['jurnal_nilai']); // (4 + 6) * 120.000

        // 1. Verifikasi Brand & KategoriProduk & Supplier & SatuanUnit
        $brand = Brand::where('nama', 'Apple')->first();
        $this->assertNotNull($brand);

        $kategori = KategoriProduk::where('nama', 'Baterai')->first();
        $this->assertNotNull($kategori);

        $supplier = Supplier::where('nama', 'PT Sumber Rejeki')->first();
        $this->assertNotNull($supplier);

        $satuan = SatuanUnit::where('kode', 'pcs')->first();
        $this->assertNotNull($satuan);

        // 2. Verifikasi Produk & SkuVariant
        $produk = Produk::where('nama', 'Baterai iPhone 11')->first();
        $this->assertNotNull($produk);
        $this->assertEquals($brand->id, $produk->brand_id);
        $this->assertEquals($kategori->id, $produk->kategori_id);
        $this->assertEquals(120000, $produk->harga_beli);
        $this->assertEquals(200000, $produk->harga_jual_retail);
        $this->assertEquals(3, $produk->min_stock);
        $this->assertEquals(15, $produk->reorder_point);

        $variant = SkuVariant::where('sku', 'BRG-IP11')->first();
        $this->assertNotNull($variant);
        $this->assertEquals($produk->id, $variant->produk_id);
        $this->assertEquals('8991111', $variant->barcode);

        // 3. Verifikasi SidImportMap
        $this->assertEquals($variant->id, SidImportMap::getId('BRG-IP11', 'barang', 'sku_variant'));
        $this->assertEquals($produk->id, SidImportMap::getId('BRG-IP11', 'barang', 'produk'));

        // 4. Verifikasi HargaTier
        $tiers = HargaTier::where('produk_id', $produk->id)->get();
        $this->assertCount(3, $tiers);
        $retailTier = $tiers->firstWhere('tipe_konsumen', 'retail');
        $this->assertEquals(200000, (float) $retailTier->harga);
        $resellerTier = $tiers->firstWhere('tipe_konsumen', 'reseller');
        $this->assertEquals(180000, (float) $resellerTier->harga);
        $agenTier = $tiers->firstWhere('tipe_konsumen', 'agen');
        $this->assertEquals(170000, (float) $agenTier->harga);

        // 5. Verifikasi Rak
        $rak = Rak::where('nama', 'Rak B2')->first();
        $this->assertNotNull($rak);
        $this->assertEquals($this->gudangToko->id, $rak->gudang_id);

        // 6. Verifikasi Stok & Mutasi
        $stokToko = StokItem::where('produk_id', $produk->id)
            ->where('sku_variant_id', $variant->id)
            ->where('gudang_id', $this->gudangToko->id)
            ->first();
        $this->assertNotNull($stokToko);
        $this->assertEquals(4, $stokToko->jumlah);
        $this->assertEquals($rak->id, $stokToko->rak_id);

        $stokGudang = StokItem::where('produk_id', $produk->id)
            ->where('sku_variant_id', $variant->id)
            ->where('gudang_id', $this->gudangPusat->id)
            ->first();
        $this->assertNotNull($stokGudang);
        $this->assertEquals(6, $stokGudang->jumlah);

        $mutationLogs = StockMutationLog::where('produk_id', $produk->id)->get();
        $this->assertCount(2, $mutationLogs);
        $this->assertEquals('import:sid_retail', $mutationLogs[0]->sumber);

        // 7. Verifikasi Jurnal Akuntansi
        $padLogId = str_pad((string) $importLog->id, 4, '0', STR_PAD_LEFT);
        $expectedNoJurnal = 'JRL-SID-'.now()->format('Ymd').'-IL'.$padLogId.'-C1';
        $jurnalEntries = JurnalAkuntansi::where('no_jurnal', $expectedNoJurnal)->get();
        $this->assertCount(2, $jurnalEntries);
        $this->assertEquals(1200000, (float) $jurnalEntries->sum('debit'));
        $this->assertEquals(1200000, (float) $jurnalEntries->sum('kredit'));

        // 8. Test Idempotensi: jalankan commit lagi, tidak boleh error dan tidak duplikasi data
        $result2 = $service->commit(
            $filePath,
            $importLog->id,
            $this->cabang->id,
            $this->gudangToko->id,
            $this->gudangPusat->id,
            $this->user->id
        );
        $this->assertEquals(1, $result2['sukses']);
        $this->assertEquals(1, Produk::where('nama', 'Baterai iPhone 11')->count());
        $this->assertEquals(1, SkuVariant::where('sku', 'BRG-IP11')->count());
        // Jurnal tidak diduplikasi (tetap 2 baris: debit 130-01 dan kredit 310-01)
        $this->assertEquals(2, JurnalAkuntansi::where('no_jurnal', $expectedNoJurnal)->count());
    }

    public function test_import_job_mendukung_format_sid_retail(): void
    {
        $headers = [
            'KODE_BARANG', 'KODE_BARCODE', 'NAMA', 'KATEGORI', 'SUB_KATEGORI',
            'SUPPLIER', 'SATUAN_1', 'HPP', 'HARGA_TOKO_1', 'HARGA_PARTAI_1',
            'HARGA_TOKO_2', 'TOKO', 'GUDANG', 'LOKASI', 'STOK_MIN', 'STOK_MAX',
        ];

        $rows = [
            [
                'KODE_BARANG' => 'JOB-001',
                'KODE_BARCODE' => '8999901',
                'NAMA' => 'Konektor Cas Type-C',
                'KATEGORI' => 'Universal',
                'SUB_KATEGORI' => 'Konektor',
                'SUPPLIER' => '',
                'SATUAN_1' => 'PCS',
                'HPP' => 5000,
                'HARGA_TOKO_1' => 15000,
                'HARGA_PARTAI_1' => 10000,
                'HARGA_TOKO_2' => 8000,
                'TOKO' => 20,
                'GUDANG' => 0,
                'LOKASI' => '',
                'STOK_MIN' => 5,
                'STOK_MAX' => 50,
            ],
        ];

        $filePath = $this->createDummyXlsx($headers, $rows, 'test_job.xlsx');

        $importLog = ImportLog::create([
            'tipe' => 'sid_retail',
            'nama_file' => 'test_job.xlsx',
            'total_baris' => 0,
            'sukses' => 0,
            'gagal' => 0,
            'status' => 'proses',
        ]);

        ImportProdukExcelJob::dispatchSync(
            $importLog->id,
            $filePath,
            $this->user->id,
            'sid_retail',
            $this->cabang->id,
            $this->gudangToko->id,
            $this->gudangPusat->id
        );

        $importLog->refresh();
        $this->assertEquals('selesai', $importLog->status);
        $this->assertEquals(1, $importLog->sukses);
        $this->assertEquals(0, $importLog->gagal);

        $this->assertDatabaseHas('produk', [
            'nama' => 'Konektor Cas Type-C',
        ]);
        $this->assertDatabaseHas('sku_variants', [
            'sku' => 'JOB-001',
        ]);
    }
}
