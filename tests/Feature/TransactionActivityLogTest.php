<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Crm\Models\Pelanggan;
use App\Modules\Pos\Models\ReturnPenjualan;
use App\Modules\Pos\Models\Transaksi;
use App\Modules\Rbac\Livewire\RiwayatAktivitas;
use App\Modules\Rbac\Livewire\SettingsRbac;
use App\Modules\Rbac\Models\Cabang;
use App\Modules\Rbac\Services\TransactionActivityLogService;
use App\Modules\Wms\Models\Gudang;
use App\Modules\Wms\Models\KategoriProduk;
use App\Modules\Wms\Models\Produk;
use App\Modules\Wms\Models\StokItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TransactionActivityLogTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Cabang $cabang;

    protected Gudang $gudang;

    protected Produk $produkA;

    protected Produk $produkB;

    protected Pelanggan $pelanggan;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'lihat-audit-log', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'pos.transaksi', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'pengaturan.manage', 'guard_name' => 'web']);
        Role::findByName('super-admin', 'web')->givePermissionTo(['lihat-audit-log', 'pos.transaksi', 'pengaturan.manage']);

        $this->cabang = Cabang::create(['nama' => 'Cabang Utama Log', 'kode' => 'CUL01', 'is_active' => true]);
        $this->gudang = Gudang::create(['nama' => 'Gudang Pusat Log', 'kode' => 'GPL01', 'cabang_id' => $this->cabang->id, 'is_active' => true]);

        $this->user = User::create([
            'name' => 'Kasir Utama',
            'email' => 'kasir@logtest.com',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);
        $this->user->assignRole('super-admin');
        $this->user->cabangs()->attach($this->cabang->id);

        $this->actingAs($this->user);
        session(['cabang_id' => $this->cabang->id]);

        $kat = KategoriProduk::create(['nama' => 'Sparepart']);

        $this->produkA = Produk::create([
            'nama' => 'LCD iPhone 11 Original',
            'barcode' => 'LCD-IPH11',
            'kategori_id' => $kat->id,
            'harga_beli' => 200000,
            'harga_jual_retail' => 350000,
            'is_active' => true,
        ]);

        $this->produkB = Produk::create([
            'nama' => 'Baterai iPhone 11 Original',
            'barcode' => 'BAT-IPH11',
            'kategori_id' => $kat->id,
            'harga_beli' => 100000,
            'harga_jual_retail' => 180000,
            'is_active' => true,
        ]);

        StokItem::create(['gudang_id' => $this->gudang->id, 'produk_id' => $this->produkA->id, 'jumlah' => 50]);
        StokItem::create(['gudang_id' => $this->gudang->id, 'produk_id' => $this->produkB->id, 'jumlah' => 50]);

        $this->pelanggan = Pelanggan::create([
            'nama' => 'Budi Pembeli',
            'telepon' => '081299990001',
            'cabang_id' => $this->cabang->id,
            'is_active' => true,
        ]);
    }

    public function test_catat_penjualan_transaction_centric_dan_dual_indexing_properties(): void
    {
        $trx = Transaksi::create([
            'no_transaksi' => 'TRX-LOG-0001',
            'cabang_id' => $this->cabang->id,
            'kasir_id' => $this->user->id,
            'pelanggan_id' => $this->pelanggan->id,
            'gudang_id' => $this->gudang->id,
            'subtotal' => 530000,
            'total_akhir' => 530000,
            'metode_bayar' => 'tunai',
            'jumlah_bayar' => 530000,
            'kembalian' => 0,
            'status' => 'selesai',
        ]);

        $trx->items()->create([
            'produk_id' => $this->produkA->id,
            'jumlah' => 1,
            'harga_satuan' => 350000,
            'subtotal' => 350000,
        ]);

        $trx->items()->create([
            'produk_id' => $this->produkB->id,
            'jumlah' => 1,
            'harga_satuan' => 180000,
            'subtotal' => 180000,
        ]);

        $service = app(TransactionActivityLogService::class);
        $log = $service->catatPenjualan($trx);

        $this->assertNotNull($log);
        $this->assertEquals(Transaksi::class, $log->subject_type);
        $this->assertEquals($trx->id, $log->subject_id);
        $this->assertEquals('penjualan', $log->event);
        $this->assertEquals($this->cabang->id, $log->cabang_id);

        $props = $log->properties;
        $this->assertContains($this->produkA->id, $props['produk_ids']);
        $this->assertContains($this->produkB->id, $props['produk_ids']);
        $this->assertCount(2, $props['items']);
        $this->assertStringContainsString('Budi Pembeli', $props['ringkasan']);
        $this->assertStringContainsString('Penjualan kasir', $props['ringkasan']);
    }

    public function test_catat_void_transaksi_menyimpan_alasan_dan_dual_indexing(): void
    {
        $trx = Transaksi::create([
            'no_transaksi' => 'TRX-VOID-0001',
            'cabang_id' => $this->cabang->id,
            'kasir_id' => $this->user->id,
            'pelanggan_id' => $this->pelanggan->id,
            'gudang_id' => $this->gudang->id,
            'subtotal' => 350000,
            'total_akhir' => 350000,
            'metode_bayar' => 'tunai',
            'status' => 'selesai',
        ]);

        $trx->items()->create([
            'produk_id' => $this->produkA->id,
            'jumlah' => 1,
            'harga_satuan' => 350000,
            'subtotal' => 350000,
        ]);

        $service = app(TransactionActivityLogService::class);
        $log = $service->catatVoid($trx, $this->user, 'Konsumen salah pilih seri handphone');

        $this->assertNotNull($log);
        $this->assertEquals('void', $log->event);
        $props = $log->properties;
        $this->assertEquals('Konsumen salah pilih seri handphone', $props['alasan']);
        $this->assertContains($this->produkA->id, $props['produk_ids']);
        $this->assertStringContainsString('dibatalkan (VOID)', $props['ringkasan']);
    }

    public function test_catat_retur_penjualan_exchange_dual_indexing_produk_retur_dan_tukar(): void
    {
        $trx = Transaksi::create([
            'no_transaksi' => 'TRX-ASAL-0001',
            'cabang_id' => $this->cabang->id,
            'kasir_id' => $this->user->id,
            'pelanggan_id' => $this->pelanggan->id,
            'gudang_id' => $this->gudang->id,
            'subtotal' => 180000,
            'total_akhir' => 180000,
            'metode_bayar' => 'tunai',
            'status' => 'selesai',
        ]);

        $trxItem = $trx->items()->create([
            'produk_id' => $this->produkB->id,
            'jumlah' => 1,
            'harga_satuan' => 180000,
            'subtotal' => 180000,
        ]);

        $retur = ReturnPenjualan::create([
            'no_return' => 'RET-EXCH-0001',
            'transaksi_id' => $trx->id,
            'cabang_id' => $this->cabang->id,
            'gudang_id' => $this->gudang->id,
            'pelanggan_id' => $this->pelanggan->id,
            'jumlah' => 180000,
            'status' => 'selesai',
            'tipe_penyelesaian' => 'tukar_produk',
            'alasan' => 'Baterai drop cacat pabrik',
            'selisih_nominal' => 170000,
            'tukar_items_payload' => [
                [
                    'produk_id' => $this->produkA->id,
                    'jumlah' => 1,
                    'harga_satuan' => 350000,
                ],
            ],
        ]);

        $retur->items()->create([
            'transaksi_item_id' => $trxItem->id,
            'produk_id' => $this->produkB->id,
            'jumlah' => 1,
            'harga_satuan' => 180000,
            'subtotal' => 180000,
            'kondisi_barang' => 'rusak',
            'alasan' => 'Baterai drop',
            'tipe_penyelesaian' => 'tukar_produk',
        ]);

        $service = app(TransactionActivityLogService::class);
        $log = $service->catatReturPenjualan($retur);

        $this->assertNotNull($log);
        $this->assertEquals(ReturnPenjualan::class, $log->subject_type);
        $this->assertEquals('retur_penjualan', $log->event);

        $props = $log->properties;
        // Dual-indexing must contain BOTH the returned product (produkB) AND the exchanged product (produkA)
        $this->assertContains($this->produkB->id, $props['produk_ids']);
        $this->assertContains($this->produkA->id, $props['produk_ids']);
        $this->assertEquals(170000, $props['selisih_nominal']);
        $this->assertStringContainsString('Tukar Produk (Exchange)', $props['ringkasan']);
    }

    public function test_dual_indexing_menampilkan_riwayat_transaksi_di_modal_produk(): void
    {
        // 1. Catat log transaksi penjualan melibatkan produkA
        $trx = Transaksi::create([
            'no_transaksi' => 'TRX-DUAL-0001',
            'cabang_id' => $this->cabang->id,
            'kasir_id' => $this->user->id,
            'pelanggan_id' => $this->pelanggan->id,
            'gudang_id' => $this->gudang->id,
            'subtotal' => 350000,
            'total_akhir' => 350000,
            'metode_bayar' => 'tunai',
            'status' => 'selesai',
        ]);

        $trx->items()->create([
            'produk_id' => $this->produkA->id,
            'jumlah' => 1,
            'harga_satuan' => 350000,
            'subtotal' => 350000,
        ]);

        app(TransactionActivityLogService::class)->catatPenjualan($trx);

        // 2. Buka RiwayatAktivitas untuk produkA (tipe = 'produk', entityId = $this->produkA->id)
        $component = Livewire::test(RiwayatAktivitas::class, [
            'tipe' => 'produk',
            'entityId' => $this->produkA->id,
        ]);

        $component->assertStatus(200);

        // Transaksi penjualan harus terlihat pada riwayat produkA berkat Dual-Indexing
        $component->assertSee('TRX-DUAL-0001');
        $component->assertSee('Penjualan Kasir');
        $component->assertSee($this->produkA->nama);
    }

    public function test_filter_kategori_transaksi_dan_produk_pada_central_audit_trail(): void
    {
        $trx = Transaksi::create([
            'no_transaksi' => 'TRX-FILTER-0001',
            'cabang_id' => $this->cabang->id,
            'kasir_id' => $this->user->id,
            'pelanggan_id' => $this->pelanggan->id,
            'gudang_id' => $this->gudang->id,
            'subtotal' => 350000,
            'total_akhir' => 350000,
            'metode_bayar' => 'tunai',
            'status' => 'selesai',
        ]);
        $trx->items()->create([
            'produk_id' => $this->produkA->id,
            'jumlah' => 1,
            'harga_satuan' => 350000,
            'subtotal' => 350000,
        ]);
        app(TransactionActivityLogService::class)->catatPenjualan($trx);

        // Test central audit trail tanpa tipe entitas spesifik
        $component = Livewire::test(RiwayatAktivitas::class);
        $component->assertStatus(200);
        $component->assertSee('TRX-FILTER-0001');

        // Filter kategori alur transaksi
        $component->set('filterKategoriTransaksi', 'penjualan');
        $component->assertSee('TRX-FILTER-0001');

        $component->set('filterKategoriTransaksi', 'void');
        $component->assertDontSee('TRX-FILTER-0001');

        // Filter cari produk
        $component->set('filterKategoriTransaksi', '');
        $component->set('filterProduk', 'LCD-IPH11');
        $component->assertSee('TRX-FILTER-0001');

        $component->set('filterProduk', 'PRODUK-YANG-TIDAK-ADA');
        $component->assertDontSee('TRX-FILTER-0001');
    }

    public function test_audit_trail_terintegrasi_di_tab_pengaturan_rbac(): void
    {
        $component = Livewire::test(SettingsRbac::class);
        $component->assertStatus(200);

        // Pindah ke tab 'audit'
        $component->set('activeTab', 'audit');
        $component->assertStatus(200);
        $component->assertSee('Audit Trail');
    }
}
