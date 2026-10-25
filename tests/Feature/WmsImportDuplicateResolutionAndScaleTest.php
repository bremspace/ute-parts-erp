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
use Tests\TestCase;

class WmsImportDuplicateResolutionAndScaleTest extends TestCase
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
            'kode' => 'GDG-01',
            'nama' => 'Gudang Utama',
            'is_default' => true,
            'is_active' => true,
        ]);

        $this->admin = User::factory()->create();

        $this->service = app(ImportProdukService::class);
    }

    public function test_large_dataset_preview_skips_fuzzy_matching_and_preloads_db(): void
    {
        SatuanUnit::firstOrCreate(['kode' => 'pcs'], ['nama' => 'Pieces']);

        $lines = ['sku;nama;satuan;harga_beli;harga_jual;stok_awal;gudang'];
        for ($i = 1; $i <= 350; $i++) {
            $lines[] = "SCALE-SKU-{$i};Produk Scale Ke-{$i};pcs;10000;15000;0;GDG-01";
        }
        $csv = implode("\n", $lines);

        $filePath = sys_get_temp_dir().'/test_scale_350.csv';
        file_put_contents($filePath, $csv);

        try {
            $start = microtime(true);
            $preview = $this->service->preview($filePath);
            $duration = microtime(true) - $start;

            $this->assertSame(350, $preview['total_baris']);
            $this->assertSame(350, $preview['valid']);
            $this->assertSame(0, $preview['invalid']);
            $this->assertEmpty($preview['kemiripan_rows']);
            $this->assertLessThan(5.0, $duration, 'Preview 350 baris harus sangat cepat (< 5 detik) berkat preload dan bypass fuzzy');
        } finally {
            @unlink($filePath);
        }
    }

    public function test_duplicate_resolusi_semua_actions_in_livewire(): void
    {
        $role = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $permission = Permission::firstOrCreate(['name' => 'wms.view', 'guard_name' => 'web']);
        $role->givePermissionTo($permission);
        $this->admin->assignRole($role);

        $mockPreview = [
            'total_baris' => 3,
            'valid' => 3,
            'invalid' => 0,
            'duplikat_rows' => [
                [
                    'baris' => 2,
                    'sku' => 'SKU-DUP-1',
                    'nama' => 'Produk Duplikat 1',
                    'lokasi' => 'database',
                    'sku_rekomendasi' => 'SKU-DUP-1-V2',
                ],
                [
                    'baris' => 3,
                    'sku' => 'SKU-DUP-2',
                    'nama' => 'Produk Duplikat 2',
                    'lokasi' => 'database',
                    'sku_rekomendasi' => 'SKU-DUP-2-V2',
                ],
                [
                    'baris' => 4,
                    'sku' => 'SKU-DUP-3',
                    'nama' => 'Produk Duplikat 3',
                    'lokasi' => 'intra_file',
                    'sku_rekomendasi' => 'SKU-DUP-3-V2',
                ],
            ],
            'error_rows' => [],
        ];

        Livewire::actingAs($this->admin)
            ->test(ImportExportPage::class)
            ->set('importPreview', $mockPreview)
            ->call('setAksiDuplikatSemua', 'ganti_sku')
            ->assertSet('resolusiDuplikat.2.aksi', 'ganti_sku')
            ->assertSet('resolusiDuplikat.2.sku_baru', 'SKU-DUP-1-V2')
            ->assertSet('resolusiDuplikat.3.aksi', 'ganti_sku')
            ->assertSet('resolusiDuplikat.4.aksi', 'ganti_sku')
            ->call('setAksiDuplikatSemua', 'skip')
            ->assertSet('resolusiDuplikat.2.aksi', 'skip')
            ->assertSet('resolusiDuplikat.3.aksi', 'skip')
            ->assertSet('resolusiDuplikat.4.aksi', 'skip')
            ->call('setAksiDuplikatSemua', 'timpa_update')
            ->assertSet('resolusiDuplikat.2.aksi', 'timpa_update')
            ->assertSet('resolusiDuplikat.3.aksi', 'timpa_update')
            ->assertSet('resolusiDuplikat.4.aksi', 'ganti_sku'); // intra_file falls back to ganti_sku
    }

    public function test_commit_with_ganti_sku_is_not_treated_as_duplicate_error(): void
    {
        SatuanUnit::firstOrCreate(['kode' => 'pcs'], ['nama' => 'Pieces']);

        $existing = Produk::create([
            'nama' => 'Produk Lama',
            'satuan_id' => SatuanUnit::where('kode', 'pcs')->first()->id,
            'harga_beli' => 10000,
            'harga_jual' => 20000,
            'cabang_id' => $this->cabang->id,
            'is_active' => true,
        ]);
        SkuVariant::create([
            'produk_id' => $existing->id,
            'sku' => 'SKU-DUP-TEST',
            'harga_beli' => 10000,
            'harga_jual_retail' => 20000,
            'is_active' => true,
        ]);

        $csv = "sku;nama;satuan;harga_beli;harga_jual;stok_awal;gudang\n"
             .'SKU-DUP-TEST;Produk Baru Dengan SKU Bentrok;pcs;15000;25000;0;GDG-01';

        $filePath = sys_get_temp_dir().'/test_resolusi_commit.csv';
        file_put_contents($filePath, $csv);

        try {
            $log = ImportLog::create([
                'tipe' => 'produk_excel',
                'nama_file' => 'test_resolusi_commit.csv',
                'status' => 'proses',
                'user_id' => $this->admin->id,
            ]);

            $resolusi = [
                2 => [
                    'aksi' => 'ganti_sku',
                    'sku_baru' => 'SKU-DUP-TEST-CUSTOM',
                ],
            ];

            $hasil = $this->service->commit($filePath, $log->id, $this->admin->id, [], $resolusi);

            $this->assertSame(1, $hasil['sukses']);
            $this->assertSame(0, $hasil['gagal']);
            $this->assertTrue(SkuVariant::where('sku', 'SKU-DUP-TEST-CUSTOM')->exists());
        } finally {
            @unlink($filePath);
        }
    }
}
