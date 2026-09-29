<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Crm\Models\Pelanggan;
use App\Modules\Crm\Models\TierMembership;
use App\Modules\Pos\Livewire\PosKasir;
use App\Modules\Pos\Models\HargaTier;
use App\Modules\Rbac\Models\Cabang;
use App\Modules\Wms\Models\Gudang;
use App\Modules\Wms\Models\KategoriProduk;
use App\Modules\Wms\Models\Produk;
use App\Modules\Wms\Models\SkuVariant;
use App\Modules\Wms\Models\StokItem;
use App\Modules\Wms\Models\TipeHp;
use Database\Seeders\AkunCoaSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class PencarianOptimasiKatalogTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Cabang $cabang;

    private Gudang $gudang;

    private Pelanggan $pelanggan;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(AkunCoaSeeder::class);

        $this->cabang = Cabang::create(['nama' => 'Cabang Test', 'kode' => 'CBG-TST', 'is_active' => true]);
        $this->gudang = Gudang::create(['cabang_id' => $this->cabang->id, 'nama' => 'Gudang Test', 'kode' => 'GDG-TST', 'is_active' => true]);

        $this->user = User::create([
            'name' => 'Kasir Test',
            'email' => 'kasir@test.com',
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);
        $this->user->assignRole('admin-toko');
        $this->user->cabangs()->attach($this->cabang->id);
        session(['cabang_id' => $this->cabang->id]);

        $tier = TierMembership::create([
            'nama' => 'Gold',
            'kode' => 'GOLD',
            'diskon_persen' => 10,
            'is_active' => true,
        ]);

        $this->pelanggan = Pelanggan::create([
            'nama' => 'Budi Santoso',
            'telepon' => '08123456789',
            'email' => 'budi@test.com',
            'tier_membership_id' => $tier->id,
            'is_reseller' => false,
        ]);
    }

    public function test_scope_cari_pintar_mencari_barcode_sku_nama_tipe_hp(): void
    {
        $kategori = KategoriProduk::create(['nama' => 'LCD', 'slug' => 'lcd']);
        $tipeHp = TipeHp::create(['merk' => 'Xiaomi', 'model' => 'Redmi Note 10']);

        $p1 = Produk::create([
            'nama' => 'LCD Xiaomi Redmi Note 10 Original',
            'slug' => 'lcd-redmi-note-10',
            'barcode' => '8991234567890',
            'kategori_id' => $kategori->id,
            'harga_beli' => 100000,
            'harga_jual_retail' => 150000,
            'is_active' => true,
        ]);
        $p1->tipeHps()->attach($tipeHp->id);

        $p2 = Produk::create([
            'nama' => 'Baterai Samsung Galaxy A50',
            'slug' => 'baterai-samsung-a50',
            'barcode' => '8999876543210',
            'harga_beli' => 50000,
            'harga_jual_retail' => 80000,
            'is_active' => true,
        ]);

        $v2 = SkuVariant::create([
            'produk_id' => $p2->id,
            'sku' => 'BAT-SM-A50-ORIG',
            'barcode' => '8991112223334',
            'nama_varian' => 'Original',
            'harga_jual_retail' => 80000,
            'is_active' => true,
        ]);

        // 1. Cari exact barcode produk
        $resBarcode = Produk::cariPintar('8991234567890')->pluck('id')->all();
        $this->assertEquals([$p1->id], $resBarcode);

        // 2. Cari exact barcode varian
        $resVariantBarcode = Produk::cariPintar('8991112223334')->pluck('id')->all();
        $this->assertEquals([$p2->id], $resVariantBarcode);

        // 3. Cari SKU varian
        $resSku = Produk::cariPintar('BAT-SM-A50')->pluck('id')->all();
        $this->assertEquals([$p2->id], $resSku);

        // 4. Cari tipe HP
        $resTipeHp = Produk::cariPintar('Redmi Note 10')->pluck('id')->all();
        $this->assertEquals([$p1->id], $resTipeHp);

        // 5. Multi kata
        $resMulti = Produk::cariPintar('LCD Note 10')->pluck('id')->all();
        $this->assertEquals([$p1->id], $resMulti);
    }

    public function test_api_pos_produk_bebas_n_plus_one_dengan_stok_dan_harga_tier(): void
    {
        $this->actingAs($this->user, 'sanctum');

        // Buat 10 produk dengan stok dan harga tier
        for ($i = 1; $i <= 10; $i++) {
            $prod = Produk::create([
                'nama' => "Produk POS {$i}",
                'slug' => "produk-pos-{$i}",
                'barcode' => "BARCODE-{$i}",
                'harga_beli' => 10000,
                'harga_jual_retail' => 20000,
                'is_active' => true,
            ]);

            SkuVariant::create([
                'produk_id' => $prod->id,
                'sku' => "SKU-POS-{$i}",
                'harga_jual_retail' => 20000,
                'is_active' => true,
            ]);

            StokItem::create([
                'produk_id' => $prod->id,
                'gudang_id' => $this->gudang->id,
                'jumlah' => 10 + $i,
            ]);

            HargaTier::create([
                'produk_id' => $prod->id,
                'tier_membership_id' => $this->pelanggan->tier_membership_id,
                'tipe_konsumen' => 'retail',
                'nominal_tetap' => 18000,
                'harga' => 18000,
            ]);
        }

        // Test mode ringkas ?q=
        DB::enableQueryLog();
        DB::flushQueryLog();

        $responseRingkas = $this->getJson("/api/pos/produk?q=Produk&gudang_id={$this->gudang->id}&customer_id={$this->pelanggan->id}");
        $responseRingkas->assertOk();
        $responseRingkas->assertJsonCount(10, 'data');

        $stokQueriesRingkas = array_filter(DB::getQueryLog(), fn ($q) => str_contains($q['query'], 'stok_items'));
        $hargaTierQueriesRingkas = array_filter(DB::getQueryLog(), fn ($q) => str_contains($q['query'], 'harga_tier'));

        // Query stok dan harga tier wajib tepat 1x (batch aggregated, bukan N+1 per produk)
        $this->assertCount(1, $stokQueriesRingkas, 'Query stok_items harus tepat 1x (bebas N+1)');
        $this->assertCount(1, $hargaTierQueriesRingkas, 'Query harga_tier harus tepat 1x (bebas N+1)');

        // Test mode legacy ?search=
        DB::flushQueryLog();

        $responseLegacy = $this->getJson("/api/pos/produk?search=Produk&gudang_id={$this->gudang->id}&customer_id={$this->pelanggan->id}");
        $responseLegacy->assertOk();

        $stokQueriesLegacy = array_filter(DB::getQueryLog(), fn ($q) => str_contains($q['query'], 'stok_items'));
        $hargaTierQueriesLegacy = array_filter(DB::getQueryLog(), fn ($q) => str_contains($q['query'], 'harga_tier'));

        $this->assertCount(1, $stokQueriesLegacy, 'Query legacy stok_items harus tepat 1x (bebas N+1)');
        $this->assertCount(1, $hargaTierQueriesLegacy, 'Query legacy harga_tier harus tepat 1x (bebas N+1)');
    }

    public function test_pos_kasir_scan_enter_barcode_dan_sku_langsung_tambah_ke_keranjang(): void
    {
        $this->actingAs($this->user, 'web');

        $prod = Produk::create([
            'nama' => 'Kabel Data Type C',
            'slug' => 'kabel-data-type-c',
            'barcode' => '8997788990011',
            'harga_beli' => 5000,
            'harga_jual_retail' => 15000,
            'is_active' => true,
        ]);

        StokItem::create([
            'produk_id' => $prod->id,
            'gudang_id' => $this->gudang->id,
            'jumlah' => 25,
        ]);

        Livewire::test(PosKasir::class)
            ->set('selectedGudangId', $this->gudang->id)
            ->set('search', '8997788990011')
            ->call('scanEnter')
            ->assertSet('search', '')
            ->assertCount('cart', 1);
    }

    public function test_shop_katalog_bebas_n_plus_one_saat_customer_login(): void
    {
        $this->actingAs($this->pelanggan, 'customer');

        DB::enableQueryLog();
        DB::flushQueryLog();

        $response = $this->getJson('/api/shop/produk?search=Produk');
        $response->assertOk();

        // Cek bahwa query harga_tier dieksekusi tepat 1x (eager loaded batch)
        $hargaTierQueries = array_filter(DB::getQueryLog(), fn ($q) => str_contains($q['query'], 'harga_tier'));
        $this->assertLessThanOrEqual(1, count($hargaTierQueries), 'Katalog marketplace tidak boleh N+1 pada harga_tier');
    }

    public function test_wms_pencarian_stok_mencari_via_sku_dan_barcode(): void
    {
        $this->actingAs($this->user, 'sanctum');

        $prod = Produk::create([
            'nama' => 'Konektor Charger iPhone 12',
            'slug' => 'konektor-charger-iphone-12',
            'barcode' => '8995556667778',
            'harga_beli' => 12000,
            'harga_jual_retail' => 35000,
            'is_active' => true,
        ]);

        $variant = SkuVariant::create([
            'produk_id' => $prod->id,
            'sku' => 'CHG-IP12-ORIG',
            'barcode' => '8998889990001',
            'harga_jual_retail' => 35000,
            'is_active' => true,
        ]);

        StokItem::create([
            'produk_id' => $prod->id,
            'sku_variant_id' => $variant->id,
            'gudang_id' => $this->gudang->id,
            'jumlah' => 15,
        ]);

        // Cari via SKU
        $resSku = $this->getJson('/api/wms/stok?search=CHG-IP12');
        $resSku->assertOk();
        $this->assertTrue(collect($resSku->json('data.data'))->contains('produk_id', $prod->id));

        // Cari via Barcode Varian
        $resBarcode = $this->getJson('/api/wms/stok?search=8998889990001');
        $resBarcode->assertOk();
        $this->assertTrue(collect($resBarcode->json('data.data'))->contains('produk_id', $prod->id));
    }
}
