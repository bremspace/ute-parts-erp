<?php

namespace Tests\Feature;

use App\Modules\Akunting\Models\AkunCOA;
use App\Modules\Crm\Models\Pelanggan;
use App\Modules\Pos\Models\Transaksi;
use App\Modules\Rbac\Models\Cabang;
use App\Modules\Wms\Models\Gudang;
use App\Modules\Wms\Models\Produk;
use App\Modules\Wms\Models\StockMutationLog;
use App\Modules\Wms\Models\StokItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * B-10f / P1-7 — `transaksi.paid_at` hilang diam-diam.
 *
 * Temuan audit: kolom `paid_at` (dan `payment_reference`, `payment_url`) SUDAH
 * ADA di tabel `transaksi` — dibuat migrasi 2026_09_16_000027
 * (create_payment_shipping_tables). TIDAK ada tabel payment terpisah.
 * Penyebab nilainya hilang: model `Transaksi` tidak menaruh ketiga kolom itu di
 * `#[Fillable]`, sehingga Eloquent membuangnya diam-diam saat
 * PaymentController (webhook Duitku) menulis `paid_at`.
 *
 * Keputusan: `transaksi.paid_at` tetap sumber kebenaran (tidak ada duplikasi
 * ke tabel lain) — yang diperbaiki adalah penulisan mati di model, plus
 *sezero laporan yang bergantung padanya (grep: tidak ada reader lain).
 */
class TransaksiPaidAtTest extends TestCase
{
    use RefreshDatabase;

    private Transaksi $transaksi;

    protected function setUp(): void
    {
        parent::setUp();

        $cabang = Cabang::create(['nama' => 'CBG Paid', 'kode' => 'CBG-PAY', 'is_active' => true]);
        $gudang = Gudang::create([
            'cabang_id' => $cabang->id, 'nama' => 'GDG Paid', 'kode' => 'GDG-PAY', 'is_active' => true,
        ]);
        $produk = Produk::create([
            'nama' => 'Produk Paid', 'slug' => 'produk-paid', 'kategori' => 'Umum',
            'kondisi' => 'baru', 'harga_beli' => 50000, 'harga_jual_retail' => 100000,
        ]);
        StokItem::create([
            'produk_id' => $produk->id, 'gudang_id' => $gudang->id,
            'jumlah' => 10, 'jumlah_minimum' => 1,
        ]);
        $pelanggan = Pelanggan::create(['nama' => 'Pembeli', 'telepon' => '081200000001']);

        $this->transaksi = Transaksi::create([
            'no_transaksi' => 'PAY-B10F-0001',
            'cabang_id' => $cabang->id,
            'pelanggan_id' => $pelanggan->id,
            'sumber' => 'marketplace',
            'subtotal' => 100000,
            'total_akhir' => 100000,
            'metode_bayar' => 'duitku',
            'status' => 'lunas',
            'paid_at' => now(),
        ]);
    }

    public function test_kolom_paid_at_ada_di_tabel_transaksi(): void
    {
        $this->assertTrue(
            Schema::hasColumn('transaksi', 'paid_at'),
            'Migrasi 2026_09_16_000027 harusnya sudah menambah paid_at'
        );
        $this->assertTrue(Schema::hasColumn('transaksi', 'payment_reference'));
        $this->assertTrue(Schema::hasColumn('transaksi', 'payment_url'));
    }

    public function test_paid_at_tersimpan_saat_create(): void
    {
        $this->assertNotNull(
            $this->transaksi->fresh()->paid_at,
            'paid_at tidak boleh dibuang Eloquent (harus masuk #[Fillable])'
        );
        $this->assertDatabaseHas('transaksi', [
            'no_transaksi' => 'PAY-B10F-0001',
            'status' => 'lunas',
        ]);
        $this->assertNotNull(
            DB::table('transaksi')->where('no_transaksi', 'PAY-B10F-0001')->value('paid_at'),
            'paid_at harus benar-benar tertulis di DB'
        );
    }

    public function test_paid_at_tersimpan_saat_update(): void
    {
        $waktu = now()->subMinutes(3);

        $this->transaksi->update([
            'status' => 'gagal',
            'paid_at' => null,
        ]);
        $this->assertNull($this->transaksi->fresh()->paid_at, 'paid_at harus bisa dikosongkan (callback gagal)');

        $this->transaksi->update(['paid_at' => $waktu]);
        $this->assertSame(
            $waktu->toDateTimeString(),
            $this->transaksi->fresh()->paid_at->toDateTimeString(),
            'Update paid_at harus tercermin (webhook Duitku menulis ulang paid_at)'
        );
    }

    public function test_payment_reference_dan_url_tidak_terbuang(): void
    {
        // Akar masalah yang sama: ketiga kolom payment tidak ada di fillable
        $this->transaksi->update([
            'payment_reference' => 'DUITKU-REF-123',
            'payment_url' => 'https://checkout.duitku.test/abc',
        ]);

        $fresh = $this->transaksi->fresh();
        $this->assertSame('DUITKU-REF-123', $fresh->payment_reference);
        $this->assertSame('https://checkout.duitku.test/abc', $fresh->payment_url);
    }

    public function test_webhook_duitku_menulis_paid_at(): void
    {
        config([
            'duitku.merchant_code' => 'TST001',
            'duitku.api_key' => 'testapikey123',
            'duitku.merchant_key' => 'testkey456',
            'duitku.sandbox' => true,
        ]);
        AkunCOA::create(['kode' => '110-01', 'nama' => 'Kas', 'tipe' => 'aset', 'kelompok' => 'kas', 'saldo_normal' => 'debit']);
        AkunCOA::create(['kode' => '410-01', 'nama' => 'Pendapatan', 'tipe' => 'pendapatan', 'kelompok' => 'pendapatan_penjualan', 'saldo_normal' => 'kredit']);
        AkunCOA::create(['kode' => '130-01', 'nama' => 'Persediaan', 'tipe' => 'aset', 'kelompok' => 'persediaan', 'saldo_normal' => 'debit']);

        // Order menunggu pembayaran
        $this->transaksi->update(['status' => 'menunggu_pembayaran', 'paid_at' => null, 'jumlah_bayar' => 0]);

        $orderId = 'PAY-B10F-0001';
        $amount = 100000;
        $payload = [
            'merchantCode' => 'TST001',
            'amount' => $amount,
            'merchantOrderId' => $orderId,
            'resultCode' => '00',
            'reference' => 'REF-1',
            'signature' => md5('TST001'.$amount.$orderId.'testapikey123'),
        ];

        $this->post('/webhook/duitku', $payload)->assertOk()->assertJson(['success' => true, 'message' => 'OK']);

        $fresh = $this->transaksi->fresh();
        $this->assertSame('lunas', $fresh->status);
        $this->assertNotNull($fresh->paid_at, 'Webhook harus mengisi paid_at di DB');
        $this->assertSame(0, StockMutationLog::count(), 'Order tanpa item → tidak ada mutasi stok');
    }
}
