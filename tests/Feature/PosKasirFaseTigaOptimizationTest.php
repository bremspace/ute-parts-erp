<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Akunting\Models\AkunCOA;
use App\Modules\Crm\Models\Pelanggan;
use App\Modules\Pos\Livewire\PosKasir;
use App\Modules\Pos\Models\Transaksi;
use App\Modules\Pos\Services\PosCheckoutService;
use App\Modules\Rbac\Models\AktivitasLog;
use App\Modules\Rbac\Models\Cabang;
use App\Modules\Wms\Models\Gudang;
use App\Modules\Wms\Models\KategoriProduk;
use App\Modules\Wms\Models\Produk;
use App\Modules\Wms\Models\StokItem;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class PosKasirFaseTigaOptimizationTest extends TestCase
{
    use RefreshDatabase;

    protected Cabang $cabangA;

    protected Cabang $cabangB;

    protected Gudang $gudangA;

    protected Gudang $gudangB;

    protected User $user;

    protected Pelanggan $pelanggan;

    protected Produk $produk;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cabangA = Cabang::create(['nama' => 'Cabang Jakarta', 'kode' => 'JKT', 'is_active' => true]);
        $this->cabangB = Cabang::create(['nama' => 'Cabang Surabaya', 'kode' => 'SBY', 'is_active' => true]);

        $this->gudangA = Gudang::create(['cabang_id' => $this->cabangA->id, 'nama' => 'Gudang JKT', 'kode' => 'GD-JKT', 'is_active' => true]);
        $this->gudangB = Gudang::create(['cabang_id' => $this->cabangB->id, 'nama' => 'Gudang SBY', 'kode' => 'GD-SBY', 'is_active' => true]);

        $this->user = User::factory()->create();
        $this->user->cabangs()->attach($this->cabangA->id, ['is_default' => true]);
        $this->actingAs($this->user);

        $kategori = KategoriProduk::create(['nama' => 'Sparepart', 'kode' => 'SPP']);
        $this->produk = Produk::create([
            'kategori_id' => $kategori->id,
            'kode' => 'PRD-001',
            'nama' => 'Busi Iridium',
            'harga_beli' => 25000,
            'harga_jual_retail' => 50000,
            'is_active' => true,
        ]);

        StokItem::create(['produk_id' => $this->produk->id, 'gudang_id' => $this->gudangA->id, 'jumlah' => 50]);
        StokItem::create(['produk_id' => $this->produk->id, 'gudang_id' => $this->gudangB->id, 'jumlah' => 50]);

        $this->pelanggan = Pelanggan::create([
            'cabang_id' => $this->cabangA->id,
            'nama' => 'Budi Santoso',
            'telepon' => '08123456789',
            'max_piutang' => 500000,
            'saldo_piutang' => 0,
        ]);

        // Akun COA dasar
        AkunCOA::firstOrCreate(['kode' => '110-01'], ['nama' => 'Kas Toko', 'tipe' => 'aset', 'kelompok' => 'kas', 'saldo_normal' => 'debit']);
        AkunCOA::firstOrCreate(['kode' => '110-04'], ['nama' => 'Piutang Usaha', 'tipe' => 'aset', 'kelompok' => 'piutang', 'saldo_normal' => 'debit']);
        AkunCOA::firstOrCreate(['kode' => '410-01'], ['nama' => 'Pendapatan Penjualan', 'tipe' => 'pendapatan', 'kelompok' => 'pendapatan_penjualan', 'saldo_normal' => 'kredit']);
        AkunCOA::firstOrCreate(['kode' => '510-02'], ['nama' => 'HPP', 'tipe' => 'beban', 'kelompok' => 'hpp', 'saldo_normal' => 'debit']);
        AkunCOA::firstOrCreate(['kode' => '130-01'], ['nama' => 'Persediaan', 'tipe' => 'aset', 'kelompok' => 'persediaan', 'saldo_normal' => 'debit']);
    }

    /**
     * T-15: Pastikan audit log aktivitas transaksi tercatat tepat 1 kali (tidak duplikat).
     */
    public function test_t15_audit_log_transaksi_kasir_tidak_duplikat(): void
    {
        DB::table('kas_sesi')->insert([
            'cabang_id' => $this->cabangA->id,
            'user_id' => $this->user->id,
            'saldo_awal' => 100000,
            'dibuka_at' => now(),
            'status' => 'buka',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        session(['cabang_id' => $this->cabangA->id]);

        Livewire::test(PosKasir::class)
            ->set('selectedGudangId', $this->gudangA->id)
            ->call('addToCart', $this->produk->id)
            ->set('metodeBayar', 'tunai')
            ->set('jumlahBayar', 50000)
            ->call('processTransaction');

        $transaksi = Transaksi::latest('id')->first();
        $this->assertNotNull($transaksi);

        $activityCount = AktivitasLog::where('subject_type', $transaksi->getMorphClass())
            ->where('subject_id', $transaksi->id)
            ->where('event', 'penjualan')
            ->count();

        $this->assertEquals(1, $activityCount, 'Audit log transaksi penjualan harus tercatat tepat 1 kali.');
    }

    /**
     * T-16: Pastikan data harga_beli (HPP) tidak bocor di dalam array $cart publik Livewire.
     */
    public function test_t16_harga_beli_tidak_ada_di_public_cart(): void
    {
        session(['cabang_id' => $this->cabangA->id]);

        $test = Livewire::test(PosKasir::class)
            ->call('addToCart', $this->produk->id);

        $cart = $test->get('cart');
        $this->assertNotEmpty($cart);

        foreach ($cart as $key => $item) {
            $this->assertArrayNotHasKey('harga_beli', $item, "Item keranjang '{$key}' tidak boleh mengekspos field harga_beli.");
        }
    }

    /**
     * T-17: Scoping ketat where('cabang_id', ...) mencegah akses detail & hold lintas cabang.
     */
    public function test_t17_scoping_cabang_ketat_pada_detail_dan_hold_transactions(): void
    {
        // 1. Buat transaksi dan transaksi ditahan di Cabang B
        $trxCabangB = Transaksi::create([
            'no_transaksi' => 'TRX-C02-20261108-0001',
            'cabang_id' => $this->cabangB->id,
            'kasir_id' => $this->user->id,
            'sumber' => 'pos',
            'subtotal' => 50000,
            'total_akhir' => 50000,
            'metode_bayar' => 'tunai',
            'status' => 'selesai',
        ]);

        $trxHoldCabangB = Transaksi::create([
            'no_transaksi' => 'TRX-C02-20261108-T001',
            'cabang_id' => $this->cabangB->id,
            'kasir_id' => $this->user->id,
            'sumber' => 'pos',
            'subtotal' => 50000,
            'total_akhir' => 50000,
            'metode_bayar' => 'ditahan',
            'status' => 'ditahan',
            'split_detail' => ['cart' => [['produk_id' => $this->produk->id, 'qty' => 1, 'harga' => 50000]]],
        ]);

        // 2. Set sesi kasir di Cabang A
        session(['cabang_id' => $this->cabangA->id]);

        // Coba akses transaksi Cabang B dari PosKasir di Cabang A -> Harus ModelNotFoundException
        $this->expectException(ModelNotFoundException::class);
        $component = new PosKasir;
        $component->lihatDetailTransaksi($trxCabangB->id);
    }

    /**
     * T-17b: Transaksi ditahan milik cabang lain tidak boleh muncul di daftar getDitahanList.
     */
    public function test_t17b_hold_transactions_hanya_menampilkan_cabang_sendiri(): void
    {
        Transaksi::create([
            'no_transaksi' => 'TRX-C01-20261108-T001',
            'cabang_id' => $this->cabangA->id,
            'kasir_id' => $this->user->id,
            'sumber' => 'pos',
            'subtotal' => 50000,
            'total_akhir' => 50000,
            'metode_bayar' => 'ditahan',
            'status' => 'ditahan',
        ]);

        Transaksi::create([
            'no_transaksi' => 'TRX-C02-20261108-T002',
            'cabang_id' => $this->cabangB->id,
            'kasir_id' => $this->user->id,
            'sumber' => 'pos',
            'subtotal' => 75000,
            'total_akhir' => 75000,
            'metode_bayar' => 'ditahan',
            'status' => 'ditahan',
        ]);

        session(['cabang_id' => $this->cabangA->id]);

        Livewire::test(PosKasir::class)
            ->assertViewHas('ditahanList', function ($list) {
                // Hanya transaksi milik Cabang A yang boleh tampil
                return $list->count() === 1 && $list->first()->cabang_id === $this->cabangA->id;
            });
    }

    /**
     * T-18: Transaksi kasbon / piutang wajib memilih data pelanggan.
     */
    public function test_t18_transaksi_piutang_wajib_pilih_pelanggan(): void
    {
        // 1. Uji di level backend PosCheckoutService
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Transaksi kasbon/piutang wajib memilih data pelanggan.');

        app(PosCheckoutService::class)->checkout([
            'cabang_id' => $this->cabangA->id,
            'kasir_id' => $this->user->id,
            'pelanggan_id' => null, // Pelanggan kosong
            'sumber' => 'pos',
            'subtotal' => 50000,
            'dpp' => 50000,
            'total_akhir' => 50000,
            'metode_bayar' => 'piutang',
            'jumlah_bayar' => 0,
            'kembalian' => 0,
        ], [
            [
                'produk_id' => $this->produk->id,
                'jumlah' => 1,
                'harga_satuan' => 50000,
                'subtotal' => 50000,
            ],
        ]);
    }

    /**
     * T-18b: Livewire PosKasir menolak proses transaksi piutang tanpa memilih pelanggan.
     */
    public function test_t18b_livewire_pos_kasir_tolak_piutang_tanpa_pelanggan(): void
    {
        session(['cabang_id' => $this->cabangA->id]);

        $initialCount = Transaksi::count();

        $comp = Livewire::test(PosKasir::class)
            ->set('selectedGudangId', $this->gudangA->id)
            ->call('addToCart', $this->produk->id)
            ->set('metodeBayar', 'piutang')
            ->set('selectedCustomerId', null)
            ->call('processTransaction');

        $this->assertEquals($initialCount, Transaksi::count(), 'Transaksi tidak boleh dibuat jika pelanggan belum dipilih untuk piutang.');
    }
}
