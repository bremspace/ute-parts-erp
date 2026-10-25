<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Rbac\Models\Cabang;
use App\Modules\Wms\Livewire\ImportExportPage;
use App\Modules\Wms\Models\Gudang;
use App\Modules\Wms\Models\ImportLog;
use App\Modules\Wms\Models\Produk;
use App\Modules\Wms\Models\SatuanUnit;
use App\Modules\Wms\Models\SkuVariant;
use App\Modules\Wms\Services\ImportProdukService;
use Database\Seeders\AkunCoaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Tests\TestCase;

class WmsImportErrorManagementTest extends TestCase
{
    use RefreshDatabase;

    private Cabang $cabang;

    private Gudang $gudang;

    private User $admin;

    private ImportProdukService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(AkunCoaSeeder::class);

        $this->cabang = Cabang::create([
            'kode' => 'CB01',
            'nama' => 'Cabang Utama',
            'is_pusat' => true,
            'is_active' => true,
        ]);

        $this->gudang = Gudang::create([
            'cabang_id' => $this->cabang->id,
            'kode' => 'G-UTAMA',
            'nama' => 'Gudang Utama',
            'is_active' => true,
        ]);

        SatuanUnit::firstOrCreate(['kode' => 'pcs'], ['nama' => 'Pcs']);

        $role = Role::firstOrCreate(['name' => 'Super Admin', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'wms.produk.create', 'guard_name' => 'web']);
        $role->givePermissionTo('wms.produk.create');

        $this->admin = User::factory()->create();
        $this->admin->assignRole($role);

        $this->service = app(ImportProdukService::class);
    }

    private function createDummyCsv(array $rows): string
    {
        $path = tempnam(sys_get_temp_dir(), 'ute_err_test_').'.csv';
        $fp = fopen($path, 'w');
        fputcsv($fp, array_keys($rows[0]));
        foreach ($rows as $row) {
            fputcsv($fp, array_values($row));
        }
        fclose($fp);

        return $path;
    }

    public function test_kosongkan_barcode_dan_skip_error_baris(): void
    {
        $p = Produk::create([
            'nama' => 'Produk Awal',
            'slug' => 'produk-awal',
            'kategori_id' => null,
            'satuan_id' => null,
            'is_active' => true,
        ]);
        SkuVariant::create([
            'produk_id' => $p->id,
            'sku' => 'SKU-EXIST-01',
            'barcode' => 'BC-EXIST-01',
            'nama_varian' => 'Standar',
            'harga_beli' => 10000,
            'harga_jual_retail' => 15000,
            'is_active' => true,
        ]);

        $rows = [
            [
                'sku' => 'SKU-NEW-01',
                'barcode' => 'BC-EXIST-01',
                'nama' => 'Produk Baru Barcode Tabrakan',
                'satuan' => 'pcs',
                'harga_beli' => 20000,
                'harga_jual' => 25000,
                'stok_awal' => 5,
                'gudang' => $this->gudang->nama,
            ],
            [
                'sku' => '',
                'barcode' => 'BC-ERR-02',
                'nama' => 'Produk Tanpa SKU',
                'satuan' => 'pcs',
                'harga_beli' => 10000,
                'harga_jual' => 12000,
                'stok_awal' => 0,
                'gudang' => $this->gudang->nama,
            ],
            [
                'sku' => 'SKU-NEW-03',
                'barcode' => 'BC-CLEAN-03',
                'nama' => 'Produk Bersih',
                'satuan' => 'pcs',
                'harga_beli' => 30000,
                'harga_jual' => 35000,
                'stok_awal' => 2,
                'gudang' => $this->gudang->nama,
            ],
        ];

        $filePath = $this->createDummyCsv($rows);

        $preview = $this->service->preview($filePath);
        $this->assertCount(1, $preview['duplikat_rows']);
        $this->assertEquals('barcode', $preview['duplikat_rows'][0]['tipe']);

        $importLog = ImportLog::create([
            'tipe' => 'produk_excel',
            'nama_file' => 'test-error.csv',
            'status' => 'proses',
            'user_id' => $this->admin->id,
        ]);

        $resolusiBarcode = [
            2 => ['aksi' => 'kosongkan_barcode'],
        ];
        $resolusiError = [
            3 => 'skip',
        ];

        $result = $this->service->commit(
            $filePath,
            $importLog->id,
            $this->admin->id,
            [],
            [],
            99,
            $resolusiBarcode,
            $resolusiError
        );

        $this->assertEquals(2, $result['sukses']);
        $this->assertEquals(0, $result['gagal']);

        $v1 = SkuVariant::where('sku', 'SKU-NEW-01')->first();
        $this->assertNotNull($v1);
        $this->assertNull($v1->barcode);

        $v3 = SkuVariant::where('sku', 'SKU-NEW-03')->first();
        $this->assertNotNull($v3);
        $this->assertEquals('BC-CLEAN-03', $v3->barcode);

        $logFresh = $importLog->fresh();
        $this->assertCount(1, $logFresh->skip_list);
        $this->assertEquals(3, $logFresh->skip_list[0]['baris']);

        @unlink($filePath);
    }

    public function test_export_error_rows_download_csv(): void
    {
        $log = ImportLog::create([
            'tipe' => 'produk_excel',
            'nama_file' => 'sample.xlsx',
            'status' => 'selesai',
            'total_baris' => 3,
            'sukses' => 1,
            'gagal' => 1,
            'user_id' => $this->admin->id,
            'detail' => [
                [
                    'baris' => 2,
                    'sku' => 'SKU-OK',
                    'nama' => 'Produk Sukses',
                    'status' => 'ok',
                ],
                [
                    'baris' => 3,
                    'sku' => 'SKU-FAIL',
                    'nama' => 'Produk Gagal',
                    'status' => 'gagal',
                    'error' => 'Kategori wajib diisi',
                ],
                [
                    'baris' => 4,
                    'sku' => 'SKU-SKIP',
                    'nama' => 'Produk Dilewati',
                    'status' => 'skip',
                    'catatan' => 'Dilewati oleh pengguna',
                ],
            ],
        ]);

        session(['cabang_id' => $this->cabang->id]);

        $component = Livewire::actingAs($this->admin)
            ->test(ImportExportPage::class)
            ->set('importLogId', $log->id);

        $response = $component->instance()->exportErrorRows();

        $this->assertInstanceOf(StreamedResponse::class, $response);

        ob_start();
        $response->sendContent();
        $csvContent = ob_get_clean();

        $this->assertStringContainsString('No. Baris', $csvContent);
        $this->assertStringContainsString('SKU-FAIL', $csvContent);
        $this->assertStringContainsString('Kategori wajib diisi', $csvContent);
        $this->assertStringContainsString('SKU-SKIP', $csvContent);
        $this->assertStringNotContainsString('SKU-OK', $csvContent);
    }
}
