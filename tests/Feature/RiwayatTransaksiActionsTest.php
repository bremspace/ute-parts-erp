<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Crm\Models\Pelanggan;
use App\Modules\Pos\Livewire\RiwayatTransaksiIndex;
use App\Modules\Pos\Models\ReturnPenjualan;
use App\Modules\Pos\Models\Transaksi;
use App\Modules\Pos\Models\TransaksiItem;
use App\Modules\Rbac\Models\Cabang;
use App\Modules\Wms\Models\Gudang;
use App\Modules\Wms\Models\KategoriProduk;
use App\Modules\Wms\Models\Produk;
use App\Modules\Wms\Models\StokItem;
use Database\Seeders\AkunCoaSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class RiwayatTransaksiActionsTest extends TestCase
{
    use RefreshDatabase;

    private Cabang $cabang;

    private Cabang $cabangLain;

    private User $kasirUser;

    private User $supervisorUser;

    private Pelanggan $pelanggan;

    private Produk $produk;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(AkunCoaSeeder::class);

        $this->cabang = Cabang::create([
            'nama' => 'Cabang Utama Test',
            'kode' => 'CBG-UTAMA',
            'is_active' => true,
        ]);

        $this->cabangLain = Cabang::create([
            'nama' => 'Cabang Lain Test',
            'kode' => 'CBG-LAIN',
            'is_active' => true,
        ]);

        $this->kasirUser = User::create([
            'name' => 'Kasir POS Test',
            'email' => 'kasir-actions@uteparts.id',
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);
        $this->kasirUser->assignRole('kasir');
        $this->kasirUser->cabangs()->attach($this->cabang->id);

        $this->supervisorUser = User::create([
            'name' => 'Supervisor Toko Test',
            'email' => 'supervisor-actions@uteparts.id',
            'password' => Hash::make('supervisorSecret123'),
            'is_active' => true,
        ]);
        $this->supervisorUser->assignRole('admin-toko');
        $this->supervisorUser->cabangs()->attach($this->cabang->id);

        $this->pelanggan = Pelanggan::create([
            'cabang_id' => $this->cabang->id,
            'nama' => 'Pelanggan POS Test',
            'telepon' => '0899999999',
        ]);

        $kategori = KategoriProduk::create([
            'nama' => 'Sparepart Test',
            'kode' => 'SP-TEST',
            'cabang_id' => $this->cabang->id,
        ]);

        $this->produk = Produk::create([
            'kategori_id' => $kategori->id,
            'nama' => 'Baterai iPhone 11',
            'harga_beli' => 100000,
            'harga_jual_retail' => 200000,
            'satuan' => 'pcs',
            'is_active' => true,
        ]);

        $gudang = Gudang::create([
            'cabang_id' => $this->cabang->id,
            'nama' => 'Gudang Utama Test',
            'kode' => 'GUD-ACT-01',
            'is_active' => true,
        ]);

        StokItem::create([
            'produk_id' => $this->produk->id,
            'gudang_id' => $gudang->id,
            'jumlah' => 10,
        ]);
    }

    private function buatTransaksi(array $overrides = []): Transaksi
    {
        $createdAt = $overrides['created_at'] ?? now();
        unset($overrides['created_at']);

        $trx = new Transaksi(array_merge([
            'no_transaksi' => 'TRX-ACT-'.uniqid(),
            'cabang_id' => $this->cabang->id,
            'kasir_id' => $this->kasirUser->id,
            'pelanggan_id' => $this->pelanggan->id,
            'sumber' => 'pos',
            'subtotal' => 200000,
            'total_akhir' => 200000,
            'metode_bayar' => 'tunai',
            'status' => 'selesai',
        ], $overrides));

        $trx->created_at = $createdAt;
        $trx->save();

        TransaksiItem::create([
            'transaksi_id' => $trx->id,
            'produk_id' => $this->produk->id,
            'jumlah' => 1,
            'harga_satuan' => 200000,
            'harga_beli' => 100000,
            'subtotal' => 200000,
        ]);

        return $trx;
    }

    public function test_date_presets_and_timezone_boundaries_filter_correctly(): void
    {
        session(['cabang_id' => $this->cabang->id]);
        $this->actingAs($this->kasirUser, 'web');

        $tz = config('app.timezone', 'Asia/Jakarta');
        $nowTz = Carbon::now($tz);

        // Transaksi Hari ini
        $trxHariIni = $this->buatTransaksi([
            'no_transaksi' => 'TRX-TODAY-01',
            'created_at' => $nowTz->copy()->midDay()->utc(),
        ]);

        // Transaksi Kemarin
        $trxKemarin = $this->buatTransaksi([
            'no_transaksi' => 'TRX-YEST-01',
            'created_at' => $nowTz->copy()->subDay()->midDay()->utc(),
        ]);

        // Transaksi Bulan Lalu
        $trxBulanLalu = $this->buatTransaksi([
            'no_transaksi' => 'TRX-PAST-01',
            'created_at' => $nowTz->copy()->subMonth()->startOfMonth()->midDay()->utc(),
        ]);

        // 1. Preset Today
        Livewire::test(RiwayatTransaksiIndex::class, ['activeTab' => 'pos'])
            ->set('filterTanggalPreset', 'today')
            ->assertSee($trxHariIni->no_transaksi)
            ->assertDontSee($trxKemarin->no_transaksi)
            ->assertDontSee($trxBulanLalu->no_transaksi);

        // 2. Preset Yesterday
        Livewire::test(RiwayatTransaksiIndex::class, ['activeTab' => 'pos'])
            ->set('filterTanggalPreset', 'yesterday')
            ->assertSee($trxKemarin->no_transaksi)
            ->assertDontSee($trxHariIni->no_transaksi)
            ->assertDontSee($trxBulanLalu->no_transaksi);

        // 3. Custom Date Range
        $dari = $nowTz->copy()->subMonth()->startOfMonth()->toDateString();
        $sampai = $nowTz->copy()->subMonth()->endOfMonth()->toDateString();

        Livewire::test(RiwayatTransaksiIndex::class, ['activeTab' => 'pos'])
            ->set('filterTanggalPreset', 'custom')
            ->set('filterTanggalDari', $dari)
            ->set('filterTanggalSampai', $sampai)
            ->assertSee($trxBulanLalu->no_transaksi)
            ->assertDontSee($trxHariIni->no_transaksi);
    }

    public function test_branch_scoping_prevents_seeing_or_voiding_other_branch_transactions(): void
    {
        $trxCabangLain = $this->buatTransaksi([
            'no_transaksi' => 'TRX-OTHER-BRANCH-01',
            'cabang_id' => $this->cabangLain->id,
            'created_at' => now(),
        ]);

        session(['cabang_id' => $this->cabang->id]);
        $this->actingAs($this->kasirUser, 'web');

        Livewire::test(RiwayatTransaksiIndex::class, ['activeTab' => 'pos'])
            ->set('filterTanggalPreset', 'today')
            ->assertDontSee($trxCabangLain->no_transaksi)
            ->call('bukaVoidModal', $trxCabangLain->id)
            ->assertSet('showVoidModal', false)
            ->assertDispatched('alert', fn ($event, $params) => ($params[0]['type'] ?? $params['type'] ?? '') === 'error');
    }

    public function test_void_permission_and_successful_supervisor_void_flow(): void
    {
        $trx = $this->buatTransaksi([
            'no_transaksi' => 'TRX-TO-VOID-01',
            'created_at' => now(),
        ]);

        // User tanpa pos.void tidak melihat tombol void
        session(['cabang_id' => $this->cabang->id]);
        $this->actingAs($this->kasirUser, 'web');

        Livewire::test(RiwayatTransaksiIndex::class, ['activeTab' => 'pos'])
            ->assertDontSee('bukaVoidModal')
            ->assertSee('/app/pos/retur?no_faktur='.$trx->no_transaksi)
            ->assertSee('↩ Retur');

        // User dengan izin pos.void
        $adminUser = User::create([
            'name' => 'Admin POS Test',
            'email' => 'admin-pos@uteparts.id',
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);
        $adminUser->assignRole('super-admin');
        $adminUser->cabangs()->attach($this->cabang->id);

        $this->actingAs($adminUser, 'web');

        Livewire::test(RiwayatTransaksiIndex::class, ['activeTab' => 'pos'])
            ->assertSee('bukaVoidModal('.$trx->id.')', false)
            ->call('bukaVoidModal', $trx->id)
            ->assertSet('showVoidModal', true)
            ->assertSet('voidTransaksiId', $trx->id)
            ->set('voidAlasan', 'Salah input kasir')
            ->set('voidSupervisorAuth', $this->supervisorUser->email)
            ->set('voidSupervisorPassword', 'supervisorSecret123')
            ->call('prosesVoidTransaksi')
            ->assertSet('showVoidModal', false)
            ->assertDispatched('alert', fn ($event, $params) => ($params[0]['type'] ?? $params['type'] ?? '') === 'success');

        $this->assertEquals('void', $trx->fresh()->status);
    }

    public function test_global_history_filters_void_and_return_entries_with_audit_actions(): void
    {
        $void = $this->buatTransaksi([
            'no_transaksi' => 'TRX-HISTORY-VOID',
            'status' => 'void',
            'void_by_id' => $this->supervisorUser->id,
            'void_at' => now(),
            'void_alasan' => 'Salah nominal kasir',
        ]);
        $normal = $this->buatTransaksi(['no_transaksi' => 'TRX-HISTORY-NORMAL']);

        $retur = ReturnPenjualan::create([
            'no_return' => 'RET-HISTORY-001',
            'transaksi_id' => $normal->id,
            'pelanggan_id' => $this->pelanggan->id,
            'cabang_id' => $this->cabang->id,
            'user_id' => $this->kasirUser->id,
            'tanggal' => now(),
            'jumlah' => 200000,
            'status' => 'selesai',
            'metode_pengembalian' => 'kas',
            'alasan' => 'Barang tidak sesuai',
        ]);

        session(['cabang_id' => $this->cabang->id]);
        $this->actingAs($this->kasirUser, 'web');

        Livewire::test(RiwayatTransaksiIndex::class)
            ->set('filterStatus', 'void')
            ->assertSee($void->no_transaksi)
            ->assertDontSee($retur->no_return)
            ->call('lihatDetailTransaksi', $void->id)
            ->assertSet('selectedDetail.void.oleh', $this->supervisorUser->name)
            ->assertSet('selectedDetail.void.alasan', 'Salah nominal kasir')
            ->call('bukaRiwayat', 'transaksi', $void->id)
            ->assertSet('riwayatTipe', 'transaksi')
            ->assertSet('riwayatId', $void->id)
            ->set('filterStatus', 'retur')
            ->assertSee($retur->no_return)
            ->assertDontSee($normal->no_transaksi);
    }

    public function test_modal_detail_retur_renders_complete_data_and_print_action(): void
    {
        $normal = $this->buatTransaksi(['no_transaksi' => 'TRX-RETUR-SAMPLE']);

        $retur = ReturnPenjualan::create([
            'no_return' => 'RET-DETAIL-001',
            'transaksi_id' => $normal->id,
            'pelanggan_id' => $this->pelanggan->id,
            'cabang_id' => $this->cabang->id,
            'user_id' => $this->kasirUser->id,
            'tanggal' => now(),
            'jumlah' => 200000,
            'status' => 'selesai',
            'metode_pengembalian' => 'kas',
            'alasan' => 'Barang cacat pabrik',
            'catatan' => 'Lolos QC retur',
        ]);

        $retur->items()->create([
            'produk_id' => $this->produk->id,
            'jumlah' => 1,
            'harga_satuan' => 200000,
            'subtotal' => 200000,
            'kondisi_barang' => 'rusak',
            'alasan' => 'Tidak mau charge',
        ]);

        session(['cabang_id' => $this->cabang->id]);
        $this->actingAs($this->kasirUser, 'web');

        Livewire::test(RiwayatTransaksiIndex::class)
            ->call('lihatDetailRetur', $retur->id)
            ->assertSet('showDetailModal', true)
            ->assertSet('selectedDetail.tipe', 'retur')
            ->assertSet('selectedDetail.no_return', 'RET-DETAIL-001')
            ->assertSet('selectedDetail.no_transaksi', 'TRX-RETUR-SAMPLE')
            ->assertSet('selectedDetail.alasan', 'Barang cacat pabrik')
            ->assertSee('RET-DETAIL-001')
            ->assertSee('TRX-RETUR-SAMPLE')
            ->assertSee('Baterai iPhone 11')
            ->call('cetakUlangStruk', 'retur', $retur->id)
            ->assertSet('showReceiptModal', true)
            ->assertSet('receiptData.no_transaksi', 'RET-DETAIL-001')
            ->assertSet('receiptData.total', 200000.0);
    }
}
