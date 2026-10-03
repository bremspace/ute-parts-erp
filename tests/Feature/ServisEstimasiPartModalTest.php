<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Rbac\Models\Cabang;
use App\Modules\Servis\Livewire\ServisBoard;
use App\Modules\Servis\Models\TiketServis;
use App\Modules\Wms\Models\Produk;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ServisEstimasiPartModalTest extends TestCase
{
    use RefreshDatabase;

    private Cabang $cabang;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cabang = Cabang::create([
            'nama' => 'Cabang Test Servis Part',
            'kode' => 'CTSP',
            'alamat' => 'Jl. Test No. 12',
            'telepon' => '08123456789',
            'is_active' => true,
        ]);

        session(['cabang_id' => $this->cabang->id]);

        $permissions = [
            'servis.view',
            'servis.create',
            'servis.update-status',
            'servis.approve-estimasi',
            'servis.input-sparepart',
            'laporan.cabang',
        ];

        foreach ($permissions as $p) {
            Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
        }

        $superRole = Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']);
        $superRole->syncPermissions($permissions);

        Role::firstOrCreate(['name' => 'teknisi', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'admin-toko', 'guard_name' => 'web']);

        $this->user = User::create([
            'name' => 'Admin Servis Part',
            'email' => 'admin-servis-part@example.com',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);
        $this->user->assignRole('super-admin');
        $this->user->cabangs()->attach($this->cabang->id);
    }

    public function test_klik_cari_part_di_estimasi_membuka_modal_inline(): void
    {
        $this->actingAs($this->user, 'web');

        $tiket = TiketServis::create([
            'cabang_id' => $this->cabang->id,
            'no_tiket' => 'SRV-TEST-PART-01',
            'nama_pelanggan' => 'User Test Part',
            'telepon_pelanggan' => '0811223344',
            'jenis_hp' => 'iPhone 13',
            'keluhan' => 'Layar bergaris',
            'status' => 'diagnosa',
            'tanggal_terima' => now(),
        ]);

        Livewire::test(ServisBoard::class)
            ->call('openEstimasiModal', $tiket->id)
            ->assertSet('showEstimasiModal', true)
            ->call('addEstimasiRow', 'part')
            ->assertCount('estimasiItems', 2)
            ->call('bukaPencarianProduk', 1, 'servis_estimasi')
            ->assertSet('showCariPartModal', true)
            ->assertSet('cariPartIndex', 1)
            ->assertSet('cariPartContext', 'servis_estimasi');
    }

    public function test_pencarian_part_menampilkan_produk_dan_bisa_dipilih(): void
    {
        $this->actingAs($this->user, 'web');

        $produk = Produk::create([
            'nama' => 'LCD iPhone 13 OLED',
            'barcode' => '899123456001',
            'harga_beli' => 800000,
            'harga_jual_retail' => 1200000,
            'is_active' => true,
        ]);

        $tiket = TiketServis::create([
            'cabang_id' => $this->cabang->id,
            'no_tiket' => 'SRV-TEST-PART-02',
            'nama_pelanggan' => 'Test User',
            'telepon_pelanggan' => '0811223355',
            'jenis_hp' => 'iPhone 13',
            'keluhan' => 'Layar blank',
            'status' => 'diagnosa',
            'tanggal_terima' => now(),
        ]);

        Livewire::test(ServisBoard::class)
            ->call('openEstimasiModal', $tiket->id)
            ->call('addEstimasiRow', 'part')
            ->call('bukaPencarianProduk', 1, 'servis_estimasi')
            ->assertSet('showCariPartModal', true)
            ->set('cariPartQuery', 'LCD iPhone')
            ->assertSee('LCD iPhone 13 OLED')
            ->call('pilihPartServis', $produk->id)
            ->assertSet('showCariPartModal', false)
            ->assertSet('estimasiItems.1.produk_id', $produk->id)
            ->assertSet('estimasiItems.1.produk_nama', 'LCD iPhone 13 OLED')
            ->assertSet('estimasiItems.1.nama_item', 'LCD iPhone 13 OLED')
            ->assertSet('estimasiItems.1.harga', 1200000);
    }

    public function test_reset_part_row_menghapus_pilihan_produk(): void
    {
        $this->actingAs($this->user, 'web');

        $produk = Produk::create([
            'nama' => 'LCD iPhone 13 OLED Super',
            'barcode' => '899123456002',
            'harga_beli' => 900000,
            'harga_jual_retail' => 1350000,
            'is_active' => true,
        ]);

        $tiket = TiketServis::create([
            'cabang_id' => $this->cabang->id,
            'no_tiket' => 'SRV-TEST-PART-03',
            'nama_pelanggan' => 'Test User 3',
            'telepon_pelanggan' => '0811223366',
            'jenis_hp' => 'iPhone 13',
            'keluhan' => 'Layar pecah',
            'status' => 'diagnosa',
            'tanggal_terima' => now(),
        ]);

        Livewire::test(ServisBoard::class)
            ->call('openEstimasiModal', $tiket->id)
            ->call('addEstimasiRow', 'part')
            ->call('bukaPencarianProduk', 1, 'servis_estimasi')
            ->call('pilihPartServis', $produk->id)
            ->assertSet('estimasiItems.1.produk_id', $produk->id)
            ->assertSet('estimasiItems.1.harga', 1350000)
            ->call('resetEstimasiPartRow', 1)
            ->assertSet('estimasiItems.1.produk_id', null)
            ->assertSet('estimasiItems.1.produk_nama', '')
            ->assertSet('estimasiItems.1.nama_item', '')
            ->assertSet('estimasiItems.1.harga', 0)
            ->assertSet('estimasiBiaya', 0);
    }
}
