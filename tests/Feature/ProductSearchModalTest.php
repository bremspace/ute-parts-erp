<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Rbac\Models\Cabang;
use App\Modules\Wms\Livewire\PoTab;
use App\Modules\Wms\Livewire\ProductSearchModal;
use App\Modules\Wms\Livewire\TransferTab;
use App\Modules\Wms\Models\Gudang;
use App\Modules\Wms\Models\Produk;
use App\Modules\Wms\Models\Supplier;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ProductSearchModalTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Cabang $cabang;

    private Gudang $gudang;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->cabang = Cabang::create([
            'kode' => 'CBG-01',
            'nama' => 'Cabang Utama',
            'is_active' => true,
        ]);

        $this->user = User::factory()->create();
        $this->user->assignRole('super-admin');
        $this->user->cabangs()->attach([$this->cabang->id]);
        $this->actingAs($this->user, 'web');

        $this->gudang = Gudang::create([
            'kode' => 'GDG-01',
            'nama' => 'Gudang Pusat',
            'cabang_id' => $this->cabang->id,
            'is_active' => true,
        ]);

        session(['cabang_id' => $this->cabang->id]);
    }

    public function test_modal_pencarian_produk_dapat_mencari_menggunakan_cari_pintar(): void
    {
        $p1 = Produk::create([
            'kode' => 'PRD-01',
            'nama' => 'LCD iPhone 11 Pro Original',
            'barcode' => '899123456781',
            'harga_beli' => 250000,
            'harga_jual' => 350000,
            'is_active' => true,
        ]);

        $p2 = Produk::create([
            'kode' => 'PRD-02',
            'nama' => 'Baterai Samsung S20',
            'barcode' => '899123456782',
            'harga_beli' => 120000,
            'harga_jual' => 180000,
            'is_active' => true,
        ]);

        Livewire::test(ProductSearchModal::class)
            ->assertSet('isOpen', false)
            ->dispatch('buka-pencarian-produk', targetIndex: 0, context: 'po')
            ->assertSet('isOpen', true)
            ->assertSet('targetIndex', 0)
            ->assertSet('context', 'po')
            ->set('search', 'iPhone 11')
            ->assertSee('LCD iPhone 11 Pro Original')
            ->assertDontSee('Baterai Samsung S20')
            ->call('pilihProduk', $p1->id)
            ->assertDispatched('produk-dipilih-global', [
                'targetIndex' => 0,
                'context' => 'po',
                'produkId' => $p1->id,
            ])
            ->assertSet('isOpen', false);
    }

    public function test_po_tab_menerima_event_produk_dipilih_global(): void
    {
        $supplier = Supplier::create(['nama' => 'Supplier Test', 'termin_hari' => 14]);
        $produk = Produk::create([
            'kode' => 'PRD-SPECIAL',
            'nama' => 'Kamera Belakang Xiaomi Note 10',
            'barcode' => '999888777',
            'harga_beli' => 75000,
            'harga_jual' => 110000,
            'is_active' => true,
        ]);

        Livewire::test(PoTab::class)
            ->call('openPoModal')
            ->dispatch('produk-dipilih-global', [
                'targetIndex' => 0,
                'context' => 'po',
                'produkId' => $produk->id,
            ])
            ->assertSet('poForm.items.0.produk_id', $produk->id)
            ->assertSet('poForm.items.0.harga_beli', 75000.0);
    }

    public function test_transfer_tab_menerima_event_produk_dipilih_global(): void
    {
        $gudangTujuan = Gudang::create([
            'kode' => 'GDG-02',
            'nama' => 'Gudang Cabang 2',
            'cabang_id' => $this->cabang->id,
            'is_active' => true,
        ]);

        $produk = Produk::create([
            'kode' => 'PRD-TRF',
            'nama' => 'Flex Ribbon Tombol Power',
            'barcode' => '777666555',
            'harga_beli' => 15000,
            'harga_jual' => 25000,
            'is_active' => true,
        ]);

        Livewire::test(TransferTab::class)
            ->call('openNewTransferModal')
            ->dispatch('produk-dipilih-global', [
                'targetIndex' => 0,
                'context' => 'transfer',
                'produkId' => $produk->id,
            ])
            ->assertSet('transferItems.0.produk_id', $produk->id);
    }

    public function test_po_tab_dapat_menerima_buka_pencarian_produk_dengan_named_args(): void
    {
        Livewire::test(PoTab::class)
            ->call('openPoModal')
            ->dispatch('buka-pencarian-produk', targetIndex: 0, context: 'po')
            ->assertDispatched('open-global-product-search');

        Livewire::test(TransferTab::class)
            ->call('openNewTransferModal')
            ->dispatch('buka-pencarian-produk', targetIndex: 0, context: 'transfer')
            ->assertDispatched('open-global-product-search');
    }

    public function test_modal_pencarian_produk_menampilkan_semua_produk_aktif_saat_pertama_dibuka(): void
    {
        $p = Produk::create([
            'kode' => 'PRD-NEW',
            'nama' => 'Speaker Earpiece iPhone 12',
            'barcode' => '899123456799',
            'harga_beli' => 45000,
            'harga_jual' => 70000,
            'is_active' => true,
        ]);

        Livewire::test(ProductSearchModal::class)
            ->dispatch('buka-pencarian-produk', targetIndex: 0, context: 'po')
            ->assertSet('isOpen', true)
            ->assertSee('Speaker Earpiece iPhone 12')
            ->assertSee('Cari Produk');
    }

    public function test_po_tab_dan_transfer_tab_menangani_produk_dipilih_global_dengan_named_args(): void
    {
        $supplier = Supplier::create(['nama' => 'Supplier Test 2', 'termin_hari' => 14]);
        $produk = Produk::create([
            'kode' => 'PRD-NAMED',
            'nama' => 'Kaca Kamera Belakang iPhone 12',
            'barcode' => '999888111',
            'harga_beli' => 20000,
            'harga_jual' => 35000,
            'is_active' => true,
        ]);

        Livewire::test(PoTab::class)
            ->call('openPoModal')
            ->dispatch('produk-dipilih-global', targetIndex: 0, context: 'po', produkId: $produk->id)
            ->assertSet('poForm.items.0.produk_id', $produk->id)
            ->assertSet('poForm.items.0.produk_nama', 'Kaca Kamera Belakang iPhone 12')
            ->assertSet('poForm.items.0.harga_beli', 20000.0);

        Livewire::test(TransferTab::class)
            ->call('openNewTransferModal')
            ->dispatch('produk-dipilih-global', targetIndex: 0, context: 'transfer', produkId: $produk->id)
            ->assertSet('transferItems.0.produk_id', $produk->id)
            ->assertSet('transferItems.0.produk_nama', 'Kaca Kamera Belakang iPhone 12');
    }
}
