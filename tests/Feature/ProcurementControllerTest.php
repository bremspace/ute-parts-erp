<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Rbac\Models\Cabang;
use App\Modules\Wms\Livewire\PoTab;
use App\Modules\Wms\Livewire\ProdukTab;
use App\Modules\Wms\Models\Gudang;
use App\Modules\Wms\Models\Produk;
use App\Modules\Wms\Models\StokItem;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ProcurementControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Cabang $cabang;

    protected Gudang $gudang;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->cabang = Cabang::create([
            'kode' => 'CB01',
            'nama' => 'Cabang Pusat',
            'alamat' => 'Jakarta',
            'is_active' => true,
        ]);

        $this->gudang = Gudang::create([
            'cabang_id' => $this->cabang->id,
            'nama' => 'Gudang Pusat',
            'kode' => 'GD01',
            'is_active' => true,
        ]);

        $this->user = User::factory()->create();
        $this->user->givePermissionTo('wms.view');
    }

    public function test_api_recommendations_endpoint_returns_json_and_filters(): void
    {
        Produk::create([
            'nama' => 'Produk A Fast',
            'kategori' => 'LCD',
            'harga_beli' => 500000,
            'harga_jual_retail' => 750000,
            'abc_class' => 'A',
            'reorder_point' => 10,
            'min_stock' => 5,
            'max_stock' => 25,
            'is_ondemand' => false,
            'is_active' => true,
        ]);

        Produk::create([
            'nama' => 'Produk B Normal',
            'kategori' => 'Baterai',
            'harga_beli' => 100000,
            'harga_jual_retail' => 150000,
            'abc_class' => 'B',
            'reorder_point' => 5,
            'min_stock' => 3,
            'max_stock' => 10,
            'is_ondemand' => false,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->user)
            ->withSession(['cabang_id' => $this->cabang->id])
            ->getJson('/api/wms/procurement/recommendations?abc_class=A');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Rekomendasi pengadaan berhasil dimuat',
            ]);

        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals('A', $data[0]['abc_class']);
        $this->assertEquals('Produk A Fast', $data[0]['nama']);
    }

    public function test_api_summary_endpoint(): void
    {
        $response = $this->actingAs($this->user)
            ->withSession(['cabang_id' => $this->cabang->id])
            ->getJson('/api/wms/procurement/summary');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Ringkasan pengadaan berhasil dimuat',
            ])
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'total_sku',
                    'total_needs_order',
                    'total_estimasi_biaya',
                    'breakdown' => [
                        'A', 'B', 'C', 'JIT',
                    ],
                ],
            ]);
    }

    public function test_livewire_po_tab_procurement_modal_dan_terapkan_ke_po(): void
    {
        $produk = Produk::create([
            'nama' => 'LCD Test Order',
            'kategori' => 'LCD',
            'harga_beli' => 250000,
            'harga_jual_retail' => 400000,
            'abc_class' => 'A',
            'reorder_point' => 10,
            'max_stock' => 20,
            'is_ondemand' => false,
            'is_active' => true,
        ]);

        StokItem::create([
            'produk_id' => $produk->id,
            'gudang_id' => $this->gudang->id,
            'jumlah' => 2,
            'jumlah_minimum' => 5,
        ]);

        session(['cabang_id' => $this->cabang->id]);

        Livewire::actingAs($this->user)
            ->test(PoTab::class)
            ->dispatch('wms-procurement-modal')
            ->assertSet('showProcurementModal', true)
            ->call('terapkanKePo', $produk->id, 18)
            ->assertSet('showProcurementModal', false)
            ->assertSet('showPoModal', true)
            ->assertSet('poForm.items.0.produk_id', $produk->id)
            ->assertSet('poForm.items.0.jumlah', 18);
    }

    public function test_livewire_produk_tab_simpan_dan_update_konfigurasi_pengadaan(): void
    {
        $this->user->givePermissionTo('wms.create');

        session(['cabang_id' => $this->cabang->id]);

        $component = Livewire::actingAs($this->user)
            ->test(ProdukTab::class)
            ->set('produkForm.nama', 'LCD iPhone 14 Pro Max')
            ->set('produkForm.satuan_kode', 'pcs')
            ->set('produkForm.harga_beli', 1200000)
            ->set('produkForm.harga_jual_retail', 1800000)
            ->set('produkForm.harga_tier.retail.nominal_tetap', 1800000)
            ->set('produkForm.abc_class', 'A')
            ->set('produkForm.reorder_point', 12)
            ->set('produkForm.min_stock', 6)
            ->set('produkForm.max_stock', 30)
            ->set('produkForm.is_ondemand', false)
            ->call('simpanProduk')
            ->assertHasNoErrors();

        $produk = Produk::where('nama', 'LCD iPhone 14 Pro Max')->first();
        $this->assertNotNull($produk);
        $this->assertEquals('A', $produk->abc_class);
        $this->assertEquals(12, $produk->reorder_point);
        $this->assertEquals(6, $produk->min_stock);
        $this->assertEquals(30, $produk->max_stock);
        $this->assertFalse((bool) $produk->is_ondemand);

        // Test update melalui modal edit
        $component->call('openEditProdukModal', $produk->id)
            ->set('editProdukForm.abc_class', 'C')
            ->set('editProdukForm.is_ondemand', true)
            ->call('simpanEditProduk')
            ->assertHasNoErrors();

        $produk->refresh();
        $this->assertEquals('C', $produk->abc_class);
        $this->assertTrue((bool) $produk->is_ondemand);
    }
}
