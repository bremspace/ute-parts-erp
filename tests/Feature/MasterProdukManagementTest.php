<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Crm\Services\KonfigurasiService;
use App\Modules\Rbac\Livewire\SettingsRbac;
use App\Modules\Rbac\Models\Cabang;
use App\Modules\Wms\Livewire\ProdukTab;
use App\Modules\Wms\Models\Brand;
use App\Modules\Wms\Models\Gudang;
use App\Modules\Wms\Models\KategoriProduk;
use App\Modules\Wms\Models\TipeHp;
use App\Modules\Wms\Services\ProdukService;
use Database\Seeders\AkunCoaSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class MasterProdukManagementTest extends TestCase
{
    use RefreshDatabase;

    private function authedAdmin(): User
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(AkunCoaSeeder::class);

        $cabang = Cabang::create(['nama' => 'Cabang Utama', 'kode' => 'CBG-01', 'is_active' => true]);
        Gudang::create(['cabang_id' => $cabang->id, 'nama' => 'Gudang Pusat', 'kode' => 'GDG-01', 'is_active' => true]);

        $user = User::create([
            'name' => 'Super Admin Test',
            'email' => 'admin-master@test.com',
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);
        $user->assignRole('super-admin');
        $user->cabangs()->attach($cabang->id);
        session(['cabang_id' => $cabang->id]);

        $this->actingAs($user, 'web');

        return $user;
    }

    public function test_settings_master_produk_tab_crud_kategori(): void
    {
        $this->authedAdmin();

        $comp = Livewire::test(SettingsRbac::class)
            ->set('activeTab', 'master_produk')
            ->set('masterProdukSubTab', 'kategori')
            ->assertSee('Katalog Kategori Berjenjang')
            ->call('openKategoriModal')
            ->set('kategoriForm.nama', 'Kategori Mesin Utama')
            ->set('kategoriForm.urutan', 1)
            ->call('simpanKategori');

        $kat = KategoriProduk::where('nama', 'Kategori Mesin Utama')->first();
        $this->assertNotNull($kat);
        $this->assertTrue($kat->is_active);

        // Edit
        $comp->call('openKategoriModal', $kat->id)
            ->set('kategoriForm.nama', 'Kategori Mesin & Komponen')
            ->call('simpanKategori');

        $this->assertEquals('Kategori Mesin & Komponen', $kat->fresh()->nama);

        // Toggle status
        $comp->call('toggleKategoriStatus', $kat->id);
        $this->assertFalse($kat->fresh()->is_active);

        // Delete
        $comp->call('hapusKategori', $kat->id);
        $this->assertNull(KategoriProduk::find($kat->id));
    }

    public function test_settings_master_produk_crud_brand_kualitas_satuan_kondisi_tipehp(): void
    {
        $this->authedAdmin();

        $comp = Livewire::test(SettingsRbac::class)
            // Brand
            ->call('openBrandModal')
            ->set('brandForm.nama', 'Foxconn OEM')
            ->call('simpanBrand')
            // Kualitas
            ->call('openKualitasModal')
            ->set('kualitasForm.nama', 'Grade AAA+')
            ->call('simpanKualitas')
            // Satuan
            ->call('openSatuanModal')
            ->set('satuanForm.kode', 'box-set')
            ->set('satuanForm.nama', 'Box Set Lengkap')
            ->call('simpanSatuan')
            // Kondisi
            ->call('openKondisiModal')
            ->set('kondisiForm.nama', 'Copotan Original Like New')
            ->call('simpanKondisi')
            // Tipe HP
            ->call('openTipeHpModal')
            ->set('tipeHpForm.merk', 'Google')
            ->set('tipeHpForm.model', 'Pixel 8 Pro')
            ->call('simpanTipeHp');

        $this->assertDatabaseHas('brands', ['nama' => 'Foxconn OEM']);
        $this->assertDatabaseHas('kualitas_produk', ['nama' => 'Grade AAA+']);
        $this->assertDatabaseHas('satuan_unit', ['kode' => 'box-set']);
        $this->assertDatabaseHas('tipe_hp', ['merk' => 'Google', 'model' => 'Pixel 8 Pro']);

        // Check kondisi list in Konfigurasi
        $configList = app(KonfigurasiService::class)->get('master_produk_kondisi_list');
        $kondisi = collect($configList)->firstWhere('nama', 'Copotan Original Like New');
        $this->assertNotNull($kondisi);
    }

    public function test_quick_add_in_produk_tab(): void
    {
        $this->authedAdmin();

        Livewire::test(ProdukTab::class)
            // Quick Add Kategori
            ->call('openQuickAdd', 'kategori')
            ->set('quickAddForm.nama', 'Kamera & Lensa Baru')
            ->call('simpanQuickAdd')
            ->assertSet('produkForm.kategori_id', fn ($val) => ! empty($val))
            // Quick Add Brand
            ->call('openQuickAdd', 'brand')
            ->set('quickAddForm.nama', 'Sony Sensor Corp')
            ->call('simpanQuickAdd')
            ->assertSet('produkForm.brand_id', fn ($val) => ! empty($val))
            // Quick Add Kualitas
            ->call('openQuickAdd', 'kualitas')
            ->set('quickAddForm.nama', 'Grade Platinum')
            ->call('simpanQuickAdd')
            ->assertSet('produkForm.kualitas_id', fn ($val) => ! empty($val))
            // Quick Add Satuan
            ->call('openQuickAdd', 'satuan')
            ->set('quickAddForm.nama', 'Roll Panjang')
            ->set('quickAddForm.kode', 'roll')
            ->call('simpanQuickAdd')
            ->assertSet('produkForm.satuan_kode', 'roll')
            // Quick Add Kondisi
            ->call('openQuickAdd', 'kondisi')
            ->set('quickAddForm.nama', 'Kondisi Swap Tested')
            ->call('simpanQuickAdd')
            ->assertSet('produkForm.kondisi', fn ($val) => ! empty($val))
            // Quick Add Tipe HP
            ->call('openQuickAdd', 'tipe_hp')
            ->set('quickAddForm.merk', 'Xiaomi')
            ->set('quickAddForm.model', '14 Ultra')
            ->call('simpanQuickAdd')
            ->assertSet('produkForm.brand_kompatibel', 'Xiaomi')
            ->assertSet('produkForm.model_kompatibel', '14 Ultra')
            ->assertSet('produkForm.tipe_hp_ids', fn ($ids) => count($ids) > 0);

        $this->assertDatabaseHas('kategori_produk', ['nama' => 'Kamera & Lensa Baru']);
        $this->assertDatabaseHas('brands', ['nama' => 'Sony Sensor Corp']);
        $this->assertDatabaseHas('kualitas_produk', ['nama' => 'Grade Platinum']);
        $this->assertDatabaseHas('satuan_unit', ['kode' => 'roll']);
        $this->assertDatabaseHas('tipe_hp', ['merk' => 'Xiaomi', 'model' => '14 Ultra']);
    }

    public function test_sinkronisasi_tipe_hp_dan_model_kompatibel_legacy(): void
    {
        $this->authedAdmin();

        $service = app(ProdukService::class);

        // Kasus 1: Input text brand & model legacy tanpa tipe_hp_ids -> otomatis dibuat & di-link TipeHp
        $p1 = $service->buatProduk(
            nama: 'Baterai Hippo iPhone 11',
            kategori: 'Baterai',
            brand: 'Apple',
            model: 'iPhone 11',
            kondisi: 'baru',
            hargaBeli: 120000,
            hargaJual: 180000
        );

        $this->assertEquals('Apple', $p1->brand_kompatibel);
        $this->assertEquals('iPhone 11', $p1->model_kompatibel);
        $this->assertCount(1, $p1->tipeHps);
        $this->assertEquals('Apple', $p1->tipeHps->first()->merk);
        $this->assertEquals('iPhone 11', $p1->tipeHps->first()->model);

        // Kasus 2: Input tipe_hp_ids tapi brand & model legacy kosong -> otomatis terisi dari TipeHp
        $tipeHp = TipeHp::firstOrCreate(
            ['merk' => 'Samsung', 'model' => 'Galaxy S24 Ultra'],
            ['nama' => 'Samsung Galaxy S24 Ultra', 'is_active' => true]
        );

        $p2 = $service->buatProduk(
            nama: 'LCD Samsung S24 Ultra OLED',
            kategori: 'LCD',
            brand: null,
            model: null,
            kondisi: 'baru',
            hargaBeli: 800000,
            hargaJual: 1100000,
            tipeHpIds: [$tipeHp->id]
        );

        $this->assertEquals('Samsung', $p2->fresh()->brand_kompatibel);
        $this->assertEquals('Galaxy S24 Ultra', $p2->fresh()->model_kompatibel);
        $this->assertTrue($p2->tipeHps->contains('id', $tipeHp->id));
    }
}
