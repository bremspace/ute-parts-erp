<?php

namespace Tests\Feature;

use App\Modules\Akunting\Models\AkunCOA;
use App\Modules\Akunting\Models\JurnalAkuntansi;
use App\Modules\Akunting\Services\PajakService;
use App\Modules\Crm\Models\Pelanggan;
use App\Modules\Marketplace\Services\OrderService;
use App\Modules\Pos\Models\Transaksi;
use App\Modules\Rbac\Models\Cabang;
use App\Modules\Wms\Models\Gudang;
use App\Modules\Wms\Models\Produk;
use App\Modules\Wms\Models\StokItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * B-14 — PPN marketplace harus sama dgn POS (sebelumnya journal order Duitku
 * membukukan SELURUH `total_akhir` ke pendapatan 410-01 tanpa memisah PPN ke
 * akun pajak 220-01 → laporan penjualan marketplace ≠ POS + laporan pajak F1-2
 * under-reported).
 *
 * Kontrak yang diuji (identik `PosController::store()`):
 *   DPP   = subtotal - diskon
 *   PPN   = DPP × persen
 *   Total = DPP + PPN   →  debit 110-01 = total, kredit 410-01 = DPP, kredit 220-01 = PPN
 *
 * Catatan: method wajib prefix test_ (PHPUnit 12 tidak deteksi @test docblock).
 */
class MarketplacePpnTest extends TestCase
{
    use RefreshDatabase;

    private Cabang $cabang;

    private Pelanggan $pelanggan;

    protected function setUp(): void
    {
        parent::setUp();

        // COA minimal utk jurnal marketplace + PPN
        AkunCOA::firstOrCreate(['kode' => '110-01'], ['nama' => 'Kas', 'tipe' => 'aset', 'kelompok' => 'kas', 'saldo_normal' => 'debit']);
        AkunCOA::firstOrCreate(['kode' => '410-01'], ['nama' => 'Pendapatan Penjualan', 'tipe' => 'pendapatan', 'kelompok' => 'pendapatan_penjualan', 'saldo_normal' => 'kredit']);
        AkunCOA::firstOrCreate(['kode' => '510-02'], ['nama' => 'HPP', 'tipe' => 'beban', 'kelompok' => 'hpp', 'saldo_normal' => 'debit']);
        AkunCOA::firstOrCreate(['kode' => '130-01'], ['nama' => 'Persediaan', 'tipe' => 'aset', 'kelompok' => 'persediaan', 'saldo_normal' => 'debit']);
        AkunCOA::firstOrCreate(['kode' => '220-01'], ['nama' => 'PPN Keluaran', 'tipe' => 'kewajiban', 'kelompok' => 'pajak', 'saldo_normal' => 'kredit']);

        $this->cabang = Cabang::create([
            'kode' => 'CBG-PPN-'.Str::random(3),
            'nama' => 'Cabang PPN',
            'is_active' => true,
        ]);

        $gudang = Gudang::create([
            'cabang_id' => $this->cabang->id,
            'kode' => 'GDG-PPN',
            'nama' => 'Gudang PPN',
            'is_active' => true,
        ]);

        $produk = Produk::create([
            'nama' => 'LCD PPN', 'slug' => 'lcd-ppn-'.Str::random(4), 'kategori' => 'LCD',
            'kondisi' => 'baru', 'harga_beli' => 400000, 'harga_jual_retail' => 1000000,
        ]);
        StokItem::create([
            'produk_id' => $produk->id, 'gudang_id' => $gudang->id,
            'jumlah' => 10, 'jumlah_minimum' => 1,
        ]);

        $this->pelanggan = Pelanggan::create([
            'nama' => 'Buyer PPN', 'telepon' => '081200000001', 'is_reseller' => false,
        ]);

        config([
            'duitku.merchant_code' => 'TST001',
            'duitku.api_key' => 'testapikey123',
            'duitku.merchant_key' => 'testkey456',
            'duitku.sandbox' => true,
        ]);
    }

    private function aktifkanPajak(float $persen = 11.0): void
    {
        app(PajakService::class)->set($this->cabang->id, true, $persen);
    }

    /** Order lewat OrderService (jalur produksi) — 1 item harga 1.000.000. */
    private function buatOrder(): Transaksi
    {
        $produkId = StokItem::query()->value('produk_id');

        return app(OrderService::class)->buatOrder(
            $this->pelanggan,
            [['produk_id' => $produkId, 'jumlah' => 1]],
            $this->cabang->id,
            'ambil_ke_toko'
        );
    }

    private function webhookLunas(Transaksi $transaksi)
    {
        $amount = (int) round((float) $transaksi->total_akhir);
        $signature = md5('TST001'.$amount.$transaksi->no_transaksi.'testapikey123');

        return $this->post('/webhook/duitku', [
            'merchantCode' => 'TST001',
            'amount' => $amount,
            'merchantOrderId' => $transaksi->no_transaksi,
            'resultCode' => '00',
            'reference' => 'REF-'.$transaksi->no_transaksi,
            'signature' => $signature,
        ]);
    }

    private function akunId(string $kode): int
    {
        return (int) AkunCOA::where('kode', $kode)->value('id');
    }

    // ===== ORDER: DPP / PPN / total saat checkout =====

    public function test_order_marketplace_pisah_dpp_ppn_saat_pajak_aktif(): void
    {
        $this->aktifkanPajak(11.0);

        $order = $this->buatOrder();

        $this->assertSame('marketplace', $order->sumber);
        $this->assertEquals(1000000, (float) $order->subtotal);
        $this->assertEquals(1000000, (float) $order->dpp);          // DPP = subtotal - diskon
        $this->assertEquals(110000, (float) $order->ppn_nominal);   // 11% × DPP
        $this->assertEquals(110000, (float) $order->pajak_nominal);
        $this->assertEquals(1110000, (float) $order->total_akhir);  // DPP + PPN
    }

    public function test_order_marketplace_tanpa_pajak_perilaku_tidak_berubah(): void
    {
        // pajak nonaktif (default) → PPN 0, total_akhir = subtotal (paritas lama)
        $order = $this->buatOrder();

        $this->assertEquals(0, (float) $order->ppn_nominal);
        $this->assertEquals(0, (float) $order->pajak_nominal);
        $this->assertEquals(1000000, (float) $order->dpp);
        $this->assertEquals(1000000, (float) $order->total_akhir);
    }

    public function test_preview_pajak_akan_sama_dengan_yang_ditagihkan(): void
    {
        $this->aktifkanPajak(10.0);

        $preview = app(OrderService::class)->previewPajak($this->cabang->id, 1000000);
        $order = $this->buatOrder();

        $this->assertEquals(100000.0, $preview['ppn_nominal']);
        $this->assertEquals($preview['total_akhir'], (float) $order->total_akhir);
        $this->assertTrue($preview['enabled']);
    }

    // ===== JURNAL WEBHOOK: PPN dipisah ke 220-01 =====

    public function test_jurnal_webhook_pisah_ppn_ke_220_01_saat_pajak_aktif(): void
    {
        $this->aktifkanPajak(11.0);
        $order = $this->buatOrder();

        $this->webhookLunas($order)->assertOk()->assertJson(['success' => true]);

        $jurnal = JurnalAkuntansi::all();
        // Kas + Pendapatan(DPP) + PPN(220-01) + HPP + Persediaan = 5 baris
        $this->assertCount(5, $jurnal);

        // Balance
        $this->assertEquals(
            round((float) $jurnal->sum('debit'), 2),
            round((float) $jurnal->sum('kredit'), 2)
        );

        // Nominal per akun
        $this->assertDatabaseHas('jurnal_akuntansi', [
            'akun_coa_id' => $this->akunId('110-01'), 'debit' => 1110000, 'kredit' => 0,
        ]);
        $this->assertDatabaseHas('jurnal_akuntansi', [
            'akun_coa_id' => $this->akunId('410-01'), 'debit' => 0, 'kredit' => 1000000,
        ]);
        $this->assertDatabaseHas('jurnal_akuntansi', [
            'akun_coa_id' => $this->akunId('220-01'), 'debit' => 0, 'kredit' => 110000,
        ]);
        $this->assertDatabaseHas('jurnal_akuntansi', [
            'akun_coa_id' => $this->akunId('510-02'), 'debit' => 400000, 'kredit' => 0,
        ]);
        $this->assertDatabaseHas('jurnal_akuntansi', [
            'akun_coa_id' => $this->akunId('130-01'), 'debit' => 0, 'kredit' => 400000,
        ]);
    }

    public function test_saldo_akun_ppn_220_01_mencerminkan_penjualan_marketplace(): void
    {
        $this->aktifkanPajak(11.0);
        $order = $this->buatOrder();

        $this->webhookLunas($order)->assertOk();

        // Saldo akun pajak = kredit PPN (normal kredit)
        $saldoPpn = (float) JurnalAkuntansi::where('akun_coa_id', $this->akunId('220-01'))
            ->selectRaw('COALESCE(SUM(kredit),0) - COALESCE(SUM(debit),0) as saldo')
            ->value('saldo');
        $this->assertEquals(110000, $saldoPpn);

        // Pendapatan (410-01) hanya DPP — BUKAN total incl. PPN
        $saldoPendapatan = (float) JurnalAkuntansi::where('akun_coa_id', $this->akunId('410-01'))
            ->selectRaw('COALESCE(SUM(kredit),0) - COALESCE(SUM(debit),0) as saldo')
            ->value('saldo');
        $this->assertEquals(1000000, $saldoPendapatan);
    }

    public function test_nominal_yang_dibayar_pelanggan_tidak_berubah_karena_ppn(): void
    {
        $this->aktifkanPajak(11.0);
        $order = $this->buatOrder();

        // Rekonsiliasi Duitku: total_akhir yang ditagihkan = yang dibayar & didebit Kas
        $this->webhookLunas($order)->assertOk();

        $this->assertDatabaseHas('transaksi', [
            'no_transaksi' => $order->no_transaksi,
            'status' => 'lunas',
            'jumlah_bayar' => 1110000,
        ]);
        $this->assertEquals(1110000, (float) $order->fresh()->jumlah_bayar);
    }

    // ===== PARITAS: pajak nonaktif =====

    public function test_jurnal_webhook_tanpa_ppn_saat_pajak_nonaktif(): void
    {
        // pajak TIDAK diaktifkan
        $order = $this->buatOrder();

        $this->webhookLunas($order)->assertOk()->assertJson(['success' => true]);

        $jurnal = JurnalAkuntansi::all();
        // Kas + Pendapatan + HPP + Persediaan = 4 baris (identik perilaku lama)
        $this->assertCount(4, $jurnal);
        $this->assertEquals(
            round((float) $jurnal->sum('debit'), 2),
            round((float) $jurnal->sum('kredit'), 2)
        );

        $this->assertDatabaseHas('jurnal_akuntansi', [
            'akun_coa_id' => $this->akunId('110-01'), 'debit' => 1000000, 'kredit' => 0,
        ]);
        $this->assertDatabaseHas('jurnal_akuntansi', [
            'akun_coa_id' => $this->akunId('410-01'), 'debit' => 0, 'kredit' => 1000000,
        ]);
        $this->assertDatabaseMissing('jurnal_akuntansi', [
            'akun_coa_id' => $this->akunId('220-01'),
        ]);
    }

    // ===== NON-RETROAKTIF =====

    public function test_order_lama_tanpa_ppn_tidak_diberi_ppn_retroaktif(): void
    {
        // Order dibuat saat pajak nonaktif…
        $order = $this->buatOrder();
        $this->assertEquals(1000000, (float) $order->total_akhir);

        // …lalu pajak diaktifkan sebelum webhook → order lama TIDAK berubah
        $this->aktifkanPajak(11.0);

        $this->webhookLunas($order)->assertOk();

        $this->assertDatabaseMissing('jurnal_akuntansi', [
            'akun_coa_id' => $this->akunId('220-01'),
        ]);
        $this->assertDatabaseHas('jurnal_akuntansi', [
            'akun_coa_id' => $this->akunId('410-01'), 'debit' => 0, 'kredit' => 1000000,
        ]);
        $jurnal = JurnalAkuntansi::all();
        $this->assertEquals(
            round((float) $jurnal->sum('debit'), 2),
            round((float) $jurnal->sum('kredit'), 2)
        );
    }

    // ===== IDEMPOTENSI (B-10b) tetap berlaku dgn PPN =====

    public function test_webhook_duplikat_tidak_membuat_jurnal_ppn_kedua(): void
    {
        $this->aktifkanPajak(11.0);
        $order = $this->buatOrder();

        $this->webhookLunas($order)->assertOk()->assertJson(['message' => 'OK']);
        $this->webhookLunas($order)
            ->assertOk()
            ->assertJson(['success' => true, 'message' => 'Already processed']);

        $this->assertSame(5, JurnalAkuntansi::count());
        $this->assertSame(1, JurnalAkuntansi::where('akun_coa_id', $this->akunId('220-01'))->count());
    }

    // ===== PERCENT PER-CABANG (tak dikunci ke 11) =====

    public function test_ppn_mengikuti_persen_per_cabang(): void
    {
        $this->aktifkanPajak(5.0);
        $order = $this->buatOrder();

        $this->assertEquals(50000, (float) $order->ppn_nominal);
        $this->assertEquals(1050000, (float) $order->total_akhir);

        $this->webhookLunas($order)->assertOk();

        $this->assertDatabaseHas('jurnal_akuntansi', [
            'akun_coa_id' => $this->akunId('220-01'), 'debit' => 0, 'kredit' => 50000,
        ]);
        $jurnal = JurnalAkuntansi::all();
        $this->assertEquals(
            round((float) $jurnal->sum('debit'), 2),
            round((float) $jurnal->sum('kredit'), 2)
        );
    }
}
