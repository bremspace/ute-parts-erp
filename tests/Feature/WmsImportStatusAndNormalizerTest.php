<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Rbac\Models\Cabang;
use App\Modules\Wms\Exports\ImportProdukTemplateExport;
use App\Modules\Wms\Models\Gudang;
use App\Modules\Wms\Models\ImportLog;
use App\Modules\Wms\Models\Rak;
use App\Modules\Wms\Models\SatuanUnit;
use App\Modules\Wms\Services\ImportProdukService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use Tests\TestCase;

class WmsImportStatusAndNormalizerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Cabang $cabang;

    private Gudang $gudang;

    private ImportProdukService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cabang = Cabang::create([
            'kode' => 'CB01',
            'nama' => 'Cabang Utama',
            'is_pusat' => true,
            'is_active' => true,
        ]);

        $this->gudang = Gudang::create([
            'cabang_id' => $this->cabang->id,
            'kode' => 'GDG-01',
            'nama' => 'Gudang Utama',
            'is_default' => true,
            'is_active' => true,
        ]);

        Rak::create([
            'gudang_id' => $this->gudang->id,
            'kode' => 'RAK-01',
            'nama' => 'Rak A1',
            'is_active' => true,
        ]);
        Rak::create([
            'gudang_id' => $this->gudang->id,
            'kode' => 'RAK-02',
            'nama' => 'Rak A2',
            'is_active' => true,
        ]);

        $this->admin = User::factory()->create();

        SatuanUnit::firstOrCreate(['kode' => 'pcs'], ['nama' => 'Pieces', 'is_active' => true]);
        SatuanUnit::firstOrCreate(['kode' => 'box'], ['nama' => 'Box', 'is_active' => true]);
        SatuanUnit::firstOrCreate(['kode' => 'unit'], ['nama' => 'Unit', 'is_active' => true]);

        $this->service = app(ImportProdukService::class);
    }

    public function test_normalizer_satuan_dan_kondisi_bekerja_dengan_benar(): void
    {
        // Variasi penulisan satuan
        $this->assertSame('pcs', $this->service->normalizeSatuan('PCS'));
        $this->assertSame('pcs', $this->service->normalizeSatuan('pieces'));
        $this->assertSame('pcs', $this->service->normalizeSatuan('buah'));
        $this->assertSame('pcs', $this->service->normalizeSatuan('bh'));
        $this->assertSame('box', $this->service->normalizeSatuan('dus'));
        $this->assertSame('box', $this->service->normalizeSatuan('kotak'));
        $this->assertSame('unit', $this->service->normalizeSatuan('unt'));

        // Variasi penulisan kondisi
        $this->assertSame('baru', $this->service->normalizeKondisi('baru'));
        $this->assertSame('baru', $this->service->normalizeKondisi('NEW'));
        $this->assertSame('oem', $this->service->normalizeKondisi('OEM'));
        $this->assertSame('compatible', $this->service->normalizeKondisi('kompatibel'));

        // Variasi kualitas
        $this->assertSame('Original', $this->service->normalizeKualitas('ori'));
        $this->assertSame('Grade A', $this->service->normalizeKualitas('grade-a'));

        // Variasi SN
        $this->assertTrue($this->service->normalizeSn('ya'));
        $this->assertTrue($this->service->normalizeSn('1'));
        $this->assertFalse($this->service->normalizeSn('tidak'));
        $this->assertFalse($this->service->normalizeSn('0'));
    }

    public function test_preview_mendeteksi_status_sempurna_dan_peringatan_kompatibilitas(): void
    {
        $csvContent = implode("\n", [
            'sku;nama;satuan;harga_beli;harga_jual;brand;tipe_hp;kualitas;kondisi;min_stock;reorder_point;sn;deskripsi',
            'LCD-IP11;LCD iPhone 11 Pro Max Original;buah;250000;450000;Apple;iPhone 11 Pro Max;Original;baru;5;10;ya;LCD Original',
            'LCD-IP11P;LCD iPhone 11 Pro Max Original;pieces;300000;550000;Apple;iPhone 11 Pro Max;Original;baru;5;10;ya;LCD Original',
        ]);

        $filePath = sys_get_temp_dir().'/test_import_preview_compat.csv';
        file_put_contents($filePath, $csvContent);

        $preview = $this->service->preview($filePath);
        @unlink($filePath);

        $this->assertSame(2, $preview['total_baris']);
        $this->assertSame(2, $preview['valid']);
        $this->assertSame(0, $preview['invalid']);
        // Terdapat kemiripan nama LCD iPhone 11 vs iPhone 11 Pro → memunculkan perhatian kompatibilitas
        $this->assertGreaterThanOrEqual(1, $preview['total_peringatan']);
        $this->assertSame('perlu_perhatian', $preview['status_preview']);
        $this->assertStringContainsString('Perhatian Khusus Kompatibilitas', $preview['label_status']);
    }

    public function test_preview_file_xlsx_berhasil_tanpa_error(): void
    {
        $export = new ImportProdukTemplateExport;
        Excel::store($export, 'test_preview.xlsx', 'local');
        $path = Storage::disk('local')->path('test_preview.xlsx');

        try {
            $preview = $this->service->preview($path);
            $this->assertSame(2, $preview['total_baris']);
            $this->assertSame(2, $preview['valid']);
            $this->assertSame(0, $preview['invalid']);
            $this->assertSame('sempurna', $preview['status_preview']);
        } finally {
            Storage::disk('local')->delete('test_preview.xlsx');
        }
    }

    public function test_import_log_menghasilkan_status_badge_dan_label_yang_tepat(): void
    {
        // 1. Selesai Sempurna
        $logSempurna = ImportLog::create([
            'tipe' => 'produk_excel',
            'total_baris' => 10,
            'sukses' => 10,
            'gagal' => 0,
            'status' => 'selesai',
            'detail' => [],
        ]);
        $this->assertSame('Diterima Sempurna', $logSempurna->status_label);
        $this->assertStringContainsString('text-up-mint', $logSempurna->status_badge_class);

        // 2. Diterima Sebagian
        $logSebagian = ImportLog::create([
            'tipe' => 'produk_excel',
            'total_baris' => 10,
            'sukses' => 8,
            'gagal' => 2,
            'status' => 'selesai',
            'detail' => [
                ['baris' => 3, 'sku' => 'ERR-1', 'status' => 'gagal', 'error' => 'SKU duplikat'],
            ],
        ]);
        $this->assertSame('Diterima Sebagian', $logSebagian->status_label);
        $this->assertStringContainsString('text-up-amber', $logSebagian->status_badge_class);
        $this->assertCount(1, $logSebagian->gagal_list);

        // 3. Gagal Total
        $logGagal = ImportLog::create([
            'tipe' => 'produk_excel',
            'total_baris' => 5,
            'sukses' => 0,
            'gagal' => 5,
            'status' => 'selesai',
            'detail' => [],
        ]);
        $this->assertSame('Gagal Total', $logGagal->status_label);
        $this->assertStringContainsString('text-up-red', $logGagal->status_badge_class);
    }

    public function test_normalizer_heading_kompabilitas_varian(): void
    {
        $this->assertSame('kompatibilitas_hp', $this->service->normalizeImportHeading('kompabilitas_hp'));
        $this->assertSame('kompatibilitas_hp', $this->service->normalizeImportHeading('kompabilitas'));
        $this->assertSame('kompatibilitas_hp', $this->service->normalizeImportHeading('tipe_kompatibel'));
        $this->assertSame('tipe_hp', $this->service->normalizeImportHeading('model_kompatibilitas'));
    }

    public function test_parse_kompatibilitas_hp_multi_model_dan_multi_brand(): void
    {
        // 1. Format export sistem: Merk:Model;Merk:Model
        $hasil1 = $this->service->parseKompatibilitas('Apple:iPhone 13;Apple:iPhone 13 Pro;Apple:iPhone 14');
        $this->assertCount(3, $hasil1);
        $this->assertSame('Apple', $hasil1[0]['merk']);
        $this->assertSame('iPhone 13', $hasil1[0]['model']);
        $this->assertSame('Apple', $hasil1[1]['merk']);
        $this->assertSame('iPhone 13 Pro', $hasil1[1]['model']);
        $this->assertSame('Apple', $hasil1[2]['merk']);
        $this->assertSame('iPhone 14', $hasil1[2]['model']);

        // 2. Format multi-model dalam satu merk dengan koma: Apple: iPhone 13, iPhone 13 Pro; Samsung: Galaxy A54
        $hasil2 = $this->service->parseKompatibilitas('Apple: iPhone 13, iPhone 13 Pro; Samsung: Galaxy A54');
        $this->assertCount(3, $hasil2);
        $this->assertSame('Apple', $hasil2[0]['merk']);
        $this->assertSame('iPhone 13', $hasil2[0]['model']);
        $this->assertSame('Apple', $hasil2[1]['merk']);
        $this->assertSame('iPhone 13 Pro', $hasil2[1]['model']);
        $this->assertSame('Samsung', $hasil2[2]['merk']);
        $this->assertSame('Galaxy A54', $hasil2[2]['model']);

        // 3. Format JSON array
        $json = json_encode([
            ['merk' => 'Xiaomi', 'model' => 'Redmi Note 10'],
            ['merk' => 'Xiaomi', 'model' => 'Redmi Note 11'],
        ]);
        $hasil3 = $this->service->parseKompatibilitas($json);
        $this->assertCount(2, $hasil3);
        $this->assertSame('Xiaomi', $hasil3[0]['merk']);
        $this->assertSame('Redmi Note 10', $hasil3[0]['model']);

        // 4. Penggabungan tipe_hp dan kompatibilitas_hp via gabungDaftarTipe
        $gabung = $this->service->gabungDaftarTipe(
            [['merk' => 'Apple', 'model' => 'iPhone 13']],
            [['merk' => 'Apple', 'model' => 'iPhone 13 Pro'], ['merk' => 'Apple', 'model' => 'iPhone 13']]
        );
        $this->assertCount(2, $gabung);
    }
}
