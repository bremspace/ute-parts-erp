<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Pos\Models\HargaTier;
use App\Modules\Rbac\Models\Cabang;
use App\Modules\Wms\Models\Gudang;
use App\Modules\Wms\Models\Produk;
use App\Modules\Wms\Models\StokItem;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PosMasterOfflineApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_request_is_rejected(): void
    {
        $response = $this->getJson('/api/pos/master-offline');
        $response->assertStatus(401);
    }

    public function test_pos_master_offline_returns_products_scoped_to_branch(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $cabang = Cabang::create([
            'nama' => 'Cabang Offline',
            'kode' => 'CBG-OFF',
            'alamat' => 'Jl. Merdeka No. 1',
            'telepon' => '08111111111',
        ]);

        $gudang = Gudang::create([
            'kode' => 'GDG-OFF-01',
            'nama' => 'Gudang Utama Cabang Offline',
            'cabang_id' => $cabang->id,
            'is_utama' => true,
        ]);

        $user = User::factory()->create();
        $user->assignRole('admin-toko');
        $user->cabangs()->attach($cabang->id);

        $produk = Produk::create([
            'nama' => 'Baterai iPhone 12',
            'barcode' => '8999999991234',
            'kategori' => 'Baterai',
            'harga_beli' => 150000,
            'harga_jual_retail' => 250000,
            'is_active' => true,
        ]);

        HargaTier::create([
            'produk_id' => $produk->id,
            'tipe_konsumen' => 'reseller',
            'harga' => 220000,
            'nominal_tetap' => 220000,
        ]);

        HargaTier::create([
            'produk_id' => $produk->id,
            'tipe_konsumen' => 'agen',
            'harga' => 200000,
            'nominal_tetap' => 200000,
        ]);

        StokItem::create([
            'produk_id' => $produk->id,
            'gudang_id' => $gudang->id,
            'jumlah' => 15,
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->withSession(['cabang_id' => $cabang->id])
            ->getJson('/api/pos/master-offline?cabang_id='.$cabang->id);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'data' => [
                'cabang_id' => $cabang->id,
            ],
        ]);

        $products = $response->json('data.products');
        $this->assertNotEmpty($products);

        $item = collect($products)->firstWhere('id', $produk->id);
        $this->assertNotNull($item);
        $this->assertEquals('Baterai iPhone 12', $item['nama']);
        $this->assertEquals('8999999991234', $item['barcode']);
        $this->assertEquals(250000.0, $item['harga_retail']);
        $this->assertEquals(220000.0, $item['harga_bengkel']);
        $this->assertEquals(200000.0, $item['harga_grosir']);
        $this->assertEquals(15, $item['stok_terakhir']);
    }
}
