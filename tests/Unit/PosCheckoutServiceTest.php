<?php

namespace Tests\Unit;

use App\Models\User;
use App\Modules\Akunting\Models\AkunCOA;
use App\Modules\Pos\Models\Transaksi;
use App\Modules\Pos\Services\PosCheckoutService;
use App\Modules\Rbac\Models\Cabang;
use App\Modules\Wms\Models\Gudang;
use App\Modules\Wms\Models\Produk;
use App\Modules\Wms\Models\StokItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PosCheckoutServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_checkout_creates_transaction_items_and_deducts_stock(): void
    {
        $user = User::factory()->create();
        $cabang = Cabang::create(['kode' => 'CB1', 'nama' => 'Cabang Test 1', 'is_active' => true]);
        $gudang = Gudang::create(['kode' => 'GD1', 'nama' => 'Gudang Utama', 'cabang_id' => $cabang->id, 'is_active' => true]);

        AkunCOA::firstOrCreate(['kode' => '110-04'], ['nama' => 'Kas Laci', 'tipe' => 'aset', 'kelompok' => 'kas', 'saldo_normal' => 'debit']);
        AkunCOA::firstOrCreate(['kode' => '410-01'], ['nama' => 'Pendapatan Penjualan', 'tipe' => 'pendapatan', 'kelompok' => 'pendapatan_penjualan', 'saldo_normal' => 'kredit']);
        AkunCOA::firstOrCreate(['kode' => '510-02'], ['nama' => 'HPP Penjualan', 'tipe' => 'beban', 'kelompok' => 'hpp', 'saldo_normal' => 'debit']);
        AkunCOA::firstOrCreate(['kode' => '130-01'], ['nama' => 'Persediaan Barang', 'tipe' => 'aset', 'kelompok' => 'persediaan', 'saldo_normal' => 'debit']);

        $produk = Produk::create([
            'kode' => 'PRD-01',
            'nama' => 'Layar LCD',
            'harga_beli' => 50000,
            'harga_jual' => 100000,
            'satuan' => 'pcs',
            'is_aktif' => true,
        ]);

        StokItem::create([
            'produk_id' => $produk->id,
            'gudang_id' => $gudang->id,
            'jumlah' => 10,
        ]);

        $service = app(PosCheckoutService::class);

        $transaksi = $service->checkout([
            'cabang_id' => $cabang->id,
            'kasir_id' => $user->id,
            'gudang_id' => $gudang->id,
            'subtotal' => 100000,
            'dpp' => 100000,
            'pajak_nominal' => 0,
            'total_akhir' => 100000,
            'metode_bayar' => 'tunai',
            'jumlah_bayar' => 100000,
            'kembalian' => 0,
        ], [
            [
                'produk_id' => $produk->id,
                'jumlah' => 2,
                'harga_satuan' => 50000,
                'subtotal' => 100000,
            ],
        ]);

        $this->assertInstanceOf(Transaksi::class, $transaksi);
        $this->assertEquals('selesai', $transaksi->status);
        $this->assertEquals(100000, $transaksi->total_akhir);
        $this->assertCount(1, $transaksi->items);

        $sisaStok = (int) StokItem::where('produk_id', $produk->id)->where('gudang_id', $gudang->id)->value('jumlah');
        $this->assertEquals(8, $sisaStok);
    }
}
