<?php

namespace Tests\Feature;

use App\Modules\Akunting\Models\AkunCOA;
use App\Modules\Crm\Models\Pelanggan;
use App\Modules\Pos\Models\Transaksi;
use App\Modules\Pos\Models\TransaksiItem;
use App\Modules\Rbac\Models\Cabang;
use App\Modules\Wms\Models\Gudang;
use App\Modules\Wms\Models\Produk;
use App\Modules\Wms\Models\StokItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Uji alur kritis pembayaran marketplace (PRD §6 ekst): webhook Duitku
 * signature verification + idempotency — duplikat callback TIDAK boleh
 * kurangi stok 2x / buat jurnal dobel.
 */
class DuitkuWebhookTest extends TestCase
{
    use RefreshDatabase;

    private function setUpData(): Transaksi
    {
        $this->seedDuitkuConfig();

        $cabang = Cabang::create(['nama' => 'CBG Test', 'kode' => 'CBG-T', 'is_active' => true]);
        $gudang = Gudang::create(['cabang_id' => $cabang->id, 'nama' => 'GDG Test', 'kode' => 'GDG-T', 'is_active' => true]);

        $produk = Produk::create([
            'nama' => 'LCD Test', 'slug' => 'lcd-test', 'kategori' => 'LCD',
            'kondisi' => 'baru', 'harga_beli' => 50000, 'harga_jual_retail' => 100000,
        ]);

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
        $signature = md5($merchantCode . $amount . $orderId . 'testapikey123');

        return [
            'merchantCode' => $merchantCode,
            'amount' => $amount,
            'merchantOrderId' => $orderId,
            'resultCode' => $resultCode,
            'reference' => 'REF-' . $orderId,
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
}