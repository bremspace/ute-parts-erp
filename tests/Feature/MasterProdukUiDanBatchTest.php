<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Pos\Models\HargaTier;
use App\Modules\Rbac\Models\Cabang;
use App\Modules\Wms\Livewire\ProdukTab;
use App\Modules\Wms\Models\Gudang;
use App\Modules\Wms\Models\Produk;
use Database\Seeders\AkunCoaSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class MasterProdukUiDanBatchTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Cabang $cabang;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(AkunCoaSeeder::class);

        $this->cabang = Cabang::create(['nama' => 'Cabang Test', 'kode' => 'CB-TEST', 'is_active' => true]);
        Gudang::create(['nama' => 'Gudang Pusat', 'kode' => 'GD-01', 'cabang_id' => $this->cabang->id, 'is_active' => true]);

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

    public function test_pagination_and_per_page_options(): void
    {
        for ($i = 1; $i <= 30; $i++) {
            Produk::create([
                'nama' => sprintf('Barang %02d', $i),
                'harga_beli' => 10000,
                'harga_jual_retail' => 15000,
                'is_active' => true,
            ]);
        }

        Livewire::test(ProdukTab::class)
            ->assertSet('perPage', 25)
            ->assertSee('Barang 01')
            ->assertSee('Barang 25')
            ->assertDontSee('Barang 26')
            ->set('perPage', 50)
            ->assertSee('Barang 26')
            ->assertSee('Barang 30');
    }

    public function test_copy_single_produk_and_batch_copy_with_tier_filter(): void
    {
        $p1 = Produk::create([
            'nama' => 'LCD iPhone 11',
            'harga_beli' => 200000,
            'harga_jual_retail' => 300000,
            'is_active' => true,
        ]);

        $p2 = Produk::create([
            'nama' => 'Baterai iPhone 11',
            'harga_beli' => 100000,
            'harga_jual_retail' => 150000,
            'is_active' => true,
        ]);

        // Harga tier reseller
        HargaTier::create([
            'produk_id' => $p1->id,
            'tipe_konsumen' => 'reseller',
            'harga' => 270000,
            'nominal_tetap' => 270000,
        ]);
        HargaTier::create([
            'produk_id' => $p2->id,
            'tipe_konsumen' => 'reseller',
            'harga' => 135000,
            'nominal_tetap' => 135000,
        ]);

        // Test single copy retail
        Livewire::test(ProdukTab::class)
            ->call('copySingleProduk', $p1->id)
            ->assertDispatched('salin-teks', function ($event, $params) {
                return str_contains($params['teks'], 'LCD iPhone 11')
                    && str_contains($params['teks'], '300.000');
            });

        // Test single copy reseller
        Livewire::test(ProdukTab::class)
            ->set('filterTierHarga', 'reseller')
            ->call('copySingleProduk', $p1->id)
            ->assertDispatched('salin-teks', function ($event, $params) {
                return str_contains($params['teks'], 'LCD iPhone 11')
                    && str_contains($params['teks'], '270.000');
            });

        // Test batch copy multiple items with reseller tier
        $comp = Livewire::test(ProdukTab::class)
            ->set('filterTierHarga', 'reseller')
            ->set('selectedProdukIds', [$p1->id, $p2->id])
            ->call('salinHargaTerpilih');

        $dispatched = $comp->effects['dispatches'] ?? [];
        $hasSalinTeks = false;
        foreach ($dispatched as $d) {
            if (($d['name'] ?? '') === 'salin-teks') {
                $hasSalinTeks = true;
                $teks = $d['params']['teks'] ?? '';
                $this->assertStringContainsString('LCD iPhone 11 - Rp 270.000', $teks);
                $this->assertStringContainsString('Baterai iPhone 11 - Rp 135.000', $teks);
            }
        }
        $this->assertTrue($hasSalinTeks, 'Event salin-teks tidak ditemukan di dispatches');
    }

    public function test_batch_delete_produk(): void
    {
        $p1 = Produk::create([
            'nama' => 'Produk Tanpa Riwayat 1',
            'harga_beli' => 10000,
            'harga_jual_retail' => 15000,
            'is_active' => true,
        ]);
        $p2 = Produk::create([
            'nama' => 'Produk Tanpa Riwayat 2',
            'harga_beli' => 20000,
            'harga_jual_retail' => 30000,
            'is_active' => true,
        ]);

        Livewire::test(ProdukTab::class)
            ->set('selectedProdukIds', [$p1->id, $p2->id])
            ->call('hapusProdukMasal')
            ->assertSet('selectedProdukIds', []);

        $this->assertDatabaseMissing('produk', ['id' => $p1->id]);
        $this->assertDatabaseMissing('produk', ['id' => $p2->id]);
    }
}
