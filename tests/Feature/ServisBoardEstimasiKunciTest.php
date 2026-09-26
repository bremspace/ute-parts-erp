<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Crm\Models\Pelanggan;
use App\Modules\Rbac\Models\Cabang;
use App\Modules\Servis\Livewire\ServisBoard;
use App\Modules\Servis\Models\JenisServis;
use App\Modules\Servis\Models\TiketServis;
use App\Modules\Servis\Models\TiketServisEstimasiItem;
use App\Modules\Wms\Models\Gudang;
use App\Modules\Wms\Models\Produk;
use App\Modules\Wms\Models\StokItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ServisBoardEstimasiKunciTest extends TestCase
{
    use RefreshDatabase;

    private Cabang $cabang;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cabang = Cabang::create([
            'nama' => 'Cabang Estimasi Test',
            'kode' => 'CET',
            'alamat' => 'Jl. Estimasi No. 1',
            'telepon' => '0811111111',
            'is_active' => true,
        ]);

        session(['cabang_id' => $this->cabang->id]);

        $permissions = [
            'servis.view',
            'servis.create',
            'servis.update-status',
            'laporan.cabang',
        ];

        foreach ($permissions as $p) {
            Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
        }

        $superRole = Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']);
        $superRole->syncPermissions($permissions);

        Role::firstOrCreate(['name' => 'teknisi', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'admin-toko', 'guard_name' => 'web']);

        $this->admin = User::create([
            'name' => 'Super Admin Test',
            'email' => 'admin-test@example.com',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);
        $this->admin->assignRole('super-admin');
        $this->admin->cabangs()->attach($this->cabang->id);
    }

    public function test_reset_pola_kunci_pada_terima_unit(): void
    {
        $this->actingAs($this->admin, 'web');

        Livewire::test(ServisBoard::class)
            ->call('openTerimaModal')
            ->set('terimaForm.tipe_kunci', 'pola')
            ->set('terimaForm.kunci_terenkripsi', [1, 2, 3])
            ->assertSet('terimaForm.kunci_terenkripsi', [1, 2, 3])
            ->set('terimaForm.kunci_terenkripsi', '')
            ->assertSet('terimaForm.kunci_terenkripsi', '');
    }

    public function test_visual_pola_kunci_pada_detail_tiket(): void
    {
        $this->actingAs($this->admin, 'web');

        $tiket = TiketServis::create([
            'cabang_id' => $this->cabang->id,
            'no_tiket' => 'SRV-TEST-POLA',
            'nama_pelanggan' => 'Pola User',
            'telepon_pelanggan' => '08123456789',
            'jenis_hp' => 'Xiaomi Note 10',
            'keluhan' => 'Layar sentuh sebagian mati',
            'status' => 'diagnosa',
            'tipe_kunci' => 'pola',
            'kunci_terenkripsi' => '1-2-3-6-9',
            'tanggal_terima' => now(),
        ]);

        Livewire::test(ServisBoard::class)
            ->call('openDetail', $tiket->id)
            ->assertSee('••••••••')
            ->call('toggleKunciGadget')
            ->assertSee('1-2-3-6-9')
            ->assertSee('Visual Pola (Urutan Sentuh):');
    }

    public function test_buka_modal_estimasi_dan_simpan_estimasi_multi_baris(): void
    {
        $this->actingAs($this->admin, 'web');

        $pelanggan = Pelanggan::create([
            'cabang_id' => $this->cabang->id,
            'nama' => 'Konsumen Budi',
            'telepon' => '081987654321',
        ]);

        $produk = Produk::create([
            'nama' => 'LCD iPhone 11 Original',
            'slug' => 'lcd-iphone-11-original',
            'harga_beli' => 200000,
            'harga_jual_retail' => 350000,
            'is_active' => true,
        ]);

        $gudang = Gudang::create([
            'cabang_id' => $this->cabang->id,
            'nama' => 'Gudang Utama',
            'kode' => 'GDG-UTM',
            'is_active' => true,
        ]);

        StokItem::create([
            'produk_id' => $produk->id,
            'gudang_id' => $gudang->id,
            'jumlah' => 10,
        ]);

        $jenisServis = JenisServis::create([
            'nama' => 'Ganti LCD Ringan',
            'kode' => 'LCD-01',
            'biaya_jasa' => 75000,
            'durasi_garansi_hari' => 30,
            'is_active' => true,
        ]);

        $tiket = TiketServis::create([
            'cabang_id' => $this->cabang->id,
            'no_tiket' => 'SRV-TEST-EST',
            'pelanggan_id' => $pelanggan->id,
            'nama_pelanggan' => $pelanggan->nama,
            'telepon_pelanggan' => $pelanggan->telepon,
            'jenis_hp' => 'iPhone 11',
            'keluhan' => 'Layar pecah bergaris',
            'status' => 'diagnosa',
            'tanggal_terima' => now(),
        ]);

        $test = Livewire::test(ServisBoard::class)
            ->call('openEstimasiModal', $tiket->id)
            ->assertSet('showEstimasiModal', true)
            ->assertCount('estimasiItems', 1)
            // Tambah baris part
            ->call('addEstimasiRow', 'part')
            ->assertCount('estimasiItems', 2)
            // Set jenis_servis di baris 0 (jasa)
            ->set('estimasiItems.0.jenis_servis_id', $jenisServis->id)
            ->assertSet('estimasiItems.0.nama_item', 'Ganti LCD Ringan')
            ->assertSet('estimasiItems.0.harga', 75000)
            // Set produk_id di baris 1 (part)
            ->set('estimasiItems.1.produk_id', $produk->id)
            ->assertSet('estimasiItems.1.nama_item', 'LCD iPhone 11 Original')
            ->assertSet('estimasiItems.1.harga', 350000)
            // Total biaya otomatis sinkron: 75.000 + 350.000 = 425.000
            ->assertSet('estimasiBiaya', 425000)
            ->set('estimasiAlasan', 'Penggantian LCD Original + jasa pasang bergaransi 30 hari')
            ->call('simpanEstimasi')
            ->assertDispatched('alert')
            ->assertSet('showEstimasiModal', false);

        $tiket->refresh();
        $this->assertEquals('menunggu_approval', $tiket->status);
        $this->assertEquals(425000, (float) $tiket->estimasi_biaya);
        $this->assertDatabaseHas('tiket_servis_estimasi_item', [
            'tiket_servis_id' => $tiket->id,
            'tipe' => 'jasa',
            'nama_item' => 'Ganti LCD Ringan',
            'harga' => 75000,
        ]);
        $this->assertDatabaseHas('tiket_servis_estimasi_item', [
            'tiket_servis_id' => $tiket->id,
            'tipe' => 'part',
            'produk_id' => $produk->id,
            'nama_item' => 'LCD iPhone 11 Original',
            'harga' => 350000,
        ]);
    }

    public function test_prefill_estimasi_items_ke_pekerjaan_teknisi(): void
    {
        $this->actingAs($this->admin, 'web');

        $tiket = TiketServis::create([
            'cabang_id' => $this->cabang->id,
            'no_tiket' => 'SRV-TEST-PREFILL',
            'nama_pelanggan' => 'Andi',
            'telepon_pelanggan' => '0812345678',
            'jenis_hp' => 'Samsung S20',
            'keluhan' => 'Mati total',
            'status' => 'disetujui',
            'tanggal_terima' => now(),
        ]);

        TiketServisEstimasiItem::create([
            'tiket_servis_id' => $tiket->id,
            'tipe' => 'jasa',
            'nama_item' => 'Jasa Analisa Mesin',
            'qty' => 1,
            'harga' => 100000,
            'subtotal' => 100000,
        ]);

        TiketServisEstimasiItem::create([
            'tiket_servis_id' => $tiket->id,
            'tipe' => 'part',
            'nama_item' => 'IC Power',
            'qty' => 2,
            'harga' => 150000,
            'subtotal' => 300000,
        ]);

        Livewire::test(ServisBoard::class)
            ->call('openDetail', $tiket->id)
            ->assertCount('pekerjaanItems', 2)
            ->assertSet('pekerjaanItems.0.nama_item', 'Jasa Analisa Mesin')
            ->assertSet('pekerjaanItems.1.nama_item', 'IC Power')
            ->assertSet('pekerjaanItems.1.qty', 2);
    }
}
