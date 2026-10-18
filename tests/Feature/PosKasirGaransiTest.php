<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Pos\Livewire\PosKasir;
use App\Modules\Pos\Models\Transaksi;
use App\Modules\Pos\Services\KasSesiState;
use App\Modules\Rbac\Models\Cabang;
use App\Modules\Wms\Models\Gudang;
use App\Modules\Wms\Models\KategoriProduk;
use App\Modules\Wms\Models\Produk;
use App\Modules\Wms\Models\StokItem;
use Database\Seeders\AkunCoaSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class PosKasirGaransiTest extends TestCase
{
    use RefreshDatabase;

    private User $kasir;

    private Cabang $cabang;

    private Gudang $gudang;

    private Produk $produk;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(AkunCoaSeeder::class);

        $this->cabang = Cabang::create([
            'kode' => 'CB01',
            'nama' => 'Cabang Kasir Garansi',
            'tipe' => 'cabang',
            'is_active' => true,
        ]);

        $this->gudang = Gudang::create([
            'kode' => 'GD-CB01',
            'nama' => 'Gudang Kasir CB01',
            'cabang_id' => $this->cabang->id,
            'is_active' => true,
        ]);

        $this->kasir = User::create([
            'name' => 'Kasir Garansi',
            'email' => 'kasir.garansi@uteparts.test',
            'password' => Hash::make('password'),
            'cabang_id' => $this->cabang->id,
        ]);
        $this->kasir->assignRole('kasir');

        $kategori = KategoriProduk::create([
            'nama' => 'LCD Touchscreen',
            'kode' => 'LCD',
        ]);

        $this->produk = Produk::create([
            'kategori_id' => $kategori->id,
            'kode' => 'PRD-LCD-01',
            'nama' => 'LCD iPhone 13 Pro',
            'sku' => 'LCD-IP13P',
            'harga_beli' => 1000000,
            'harga_jual' => 1500000,
            'is_active' => true,
        ]);

        StokItem::create([
            'gudang_id' => $this->gudang->id,
            'produk_id' => $this->produk->id,
            'jumlah' => 10,
        ]);

        session(['cabang_id' => $this->cabang->id]);

        app(KasSesiState::class)->bukaKas(
            saldoAwal: 100000,
            cabangId: $this->cabang->id,
            userId: $this->kasir->id,
            sumber: 'manual',
            akunSumberKode: '110-01'
        );
    }

    public function test_transaksi_kasir_menggunakan_garansi_default_3_hari(): void
    {
        $this->actingAs($this->kasir);

        Livewire::test(PosKasir::class)
            ->call('addToCart', $this->produk->id)
            ->call('openPaymentModal')
            ->assertSet('garansiPilihan', '3_hari')
            ->set('metodeBayar', 'tunai')
            ->set('jumlahBayar', '1.500.000')
            ->call('processTransaction')
            ->assertSet('showReceiptModal', true);

        $transaksi = Transaksi::latest('id')->first();
        $this->assertNotNull($transaksi);
        $this->assertSame(3, $transaksi->durasi_garansi_hari);
        $this->assertStringContainsString('3 Hari Toko', (string) $transaksi->garansi_teks);
        $this->assertSame(now()->addDays(3)->toDateString(), $transaksi->garansi_berakhir_pada?->toDateString());
    }

    public function test_transaksi_kasir_dapat_diatur_manual_ke_garansi_7_hari(): void
    {
        $this->actingAs($this->kasir);

        Livewire::test(PosKasir::class)
            ->call('addToCart', $this->produk->id)
            ->call('openPaymentModal')
            ->call('setGaransiPilihan', '7_hari')
            ->set('metodeBayar', 'tunai')
            ->set('jumlahBayar', '1.500.000')
            ->call('processTransaction')
            ->assertSet('showReceiptModal', true);

        $transaksi = Transaksi::latest('id')->first();
        $this->assertNotNull($transaksi);
        $this->assertSame(7, $transaksi->durasi_garansi_hari);
        $this->assertStringContainsString('7 Hari Toko', (string) $transaksi->garansi_teks);
        $this->assertSame(now()->addDays(7)->toDateString(), $transaksi->garansi_berakhir_pada?->toDateString());
    }

    public function test_transaksi_kasir_dapat_diatur_custom_garansi(): void
    {
        $this->actingAs($this->kasir);

        Livewire::test(PosKasir::class)
            ->call('addToCart', $this->produk->id)
            ->call('openPaymentModal')
            ->call('setGaransiPilihan', 'custom', 45)
            ->set('metodeBayar', 'tunai')
            ->set('jumlahBayar', '1.500.000')
            ->call('processTransaction')
            ->assertSet('showReceiptModal', true);

        $transaksi = Transaksi::latest('id')->first();
        $this->assertNotNull($transaksi);
        $this->assertSame(45, $transaksi->durasi_garansi_hari);
        $this->assertStringContainsString('45 Hari Toko', (string) $transaksi->garansi_teks);
        $this->assertSame(now()->addDays(45)->toDateString(), $transaksi->garansi_berakhir_pada?->toDateString());
    }

    public function test_ui_pos_kasir_memiliki_baris_shortcut_dan_tanpa_dropdown_gudang(): void
    {
        $this->actingAs($this->kasir);

        Livewire::test(PosKasir::class)
            ->assertDontSee('Pilih Gudang...')
            ->assertSee('Retur Konsumen')
            ->assertSee('Riwayat Transaksi')
            ->assertSee('Pesanan Online')
            ->assertSee('Bayar Servis');
    }
}
