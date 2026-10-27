<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Pos\Livewire\PosKasir;
use App\Modules\Rbac\Models\Cabang;
use App\Modules\Wms\Models\Gudang;
use App\Modules\Wms\Models\KategoriProduk;
use App\Modules\Wms\Models\Produk;
use App\Modules\Wms\Models\StokItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;
use Tests\TestCase;

class PosDraftAutoRestoreTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Cabang $cabang;

    private Gudang $gudang;

    private Produk $produk;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cabang = Cabang::create([
            'nama' => 'Cabang Uji Coba',
            'kode' => 'C01',
            'is_active' => true,
        ]);

        $this->gudang = Gudang::create([
            'nama' => 'Gudang Utama',
            'kode' => 'G01',
            'cabang_id' => $this->cabang->id,
            'is_active' => true,
        ]);

        $this->user = User::factory()->create();
        $this->user->cabangs()->attach($this->cabang->id);

        $kategori = KategoriProduk::create(['nama' => 'Sparepart']);
        $this->produk = Produk::create([
            'nama' => 'Baterai iPhone 11',
            'kategori_id' => $kategori->id,
            'harga_jual' => 150000,
            'is_active' => true,
        ]);

        StokItem::create([
            'produk_id' => $this->produk->id,
            'gudang_id' => $this->gudang->id,
            'jumlah' => 10,
        ]);

        session(['cabang_id' => $this->cabang->id]);
    }

    public function test_draft_keranjang_tersimpan_di_cache_dan_dapat_dipulihkan_saat_refresh(): void
    {
        config(['pos.test_auto_restore' => true]);

        $this->actingAs($this->user);

        // Kasir menambah produk ke keranjang dan membuka modal bayar QRIS
        $component = Livewire::test(PosKasir::class)
            ->set('selectedGudangId', $this->gudang->id)
            ->call('addToCart', $this->produk->id)
            ->call('openPaymentModal')
            ->set('metodeBayar', 'qris')
            ->set('qrisInvoice', 'INV-DRAFT-001')
            ->set('qrisMerchantName', 'Toko Sparepart');

        // Simulasi siklus dehydrate
        $component->call('simpanDraftCheckout');

        // Verifikasi cache draft tersimpan
        $cacheKey = "pos_draft_{$this->user->id}_{$this->cabang->id}";
        $draft = Cache::get($cacheKey);
        $this->assertIsArray($draft);
        $this->assertNotEmpty($draft['cart']);
        $this->assertTrue($draft['showPaymentModal']);
        $this->assertSame('qris', $draft['metodeBayar']);
        $this->assertSame('INV-DRAFT-001', $draft['qrisInvoice']);

        // Simulasi kasir refresh browser (mount ulang instance baru)
        $newComponent = Livewire::test(PosKasir::class);

        // Verifikasi state telah dipulihkan otomatis
        $cartRestored = $newComponent->get('cart');
        $this->assertNotEmpty($cartRestored);
        $this->assertTrue($newComponent->get('showPaymentModal'));
        $this->assertSame('qris', $newComponent->get('metodeBayar'));
        $this->assertSame('INV-DRAFT-001', $newComponent->get('qrisInvoice'));

        // Saat clearCart dipanggil, cache draft harus dihapus
        $newComponent->call('clearCart');
        $this->assertNull(Cache::get($cacheKey));
    }
}
