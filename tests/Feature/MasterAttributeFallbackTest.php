<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Rbac\Livewire\SettingsRbac;
use App\Modules\Rbac\Models\Cabang;
use App\Modules\Rbac\Services\DatabaseManagementService;
use App\Modules\Servis\Models\JenisServis;
use App\Modules\Servis\Models\TiketServis;
use App\Modules\Wms\Models\Brand;
use App\Modules\Wms\Models\Gudang;
use App\Modules\Wms\Models\KategoriProduk;
use App\Modules\Wms\Models\KualitasProduk;
use App\Modules\Wms\Models\Produk;
use App\Modules\Wms\Models\SatuanUnit;
use App\Modules\Wms\Models\TipeHp;
use Database\Seeders\AkunCoaSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class MasterAttributeFallbackTest extends TestCase
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
            'email' => 'admin-fallback@test.com',
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);
        $user->assignRole('super-admin');
        $user->cabangs()->attach($cabang->id);
        session(['cabang_id' => $cabang->id]);

        $this->actingAs($user, 'web');

        return $user;
    }

    public function test_default_master_attributes_cannot_be_deleted_directly(): void
    {
        $this->authedAdmin();

        $defaultKat = KategoriProduk::getDefault();
        $defaultBrand = Brand::getDefault();
        $defaultKualitas = KualitasProduk::getDefault();
        $defaultSatuan = SatuanUnit::getDefault();
        $defaultTipe = TipeHp::getDefault();
        $defaultServis = JenisServis::getDefault();

        $this->expectException(DomainException::class);
        $defaultKat->delete();
    }

    public function test_kategori_deletion_migrates_referencing_products_to_default_umum(): void
    {
        $this->authedAdmin();

        $defaultKat = KategoriProduk::getDefault();
        $customKat = KategoriProduk::create([
            'nama' => 'Kategori Khusus',
            'slug' => 'kategori-khusus',
            'is_active' => true,
        ]);

        $produk = Produk::create([
            'nama' => 'Produk Tes Kategori',
            'slug' => 'produk-tes-kategori',
            'kategori' => $customKat->nama,
            'kategori_id' => $customKat->id,
            'satuan' => 'pcs',
            'harga_beli' => 10000,
            'harga_jual_retail' => 15000,
            'is_active' => true,
        ]);

        $this->assertEquals($customKat->id, $produk->kategori_id);

        Livewire::test(SettingsRbac::class)
            ->call('hapusKategori', $customKat->id);

        $produk->refresh();
        $this->assertEquals($defaultKat->id, $produk->kategori_id);
        $this->assertEquals($defaultKat->nama, $produk->kategori);
        $this->assertDatabaseMissing('kategori_produk', ['id' => $customKat->id]);
    }

    public function test_brand_deletion_migrates_referencing_products_to_default_tanpa_merek(): void
    {
        $this->authedAdmin();

        $defaultBrand = Brand::getDefault();
        $customBrand = Brand::create([
            'nama' => 'Brand Khusus',
            'is_active' => true,
        ]);

        $produk = Produk::create([
            'nama' => 'Produk Tes Brand',
            'slug' => 'produk-tes-brand',
            'brand_id' => $customBrand->id,
            'satuan' => 'pcs',
            'harga_beli' => 10000,
            'harga_jual_retail' => 15000,
            'is_active' => true,
        ]);

        $this->assertEquals($customBrand->id, $produk->brand_id);

        Livewire::test(SettingsRbac::class)
            ->call('hapusBrand', $customBrand->id);

        $produk->refresh();
        $this->assertEquals($defaultBrand->id, $produk->brand_id);
        $this->assertDatabaseMissing('brands', ['id' => $customBrand->id]);
    }

    public function test_kualitas_deletion_migrates_referencing_products_to_default_standar(): void
    {
        $this->authedAdmin();

        $defaultKualitas = KualitasProduk::getDefault();
        $customKualitas = KualitasProduk::create([
            'nama' => 'Kualitas Super',
            'is_active' => true,
        ]);

        $produk = Produk::create([
            'nama' => 'Produk Tes Kualitas',
            'slug' => 'produk-tes-kualitas',
            'kualitas_id' => $customKualitas->id,
            'satuan' => 'pcs',
            'harga_beli' => 10000,
            'harga_jual_retail' => 15000,
            'is_active' => true,
        ]);

        $this->assertEquals($customKualitas->id, $produk->kualitas_id);

        Livewire::test(SettingsRbac::class)
            ->call('hapusKualitas', $customKualitas->id);

        $produk->refresh();
        $this->assertEquals($defaultKualitas->id, $produk->kualitas_id);
        $this->assertDatabaseMissing('kualitas_produk', ['id' => $customKualitas->id]);
    }

    public function test_satuan_deletion_migrates_referencing_products_to_default_pcs(): void
    {
        $this->authedAdmin();

        $defaultSatuan = SatuanUnit::getDefault();
        $customSatuan = SatuanUnit::create([
            'kode' => 'lusin',
            'nama' => 'Lusin',
            'is_active' => true,
        ]);

        $produk = Produk::create([
            'nama' => 'Produk Tes Satuan',
            'slug' => 'produk-tes-satuan',
            'satuan' => 'lusin',
            'harga_beli' => 10000,
            'harga_jual_retail' => 15000,
            'is_active' => true,
        ]);

        Livewire::test(SettingsRbac::class)
            ->call('hapusSatuan', $customSatuan->id);

        $produk->refresh();
        $this->assertEquals($defaultSatuan->kode, $produk->satuan);
        $this->assertDatabaseMissing('satuan_unit', ['id' => $customSatuan->id]);
    }

    public function test_tipe_hp_deletion_migrates_referencing_products_to_default_universal(): void
    {
        $this->authedAdmin();

        $defaultTipe = TipeHp::getDefault();
        $customTipe = TipeHp::create([
            'merk' => 'BrandX',
            'model' => 'ModelY',
            'nama' => 'BrandX ModelY',
            'is_active' => true,
        ]);

        $produk = Produk::create([
            'nama' => 'Produk Tes Tipe HP',
            'slug' => 'produk-tes-tipe-hp',
            'satuan' => 'pcs',
            'harga_beli' => 10000,
            'harga_jual_retail' => 15000,
            'is_active' => true,
        ]);

        DB::table('kompatibilitas_produk_tipe_hp')->insert([
            'produk_id' => $produk->id,
            'tipe_hp_id' => $customTipe->id,
        ]);

        Livewire::test(SettingsRbac::class)
            ->call('hapusTipeHp', $customTipe->id);

        $this->assertDatabaseMissing('tipe_hp', ['id' => $customTipe->id]);
        $this->assertDatabaseHas('kompatibilitas_produk_tipe_hp', [
            'produk_id' => $produk->id,
            'tipe_hp_id' => $defaultTipe->id,
        ]);
    }

    public function test_jenis_servis_deletion_migrates_referencing_tickets_to_default_svc_umum(): void
    {
        $this->authedAdmin();

        $defaultServis = JenisServis::getDefault();
        $customServis = JenisServis::create([
            'nama' => 'Servis Kamera Khusus',
            'kode' => 'SVC-KAMERA',
            'durasi_garansi_hari' => 14,
            'biaya_jasa' => 80000,
            'is_active' => true,
        ]);

        $pelangganId = DB::table('pelanggan')->insertGetId([
            'nama' => 'Pelanggan Tes',
            'telepon' => '081234567890',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $tiket = TiketServis::create([
            'no_tiket' => 'SRV-TEST-001',
            'cabang_id' => session('cabang_id'),
            'pelanggan_id' => $pelangganId,
            'nama_pelanggan' => 'Pelanggan Tes',
            'jenis_servis_id' => $customServis->id,
            'jenis_hp' => 'Android',
            'keluhan' => 'Kamera buram',
            'status' => 'antrian',
        ]);

        $this->assertEquals($customServis->id, $tiket->jenis_servis_id);

        Livewire::test(SettingsRbac::class)
            ->call('hapusJenisServis', $customServis->id);

        $tiket->refresh();
        $this->assertEquals($defaultServis->id, $tiket->jenis_servis_id);
        $this->assertDatabaseMissing('jenis_servis', ['id' => $customServis->id]);
    }

    public function test_ensure_default_master_attributes_provisions_all_defaults(): void
    {
        $this->authedAdmin();

        $service = app(DatabaseManagementService::class);
        $service->ensureDefaultMasterAttributes();

        $this->assertDatabaseHas('kategori_produk', ['slug' => 'umum', 'is_default' => 1]);
        $this->assertDatabaseHas('brands', ['nama' => 'Tanpa Merek', 'is_default' => 1]);
        $this->assertDatabaseHas('kualitas_produk', ['nama' => 'Standar', 'brand_id' => null, 'is_default' => 1]);
        $this->assertDatabaseHas('satuan_unit', ['kode' => 'pcs', 'is_default' => 1]);
        $this->assertDatabaseHas('tipe_hp', ['merk' => 'Universal', 'model' => 'Semua Tipe HP', 'is_default' => 1]);
        $this->assertDatabaseHas('jenis_servis', ['kode' => 'SVC-UMUM', 'is_default' => 1]);
    }
}
