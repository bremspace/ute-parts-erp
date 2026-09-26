<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Crm\Models\Pelanggan;
use App\Modules\Crm\Models\TierMembership;
use App\Modules\Rbac\Models\Cabang;
use App\Modules\Servis\Models\JenisServis;
use App\Modules\Servis\Models\TiketServis;
use App\Modules\Servis\Models\TiketServisEstimasiItem;
use App\Modules\Servis\Services\ServisService;
use App\Modules\Wms\Models\Gudang;
use App\Modules\Wms\Models\Produk;
use App\Modules\Wms\Models\StokItem;
use Database\Seeders\AkunCoaSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ServisEstimasiTerperinciTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Cabang $cabang;

    private Gudang $gudang;

    private JenisServis $jenisServis;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(AkunCoaSeeder::class);
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->cabang = Cabang::create(['nama' => 'Pusat', 'kode' => 'CBG-01', 'is_active' => true]);
        $this->gudang = Gudang::create([
            'cabang_id' => $this->cabang->id,
            'nama' => 'Gudang Pusat',
            'kode' => 'GDG-01',
            'is_active' => true,
        ]);

        $this->jenisServis = JenisServis::create([
            'nama' => 'Ganti LCD',
            'kode' => 'LCD-01',
            'biaya_jasa' => 75000,
            'durasi_garansi_hari' => 30,
            'is_part_original' => false,
            'is_active' => true,
        ]);

        $this->user = User::create([
            'name' => 'Admin Servis',
            'email' => 'admin-servis-test@test.com',
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);
        $this->user->assignRole('super-admin');
        $this->user->cabangs()->attach($this->cabang->id, ['is_default' => true]);
        session(['cabang_id' => $this->cabang->id]);
        $this->actingAs($this->user, 'web');
    }

    private function createTiket(array $attrs = []): TiketServis
    {
        return TiketServis::create(array_merge([
            'no_tiket' => 'SRV-TEST-'.uniqid(),
            'cabang_id' => $this->cabang->id,
            'jenis_servis_id' => $this->jenisServis->id,
            'nama_pelanggan' => 'Budi',
            'telepon_pelanggan' => '081234567890',
            'jenis_hp' => 'iPhone',
            'seri_hp' => '13 Pro',
            'keluhan' => 'Layar bergaris',
            'kondisi_fisik' => [],
            'foto_unit' => [],
            'status' => 'diagnosa',
            'sumber' => 'offline',
        ], $attrs));
    }

    private function createProduk(float $hargaJual = 200000): Produk
    {
        $nama = 'LCD iPhone 13 Pro OEM '.uniqid();

        return Produk::create([
            'nama' => $nama,
            'slug' => str($nama)->slug()->value(),
            'kategori' => 'Sparepart',
            'kondisi' => 'baru',
            'harga_beli' => 120000,
            'harga_jual_retail' => $hargaJual,
            'sn' => false,
            'is_active' => true,
        ]);
    }

    public function test_estimasi_dengan_rincian_part_dan_jasa_tersimpan_di_estimasi_item(): void
    {
        $tiket = $this->createTiket();
        $produk = $this->createProduk(250000);

        $items = [
            [
                'tipe' => 'jasa',
                'jenis_servis_id' => $this->jenisServis->id,
                'qty' => 1,
                'harga' => 80000,
            ],
            [
                'tipe' => 'part',
                'produk_id' => $produk->id,
                'qty' => 2,
                'harga' => 250000,
            ],
        ];

        $service = app(ServisService::class);
        $updatedTiket = $service->setEstimasi($tiket, 0, 'Perlu ganti LCD & jasa pasang', $this->user, $items);

        $this->assertEquals('menunggu_approval', $updatedTiket->status);
        $this->assertNotEmpty($updatedTiket->token_approval);
        $this->assertCount(2, $updatedTiket->estimasiItems);

        $this->assertDatabaseHas('tiket_servis_estimasi_item', [
            'tiket_servis_id' => $tiket->id,
            'tipe' => 'jasa',
            'jenis_servis_id' => $this->jenisServis->id,
            'qty' => 1,
            'harga' => 80000,
            'subtotal' => 80000,
        ]);

        $this->assertDatabaseHas('tiket_servis_estimasi_item', [
            'tiket_servis_id' => $tiket->id,
            'tipe' => 'part',
            'produk_id' => $produk->id,
            'qty' => 2,
            'harga' => 250000,
            'subtotal' => 500000,
        ]);
    }

    public function test_harga_part_ter_resolve_sesuai_tier_pelanggan_jika_tidak_dispesifikasikan(): void
    {
        $tierGrosir = TierMembership::create([
            'nama' => 'Grosir',
            'kode' => 'GROSIR',
            'diskon_persen' => 10,
            'is_active' => true,
        ]);

        $pelanggan = Pelanggan::create([
            'cabang_id' => $this->cabang->id,
            'nama' => 'Toko Mitra',
            'telepon' => '089988776655',
            'tier_membership_id' => $tierGrosir->id,
            'is_active' => true,
        ]);

        $tiket = $this->createTiket(['pelanggan_id' => $pelanggan->id]);
        $produk = $this->createProduk(200000); // 10% diskon = 180000

        $items = [
            [
                'tipe' => 'part',
                'produk_id' => $produk->id,
                'qty' => 1,
                // harga tidak diisi, harus resolve otomatis via PricingService
            ],
            [
                'tipe' => 'jasa',
                'jenis_servis_id' => $this->jenisServis->id,
                // harga tidak diisi, harus ambil dari jenis_servis.biaya_jasa (75000)
            ],
        ];

        $service = app(ServisService::class);
        $updatedTiket = $service->setEstimasi($tiket, 0, 'Estimasi pelanggan grosir', $this->user, $items);

        $partItem = TiketServisEstimasiItem::where('tiket_servis_id', $tiket->id)
            ->where('tipe', 'part')
            ->first();

        $this->assertNotNull($partItem);
        $this->assertEquals(180000, $partItem->harga);
        $this->assertEquals(180000, $partItem->subtotal);

        $jasaItem = TiketServisEstimasiItem::where('tiket_servis_id', $tiket->id)
            ->where('tipe', 'jasa')
            ->first();

        $this->assertNotNull($jasaItem);
        $this->assertEquals(75000, $jasaItem->harga);
        $this->assertEquals(75000, $jasaItem->subtotal);
    }

    public function test_total_estimasi_biaya_terhitung_otomatis_dari_sum_item(): void
    {
        $tiket = $this->createTiket();
        $produk = $this->createProduk(150000);

        $items = [
            [
                'tipe' => 'jasa',
                'jenis_servis_id' => $this->jenisServis->id,
                'qty' => 1,
                'harga' => 50000,
            ],
            [
                'tipe' => 'part',
                'produk_id' => $produk->id,
                'qty' => 2,
                'harga' => 150000,
            ],
        ];

        $service = app(ServisService::class);
        // Biaya dioper 0 → harus otomatis hitung: 50.000 + (2 * 150.000) = 350.000
        $updatedTiket = $service->setEstimasi($tiket, 0, 'Auto compute total', $this->user, $items);

        $this->assertEquals(350000, $updatedTiket->estimasi_biaya);
        $this->assertDatabaseHas('tiket_servis', [
            'id' => $tiket->id,
            'estimasi_biaya' => 350000,
        ]);
    }

    public function test_stok_toko_tidak_berkurang_saat_set_estimasi(): void
    {
        $tiket = $this->createTiket();
        $produk = $this->createProduk(200000);

        $stokAwal = 10;
        $stokItem = StokItem::create([
            'gudang_id' => $this->gudang->id,
            'produk_id' => $produk->id,
            'jumlah' => $stokAwal,
        ]);

        $items = [
            [
                'tipe' => 'part',
                'produk_id' => $produk->id,
                'qty' => 3,
                'harga' => 200000,
            ],
        ];

        $service = app(ServisService::class);
        $service->setEstimasi($tiket, 600000, 'Estimasi tidak boleh potong stok', $this->user, $items);

        $stokItem->refresh();
        $this->assertEquals($stokAwal, $stokItem->jumlah, 'Stok item tidak boleh berkurang saat estimasi');

        $this->assertDatabaseMissing('stok_log', [
            'produk_id' => $produk->id,
            'referensi_tipe' => 'tiket_servis',
            'referensi_id' => $tiket->id,
        ]);
    }
}
