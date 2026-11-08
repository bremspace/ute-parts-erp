<?php

namespace Tests\Feature;

use App\Modules\Akunting\Models\AkunCOA;
use App\Modules\Akunting\Models\JurnalHeader;
use App\Modules\Crm\Models\Pelanggan;
use App\Modules\Marketplace\Services\DuitkuPaymentProcessor;
use App\Modules\Marketplace\Services\DuitkuService;
use App\Modules\Marketplace\Services\OrderService;
use App\Modules\Pos\Models\Transaksi;
use App\Modules\Pos\Services\TransaksiSequenceService;
use App\Modules\Rbac\Models\Cabang;
use App\Modules\Wms\Models\Gudang;
use App\Modules\Wms\Models\KategoriProduk;
use App\Modules\Wms\Models\Produk;
use App\Modules\Wms\Models\StokItem;
use App\Modules\Wms\Services\StokDeductionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class MarketplaceFaseDuaOptimizationTest extends TestCase
{
    use RefreshDatabase;

    protected Cabang $cabang;

    protected Gudang $gudang1;

    protected Gudang $gudang2;

    protected Pelanggan $pelanggan;

    protected Produk $produk;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'duitku.merchant_code' => 'DTEST01',
            'duitku.api_key' => 'apikeytest123',
            'duitku.merchant_key' => 'merchantkeytest456',
            'duitku.signature_mode' => 'dual',
        ]);

        $this->cabang = Cabang::create([
            'nama' => 'Cabang Test Marketplace',
            'kode' => 'CB-TEST',
            'is_active' => true,
        ]);

        $this->gudang1 = Gudang::create([
            'cabang_id' => $this->cabang->id,
            'nama' => 'Gudang Utama',
            'kode' => 'GD-01',
            'is_active' => true,
        ]);

        $this->gudang2 = Gudang::create([
            'cabang_id' => $this->cabang->id,
            'nama' => 'Gudang Cadangan',
            'kode' => 'GD-02',
            'is_active' => true,
        ]);

        $this->pelanggan = Pelanggan::create([
            'cabang_id' => $this->cabang->id,
            'nama' => 'Pembeli Online',
            'telepon' => '081234567890',
            'email' => 'pembeli@test.com',
            'alamat' => 'Jl. Merdeka No. 10',
        ]);

        $kategori = KategoriProduk::create(['nama' => 'Otomotif', 'kode' => 'OTO']);
        $this->produk = Produk::create([
            'kategori_id' => $kategori->id,
            'kode' => 'PRD-MP-01',
            'nama' => 'Kampas Rem Ori',
            'harga_beli' => 50000,
            'harga_jual_retail' => 100000,
            'is_active' => true,
        ]);

        StokItem::create([
            'produk_id' => $this->produk->id,
            'gudang_id' => $this->gudang1->id,
            'jumlah' => 20,
        ]);

        StokItem::create([
            'produk_id' => $this->produk->id,
            'gudang_id' => $this->gudang2->id,
            'jumlah' => 10,
        ]);

        // COA Setup
        AkunCOA::firstOrCreate(['kode' => '110-02'], ['nama' => 'Kas QRIS', 'tipe' => 'aset', 'kelompok' => 'kas', 'saldo_normal' => 'debit']);
        AkunCOA::firstOrCreate(['kode' => '120-02'], ['nama' => 'Kliring Duitku', 'tipe' => 'aset', 'kelompok' => 'kas', 'saldo_normal' => 'debit']);
        AkunCOA::firstOrCreate(['kode' => '410-03'], ['nama' => 'Pendapatan Marketplace', 'tipe' => 'pendapatan', 'kelompok' => 'pendapatan_penjualan', 'saldo_normal' => 'kredit']);
        AkunCOA::firstOrCreate(['kode' => '210-04'], ['nama' => 'Titipan Ongkir Pengiriman', 'tipe' => 'kewajiban', 'kelompok' => 'utang_lain', 'saldo_normal' => 'kredit']);
        AkunCOA::firstOrCreate(['kode' => '210-05'], ['nama' => 'Titipan Pembayaran Konsumen (Holding)', 'tipe' => 'kewajiban', 'kelompok' => 'utang_lain', 'saldo_normal' => 'kredit']);
        AkunCOA::firstOrCreate(['kode' => '220-01'], ['nama' => 'PPN Keluaran', 'tipe' => 'kewajiban', 'kelompok' => 'pajak', 'saldo_normal' => 'kredit']);
        AkunCOA::firstOrCreate(['kode' => '510-02'], ['nama' => 'HPP', 'tipe' => 'beban', 'kelompok' => 'hpp', 'saldo_normal' => 'debit']);
        AkunCOA::firstOrCreate(['kode' => '130-01'], ['nama' => 'Persediaan', 'tipe' => 'aset', 'kelompok' => 'persediaan', 'saldo_normal' => 'debit']);
    }

    /**
     * T-09: Sequence generator pesimistik menghasilkan prefix MP-Cxx-YYYYMMDD-xxxx.
     */
    public function test_t09_sequence_generator_pesimistik_prefix_mp(): void
    {
        $seq = app(TransaksiSequenceService::class)->generateNoTransaksi($this->cabang->id, 'MP');
        $today = now()->format('Ymd');
        $expectedPrefix = sprintf('MP-C%02d-%s-', $this->cabang->id, $today);

        $this->assertStringStartsWith($expectedPrefix, $seq);

        $order = app(OrderService::class)->buatOrder(
            $this->pelanggan,
            [['produk_id' => $this->produk->id, 'jumlah' => 1]],
            $this->cabang->id,
            'ambil_toko'
        );

        $this->assertStringStartsWith($expectedPrefix, $order->no_transaksi);
    }

    /**
     * T-10: Komponen ongkir masuk ke total_akhir dan dipisah ke akun 210-04 saat pelunasan Duitku.
     */
    public function test_t10_ongkir_masuk_total_akhir_dan_jurnal_titipan_ongkir(): void
    {
        $ongkirNominal = 25000.0;
        $order = app(OrderService::class)->buatOrder(
            $this->pelanggan,
            [['produk_id' => $this->produk->id, 'jumlah' => 1]],
            $this->cabang->id,
            'pengiriman',
            'Kirim segera',
            ['ongkir' => $ongkirNominal, 'kurir' => 'JNE', 'layanan' => 'REG']
        );

        $this->assertEquals(125000.0, (float) $order->total_akhir);
        $this->assertDatabaseHas('pengiriman', [
            'transaksi_id' => $order->id,
            'ongkir' => $ongkirNominal,
        ]);

        $processor = app(DuitkuPaymentProcessor::class);
        $hasil = $processor->prosesLunas($order->no_transaksi, 125000, 'REF-TEST-01', 'duitku');

        $this->assertEquals('sukses', $hasil['kode']);
        $order->refresh();
        $this->assertEquals('lunas', $order->status);

        // Verifikasi baris jurnal Titipan Ongkir (210-04) kredit Rp 25.000
        $jurnal = JurnalHeader::where('referensi_tipe', Transaksi::class)
            ->where('referensi_id', $order->id)
            ->with('lines.akun')
            ->first();

        $this->assertNotNull($jurnal);
        $lineOngkir = $jurnal->lines->first(fn ($l) => $l->akun?->kode === '210-04');
        $this->assertNotNull($lineOngkir, 'Baris jurnal akun 210-04 Titipan Ongkir harus terbentuk.');
        $this->assertEquals(25000.0, (float) $lineOngkir->kredit);
        $this->assertEquals(0.0, (float) $lineOngkir->debit);

        // Total debit Kas Duitku (120-02) harus sama dengan total_akhir (125.000)
        $lineKas = $jurnal->lines->first(fn ($l) => $l->akun?->kode === '120-02');
        $this->assertNotNull($lineKas);
        $this->assertEquals(125000.0, (float) $lineKas->debit);
    }

    /**
     * T-11: Validasi dynamic merchant code dan dual signature MD5 (apiKey dan merchantKey).
     */
    public function test_t11_dynamic_merchant_code_dan_dual_key_signature_md5(): void
    {
        $duitkuService = app(DuitkuService::class);
        $amount = 100000;
        $orderId = 'MP-TEST-ORD-01';

        // 1. Signature dengan apiKey
        $sigApiKey = md5('DTEST01'.$amount.$orderId.'apikeytest123');
        $this->assertTrue($duitkuService->verifyCallbackSignature($amount, $orderId, $sigApiKey));

        // 2. Signature dengan merchantKey
        $sigMerchantKey = md5('DTEST01'.$amount.$orderId.'merchantkeytest456');
        $this->assertTrue($duitkuService->verifyCallbackSignature($amount, $orderId, $sigMerchantKey));

        // 3. Signature salah
        $this->assertFalse($duitkuService->verifyCallbackSignature($amount, $orderId, 'invalidsignature'));

        // 4. Controller webhook dengan merchantKey
        $order = app(OrderService::class)->buatOrder(
            $this->pelanggan,
            [['produk_id' => $this->produk->id, 'jumlah' => 1]],
            $this->cabang->id,
            'ambil_toko'
        );

        $payload = [
            'merchantCode' => 'DTEST01',
            'amount' => 100000,
            'merchantOrderId' => $order->no_transaksi,
            'signature' => md5('DTEST01100000'.$order->no_transaksi.'merchantkeytest456'),
            'resultCode' => '00',
            'reference' => 'REF-KEY-DUAL',
        ];

        $response = $this->postJson('/webhook/duitku', $payload);
        $response->assertOk();
        $this->assertDatabaseHas('transaksi', ['id' => $order->id, 'status' => 'lunas']);
    }

    /**
     * T-12: Pembayaran order kadaluarsa masuk holding account 210-05 dan log peringatan staf.
     */
    public function test_t12_safety_holding_account_order_kadaluarsa(): void
    {
        $order = app(OrderService::class)->buatOrder(
            $this->pelanggan,
            [['produk_id' => $this->produk->id, 'jumlah' => 1]],
            $this->cabang->id,
            'ambil_toko'
        );

        $order->update(['status' => 'kadaluarsa']);

        $processor = app(DuitkuPaymentProcessor::class);
        $hasil = $processor->prosesLunas($order->no_transaksi, 100000, 'REF-EXPIRED-LATE', 'duitku');

        $this->assertEquals('order_kadaluarsa_holding', $hasil['kode']);

        // Transaksi tidak berubah jadi lunas (karena stok sudah dilepas), tapi catatan ditambahkan
        $order->refresh();
        $this->assertEquals('kadaluarsa', $order->status);
        $this->assertStringContainsString('PERLU TINDAKAN MANUAL', (string) $order->catatan);

        // Verifikasi terbentuknya jurnal holding 210-05
        $jurnal = JurnalHeader::where('referensi_tipe', Transaksi::class)
            ->where('referensi_id', $order->id)
            ->with('lines.akun')
            ->first();

        $this->assertNotNull($jurnal);
        $lineHolding = $jurnal->lines->first(fn ($l) => $l->akun?->kode === '210-05');
        $this->assertNotNull($lineHolding);
        $this->assertEquals(100000.0, (float) $lineHolding->kredit);
    }

    /**
     * T-13: checkStatus mengenali cache berdasarkan invoice reference spesifik "qris_payment_{no_transaksi}".
     */
    public function test_t13_check_status_dengan_invoice_reference_spesifik(): void
    {
        $order = app(OrderService::class)->buatOrder(
            $this->pelanggan,
            [['produk_id' => $this->produk->id, 'jumlah' => 1]],
            $this->cabang->id,
            'ambil_toko'
        );

        Cache::put("qris_payment_{$order->no_transaksi}", [
            'nominal' => 100000,
            'title' => 'QRIS Toko BCA',
            'text' => "QRIS Rp 100.000 untuk {$order->no_transaksi}",
        ], 300);

        $response = $this->postJson('/api/payment/check-status', [
            'no_transaksi' => $order->no_transaksi,
        ]);

        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'data' => [
                'is_lunas' => true,
                'status' => 'lunas',
            ],
        ]);

        $order->refresh();
        $this->assertEquals('lunas', $order->status);
        $this->assertNull(Cache::get("qris_payment_{$order->no_transaksi}"));
    }

    /**
     * T-14: Kalkulasi reservasi dihitung terhadap stok agregat cabang (multi-gudang),
     * bukan mengunci mentah stok individual tiap gudang secara berlebihan.
     */
    public function test_t14_kalkulasi_reservasi_multi_gudang_level_cabang(): void
    {
        // Kondisi awal stok:
        // gudang1: 20 unit
        // gudang2: 10 unit
        // Total cabang = 30 unit.
        // Buat pesanan marketplace sebanyak 15 unit (mereservasi 15 unit di cabang).
        // Sisa stok bebas cabang = 30 - 15 = 15 unit.

        $order = app(OrderService::class)->buatOrder(
            $this->pelanggan,
            [['produk_id' => $this->produk->id, 'jumlah' => 15]],
            $this->cabang->id,
            'ambil_toko'
        );

        $deductionService = app(StokDeductionService::class);

        // Kasir POS ingin menjual 10 unit dari Gudang 1:
        // Stok fisik gudang 1 = 20.
        // Sisa bebas cabang = 15.
        // Gudang 1 mampu melayani 10 unit (karena 10 <= min(20, 15)). Ini HARUS BERHASIL.
        $stokItem = $deductionService->kurangi(
            $this->produk->id,
            null,
            $this->gudang1->id,
            10,
            'penjualan',
            'TransaksiPos',
            99999,
            null
        );

        $this->assertEquals(10, $stokItem->jumlah);

        // Setelah deduksi 10 unit dari Gudang 1:
        // Total fisik cabang sekarang = 10 (Gudang 1) + 10 (Gudang 2) = 20 unit.
        // Reservasi online aktif = 15 unit.
        // Sisa bebas cabang tinggal 5 unit.
        // Jika kasir POS mencoba menjual 6 unit lagi dari Gudang 2 (fisik ada 10 tapi bebas cabang cuma 5):
        // HARUS throw exception.
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('sedang direservasi pesanan online di cabang');

        $deductionService->kurangi(
            $this->produk->id,
            null,
            $this->gudang2->id,
            6,
            'penjualan',
            'TransaksiPos',
            99998,
            null
        );
    }
}
