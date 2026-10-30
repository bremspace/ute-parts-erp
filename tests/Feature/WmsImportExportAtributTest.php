<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Rbac\Livewire\SettingsRbac;
use App\Modules\Rbac\Models\Cabang;
use App\Modules\Wms\Exports\AtributProdukExport;
use App\Modules\Wms\Models\Brand;
use App\Modules\Wms\Models\Gudang;
use App\Modules\Wms\Models\KategoriProduk;
use App\Modules\Wms\Models\KualitasProduk;
use App\Modules\Wms\Models\Rak;
use App\Modules\Wms\Models\SatuanUnit;
use App\Modules\Wms\Models\TipeHp;
use App\Modules\Wms\Services\ImportAtributService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Maatwebsite\Excel\Facades\Excel;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class WmsImportExportAtributTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Cabang $cabang;

    private Gudang $gudang;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cabang = Cabang::create([
            'nama' => 'Cabang Pusat',
            'kode' => 'PST',
            'alamat' => 'Jl. Pusat No. 1',
            'telepon' => '08123456789',
            'is_aktif' => true,
        ]);

        $this->gudang = Gudang::create([
            'cabang_id' => $this->cabang->id,
            'nama' => 'Gudang Pusat',
            'kode' => 'GDG-PST',
            'is_active' => true,
        ]);

        $role = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'wms.view', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'wms.create', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'pengaturan.manage', 'guard_name' => 'web']);
        $role->givePermissionTo(['wms.view', 'wms.create', 'pengaturan.manage']);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');
    }

    public function test_atribut_produk_export_template_dan_data_sheets(): void
    {
        // 1. Template multi-sheet
        $export = new AtributProdukExport('template', null, $this->cabang->id);
        $sheets = $export->sheets();
        $this->assertCount(6, $sheets);
        $titles = array_map(fn ($s) => $s->title(), $sheets);
        $this->assertEquals(['Kategori', 'Brand', 'Kualitas', 'Satuan', 'Rak', 'Tipe HP'], $titles);

        // 2. Data sheet dengan record eksisting
        Brand::create(['nama' => 'BrandTest', 'is_active' => true]);
        SatuanUnit::create(['kode' => 'pack', 'nama' => 'Paket', 'is_active' => true]);

        $exportData = new AtributProdukExport('data', 'brand', $this->cabang->id);
        $brandSheets = $exportData->sheets();
        $this->assertCount(1, $brandSheets);
        $this->assertEquals('Brand', $brandSheets[0]->title());
        $col = $brandSheets[0]->collection();
        $this->assertTrue($col->contains(fn ($r) => $r['nama'] === 'BrandTest'));
    }

    public function test_import_atribut_service_execute_kategori_brand_satuan_kualitas_rak_tipehp(): void
    {
        $service = new ImportAtributService;

        $items = [
            'kategori' => [
                [
                    'kategori_utama' => 'Sparepart Layar',
                    'sub_kategori' => 'LCD Touchscreen',
                    'kode_slug' => 'lcd-touchscreen',
                    'urutan' => 1,
                    'is_active' => 'ya',
                    'status' => 'baru',
                ],
            ],
            'brand' => [
                [
                    'nama' => 'MerkSuper',
                    'keterangan' => 'Brand komponen super',
                    'is_active' => 'ya',
                    'status' => 'baru',
                ],
            ],
            'kualitas' => [
                [
                    'nama' => 'Grade Platinum',
                    'brand' => 'MerkSuper',
                    'keterangan' => 'Kualitas tertinggi',
                    'is_active' => 'ya',
                    'status' => 'baru',
                ],
            ],
            'satuan' => [
                [
                    'kode' => 'dus',
                    'nama' => 'Kardus Besar',
                    'is_active' => 'ya',
                    'status' => 'baru',
                ],
            ],
            'rak' => [
                [
                    'gudang' => 'Gudang Pusat',
                    'kode' => 'RAK-PST-1',
                    'nama' => 'Rak Utama 1',
                    'zona' => 'Zona A',
                    'is_active' => 'ya',
                    'status' => 'baru',
                ],
            ],
            'tipe_hp' => [
                [
                    'merk' => 'Xiaomi',
                    'model' => 'Redmi Note 12',
                    'nama' => 'Xiaomi Redmi Note 12 5G',
                    'is_active' => 'ya',
                    'status' => 'baru',
                ],
            ],
        ];

        $hasil = $service->execute($items, $this->cabang->id);

        $this->assertEquals(6, $hasil['total_berhasil']);
        $this->assertEquals(0, $hasil['total_gagal']);

        // Verifikasi di database
        $parentKat = KategoriProduk::where('nama', 'Sparepart Layar')->first();
        $this->assertNotNull($parentKat);

        $subKat = KategoriProduk::where('nama', 'LCD Touchscreen')->where('parent_id', $parentKat->id)->first();
        $this->assertNotNull($subKat);

        $brand = Brand::where('nama', 'MerkSuper')->first();
        $this->assertNotNull($brand);

        $kual = KualitasProduk::where('nama', 'Grade Platinum')->where('brand_id', $brand->id)->first();
        $this->assertNotNull($kual);

        $satuan = SatuanUnit::where('kode', 'dus')->first();
        $this->assertNotNull($satuan);
        $this->assertEquals('Kardus Besar', $satuan->nama);

        $rak = Rak::where('kode', 'RAK-PST-1')->first();
        $this->assertNotNull($rak);
        $this->assertEquals($this->gudang->id, $rak->gudang_id);

        $tipeHp = TipeHp::where('merk', 'Xiaomi')->where('model', 'Redmi Note 12')->first();
        $this->assertNotNull($tipeHp);
        $this->assertEquals('Xiaomi Redmi Note 12 5G', $tipeHp->nama);
    }

    public function test_import_atribut_service_execute_handles_existing_update(): void
    {
        $service = new ImportAtributService;

        Brand::create(['nama' => 'BrandLama', 'keterangan' => 'Awal', 'is_active' => true]);
        SatuanUnit::firstOrCreate(['kode' => 'pcs'], ['nama' => 'Pieces', 'is_active' => true]);

        $items = [
            'brand' => [
                [
                    'nama' => 'BrandLama',
                    'keterangan' => 'Keterangan Diperbarui',
                    'is_active' => 'tidak',
                    'status' => 'update',
                ],
            ],
            'satuan' => [
                [
                    'kode' => 'pcs',
                    'nama' => 'Satuan Butir',
                    'is_active' => 'ya',
                    'status' => 'update',
                ],
            ],
        ];

        $hasil = $service->execute($items, $this->cabang->id);

        $this->assertEquals(2, $hasil['total_berhasil']);
        $this->assertEquals(1, $hasil['brand']['update']);
        $this->assertEquals(1, $hasil['satuan']['update']);

        $updatedBrand = Brand::where('nama', 'BrandLama')->first();
        $this->assertEquals('Keterangan Diperbarui', $updatedBrand->keterangan);
        $this->assertFalse($updatedBrand->is_active);

        $updatedSatuan = SatuanUnit::where('kode', 'pcs')->first();
        $this->assertEquals('Satuan Butir', $updatedSatuan->nama);
    }

    public function test_livewire_import_export_page_atribut_tab_and_actions(): void
    {
        Excel::fake();

        $this->actingAs($this->admin);
        session(['cabang_id' => $this->cabang->id]);

        $component = Livewire::test(SettingsRbac::class)
            ->set('activeTab', 'export_import')
            ->assertSee('Export &amp; Import Master Data', false)
            ->assertSee('Template .xlsx')
            ->assertSee('Data .xlsx')
            ->call('downloadTemplateAtribut');

        Excel::assertDownloaded('template-atribut.xlsx');
    }

    public function test_livewire_import_atribut_preview_and_commit(): void
    {
        Storage::fake('local');
        $this->actingAs($this->admin);
        session(['cabang_id' => $this->cabang->id]);

        // Buat file CSV contoh
        $csvContent = "kategori_utama,sub_kategori,kode_slug,urutan,is_active\n"
                    ."Kategori Baris,Sub Kategori Baris,kategori-baris,1,ya\n";

        $tempFile = UploadedFile::fake()->createWithContent('kategori_test.csv', $csvContent);

        Livewire::test(SettingsRbac::class)
            ->set('activeTab', 'export_import')
            ->set('atributTarget', 'kategori')
            ->set('atributFile', $tempFile)
            ->call('previewImportAtribut')
            ->assertSet('atributStep', 'preview')
            ->assertSee('Kategori Baris')
            ->call('commitImportAtribut')
            ->assertSet('atributStep', 'selesai');

        // Pastikan tersimpan ke database
        $this->assertDatabaseHas('kategori_produk', [
            'nama' => 'Kategori Baris',
        ]);
        $this->assertDatabaseHas('kategori_produk', [
            'nama' => 'Sub Kategori Baris',
        ]);
    }
}
