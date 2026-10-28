<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Akunting\Livewire\AkuntingDashboard;
use App\Modules\Crm\Models\Pelanggan;
use App\Modules\Dashboard\Livewire\DashboardIndex;
use App\Modules\Pos\Models\Transaksi;
use App\Modules\Pos\Models\TransaksiItem;
use App\Modules\Rbac\Models\Cabang;
use App\Modules\Servis\Models\TiketServis;
use App\Modules\Wms\Models\Gudang;
use App\Modules\Wms\Models\KategoriProduk;
use App\Modules\Wms\Models\Produk;
use App\Modules\Wms\Models\PurchaseOrder;
use App\Modules\Wms\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DashboardFase1FixTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Cabang $cabang;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'kasir', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'akunting.view', 'guard_name' => 'web']);
        Role::findByName('super-admin', 'web')->givePermissionTo('akunting.view');

        $this->cabang = Cabang::create(['nama' => 'Cabang Utama', 'kode' => 'C01', 'is_active' => true]);

        $this->user = User::create([
            'name' => 'Admin User',
            'email' => 'admin@test.com',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);
        $this->user->assignRole('super-admin');
        $this->user->cabangs()->attach($this->cabang->id);
    }

    public function test_t01_filter_sumber_website_dan_status_selesai_dan_pagination()
    {
        session(['cabang_id' => $this->cabang->id]);

        // Buat 20 transaksi sumber marketplace / channel
        for ($i = 1; $i <= 20; $i++) {
            Transaksi::create([
                'no_transaksi' => 'TRX-WEB-'.str_pad($i, 3, '0', STR_PAD_LEFT),
                'cabang_id' => $this->cabang->id,
                'user_id' => $this->user->id,
                'sumber' => $i % 2 === 0 ? 'marketplace' : 'website',
                'status' => 'lunas',
                'total_akhir' => 50000,
                'subtotal' => 50000,
                'diskon_nominal' => 0,
                'pajak_nominal' => 0,
                'jumlah_bayar' => 50000,
                'kembalian' => 0,
                'metode_bayar' => 'transfer',
            ]);
        }

        $component = Livewire::actingAs($this->user)
            ->test(DashboardIndex::class)
            ->set('periodePenjualan', 'today')
            ->set('penjualanFilterSumber', 'website')
            ->set('penjualanFilterStatus', 'selesai');

        $component->assertStatus(200);

        // Verifikasi trackingPenjualan pagination 15 item
        $tracking = $component->get('trackingPenjualan');
        $this->assertEquals(20, $tracking->total());
        $this->assertEquals(15, $tracking->perPage());
        $this->assertCount(15, $tracking->items());

        // Verifikasi detail modal tracking
        $firstId = $tracking->first()->id;
        $component->call('lihatDetailTransaksi', $firstId)
            ->assertSet('showDetailTransaksiModal', true)
            ->assertSee($tracking->first()->no_transaksi);
    }

    public function test_t02_komposisi_kategori_produk_dan_tren_omzet()
    {
        session(['cabang_id' => $this->cabang->id]);

        $kategori = KategoriProduk::create(['nama' => 'Sparepart Mesin', 'slug' => 'sparepart-mesin']);
        $produk = Produk::create([
            'nama' => 'Piston Racing',
            'kategori_id' => $kategori->id,
            'kategori' => 'Sparepart Mesin',
            'harga_jual' => 100000,
            'harga_beli' => 70000,
            'stok' => 10,
        ]);

        $trx = Transaksi::create([
            'no_transaksi' => 'TRX-OMZET-01',
            'cabang_id' => $this->cabang->id,
            'user_id' => $this->user->id,
            'sumber' => 'pos',
            'status' => 'selesai',
            'total_akhir' => 100000,
            'subtotal' => 100000,
            'diskon_nominal' => 0,
            'pajak_nominal' => 0,
            'jumlah_bayar' => 100000,
            'kembalian' => 0,
            'metode_bayar' => 'tunai',
        ]);

        TransaksiItem::create([
            'transaksi_id' => $trx->id,
            'produk_id' => $produk->id,
            'jumlah' => 1,
            'harga_satuan' => 100000,
            'subtotal' => 100000,
        ]);

        $component = Livewire::actingAs($this->user)->test(DashboardIndex::class);
        $chartKategori = $component->get('chartKategori');

        $this->assertContains('Sparepart Mesin', $chartKategori['labels']);
        $this->assertGreaterThan(0, $chartKategori['total']);

        $chartOmzet = $component->get('chartOmzet30Hari');
        $this->assertGreaterThan(0, $chartOmzet['total']);
    }

    public function test_t03_transaksi_terbaru_multi_entity_dan_role_gate()
    {
        session(['cabang_id' => $this->cabang->id]);

        // Buat record multi entity
        $supplier = Supplier::create(['nama' => 'PT Supplier Utama', 'kode' => 'SUP01']);
        $gudang = Gudang::create(['nama' => 'Gudang Pusat', 'kode' => 'GDG01', 'cabang_id' => $this->cabang->id]);

        PurchaseOrder::create([
            'no_po' => 'PO-TEST-001',
            'supplier_id' => $supplier->id,
            'gudang_tujuan_id' => $gudang->id,
            'status' => 'dikirim',
            'total' => 250000,
        ]);

        $pelanggan = Pelanggan::create(['nama' => 'Budi Santoso', 'telepon' => '08123456789']);

        TiketServis::create([
            'no_tiket' => 'SRV-TEST-001',
            'pelanggan_id' => $pelanggan->id,
            'cabang_id' => $this->cabang->id,
            'jenis_hp' => 'iPhone 13',
            'keluhan' => 'Layar retak',
            'status' => 'dikerjakan',
            'estimasi_biaya' => 150000,
        ]);

        // User super-admin bisa melihat feed multi entitas
        $componentSuper = Livewire::actingAs($this->user)->test(DashboardIndex::class);
        $feed = $componentSuper->get('transaksiTerbaru');

        $this->assertTrue($componentSuper->get('canViewTransaksiTerbaru'));
        $this->assertNotEmpty($feed);
        $noDokumenList = collect($feed)->pluck('no_dokumen')->all();
        $this->assertContains('PO-TEST-001', $noDokumenList);
        $this->assertContains('SRV-TEST-001', $noDokumenList);

        // Kasir biasa tanpa izin tidak bisa melihat feed multi entitas
        $userKasir = User::create([
            'name' => 'Kasir User',
            'email' => 'kasir@test.com',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);
        $userKasir->assignRole('kasir');
        $userKasir->cabangs()->attach($this->cabang->id);

        $componentKasir = Livewire::actingAs($userKasir)->test(DashboardIndex::class);
        $this->assertFalse($componentKasir->get('canViewTransaksiTerbaru'));
        $this->assertEmpty($componentKasir->get('transaksiTerbaru'));
    }

    public function test_t04_drilldown_piutang_dan_utang_dan_tab_handler()
    {
        session(['cabang_id' => $this->cabang->id]);

        $component = Livewire::actingAs($this->user)->test(DashboardIndex::class);
        $treasury = $component->get('treasuryProjection');

        $this->assertStringContainsString('tab=piutang', $treasury['drill_piutang']);
        $this->assertStringContainsString('tab=utang', $treasury['drill_utang']);

        // Test handler tab di AkuntingDashboard
        $this->actingAs($this->user)->get(route('akunting', ['tab' => 'piutang']))->assertSeeLivewire(AkuntingDashboard::class);

        $componentAkunting = Livewire::actingAs($this->user)
            ->withQueryParams(['tab' => 'piutang'])
            ->test(AkuntingDashboard::class);

        $this->assertEquals('piutang', $componentAkunting->get('activeTab'));

        $componentAkuntingUtang = Livewire::actingAs($this->user)
            ->withQueryParams(['tab' => 'utang'])
            ->test(AkuntingDashboard::class);

        $this->assertEquals('utang', $componentAkuntingUtang->get('activeTab'));
    }
}
