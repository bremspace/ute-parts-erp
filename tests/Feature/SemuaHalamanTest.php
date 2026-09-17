<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Rbac\Models\Cabang;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Smoke test semua halaman backoffice + marketplace:
 * render tiap route authed harus 200 (menangkap "Undefined variable X" dll).
 */
class SemuaHalamanTest extends TestCase
{
    use RefreshDatabase;

    private function authed(): void
    {
        // Seed roles & permissions lengkap (dipakai role() scope di ServisBoard)
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        $this->seed(\Database\Seeders\AkunCoaSeeder::class); // untuk jurnal pembelian

        $cabang = Cabang::create(['nama' => 'Pusat', 'kode' => 'CBG-01', 'is_active' => true]);
        $gudang = \App\Modules\Wms\Models\Gudang::create(['cabang_id' => $cabang->id, 'nama' => 'Gudang 1', 'kode' => 'GDG-01', 'is_active' => true]);

        $user = User::create([
            'name' => 'Admin', 'email' => 'admin@test.com',
            'password' => Hash::make('password'), 'is_active' => true,
        ]);
        $user->assignRole('super-admin');
        $user->cabangs()->attach($cabang->id);
        session(['cabang_id' => $cabang->id]);

        $produk = \App\Modules\Wms\Models\Produk::create([
            'nama' => 'LCD iPhone', 'slug' => 'lcd-iphone', 'kategori' => 'LCD',
            'kondisi' => 'baru', 'harga_beli' => 750000, 'harga_jual_retail' => 1100000,
        ]);
        \App\Modules\Wms\Models\StokItem::create(['produk_id' => $produk->id, 'gudang_id' => $gudang->id, 'jumlah' => 5, 'jumlah_minimum' => 2]);

        $this->actingAs($user, 'web');
    }

    public function test_semua_halaman_backoffice_render(): void
    {
        $this->authed();

        $routes = [
            '/app/dashboard', '/app/pos', '/app/wms', '/app/servis',
            '/app/crm', '/app/reseller', '/app/akunting', '/app/omnichannel', '/app/pengaturan',
        ];

        foreach ($routes as $url) {
            $this->get($url)->assertSuccessful("Halaman {$url} harus 2xx");
        }
    }

    public function test_halaman_marketplace_render(): void
    {
        $this->get('/shop')->assertSuccessful();
        $this->get('/shop/lcd-iphone')->assertSuccessful();
        $this->get('/cart')->assertSuccessful();
        $this->get('/checkout')->assertSuccessful();
        $this->get('/login-pelanggan')->assertSuccessful();
        $this->get('/daftar-pelanggan')->assertSuccessful();
    }

    public function test_tambah_produk_dengan_stok_awal_membuat_jurnal_pembelian(): void
    {
        $this->authed();
        $gudang = \App\Modules\Wms\Models\Gudang::where('kode', 'GDG-01')->firstOrFail();

        $produk = app(\App\Modules\Wms\Services\ProdukService::class)->buatProduk(
            nama: 'Baterai Samsung Baru',
            kategori: 'Baterai',
            brand: 'Samsung',
            model: 'A54',
            kondisi: 'baru',
            hargaBeli: 80000,
            hargaJual: 150000,
            gudangId: $gudang->id,
            stokAwal: 10,
            stokMinimum: 3,
            userId: auth()->id()
        );

        // Produk + variant + stok + stok log tercatat
        $this->assertDatabaseHas('produk', ['id' => $produk->id]);
        $this->assertDatabaseHas('sku_variants', ['produk_id' => $produk->id]);
        $this->assertDatabaseHas('stok_items', ['produk_id' => $produk->id, 'jumlah' => 10]);
        $this->assertDatabaseHas('stok_log', [
            'produk_id' => $produk->id, 'jenis' => 'pembelian', 'perubahan' => 10,
        ]);

        // Jurnal pembelian: Debit Persediaan (130-01) = 800.000, utk Kredit Utang (210-01)
        $this->assertDatabaseHas('jurnal_akuntansi', [
            'sumber' => 'pembelian',
            'akun_coa_id' => \App\Modules\Akunting\Models\AkunCOA::where('kode', '130-01')->first()->id,
            'debit' => 800000,
        ]);

        // Double-entry balance untuk jurnal pembelian
        $noJurnal = \Illuminate\Support\Facades\DB::table('jurnal_akuntansi')
            ->where('sumber', 'pembelian')->value('no_jurnal');
        $debit = \Illuminate\Support\Facades\DB::table('jurnal_akuntansi')->where('no_jurnal', $noJurnal)->sum('debit');
        $kredit = \Illuminate\Support\Facades\DB::table('jurnal_akuntansi')->where('no_jurnal', $noJurnal)->sum('kredit');
        $this->assertEqualsWithDelta($debit, $kredit, 0.01);
    }
}