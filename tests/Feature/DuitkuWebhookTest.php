<?php

namespace Tests\Feature;

use App\Modules\Akunting\Models\AkunCOA;
use App\Modules\Akunting\Models\JurnalAkuntansi;
use App\Modules\Crm\Models\Pelanggan;
use App\Modules\Pos\Models\Transaksi;
use App\Modules\Pos\Models\TransaksiItem;
use App\Modules\Rbac\Models\Cabang;
use App\Modules\Wms\Models\Gudang;
use App\Modules\Wms\Models\Produk;
use App\Modules\Wms\Models\StockMutationLog;
use App\Modules\Wms\Models\StokItem;
use App\Modules\Wms\Models\StokLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Uji alur kritis pembayaran marketplace (PRD §6 ekst): webhook Duitku
 * signature verification + idempotency — duplikat callback TIDAK boleh
 * kurangi stok 2x / buat jurnal dobel.
 *
 * [B-10b/P0-5] Menambah: race-safety (baris order di-lockForUpdate di dalam
 * DB::transaction) + validasi nominal callback vs total_akhir.
 */
class DuitkuWebhookTest extends TestCase
{
    use RefreshDatabase;

    /** Id produk fixture (untuk assert stok/mutasi). */
    private int $produkId;

    private function setUpData(): Transaksi
    {
        $this->seedDuitkuConfig();

        $cabang = Cabang::create(['nama' => 'CBG Test', 'kode' => 'CBG-T', 'is_active' => true]);
        $gudang = Gudang::create(['cabang_id' => $cabang->id, 'nama' => 'GDG Test', 'kode' => 'GDG-T', 'is_active' => true]);

        $produk = Produk::create([
            'nama' => 'LCD Test', 'slug' => 'lcd-test', 'kategori' => 'LCD',
            'kondisi' => 'baru', 'harga_beli' => 50000, 'harga_jual_retail' => 100000,
        ]);
        $this->produkId = $produk->id;

        StokItem::create([
            'produk_id' => $produk->id, 'gudang_id' => $gudang->id,
            'jumlah' => 10, 'jumlah_minimum' => 1,
        ]);

        $customer = Pelanggan::create([
            'nama' => 'Test Buyer', 'telepon' => '081212341234',
            'is_reseller' => false,
        ]);

        $transaksi = Transaksi::create([
            'no_transaksi' => 'MP-TEST-0001',
            'cabang_id' => $cabang->id,
            'pelanggan_id' => $customer->id,
            'sumber' => 'marketplace',
            'subtotal' => 100000,
            'total_akhir' => 100000,
            'metode_bayar' => 'menunggu',
            'status' => 'menunggu_pembayaran',
        ]);

        TransaksiItem::create([
            'transaksi_id' => $transaksi->id,
            'produk_id' => $produk->id,
            'jumlah' => 1,
            'harga_satuan' => 100000,
            'subtotal' => 100000,
            'hpp' => 50000,
        ]);

        return $transaksi;
    }

    private function seedDuitkuConfig(): void
    {
        config([
            'duitku.merchant_code' => 'TST001',
            'duitku.api_key' => 'testapikey123',
            'duitku.merchant_key' => 'testkey456',
            'duitku.sandbox' => true,
        ]);

        // COA minimal
        AkunCOA::create(['kode' => '110-01', 'nama' => 'Kas', 'tipe' => 'aset', 'kelompok' => 'kas', 'saldo_normal' => 'debit']);
        AkunCOA::create(['kode' => '410-01', 'nama' => 'Pendapatan', 'tipe' => 'pendapatan', 'kelompok' => 'pendapatan_penjualan', 'saldo_normal' => 'kredit']);
        AkunCOA::create(['kode' => '510-02', 'nama' => 'HPP', 'tipe' => 'beban', 'kelompok' => 'hpp', 'saldo_normal' => 'debit']);
        AkunCOA::create(['kode' => '130-01', 'nama' => 'Persediaan', 'tipe' => 'aset', 'kelompok' => 'persediaan', 'saldo_normal' => 'debit']);
    }

    private function callbackPayload(string $orderId, string $resultCode): array
    {
        $merchantCode = 'TST001';
        $amount = 100000;
        $signature = md5($merchantCode.$amount.$orderId.'testapikey123');

        return [
            'merchantCode' => $merchantCode,
            'amount' => $amount,
            'merchantOrderId' => $orderId,
            'resultCode' => $resultCode,
            'reference' => 'REF-'.$orderId,
            'signature' => $signature,
        ];
    }

    public function test_callback_signature_invalid_ditolak(): void
    {
        $this->setUpData();

        $payload = $this->callbackPayload('MP-TEST-0001', '00');
        $payload['signature'] = 'invalid-signature';

        $this->post('/webhook/duitku', $payload)
            ->assertStatus(400)
            ->assertJson(['success' => false]);
    }

    public function test_callback_lunas_proses_stok_jurnal_dan_status(): void
    {
        $this->setUpData();

        $this->post('/webhook/duitku', $this->callbackPayload('MP-TEST-0001', '00'))
            ->assertOk()
            ->assertJson(['success' => true]);

        // Status lunas + paid_at terisi
        $this->assertDatabaseHas('transaksi', [
            'no_transaksi' => 'MP-TEST-0001',
            'status' => 'lunas',
        ]);

        // Stok berkurang 10 → 9
        $this->assertDatabaseHas('stok_items', [
            'jumlah' => 9,
        ]);

        // Jurnal dibuat (debit Kas = kredit Pendapatan)
        $this->assertDatabaseHas('jurnal_akuntansi', [
            'referensi_tipe' => Transaksi::class,
            'akun_coa_id' => AkunCOA::where('kode', '110-01')->first()->id,
            'debit' => 100000,
        ]);

        // Stok log tercatat
        $this->assertDatabaseHas('stok_log', [
            'jenis' => 'penjualan',
            'perubahan' => -1,
        ]);
    }

    public function test_callback_duplikat_idempotent_tidak_dobel_kurangi_stok(): void
    {
        $this->setUpData();

        // Callback pertama
        $this->post('/webhook/duitku', $this->callbackPayload('MP-TEST-0001', '00'))->assertOk();

        // Callback duplikat — harus ditolak pemrosesan (skip), jurnal tetap 1x
        $this->post('/webhook/duitku', $this->callbackPayload('MP-TEST-0001', '00'))
            ->assertOk()
            ->assertJson(['success' => true, 'message' => 'Already processed']);

        // Stok TIDAK berkurang dua kali (tetap 9)
        $this->assertDatabaseHas('stok_items', ['jumlah' => 9]);

        // Jurnal tetap 1 set lengkap (Kas + Pendapatan + HPP + Persediaan = 4 baris), bukan dobel (8)
        $this->assertDatabaseCount('jurnal_akuntansi', 4);
    }

    public function test_callback_gagal_update_status_gagal(): void
    {
        $this->setUpData();

        $this->post('/webhook/duitku', $this->callbackPayload('MP-TEST-0001', '01'))
            ->assertOk();

        $this->assertDatabaseHas('transaksi', [
            'no_transaksi' => 'MP-TEST-0001',
            'status' => 'gagal',
        ]);

        // Stok tidak berubah (tetap 10)
        $this->assertDatabaseHas('stok_items', ['jumlah' => 10]);
    }

    // ===== [B-10b/P0-5] Race safety + validasi nominal =====

    /**
     * Dua webhook "ber bersamaan" (simulasi sequential pada kode yang sama):
     * keduanya membaca order yang sama; hanya request PERTAMA boleh memproses.
     * Hasil akhir harus identik dgn satu callback tunggal: 1 set jurnal (4 baris)
     * & stok berkurang SEKALI (10 → 9).
     */
    public function test_dua_webhook_berbamaan_hanya_satu_jurnal_dan_stok_kurang_sekali(): void
    {
        $this->setUpData();

        $payload = $this->callbackPayload('MP-TEST-0001', '00');

        // Webhook A memproses (lunas)
        $this->post('/webhook/duitku', $payload)
            ->assertOk()
            ->assertJson(['success' => true, 'message' => 'OK']);

        // Webhook B "menunggu lock" → setelah A commit, ia baca status 'lunas'
        $this->post('/webhook/duitku', $payload)
            ->assertOk()
            ->assertJson(['success' => true, 'message' => 'Already processed']);

        // Webhook C (retry ketiga) → tetap no-op
        $this->post('/webhook/duitku', $payload)
            ->assertOk()
            ->assertJson(['success' => true, 'message' => 'Already processed']);

        // Stok berkurang SATU KALI: 10 → 9 (bukan 8)
        $this->assertSame(9, (int) StokItem::where('produk_id', $this->produkId)->value('jumlah'));

        // Jurnal: tepat 1 set (Kas + Pendapatan + HPP + Persediaan = 4 baris)
        $this->assertSame(4, JurnalAkuntansi::count(), 'Tidak boleh ada jurnal kedua dari webhook paralel');

        // StokLog & StockMutationLog: satu mutasi per callback sukses
        $this->assertSame(1, StokLog::where('referensi_tipe', Transaksi::class)->count());
        $this->assertSame(1, StockMutationLog::where('sumber', 'penjualan')->count());

        // paid_at tidak berubah (tidak ada proses kedua)
        $transaksi = Transaksi::firstOrFail();
        $this->assertEquals('lunas', $transaksi->status);
        $this->assertEquals(100000, (float) $transaksi->jumlah_bayar);
        $this->assertEquals('duitku', $transaksi->metode_bayar);
    }

    /** Nominal callback tidak cocok dgn total_akhir → ditolak, TIDAK ada efek samping. */
    public function test_nominal_tidak_cocok_ditolak_dengan_pesan_indonesia_tanpa_efek_samping(): void
    {
        $transaksi = $this->setUpData();

        $payload = $this->callbackPayload('MP-TEST-0001', '00');
        $payload['amount'] = 120000; // total_akhir = 100.000
        $payload['signature'] = md5('TST001'.$payload['amount'].$payload['merchantOrderId'].'testapikey123');

        $response = $this->post('/webhook/duitku', $payload)->assertOk();
        $response->assertJson(['success' => false]);
        $this->assertStringContainsString(
            'Nominal pembayaran tidak sesuai dengan total transaksi',
            $response->json('message')
        );

        // TIDAK diproses: status tetap menunggu, stok utuh, tanpa jurnal
        $this->assertSame('menunggu_pembayaran', $transaksi->fresh()->status);
        $this->assertSame(10, (int) StokItem::where('produk_id', $this->produkId)->value('jumlah'));
        $this->assertSame(0, JurnalAkuntansi::count());
        $this->assertSame(0, StokLog::count());
        $this->assertSame(0, StockMutationLog::count());
    }

    /** Toleransi pembulatan 1 rupiah tetap dianggap cocok. */
    public function test_nominal_selisih_satu_rupiah_masih_diproses(): void
    {
        $this->setUpData();

        $payload = $this->callbackPayload('MP-TEST-0001', '00');
        $payload['amount'] = 100001; // toleransi pembulatan
        $payload['signature'] = md5('TST001'.$payload['amount'].$payload['merchantOrderId'].'testapikey123');

        $this->post('/webhook/duitku', $payload)
            ->assertOk()
            ->assertJson(['success' => true, 'message' => 'OK']);

        $this->assertDatabaseHas('transaksi', ['no_transaksi' => 'MP-TEST-0001', 'status' => 'lunas']);
        $this->assertSame(4, JurnalAkuntansi::count());
    }

    /** Callback gagal yang TERLAMBAT tidak boleh menimpa order yang sudah lunas. */
    public function test_callback_gagal_terlambat_tidak_menimpa_order_lunas(): void
    {
        $this->setUpData();

        $this->post('/webhook/duitku', $this->callbackPayload('MP-TEST-0001', '00'))->assertOk();

        $this->post('/webhook/duitku', $this->callbackPayload('MP-TEST-0001', '01'))
            ->assertOk()
            ->assertJson(['success' => true, 'message' => 'Already processed']);

        $this->assertDatabaseHas('transaksi', ['no_transaksi' => 'MP-TEST-0001', 'status' => 'lunas']);
        $this->assertSame(4, JurnalAkuntansi::count());
    }
}
