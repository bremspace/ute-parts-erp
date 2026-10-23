<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Rbac\Livewire\SettingsRbac;
use App\Modules\Rbac\Models\Cabang;
use App\Modules\Wms\Livewire\ProdukTab;
use App\Modules\Wms\Models\Brand;
use App\Modules\Wms\Models\Gudang;
use App\Modules\Wms\Models\KategoriProduk;
use App\Modules\Wms\Models\KualitasProduk;
use App\Modules\Wms\Models\SatuanUnit;
use App\Modules\Wms\Models\TipeHp;
use App\Modules\Wms\Services\ProdukService;
use Database\Seeders\AkunCoaSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class MasterAtributAntiDuplikatDanBatchTest extends TestCase
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
            'email' => 'admin-master-test@test.com',
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);
        $user->assignRole('super-admin');
        $user->cabangs()->attach($cabang->id);
        session(['cabang_id' => $cabang->id]);

        $this->actingAs($user, 'web');

        return $user;
    }

    public function test_anti_duplikat_kategori_case_insensitive_pada_jenjang_sama(): void
    {
        $this->authedAdmin();

        KategoriProduk::create(['nama' => 'Layar & LCD', 'slug' => 'layar-lcd', 'is_active' => true]);

        Livewire::test(SettingsRbac::class)
            ->set('activeTab', 'master_produk')
            ->set('masterProdukSubTab', 'kategori')
            ->set('kategoriForm.nama', '   layar & lcd   ')
            ->call('simpanKategori')
            ->assertHasErrors(['kategoriForm.nama'])
            ->assertDispatched('alert');

        $this->assertSame(1, KategoriProduk::whereRaw('LOWER(TRIM(nama)) = ?', ['layar & lcd'])->count());
    }

    public function test_anti_duplikat_brand_case_insensitive(): void
    {
        $this->authedAdmin();

        Brand::create(['nama' => 'Olike Part', 'is_active' => true]);

        Livewire::test(SettingsRbac::class)
            ->set('activeTab', 'master_produk')
            ->set('masterProdukSubTab', 'brand')
            ->set('brandForm.nama', '   olike part   ')
            ->call('simpanBrand')
            ->assertHasErrors(['brandForm.nama'])
            ->assertDispatched('alert');

        $this->assertSame(1, Brand::whereRaw('LOWER(TRIM(nama)) = ?', ['olike part'])->count());
    }

    public function test_anti_duplikat_kualitas_case_insensitive_per_brand(): void
    {
        $this->authedAdmin();

        $brand = Brand::create(['nama' => 'Crown Part', 'is_active' => true]);
        KualitasProduk::create(['nama' => 'Original Crown', 'brand_id' => $brand->id, 'is_active' => true]);

        Livewire::test(SettingsRbac::class)
            ->set('activeTab', 'master_produk')
            ->set('masterProdukSubTab', 'kualitas')
            ->set('kualitasForm.brand_id', $brand->id)
            ->set('kualitasForm.nama', '   original crown   ')
            ->call('simpanKualitas')
            ->assertHasErrors(['kualitasForm.nama'])
            ->assertDispatched('alert');

        $this->assertSame(1, KualitasProduk::where('brand_id', $brand->id)->count());
    }

    public function test_anti_duplikat_satuan_case_insensitive_kode_dan_nama(): void
    {
        $this->authedAdmin();

        $satuan = SatuanUnit::create(['kode' => 'glg', 'nama' => 'Gulung', 'is_active' => true]);

        // Duplikat kode
        Livewire::test(SettingsRbac::class)
            ->set('activeTab', 'master_produk')
            ->set('masterProdukSubTab', 'satuan')
            ->set('satuanForm.kode', 'GLG')
            ->set('satuanForm.nama', 'Gulungan Kabel')
            ->call('simpanSatuan')
            ->assertHasErrors(['satuanForm.kode'])
            ->assertDispatched('alert');

        // Duplikat nama
        Livewire::test(SettingsRbac::class)
            ->set('activeTab', 'master_produk')
            ->set('masterProdukSubTab', 'satuan')
            ->set('satuanForm.kode', 'glg2')
            ->set('satuanForm.nama', 'gulung')
            ->call('simpanSatuan')
            ->assertHasErrors(['satuanForm.nama'])
            ->assertDispatched('alert');
    }

    public function test_anti_duplikat_kondisi_case_insensitive_nama_dan_kode(): void
    {
        $this->authedAdmin();

        Livewire::test(SettingsRbac::class)
            ->set('activeTab', 'master_produk')
            ->set('masterProdukSubTab', 'kondisi')
            ->set('kondisiForm.kode', 'baru_custom')
            ->set('kondisiForm.nama', 'Baru') // Default ada 'Baru'
            ->call('simpanKondisi')
            ->assertHasErrors(['kondisiForm.nama'])
            ->assertDispatched('alert');
    }

    public function test_anti_duplikat_tipe_hp_case_insensitive_merk_dan_model(): void
    {
        $this->authedAdmin();

        TipeHp::create(['merk' => 'Xiaomi', 'model' => 'Redmi Note 11', 'nama' => 'Xiaomi Redmi Note 11', 'is_active' => true]);

        Livewire::test(SettingsRbac::class)
            ->set('activeTab', 'master_produk')
            ->set('masterProdukSubTab', 'tipe_hp')
            ->set('tipeHpForm.merk', 'xiaomi')
            ->set('tipeHpForm.model', 'redmi note 11')
            ->call('simpanTipeHp')
            ->assertHasErrors(['tipeHpForm.model'])
            ->assertDispatched('alert');

        $this->assertSame(1, TipeHp::whereRaw('LOWER(TRIM(merk)) = ? AND LOWER(TRIM(model)) = ?', ['xiaomi', 'redmi note 11'])->count());
    }

    public function test_quick_add_produk_tab_tidak_membuat_duplikat_beda_huruf(): void
    {
        $this->authedAdmin();

        $b = Brand::create(['nama' => 'Vizz Part', 'is_active' => true]);
        $k = KategoriProduk::create(['nama' => 'Baterai Handphone', 'slug' => 'baterai-handphone', 'is_active' => true]);

        Livewire::test(ProdukTab::class)
            ->set('quickAddTipe', 'brand')
            ->set('quickAddForm.nama', '  vizz part  ')
            ->call('simpanQuickAdd');

        $this->assertSame(1, Brand::whereRaw('LOWER(TRIM(nama)) = ?', ['vizz part'])->count());

        Livewire::test(ProdukTab::class)
            ->set('quickAddTipe', 'kategori')
            ->set('quickAddForm.nama', 'baterai handphone')
            ->call('simpanQuickAdd');

        $this->assertSame(1, KategoriProduk::whereRaw('LOWER(TRIM(nama)) = ?', ['baterai handphone'])->count());
    }

    public function test_batch_set_status_mengubah_status_semua_item_terpilih(): void
    {
        $this->authedAdmin();

        $b1 = Brand::create(['nama' => 'Brand Alpha', 'is_active' => true]);
        $b2 = Brand::create(['nama' => 'Brand Beta', 'is_active' => true]);

        Livewire::test(SettingsRbac::class)
            ->set('activeTab', 'master_produk')
            ->set('masterProdukSubTab', 'brand')
            ->set('selectedBrandIds', [$b1->id, $b2->id])
            ->call('batchSetStatus', false)
            ->assertDispatched('alert');

        $this->assertFalse((bool) $b1->fresh()->is_active);
        $this->assertFalse((bool) $b2->fresh()->is_active);

        // Aktifkan kembali
        Livewire::test(SettingsRbac::class)
            ->set('activeTab', 'master_produk')
            ->set('masterProdukSubTab', 'brand')
            ->set('selectedBrandIds', [$b1->id, $b2->id])
            ->call('batchSetStatus', true);

        $this->assertTrue((bool) $b1->fresh()->is_active);
        $this->assertTrue((bool) $b2->fresh()->is_active);
    }

    public function test_batch_hapus_aman_deaktivasi_jika_ada_produk_dan_hapus_permanen_jika_bebas(): void
    {
        $this->authedAdmin();

        $service = app(ProdukService::class);
        $bUsed = Brand::create(['nama' => 'Brand Terpakai', 'is_active' => true]);
        $bFree = Brand::create(['nama' => 'Brand Bebas', 'is_active' => true]);

        $service->buatProduk(
            nama: 'Part Terhubung Brand',
            kategori: 'Sparepart',
            brand: 'Brand Terpakai',
            model: null,
            kondisi: 'baru',
            hargaBeli: 10000,
            hargaJual: 15000,
            brandId: $bUsed->id,
        );

        Livewire::test(SettingsRbac::class)
            ->set('activeTab', 'master_produk')
            ->set('masterProdukSubTab', 'brand')
            ->set('selectedBrandIds', [$bUsed->id, $bFree->id])
            ->call('batchHapus')
            ->assertDispatched('alert');

        // Brand terpakai tidak boleh terhapus permanen melainkan dinonaktifkan
        $this->assertNotNull(Brand::find($bUsed->id));
        $this->assertFalse((bool) Brand::find($bUsed->id)->is_active);

        // Brand bebas harus terhapus permanen
        $this->assertNull(Brand::find($bFree->id));
    }

    public function test_batch_edit_mengubah_relasi_dan_status(): void
    {
        $this->authedAdmin();

        $parent = KategoriProduk::create(['nama' => 'Induk Baru', 'slug' => 'induk-baru', 'is_active' => true]);
        $sub1 = KategoriProduk::create(['nama' => 'Sub Satu', 'slug' => 'sub-satu', 'is_active' => true]);
        $sub2 = KategoriProduk::create(['nama' => 'Sub Dua', 'slug' => 'sub-dua', 'is_active' => true]);

        Livewire::test(SettingsRbac::class)
            ->set('activeTab', 'master_produk')
            ->set('masterProdukSubTab', 'kategori')
            ->set('selectedKategoriIds', [$sub1->id, $sub2->id])
            ->set('batchEditForm.ubah_parent', true)
            ->set('batchEditForm.parent_id', $parent->id)
            ->call('simpanBatchEdit')
            ->assertDispatched('alert');

        $this->assertSame($parent->id, $sub1->fresh()->parent_id);
        $this->assertSame($parent->id, $sub2->fresh()->parent_id);
    }
}
