<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Rbac\Models\Cabang;
use App\Modules\Wms\Livewire\ProdukTab;
use App\Modules\Wms\Models\Gudang;
use App\Modules\Wms\Models\Produk;
use App\Modules\Wms\Models\SkuVariant;
use App\Modules\Wms\Models\StokItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class MasterProdukEditStokDuplicateTest extends TestCase
{
    use RefreshDatabase;

    public function test_open_edit_produk_tidak_error_500_saat_ada_orphan_stok_item(): void
    {
        Permission::findOrCreate('wms.view');
        Permission::findOrCreate('wms.create');

        $cabang = Cabang::create([
            'nama' => 'Cabang Utama',
            'kode' => 'CBG-01',
            'is_active' => true,
        ]);

        $user = User::factory()->create();
        $user->givePermissionTo('wms.view');
        $user->givePermissionTo('wms.create');
        $this->actingAs($user);
        session(['cabang_id' => $cabang->id]);

        $gudang = Gudang::create([
            'cabang_id' => $cabang->id,
            'nama' => 'Gudang Utama',
            'kode' => 'GDG-01',
            'is_active' => true,
        ]);

        $produk = Produk::create([
            'nama' => 'Test Produk Multi Stok',
            'harga_beli' => 10000,
            'harga_jual_retail' => 15000,
            'kondisi' => 'baru',
            'satuan' => 'pcs',
        ]);

        $variant = SkuVariant::create([
            'produk_id' => $produk->id,
            'sku' => 'TEST-SKU-001',
            'harga_beli' => 10000,
            'harga_jual_retail' => 15000,
            'is_active' => true,
        ]);

        // Buat stok item valid
        StokItem::create([
            'produk_id' => $produk->id,
            'sku_variant_id' => $variant->id,
            'gudang_id' => $gudang->id,
            'jumlah' => 5,
            'jumlah_minimum' => 0,
        ]);

        // Buat orphan stok item (sku_variant_id null) simulasi bekas stock opname
        StokItem::create([
            'produk_id' => $produk->id,
            'sku_variant_id' => null,
            'gudang_id' => $gudang->id,
            'jumlah' => 0,
            'jumlah_minimum' => 0,
        ]);

        Livewire::test(ProdukTab::class)
            ->call('openEditProdukModal', $produk->id)
            ->assertSet('showEditProdukModal', true)
            ->assertSet('editProdukId', $produk->id)
            ->call('simpanEditProduk')
            ->assertHasNoErrors();

        // Pastikan tidak ada orphan tersisa
        $this->assertDatabaseMissing('stok_items', [
            'produk_id' => $produk->id,
            'sku_variant_id' => null,
        ]);
    }
}
