<?php

namespace Tests\Unit;

use App\Modules\Wms\Exports\BulkFotoSkippedExport;
use App\Modules\Wms\Jobs\ImportFotoMasalJob;
use App\Modules\Wms\Models\ImportLog;
use App\Modules\Wms\Models\Produk;
use App\Modules\Wms\Models\SkuVariant;
use App\Modules\Wms\Services\BulkProductImageImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BulkProductImageImportTest extends TestCase
{
    use RefreshDatabase;

    protected BulkProductImageImportService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(BulkProductImageImportService::class);
        Storage::fake('public');
    }

    public function test_parse_sku_from_various_filename_formats(): void
    {
        // 1. Exact match
        $this->assertEquals('LCD-IP13-ORI', $this->service->parseSkuFromFilename('LCD-IP13-ORI.jpg'));
        $this->assertEquals('BAT-SAM-A52', $this->service->parseSkuFromFilename('BAT-SAM-A52.png'));

        // 2. Underscore sequence
        $this->assertEquals('LCD-IP13-ORI', $this->service->parseSkuFromFilename('LCD-IP13-ORI_1.jpg'));
        $this->assertEquals('LCD-IP13-ORI', $this->service->parseSkuFromFilename('LCD-IP13-ORI_2.webp'));

        // 3. Dash sequence
        $this->assertEquals('LCD-IP13-ORI', $this->service->parseSkuFromFilename('LCD-IP13-ORI-1.jpeg'));
        $this->assertEquals('LCD-IP13-ORI', $this->service->parseSkuFromFilename('LCD-IP13-ORI-3.jpg'));

        // 4. Bracket sequence
        $this->assertEquals('FLX-CHG-OPP', $this->service->parseSkuFromFilename('FLX-CHG-OPP (1).jpg'));
        $this->assertEquals('FLX-CHG-OPP', $this->service->parseSkuFromFilename('FLX-CHG-OPP (2).png'));

        // 5. Main / Cover / Depan suffix
        $this->assertEquals('SPK-IPX', $this->service->parseSkuFromFilename('SPK-IPX-main.jpg'));
        $this->assertEquals('SPK-IPX', $this->service->parseSkuFromFilename('SPK-IPX_cover.png'));
        $this->assertEquals('SPK-IPX', $this->service->parseSkuFromFilename('SPK-IPX_depan.webp'));

        // 6. Subfolder as SKU
        $this->assertEquals('LCD-IP13-PRO', $this->service->parseSkuFromFilename('front.jpg', 'LCD-IP13-PRO/front.jpg'));
        $this->assertEquals('LCD-IP13-PRO', $this->service->parseSkuFromFilename('detail.png', 'LCD-IP13-PRO/detail.png'));
    }

    public function test_is_primary_designation(): void
    {
        $this->assertTrue($this->service->isPrimaryDesignation('LCD-IP13_main.jpg'));
        $this->assertTrue($this->service->isPrimaryDesignation('LCD-IP13-cover.png'));
        $this->assertTrue($this->service->isPrimaryDesignation('LCD-IP13_1.webp'));
        $this->assertTrue($this->service->isPrimaryDesignation('LCD-IP13-1.jpg'));
        $this->assertFalse($this->service->isPrimaryDesignation('LCD-IP13_2.jpg'));
        $this->assertFalse($this->service->isPrimaryDesignation('LCD-IP13-3.png'));
    }

    public function test_prescan_separates_matched_and_skipped(): void
    {
        // Buat produk di DB
        $p1 = Produk::create([
            'nama' => 'Produk Tes 01',
            'slug' => 'produk-tes-01-'.uniqid(),
            'kategori' => 'Sparepart',
            'kondisi' => 'baru',
            'satuan' => 'pcs',
            'harga_beli' => 10000,
            'harga_jual_retail' => 20000,
            'is_active' => true,
        ]);
        SkuVariant::create([
            'produk_id' => $p1->id,
            'sku' => 'SKU-EXIST-01',
            'nama_varian' => 'Standar',
            'is_active' => true,
        ]);

        $p2 = Produk::create([
            'nama' => 'Produk Tes 02',
            'slug' => 'produk-tes-02-'.uniqid(),
            'kategori' => 'Sparepart',
            'kondisi' => 'baru',
            'satuan' => 'pcs',
            'harga_beli' => 15000,
            'harga_jual_retail' => 25000,
            'is_active' => true,
        ]);
        SkuVariant::create([
            'produk_id' => $p2->id,
            'sku' => 'SKU-EXIST-02',
            'nama_varian' => 'Standar',
            'is_active' => true,
        ]);

        $files = [
            ['name' => 'SKU-EXIST-01.jpg', 'size' => 1024],
            ['name' => 'SKU-EXIST-02_1.png', 'size' => 2048],
            ['name' => 'SKU-EXIST-01.heic', 'size' => 3072], // Format HEIC/HEIF
            ['name' => 'SKU-EXIST-02.avif', 'size' => 1500], // Format AVIF
            ['name' => 'SKU-NOTFOUND-99.jpg', 'size' => 512],
            ['name' => 'document.pdf', 'size' => 4096], // Invalid format
        ];

        $res = $this->service->preScanFiles($files);

        $this->assertEquals(6, $res['total']);
        $this->assertEquals(4, $res['matched_count']);
        $this->assertEquals(2, $res['skipped_count']);

        $matchedSkus = array_column($res['matched'], 'sku');
        $this->assertContains('SKU-EXIST-01', $matchedSkus);
        $this->assertContains('SKU-EXIST-02', $matchedSkus);

        $skippedFilenames = array_column($res['skipped'], 'filename');
        $this->assertContains('SKU-NOTFOUND-99.jpg', $skippedFilenames);
        $this->assertContains('document.pdf', $skippedFilenames);
    }

    public function test_process_and_attach_image_to_product(): void
    {
        $produk = Produk::create([
            'nama' => 'Produk Uji Gambar',
            'slug' => 'produk-uji-gambar-'.uniqid(),
            'kategori' => 'Sparepart',
            'kondisi' => 'baru',
            'satuan' => 'pcs',
            'harga_beli' => 10000,
            'harga_jual_retail' => 20000,
            'is_active' => true,
            'gambar' => null,
            'foto' => null,
        ]);
        SkuVariant::create([
            'produk_id' => $produk->id,
            'sku' => 'TEST-SKU-IMAGE',
            'nama_varian' => 'Standar',
            'is_active' => true,
        ]);

        $fakeImage = UploadedFile::fake()->image('test_product.jpg', 600, 600);

        // 1. Test append mode
        $res = $this->service->processAndAttach($fakeImage, $produk->id, 'append', true);

        $this->assertTrue($res['success']);

        $produk->refresh();
        $this->assertNotNull($produk->gambar);
        $this->assertIsArray($produk->foto);
        $this->assertCount(1, $produk->foto);
        $this->assertTrue($produk->foto[0]['is_primary']);
        $this->assertStringEndsWith('.webp', $produk->gambar);

        // 2. Test skip_existing mode
        $fakeImage2 = UploadedFile::fake()->image('test_product_2.jpg', 600, 600);
        $resSkip = $this->service->processAndAttach($fakeImage2, $produk->id, 'skip_existing');
        $this->assertTrue($resSkip['success']);
        $this->assertTrue($resSkip['skipped'] ?? false);

        // Foto tetap 1 karena di-skip
        $produk->refresh();
        $this->assertCount(1, $produk->foto);

        // 3. Test replace mode
        $fakeImage3 = UploadedFile::fake()->image('test_product_3.jpg', 600, 600);
        $resReplace = $this->service->processAndAttach($fakeImage3, $produk->id, 'replace');
        $this->assertTrue($resReplace['success']);

        $produk->refresh();
        $this->assertCount(1, $produk->foto);
        $this->assertTrue($produk->foto[0]['is_primary']);
    }

    public function test_skipped_export_generates_collection(): void
    {
        $skipped = [
            [
                'filename' => 'UNKNOWN-SKU.jpg',
                'sku' => 'UNKNOWN-SKU',
                'reason' => 'SKU tidak terdaftar',
                'size' => 1024,
            ],
        ];

        $export = new BulkFotoSkippedExport($skipped);
        $collection = $export->collection();

        $this->assertCount(1, $collection);
        $this->assertEquals('UNKNOWN-SKU.jpg', $collection[0]['filename']);
        $this->assertEquals('UNKNOWN-SKU', $collection[0]['sku']);
    }

    public function test_import_foto_masal_job_execution_and_skip(): void
    {
        $produk = Produk::create([
            'nama' => 'Produk Job Test',
            'slug' => 'produk-job-test-'.uniqid(),
            'kategori' => 'Sparepart',
            'kondisi' => 'baru',
            'satuan' => 'pcs',
            'harga_beli' => 10000,
            'harga_jual_retail' => 20000,
            'is_active' => true,
        ]);
        SkuVariant::create([
            'produk_id' => $produk->id,
            'sku' => 'JOB-TEST-SKU',
            'nama_varian' => 'Standar',
            'is_active' => true,
        ]);

        $fakeImage = UploadedFile::fake()->image('JOB-TEST-SKU.jpg', 400, 400);
        $tempPath = $fakeImage->getRealPath();

        $log = ImportLog::create([
            'tipe' => 'produk_foto',
            'nama_file' => 'test-job.zip',
            'status' => 'proses',
            'total_baris' => 2,
        ]);

        $items = [
            [
                'filename' => 'JOB-TEST-SKU.jpg',
                'sku' => 'JOB-TEST-SKU',
                'produk_id' => $produk->id,
                'produk_nama' => $produk->nama,
                'temp_path' => $tempPath,
                'is_primary' => true,
            ],
            [
                'filename' => 'JOB-TEST-SKU-2.jpg',
                'sku' => 'JOB-TEST-SKU',
                'produk_id' => $produk->id,
                'produk_nama' => $produk->nama,
                'temp_path' => $tempPath,
                'is_primary' => false,
            ],
        ];

        // Resolusi error: skip baris ke-2
        $resolusi = [2 => 'skip'];

        ImportFotoMasalJob::dispatchSync(
            $log->id,
            $items,
            null,
            'append',
            null,
            $resolusi
        );

        $log->refresh();
        $this->assertEquals('selesai', $log->status);
        $this->assertEquals(1, $log->sukses);
        $this->assertEquals(0, $log->gagal);
        $this->assertIsArray($log->detail);
        $this->assertCount(2, $log->detail);
        $this->assertEquals('skip', $log->detail[1]['status']);
    }
}
