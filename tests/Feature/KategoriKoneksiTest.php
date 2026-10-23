<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Rbac\Models\Cabang;
use App\Modules\Wms\Livewire\ProdukTab;
use App\Modules\Wms\Models\Gudang;
use App\Modules\Wms\Models\KategoriProduk;
use App\Modules\Wms\Models\Produk;
use App\Modules\Wms\Services\ProdukService;
use Database\Seeders\KategoriProdukSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class KategoriKoneksiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $cabang = Cabang::create(['nama' => 'Cabang Test', 'kode' => 'CB-KAT', 'is_active' => true]);
        Gudang::create(['nama' => 'Gudang Pusat', 'kode' => 'GD-KAT', 'cabang_id' => $cabang->id, 'is_active' => true]);

        $user = User::create([
            'name' => 'Super Admin Kategori',
            'email' => 'admin-kat@test.com',
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);
        $user->assignRole('super-admin');
        $user->cabangs()->attach($cabang->id);

        session(['cabang_id' => $cabang->id]);
        $this->actingAs($user);
    }

    public function test_form_produk_menampilkan_kategori_baru_yang_belum_dipakai(): void
    {
        // Kategori yang BELUM direferensikan oleh produk manapun
        $kategori = KategoriProduk::create([
            'nama' => 'Kategori Sembunyi Uji',
            'is_active' => true,
        ]);

        $this->assertCount(0, $kategori->produks);

        Livewire::test(ProdukTab::class)
            ->assertSee('Kategori Sembunyi Uji');

        // getTreeTersediaDiProduk lama akan menyembunyikannya; getTree() harus memuat
        $tree = KategoriProduk::getTree();
        $found = $tree->contains(fn ($root) => $root->id === $kategori->id
            || $root->children->contains('id', $kategori->id));
        $this->assertTrue($found, 'Kategori baru harus muncul di pohon kategori form produk');
    }

    public function test_create_produk_membuat_dan_menghubungkan_kategori_string_baru(): void
    {
        $service = app(ProdukService::class);

        $produk = $service->buatProduk(
            nama: 'LCD iPhone 16 Uji Kategori',
            kategori: 'Kategori Uji Baru',
            brand: null,
            model: null,
            kondisi: 'baru',
            hargaBeli: 500000,
            hargaJual: 750000,
        );

        $this->assertNotNull($produk->kategori_id);

        $kategori = KategoriProduk::find($produk->kategori_id);
        $this->assertNotNull($kategori, 'KategoriProduk harus dibuat otomatis');
        $this->assertEquals('Kategori Uji Baru', $kategori->nama);
        $this->assertTrue($kategori->is_active);
        $this->assertDatabaseHas('kategori_produk', [
            'nama' => 'Kategori Uji Baru',
            'slug' => 'kategori-uji-baru',
            'is_active' => true,
        ]);
        $this->assertEquals('Kategori Uji Baru', $produk->kategori);
    }

    public function test_command_backfill_menghubungkan_produk_legacy(): void
    {
        // Seed pohon default agar 'Baterai' (child) sudah ada
        $this->seed(KategoriProdukSeeder::class);

        $baterai = KategoriProduk::where('nama', 'Baterai')->first();
        $this->assertNotNull($baterai);

        // Legacy produk: string kategori dikenal, kategori_id NULL
        $legacy1 = Produk::create([
            'nama' => 'Baterai LG Legacy',
            'kategori' => 'Baterai',
            'harga_beli' => 50000,
            'harga_jual_retail' => 80000,
            'is_active' => true,
        ]);

        // Legacy produk: string kategori baru, kategori_id NULL
        $legacy2 = Produk::create([
            'nama' => 'Produk Sangat Legacy',
            'kategori' => 'Kategori Backfill Uji',
            'harga_beli' => 20000,
            'harga_jual_retail' => 35000,
            'is_active' => true,
        ]);

        $this->artisan('wms:kategori-sinkron')->assertSuccessful();

        $legacy1->refresh();
        $legacy2->refresh();

        $this->assertEquals($baterai->id, $legacy1->kategori_id, 'Produk legacy harus tertaut ke kategori Baterai yang sudah ada');

        $baru = KategoriProduk::where('nama', 'Kategori Backfill Uji')->first();
        $this->assertNotNull($baru, 'Kategori baru harus dibuat oleh command backfill');
        $this->assertTrue($baru->is_active);
        $this->assertEquals($baru->id, $legacy2->kategori_id);

        // Idempotent: run kedua tidak mengapa-apa
        $this->artisan('wms:kategori-sinkron')->assertSuccessful();
        $legacy1->refresh();
        $this->assertEquals($baterai->id, $legacy1->kategori_id);
    }

    public function test_seeder_kategori_idempotent(): void
    {
        $this->seed(KategoriProdukSeeder::class);
        $this->seed(KategoriProdukSeeder::class); // Run kedua: tidak boleh duplikat

        $sparepart = KategoriProduk::where('nama', 'Sparepart HP')->first();
        $this->assertNotNull($sparepart);
        $this->assertNull($sparepart->parent_id);
        $this->assertTrue($sparepart->is_active);

        // Anak-anak unik by slug
        $childSlugs = KategoriProduk::where('parent_id', $sparepart->id)->pluck('slug');
        $this->assertCount(8, $childSlugs);
        $this->assertCount(8, $childSlugs->unique());

        $childNama = KategoriProduk::where('parent_id', $sparepart->id)->orderBy('urutan')->pluck('nama')->all();
        $this->assertEquals([
            'LCD & Touchscreen',
            'Baterai',
            'Fleksibel & Board',
            'Kamera',
            'IC & Chipset',
            'Speaker & Buzzer',
            'Housing & Backdoor',
            'Konektor & Sim Tray',
        ], $childNama);

        // Tools & Servis dan Aksesoris HP juga ada
        foreach (['Tools & Servis', 'Aksesoris HP'] as $namaRoot) {
            $root = KategoriProduk::where('nama', $namaRoot)->first();
            $this->assertNotNull($root, "Root '{$namaRoot}' harus ada");
            $this->assertTrue($root->children()->count() >= 3, "Root '{$namaRoot}' harus punya minimal 3 anak");
        }
    }
}
