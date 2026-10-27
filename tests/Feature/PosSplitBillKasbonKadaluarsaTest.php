<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Akunting\Models\JurnalHeader;
use App\Modules\Akunting\Models\Piutang;
use App\Modules\Crm\Models\Pelanggan;
use App\Modules\Pos\Livewire\PesananOnlineIndex;
use App\Modules\Pos\Livewire\PosKasir;
use App\Modules\Pos\Livewire\RiwayatTransaksiIndex;
use App\Modules\Pos\Models\Transaksi;
use App\Modules\Pos\Services\KasSesiState;
use App\Modules\Rbac\Models\Cabang;
use App\Modules\Wms\Models\Gudang;
use App\Modules\Wms\Models\KategoriProduk;
use App\Modules\Wms\Models\Produk;
use App\Modules\Wms\Models\StokItem;
use Database\Seeders\AkunCoaSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class PosSplitBillKasbonKadaluarsaTest extends TestCase
{
    use RefreshDatabase;

    private User $kasir;

    private Cabang $cabang;

    private Gudang $gudang;

    private Produk $produk;

    private Pelanggan $pelanggan;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(AkunCoaSeeder::class);

        $this->cabang = Cabang::create([
            'kode' => 'CB01',
            'nama' => 'Cabang Uji POS',
            'tipe' => 'cabang',
            'is_active' => true,
        ]);

        $this->gudang = Gudang::create([
            'kode' => 'GD-CB01',
            'nama' => 'Gudang Uji CB01',
            'cabang_id' => $this->cabang->id,
            'is_active' => true,
        ]);

        $this->kasir = User::create([
            'name' => 'Kasir Uji',
            'email' => 'kasir.uji@uteparts.test',
            'password' => Hash::make('password'),
            'cabang_id' => $this->cabang->id,
        ]);
        $this->kasir->assignRole('kasir');
        $this->kasir->cabangs()->attach($this->cabang->id);

        $kategori = KategoriProduk::create([
            'kode' => 'SP',
            'nama' => 'Sparepart',
            'is_active' => true,
        ]);

        $this->produk = Produk::create([
            'kode' => 'PRD-001',
            'nama' => 'Baterai iPhone 11',
            'kategori_id' => $kategori->id,
            'kategori' => 'Baterai',
            'harga_beli' => 100000,
            'harga_jual_retail' => 150000,
            'satuan' => 'pcs',
            'is_active' => true,
            'sn' => false,
        ]);

        StokItem::create([
            'produk_id' => $this->produk->id,
            'gudang_id' => $this->gudang->id,
            'jumlah' => 20,
            'jumlah_minimum' => 1,
        ]);

        $this->pelanggan = Pelanggan::create([
            'nama' => 'Budi Pelanggan',
            'telepon' => '081234567890',
            'tipe_konsumen' => 'retail',
            'saldo_piutang' => 0,
            'max_piutang' => 500000,
        ]);

        session(['cabang_id' => $this->cabang->id]);
    }

    private function bukaSesiKas(): void
    {
        app(KasSesiState::class)->bukaKas(
            100000,
            $this->cabang->id,
            $this->kasir->id
        );
    }

    public function test_kembalian_dan_livewire_cache_unset(): void
    {
        $this->actingAs($this->kasir);
        $this->bukaSesiKas();

        $comp = Livewire::test(PosKasir::class)
            ->call('addToCart', $this->produk->id)
            ->call('openPaymentModal');

        // Total akhir = 150.000
        $this->assertEquals(150000, $comp->get('totalAkhir'));

        // Input bayar pas → kembalian 0
        $comp->set('jumlahBayar', '150.000');
        $this->assertEquals(0, $comp->get('kembalian'));

        // Quick cash 200.000 → kembalian 50.000
        $comp->call('setQuickCash', 200000);
        $this->assertEquals(50000, $comp->get('kembalian'));

        // Input 300.000 → kembalian 150.000
        $comp->set('jumlahBayar', '300.000');
        $this->assertEquals(150000, $comp->get('kembalian'));
    }

    public function test_transaksi_split_bill_kembalian_dan_jurnal_balance(): void
    {
        $this->actingAs($this->kasir);
        $this->bukaSesiKas();

        $comp = Livewire::test(PosKasir::class)
            ->call('addToCart', $this->produk->id)
            ->call('openPaymentModal')
            ->set('metodeBayar', 'split')
            ->set('splitMetodeNonTunai', 'qris')
            ->set('splitNonTunai', '50.000')
            ->set('splitTunai', '120.000'); // total bayar = 170.000, belanja = 150.000 → kembalian 20.000

        $this->assertEquals(20000, $comp->get('kembalian'));

        $comp->call('processTransaction');

        $transaksi = Transaksi::latest('id')->first();
        $this->assertNotNull($transaksi);
        $this->assertEquals('split', $transaksi->metode_bayar);
        $this->assertEquals(150000, (float) $transaksi->total_akhir);
        $this->assertEquals(170000, (float) $transaksi->jumlah_bayar);
        $this->assertEquals(20000, (float) $transaksi->kembalian);

        // Periksa jurnal akuntansi: total debit harus sama persis dengan total kredit
        $jurnal = JurnalHeader::where('referensi_tipe', Transaksi::class)
            ->where('referensi_id', $transaksi->id)
            ->with('lines.akun')
            ->first();

        $this->assertNotNull($jurnal);
        $this->assertTrue($jurnal->balanced(), 'Jurnal split bill harus balance');

        $totalDebit = (float) $jurnal->lines->sum('debit');
        $totalKredit = (float) $jurnal->lines->sum('kredit');
        $this->assertEquals($totalKredit, $totalDebit, 'Jurnal split bill harus balance');

        // Porsi non-tunai (110-02): 50.000
        $debitBank = (float) $jurnal->lines->filter(fn ($l) => $l->akun?->kode === '110-02')->sum('debit');
        $this->assertEquals(50000, $debitBank);

        // Porsi kas laci (110-04): 150.000 - 50.000 = 100.000 (bersih setelah potong kembalian 20.000)
        $debitKas = (float) $jurnal->lines->filter(fn ($l) => $l->akun?->kode === '110-04')->sum('debit');
        $this->assertEquals(100000, $debitKas);
    }

    public function test_transaksi_kasbon_piutang_dan_saldo_piutang_pelanggan(): void
    {
        $this->actingAs($this->kasir);

        $comp = Livewire::test(PosKasir::class)
            ->call('addToCart', $this->produk->id)
            ->call('setPelanggan', $this->pelanggan->id)
            ->call('openPaymentModal')
            ->set('metodeBayar', 'piutang')
            ->call('processTransaction');

        $transaksi = Transaksi::latest('id')->first();
        $this->assertNotNull($transaksi);
        $this->assertEquals('piutang', $transaksi->metode_bayar);
        $this->assertEquals(0, (float) $transaksi->jumlah_bayar);
        $this->assertEquals(0, (float) $transaksi->kembalian);

        // Piutang terbuat dengan sisa sesuai total akhir
        $piutang = Piutang::where('transaksi_id', $transaksi->id)->first();
        $this->assertNotNull($piutang);
        $this->assertEquals(150000, (float) $piutang->jumlah);
        $this->assertEquals(150000, (float) $piutang->sisa);
        $this->assertEquals('belum_lunas', $piutang->status);

        // Saldo piutang pelanggan ter-increment
        $this->pelanggan->refresh();
        $this->assertEquals(150000, (float) $this->pelanggan->saldo_piutang);
    }

    public function test_transaksi_kasbon_menolak_melebihi_plafon_limit(): void
    {
        $this->actingAs($this->kasir);

        // Set saldo piutang sudah mendekati limit (400.000 dari limit 500.000)
        $this->pelanggan->update(['saldo_piutang' => 400000, 'max_piutang' => 500000]);

        // Tambah qty 1 item (150.000) → 400.000 + 150.000 = 550.000 (> 500.000)
        $comp = Livewire::test(PosKasir::class)
            ->call('addToCart', $this->produk->id)
            ->call('setPelanggan', $this->pelanggan->id)
            ->call('openPaymentModal')
            ->set('metodeBayar', 'piutang')
            ->call('processTransaction');

        $comp->assertDispatched('alert', function ($event, $params) {
            return $params[0]['type'] === 'error' && str_contains($params[0]['message'], 'plafon');
        });

        // Transaksi tidak tersimpan
        $this->assertEquals(0, Transaksi::count());
    }

    public function test_pesanan_online_dan_riwayat_menampilkan_status_kadaluarsa(): void
    {
        $this->actingAs($this->kasir);

        // Buat transaksi dengan status kadaluarsa
        $trxKadaluarsa = Transaksi::create([
            'no_transaksi' => 'TRX-ONLINE-KADALUARSA',
            'cabang_id' => $this->cabang->id,
            'kasir_id' => $this->kasir->id,
            'sumber' => 'marketplace',
            'subtotal' => 150000,
            'total_akhir' => 150000,
            'metode_bayar' => 'duitku',
            'status' => 'kadaluarsa',
        ]);

        // Test filter di PesananOnlineIndex
        Livewire::test(PesananOnlineIndex::class)
            ->set('statusFilter', 'kadaluarsa')
            ->assertSee('TRX-ONLINE-KADALUARSA')
            ->assertSee('Kadaluarsa');

        // Test filter di RiwayatTransaksiIndex
        Livewire::test(RiwayatTransaksiIndex::class, ['activeTab' => 'online'])
            ->set('filterStatus', 'kadaluarsa')
            ->assertSee('TRX-ONLINE-KADALUARSA');
    }
}
