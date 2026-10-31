<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Rbac\Livewire\SettingsRbac;
use App\Modules\Rbac\Models\Cabang;
use App\Modules\Wms\Models\Gudang;
use App\Modules\Wms\Models\KategoriProduk;
use App\Modules\Wms\Models\Produk;
use App\Modules\Wms\Models\SatuanUnit;
use App\Modules\Wms\Models\SkuVariant;
use App\Modules\Wms\Services\ExportMasterProdukService;
use App\Modules\Wms\Services\ImportProdukService;
use App\Modules\Wms\Services\ProdukService;
use App\Modules\Wms\Services\ScanResolverService;
use App\Modules\Wms\Services\SmartSearchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Maatwebsite\Excel\Facades\Excel;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class WmsMultiBarcodeDanAtributTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Cabang $cabang;

    private Gudang $gudang;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cabang = Cabang::create([
            'nama' => 'Cabang Utama Test',
            'kode' => 'CUT',
            'is_aktif' => true,
        ]);

        $this->gudang = Gudang::create([
            'cabang_id' => $this->cabang->id,
            'nama' => 'Gudang Utama Test',
            'kode' => 'GDG-CUT',
            'is_active' => true,
        ]);

        SatuanUnit::firstOrCreate(['kode' => 'pcs'], ['nama' => 'Pieces', 'is_active' => true]);
        KategoriProduk::firstOrCreate(['nama' => 'Umum'], ['slug' => 'umum', 'is_active' => true]);

        $role = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'wms.view', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'wms.create', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'pengaturan.manage', 'guard_name' => 'web']);
        $role->givePermissionTo(['wms.view', 'wms.create', 'pengaturan.manage']);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');
    }

    public function test_produk_model_multi_barcode_accessors_and_helper(): void
    {
        $produk = Produk::create([
            'nama' => 'Baterai iPhone 11 Pro',
            'slug' => 'baterai-iphone-11-pro',
            'satuan' => 'pcs',
            'barcode' => '8991234567890',
            'barcode_alt' => ['SUPP-8812', 'ALT-QR-9901'],
            'harga_beli' => 120000,
            'harga_jual_retail' => 180000,
            'is_active' => true,
        ]);

        $this->assertEquals(['8991234567890', 'SUPP-8812', 'ALT-QR-9901'], $produk->all_barcodes);
        $this->assertEquals('SUPP-8812, ALT-QR-9901', $produk->barcode_alt_string);
        $this->assertTrue($produk->hasBarcode('8991234567890'));
        $this->assertTrue($produk->hasBarcode('SUPP-8812'));
        $this->assertTrue($produk->hasBarcode('ALT-QR-9901'));
        $this->assertFalse($produk->hasBarcode('UNKNOWN-BARCODE'));
    }

    public function test_produk_service_opsi_penamaan_sku_dan_multi_barcode(): void
    {
        $service = app(ProdukService::class);

        // 1. Opsi 'barcode' -> SKU otomatis sama persis dengan barcode utama
        $p1 = $service->buatProduk(
            nama: 'LCD Samsung A51',
            kategori: 'LCD',
            brand: 'Samsung',
            model: 'A51',
            kondisi: 'baru',
            hargaBeli: 200000,
            hargaJual: 300000,
            barcode: 'BC-SAMS-A51',
            barcodeAlt: ['BC-SUPP-1', 'BC-SUPP-2'],
            opsiSku: 'barcode'
        );
        $sku1 = $p1->skuVariants->first()?->sku;
        $this->assertEquals('BC-SAMS-A51', $sku1);
        $this->assertEquals(['barcode_2' => 'BC-SUPP-1', 'barcode_3' => 'BC-SUPP-2'], $p1->barcode_alt);
        $this->assertEquals(['BC-SAMS-A51', 'BC-SUPP-1', 'BC-SUPP-2'], $p1->all_barcodes);

        // 2. Opsi 'otomatis' -> SKU otomatis digenerate sistem jika sku kosong
        $p2 = $service->buatProduk(
            nama: 'Kamera Belakang iPhone 12',
            kategori: 'Kamera',
            brand: 'Apple',
            model: 'iPhone 12',
            kondisi: 'baru',
            hargaBeli: 250000,
            hargaJual: 400000,
            barcode: 'BC-IP12-CAM',
            opsiSku: 'otomatis'
        );
        $sku2 = $p2->skuVariants->first()?->sku;
        $this->assertNotEmpty($sku2);
        $this->assertStringStartsWith('SKU-', $sku2);

        // 3. Opsi 'manual' -> SKU mengikuti input manual
        $p3 = $service->buatProduk(
            nama: 'Konektor Cas Type-C',
            kategori: 'Konektor',
            brand: 'Universal',
            model: null,
            kondisi: 'baru',
            hargaBeli: 5000,
            hargaJual: 20000,
            sku: 'MAN-CON-TYPEC',
            barcode: 'BC-CON-001',
            opsiSku: 'manual'
        );
        $sku3 = $p3->skuVariants->first()?->sku;
        $this->assertEquals('MAN-CON-TYPEC', $sku3);

        // 4. Update produk dengan barcode_alt dan opsiSku 'barcode'
        $updated = $service->updateProduk(
            produkId: $p3->id,
            nama: 'Konektor Cas Type-C Premium',
            kategori: 'Konektor',
            brand: 'Universal',
            model: null,
            kondisi: 'baru',
            hargaBeli: 5000,
            hargaJual: 20000,
            barcode: 'BC-CON-001-NEW',
            sku: 'MAN-CON-TYPEC',
            barcodeAlt: ['ALT-SUPP-100', 'ALT-SUPP-200'],
            opsiSku: 'barcode'
        );
        $this->assertEquals('BC-CON-001-NEW', $updated->skuVariants->first()?->sku);
        $this->assertEquals(['barcode_2' => 'ALT-SUPP-100', 'barcode_3' => 'ALT-SUPP-200'], $updated->barcode_alt);
    }

    public function test_scan_resolver_and_smart_search_resolve_multi_barcode(): void
    {
        $produk = Produk::create([
            'nama' => 'Fleksibel On Off Vivo V20',
            'slug' => 'fleksibel-on-off-vivo-v20',
            'satuan' => 'pcs',
            'barcode' => '899999000111',
            'barcode_alt' => ['ALT-VIVO-V20-1', 'QR-VIVO-SUPP-99'],
            'harga_beli' => 15000,
            'harga_jual_retail' => 45000,
            'is_active' => true,
        ]);
        SkuVariant::create([
            'produk_id' => $produk->id,
            'sku' => 'FLX-VIV-V20',
            'barcode' => '899999000111',
            'harga_beli' => 15000,
            'harga_jual_retail' => 45000,
            'is_active' => true,
        ]);
        $produk->syncBarcodes($produk->all_barcodes, 'FLX-VIV-V20');

        $resolver = app(ScanResolverService::class);
        $searcher = app(SmartSearchService::class);

        // 1. Resolve via Barcode Utama
        $resUtama = $resolver->resolveProduct('899999000111');
        $this->assertTrue($resUtama['found']);
        $this->assertEquals($produk->id, $resUtama['produk']->id);

        // 2. Resolve via Barcode Alternatif 1
        $resAlt1 = $resolver->resolveProduct('ALT-VIVO-V20-1');
        $this->assertTrue($resAlt1['found']);
        $this->assertEquals($produk->id, $resAlt1['produk']->id);

        // 3. Resolve via Barcode Alternatif 2
        $resAlt2 = $resolver->resolveProduct('QR-VIVO-SUPP-99');
        $this->assertTrue($resAlt2['found']);
        $this->assertEquals($produk->id, $resAlt2['produk']->id);

        // 4. SmartSearch via Barcode Alt
        $searchHits = $searcher->applySmartSearch(Produk::query(), 'QR-VIVO-SUPP-99')->get();
        $this->assertNotEmpty($searchHits);
        $this->assertEquals($produk->id, $searchHits->first()->id);
    }

    public function test_settings_rbac_export_template_dan_import_modal_target(): void
    {
        Excel::fake();
        $this->actingAs($this->admin);
        session(['cabang_id' => $this->cabang->id]);

        $component = Livewire::test(SettingsRbac::class)
            ->set('activeTab', 'master_produk')
            ->set('masterProdukSubTab', 'kategori')
            ->assertSee('+ Kategori Baru')
            ->call('exportAtributTarget', 'kategori', 'xlsx')
            ->call('downloadTemplateAtributTarget', 'kategori')
            ->call('openImportModalFor', 'kategori')
            ->assertSet('showAtributImportModal', true)
            ->assertSet('atributTarget', 'kategori')
            ->assertSet('atributStep', 'upload')
            ->call('closeAtributImportModal')
            ->assertSet('showAtributImportModal', false);

        Excel::assertDownloaded('template-atribut-kategori.xlsx');

        // Test export & template untuk rak di tab cabang
        $component->set('activeTab', 'cabang')
            ->assertSee('Export Rak')
            ->call('exportAtributTarget', 'rak', 'xlsx')
            ->call('downloadTemplateAtributTarget', 'rak');

        Excel::assertDownloaded('template-atribut-rak.xlsx');
    }

    public function test_export_and_import_master_produk_with_multi_barcode_and_opsi_sku(): void
    {
        $service = app(ImportProdukService::class);
        $exportService = app(ExportMasterProdukService::class);

        // 1. Simpan baris dengan barcode_alt dan opsi_sku: 'barcode'
        $row = [
            'sku' => '',
            'nama' => 'IC Power Xiaomi Note 10',
            'barcode' => 'BC-IC-PWR-10',
            'barcode_alt' => 'IC-SUPP-A, IC-SUPP-B',
            'opsi_sku' => 'barcode',
            'satuan' => 'pcs',
            'harga_beli' => 35000,
            'harga_jual' => 85000,
        ];

        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('prosesSatuBaris');
        $method->setAccessible(true);

        $res = $method->invoke($service, $row, 1, $this->admin->id, $this->cabang->id);
        $this->assertNotNull($res['produk_id']);
        $this->assertEquals('BC-IC-PWR-10', $res['sku']);

        $savedProduk = Produk::where('nama', 'IC Power Xiaomi Note 10')->first();
        $this->assertNotNull($savedProduk);
        $this->assertEquals('BC-IC-PWR-10', $savedProduk->barcode);
        $this->assertEquals(['barcode_2' => 'IC-SUPP-A', 'barcode_3' => 'IC-SUPP-B'], $savedProduk->barcode_alt);
        $this->assertEquals(['BC-IC-PWR-10', 'IC-SUPP-A', 'IC-SUPP-B'], $savedProduk->all_barcodes);
        $this->assertEquals('BC-IC-PWR-10', $savedProduk->skuVariants->first()?->sku);

        // 2. Export Master Produk memuat barcode_1, barcode_2, barcode_3, multi_barcode
        $exportRows = $exportService->buildMasterRows($this->cabang->id);
        $this->assertNotEmpty($exportRows);
        $rowP = collect($exportRows)->firstWhere('nama', 'IC Power Xiaomi Note 10');
        $this->assertNotNull($rowP);
        $this->assertEquals('BC-IC-PWR-10', $rowP['barcode_1']);
        $this->assertEquals('IC-SUPP-A', $rowP['barcode_2']);
        $this->assertEquals('IC-SUPP-B', $rowP['barcode_3']);

        // 3. Test import dengan format barcode_1, barcode_2, barcode_3, multi_barcode
        $rowMulti = [
            'sku' => 'SKU-LCD-ADVANCE',
            'nama' => 'LCD Advance Multi Barcode',
            'barcode_1' => 'BC1-ADVANCE-MAIN',
            'barcode_2' => 'BC2-ADVANCE-ALT1',
            'barcode_3' => 'BC3-ADVANCE-ALT2',
            'multi_barcode' => 'BC4-EXTRA-1, BC5-EXTRA-2',
            'satuan' => 'pcs',
            'harga_beli' => 100000,
            'harga_jual' => 200000,
        ];
        $resMulti = $method->invoke($service, $rowMulti, 2, $this->admin->id, $this->cabang->id);
        $this->assertEquals('SKU-LCD-ADVANCE', $resMulti['sku']);

        $savedMulti = Produk::with('barcodes')->where('nama', 'LCD Advance Multi Barcode')->first();
        $this->assertNotNull($savedMulti);
        $this->assertCount(5, $savedMulti->barcodes);
        $this->assertEquals('BC1-ADVANCE-MAIN', $savedMulti->barcode);
        $this->assertEquals('BC1-ADVANCE-MAIN', $savedMulti->barcodes->firstWhere('label', 'barcode_1')->barcode);
        $this->assertEquals('BC2-ADVANCE-ALT1', $savedMulti->barcodes->firstWhere('label', 'barcode_2')->barcode);
        $this->assertEquals('BC3-ADVANCE-ALT2', $savedMulti->barcodes->firstWhere('label', 'barcode_3')->barcode);
        $this->assertEquals('BC4-EXTRA-1', $savedMulti->barcodes->firstWhere('label', 'barcode_4')->barcode);
        $this->assertEquals('BC5-EXTRA-2', $savedMulti->barcodes->firstWhere('label', 'barcode_5')->barcode);
    }

    public function test_ratusan_barcode_merujuk_ke_sku_tepat(): void
    {
        $produk = Produk::create([
            'nama' => 'Fleksibel Fingerprint Universal',
            'slug' => 'fleksibel-fingerprint-universal',
            'satuan' => 'pcs',
            'harga_beli' => 10000,
            'harga_jual_retail' => 30000,
            'is_active' => true,
        ]);

        $variant = SkuVariant::create([
            'produk_id' => $produk->id,
            'sku' => 'FP-UNIV-999',
            'barcode' => 'BC-MAIN-000',
            'is_active' => true,
        ]);

        // Generate 150 barcode unik
        $barcodes = ['BC-MAIN-000'];
        for ($i = 2; $i <= 150; $i++) {
            $barcodes[] = "BC-SUPP-ALT-{$i}";
        }

        $produk->syncBarcodes($barcodes, 'FP-UNIV-999');

        $this->assertCount(150, $produk->barcodes);
        $this->assertEquals('BC-MAIN-000', $produk->fresh()->barcode);

        $resolver = app(ScanResolverService::class);

        // Scan barcode_1 (utama)
        $res1 = $resolver->resolveProduct('BC-MAIN-000');
        $this->assertTrue($res1['found']);
        $this->assertEquals('FP-UNIV-999', $res1['sku'] ?? $res1['variant']->sku);

        // Scan barcode_75 (tengah)
        $res75 = $resolver->resolveProduct('BC-SUPP-ALT-75');
        $this->assertTrue($res75['found']);
        $this->assertEquals('FP-UNIV-999', $res75['sku']);
        $this->assertEquals('barcode_75', $res75['barcode_label']);

        // Scan barcode_150 (terakhir)
        $res150 = $resolver->resolveProduct('BC-SUPP-ALT-150');
        $this->assertTrue($res150['found']);
        $this->assertEquals('FP-UNIV-999', $res150['sku']);
        $this->assertEquals('barcode_150', $res150['barcode_label']);
    }
}
