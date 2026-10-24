<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Akunting\Models\AkunCOA;
use App\Modules\Crm\Models\Pelanggan;
use App\Modules\Marketplace\Services\DuitkuPaymentProcessor;
use App\Modules\Marketplace\Services\OrderService;
use App\Modules\Pos\Models\Transaksi;
use App\Modules\Rbac\Models\Cabang;
use App\Modules\Wms\Models\Gudang;
use App\Modules\Wms\Models\KategoriProduk;
use App\Modules\Wms\Models\Produk;
use App\Modules\Wms\Models\StokItem;
use App\Modules\Wms\Services\StockReservationService;
use App\Modules\Wms\Services\StokDeductionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class StockReservationSystemTest extends TestCase
{
    use RefreshDatabase;

    protected User $staff;

    protected Cabang $cabang;

    protected Gudang $gudang;

    protected Pelanggan $pelanggan;

    protected Produk $produk;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cabang = Cabang::create([
            'kode' => 'CBG-01',
            'nama' => 'Cabang Pusat',
            'alamat' => 'Jl. Pusat No. 1',
            'telepon' => '08123456789',
            'is_pusat' => true,
        ]);

        $this->gudang = Gudang::create([
            'cabang_id' => $this->cabang->id,
            'nama' => 'Gudang Utama',
            'kode' => 'GDG-01',
            'is_aktif' => true,
        ]);

        AkunCOA::firstOrCreate(['kode' => '110-01'], ['nama' => 'Kas Toko', 'tipe' => 'aset', 'kelompok' => 'kas', 'saldo_normal' => 'debit']);
        AkunCOA::firstOrCreate(['kode' => '110-04'], ['nama' => 'Kas Laci', 'tipe' => 'aset', 'kelompok' => 'kas', 'saldo_normal' => 'debit']);
        AkunCOA::firstOrCreate(['kode' => '410-01'], ['nama' => 'Pendapatan Penjualan', 'tipe' => 'pendapatan', 'kelompok' => 'pendapatan_penjualan', 'saldo_normal' => 'kredit']);
        AkunCOA::firstOrCreate(['kode' => '510-02'], ['nama' => 'HPP Suku Cadang', 'tipe' => 'beban', 'kelompok' => 'hpp', 'saldo_normal' => 'debit']);
        AkunCOA::firstOrCreate(['kode' => '130-01'], ['nama' => 'Persediaan Barang Dagang', 'tipe' => 'aset', 'kelompok' => 'persediaan', 'saldo_normal' => 'debit']);

        $perm1 = Permission::firstOrCreate(['name' => 'pos.view']);
        $role = Role::create(['name' => 'super-admin']);
        $role->givePermissionTo($perm1);

        $this->staff = User::factory()->create();
        $this->staff->assignRole($role);
        $this->staff->cabangs()->attach($this->cabang->id, ['is_default' => true]);

        session(['cabang_id' => $this->cabang->id]);

        $kat = KategoriProduk::create(['nama' => 'Sparepart Rem', 'kode' => 'REM']);

        $this->produk = Produk::create([
            'kode' => 'PRD-01',
            'nama' => 'Brake Pad Brembo',
            'sku' => 'SKU-BRM-01',
            'kategori_id' => $kat->id,
            'harga_beli' => 100000,
            'harga_jual' => 150000,
            'stok_minimum' => 5,
        ]);

        StokItem::create([
            'gudang_id' => $this->gudang->id,
            'produk_id' => $this->produk->id,
            'jumlah' => 10,
        ]);

        $this->pelanggan = Pelanggan::create([
            'nama' => 'Budi Customer',
            'email' => 'budi@example.com',
            'telepon' => '081299887766',
            'alamat' => 'Jl. Customer',
        ]);
    }

    public function test_order_creates_reservation_and_sets_expires_at(): void
    {
        $orderService = app(OrderService::class);
        $transaksi = $orderService->buatOrder(
            $this->pelanggan,
            [['produk_id' => $this->produk->id, 'jumlah' => 4]],
            $this->cabang->id,
            'pickup'
        );

        $this->assertNotNull($transaksi->expires_at);
        $this->assertTrue($transaksi->expires_at->isFuture());

        $reservationService = app(StockReservationService::class);
        $reserved = $reservationService->getActiveReservation($this->cabang->id, $this->produk->id);
        $this->assertEquals(4, $reserved);

        $available = $reservationService->getAvailableStockForOrder($this->cabang->id, $this->produk->id);
        $this->assertEquals(6, $available);
    }

    public function test_reservation_blocks_second_order_that_exceeds_available_stock(): void
    {
        $orderService = app(OrderService::class);

        // Order 1: ambil 7 dari 10
        $orderService->buatOrder(
            $this->pelanggan,
            [['produk_id' => $this->produk->id, 'jumlah' => 7]],
            $this->cabang->id,
            'pickup'
        );

        // Order 2: minta 4 (tersedia tinggal 3 bebas) -> harus ditolak
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage("Stok {$this->produk->nama} tidak mencukupi di cabang yang dipilih (tersedia: 3)");

        $orderService->buatOrder(
            $this->pelanggan,
            [['produk_id' => $this->produk->id, 'jumlah' => 4]],
            $this->cabang->id,
            'pickup'
        );
    }

    public function test_reservation_blocks_pos_sale_when_stock_insufficient(): void
    {
        $orderService = app(OrderService::class);

        // Reserve 8 dari 10
        $orderService->buatOrder(
            $this->pelanggan,
            [['produk_id' => $this->produk->id, 'jumlah' => 8]],
            $this->cabang->id,
            'pickup'
        );

        $this->actingAs($this->staff);

        // Kasir POS coba jual 3 (sisa bebas hanya 2) -> StokDeductionService tolak
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Stok tidak mencukupi');

        app(StokDeductionService::class)->kurangi(
            produkId: $this->produk->id,
            skuVariantId: null,
            gudangId: $this->gudang->id,
            qty: 3,
            jenis: 'penjualan',
            referensiTipe: Transaksi::class,
            referensiId: null,
            userId: $this->staff->id
        );
    }

    public function test_cancellation_releases_reservation(): void
    {
        $orderService = app(OrderService::class);
        $transaksi = $orderService->buatOrder(
            $this->pelanggan,
            [['produk_id' => $this->produk->id, 'jumlah' => 6]],
            $this->cabang->id,
            'pickup'
        );

        $reservationService = app(StockReservationService::class);
        $this->assertEquals(6, $reservationService->getActiveReservation($this->cabang->id, $this->produk->id));

        // Konsumen membatalkan pesanan
        $transaksi->update(['status' => 'batal']);

        $this->assertEquals(0, $reservationService->getActiveReservation($this->cabang->id, $this->produk->id));
        $this->assertEquals(10, $reservationService->getAvailableStockForOrder($this->cabang->id, $this->produk->id));
    }

    public function test_expired_order_releases_reservation(): void
    {
        $orderService = app(OrderService::class);
        $transaksi = $orderService->buatOrder(
            $this->pelanggan,
            [['produk_id' => $this->produk->id, 'jumlah' => 5]],
            $this->cabang->id,
            'pickup'
        );

        $reservationService = app(StockReservationService::class);
        $this->assertEquals(5, $reservationService->getActiveReservation($this->cabang->id, $this->produk->id));

        // Time travel: set expires_at ke masa lalu
        $transaksi->update(['expires_at' => now()->subMinutes(10)]);

        $this->assertEquals(0, $reservationService->getActiveReservation($this->cabang->id, $this->produk->id));
        $this->assertEquals(10, $reservationService->getAvailableStockForOrder($this->cabang->id, $this->produk->id));
    }

    public function test_payment_success_deducts_stock_and_releases_reservation(): void
    {
        $orderService = app(OrderService::class);
        $transaksi = $orderService->buatOrder(
            $this->pelanggan,
            [['produk_id' => $this->produk->id, 'jumlah' => 4]],
            $this->cabang->id,
            'pickup'
        );

        $reservationService = app(StockReservationService::class);
        $this->assertEquals(4, $reservationService->getActiveReservation($this->cabang->id, $this->produk->id));

        // Simulasi pembayaran lunas melalui processor Duitku
        $processor = app(DuitkuPaymentProcessor::class);
        $hasil = $processor->prosesLunas($transaksi->no_transaksi, (int) round((float) $transaksi->total_akhir), 'DUITKU-REF-123');

        $this->assertEquals('sukses', $hasil['kode']);
        $transaksi->refresh();
        $this->assertEquals('lunas', $transaksi->status);

        // Reservasi aktif harus 0 karena status lunas
        $this->assertEquals(0, $reservationService->getActiveReservation($this->cabang->id, $this->produk->id));

        // Stok fisik berkurang dari 10 jadi 6
        $stokAkhir = StokItem::where('gudang_id', $this->gudang->id)->where('produk_id', $this->produk->id)->value('jumlah');
        $this->assertEquals(6, $stokAkhir);

        // Stok tersedia bersih juga 6
        $available = $reservationService->getAvailableStockForOrder($this->cabang->id, $this->produk->id);
        $this->assertEquals(6, $available);
    }
}
