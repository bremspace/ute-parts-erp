<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserPreference;
use App\Modules\Akunting\Models\AkunCOA;
use App\Modules\Pos\Livewire\PosKasir;
use App\Modules\Rbac\Models\Cabang;
use App\Modules\Wms\Livewire\ProductSearchModal;
use App\Modules\Wms\Livewire\ProdukTab;
use App\Modules\Wms\Models\Brand;
use App\Modules\Wms\Models\Gudang;
use App\Modules\Wms\Models\KategoriProduk;
use App\Modules\Wms\Models\KualitasProduk;
use App\Modules\Wms\Models\Produk;
use App\Modules\Wms\Models\SkuVariant;
use App\Modules\Wms\Models\StokItem;
use App\Modules\Wms\Models\TipeHp;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ProductCustomFilterPreferenceTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Cabang $cabang;

    private Gudang $gudang;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        // Akun COA wajib untuk POS
        foreach ([
            '110-01' => ['Kas', 'aset', 'kas', 'debit'],
            '110-02' => ['Bank', 'aset', 'kas', 'debit'],
            '110-04' => ['Kas Laci', 'aset', 'kas', 'debit'],
            '120-01' => ['Piutang Usaha', 'aset', 'piutang_usaha', 'debit'],
            '130-01' => ['Persediaan', 'aset', 'persediaan', 'debit'],
            '220-01' => ['PPN Keluaran', 'kewajiban', 'pajak', 'kredit'],
            '410-01' => ['Pendapatan Penjualan', 'pendapatan', 'pendapatan_penjualan', 'kredit'],
            '510-02' => ['HPP', 'beban', 'hpp', 'debit'],
        ] as $kode => [$nama, $tipe, $kelompok, $saldo]) {
            AkunCOA::firstOrCreate(['kode' => $kode], [
                'nama' => $nama, 'tipe' => $tipe, 'kelompok' => $kelompok, 'saldo_normal' => $saldo,
            ]);
        }

        $this->cabang = Cabang::create([
            'kode' => 'CBG-01',
            'nama' => 'Cabang Pusat',
            'is_active' => true,
        ]);

        $this->gudang = Gudang::create([
            'kode' => 'GDG-01',
            'nama' => 'Gudang Pusat',
            'cabang_id' => $this->cabang->id,
            'is_active' => true,
        ]);

        $this->user = User::factory()->create();
        $this->user->assignRole('super-admin');
        $this->user->cabangs()->attach([$this->cabang->id]);

        $this->actingAs($this->user, 'web');
        session(['cabang_id' => $this->cabang->id]);
    }

    public function test_product_search_modal_toggle_filter_dan_persistensi(): void
    {
        Livewire::test(ProductSearchModal::class)
            ->call('toggleFilter', 'kategori')
            ->assertSet('visibleFilters.kategori', true)
            ->call('toggleFilter', 'stok')
            ->assertSet('visibleFilters.stok', true);

        // Cek tersimpan di DB
        $saved = UserPreference::getPref($this->user->id, 'product_search_custom_filters');
        $this->assertIsArray($saved);
        $this->assertTrue($saved['kategori']);
        $this->assertTrue($saved['stok']);
        $this->assertFalse($saved['brand']);

        // Komponen baru memuat preferensi yang sudah tersimpan
        Livewire::test(ProductSearchModal::class)
            ->assertSet('visibleFilters.kategori', true)
            ->assertSet('visibleFilters.stok', true)
            ->assertSet('visibleFilters.brand', false)
            ->set('showCustomFilterSettings', true)
            ->assertSeeHtml('wire:key="psm-chk-kategori-1"')
            ->assertSeeHtml('wire:key="psm-chk-brand-0"')
            ->call('resetCustomFilters')
            ->assertSet('visibleFilters.kategori', false)
            ->assertSet('visibleFilters.stok', false)
            ->assertSeeHtml('wire:key="psm-chk-kategori-0"')
            ->assertSeeHtml('wire:key="psm-chk-brand-0"');

        // Cek reset tersimpan di DB
        $savedAfterReset = UserPreference::getPref($this->user->id, 'product_search_custom_filters');
        $this->assertFalse($savedAfterReset['kategori']);
        $this->assertFalse($savedAfterReset['stok']);
    }

    public function test_produk_tab_toggle_filter_dan_persistensi(): void
    {
        Livewire::test(ProdukTab::class)
            ->call('toggleFilter', 'kualitas')
            ->assertSet('visibleFilters.kualitas', true)
            ->call('toggleFilter', 'tier')
            ->assertSet('visibleFilters.tier', false);

        // Cek tersimpan di DB
        $saved = UserPreference::getPref($this->user->id, 'wms_produk_tab_custom_filters');
        $this->assertIsArray($saved);
        $this->assertTrue($saved['kualitas']);
        $this->assertFalse($saved['tier']);

        // Komponen baru memuat preferensi
        Livewire::test(ProdukTab::class)
            ->assertSet('visibleFilters.kualitas', true)
            ->assertSet('visibleFilters.tier', false)
            ->set('showCustomFilterSettings', true)
            ->assertSeeHtml('wire:key="pt-chk-kualitas-1"')
            ->assertSeeHtml('wire:key="pt-chk-tier-0"')
            ->call('resetCustomFilters')
            ->assertSet('visibleFilters.kualitas', false)
            ->assertSet('visibleFilters.tier', true)
            ->assertSeeHtml('wire:key="pt-chk-kualitas-0"')
            ->assertSeeHtml('wire:key="pt-chk-tier-1"');

        $savedAfterReset = UserPreference::getPref($this->user->id, 'wms_produk_tab_custom_filters');
        $this->assertFalse($savedAfterReset['kualitas']);
        $this->assertTrue($savedAfterReset['tier']);
    }

    public function test_pos_kasir_toggle_filter_dan_persistensi(): void
    {
        Livewire::test(PosKasir::class)
            ->call('toggleFilter', 'kategori')
            ->assertSet('visibleFilters.kategori', true)
            ->call('toggleFilter', 'brand')
            ->assertSet('visibleFilters.brand', true);

        // Cek tersimpan di DB
        $saved = UserPreference::getPref($this->user->id, 'pos_kasir_custom_filters');
        $this->assertIsArray($saved);
        $this->assertTrue($saved['kategori']);
        $this->assertTrue($saved['brand']);
        $this->assertFalse($saved['kualitas']);

        // Komponen baru memuat preferensi
        Livewire::test(PosKasir::class)
            ->assertSet('visibleFilters.kategori', true)
            ->assertSet('visibleFilters.brand', true)
            ->assertSet('visibleFilters.kualitas', false)
            ->set('showCustomFilterSettings', true)
            ->assertSeeHtml('wire:key="pos-chk-kategori-1"')
            ->assertSeeHtml('wire:key="pos-chk-kualitas-0"')
            ->call('resetCustomFilters')
            ->assertSet('visibleFilters.kategori', false)
            ->assertSet('visibleFilters.brand', false)
            ->assertSeeHtml('wire:key="pos-chk-kategori-0"')
            ->assertSeeHtml('wire:key="pos-chk-kualitas-0"');

        $savedAfterReset = UserPreference::getPref($this->user->id, 'pos_kasir_custom_filters');
        $this->assertFalse($savedAfterReset['kategori']);
        $this->assertFalse($savedAfterReset['brand']);
    }

    public function test_pos_kasir_filter_dinamis_menyaring_produk(): void
    {
        $katLCD = KategoriProduk::firstOrCreate(['nama' => 'LCD Layar'], ['slug' => 'lcd-layar', 'is_active' => true]);
        $katBaterai = KategoriProduk::firstOrCreate(['nama' => 'Baterai HP'], ['slug' => 'baterai-hp', 'is_active' => true]);

        $brandApple = Brand::firstOrCreate(['nama' => 'Apple Custom Filter'], ['slug' => 'apple-filter', 'is_active' => true]);
        $brandSamsung = Brand::firstOrCreate(['nama' => 'Samsung Custom Filter'], ['slug' => 'samsung-filter', 'is_active' => true]);

        $kualOem = KualitasProduk::firstOrCreate(['nama' => 'OEM Custom'], ['slug' => 'oem-custom', 'is_active' => true]);
        $kualOri = KualitasProduk::firstOrCreate(['nama' => 'Original Custom'], ['slug' => 'original-custom', 'is_active' => true]);

        // Produk 1: LCD Apple OEM (Ready Stok 10)
        $p1 = Produk::create([
            'nama' => 'LCD iPhone 12 OEM',
            'slug' => 'lcd-iphone-12-oem',
            'kategori_id' => $katLCD->id,
            'brand_id' => $brandApple->id,
            'kualitas_id' => $kualOem->id,
            'harga_beli' => 200000,
            'harga_jual_retail' => 350000,
            'is_active' => true,
        ]);
        $v1 = SkuVariant::create(['produk_id' => $p1->id, 'nama_varian' => 'Black', 'sku' => 'IPH12-BLK', 'is_active' => true]);
        StokItem::create(['produk_id' => $p1->id, 'sku_variant_id' => $v1->id, 'gudang_id' => $this->gudang->id, 'jumlah' => 10]);

        // Produk 2: Baterai Samsung Original (Stok Kosong 0)
        $p2 = Produk::create([
            'nama' => 'Baterai Samsung S21 Original',
            'slug' => 'baterai-samsung-s21-ori',
            'kategori_id' => $katBaterai->id,
            'brand_id' => $brandSamsung->id,
            'kualitas_id' => $kualOri->id,
            'harga_beli' => 100000,
            'harga_jual_retail' => 200000,
            'is_active' => true,
        ]);
        $v2 = SkuVariant::create(['produk_id' => $p2->id, 'nama_varian' => 'Standar', 'sku' => 'BAT-S21', 'is_active' => true]);
        StokItem::create(['produk_id' => $p2->id, 'sku_variant_id' => $v2->id, 'gudang_id' => $this->gudang->id, 'jumlah' => 0]);

        // Uji Filter Kategori di POS
        Livewire::test(PosKasir::class)
            ->call('toggleFilter', 'kategori')
            ->set('filterKategoriId', $katLCD->id)
            ->assertSee('LCD iPhone 12 OEM')
            ->assertDontSee('Baterai Samsung S21 Original');

        // Uji Filter Brand di POS
        Livewire::test(PosKasir::class)
            ->call('toggleFilter', 'brand')
            ->set('filterBrandId', $brandSamsung->id)
            ->assertSee('Baterai Samsung S21 Original')
            ->assertDontSee('LCD iPhone 12 OEM');

        // Uji Filter Kualitas di POS
        Livewire::test(PosKasir::class)
            ->call('toggleFilter', 'kualitas')
            ->set('filterKualitasId', $kualOem->id)
            ->assertSee('LCD iPhone 12 OEM')
            ->assertDontSee('Baterai Samsung S21 Original');

        // Uji Filter Hanya Ready Stok di POS
        Livewire::test(PosKasir::class)
            ->call('toggleFilter', 'stok')
            ->set('filterHanyaReadyStok', true)
            ->assertSee('LCD iPhone 12 OEM')
            ->assertDontSee('Baterai Samsung S21 Original');
    }

    public function test_produk_tab_filter_dinamis_menyaring_produk(): void
    {
        $kat = KategoriProduk::firstOrCreate(['nama' => 'Speaker Audio Tab'], ['slug' => 'speaker-audio-tab', 'is_active' => true]);
        $brand = Brand::firstOrCreate(['nama' => 'Xiaomi Audio'], ['slug' => 'xiaomi-audio', 'is_active' => true]);
        $kualitas = KualitasProduk::firstOrCreate(['nama' => 'Grade A Tab'], ['slug' => 'grade-a-tab', 'is_active' => true]);

        $p1 = Produk::create([
            'nama' => 'Buzzer Xiaomi Note 9 Grade A',
            'slug' => 'buzzer-note-9',
            'kategori_id' => $kat->id,
            'brand_id' => $brand->id,
            'kualitas_id' => $kualitas->id,
            'harga_beli' => 15000,
            'harga_jual_retail' => 30000,
            'is_active' => true,
        ]);
        $v1 = SkuVariant::create(['produk_id' => $p1->id, 'nama_varian' => 'Standar', 'sku' => 'BZR-N9', 'is_active' => true]);
        StokItem::create(['produk_id' => $p1->id, 'sku_variant_id' => $v1->id, 'gudang_id' => $this->gudang->id, 'jumlah' => 5]);

        $p2 = Produk::create([
            'nama' => 'Kabel Data Type C Reguler',
            'slug' => 'kabel-type-c-reg',
            'harga_beli' => 5000,
            'harga_jual_retail' => 10000,
            'is_active' => true,
        ]);

        Livewire::test(ProdukTab::class)
            ->set('filterKategoriId', $kat->id)
            ->assertSee('Buzzer Xiaomi Note 9 Grade A')
            ->assertDontSee('Kabel Data Type C Reguler');

        Livewire::test(ProdukTab::class)
            ->set('filterBrandId', $brand->id)
            ->assertSee('Buzzer Xiaomi Note 9 Grade A')
            ->assertDontSee('Kabel Data Type C Reguler');

        Livewire::test(ProdukTab::class)
            ->call('toggleFilter', 'kualitas')
            ->set('filterKualitasId', $kualitas->id)
            ->assertSee('Buzzer Xiaomi Note 9 Grade A')
            ->assertDontSee('Kabel Data Type C Reguler');

        Livewire::test(ProdukTab::class)
            ->call('toggleFilter', 'stok')
            ->set('filterHanyaReadyStok', true)
            ->assertSee('Buzzer Xiaomi Note 9 Grade A')
            ->assertDontSee('Kabel Data Type C Reguler');
    }

    public function test_product_search_modal_filter_dinamis_menyaring_produk(): void
    {
        $kat = KategoriProduk::firstOrCreate(['nama' => 'Kamera Modal'], ['slug' => 'kamera-modal', 'is_active' => true]);
        $brand = Brand::firstOrCreate(['nama' => 'Oppo Modal'], ['slug' => 'oppo-modal', 'is_active' => true]);
        $kualitas = KualitasProduk::firstOrCreate(['nama' => 'Original Copotan Modal'], ['slug' => 'ori-copotan-modal', 'is_active' => true]);

        $p1 = Produk::create([
            'nama' => 'Kamera Depan Oppo Reno 4',
            'slug' => 'kamera-depan-reno-4',
            'kategori_id' => $kat->id,
            'brand_id' => $brand->id,
            'kualitas_id' => $kualitas->id,
            'harga_beli' => 40000,
            'harga_jual' => 75000,
            'is_active' => true,
        ]);
        $v1 = SkuVariant::create(['produk_id' => $p1->id, 'nama_varian' => 'Standar', 'sku' => 'CAM-R4', 'is_active' => true]);
        StokItem::create(['produk_id' => $p1->id, 'sku_variant_id' => $v1->id, 'gudang_id' => $this->gudang->id, 'jumlah' => 2]);

        $p2 = Produk::create([
            'nama' => 'Lem LCD T7000',
            'slug' => 'lem-lcd-t7000',
            'harga_beli' => 12000,
            'harga_jual' => 25000,
            'is_active' => true,
        ]);

        Livewire::test(ProductSearchModal::class)
            ->call('openModal')
            ->call('toggleFilter', 'kategori')
            ->set('filterKategoriId', $kat->id)
            ->assertSee('Kamera Depan Oppo Reno 4')
            ->assertDontSee('Lem LCD T7000');

        Livewire::test(ProductSearchModal::class)
            ->call('openModal')
            ->call('toggleFilter', 'brand')
            ->set('filterBrandId', $brand->id)
            ->assertSee('Kamera Depan Oppo Reno 4')
            ->assertDontSee('Lem LCD T7000');

        Livewire::test(ProductSearchModal::class)
            ->call('openModal')
            ->call('toggleFilter', 'kualitas')
            ->set('filterKualitasId', $kualitas->id)
            ->assertSee('Kamera Depan Oppo Reno 4')
            ->assertDontSee('Lem LCD T7000');

        Livewire::test(ProductSearchModal::class)
            ->call('openModal')
            ->call('toggleFilter', 'stok')
            ->set('filterHanyaReadyStok', true)
            ->assertSee('Kamera Depan Oppo Reno 4')
            ->assertDontSee('Lem LCD T7000');
    }

    public function test_filter_lengkap_identitas_produk_pada_semua_komponen(): void
    {
        $tipeHp = TipeHp::firstOrCreate(
            ['merk' => 'Infinix', 'model' => 'Hot 11 Play'],
            ['nama' => 'Infinix Hot 11 Play', 'is_active' => true]
        );

        $prodLengkap = Produk::create([
            'nama' => 'Papan Cas Infinix Hot 11 Play',
            'slug' => 'papan-cas-infinix-hot-11-play',
            'brand_kompatibel' => 'Infinix',
            'model_kompatibel' => 'Hot 11 Play',
            'kondisi' => 'oem',
            'satuan' => 'PCS',
            'abc_class' => 'A',
            'harga_beli' => 15000,
            'harga_jual_retail' => 35000,
            'is_active' => true,
        ]);
        $prodLengkap->tipeHps()->attach($tipeHp->id);

        $prodLain = Produk::create([
            'nama' => 'Obeng Plus Mini Reparasi',
            'slug' => 'obeng-plus-mini',
            'kondisi' => 'baru',
            'satuan' => 'SET',
            'abc_class' => 'C',
            'harga_beli' => 5000,
            'harga_jual_retail' => 12000,
            'is_active' => true,
        ]);

        // 1. Uji filter tipe HP pada ProductSearchModal
        Livewire::test(ProductSearchModal::class)
            ->call('openModal')
            ->call('toggleFilter', 'tipe_hp')
            ->set('filterTipeHpId', $tipeHp->id)
            ->assertSee('Papan Cas Infinix Hot 11 Play')
            ->assertDontSee('Obeng Plus Mini Reparasi');

        // 2. Uji filter brand kompatibel pada ProdukTab
        Livewire::test(ProdukTab::class)
            ->call('toggleFilter', 'brand_kompatibel')
            ->set('filterBrandKompatibel', 'Infinix')
            ->assertSee('Papan Cas Infinix Hot 11 Play')
            ->assertDontSee('Obeng Plus Mini Reparasi');

        // 3. Uji filter kondisi pada PosKasir
        Livewire::test(PosKasir::class)
            ->call('toggleFilter', 'kondisi')
            ->set('filterKondisi', 'oem')
            ->assertSee('Papan Cas Infinix Hot 11 Play')
            ->assertDontSee('Obeng Plus Mini Reparasi');

        // 4. Uji filter satuan pada PosKasir
        Livewire::test(PosKasir::class)
            ->call('toggleFilter', 'satuan')
            ->set('filterSatuan', 'PCS')
            ->assertSee('Papan Cas Infinix Hot 11 Play')
            ->assertDontSee('Obeng Plus Mini Reparasi');

        // 5. Uji filter abc_class pada ProdukTab
        Livewire::test(ProdukTab::class)
            ->call('toggleFilter', 'abc_class')
            ->set('filterAbcClass', 'A')
            ->assertSee('Papan Cas Infinix Hot 11 Play')
            ->assertDontSee('Obeng Plus Mini Reparasi');

        // 6. Uji pencarian pintar cariPintar mencakup identitas kompatibel & teks
        Livewire::test(ProdukTab::class)
            ->set('produkSearch', 'Infinix')
            ->assertSee('Papan Cas Infinix Hot 11 Play')
            ->assertDontSee('Obeng Plus Mini Reparasi');
    }
}
