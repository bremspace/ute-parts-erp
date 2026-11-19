<?php

namespace Tests\Feature;

use App\Modules\Akunting\Models\AkunCOA;
use App\Modules\Akunting\Models\JurnalAkuntansi;
use App\Modules\Akunting\Models\JurnalHeader;
use App\Modules\Akunting\Services\PajakService;
use App\Modules\Crm\Models\Pelanggan;
use App\Modules\Marketplace\Models\Pengiriman;
use App\Modules\Marketplace\Services\BiteshipService;
use App\Modules\Marketplace\Services\DuitkuPaymentProcessor;
use App\Modules\Marketplace\Services\OrderService;
use App\Modules\Omnichannel\Models\Channel;
use App\Modules\Omnichannel\Models\ChannelProductMapping;
use App\Modules\Omnichannel\Services\ChannelSyncService;
use App\Modules\Pos\Models\Transaksi;
use App\Modules\Rbac\Models\Cabang;
use App\Modules\Wms\Models\Gudang;
use App\Modules\Wms\Models\KategoriProduk;
use App\Modules\Wms\Models\Produk;
use App\Modules\Wms\Models\StokItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * [H6][H7][H12] Fix HIGH siklus pembayaran online & channel:
 *  - H6: jurnal holding 210-05 idempoten per order — retry webhook order
 *    kadaluarsa tidak boleh posting holding dobel.
 *  - H7: settlement kliring 120-02/120-03/120-04 ke bank (Dr 110-02,
 *    selisih fee → 520-06) idempoten per referensi pencairan; ongkir
 *    210-04 dilepas saat kurir ditagih (Biteship) — Dr 210-04 / Cr 110-02,
 *    idempoten per pengiriman.
 *  - H12: jurnal channel fail-CLOSED (jurnal gagal → rollback stok+transaksi)
 *    dan pisah DPP/PPN/ongkir (pola DuitkuPaymentProcessor::pisahDppPpn /
 *    PajakService).
 */
class PaymentChannelHighFixTest extends TestCase
{
    use RefreshDatabase;

    protected Cabang $cabang;

    protected Gudang $gudang;

    protected Produk $produk;

    protected Pelanggan $pelanggan;

    protected Channel $channel;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cabang = Cabang::create([
            'kode' => 'CB-FH', 'nama' => 'Cabang FH', 'is_pusat' => true, 'is_active' => true,
        ]);
        $this->gudang = Gudang::create([
            'cabang_id' => $this->cabang->id, 'nama' => 'Gudang FH', 'kode' => 'GD-FH',
            'is_active' => true, 'is_default' => true,
        ]);
        session(['cabang_id' => $this->cabang->id]);

        $kat = KategoriProduk::create(['nama' => 'Kaki Kaki', 'kode' => 'KAKI-FH']);
        $this->produk = Produk::create([
            'kode' => 'PRD-FH', 'nama' => 'Tie Rod End', 'sku' => 'SKU-FH',
            'kategori_id' => $kat->id, 'harga_beli' => 100000,
            'harga_jual' => 150000, 'harga_jual_retail' => 150000,
            'satuan' => 'pcs', 'stok_minimum' => 5,
        ]);
        StokItem::create([
            'gudang_id' => $this->gudang->id, 'produk_id' => $this->produk->id, 'jumlah' => 100,
        ]);
        $this->pelanggan = Pelanggan::create([
            'nama' => 'Budi FH', 'email' => 'budi-fh@example.com', 'telepon' => '0812000222',
            'alamat' => 'Jl. FH No. 2', 'tipe_konsumen' => 'retail', 'password' => bcrypt('secret123'),
        ]);

        $coas = [
            ['kode' => '110-02', 'nama' => 'Bank', 'tipe' => 'aset', 'kelompok' => 'kas', 'saldo_normal' => 'debit'],
            ['kode' => '120-02', 'nama' => 'Kliring Gateway', 'tipe' => 'aset', 'kelompok' => 'piutang', 'saldo_normal' => 'debit'],
            ['kode' => '120-03', 'nama' => 'Piutang Channel Shopee', 'tipe' => 'aset', 'kelompok' => 'piutang', 'saldo_normal' => 'debit'],
            ['kode' => '120-04', 'nama' => 'Piutang Channel Lainnya', 'tipe' => 'aset', 'kelompok' => 'piutang', 'saldo_normal' => 'debit'],
            ['kode' => '130-01', 'nama' => 'Persediaan', 'tipe' => 'aset', 'kelompok' => 'persediaan', 'saldo_normal' => 'debit'],
            ['kode' => '210-04', 'nama' => 'Titipan Ongkir', 'tipe' => 'kewajiban', 'kelompok' => 'utang_lain', 'saldo_normal' => 'kredit'],
            ['kode' => '210-05', 'nama' => 'Titipan Konsumen', 'tipe' => 'kewajiban', 'kelompok' => 'utang_lain', 'saldo_normal' => 'kredit'],
            ['kode' => '220-01', 'nama' => 'PPN Keluaran', 'tipe' => 'kewajiban', 'kelompok' => 'pajak', 'saldo_normal' => 'kredit'],
            ['kode' => '410-03', 'nama' => 'Pendapatan Online', 'tipe' => 'pendapatan', 'kelompok' => 'pendapatan_penjualan', 'saldo_normal' => 'kredit'],
            ['kode' => '410-04', 'nama' => 'Pendapatan Channel', 'tipe' => 'pendapatan', 'kelompok' => 'pendapatan_penjualan', 'saldo_normal' => 'kredit'],
            ['kode' => '510-02', 'nama' => 'HPP', 'tipe' => 'beban', 'kelompok' => 'hpp', 'saldo_normal' => 'debit'],
            ['kode' => '520-06', 'nama' => 'Beban Admin Marketplace', 'tipe' => 'beban', 'kelompok' => 'biaya_marketplace', 'saldo_normal' => 'debit'],
        ];
        foreach ($coas as $coa) {
            AkunCOA::firstOrCreate(['kode' => $coa['kode']], $coa + ['is_active' => true]);
        }

        $this->channel = Channel::create([
            'nama' => 'Ute Parts Shopee', 'platform' => 'shopee', 'status' => 'terhubung',
            'kredensial' => [
                'partner_id' => 1, 'partner_key' => 'key', 'shop_id' => 2,
                'access_token' => 'tok', 'refresh_token' => 'ref',
                'token_expires_at' => time() + 7200, 'biaya_persen' => 5,
                'cabang_id' => $this->cabang->id,
            ],
            'is_active' => true,
        ]);
    }

    // =====================================================================
    // Helper
    // =====================================================================

    private function buatOrderKadaluarsa(): Transaksi
    {
        $order = app(OrderService::class)->buatOrder(
            $this->pelanggan,
            [['produk_id' => $this->produk->id, 'jumlah' => 1]],
            $this->cabang->id,
            'ambil_ke_toko'
        );
        $order->update(['status' => 'kadaluarsa']);

        return $order->fresh();
    }

    /** @return array<string, array{debit: float, kredit: float}> */
    private function jurnalPerAkun(string $referensiTipe, int $referensiId): array
    {
        $out = [];
        $lines = JurnalAkuntansi::with('akun')
            ->where('referensi_tipe', $referensiTipe)
            ->where('referensi_id', $referensiId)
            ->get();
        foreach ($lines as $line) {
            $kode = $line->akun?->kode ?? '?';
            $out[$kode]['debit'] = ($out[$kode]['debit'] ?? 0) + (float) $line->debit;
            $out[$kode]['kredit'] = ($out[$kode]['kredit'] ?? 0) + (float) $line->kredit;
        }

        return $out;
    }

    private function mappingChannel(): ChannelProductMapping
    {
        return ChannelProductMapping::create([
            'channel_id' => $this->channel->id,
            'produk_id' => $this->produk->id,
            'gudang_id' => $this->gudang->id,
            'channel_sku' => 'SKU-FH',
            'channel_item_id' => 'ITEM-FH-1',
            'status' => 'tersinkron',
        ]);
    }

    private function payloadChannel(float $total, float $ongkir = 0, string $sn = 'SN-FH-1'): array
    {
        return [
            'order_sn' => $sn,
            'order_status' => 'READY_TO_SHIP',
            'total_amount' => $total,
            'actual_shipping_fee' => $ongkir,
            'item_list' => [
                [
                    'item_id' => 'ITEM-FH-1',
                    'model_id' => 0,
                    'item_sku' => 'SKU-FH',
                    'model_quantity_purchased' => 2,
                    'model_discounted_price' => ($total - $ongkir) / 2,
                ],
            ],
        ];
    }

    private function stok(): int
    {
        return (int) StokItem::where('produk_id', $this->produk->id)->value('jumlah');
    }

    // =====================================================================
    // H6 — holding idempoten per order (retry webhook)
    // =====================================================================

    public function test_h6_holding_pertama_tercatat_dr_kliring_cr_210_05_dengan_idempotency_key(): void
    {
        $order = $this->buatOrderKadaluarsa();
        $amount = (int) round((float) $order->total_akhir);

        $hasil = app(DuitkuPaymentProcessor::class)->prosesLunas($order->no_transaksi, $amount, 'REF-H6-A', 'duitku');
        $this->assertSame('order_kadaluarsa_holding', $hasil['kode']);

        $perAkun = $this->jurnalPerAkun(Transaksi::class, $order->id);
        $this->assertEqualsWithDelta($amount, $perAkun['120-02']['debit'] ?? 0, 0.01);
        $this->assertEqualsWithDelta($amount, $perAkun['210-05']['kredit'] ?? 0, 0.01);

        $header = JurnalHeader::where('referensi_tipe', Transaksi::class)
            ->where('referensi_id', $order->id)->first();
        $this->assertNotNull($header);
        $this->assertSame('holding:'.$order->no_transaksi, $header->idempotency_key);
    }

    public function test_h6_retry_callback_order_kadaluarsa_tidak_posting_holding_dobel(): void
    {
        $order = $this->buatOrderKadaluarsa();
        $amount = (int) round((float) $order->total_akhir);
        $processor = app(DuitkuPaymentProcessor::class);

        $hasil1 = $processor->prosesLunas($order->no_transaksi, $amount, 'REF-H6-B', 'duitku');
        $hasil2 = $processor->prosesLunas($order->no_transaksi, $amount, 'REF-H6-B', 'duitku');
        $this->assertSame('order_kadaluarsa_holding', $hasil1['kode']);
        $this->assertSame('order_kadaluarsa_holding', $hasil2['kode']);

        // Hanya SATU jurnal holding; liabilitas 210-05 tidak lebih saji.
        $this->assertSame(1, JurnalHeader::where('referensi_tipe', Transaksi::class)
            ->where('referensi_id', $order->id)->count());
        $perAkun = $this->jurnalPerAkun(Transaksi::class, $order->id);
        $this->assertEqualsWithDelta($amount, $perAkun['210-05']['kredit'] ?? 0, 0.01);

        // Catatan tidak menumpuk marker duplikat.
        $order->refresh();
        $this->assertSame('kadaluarsa', $order->status);
        $this->assertSame(1, substr_count((string) $order->catatan, 'PERLU TINDAKAN MANUAL'));
    }

    public function test_h6_retry_dengan_reference_berbeda_tetap_satu_holding_per_order(): void
    {
        $order = $this->buatOrderKadaluarsa();
        $amount = (int) round((float) $order->total_akhir);
        $processor = app(DuitkuPaymentProcessor::class);

        $processor->prosesLunas($order->no_transaksi, $amount, 'REF-H6-C1', 'duitku');
        $processor->prosesLunas($order->no_transaksi, $amount, 'REF-H6-C2', 'duitku');
        $processor->prosesLunas($order->no_transaksi, $amount, null, 'duitku');

        $this->assertSame(1, JurnalHeader::where('referensi_tipe', Transaksi::class)
            ->where('referensi_id', $order->id)->count());
        $perAkun = $this->jurnalPerAkun(Transaksi::class, $order->id);
        $this->assertEqualsWithDelta($amount, $perAkun['210-05']['kredit'] ?? 0, 0.01);
    }

    // =====================================================================
    // H7 — settlement kliring gateway & channel ke bank
    // =====================================================================

    public function test_h7_settlement_kliring_duitku_dr_bank_cr_120_02_selisih_fee_ke_520_06(): void
    {
        $hasil = app(DuitkuPaymentProcessor::class)->catatSettlementKliring(
            'SETTLE-DUITKU-001', 1000000, 990000, $this->cabang->id
        );
        $this->assertSame('sukses', $hasil['kode']);

        $header = JurnalHeader::where('idempotency_key', 'settlement:120-02:SETTLE-DUITKU-001')->first();
        $this->assertNotNull($header);
        $perAkun = $this->jurnalPerAkun('settlement_kliring', 0);
        $this->assertEqualsWithDelta(990000, $perAkun['110-02']['debit'] ?? 0, 0.01);
        $this->assertEqualsWithDelta(10000, $perAkun['520-06']['debit'] ?? 0, 0.01);
        $this->assertEqualsWithDelta(1000000, $perAkun['120-02']['kredit'] ?? 0, 0.01);
        $this->assertEqualsWithDelta(
            (float) $header->total_debit,
            (float) $header->total_kredit,
            0.01
        );
    }

    public function test_h7_settlement_kliring_idempoten_per_referensi_pencairan(): void
    {
        $svc = app(DuitkuPaymentProcessor::class);
        $hasil1 = $svc->catatSettlementKliring('SETTLE-DUITKU-002', 500000, 500000, $this->cabang->id);
        $hasil2 = $svc->catatSettlementKliring('SETTLE-DUITKU-002', 500000, 500000, $this->cabang->id);
        $this->assertSame('sukses', $hasil1['kode']);
        $this->assertSame('sudah_diproses', $hasil2['kode']);

        $this->assertSame(1, JurnalHeader::where('idempotency_key', 'settlement:120-02:SETTLE-DUITKU-002')->count());
        $perAkun = $this->jurnalPerAkun('settlement_kliring', 0);
        $this->assertEqualsWithDelta(500000, $perAkun['120-02']['kredit'] ?? 0, 0.01);
    }

    public function test_h7_settlement_kliring_diterima_lebih_besar_kredit_520_06(): void
    {
        // Fee estimasi ternyata lebih kecil dari realisasi → koreksi kredit 520-06.
        app(DuitkuPaymentProcessor::class)->catatSettlementKliring(
            'SETTLE-DUITKU-003', 500000, 505000, $this->cabang->id
        );
        $perAkun = $this->jurnalPerAkun('settlement_kliring', 0);
        $this->assertEqualsWithDelta(505000, $perAkun['110-02']['debit'] ?? 0, 0.01);
        $this->assertEqualsWithDelta(5000, $perAkun['520-06']['kredit'] ?? 0, 0.01);
        $this->assertEqualsWithDelta(500000, $perAkun['120-02']['kredit'] ?? 0, 0.01);
    }

    public function test_h7_settlement_channel_shopee_dr_bank_cr_120_03(): void
    {
        $hasil = app(ChannelSyncService::class)->catatSettlementChannel(
            $this->channel, 'PENCAIRAN-SHP-001', 500000, 480000
        );
        $this->assertSame('sukses', $hasil['kode']);

        $perAkun = $this->jurnalPerAkun('settlement_channel', 0);
        $this->assertEqualsWithDelta(480000, $perAkun['110-02']['debit'] ?? 0, 0.01);
        $this->assertEqualsWithDelta(20000, $perAkun['520-06']['debit'] ?? 0, 0.01);
        $this->assertEqualsWithDelta(500000, $perAkun['120-03']['kredit'] ?? 0, 0.01);
        $this->assertArrayNotHasKey('120-04', $perAkun);
    }

    public function test_h7_settlement_channel_non_shopee_memakai_120_04(): void
    {
        $channel = Channel::create([
            'nama' => 'Ute Parts Tokopedia', 'platform' => 'tokopedia', 'status' => 'terhubung',
            'kredensial' => ['cabang_id' => $this->cabang->id], 'is_active' => true,
        ]);
        app(ChannelSyncService::class)->catatSettlementChannel($channel, 'PENCAIRAN-TOPED-001', 300000, 290000);

        $perAkun = $this->jurnalPerAkun('settlement_channel', 0);
        $this->assertEqualsWithDelta(300000, $perAkun['120-04']['kredit'] ?? 0, 0.01);
        $this->assertArrayNotHasKey('120-03', $perAkun);
    }

    public function test_h7_settlement_channel_idempoten_per_referensi(): void
    {
        $svc = app(ChannelSyncService::class);
        $hasil1 = $svc->catatSettlementChannel($this->channel, 'PENCAIRAN-SHP-002', 200000, 200000);
        $hasil2 = $svc->catatSettlementChannel($this->channel, 'PENCAIRAN-SHP-002', 200000, 200000);
        $this->assertSame('sukses', $hasil1['kode']);
        $this->assertSame('sudah_diproses', $hasil2['kode']);
        $this->assertSame(1, JurnalHeader::where('idempotency_key', 'settlement:120-03:PENCAIRAN-SHP-002')->count());
    }

    // =====================================================================
    // H7 — lepas titipan ongkir 210-04 saat kurir ditagih (Biteship)
    // =====================================================================

    private function buatPengiriman(float $ongkir): Pengiriman
    {
        $order = app(OrderService::class)->buatOrder(
            $this->pelanggan,
            [['produk_id' => $this->produk->id, 'jumlah' => 1]],
            $this->cabang->id,
            'kirim_ke_alamat'
        );

        return Pengiriman::create([
            'transaksi_id' => $order->id,
            'kurir' => 'jne', 'layanan' => 'REG', 'ongkir' => $ongkir,
            'nama_penerima' => 'Budi FH', 'alamat_tujuan' => 'Jl. FH No. 2',
            'telepon_tujuan' => '0812000222', 'status' => 'diproses',
        ]);
    }

    public function test_h7_biteship_lepas_titipan_ongkir_dr_210_04_cr_bank(): void
    {
        $pengiriman = $this->buatPengiriman(20000);
        $posted = app(BiteshipService::class)->catatPembayaranOngkir($pengiriman);
        $this->assertTrue($posted);

        $perAkun = $this->jurnalPerAkun(Pengiriman::class, $pengiriman->id);
        $this->assertEqualsWithDelta(20000, $perAkun['210-04']['debit'] ?? 0, 0.01);
        $this->assertEqualsWithDelta(20000, $perAkun['110-02']['kredit'] ?? 0, 0.01);
    }

    public function test_h7_biteship_ongkir_idempoten_per_pengiriman(): void
    {
        $pengiriman = $this->buatPengiriman(20000);
        $svc = app(BiteshipService::class);
        $this->assertTrue($svc->catatPembayaranOngkir($pengiriman));
        $this->assertFalse($svc->catatPembayaranOngkir($pengiriman->fresh()));

        $this->assertSame(1, JurnalHeader::where('referensi_tipe', Pengiriman::class)
            ->where('referensi_id', $pengiriman->id)->count());
        $perAkun = $this->jurnalPerAkun(Pengiriman::class, $pengiriman->id);
        $this->assertEqualsWithDelta(20000, $perAkun['210-04']['debit'] ?? 0, 0.01);
    }

    public function test_h7_biteship_ongkir_nol_tidak_posting(): void
    {
        $pengiriman = $this->buatPengiriman(0);
        $this->assertFalse(app(BiteshipService::class)->catatPembayaranOngkir($pengiriman));
        $this->assertSame(0, JurnalAkuntansi::where('referensi_tipe', Pengiriman::class)->count());
    }

    public function test_h7_biteship_create_order_mencatat_ongkir_saat_kurir_ditagih(): void
    {
        Http::fake([
            '*/orders*' => Http::response([
                'order_id' => 'BTS-1', 'tracking_id' => 'TRK-1', 'waybill_id' => 'WB-1', 'status' => 'allocated',
            ], 200),
        ]);
        $pengiriman = $this->buatPengiriman(25000);

        $hasil = app(BiteshipService::class)->createOrder([
            'courier_company' => 'jne', 'courier_type' => 'REG',
        ], $pengiriman);

        $this->assertSame('TRK-1', $hasil['tracking_id']);
        $perAkun = $this->jurnalPerAkun(Pengiriman::class, $pengiriman->id);
        $this->assertEqualsWithDelta(25000, $perAkun['210-04']['debit'] ?? 0, 0.01);
        $this->assertEqualsWithDelta(25000, $perAkun['110-02']['kredit'] ?? 0, 0.01);
    }

    // =====================================================================
    // H12 — channel fail-closed + pisah DPP/PPN/ongkir
    // =====================================================================

    public function test_h12_jurnal_channel_gagal_rollback_stok_dan_transaksi(): void
    {
        $this->mappingChannel();
        // Akun pendapatan channel nonaktif → JurnalService::post menolak.
        AkunCOA::where('kode', '410-04')->update(['is_active' => false]);

        $order = app(ChannelSyncService::class)->prosesOrderMasuk($this->channel, $this->payloadChannel(300000));

        // Fail-CLOSED: tidak ada transaksi, stok utuh, tidak ada jurnal.
        $this->assertSame('stok_kurang', $order->status);
        $this->assertNull($order->transaksi_id);
        $this->assertSame(0, Transaksi::where('sumber', 'channel:shopee')->count());
        $this->assertSame(100, $this->stok());
        $this->assertSame(0, JurnalAkuntansi::where('sumber', 'channel')->count());
        $this->assertStringContainsString('410-04', (string) $order->catatan);
    }

    public function test_h12_jurnal_channel_pisah_dpp_ppn_ongkir_saat_pajak_aktif(): void
    {
        $this->mappingChannel();
        app(PajakService::class)->set($this->cabang->id, true, 11.0);

        // Total 333.000 termasuk ongkir 30.000 → barang 303.000 (inclusive PPN 11%).
        $order = app(ChannelSyncService::class)->prosesOrderMasuk(
            $this->channel, $this->payloadChannel(333000, 30000, 'SN-FH-PPN')
        );
        $this->assertNotNull($order->transaksi_id);
        $this->assertSame(98, $this->stok());

        $transaksi = Transaksi::findOrFail($order->transaksi_id);
        $dpp = round(303000 / 1.11, 2);          // 272.972,97
        $ppn = round(303000 - $dpp, 2);          // 30.027,03
        $this->assertEqualsWithDelta($dpp, (float) $transaksi->dpp, 0.01);
        $this->assertEqualsWithDelta($ppn, (float) $transaksi->ppn_nominal, 0.01);
        $this->assertEqualsWithDelta(303000, (float) $transaksi->subtotal, 0.01);
        $this->assertEqualsWithDelta(333000, (float) $transaksi->total_akhir, 0.01);

        $perAkun = $this->jurnalPerAkun(Transaksi::class, $transaksi->id);
        $this->assertEqualsWithDelta(333000, $perAkun['120-03']['debit'] ?? 0, 0.01);
        $this->assertEqualsWithDelta($dpp, $perAkun['410-04']['kredit'] ?? 0, 0.01);
        $this->assertEqualsWithDelta($ppn, $perAkun['220-01']['kredit'] ?? 0, 0.01);
        $this->assertEqualsWithDelta(30000, $perAkun['210-04']['kredit'] ?? 0, 0.01);
        $this->assertEqualsWithDelta(200000, $perAkun['510-02']['debit'] ?? 0, 0.01);
        $this->assertEqualsWithDelta(200000, $perAkun['130-01']['kredit'] ?? 0, 0.01);

        // Balance debit = kredit.
        $debit = array_sum(array_column($perAkun, 'debit'));
        $kredit = array_sum(array_column($perAkun, 'kredit'));
        $this->assertEqualsWithDelta($debit, $kredit, 0.01);
    }

    public function test_h12_channel_tanpa_pajak_pendapatan_neto_ongkir_tanpa_baris_ppn(): void
    {
        $this->mappingChannel();

        $order = app(ChannelSyncService::class)->prosesOrderMasuk(
            $this->channel, $this->payloadChannel(320000, 20000, 'SN-FH-NOPPN')
        );
        $this->assertNotNull($order->transaksi_id);
        $transaksi = Transaksi::findOrFail($order->transaksi_id);

        $perAkun = $this->jurnalPerAkun(Transaksi::class, $transaksi->id);
        $this->assertEqualsWithDelta(320000, $perAkun['120-03']['debit'] ?? 0, 0.01);
        $this->assertEqualsWithDelta(300000, $perAkun['410-04']['kredit'] ?? 0, 0.01);
        $this->assertEqualsWithDelta(20000, $perAkun['210-04']['kredit'] ?? 0, 0.01);
        $this->assertArrayNotHasKey('220-01', $perAkun);
        $this->assertEqualsWithDelta(200000, $perAkun['510-02']['debit'] ?? 0, 0.01);
    }

    public function test_h12_channel_tanpa_ongkir_tanpa_baris_210_04(): void
    {
        $this->mappingChannel();
        $order = app(ChannelSyncService::class)->prosesOrderMasuk(
            $this->channel, $this->payloadChannel(300000, 0, 'SN-FH-NOONGKIR')
        );
        $this->assertNotNull($order->transaksi_id);
        $perAkun = $this->jurnalPerAkun(Transaksi::class, $order->transaksi_id);
        $this->assertEqualsWithDelta(300000, $perAkun['410-04']['kredit'] ?? 0, 0.01);
        $this->assertArrayNotHasKey('210-04', $perAkun);
    }
}
