<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Akunting\Models\AkunCOA;
use App\Modules\Crm\Models\Pelanggan;
use App\Modules\Rbac\Models\Cabang;
use App\Modules\Reseller\Models\SkemaKomisi;
use App\Modules\Wms\Models\Gudang;
use App\Modules\Wms\Models\Produk;
use App\Modules\Wms\Models\SkuVariant;
use App\Modules\Wms\Models\StokItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * [T-21]/[T-22] Alur reseller daftar dgn skema custom + konfigurasi loyalitas.
 */
class ResellerFase5Test extends TestCase
{
    use RefreshDatabase;

    private function setupAdmin(): void
    {
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        $this->seed(\Database\Seeders\TierMembershipSeeder::class);

        $admin = User::create([
            'name' => 'Admin', 'email' => 'admin@uteparts.test',
            'password' => Hash::make('password'), 'is_active' => true,
        ]);
        $admin->assignRole('super-admin');

        $cabang = Cabang::create(['nama' => 'Pusat', 'kode' => 'CBG-01']);
        $admin->cabangs()->attach($cabang->id);
        session(['cabang_id' => $cabang->id]);

        $this->actingAs($admin, 'web');

        $gudang = Gudang::create(['cabang_id' => $cabang->id, 'nama' => 'Gudang 1', 'kode' => 'GDG-01']);
        $produk = Produk::create([
            'nama' => 'LCD X', 'slug' => 'lcd-x', 'kategori' => 'LCD',
            'kondisi' => 'baru', 'harga_beli' => 50000, 'harga_jual_retail' => 100000,
        ]);
        $var = SkuVariant::create(['produk_id' => $produk->id, 'sku' => 'LCD-1', 'nama_varian' => 'Standar']);
        StokItem::create(['produk_id' => $produk->id, 'sku_variant_id' => $var->id, 'gudang_id' => $gudang->id, 'jumlah' => 10]);

        // COA utk jurnal
        $this->seed(\Database\Seeders\AkunCoaSeeder::class);

        // Skema default: 5% semua kategori
        SkemaKomisi::create(['nama' => 'Default', 'kategori' => null, 'tipe' => 'persen', 'nilai' => 5]);
    }

    public function test_reseller_skema_custom_dihormati(): void
    {
        $this->setupAdmin();

        // Daftar reseller dengan skema custom: 10% utk kategori LCD
        $resp = $this->postJson('/api/reseller/daftar', [
            'nama' => 'Reseller Custom',
            'telepon' => '081555',
            'skema' => [
                ['kategori' => 'LCD', 'tipe' => 'persen', 'nilai' => 10],
            ],
        ])->assertSuccessful();

        $reseller = Pelanggan::where('telepon', '081555')->firstOrFail();
        $this->assertTrue($reseller->is_reseller);
        $this->assertDatabaseHas('skema_komisi_reseller', ['pelanggan_id' => $reseller->id, 'kategori' => 'LCD', 'nilai' => 10]);

        // Hitung komisi utk transaksi 100k kategori LCD → harus 10% (10.000) bukan 5% (5.000)
        $komisi = app(\App\Modules\Reseller\Services\KomisiService::class)->hitungKomisiDariItems(
            $reseller,
            [['kategori' => 'LCD', 'subtotal' => 100000, 'jumlah' => 1]],
            ['keterangan' => 'test']
        );

        $this->assertNotNull($komisi);
        $this->assertEquals(10000, (float) $komisi->nominal_komisi);
    }

    public function test_konfigurasi_loyalitas_tersimpan(): void
    {
        $this->setupAdmin();

        $this->postJson('/api/crm/config', [
            'poin_earn_persen' => 8,
            'poin_redeem_rupiah' => 120,
            'diskon_silver' => 4,
            'diskon_gold' => 6,
            'diskon_platinum' => 12,
        ])->assertSuccessful();

        $svc = app(\App\Modules\Crm\Services\KonfigurasiService::class);
        $this->assertEquals(8, (float) $svc->get('poin_earn_persen'));
        $this->assertEquals(120, (float) $svc->get('poin_redeem_rupiah'));

        // Diskonto tier ikut terupdate
        $this->assertDatabaseHas('tier_memberships', ['kode' => 'gold', 'diskon_persen' => 6]);
    }
}