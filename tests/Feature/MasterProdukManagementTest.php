<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Crm\Services\KonfigurasiService;
use App\Modules\Pos\Livewire\PosKasir;
use App\Modules\Rbac\Livewire\SettingsRbac;
use App\Modules\Rbac\Models\Cabang;
use App\Modules\Wms\Livewire\ProductSearchModal;
use App\Modules\Wms\Livewire\ProdukTab;
use App\Modules\Wms\Models\Brand;
use App\Modules\Wms\Models\Gudang;
use App\Modules\Wms\Models\KategoriProduk;
use App\Modules\Wms\Models\KualitasProduk;
use App\Modules\Wms\Models\Produk;
use App\Modules\Wms\Models\StokItem;
use App\Modules\Wms\Models\TipeHp;
use App\Modules\Wms\Services\ProdukService;
use Database\Seeders\AkunCoaSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
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

    public function test_edit_produk_sku_berhasil_dan_validasi_keunikan(): void
    {
        $this->authedAdmin();
        $service = app(ProdukService::class);

        $p1 = $service->buatProduk(
            nama: 'Produk A',
            kategori: 'Sparepart',
            brand: 'Apple',
            model: 'iPhone 11',
            kondisi: 'baru',
            hargaBeli: 50000,
            hargaJual: 75000,
            sku: 'SKU-AWAL-A'
        );

        $p2 = $service->buatProduk(
            nama: 'Produk B',
            kategori: 'Sparepart',
            brand: 'Apple',
            model: 'iPhone 12',
            kondisi: 'baru',
            hargaBeli: 60000,
            hargaJual: 90000,
            sku: 'SKU-AWAL-B'
        );

        Livewire::test(ProdukTab::class)
            ->call('openEditProdukModal', $p1->id)
            ->assertSet('editProdukForm.sku', 'SKU-AWAL-A')
            ->set('editProdukForm.sku', 'SKU-BARU-A')
            ->call('simpanEditProduk')
            ->assertHasNoErrors();

        $this->assertEquals('SKU-BARU-A', $p1->fresh()->skuVariants()->first()->sku);

        // Edit lagi tanpa mengubah SKU (tidak boleh kena error unique dirinya sendiri)
        Livewire::test(ProdukTab::class)
            ->call('openEditProdukModal', $p1->id)
            ->set('editProdukForm.nama', 'Produk A Diperbarui')
            ->call('simpanEditProduk')
            ->assertHasNoErrors();

        $this->assertEquals('Produk A Diperbarui', $p1->fresh()->nama);

        Livewire::test(ProdukTab::class)
            ->call('openEditProdukModal', $p1->id)
            ->set('editProdukForm.sku', 'SKU-AWAL-B')
            ->call('simpanEditProduk')
            ->assertHasErrors(['editProdukForm.sku']);
    }

    public function test_duplikasi_produk_berkali_kali_menghasilkan_penamaan_dan_sku_yang_rapi(): void
    {
        $this->authedAdmin();
        $service = app(ProdukService::class);

        $master = $service->buatProduk(
            nama: 'LCD iPhone 11 Pro',
            kategori: 'LCD',
            brand: 'Apple',
            model: 'iPhone 11 Pro',
            kondisi: 'baru',
            hargaBeli: 200000,
            hargaJual: 350000,
            sku: 'LCD-IP11PRO'
        );

        $tab = Livewire::test(ProdukTab::class);

        // Duplikasi ke-1
        $tab->call('duplikatProduk', $master->id);
        $copy1 = Produk::where('nama', 'LCD iPhone 11 Pro (Salinan)')->first();
        $this->assertNotNull($copy1);
        $this->assertStringStartsWith('LCD-IP11PRO-COPY-', $copy1->skuVariants->first()->sku);

        // Duplikasi ke-2 dari master asli -> harus jadi (Salinan 2)
        $tab->call('duplikatProduk', $master->id);
        $copy2 = Produk::where('nama', 'LCD iPhone 11 Pro (Salinan 2)')->first();
        $this->assertNotNull($copy2);
        $this->assertStringStartsWith('LCD-IP11PRO-COPY-', $copy2->skuVariants->first()->sku);

        // Duplikasi ke-3 dari produk salinan (copy2) -> tidak boleh jadi "... (Salinan 2) (Salinan)", melainkan (Salinan 3)
        $tab->call('duplikatProduk', $copy2->id);
        $copy3 = Produk::where('nama', 'LCD iPhone 11 Pro (Salinan 3)')->first();
        $this->assertNotNull($copy3);
        $this->assertStringStartsWith('LCD-IP11PRO-COPY-', $copy3->skuVariants->first()->sku);
        $this->assertStringNotContainsString('COPY-COPY', $copy3->skuVariants->first()->sku);
    }

    public function test_filter_kompatibilitas_interlinked_dan_multi_kompatibilitas(): void
    {
        $this->authedAdmin();
        $service = app(ProdukService::class);

        // Buat Tipe HP: Oppo A11 dan Realme 5
        $thOppo = TipeHp::create(['merk' => 'Oppo', 'model' => 'A11', 'is_active' => true]);
        $thRealme = TipeHp::create(['merk' => 'Realme', 'model' => '5', 'is_active' => true]);
        $thSamsung = TipeHp::create(['merk' => 'Samsung', 'model' => 'A50', 'is_active' => true]);

        // Produk 1: Multi-kompatibilitas (Oppo A11 dan Realme 5) lewat pivot tipeHps
        $pMulti = $service->buatProduk(
            nama: 'LCD Multi Oppo Realme',
            kategori: 'LCD',
            brand: 'Crown',
            model: null,
            kondisi: 'baru',
            hargaBeli: 120000,
            hargaJual: 180000,
            sku: 'LCD-OP-RL-01'
        );
        $pMulti->tipeHps()->attach([$thOppo->id, $thRealme->id]);

        // Produk 2: Samsung A50 (kompatibel lewat legacy string kolom)
        $pSamsung = $service->buatProduk(
            nama: 'LCD Samsung A50 Standar',
            kategori: 'LCD',
            brand: 'Crown',
            model: 'A50',
            kondisi: 'baru',
            hargaBeli: 150000,
            hargaJual: 220000,
            sku: 'LCD-SAM-A50'
        );
        $pSamsung->update([
            'brand_kompatibel' => 'Samsung',
            'model_kompatibel' => 'A50',
        ]);

        $testTab = Livewire::test(ProdukTab::class);

        // 1. Filter brand_kompatibel = Oppo -> harus memuat $pMulti
        $testTab->set('filterBrandKompatibel', 'Oppo');
        $produksOppo = $testTab->viewData('produks');
        $this->assertTrue($produksOppo->pluck('id')->contains($pMulti->id));
        $this->assertFalse($produksOppo->pluck('id')->contains($pSamsung->id));

        // 2. Filter brand_kompatibel = Realme -> juga harus memuat $pMulti karena multi-kompatibel
        $testTab->set('filterBrandKompatibel', 'Realme');
        $produksRealme = $testTab->viewData('produks');
        $this->assertTrue($produksRealme->pluck('id')->contains($pMulti->id));
        $this->assertFalse($produksRealme->pluck('id')->contains($pSamsung->id));

        // Model dropdown hanya berisi model Realme (Realme 5), tidak menampilkan model Samsung
        $modelsRealme = $testTab->viewData('modelKompatibelList');
        $this->assertContains('5', $modelsRealme);
        $this->assertNotContains('A50', $modelsRealme);

        // 3. Cascading: memilih filterTipeHpId = thSamsung otomatis mengisi brand & model kompatibel
        $testTab->set('filterTipeHpId', $thSamsung->id);
        $testTab->assertSet('filterBrandKompatibel', 'Samsung');
        $testTab->assertSet('filterModelKompatibel', 'A50');
        $produksSamsung = $testTab->viewData('produks');
        $this->assertTrue($produksSamsung->pluck('id')->contains($pSamsung->id));
        $this->assertFalse($produksSamsung->pluck('id')->contains($pMulti->id));
    }

    public function test_simpan_dan_edit_produk_otomatis_sinkron_kecocokan_tipe_hp(): void
    {
        $this->authedAdmin();
        $th = TipeHp::create(['merk' => 'Xiaomi', 'model' => 'Redmi Note 10', 'is_active' => true]);

        // Simpan produk baru hanya dengan memilih tipe_hp_ids (tanpa ketik manual brand/model)
        $tab = Livewire::test(ProdukTab::class)
            ->set('produkForm.nama', 'Baterai BN59 Redmi Note 10')
            ->set('produkForm.harga_beli', 45000)
            ->set('produkForm.harga_jual_retail', 85000)
            ->set('produkForm.tipe_hp_ids', [$th->id])
            ->call('simpanProduk')
            ->assertHasNoErrors();

        $prod = Produk::where('nama', 'Baterai BN59 Redmi Note 10')->first();
        $this->assertNotNull($prod);
        $this->assertEquals('Xiaomi', $prod->brand_kompatibel);
        $this->assertEquals('Redmi Note 10', $prod->model_kompatibel);
        $this->assertTrue($prod->tipeHps->pluck('id')->contains($th->id));

        // Edit produk: tambahkan tipe HP kedua (Poco M3)
        $th2 = TipeHp::create(['merk' => 'Poco', 'model' => 'M3', 'is_active' => true]);

        Livewire::test(ProdukTab::class)
            ->call('openEditProdukModal', $prod->id)
            ->set('editProdukForm.tipe_hp_ids', [$th->id, $th2->id])
            ->call('simpanEditProduk')
            ->assertHasNoErrors();

        $prodFresh = $prod->fresh();
        $this->assertCount(2, $prodFresh->tipeHps);
        $this->assertTrue($prodFresh->tipeHps->pluck('id')->contains($th2->id));
    }

    public function test_pemisahan_merek_dan_tipe_hp_serta_pencegahan_duplikat_kompatibel(): void
    {
        $this->authedAdmin();

        $oppo1 = TipeHp::create(['merk' => 'Oppo', 'model' => 'A15', 'is_active' => true]);
        $oppo2 = TipeHp::create(['merk' => 'Oppo', 'model' => 'A16', 'is_active' => true]);
        $vivo = TipeHp::create(['merk' => 'Vivo', 'model' => 'Y12', 'is_active' => true]);

        $component = Livewire::test(ProdukTab::class)
            ->call('openProdukModal')
            ->set('kompatibelMerekHp', 'Oppo');

        // Pilihan tipe HP hanya menampilkan model Oppo
        $createOpts = $component->viewData('tipeHpCreateOptions');
        $this->assertTrue($createOpts->pluck('id')->contains($oppo1->id));
        $this->assertTrue($createOpts->pluck('id')->contains($oppo2->id));
        $this->assertFalse($createOpts->pluck('id')->contains($vivo->id));

        // Tambah Oppo A15
        $component->set('kompatibelTipeHpId', $oppo1->id)
            ->call('tambahKompatibelKeForm', 'create')
            ->assertDispatched('alert', fn ($event, $params) => ($params[0]['type'] ?? null) === 'success');

        $this->assertEquals([$oppo1->id], $component->get('produkForm.tipe_hp_ids'));

        // Coba tambah Oppo A15 lagi (duplikat) -> harus ditolak & tidak boleh dobel
        $component->set('kompatibelTipeHpId', $oppo1->id)
            ->call('tambahKompatibelKeForm', 'create')
            ->assertDispatched('alert', fn ($event, $params) => ($params[0]['type'] ?? null) === 'warning' && str_contains($params[0]['message'] ?? '', 'sudah ada'));

        $this->assertEquals([$oppo1->id], $component->get('produkForm.tipe_hp_ids'));

        // Tambah tipe kedua: Oppo A16
        $component->set('kompatibelTipeHpId', $oppo2->id)
            ->call('tambahKompatibelKeForm', 'create');

        $this->assertEquals([$oppo1->id, $oppo2->id], $component->get('produkForm.tipe_hp_ids'));

        // Hapus salah satu tipe
        $component->call('hapusKompatibelDariForm', $oppo1->id, 'create');
        $this->assertEquals([$oppo2->id], $component->get('produkForm.tipe_hp_ids'));
    }

    public function test_fitur_warna_dan_keterangan_status_kondisi_sid_retail_pro(): void
    {
        $this->authedAdmin();

        $component = Livewire::test(ProdukTab::class);
        $kondisiList = collect($component->viewData('kondisiList'));

        $baru = $kondisiList->firstWhere('kode', 'baru');
        $this->assertNotNull($baru);
        $this->assertEquals('emerald', $baru['warna']);
        $this->assertStringContainsString('Segel Pabrik', $baru['keterangan']);

        $oem = $kondisiList->firstWhere('kode', 'oem');
        $this->assertNotNull($oem);
        $this->assertEquals('blue', $oem['warna']);
        $this->assertStringContainsString('Original Equipment Manufacturer', $oem['keterangan']);

        $bekas = $kondisiList->firstWhere('kode', 'bekas');
        $this->assertNotNull($bekas);
        $this->assertEquals('amber', $bekas['warna']);
        $this->assertStringContainsString('Copotan', $bekas['keterangan']);
    }

    public function test_quick_add_tipe_hp_dengan_dropdown_merk_dan_model_tersedia_atau_isi_baru(): void
    {
        $this->authedAdmin();

        TipeHp::create(['merk' => 'Infinix', 'model' => 'Hot 10', 'is_active' => true]);

        $component = Livewire::test(ProdukTab::class)
            ->call('openProdukModal')
            ->call('openQuickAdd', 'tipe_hp')
            ->assertSet('showQuickAddModal', true)
            ->assertSet('quickAddTipe', 'tipe_hp')
            // 1. Pilih merk yang sudah ada dari dropdown
            ->set('quickAddSelectedMerk', 'Infinix');

        $models = $component->viewData('quickAddExistingModels');
        $this->assertContains('Hot 10', $models);

        // 2. Isi model baru yang belum ada di list
        $component->set('quickAddForm.model', 'Hot 20 5G')
            ->call('simpanQuickAdd')
            ->assertDispatched('alert', fn ($event, $params) => ($params[0]['type'] ?? null) === 'success')
            ->assertSet('showQuickAddModal', false);

        $newTipe = TipeHp::where('merk', 'Infinix')->where('model', 'Hot 20 5G')->first();
        $this->assertNotNull($newTipe);

        // Otomatis terpasang ke produkForm.tipe_hp_ids
        $this->assertContains($newTipe->id, $component->get('produkForm.tipe_hp_ids'));
    }

    public function test_menu_pengaturan_sinkronisasi_brand_part_kondisi_sid_retail_pro_dan_tipe_hp(): void
    {
        $this->authedAdmin();

        TipeHp::firstOrCreate(['merk' => 'Google', 'model' => 'Pixel 7'], ['is_active' => true]);

        $comp = Livewire::test(SettingsRbac::class, ['activeTab' => 'master_produk'])
            ->set('masterProdukSubTab', 'brand')
            ->assertSee('Brand Part')
            // Test CRUD Brand Part
            ->call('openBrandModal')
            ->assertSet('showBrandModal', true)
            ->set('brandForm.nama', 'Olike Indonesia')
            ->set('brandForm.keterangan', 'Distributor aksesoris & sparepart')
            ->call('simpanBrand')
            ->assertDispatched('alert', fn ($event, $params) => ($params[0]['type'] ?? null) === 'success')
            ->assertSet('showBrandModal', false);

        $this->assertDatabaseHas('brands', ['nama' => 'Olike Indonesia']);

        // Test Kondisi Produk gaya SID Retail Pro
        $comp->set('masterProdukSubTab', 'kondisi')
            ->call('openKondisiModal')
            ->assertSet('showKondisiModal', true)
            ->set('kondisiForm.nama', 'Refurbished Grade A')
            ->set('kondisiForm.warna', 'rose')
            ->set('kondisiForm.keterangan', 'Unit rekondisi pabrik bergaransi')
            ->call('simpanKondisi')
            ->assertSet('showKondisiModal', false);

        $kondisiList = $comp->viewData('kondisiList');
        $refurb = collect($kondisiList)->firstWhere('nama', 'Refurbished Grade A');
        $this->assertNotNull($refurb);
        $this->assertEquals('rose', $refurb['warna']);
        $this->assertEquals('#ef4444', $refurb['hex']);

        // Test Modal Tipe HP Cascading di Pengaturan
        $comp->set('masterProdukSubTab', 'tipe_hp')
            ->call('openTipeHpModal')
            ->assertSet('showTipeHpModal', true)
            // Pilih merk Google yang ada
            ->set('tipeHpSelectedMerk', 'Google');

        $models = $comp->viewData('existingModelsForSelectedMerk');
        $this->assertContains('Pixel 7', $models);

        // Isi model Pixel 8 Pro
        $comp->set('tipeHpForm.model', 'Pixel 8 Pro')
            ->call('simpanTipeHp')
            ->assertDispatched('alert', fn ($event, $params) => ($params[0]['type'] ?? null) === 'success')
            ->assertSet('showTipeHpModal', false);

        $this->assertDatabaseHas('tipe_hp', [
            'merk' => 'Google',
            'model' => 'Pixel 8 Pro',
        ]);
    }

    public function test_aksi_saldo_awal_hanya_muncul_pada_produk_tanpa_mutasi_dan_menjurnal_ekuitas_modal(): void
    {
        $admin = $this->authedAdmin();
        $gudang = Gudang::firstOrCreate(
            ['kode' => 'GD-TEST-AWAL'],
            ['nama' => 'Gudang Test Awal', 'cabang_id' => 1, 'is_active' => true]
        );

        // Buat produk tanpa stok awal
        $produk = Produk::create([
            'nama' => 'IC Power Oppo A5s Original',
            'slug' => 'ic-power-oppo-a5s-original-'.time(),
            'kategori' => 'IC & Komponen',
            'kondisi' => 'baru',
            'satuan' => 'pcs',
            'harga_beli' => 45000,
            'harga_jual_retail' => 75000,
            'is_active' => true,
        ]);

        $component = Livewire::test(ProdukTab::class);

        // 1. Verifikasi modal saldo awal bisa dibuka
        $component->call('openSaldoAwalModal', $produk->id)
            ->assertSet('showSaldoAwalModal', true)
            ->assertSet('saldoAwalProdukId', $produk->id)
            ->assertSet('saldoAwalForm.harga_beli', 45000)
            ->set('saldoAwalForm.gudang_id', $gudang->id)
            ->set('saldoAwalForm.qty', 10)
            ->set('saldoAwalForm.harga_beli', 45000)
            ->call('simpanSaldoAwal')
            ->assertDispatched('alert', fn ($event, $params) => ($params[0]['type'] ?? null) === 'success')
            ->assertSet('showSaldoAwalModal', false);

        // 2. Verifikasi stok fisik dan log tercatat
        $this->assertDatabaseHas('stok_items', [
            'produk_id' => $produk->id,
            'gudang_id' => $gudang->id,
            'jumlah' => 10,
        ]);

        $this->assertDatabaseHas('stok_log', [
            'produk_id' => $produk->id,
            'jenis' => 'stok_awal',
            'perubahan' => 10,
            'jumlah_setelah' => 10,
        ]);

        // 3. Verifikasi Jurnal Akuntansi: Debit Persediaan (130-01) vs Kredit Modal Pemilik (310-01)
        // Nilai total: 10 pcs * Rp 45.000 = Rp 450.000
        $akunPersediaan = DB::table('akun_coa')->where('kode', '130-01')->value('id');
        $akunModal = DB::table('akun_coa')->where('kode', '310-01')->value('id');

        $this->assertDatabaseHas('jurnal_akuntansi', [
            'sumber' => 'stok_awal',
            'akun_coa_id' => $akunPersediaan,
            'debit' => 450000,
            'kredit' => 0,
        ]);

        $this->assertDatabaseHas('jurnal_akuntansi', [
            'sumber' => 'stok_awal',
            'akun_coa_id' => $akunModal,
            'debit' => 0,
            'kredit' => 450000,
        ]);

        // 4. Coba buka lagi untuk produk yang sudah punya stok/mutasi -> WAJIB DITOLAK
        $component->call('openSaldoAwalModal', $produk->id)
            ->assertDispatched('alert', fn ($event, $params) => ($params[0]['type'] ?? null) === 'warning' && str_contains($params[0]['message'] ?? '', 'sudah memiliki riwayat mutasi'))
            ->assertSet('showSaldoAwalModal', false);
    }

    public function test_filter_dan_pencarian_multi_filter_serta_ranking_relevansi_tertinggi(): void
    {
        $this->authedAdmin();
        $gudang = Gudang::firstOrCreate(
            ['kode' => 'GD-FILTER-TEST'],
            ['nama' => 'Gudang Filter Test', 'cabang_id' => 1, 'is_active' => true]
        );

        $katLcd = KategoriProduk::firstOrCreate(['nama' => 'LCD Touchscreen', 'slug' => 'lcd-touchscreen-filter']);
        $katBaterai = KategoriProduk::firstOrCreate(['nama' => 'Baterai HP', 'slug' => 'baterai-hp-filter']);

        $brandCrown = Brand::firstOrCreate(['nama' => 'Crown Original', 'slug' => 'crown-original']);
        $brandOlike = Brand::firstOrCreate(['nama' => 'Olike Prime', 'slug' => 'olike-prime']);

        // Buat 4 produk dengan karakteristik berbeda
        // P1: Exact name "LCD iPhone 11"
        $p1 = Produk::create([
            'nama' => 'LCD iPhone 11',
            'slug' => 'lcd-iphone-11-test',
            'kategori_id' => $katLcd->id,
            'kategori' => $katLcd->nama,
            'brand_id' => $brandCrown->id,
            'brand_kompatibel' => 'Apple',
            'model_kompatibel' => 'iPhone 11',
            'kondisi' => 'baru',
            'satuan' => 'pcs',
            'harga_beli' => 200000,
            'harga_jual_retail' => 350000,
            'is_active' => true,
        ]);
        StokItem::create(['produk_id' => $p1->id, 'gudang_id' => $gudang->id, 'jumlah' => 10]);

        // P2: Prefix "LCD iPhone 11 Pro Max Incell" (Dimulai dengan LCD iPhone 11)
        $p2 = Produk::create([
            'nama' => 'LCD iPhone 11 Pro Max Incell',
            'slug' => 'lcd-iphone-11-pro-max-incell-test',
            'kategori_id' => $katLcd->id,
            'kategori' => $katLcd->nama,
            'brand_id' => $brandCrown->id,
            'brand_kompatibel' => 'Apple',
            'model_kompatibel' => 'iPhone 11 Pro Max',
            'kondisi' => 'baru',
            'satuan' => 'pcs',
            'harga_beli' => 250000,
            'harga_jual_retail' => 450000,
            'is_active' => true,
        ]);
        StokItem::create(['produk_id' => $p2->id, 'gudang_id' => $gudang->id, 'jumlah' => 5]);

        // P3: "Baut LCD iPhone 11" (Secara alfabetis 'Baut' lebih awal dari 'LCD', tapi relevansi lebih rendah)
        $p3 = Produk::create([
            'nama' => 'Baut LCD iPhone 11',
            'slug' => 'baut-lcd-iphone-11-test',
            'kategori_id' => $katLcd->id,
            'kategori' => $katLcd->nama,
            'brand_id' => $brandOlike->id,
            'brand_kompatibel' => 'Apple',
            'model_kompatibel' => 'iPhone 11',
            'kondisi' => 'bekas',
            'satuan' => 'set',
            'harga_beli' => 5000,
            'harga_jual_retail' => 15000,
            'is_active' => true,
        ]);
        StokItem::create(['produk_id' => $p3->id, 'gudang_id' => $gudang->id, 'jumlah' => 20]);

        // P4: Baterai (Bukan LCD)
        $p4 = Produk::create([
            'nama' => 'Baterai iPhone 11 Original',
            'slug' => 'baterai-iphone-11-original-test',
            'kategori_id' => $katBaterai->id,
            'kategori' => $katBaterai->nama,
            'brand_id' => $brandCrown->id,
            'brand_kompatibel' => 'Apple',
            'model_kompatibel' => 'iPhone 11',
            'kondisi' => 'baru',
            'satuan' => 'pcs',
            'harga_beli' => 150000,
            'harga_jual_retail' => 280000,
            'is_active' => true,
        ]);
        StokItem::create(['produk_id' => $p4->id, 'gudang_id' => $gudang->id, 'jumlah' => 8]);

        // 1. Uji Relevansi Pencarian: "LCD iPhone 11"
        // P1 (Exact match) harus urutan pertama, P2 kedua, P3 ketiga
        $comp = Livewire::test(ProdukTab::class)
            ->set('produkSearch', 'LCD iPhone 11');

        $items = $comp->viewData('produks')->items();
        $this->assertNotEmpty($items);
        $this->assertSame($p1->id, $items[0]->id, 'Produk exact match LCD iPhone 11 harus berada di urutan paling atas');
        $this->assertSame($p2->id, $items[1]->id, 'Produk prefix match LCD iPhone 11 Pro Max harus di urutan kedua');
        $this->assertSame($p3->id, $items[2]->id, 'Produk Baut LCD iPhone 11 harus di urutan ketiga');

        // 2. Uji Multi-Filter Sekaligus (Kategori LCD + Brand Crown + Kondisi Baru + Min Harga 300.000)
        $comp->set('produkSearch', '')
            ->set('filterKategoriId', $katLcd->id)
            ->set('filterBrandId', $brandCrown->id)
            ->set('filterKondisi', 'baru')
            ->set('filterMinHarga', 300000);

        $this->assertSame(4, $comp->get('activeFilterCount'));

        $filteredItems = $comp->viewData('produks')->items();
        $filteredIds = collect($filteredItems)->pluck('id')->all();

        // P1 & P2 lolos multi-filter, P3 gagal (brand olike & bekas), P4 gagal (kategori baterai)
        $this->assertContains($p1->id, $filteredIds);
        $this->assertContains($p2->id, $filteredIds);
        $this->assertNotContains($p3->id, $filteredIds);
        $this->assertNotContains($p4->id, $filteredIds);

        // 3. Uji Reset Semua Filter
        $comp->call('resetSemuaFilter');
        $this->assertSame(0, $comp->get('activeFilterCount'));
        $this->assertSame('', $comp->get('produkSearch'));
        $this->assertNull($comp->get('filterKategoriId'));
        $this->assertNull($comp->get('filterBrandId'));
        $this->assertNull($comp->get('filterKondisi'));
        $this->assertNull($comp->get('filterMinHarga'));
    }

    public function test_filter_merk_hp_dan_tipe_hp_terpisah_presisi_dan_cascading(): void
    {
        $this->authedAdmin();

        // Ambil atau buat Tipe HP di master katalog
        $thApple11 = TipeHp::firstOrCreate(['merk' => 'Apple', 'model' => 'iPhone 11'], ['is_active' => true]);
        $thApple13 = TipeHp::firstOrCreate(['merk' => 'Apple', 'model' => 'iPhone 13'], ['is_active' => true]);
        $thSamS21 = TipeHp::firstOrCreate(['merk' => 'Samsung', 'model' => 'Galaxy S21'], ['is_active' => true]);

        // Produk 1: LCD iPhone 11 (Apple)
        $p1 = Produk::create([
            'nama' => 'LCD iPhone 11 Original',
            'slug' => 'lcd-iphone-11-orig',
            'brand_kompatibel' => 'Apple',
            'model_kompatibel' => 'iPhone 11',
            'harga_beli' => 200000,
            'harga_jual_retail' => 350000,
            'is_active' => true,
        ]);
        $p1->tipeHps()->attach($thApple11->id);

        // Produk 2: Baterai iPhone 13 (Apple)
        $p2 = Produk::create([
            'nama' => 'Baterai iPhone 13 High Cap',
            'slug' => 'baterai-iphone-13-high-cap',
            'brand_kompatibel' => 'Apple',
            'model_kompatibel' => 'iPhone 13',
            'harga_beli' => 150000,
            'harga_jual_retail' => 250000,
            'is_active' => true,
        ]);
        $p2->tipeHps()->attach($thApple13->id);

        // Produk 3: LCD Samsung S21 (Samsung)
        $p3 = Produk::create([
            'nama' => 'LCD Samsung Galaxy S21 OLED',
            'slug' => 'lcd-samsung-galaxy-s21-oled',
            'brand_kompatibel' => 'Samsung',
            'model_kompatibel' => 'Galaxy S21',
            'harga_beli' => 500000,
            'harga_jual_retail' => 750000,
            'is_active' => true,
        ]);
        $p3->tipeHps()->attach($thSamS21->id);

        $comp = Livewire::test(ProdukTab::class);

        // 1. Cascading model: ketika filterBrandKompatibel dipilih 'Apple', modelKompatibelList hanya memuat model Apple
        $comp->set('filterBrandKompatibel', 'Apple');
        $models = $comp->viewData('modelKompatibelList');
        $this->assertContains('iPhone 11', $models);
        $this->assertContains('iPhone 13', $models);
        $this->assertNotContains('Galaxy S21', $models);

        // 2. Filter Tipe HP dipilih 'iPhone 11' -> Hanya P1 yang lolos
        $comp->set('filterModelKompatibel', 'iPhone 11');
        $filteredIds = collect($comp->viewData('produks')->items())->pluck('id')->all();
        $this->assertContains($p1->id, $filteredIds);
        $this->assertNotContains($p2->id, $filteredIds);
        $this->assertNotContains($p3->id, $filteredIds);

        // 3. Filter kombinasi beda merk & model (Samsung + iPhone 11) -> Harus 0 hasil (presisi tanpa false positive)
        $comp->set('filterBrandKompatibel', 'Samsung')
            ->set('filterModelKompatibel', 'iPhone 11');
        $this->assertEmpty($comp->viewData('produks')->items());

        // 4. Filter Samsung + Galaxy S21 -> Hanya P3 yang lolos
        $comp->set('filterBrandKompatibel', 'Samsung')
            ->set('filterModelKompatibel', 'Galaxy S21');
        $filteredSamIds = collect($comp->viewData('produks')->items())->pluck('id')->all();
        $this->assertSame([$p3->id], $filteredSamIds);
    }

    public function test_opsi_filter_terkalibrasi_ke_master_produk_riil(): void
    {
        $this->authedAdmin();

        // Buat Brand sparepart (Crown & Olike)
        $brandCrown = Brand::create(['nama' => 'Crown Part', 'slug' => 'crown-part', 'is_active' => true]);
        $brandOlike = Brand::create(['nama' => 'Olike Part', 'slug' => 'olike-part', 'is_active' => true]);
        $brandUnused = Brand::create(['nama' => 'Merk Tak Terpakai', 'slug' => 'unused-brand', 'is_active' => true]);

        // Buat Kategori (LCD dengan produk, Housing tanpa produk)
        $katLcd = KategoriProduk::create(['nama' => 'LCD Screen', 'slug' => 'lcd-screen', 'is_active' => true]);
        $katHousing = KategoriProduk::create(['nama' => 'Housing Frame Kosong', 'slug' => 'housing-kosong', 'is_active' => true]);

        // Buat Produk riil yang hanya memakai brandCrown dan katLcd
        Produk::create([
            'nama' => 'LCD Test Kalibrasi',
            'slug' => 'lcd-test-kalibrasi',
            'kategori_id' => $katLcd->id,
            'kategori' => $katLcd->nama,
            'brand_id' => $brandCrown->id,
            'brand_kompatibel' => 'Xiaomi',
            'model_kompatibel' => 'Redmi Note 10',
            'satuan' => 'pcs',
            'harga_beli' => 100000,
            'harga_jual_retail' => 150000,
            'is_active' => true,
        ]);

        $comp = Livewire::test(ProdukTab::class);

        // 1. brandKompatibelList HANYA memuat merk smartphone (Xiaomi), TIDAK boleh mencampur brand sparepart (Crown/Olike)
        $brandKompatibel = $comp->viewData('brandKompatibelList');
        $this->assertContains('Xiaomi', $brandKompatibel);
        $this->assertNotContains('Crown Part', $brandKompatibel);
        $this->assertNotContains('Olike Part', $brandKompatibel);

        // 2. Tree kategori terkalibrasi ke yang ada produknya
        $kategoriTree = $comp->viewData('kategoriTree');
        $kategoriTreeIds = $kategoriTree->pluck('id')->all();
        $this->assertContains($katLcd->id, $kategoriTreeIds);
        $this->assertNotContains($katHousing->id, $kategoriTreeIds);

        // 3. Brands sparepart dropdown hanya memuat brand yang ada di produk (Crown Part), bukan unused
        $brands = $comp->viewData('brands')->pluck('id')->all();
        $this->assertContains($brandCrown->id, $brands);
        $this->assertNotContains($brandUnused->id, $brands);
    }

    public function test_pencarian_murni_filter_kategori_dan_tipe_hp_urutkan_exact_dan_cross_kompatibel(): void
    {
        $this->authedAdmin();

        // Master Kategori & Tipe HP
        $katLcd = KategoriProduk::create(['nama' => 'LCD Touchscreen', 'slug' => 'lcd-touchscreen-test', 'is_active' => true]);
        $katBaterai = KategoriProduk::create(['nama' => 'Baterai HP', 'slug' => 'baterai-hp-test', 'is_active' => true]);

        $thSamA11 = TipeHp::firstOrCreate(['merk' => 'Samsung', 'model' => 'A11'], ['is_active' => true]);
        $thSamA15 = TipeHp::firstOrCreate(['merk' => 'Samsung', 'model' => 'A15'], ['is_active' => true]);

        // Brand suku cadang berbeda
        $brandOg = Brand::create(['nama' => 'OG Original Quality', 'slug' => 'og-original-quality', 'is_active' => true]);
        $brandBrader = Brand::create(['nama' => 'Braderpart Indonesia', 'slug' => 'braderpart-indonesia', 'is_active' => true]);
        $brandCrown = Brand::create(['nama' => 'Crown Super', 'slug' => 'crown-super', 'is_active' => true]);

        // P1: LCD A11 dari brand OG (Exact primary match A11)
        $p1 = Produk::create([
            'nama' => 'LCD Samsung A11 OG Diamond',
            'slug' => 'lcd-samsung-a11-og-diamond',
            'kategori_id' => $katLcd->id,
            'kategori' => $katLcd->nama,
            'brand_id' => $brandOg->id,
            'brand_kompatibel' => 'Samsung',
            'model_kompatibel' => 'A11',
            'harga_beli' => 120000,
            'harga_jual_retail' => 180000,
            'is_active' => true,
        ]);
        $p1->tipeHps()->attach($thSamA11->id);

        // P2: LCD A11 dari brand Braderpart (Exact primary match A11)
        $p2 = Produk::create([
            'nama' => 'LCD Samsung A11 Braderpart Crown Gold',
            'slug' => 'lcd-samsung-a11-braderpart-crown-gold',
            'kategori_id' => $katLcd->id,
            'kategori' => $katLcd->nama,
            'brand_id' => $brandBrader->id,
            'brand_kompatibel' => 'Samsung',
            'model_kompatibel' => 'A11',
            'harga_beli' => 125000,
            'harga_jual_retail' => 185000,
            'is_active' => true,
        ]);
        $p2->tipeHps()->attach($thSamA11->id);

        // P3: LCD A15 dari brand Crown (Primary model A15, tapi KOMPATIBEL SILANG / substitusi dengan A11)
        $p3 = Produk::create([
            'nama' => 'LCD Samsung A15 Crown Super OLED',
            'slug' => 'lcd-samsung-a15-crown-super-oled',
            'kategori_id' => $katLcd->id,
            'kategori' => $katLcd->nama,
            'brand_id' => $brandCrown->id,
            'brand_kompatibel' => 'Samsung',
            'model_kompatibel' => 'A15',
            'harga_beli' => 140000,
            'harga_jual_retail' => 210000,
            'is_active' => true,
        ]);
        // Tautkan A11 sebagai kompatibilitas silang
        $p3->tipeHps()->attach($thSamA15->id);
        $p3->tipeHps()->attach($thSamA11->id);
        $p3->produkKompatibel()->attach($p1->id);

        // P4: Baterai Samsung A11 (Kategori bukan LCD)
        $p4 = Produk::create([
            'nama' => 'Baterai Samsung A11 Original',
            'slug' => 'baterai-samsung-a11-original',
            'kategori_id' => $katBaterai->id,
            'kategori' => $katBaterai->nama,
            'brand_id' => $brandOg->id,
            'brand_kompatibel' => 'Samsung',
            'model_kompatibel' => 'A11',
            'harga_beli' => 70000,
            'harga_jual_retail' => 110000,
            'is_active' => true,
        ]);
        $p4->tipeHps()->attach($thSamA11->id);

        // P5: LCD Xiaomi Redmi Note 10 (Kategori LCD tapi bukan A11)
        $p5 = Produk::create([
            'nama' => 'LCD Xiaomi Redmi Note 10 OG',
            'slug' => 'lcd-xiaomi-redmi-note-10-og',
            'kategori_id' => $katLcd->id,
            'kategori' => $katLcd->nama,
            'brand_id' => $brandOg->id,
            'brand_kompatibel' => 'Xiaomi',
            'model_kompatibel' => 'Redmi Note 10',
            'harga_beli' => 130000,
            'harga_jual_retail' => 190000,
            'is_active' => true,
        ]);

        // Eksekusi Livewire: Filter MURNI Kategori LCD + Tipe HP A11 (search input teks = '')
        $comp = Livewire::test(ProdukTab::class)
            ->set('produkSearch', '')
            ->set('filterKategoriId', $katLcd->id)
            ->set('filterBrandKompatibel', 'Samsung')
            ->set('filterModelKompatibel', 'A11');

        $items = $comp->viewData('produks')->items();
        $itemIds = collect($items)->pluck('id')->all();

        // 1. Verifikasi lolos filter:
        // P1 & P2 (LCD A11 dari brand OG & Braderpart) dan P3 (LCD A15 cross-compatible A11 dari brand Crown) HARUS muncul
        $this->assertContains($p1->id, $itemIds, 'LCD A11 OG harus muncul');
        $this->assertContains($p2->id, $itemIds, 'LCD A11 Braderpart harus muncul');
        $this->assertContains($p3->id, $itemIds, 'LCD A15 Crown (kompatibel silang A11) harus muncul');

        // P4 (Baterai) dan P5 (Xiaomi) TIDAK boleh muncul
        $this->assertNotContains($p4->id, $itemIds, 'Baterai harus terfilter karena filter kategori LCD aktif');
        $this->assertNotContains($p5->id, $itemIds, 'Xiaomi harus terfilter karena filter tipe HP Samsung A11 aktif');

        // 2. Verifikasi Hierarki Urutan (Ranking Kompatibilitas):
        // P1 dan P2 (Exact primary match model A11) HARUS muncul di urutan atas (posisi 0 dan 1)
        // P3 (LCD A15 - cross compatible) HARUS muncul di bawahnya (posisi 2)
        $p1Index = array_search($p1->id, $itemIds, true);
        $p2Index = array_search($p2->id, $itemIds, true);
        $p3Index = array_search($p3->id, $itemIds, true);

        $this->assertLessThan($p3Index, $p1Index, 'Produk exact match A11 (P1) harus di atas produk cross-compatible A15 (P3)');
        $this->assertLessThan($p3Index, $p2Index, 'Produk exact match A11 (P2) harus di atas produk cross-compatible A15 (P3)');
    }

    public function test_picker_kompatibel_tipe_hp_multi_select_dan_pencarian_sesuai_merek(): void
    {
        $this->authedAdmin();

        $thSamA11 = TipeHp::firstOrCreate(['merk' => 'Samsung', 'model' => 'Galaxy A11'], ['is_active' => true]);
        $thSamA15 = TipeHp::firstOrCreate(['merk' => 'Samsung', 'model' => 'Galaxy A15'], ['is_active' => true]);
        $thSamS22 = TipeHp::firstOrCreate(['merk' => 'Samsung', 'model' => 'Galaxy S22'], ['is_active' => true]);
        $thXioR9 = TipeHp::firstOrCreate(['merk' => 'Xiaomi', 'model' => 'Redmi 9'], ['is_active' => true]);
        $thApp13 = TipeHp::firstOrCreate(['merk' => 'Apple', 'model' => 'iPhone 13'], ['is_active' => true]);

        $comp = Livewire::test(ProdukTab::class);

        // 1. Sebelum merek dipilih -> tipeHpCreateOptions kosong
        $this->assertEmpty($comp->viewData('tipeHpCreateOptions'));

        // 2. Pilih merek Samsung -> Hanya model Samsung yang muncul
        $comp->set('kompatibelMerekHp', 'Samsung');
        $models = $comp->viewData('tipeHpCreateOptions')->pluck('model')->all();
        $this->assertContains('Galaxy A11', $models);
        $this->assertContains('Galaxy A15', $models);
        $this->assertContains('Galaxy S22', $models);
        $this->assertNotContains('Redmi 9', $models);
        $this->assertNotContains('iPhone 13', $models);

        // 3. Pencarian tipe di atasnya -> Cari "A1" -> Hanya Galaxy A11 dan Galaxy A15
        $comp->set('searchTipeHpCreate', 'A1');
        $filteredModels = $comp->viewData('tipeHpCreateOptions')->pluck('model')->all();
        $this->assertContains('Galaxy A11', $filteredModels);
        $this->assertContains('Galaxy A15', $filteredModels);
        $this->assertNotContains('Galaxy S22', $filteredModels);

        // 4. Pilih Semua hasil filter -> kompatibelSelectedTipeHpIds terisi A11 & A15
        $comp->call('pilihSemuaTipeHp', 'create');
        $selectedIds = $comp->get('kompatibelSelectedTipeHpIds');
        $this->assertContains($thSamA11->id, $selectedIds);
        $this->assertContains($thSamA15->id, $selectedIds);
        $this->assertNotContains($thSamS22->id, $selectedIds);

        // 5. Tambahkan multi-select ke produkForm -> Masuk ke tipe_hp_ids tanpa duplikasi
        $comp->call('tambahKompatibelKeForm', 'create')
            ->assertDispatched('alert', fn ($event, $params) => ($params[0]['type'] ?? null) === 'success');

        $formTipeHpIds = $comp->get('produkForm.tipe_hp_ids');
        $this->assertContains($thSamA11->id, $formTipeHpIds);
        $this->assertContains($thSamA15->id, $formTipeHpIds);
        $this->assertEmpty($comp->get('kompatibelSelectedTipeHpIds'));

        // 6. Ganti merek ke Xiaomi -> Pencarian dan seleksi otomatis di-reset
        $comp->set('kompatibelMerekHp', 'Xiaomi');
        $this->assertSame('', $comp->get('searchTipeHpCreate'));
        $this->assertEmpty($comp->get('kompatibelSelectedTipeHpIds'));

        $xioModels = $comp->viewData('tipeHpCreateOptions')->pluck('model')->all();
        $this->assertContains('Redmi 9', $xioModels);
        $this->assertNotContains('Galaxy A11', $xioModels);
    }

    public function test_nama_produk_bisa_lebih_dari_255_karakter_tanpa_dibatasi(): void
    {
        $this->authedAdmin();

        $kat = KategoriProduk::firstOrCreate(['nama' => 'LCD Touchscreen', 'slug' => 'lcd-touchscreen-panjang'], ['is_active' => true]);

        // Nama produk panjang (> 300 karakter)
        $namaPanjang = 'LCD Touchscreen Assembly Fullset Samsung Galaxy A11 SM-A115F SM-A115M Original Equipment Manufacturer Grade A Plus Frame Bezel Housing Hitam Presisi Tinggi Touch Responsif Garansi Tes 7 Hari Toko Ute Parts Official Sparepart HP Indonesia Terlengkap Termurah dan Terpercaya Garansi Resmi Distributor Utama';
        $this->assertGreaterThan(255, mb_strlen($namaPanjang));

        $comp = Livewire::test(ProdukTab::class);

        $comp->set('produkForm.nama', $namaPanjang)
            ->set('produkForm.kategori_id', $kat->id)
            ->set('produkForm.satuan_kode', 'pcs')
            ->set('produkForm.harga_beli', 125000)
            ->set('produkForm.harga_jual_retail', 225000)
            ->set('produkForm.harga_tier.retail.nominal_tetap', 225000)
            ->call('simpanProduk')
            ->assertHasNoErrors(['produkForm.nama'])
            ->assertDispatched('alert', fn ($event, $params) => ($params[0]['type'] ?? null) === 'success');

        $produk = Produk::where('nama', $namaPanjang)->first();
        $this->assertNotNull($produk);
        $this->assertSame($namaPanjang, $produk->nama);
        $this->assertLessThanOrEqual(255, strlen($produk->slug));

        // Test update dengan nama produk lebih panjang lagi (> 350 karakter)
        $namaUpdate = $namaPanjang.' - Versi Revisi Tambahan Informasi Spesifikasi Fleksibel Edisi Khusus Teknisi';
        $this->assertGreaterThan(300, mb_strlen($namaUpdate));

        $comp->call('openEditProdukModal', $produk->id)
            ->assertSet('editProdukForm.nama', $namaPanjang)
            ->set('editProdukForm.nama', $namaUpdate)
            ->call('simpanEditProduk')
            ->assertHasNoErrors(['editProdukForm.nama'])
            ->assertDispatched('alert', fn ($event, $params) => ($params[0]['type'] ?? null) === 'success');

        $produk->refresh();
        $this->assertSame($namaUpdate, $produk->nama);
    }

    public function test_hierarki_hybrid_grade_brand_part_ke_kualitas_produk(): void
    {
        $this->authedAdmin();

        $brandCrown = Brand::firstOrCreate(['nama' => 'Crown Part Test'], ['is_active' => true]);
        $brandOg = Brand::firstOrCreate(['nama' => 'OG Part Test'], ['is_active' => true]);

        // Kualitas universal (tanpa brand)
        $kualUniversal = KualitasProduk::firstOrCreate(
            ['nama' => 'Original Copotan Mesin Test', 'brand_id' => null],
            ['is_active' => true]
        );

        // Kualitas eksklusif Crown
        $kualCrown = KualitasProduk::firstOrCreate(
            ['nama' => 'Crown Diamond Grade Test', 'brand_id' => $brandCrown->id],
            ['is_active' => true]
        );

        // Kualitas eksklusif OG
        $kualOg = KualitasProduk::firstOrCreate(
            ['nama' => 'OG Ultra Pro Grade Test', 'brand_id' => $brandOg->id],
            ['is_active' => true]
        );

        // 1. Verifikasi Relasi
        $this->assertSame($brandCrown->id, $kualCrown->brand->id);
        $this->assertSame($brandOg->id, $kualOg->brand->id);
        $this->assertNull($kualUniversal->brand);
        $this->assertTrue($brandCrown->kualitas->pluck('id')->contains($kualCrown->id));
        $this->assertFalse($brandCrown->kualitas->pluck('id')->contains($kualOg->id));

        // 2. Verifikasi Scope forBrand
        $crownOptions = KualitasProduk::forBrand($brandCrown->id)->pluck('id')->all();
        $this->assertContains($kualCrown->id, $crownOptions);
        $this->assertContains($kualUniversal->id, $crownOptions);
        $this->assertNotContains($kualOg->id, $crownOptions);

        $ogOptions = KualitasProduk::forBrand($brandOg->id)->pluck('id')->all();
        $this->assertContains($kualOg->id, $ogOptions);
        $this->assertContains($kualUniversal->id, $ogOptions);
        $this->assertNotContains($kualCrown->id, $ogOptions);

        // 3. Verifikasi Scope Universal & Exclusive
        $this->assertTrue(KualitasProduk::universal()->pluck('id')->contains($kualUniversal->id));
        $this->assertFalse(KualitasProduk::universal()->pluck('id')->contains($kualCrown->id));
        $this->assertTrue(KualitasProduk::exclusive()->pluck('id')->contains($kualCrown->id));
        $this->assertFalse(KualitasProduk::exclusive()->pluck('id')->contains($kualUniversal->id));
    }

    public function test_cascading_dropdown_dan_filter_kualitas_di_wms_produk_tab(): void
    {
        $this->authedAdmin();

        $brandCrown = Brand::firstOrCreate(['nama' => 'Crown WMS Test'], ['is_active' => true]);
        $brandOg = Brand::firstOrCreate(['nama' => 'OG WMS Test'], ['is_active' => true]);

        $kualUniversal = KualitasProduk::firstOrCreate(
            ['nama' => 'Universal Grade WMS Test', 'brand_id' => null],
            ['is_active' => true]
        );
        $kualCrown = KualitasProduk::firstOrCreate(
            ['nama' => 'Crown Super WMS Test', 'brand_id' => $brandCrown->id],
            ['is_active' => true]
        );
        $kualOg = KualitasProduk::firstOrCreate(
            ['nama' => 'OG Master WMS Test', 'brand_id' => $brandOg->id],
            ['is_active' => true]
        );

        $comp = Livewire::test(ProdukTab::class);

        // A. Form Create: Pilih Brand Crown -> Opsi kualitas ter-filter
        $comp->set('produkForm.brand_id', $brandCrown->id);
        $createOptions = $comp->viewData('kualitasCreateList')->pluck('id')->all();
        $this->assertContains($kualCrown->id, $createOptions);
        $this->assertContains($kualUniversal->id, $createOptions);
        $this->assertNotContains($kualOg->id, $createOptions);

        // Set kualitas ke Crown Super, lalu ganti brand ke OG -> Kualitas otomatis ter-reset
        $comp->set('produkForm.kualitas_id', $kualCrown->id);
        $comp->set('produkForm.brand_id', $brandOg->id);
        $this->assertNull($comp->get('produkForm.kualitas_id'));

        // B. Filter Katalog: Set filter brand Crown -> filter kualitas ter-filter
        $comp->set('filterBrandId', $brandCrown->id);
        $filterOptions = $comp->viewData('kualitasList')->pluck('id')->all();
        $this->assertContains($kualCrown->id, $filterOptions);
        $this->assertContains($kualUniversal->id, $filterOptions);
        $this->assertNotContains($kualOg->id, $filterOptions);

        // Set filter kualitas ke Crown, ganti filter brand ke OG -> filter kualitas reset
        $comp->set('filterKualitasId', $kualCrown->id);
        $comp->set('filterBrandId', $brandOg->id);
        $this->assertNull($comp->get('filterKualitasId'));

        // C. Quick Add Modal Kualitas: bisa simpan dengan brand_id
        $comp->call('openQuickAdd', 'kualitas')
            ->assertSet('quickAddTipe', 'kualitas')
            ->set('quickAddForm.nama', 'Crown Platinum Diamond Exclusive')
            ->set('quickAddForm.brand_id', $brandCrown->id)
            ->call('simpanQuickAdd')
            ->assertDispatched('alert', fn ($event, $params) => ($params[0]['type'] ?? null) === 'success');

        $kualBaru = KualitasProduk::where('nama', 'Crown Platinum Diamond Exclusive')->first();
        $this->assertNotNull($kualBaru);
        $this->assertSame($brandCrown->id, $kualBaru->brand_id);
    }

    public function test_cascading_kualitas_di_pos_kasir(): void
    {
        $this->authedAdmin();

        $brandCrown = Brand::firstOrCreate(['nama' => 'Crown POS Test'], ['is_active' => true]);
        $brandOg = Brand::firstOrCreate(['nama' => 'OG POS Test'], ['is_active' => true]);

        $kualUniversal = KualitasProduk::firstOrCreate(
            ['nama' => 'Universal Grade POS Test', 'brand_id' => null],
            ['is_active' => true]
        );
        $kualCrown = KualitasProduk::firstOrCreate(
            ['nama' => 'Crown Super POS Test', 'brand_id' => $brandCrown->id],
            ['is_active' => true]
        );
        $kualOg = KualitasProduk::firstOrCreate(
            ['nama' => 'OG Master POS Test', 'brand_id' => $brandOg->id],
            ['is_active' => true]
        );

        $pos = Livewire::test(PosKasir::class);
        $pos->set('filterBrandId', $brandCrown->id);
        $posKualitas = $pos->viewData('kualitasList')->pluck('id')->all();
        $this->assertContains($kualCrown->id, $posKualitas);
        $this->assertContains($kualUniversal->id, $posKualitas);
        $this->assertNotContains($kualOg->id, $posKualitas);

        // Reset otomatis saat brand bertentangan
        $pos->set('filterKualitasId', $kualCrown->id);
        $pos->set('filterBrandId', $brandOg->id);
        $this->assertNull($pos->get('filterKualitasId'));
    }

    public function test_cascading_kualitas_di_product_search_modal(): void
    {
        $this->authedAdmin();

        $brandCrown = Brand::firstOrCreate(['nama' => 'Crown Modal Test'], ['is_active' => true]);
        $brandOg = Brand::firstOrCreate(['nama' => 'OG Modal Test'], ['is_active' => true]);

        $kualUniversal = KualitasProduk::firstOrCreate(
            ['nama' => 'Universal Grade Modal Test', 'brand_id' => null],
            ['is_active' => true]
        );
        $kualCrown = KualitasProduk::firstOrCreate(
            ['nama' => 'Crown Super Modal Test', 'brand_id' => $brandCrown->id],
            ['is_active' => true]
        );
        $kualOg = KualitasProduk::firstOrCreate(
            ['nama' => 'OG Master Modal Test', 'brand_id' => $brandOg->id],
            ['is_active' => true]
        );

        $modal = Livewire::test(ProductSearchModal::class);
        $modal->set('filterBrandId', $brandCrown->id);
        $modalKualitas = $modal->viewData('kualitasList')->pluck('id')->all();
        $this->assertContains($kualCrown->id, $modalKualitas);
        $this->assertContains($kualUniversal->id, $modalKualitas);
        $this->assertNotContains($kualOg->id, $modalKualitas);

        $modal->set('filterKualitasId', $kualCrown->id);
        $modal->set('filterBrandId', $brandOg->id);
        $this->assertNull($modal->get('filterKualitasId'));
    }
}
