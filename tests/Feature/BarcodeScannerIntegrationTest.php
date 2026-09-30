<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Akunting\Services\ExportLaporanService;
use App\Modules\Pos\Livewire\PosKasir;
use App\Modules\Rbac\Models\Cabang;
use App\Modules\Wms\Livewire\ProdukTab;
use App\Modules\Wms\Models\Gudang;
use App\Modules\Wms\Models\Produk;
use App\Modules\Wms\Models\SatuanUnit;
use App\Modules\Wms\Models\StokItem;
use App\Modules\Wms\Services\ImportProdukService;
use App\Modules\Wms\Services\ProdukService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class BarcodeScannerIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Cabang $cabang;

    protected Gudang $gudang;

    protected function setUp(): void
    {
        parent::setUp();

        // Setup Cabang & Gudang
        $this->cabang = Cabang::create([
            'kode' => 'CBG-TEST',
            'nama' => 'Cabang Test Barcode',
            'is_pusat' => true,
            'is_active' => true,
        ]);

        $this->gudang = Gudang::create([
            'cabang_id' => $this->cabang->id,
            'kode' => 'GDG-TEST',
            'nama' => 'Gudang Utama Test',
            'tipe' => 'utama',
            'is_active' => true,
        ]);

        // Setup Satuan Unit
        SatuanUnit::firstOrCreate(['kode' => 'pcs'], ['nama' => 'Pcs', 'is_active' => true]);

        // Setup Permissions & Super Admin User
        $permPos = Permission::firstOrCreate(['name' => 'pos.transaksi', 'guard_name' => 'web']);
        $permWms = Permission::firstOrCreate(['name' => 'wms.create', 'guard_name' => 'web']);
        $permLaporan = Permission::firstOrCreate(['name' => 'laporan.cabang', 'guard_name' => 'web']);

        $role = Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']);
        $role->givePermissionTo([$permPos, $permWms, $permLaporan]);

        $this->user = User::create([
            'name' => 'Super Admin Test',
            'email' => 'admin-barcode@test.com',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);
        $this->user->assignRole($role);
        $this->user->cabangs()->attach($this->cabang->id);

        $this->actingAs($this->user, 'web');
        session(['cabang_id' => $this->cabang->id]);
    }

    public function test_buat_dan_update_produk_dengan_barcode_pada_service(): void
    {
        $service = app(ProdukService::class);

        $produk = $service->buatProduk(
            nama: 'LCD Redmi Note 10',
            kategori: 'LCD',
            brand: 'Xiaomi',
            model: 'Redmi Note 10',
            kondisi: 'baru',
            hargaBeli: 150000,
            hargaJual: 250000,
            sku: 'LCD-RN10-001',
            satuanKode: 'pcs',
            barcode: '8991234567890'
        );

        $this->assertEquals('8991234567890', $produk->barcode);
        $variant = $produk->skuVariants()->first();
        $this->assertNotNull($variant);
        $this->assertEquals('8991234567890', $variant->barcode);

        // Update barcode produk
        $updated = $service->updateProduk(
            produkId: $produk->id,
            nama: 'LCD Redmi Note 10 Pro',
            kategori: 'LCD',
            brand: 'Xiaomi',
            model: 'Redmi Note 10 Pro',
            kondisi: 'baru',
            hargaBeli: 180000,
            hargaJual: 280000,
            barcode: '8991234567999'
        );

        $this->assertEquals('8991234567999', $updated->fresh()->barcode);
        $this->assertEquals('8991234567999', $updated->skuVariants()->first()->barcode);
    }

    public function test_produk_tab_livewire_dapat_input_dan_generate_barcode(): void
    {
        Livewire::test(ProdukTab::class)
            ->call('openProdukModal')
            ->call('generateBarcodeForm', 'create')
            ->assertSet('showProdukModal', true)
            ->set('produkForm.nama', 'Baterai iPhone 12 Original')
            ->set('produkForm.satuan_kode', 'pcs')
            ->set('produkForm.harga_beli', '120000')
            ->set('produkForm.harga_jual_retail', '220000')
            ->set('produkForm.barcode', '8999888777111')
            ->call('simpanProduk')
            ->assertDispatched('alert');

        $produk = Produk::where('barcode', '8999888777111')->first();
        $this->assertNotNull($produk);
        $this->assertEquals('Baterai iPhone 12 Original', $produk->nama);
        $this->assertEquals('8999888777111', $produk->skuVariants()->first()?->barcode);
    }

    public function test_pos_kasir_scan_barcode_direct_menambah_produk_ke_keranjang(): void
    {
        $service = app(ProdukService::class);
        $produk = $service->buatProduk(
            nama: 'Flexibel On/Off Oppo A15',
            kategori: 'Sparepart',
            brand: 'Oppo',
            model: 'A15',
            kondisi: 'baru',
            hargaBeli: 20000,
            hargaJual: 45000,
            sku: 'FLX-OPPO-A15',
            satuanKode: 'pcs',
            barcode: '8997776665554'
        );

        $variant = $produk->skuVariants()->first();

        // Berikan stok di gudang cabang
        StokItem::create([
            'produk_id' => $produk->id,
            'sku_variant_id' => $variant->id,
            'gudang_id' => $this->gudang->id,
            'jumlah' => 15,
            'jumlah_minimum' => 2,
        ]);

        Livewire::test(PosKasir::class)
            ->set('selectedGudangId', $this->gudang->id)
            ->call('scanBarcodeDirect', '8997776665554')
            ->assertDispatched('alert');

        // Pastikan keranjang berisi item tersebut
        $cartKey = "{$produk->id}-{$variant->id}";
        $component = Livewire::test(PosKasir::class)
            ->set('selectedGudangId', $this->gudang->id)
            ->call('scanBarcodeDirect', '8997776665554');

        $cart = $component->get('cart');
        $this->assertArrayHasKey($cartKey, $cart);
        $this->assertEquals(1, $cart[$cartKey]['qty']);
    }

    public function test_export_laporan_stok_menyertakan_kolom_barcode_dan_sku(): void
    {
        $service = app(ProdukService::class);
        $produk = $service->buatProduk(
            nama: 'Kamera Belakang Samsung A12',
            kategori: 'Kamera',
            brand: 'Samsung',
            model: 'Galaxy A12',
            kondisi: 'baru',
            hargaBeli: 80000,
            hargaJual: 140000,
            sku: 'CAM-A12-001',
            satuanKode: 'pcs',
            barcode: '8993332221110'
        );

        $variant = $produk->skuVariants()->first();

        StokItem::create([
            'produk_id' => $produk->id,
            'sku_variant_id' => $variant->id,
            'gudang_id' => $this->gudang->id,
            'jumlah' => 25,
            'jumlah_minimum' => 5,
        ]);

        $exportService = app(ExportLaporanService::class);
        $dataRefl = new \ReflectionMethod($exportService, 'dataStok');
        $dataRefl->setAccessible(true);
        $rows = $dataRefl->invoke($exportService, $this->cabang->id);

        $this->assertContains('BARCODE', $rows[2]);
        $this->assertContains('SKU', $rows[2]);

        $itemRow = collect($rows)->first(fn ($r) => is_array($r) && in_array('8993332221110', $r));
        $this->assertNotNull($itemRow);
        $this->assertEquals('8993332221110', $itemRow[0]);
        $this->assertEquals('CAM-A12-001', $itemRow[1]);
    }

    public function test_normalisasi_barcode_pada_import_produk_service(): void
    {
        $service = app(ImportProdukService::class);

        // Kasus notasi ilmiah dari Excel (cth: 8.99123E+12)
        $normalized1 = $service->normalizeBarcode('8.99123456789E+12');
        $this->assertEquals('8991234567890', substr($normalized1, 0, 13));

        // Kasus float (cth: 8991234500001.0)
        $normalized2 = $service->normalizeBarcode('8991234500001.0');
        $this->assertEquals('8991234500001', $normalized2);

        // Kasus normal string
        $normalized3 = $service->normalizeBarcode('  UTP-00123-ABCD  ');
        $this->assertEquals('UTP-00123-ABCD', $normalized3);
    }

    public function test_pos_kasir_scan_barcode_tidak_terdaftar_tidak_crash_dan_reset_search(): void
    {
        Livewire::test(PosKasir::class)
            ->set('selectedGudangId', $this->gudang->id)
            ->call('scanBarcodeDirect', '8999999999999-TIDAK-ADA')
            ->assertSet('search', '')
            ->assertDispatched('alert')
            ->assertDispatched('ute:barcode-not-found')
            ->assertOk();
    }
}
