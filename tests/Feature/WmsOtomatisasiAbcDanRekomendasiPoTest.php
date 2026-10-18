<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Rbac\Models\Cabang;
use App\Modules\Wms\Livewire\PoTab;
use App\Modules\Wms\Livewire\ProdukTab;
use App\Modules\Wms\Models\Gudang;
use App\Modules\Wms\Models\Produk;
use App\Modules\Wms\Models\StockMutationLog;
use App\Modules\Wms\Models\StokItem;
use App\Modules\Wms\Services\ProcurementService;
use Database\Seeders\AkunCoaSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class WmsOtomatisasiAbcDanRekomendasiPoTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Cabang $cabang;

    private Gudang $gudang;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(AkunCoaSeeder::class);

        $this->cabang = Cabang::create(['nama' => 'Cabang Test', 'kode' => 'CB-TEST', 'is_active' => true]);
        $this->gudang = Gudang::create(['nama' => 'Gudang Pusat', 'kode' => 'GD-01', 'cabang_id' => $this->cabang->id, 'is_active' => true]);

        $this->user = User::create([
            'name' => 'Super Admin Wms',
            'email' => 'admin-wms@test.com',
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);
        $this->user->assignRole('super-admin');
        $this->user->cabangs()->attach($this->cabang->id);

        session(['cabang_id' => $this->cabang->id]);
        $this->actingAs($this->user);
    }

    public function test_klasifikasi_abc_dan_parameter_wms_otomatis_oleh_sistem(): void
    {
        // 1. Buat produk fast moving (omzet tinggi)
        $pFast = Produk::create([
            'nama' => 'LCD Fast Moving Auto',
            'harga_beli' => 200000,
            'harga_jual_retail' => 300000,
            'abc_class' => 'B',
            'is_active' => true,
        ]);

        // 2. Buat produk slow moving
        $pSlow = Produk::create([
            'nama' => 'Baut Slow Moving Auto',
            'harga_beli' => 1000,
            'harga_jual_retail' => 2000,
            'abc_class' => 'B',
            'is_active' => true,
        ]);

        // Simulasikan mutasi keluar
        StockMutationLog::create([
            'produk_id' => $pFast->id,
            'gudang_id' => $this->gudang->id,
            'delta' => -90,
            'sumber' => 'pos',
            'terjadi_at' => now()->subDays(10),
        ]);

        StockMutationLog::create([
            'produk_id' => $pSlow->id,
            'gudang_id' => $this->gudang->id,
            'delta' => -1,
            'sumber' => 'pos',
            'terjadi_at' => now()->subDays(15),
        ]);

        // Jalankan artisan command wms:classify-abc
        $this->artisan('wms:classify-abc', ['--cabang' => $this->cabang->id, '--periode' => 90])
            ->assertSuccessful();

        $pFast->refresh();
        $pSlow->refresh();

        // Verifikasi pFast otomatis Kelas A dan parameter ROP terisi
        $this->assertEquals('A', $pFast->abc_class);
        $this->assertGreaterThan(0, $pFast->reorder_point);
        $this->assertGreaterThan(0, $pFast->max_stock);

        // Verifikasi pSlow otomatis Kelas C
        $this->assertEquals('C', $pSlow->abc_class);
    }

    public function test_produk_tab_livewire_auto_klasifikasi_dan_hitung_edit(): void
    {
        $p = Produk::create([
            'nama' => 'Konektor Fleksibel',
            'harga_beli' => 50000,
            'harga_jual_retail' => 80000,
            'abc_class' => 'B',
            'is_active' => true,
        ]);

        // Mutasi keluar 45 unit
        StockMutationLog::create([
            'produk_id' => $p->id,
            'gudang_id' => $this->gudang->id,
            'delta' => -45,
            'sumber' => 'pos',
            'terjadi_at' => now()->subDays(5),
        ]);

        // Test auto klasifikasi batch produk terpilih di Livewire ProdukTab
        Livewire::test(ProdukTab::class)
            ->set('selectedProdukIds', [$p->id])
            ->call('autoKlasifikasiAbcTerpilih')
            ->assertDispatched('alert', function ($event, $params) {
                $type = $params[0]['type'] ?? $params['type'] ?? '';
                $message = $params[0]['message'] ?? $params['message'] ?? '';

                return $type === 'success' && str_contains($message, 'Klasifikasi otomatis selesai');
            });

        // Test kalkulasi otomatis saat modal edit produk dibuka
        Livewire::test(ProdukTab::class)
            ->call('openEditProdukModal', $p->id)
            ->call('hitungOtomatisParameterEdit')
            ->assertSet('editProdukForm.abc_class', 'A')
            ->assertDispatched('alert', function ($event, $params) {
                $type = $params[0]['type'] ?? $params['type'] ?? '';
                $message = $params[0]['message'] ?? $params['message'] ?? '';

                return $type === 'success' && str_contains($message, 'Kelas A dihitung otomatis');
            });
    }

    public function test_rekomendasi_pengadaan_terarah_dan_masukkan_semua_ke_po(): void
    {
        $p1 = Produk::create([
            'nama' => 'LCD Krisis Stockout',
            'harga_beli' => 300000,
            'harga_jual_retail' => 500000,
            'abc_class' => 'A',
            'reorder_point' => 10,
            'min_stock' => 5,
            'max_stock' => 20,
            'is_ondemand' => false,
            'is_active' => true,
        ]);

        // Stok 0 (kritis)
        StokItem::create([
            'produk_id' => $p1->id,
            'gudang_id' => $this->gudang->id,
            'jumlah' => 0,
            'jumlah_minimum' => 5,
        ]);

        // Test ProcurementService getProcurementDataAndSummary sekali jalan
        $service = app(ProcurementService::class);
        $res = $service->getProcurementDataAndSummary($this->cabang->id, ['only_reorder' => true]);

        $this->assertArrayHasKey('recommendations', $res);
        $this->assertArrayHasKey('summary', $res);
        $this->assertEquals(1, $res['summary']['total_needs_order']);
        $this->assertEquals(1, $res['summary']['kritis_count']);

        $rec = $res['recommendations']->first();
        $this->assertEquals($p1->id, $rec['produk_id']);
        $this->assertEquals('ORDER', $rec['action']);
        $this->assertEquals(20, $rec['recommended_order']); // 20 - 0 = 20
        $this->assertEquals('KRITIS_KOSONG', $rec['urgensi']);

        // Test di PoTab: aksi masukkan semua rekomendasi ke form PO
        Livewire::test(PoTab::class)
            ->call('openProcurementModal')
            ->call('masukkanSemuaRekomendasiKePo')
            ->assertSet('showProcurementModal', false)
            ->assertSet('showPoModal', true)
            ->assertDispatched('alert', function ($event, $params) {
                $type = $params[0]['type'] ?? $params['type'] ?? '';
                $message = $params[0]['message'] ?? $params['message'] ?? '';

                return $type === 'success' && str_contains($message, 'berhasil dimasukkan ke form PO');
            });
    }
}
