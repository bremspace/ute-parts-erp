<?php

namespace Tests\Unit;

use App\Modules\Pos\Services\HargaFleksibelService;
use App\Modules\Rbac\Models\Cabang;
use App\Modules\Wms\Models\Produk;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class HargaFleksibelTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Spatie permissions & roles
        Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'kasir', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'atur-harga-fleksibel', 'guard_name' => 'web']);
        $superRole = Role::where('name', 'super-admin')->first();
        $superRole->givePermissionTo('atur-harga-fleksibel');
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }

    protected function createCabang(): Cabang
    {
        return Cabang::create([
            'kode' => 'UTP'.Str::random(3),
            'nama' => 'Cabang Test',
            'alamat' => 'Jl. Test',
            'telepon' => '08123456789',
            'is_active' => true,
        ]);
    }

    protected function createProduk(array $attrs = []): Produk
    {
        $cabang = $this->createCabang();
        return Produk::create(array_merge([
            'nama' => 'Produk Test',
            'slug' => 'produk-test-'.Str::random(4),
            'kategori' => 'Umum',
            'kondisi' => 'baru',
            'satuan' => 'pcs',
            'harga_beli' => 50000,
            'harga_jual_retail' => 100000,
            'is_active' => true,
            'harga_fleksibel' => false,
        ], $attrs));
    }

    protected function createUser(string $roleName): User
    {
        $user = User::create([
            'name' => 'Test '.ucfirst($roleName).Str::random(4),
            'email' => $roleName.Str::random(4).'@test.com',
            'password' => bcrypt('password'),
        ]);
        $user->assignRole($roleName);
        return $user;
    }

    public function test_produk_normal_fleksibel_false_validasi_lolos(): void
    {
        $produk = $this->createProduk(['harga_fleksibel' => false, 'harga_beli' => 50000]);
        $kasir = $this->createUser('kasir');
        $service = new HargaFleksibelService();

        // Harga di atas HPP - lolos
        $service->validasi($produk, 100000, $kasir);

        // Harga di bawah HPP - juga lolos karena produk TIDAK fleksibel
        $service->validasi($produk, 10000, $kasir);

        // Harga 0 - lolos
        $service->validasi($produk, 0, $kasir);

        $this->assertTrue(true);
    }

    public function test_produk_fleksibel_user_kasir_throw(): void
    {
        $produk = $this->createProduk(['harga_fleksibel' => true, 'harga_beli' => 50000]);
        $kasir = $this->createUser('kasir');
        $service = new HargaFleksibelService();

        $this->expectException(ValidationException::class);
        $service->validasi($produk, 100000, $kasir);
    }

    public function test_produk_fleksibel_user_superadmin_harga_diatas_hpp_lolos(): void
    {
        $produk = $this->createProduk(['harga_fleksibel' => true, 'harga_beli' => 50000]);
        $super = $this->createUser('super-admin');
        $service = new HargaFleksibelService();

        $service->validasi($produk, 100000, $super); // di atas HPP
        $service->validasi($produk, 50000, $super); // sama dengan HPP

        $this->assertTrue(true);
    }

    public function test_produk_fleksibel_user_superadmin_harga_dibawah_hpp_throw(): void
    {
        $produk = $this->createProduk(['harga_fleksibel' => true, 'harga_beli' => 50000]);
        $super = $this->createUser('super-admin');
        $service = new HargaFleksibelService();

        $this->expectException(ValidationException::class);
        $service->validasi($produk, 10000, $super); // di bawah HPP
    }

    public function test_produk_fleksibel_user_superadmin_hpp_nol_harga_nol_lolos(): void
    {
        $produk = $this->createProduk(['harga_fleksibel' => true, 'harga_beli' => 0]);
        $super = $this->createUser('super-admin');
        $service = new HargaFleksibelService();

        // HPP = 0, harga 0 harus lolos
        $service->validasi($produk, 0, $super);
        $service->validasi($produk, 100000, $super);

        $this->assertTrue(true);
    }

    public function test_harga_minimum_method(): void
    {
        $produkHppPositif = $this->createProduk(['harga_beli' => 50000]);
        $produkHppNol = $this->createProduk(['harga_beli' => 0]);
        // DB has NOT NULL default 0, so null not allowed - test with 0
        $produkHppNull = $this->createProduk(['harga_beli' => 0]);

        $service = new HargaFleksibelService();

        $this->assertEquals(50000, $service->hargaMinimum($produkHppPositif));
        $this->assertEquals(0, $service->hargaMinimum($produkHppNol));
        $this->assertEquals(0, $service->hargaMinimum($produkHppNull));
    }

    public function test_user_null_throw(): void
    {
        $produk = $this->createProduk(['harga_fleksibel' => true, 'harga_beli' => 50000]);
        $service = new HargaFleksibelService();

        $this->expectException(ValidationException::class);
        $service->validasi($produk, 100000, null);
    }
}