<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Crm\Services\KonfigurasiService;
use App\Modules\Rbac\Livewire\SettingsRbac;
use App\Modules\Rbac\Models\Cabang;
use App\Modules\Wms\Livewire\ProdukTab;
use App\Modules\Wms\Models\Produk;
use App\Modules\Wms\Models\SkuVariant;
use App\Modules\Wms\Services\QrCodeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class WmsProdukHeaderSortFilterAndQrTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;

    protected Cabang $cabang;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cabang = Cabang::create([
            'nama' => 'Cabang Pusat',
            'kode' => 'CP',
            'is_pusat' => true,
            'is_active' => true,
        ]);

        session(['cabang_id' => $this->cabang->id]);

        $role = Role::firstOrCreate(['name' => 'super-admin']);
        Permission::firstOrCreate(['name' => 'pengaturan.manage']);
        Permission::firstOrCreate(['name' => 'wms.read']);
        Permission::firstOrCreate(['name' => 'wms.create']);
        Permission::firstOrCreate(['name' => 'wms.update']);
        $role->givePermissionTo(Permission::all());

        $this->superAdmin = User::create([
            'name' => 'Super Admin',
            'email' => 'super@uteparts.id',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);
        $this->superAdmin->assignRole('super-admin');
        $this->superAdmin->cabangs()->attach($this->cabang->id);
    }

    public function test_header_produk_memiliki_fitur_sort_dan_filter_kolom(): void
    {
        $this->actingAs($this->superAdmin);

        $p1 = Produk::create([
            'nama' => 'Baterai iPhone 11',
            'barcode' => 'BAT-IP11-001',
            'harga_beli' => 50000,
            'harga_jual_retail' => 120000,
            'is_active' => true,
        ]);

        $p2 = Produk::create([
            'nama' => 'LCD iPhone 13 Pro',
            'barcode' => 'LCD-IP13P-001',
            'harga_beli' => 300000,
            'harga_jual_retail' => 750000,
            'is_active' => true,
        ]);

        SkuVariant::create(['produk_id' => $p1->id, 'sku' => 'SKU-BAT-11']);
        SkuVariant::create(['produk_id' => $p2->id, 'sku' => 'SKU-LCD-13']);

        Livewire::test(ProdukTab::class)
            ->assertSee('Stok Total') // Kompatibilitas test
            ->assertSee('Produk')
            ->assertSee('SKU / Barcode')
            // Test Sorting ascending
            ->call('sortByColumn', 'harga_jual')
            ->assertSet('sortBy', 'harga_jual')
            // Test Filter SKU via header
            ->set('filterSku', 'LCD-IP13P')
            ->assertSee('LCD iPhone 13 Pro')
            ->assertDontSee('Baterai iPhone 11')
            // Reset filter SKU
            ->call('resetFilterKolom', 'sku')
            ->assertSet('filterSku', null)
            ->assertSee('Baterai iPhone 11')
            // Test Filter Range Harga
            ->set('filterMinHarga', 200000)
            ->assertSee('LCD iPhone 13 Pro')
            ->assertDontSee('Baterai iPhone 11')
            ->call('resetFilterKolom', 'harga')
            ->assertSee('Baterai iPhone 11');
    }

    public function test_kustomisasi_kolom_tabel_master_produk(): void
    {
        $this->actingAs($this->superAdmin);

        $comp = Livewire::test(ProdukTab::class)
            ->assertSee('Kolom')
            // Awalnya kolom brand tidak aktif
            ->assertSet('visibleColumns.brand', false)
            // Toggle kolom brand
            ->call('toggleColumn', 'brand')
            ->assertSet('visibleColumns.brand', true)
            ->assertSee('Brand & Kualitas', false)
            // Show all columns
            ->call('showAllColumns')
            ->assertSet('visibleColumns.kategori', true)
            ->assertSet('visibleColumns.harga_beli', true)
            ->assertSet('visibleColumns.reorder', true)
            ->assertSee('Parameter ROP')
            // Reset ke default
            ->call('resetColumns')
            ->assertSet('visibleColumns.brand', false)
            ->assertSet('visibleColumns.kategori', false);
    }

    public function test_pengaturan_label_qr_dan_struk_80mm_tersimpan_di_settings(): void
    {
        $this->actingAs($this->superAdmin);

        Livewire::test(SettingsRbac::class)
            ->set('activeTab', 'printer')
            ->assertSet('printerForm.label_preset', '33x15')
            ->assertSet('printerForm.label_width', 33.0)
            ->assertSet('printerForm.label_height', 15.0)
            ->assertSet('printerForm.struk_default_width', '80mm')
            // Ubah preset ke 40x20
            ->set('printerForm.label_preset', '40x20')
            ->assertSet('printerForm.label_width', 40.0)
            ->assertSet('printerForm.label_height', 20.0)
            // Kembalikan ke 33x15 dan simpan
            ->set('printerForm.label_preset', '33x15')
            ->set('printerForm.struk_default_width', '80mm')
            ->call('simpanPengaturanPrinter')
            ->assertDispatched('alert');

        $cfg = app(KonfigurasiService::class);
        $labelCfg = $cfg->getLabelQrConfig();
        $this->assertEquals(33.0, (float) $labelCfg['width']);
        $this->assertEquals(15.0, (float) $labelCfg['height']);

        $strukCfg = $cfg->getStrukConfig();
        $this->assertEquals('80mm', $strukCfg['default_width']);
    }

    public function test_cetak_label_menggunakan_qr_code_svg_dan_preset_33x15mm(): void
    {
        $this->actingAs($this->superAdmin);

        $p = Produk::create([
            'nama' => 'Kamera Belakang iPhone 12',
            'barcode' => 'CAM-IP12-001',
            'harga_jual_retail' => 450000,
            'is_active' => true,
        ]);
        SkuVariant::create(['produk_id' => $p->id, 'sku' => 'SKU-CAM-12']);

        $response = $this->get(route('barcode.print', ['ids' => $p->id]));
        $response->assertOk();
        $response->assertSee('33mm 15mm'); // Ukuran @page CSS
        $response->assertSee('CAM-IP12-001');
        $response->assertSee('Kamera Belakang iPhone 12');
        $response->assertSee('<svg', false); // Render vector SVG QR Code
    }

    public function test_qr_code_service_menghasilkan_svg_valid(): void
    {
        $qrService = app(QrCodeService::class);
        $svg = $qrService->generateSvg('TEST-QR-DATA', 100, 0);

        $this->assertStringContainsString('<svg', $svg);
        $this->assertStringContainsString('</svg>', $svg);
        $this->assertStringNotContainsString('<?xml', $svg); // Bersih dari xml declaration
    }
}
