<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Wms\Livewire\ProdukTab;
use App\Modules\Wms\Models\Produk;
use App\Modules\Wms\Models\ProdukBarcode;
use App\Modules\Wms\Models\SkuVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MasterProdukMultiBarcodeScanTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Permission::firstOrCreate(['name' => 'wms.create', 'guard_name' => 'web']);
        $role = Role::firstOrCreate(['name' => 'Super Admin', 'guard_name' => 'web']);
        $role->givePermissionTo('wms.create');
    }

    public function test_scanner_gun_tambah_barcode_alt_otomatis_membersihkan_control_char_dan_mencegah_duplikat(): void
    {
        $user = User::factory()->create();
        $user->assignRole('Super Admin');

        $produk = Produk::create([
            'kode' => 'PRD-TEST-BC',
            'nama' => 'Flexible Volume Test',
            'slug' => 'flexible-volume-test',
            'kategori' => 'Sparepart',
            'barcode' => '899000111222',
            'harga_beli' => 15000,
            'harga_jual_retail' => 35000,
            'is_active' => true,
        ]);

        SkuVariant::create([
            'produk_id' => $produk->id,
            'sku' => 'FLX-VOL-01',
            'barcode' => '899000111222',
            'nama_varian' => 'Standard',
            'harga_beli' => 15000,
            'harga_jual' => 35000,
        ]);

        $this->actingAs($user);

        // Simulasi input dari physical scanner gun yang mengirim barcode diakhiri newline/CRLF
        $scannerRawInput1 = "SPL-SUPPLIER-9988\r\n";
        $scannerRawInput2 = "   ALT-BARCODE-5566\n";

        $component = Livewire::test(ProdukTab::class)
            ->call('openEditProdukModal', $produk->id)
            ->set('scanBarcodeAltEdit', $scannerRawInput1)
            ->call('tambahBarcodeAltDariScan', 'edit')
            ->assertSet('scanBarcodeAltEdit', '')
            ->assertSet('editProdukForm.barcode_alt_list', ['SPL-SUPPLIER-9988']);

        // Input kedua
        $component->set('scanBarcodeAltEdit', $scannerRawInput2)
            ->call('tambahBarcodeAltDariScan', 'edit')
            ->assertSet('editProdukForm.barcode_alt_list', ['SPL-SUPPLIER-9988', 'ALT-BARCODE-5566']);

        // Input barcode utama produk tidak boleh masuk ke alt
        $component->set('scanBarcodeAltEdit', "899000111222\r\n")
            ->call('tambahBarcodeAltDariScan', 'edit')
            ->assertSet('editProdukForm.barcode_alt_list', ['SPL-SUPPLIER-9988', 'ALT-BARCODE-5566']);

        // Simpan edit produk tanpa error 500
        $component->call('simpanEditProduk')
            ->assertHasNoErrors()
            ->assertSet('showEditProdukModal', false);

        $produk->refresh();
        $this->assertCount(2, $produk->barcode_alt);
        $this->assertEquals(['SPL-SUPPLIER-9988', 'ALT-BARCODE-5566'], array_values($produk->barcode_alt));

        // Pastikan tabel produk_barcodes ter-sinkronisasi sempurna
        $barcodes = ProdukBarcode::where('produk_id', $produk->id)->orderBy('urutan')->get();
        $this->assertCount(3, $barcodes);
        $this->assertEquals('899000111222', $barcodes[0]->barcode);
        $this->assertTrue((bool) $barcodes[0]->is_utama);
        $this->assertEquals('SPL-SUPPLIER-9988', $barcodes[1]->barcode);
        $this->assertFalse((bool) $barcodes[1]->is_utama);
        $this->assertEquals('ALT-BARCODE-5566', $barcodes[2]->barcode);
        $this->assertFalse((bool) $barcodes[2]->is_utama);
    }
}
