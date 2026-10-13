<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Crm\Models\Pelanggan;
use App\Modules\Pos\Models\HargaTier;
use App\Modules\Rbac\Models\Cabang;
use App\Modules\Servis\Livewire\ServisBoard;
use App\Modules\Servis\Models\Garansi;
use App\Modules\Servis\Models\JenisServis;
use App\Modules\Servis\Models\TiketServis;
use App\Modules\Servis\Models\TiketServisEstimasiItem;
use App\Modules\Servis\Services\ServisService;
use App\Modules\Wms\Models\Gudang;
use App\Modules\Wms\Models\Produk;
use App\Modules\Wms\Models\StokItem;
use Database\Seeders\AkunCoaSeeder;
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

        $this->seed(AkunCoaSeeder::class);

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

    public function test_harga_part_default_reseller_dan_proteksi_edit(): void
    {
        $produk = Produk::create([
            'nama' => 'Baterai iPhone 12',
            'slug' => 'baterai-iphone-12',
            'harga_beli' => 100000,
            'harga_jual_retail' => 250000,
            'is_active' => true,
        ]);

        HargaTier::create([
            'produk_id' => $produk->id,
            'tipe_konsumen' => 'reseller',
            'harga' => 180000,
            'nominal_tetap' => 180000,
        ]);

        // 1. Cek resolusi harga via ServisService
        $resolved = app(ServisService::class)->resolveHargaPartDefault($produk);
        $this->assertEquals(180000, $resolved['harga']);

        // 2. User teknisi (bukan owner/super-admin) tidak bisa edit harga part
        $teknisi = User::create([
            'name' => 'Teknisi Joko',
            'email' => 'teknisi-joko@example.com',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);
        $teknisi->assignRole('teknisi');
        $teknisi->givePermissionTo('servis.view', 'servis.create', 'servis.update-status');
        $teknisi->cabangs()->attach($this->cabang->id);

        $this->actingAs($teknisi, 'web');

        $tiket = TiketServis::create([
            'cabang_id' => $this->cabang->id,
            'no_tiket' => 'SRV-TEST-PART-RESELLER',
            'nama_pelanggan' => 'Pelanggan Biasa',
            'telepon_pelanggan' => '0899999999',
            'jenis_hp' => 'iPhone 12',
            'keluhan' => 'Baterai drop',
            'status' => 'diagnosa',
            'tanggal_terima' => now(),
        ]);

        Livewire::test(ServisBoard::class)
            ->call('openEstimasiModal', $tiket->id)
            ->call('addEstimasiRow', 'part')
            ->set('estimasiItems.1.produk_id', $produk->id)
            ->assertSet('estimasiItems.1.harga', 180000)
            // Coba teknisi ubah manual harga part -> tetap 180000 (terproteksi)
            ->set('estimasiItems.1.harga', 220000)
            ->assertSet('estimasiItems.1.harga', 180000)
            ->assertSet('canEditHargaPart', false);

        // 3. User super-admin bisa ubah manual harga part
        $this->actingAs($this->admin, 'web');
        Livewire::test(ServisBoard::class)
            ->call('openEstimasiModal', $tiket->id)
            ->call('addEstimasiRow', 'part')
            ->set('estimasiItems.1.produk_id', $produk->id)
            ->assertSet('estimasiItems.1.harga', 180000)
            ->set('estimasiItems.1.harga', 220000)
            ->assertSet('estimasiItems.1.harga', 220000)
            ->assertSet('canEditHargaPart', true);
    }

    public function test_garansi_custom_dan_aturan_jasa_tanpa_garansi(): void
    {
        $this->actingAs($this->admin, 'web');

        $jenisServis = JenisServis::create([
            'nama' => 'Pembersihan Port Audio',
            'kode' => 'CLN-01',
            'biaya_jasa' => 50000,
            'durasi_garansi_hari' => 14,
            'is_active' => true,
        ]);

        $tiketJasa = TiketServis::create([
            'cabang_id' => $this->cabang->id,
            'no_tiket' => 'SRV-TEST-JASA-ONLY',
            'nama_pelanggan' => 'Konsumen Jasa',
            'telepon_pelanggan' => '0877777777',
            'jenis_hp' => 'Samsung A52',
            'keluhan' => 'Port kotor',
            'status' => 'diagnosa',
            'tanggal_terima' => now(),
        ]);

        // Simpan estimasi hanya jasa
        Livewire::test(ServisBoard::class)
            ->call('openEstimasiModal', $tiketJasa->id)
            ->set('estimasiItems.0.jenis_servis_id', $jenisServis->id)
            ->call('simpanEstimasi');

        $tiketJasa->refresh();
        $servisService = app(ServisService::class);
        $servisService->updateStatus($tiketJasa, 'disetujui');
        $tiketJasa->refresh();
        $servisService->updateStatus($tiketJasa, 'dikerjakan');
        $tiketJasa->refresh();
        $servisService->updateStatus($tiketJasa, 'qc');
        $tiketJasa->refresh();
        $servisService->updateStatus($tiketJasa, 'selesai');

        // Servis khusus jasa: Garansi TIDAK diterbitkan
        $this->assertNull(Garansi::where('tiket_servis_id', $tiketJasa->id)->first());

        // Tiket kedua dengan part dan garansi custom 45 hari
        $produk = Produk::create([
            'nama' => 'Konektor Cas A52',
            'slug' => 'konektor-cas-a52',
            'harga_beli' => 20000,
            'harga_jual_retail' => 70000,
            'is_active' => true,
        ]);

        $tiketPart = TiketServis::create([
            'cabang_id' => $this->cabang->id,
            'no_tiket' => 'SRV-TEST-PART-CUSTOM',
            'nama_pelanggan' => 'Konsumen Part',
            'telepon_pelanggan' => '0866666666',
            'jenis_hp' => 'Samsung A52',
            'keluhan' => 'Tidak bisa cas',
            'status' => 'diagnosa',
            'tanggal_terima' => now(),
        ]);

        Livewire::test(ServisBoard::class)
            ->call('openEstimasiModal', $tiketPart->id)
            ->call('addEstimasiRow', 'part')
            ->set('estimasiItems.1.produk_id', $produk->id)
            ->set('estimasiDurasiGaransiCustom', 45)
            ->call('simpanEstimasi');

        $tiketPart->refresh();
        $this->assertEquals(45, $tiketPart->durasi_garansi_custom);

        $servisService->updateStatus($tiketPart, 'disetujui');
        $tiketPart->refresh();
        $servisService->updateStatus($tiketPart, 'dikerjakan');
        $tiketPart->refresh();
        $servisService->updateStatus($tiketPart, 'qc');
        $tiketPart->refresh();
        $servisService->updateStatus($tiketPart, 'selesai');

        $garansi = Garansi::where('tiket_servis_id', $tiketPart->id)->first();
        $this->assertNotNull($garansi);
        $this->assertEquals(45, $garansi->durasi_hari);
    }
}
