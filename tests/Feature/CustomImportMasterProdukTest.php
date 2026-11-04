<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Akunting\Models\AkunCOA;
use App\Modules\Rbac\Models\Cabang;
use App\Modules\Wms\Livewire\ImportExportPage;
use App\Modules\Wms\Models\Gudang;
use App\Modules\Wms\Models\ImportLog;
use App\Modules\Wms\Models\Produk;
use App\Modules\Wms\Models\Rak;
use App\Modules\Wms\Models\SatuanUnit;
use App\Modules\Wms\Models\SkuVariant;
use App\Modules\Wms\Services\ImportProdukService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class CustomImportMasterProdukTest extends TestCase
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

        $this->admin = User::factory()->create();
        session(['cabang_id' => $this->cabang->id]);

        SatuanUnit::firstOrCreate(['kode' => 'pcs'], ['nama' => 'Pieces', 'is_active' => true]);
        SatuanUnit::firstOrCreate(['kode' => 'box'], ['nama' => 'Box', 'is_active' => true]);

        AkunCOA::firstOrCreate(['kode' => '130-01'], ['nama' => 'Persediaan', 'tipe' => 'aset', 'kelompok' => 'persediaan', 'saldo_normal' => 'debit']);
        AkunCOA::firstOrCreate(['kode' => '310-01'], ['nama' => 'Modal Pemilik', 'tipe' => 'ekuitas', 'kelompok' => 'modal', 'saldo_normal' => 'kredit']);

        $this->service = app(ImportProdukService::class);
    }

    public function test_deteksi_file_bukan_template_bawaan(): void
    {
        // Headers bukan template bawaan
        $nonTemplateHeaders = ['No', 'Kode Barang', 'Deskripsi Item', 'Harga Eceran', 'Stok Fisik', 'Kolom Vendor'];
        $this->assertFalse($this->service->isTemplateBawaan($nonTemplateHeaders));

        // Headers template resmi
        $templateHeaders = $this->service->templateColumns();
        $this->assertTrue($this->service->isTemplateBawaan($templateHeaders));
    }

    public function test_inspect_file_menghasilkan_mapping_rekomendasi_dan_sample_data(): void
    {
        $csvContent = implode("\n", [
            'KdBrg;Nama Sparepart;Harga Jual;Harga Modal;Stok;Kolom Abaikan',
            'LCD-IP11;LCD iPhone 11 Incell;350000;220000;15;abaikan_ini_1',
            'BAT-IP11;Baterai iPhone 11 Original;250000;150000;20;abaikan_ini_2',
        ]);

        $tmpFile = tempnam(sys_get_temp_dir(), 'custom_imp_').'.csv';
        file_put_contents($tmpFile, $csvContent);

        $inspect = $this->service->inspectFile($tmpFile);

        $this->assertFalse($inspect['is_template']);
        $this->assertEquals(6, $inspect['total_columns']);

        $headings = collect($inspect['headings']);
        $this->assertEquals('sku', $headings->firstWhere('raw_name', 'KdBrg')['suggested_mapping']);
        $this->assertEquals('nama', $headings->firstWhere('raw_name', 'Nama Sparepart')['suggested_mapping']);
        $this->assertEquals('harga_jual', $headings->firstWhere('raw_name', 'Harga Jual')['suggested_mapping']);
        $this->assertEquals('harga_beli', $headings->firstWhere('raw_name', 'Harga Modal')['suggested_mapping']);
        $this->assertEquals('stok_awal', $headings->firstWhere('raw_name', 'Stok')['suggested_mapping']);
        $this->assertEquals('', $headings->firstWhere('raw_name', 'Kolom Abaikan')['suggested_mapping']);

        @unlink($tmpFile);
    }

    public function test_custom_import_commit_menyimpan_data_sesuai_mapping_dan_mengabaikan_kolom_unmapped(): void
    {
        $csvContent = implode("\n", [
            'PartNumber,ItemName,SellPrice,BuyPrice,Qty,UnusedNote,VendorCode',
            'LCD-IP12-OLED,LCD iPhone 12 OLED,1250000,850000,8,Jangan Diimport,VND-888',
            'FLEX-IP12-CHG,Flexible Charging iPhone 12,95000,45000,12,Catatan Internal,VND-999',
        ]);

        $tmpFile = tempnam(sys_get_temp_dir(), 'custom_commit_').'.csv';
        file_put_contents($tmpFile, $csvContent);

        $columnMapping = [
            'col_0' => 'sku',
            'col_1' => 'nama',
            'col_2' => 'harga_jual',
            'col_3' => 'harga_beli',
            'col_4' => 'stok_awal',
            'col_5' => '', // Abaikan
            'col_6' => 'abaikan', // Abaikan
        ];

        $log = ImportLog::create([
            'tipe' => 'produk_excel',
            'nama_file' => 'test_custom.csv',
            'status' => 'proses',
            'user_id' => $this->admin->id,
            'total_baris' => 2,
        ]);

        $commitResult = $this->service->commit(
            filePath: $tmpFile,
            importLogId: $log->id,
            userId: $this->admin->id,
            aksiKemiripan: [],
            resolusiDuplikat: [],
            threshold: 99,
            resolusiBarcode: [],
            resolusiErrorBaris: [],
            cabangId: $this->cabang->id,
            columnMapping: $columnMapping,
            defaultGudangId: $this->gudang->id
        );

        $this->assertEquals(2, $commitResult['sukses']);
        $this->assertEquals(0, $commitResult['gagal']);

        $p1 = Produk::where('nama', 'LCD iPhone 12 OLED')->first();
        $this->assertNotNull($p1);
        $v1 = SkuVariant::where('produk_id', $p1->id)->first();
        $this->assertEquals('LCD-IP12-OLED', $v1->sku);
        $this->assertEquals(1250000, $v1->harga_jual_retail);
        $this->assertEquals(850000, $v1->harga_beli);

        $p2 = Produk::where('nama', 'Flexible Charging iPhone 12')->first();
        $this->assertNotNull($p2);
        $v2 = SkuVariant::where('produk_id', $p2->id)->first();
        $this->assertEquals('FLEX-IP12-CHG', $v2->sku);
        $this->assertEquals(95000, $v2->harga_jual_retail);

        @unlink($tmpFile);
    }

    public function test_custom_import_auto_generate_sku_jika_kolom_sku_tidak_dipetakan(): void
    {
        $csvContent = implode("\n", [
            'Nama Barang,Harga Jual,Stok',
            'Kamera Belakang iPhone 11 Pro,450000,5',
        ]);

        $tmpFile = tempnam(sys_get_temp_dir(), 'custom_nosku_').'.csv';
        file_put_contents($tmpFile, $csvContent);

        $columnMapping = [
            'col_0' => 'nama',
            'col_1' => 'harga_jual',
            'col_2' => 'stok_awal',
        ];

        $log = ImportLog::create([
            'tipe' => 'produk_excel',
            'nama_file' => 'test_nosku.csv',
            'status' => 'proses',
            'user_id' => $this->admin->id,
            'total_baris' => 1,
        ]);

        $commitResult = $this->service->commit(
            filePath: $tmpFile,
            importLogId: $log->id,
            userId: $this->admin->id,
            aksiKemiripan: [],
            resolusiDuplikat: [],
            threshold: 99,
            resolusiBarcode: [],
            resolusiErrorBaris: [],
            cabangId: $this->cabang->id,
            columnMapping: $columnMapping,
            defaultGudangId: $this->gudang->id
        );

        $this->assertEquals(1, $commitResult['sukses']);

        $p = Produk::where('nama', 'Kamera Belakang iPhone 11 Pro')->first();
        $this->assertNotNull($p);
        $v = SkuVariant::where('produk_id', $p->id)->first();
        $this->assertNotEmpty($v->sku);
        $this->assertEquals(450000, $v->harga_jual_retail);

        @unlink($tmpFile);
    }

    public function test_livewire_import_export_page_mengaktifkan_custom_mapping_saat_file_bukan_template(): void
    {
        Storage::fake('local');

        $csvContent = "Kode Part;Deskripsi;Harga Normal;Jumlah;Catatan Gudang\nLCD-TEST-99;LCD Test 99;500000;10;Abaikan";
        $file = UploadedFile::fake()->createWithContent('bukan_template.csv', $csvContent);

        session(['cabang_id' => $this->cabang->id]);

        $component = Livewire::actingAs($this->admin)
            ->test(ImportExportPage::class)
            ->set('importFile', $file)
            ->call('previewImport');

        // Pastikan step berubah ke 'mapping' dan bukan langsung ke 'preview'
        $component->assertSet('importStep', 'mapping');
        $component->assertSet('isCustomMapping', true);
        $component->assertCount('customColumns', 5);

        // Uji validasi jika kolom wajib (nama) dikosongkan
        $component->set('columnMapping.col_1', '')
            ->call('applyCustomMappingAndPreview')
            ->assertSet('importStep', 'mapping'); // Tetap di mapping karena gagal validasi

        // Kembalikan nama & lanjutkan preview
        $component->set('columnMapping.col_0', 'sku')
            ->set('columnMapping.col_1', 'nama')
            ->set('columnMapping.col_2', 'harga_jual')
            ->set('columnMapping.col_3', 'stok_awal')
            ->set('columnMapping.col_4', '') // diabaikan
            ->call('applyCustomMappingAndPreview')
            ->assertSet('importStep', 'preview');

        // Verifikasi tombol Ubah Pemetaan Kolom dapat kembali ke mapping
        $component->call('backToMapping')
            ->assertSet('importStep', 'mapping');
    }
}
