<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Rbac\Models\Cabang;
use App\Modules\Wms\Models\Brand;
use App\Modules\Wms\Models\Gudang;
use App\Modules\Wms\Models\ImportLog;
use App\Modules\Wms\Models\KategoriProduk;
use App\Modules\Wms\Models\KualitasProduk;
use App\Modules\Wms\Models\Produk;
use App\Modules\Wms\Models\Rak;
use App\Modules\Wms\Models\SkuVariant;
use App\Modules\Wms\Services\ImportProdukService;
use App\Modules\Wms\Services\ImportSidRetailService;
use Database\Seeders\AkunCoaSeeder;
use Database\Seeders\CabangSeeder;
use Database\Seeders\KategoriProdukSeeder;
use Database\Seeders\MasterDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ImportMinimalBlankResolutionTest extends TestCase
{
    use RefreshDatabase;

    private Cabang $cabang;

    private Gudang $gudang;

    private Rak $rak01;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(AkunCoaSeeder::class);
        $this->seed(MasterDataSeeder::class);
        $this->seed(KategoriProdukSeeder::class);
        $this->seed(CabangSeeder::class);

        $this->cabang = Cabang::where('kode', 'CBG-01')->firstOrFail();
        $this->gudang = Gudang::where('cabang_id', $this->cabang->id)->firstOrFail();
        $this->rak01 = Rak::where('kode', 'RAK-01')->where('gudang_id', $this->gudang->id)->firstOrFail();

        $this->admin = User::factory()->create();
    }

    public function test_master_data_and_kategori_seeder_have_oem_and_umum(): void
    {
        $this->assertTrue(Brand::where('nama', 'OEM')->exists(), 'Brand OEM harus terdaftar di master data');
        $this->assertTrue(KategoriProduk::where('nama', 'Umum')->whereNull('parent_id')->exists(), 'Kategori root Umum harus terdaftar di KategoriProdukSeeder');
    }

    public function test_import_produk_resolves_blank_columns_to_oem_original_umum_and_rak01(): void
    {
        $service = app(ImportProdukService::class);

        $csv = implode("\n", [
            'sku;nama;satuan;kategori;sub_kategori;brand;kualitas;gudang;rak;harga_beli;harga_jual;stok_awal',
            'BLANK-SKU-001;Minimal Blank Item;pcs;;;;;Gudang Utama;;10000;15000;5',
        ]);

        $filePath = sys_get_temp_dir().'/test_import_blank_'.uniqid().'.csv';
        file_put_contents($filePath, $csv);

        try {
            $log = ImportLog::create([
                'tipe' => 'produk_excel',
                'nama_file' => 'test_blank.csv',
                'status' => 'proses',
                'user_id' => $this->admin->id,
            ]);

            session(['cabang_id' => $this->cabang->id]);

            $hasil = $service->commit($filePath, $log->id, $this->admin->id);

            $this->assertSame(1, $hasil['sukses']);
            $this->assertSame(0, $hasil['gagal']);

            $variant = SkuVariant::where('sku', 'BLANK-SKU-001')->firstOrFail();
            $produk = Produk::findOrFail($variant->produk_id);

            // Verifikasi brand fallback OEM
            $oemBrand = Brand::where('nama', 'OEM')->firstOrFail();
            $this->assertSame($oemBrand->id, $produk->brand_id);

            // Verifikasi kualitas fallback Original
            $originalKualitas = KualitasProduk::where('nama', 'Original')->firstOrFail();
            $this->assertSame($originalKualitas->id, $produk->kualitas_id);

            // Verifikasi kategori fallback Umum (bukan NULL)
            $umumKategori = KategoriProduk::where('nama', 'Umum')->whereNull('parent_id')->firstOrFail();
            $this->assertSame($umumKategori->id, $produk->kategori_id);
            $this->assertSame('Umum', $produk->kategori);

            // Verifikasi rak fallback menggunakan RAK-01 / Rak Utama yang sudah ada
            $this->assertDatabaseHas('stok_items', [
                'produk_id' => $produk->id,
                'sku_variant_id' => $variant->id,
                'gudang_id' => $this->gudang->id,
                'rak_id' => $this->rak01->id,
                'jumlah' => 5,
            ]);
        } finally {
            @unlink($filePath);
        }
    }

    public function test_import_sid_retail_resolves_default_kualitas_to_original(): void
    {
        $headers = [
            'KODE_BARANG', 'KODE_BARCODE', 'NAMA', 'KATEGORI', 'SUB_KATEGORI',
            'SUPPLIER', 'SATUAN_1', 'HPP', 'HARGA_TOKO_1', 'HARGA_PARTAI_1',
            'HARGA_TOKO_2', 'TOKO', 'GUDANG', 'LOKASI', 'STOK_MIN', 'STOK_MAX',
        ];

        $rows = [
            [
                'KODE_BARANG' => 'SID-BLANK-01',
                'KODE_BARCODE' => '89999901',
                'NAMA' => 'Item SID Minimal',
                'KATEGORI' => '',
                'SUB_KATEGORI' => '',
                'SUPPLIER' => '',
                'SATUAN_1' => 'PCS',
                'HPP' => 50000,
                'HARGA_TOKO_1' => 75000,
                'HARGA_PARTAI_1' => 70000,
                'HARGA_TOKO_2' => 68000,
                'TOKO' => 2,
                'GUDANG' => 0,
                'LOKASI' => '',
                'STOK_MIN' => 1,
                'STOK_MAX' => 10,
            ],
        ];

        $tmpXlsx = sys_get_temp_dir().'/sid_blank_test_'.uniqid().'.xlsx';

        // Buat dummy XLSX sederhana menggunakan ZipArchive
        $zip = new \ZipArchive;
        $zip->open($tmpXlsx, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);

        $sheetXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
        $sheetXml .= '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>';

        // Header
        $sheetXml .= '<row r="1">';
        foreach ($headers as $c => $h) {
            $colLetter = chr(65 + $c);
            $sheetXml .= '<c r="'.$colLetter.'1" t="inlineStr"><is><t>'.$h.'</t></is></c>';
        }
        $sheetXml .= '</row>';

        // Row 1
        $sheetXml .= '<row r="2">';
        foreach ($headers as $c => $h) {
            $colLetter = chr(65 + $c);
            $val = $rows[0][$h] ?? '';
            $sheetXml .= '<c r="'.$colLetter.'2" t="inlineStr"><is><t>'.htmlspecialchars((string) $val).'</t></is></c>';
        }
        $sheetXml .= '</row></sheetData></worksheet>';

        $zip->addFromString('xl/worksheets/sheet1.xml', $sheetXml);
        $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheets><sheet name="Sheet1" sheetId="1" r:id="rId1" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"/></sheets></workbook>');
        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
        $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/></Relationships>');
        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/></Types>');
        $zip->close();

        try {
            $log = ImportLog::create([
                'tipe' => 'sid_retail',
                'nama_file' => 'test_sid_blank.xlsx',
                'status' => 'proses',
                'user_id' => $this->admin->id,
            ]);

            $service = app(ImportSidRetailService::class);
            $hasil = $service->commit(
                $tmpXlsx,
                $log->id,
                $this->cabang->id,
                $this->gudang->id,
                null,
                $this->admin->id
            );

            $this->assertSame(1, $hasil['sukses']);

            $variant = SkuVariant::where('sku', 'SID-BLANK-01')->firstOrFail();
            $produk = Produk::findOrFail($variant->produk_id);

            $originalKualitas = KualitasProduk::where('nama', 'Original')->firstOrFail();
            $this->assertSame($originalKualitas->id, $produk->kualitas_id);
        } finally {
            @unlink($tmpXlsx);
        }
    }
}
