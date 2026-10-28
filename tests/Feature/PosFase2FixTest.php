<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Crm\Models\Pelanggan;
use App\Modules\Pos\Livewire\PosKasir;
use App\Modules\Pos\Livewire\RiwayatTransaksiIndex;
use App\Modules\Pos\Models\Transaksi;
use App\Modules\Pos\Models\TransaksiItem;
use App\Modules\Rbac\Models\Cabang;
use App\Modules\Wms\Models\Gudang;
use App\Modules\Wms\Models\Produk;
use App\Modules\Wms\Models\StokItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PosFase2FixTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Cabang $cabang;

    protected Gudang $gudang;

    protected Produk $produk;

    protected Pelanggan $pelanggan;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'kasir', 'guard_name' => 'web']);

        $this->cabang = Cabang::create(['nama' => 'Cabang Test POS', 'kode' => 'CTP01', 'is_active' => true]);

        $this->gudang = Gudang::create([
            'cabang_id' => $this->cabang->id,
            'nama' => 'Gudang Toko Test',
            'kode' => 'GTT01',
            'is_active' => true,
        ]);

        $this->user = User::create([
            'name' => 'Kasir Test',
            'email' => 'kasir@test.com',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);
        $this->user->assignRole('super-admin');
        $this->user->cabangs()->attach($this->cabang->id);

        $this->produk = Produk::create([
            'sku' => 'SKU-TEST-001',
            'nama' => 'LCD Test Pro',
            'harga_jual_retail' => 100000,
            'satuan' => 'pcs',
            'is_active' => true,
        ]);

        StokItem::create([
            'produk_id' => $this->produk->id,
            'gudang_id' => $this->gudang->id,
            'jumlah' => 50,
        ]);

        $this->pelanggan = Pelanggan::create([
            'nama' => 'Budi Santoso',
            'telepon' => '081234567890',
            'tipe_konsumen' => 'retail',
            'poin_loyalty' => 100,
        ]);
    }

    public function test_t06_qty_keranjang_bisa_diset_manual(): void
    {
        session(['cabang_id' => $this->cabang->id]);

        $component = Livewire::actingAs($this->user)
            ->test(PosKasir::class)
            ->set('selectedGudangId', $this->gudang->id)
            ->call('addToCart', $this->produk->id);

        $itemKey = "{$this->produk->id}-0";

        $component->call('setQty', $itemKey, 5);

        $cart = $component->get('cart');
        $this->assertArrayHasKey($itemKey, $cart);
        $this->assertEquals(5, $cart[$itemKey]['qty']);
        $this->assertEquals(500000, $component->get('subtotal'));
    }

    public function test_t07_auto_show_pelanggan_dan_produk_pada_pos(): void
    {
        session(['cabang_id' => $this->cabang->id]);

        $component = Livewire::actingAs($this->user)
            ->test(PosKasir::class)
            ->set('selectedGudangId', $this->gudang->id);

        // Saat pelangganSearch kosong, pelangganCari tetap mengembalikan daftar awal
        $pelangganList = $component->get('pelangganCari');
        $this->assertNotEmpty($pelangganList);
        $this->assertTrue($pelangganList->contains('id', $this->pelanggan->id));
    }

    public function test_t08_auto_split_bill_balance(): void
    {
        session(['cabang_id' => $this->cabang->id]);

        $component = Livewire::actingAs($this->user)
            ->test(PosKasir::class)
            ->set('selectedGudangId', $this->gudang->id)
            ->call('addToCart', $this->produk->id) // 100.000
            ->set('metodeBayar', 'split')
            ->set('splitMetodeNonTunai', 'qris');

        // Total akhir = 100.000
        $this->assertEquals(100000, $component->get('totalAkhir'));

        // Ubah splitTunai ke 40.000 -> splitNonTunai otomatis 60.000
        $component->set('splitTunai', '40.000');
        $this->assertEquals('60.000', $component->get('splitNonTunai'));

        // Ubah splitNonTunai ke 75.000 -> splitTunai otomatis 25.000
        $component->set('splitNonTunai', '75.000');
        $this->assertEquals('25.000', $component->get('splitTunai'));
    }

    public function test_t10_format_struk_piutang_memuat_tanda_dan_jatuh_tempo(): void
    {
        session(['cabang_id' => $this->cabang->id]);

        $trx = Transaksi::create([
            'no_transaksi' => 'TRX-PIUTANG-001',
            'cabang_id' => $this->cabang->id,
            'user_id' => $this->user->id,
            'pelanggan_id' => $this->pelanggan->id,
            'sumber' => 'pos',
            'metode_bayar' => 'piutang',
            'status' => 'belum_lunas',
            'subtotal' => 200000,
            'total_akhir' => 200000,
            'jumlah_bayar' => 0,
            'kembalian' => 0,
            'diskon_nominal' => 0,
            'pajak_nominal' => 0,
        ]);

        TransaksiItem::create([
            'transaksi_id' => $trx->id,
            'produk_id' => $this->produk->id,
            'jumlah' => 2,
            'harga_satuan' => 100000,
            'subtotal' => 200000,
            'satuan' => 'pcs',
        ]);

        // Cek lihatDetailTransaksi di PosKasir
        $component = Livewire::actingAs($this->user)
            ->test(PosKasir::class)
            ->call('lihatDetailTransaksi', $trx->id);

        $receipt = $component->get('receiptData');
        $this->assertNotNull($receipt);
        $this->assertTrue($receipt['is_piutang']);
        $this->assertNotEmpty($receipt['jatuh_tempo']);
        $this->assertEquals(200000, $receipt['sisa_piutang']);

        // Cek cetakUlangStruk di RiwayatTransaksiIndex
        $riwayatComponent = Livewire::actingAs($this->user)
            ->test(RiwayatTransaksiIndex::class)
            ->call('cetakUlangStruk', 'pos', $trx->id);

        $riwayatReceipt = $riwayatComponent->get('receiptData');
        $this->assertNotNull($riwayatReceipt);
        $this->assertTrue($riwayatReceipt['is_piutang']);
        $this->assertNotEmpty($riwayatReceipt['jatuh_tempo']);
        $this->assertEquals(200000, $riwayatReceipt['sisa_piutang']);
    }

    public function test_t17_riwayat_custom_date_responsif(): void
    {
        session(['cabang_id' => $this->cabang->id]);

        $component = Livewire::actingAs($this->user)
            ->test(RiwayatTransaksiIndex::class)
            ->set('filterTanggalPreset', 'custom')
            ->set('filterTanggalDari', '2026-03-01')
            ->set('filterTanggalSampai', '2026-03-15');

        $this->assertEquals('custom', $component->get('filterTanggalPreset'));
        $this->assertEquals('2026-03-01', $component->get('filterTanggalDari'));
        $this->assertEquals('2026-03-15', $component->get('filterTanggalSampai'));
        $this->assertEquals('2026-03-01', $component->get('filterTanggal'));
    }

    public function test_t05_dan_t09_modal_z_index_dan_qris_zoom_minimalis(): void
    {
        $thermalReceiptBlade = file_get_contents(resource_path('views/partials/thermal-receipt-modal.blade.php'));
        $this->assertStringContainsString('z-[80]', $thermalReceiptBlade);

        $paymentModalBlade = file_get_contents(resource_path('views/modules/pos/livewire/modals/payment-modal.blade.php'));
        $this->assertStringContainsString('bg-black/45 backdrop-blur-sm', $paymentModalBlade);

        $bayarServisModalBlade = file_get_contents(resource_path('views/modules/pos/livewire/modals/bayar-servis-modal.blade.php'));
        $this->assertStringContainsString('bg-black/45 backdrop-blur-sm', $bayarServisModalBlade);
    }
}
