<?php

namespace Tests\Feature;

use App\Modules\Akunting\Models\AkunCOA;
use App\Modules\Akunting\Models\JurnalAkuntansi;
use App\Modules\Pos\Models\Transaksi;
use App\Modules\Pos\Models\TransaksiItem;
use App\Modules\Rbac\Models\Cabang;
use App\Modules\Sync\Models\HybridSyncInbox;
use App\Modules\Sync\Services\HybridSyncProcessorService;
use App\Modules\Wms\Models\Gudang;
use App\Modules\Wms\Models\Produk;
use App\Modules\Wms\Models\StokItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * [C4] HybridSync order.online_paid (cloud -> lokal) wajib mengikuti kontrak
 * satuan/HPP POS-native (PosCheckoutService / TransaksiVoidService):
 * - satuan & konversi asli payload tersimpan di TransaksiItem (bukan hardcode pcs/1);
 * - hpp per SATUAN tervalidasi (cloud <= 0 -> harga_beli lokal x konversi);
 * - stok dipotong qtyDasar = jumlah x konversi (konsisten dgn yg tersimpan);
 * - jurnal WAJIB punya baris HPP (Dr 510-02 / Cr 130-01) + pisah DPP/PPN/ongkir;
 * - fail-CLOSED: stok/jurnal gagal -> rollback transaksi & stok, inbox failed;
 * - produk tak dikenal TIDAK auto-create harga_beli=0 (tolak, error jelas).
 */
class HybridSyncOrderOnlinePaidTest extends TestCase
{
    use RefreshDatabase;

    protected Cabang $cabang;

    protected Gudang $gudang;

    protected Produk $produk;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'sync.enabled' => true,
            'sync.api_secret' => 'test-secret-key-12345',
            'sync.node_id' => 'STORE-TEST-01',
            'sync.node_role' => 'primary_store',
        ]);

        $this->cabang = Cabang::create(['kode' => 'CBG-C4', 'nama' => 'Cabang C4', 'is_active' => true]);
        $this->gudang = Gudang::create([
            'cabang_id' => $this->cabang->id,
            'nama' => 'Gudang Utama',
            'kode' => 'GDG-C4',
            'is_active' => true,
            'is_default' => true,
        ]);

        foreach ([
            ['120-02', 'Kliring Gateway', 'aset', 'kas', 'debit'],
            ['410-03', 'Pendapatan Online', 'pendapatan', 'pendapatan_penjualan', 'kredit'],
            ['510-02', 'HPP', 'beban', 'hpp', 'debit'],
            ['130-01', 'Persediaan', 'aset', 'persediaan', 'debit'],
            ['220-01', 'PPN Keluaran', 'kewajiban', 'pajak', 'kredit'],
            ['210-04', 'Titipan Ongkir', 'kewajiban', 'utang_lain', 'kredit'],
        ] as [$kode, $nama, $tipe, $kelompok, $saldo]) {
            AkunCOA::firstOrCreate(['kode' => $kode], [
                'nama' => $nama, 'tipe' => $tipe, 'kelompok' => $kelompok, 'saldo_normal' => $saldo, 'is_active' => true,
            ]);
        }

        $this->produk = Produk::create([
            'nama' => 'Kampas Rem C4',
            'slug' => 'kampas-rem-c4',
            'kategori' => 'Rem',
            'kondisi' => 'baru',
            'satuan' => 'pcs',
            'harga_beli' => 50000,
            'harga_jual_retail' => 100000,
        ]);

        StokItem::create([
            'produk_id' => $this->produk->id,
            'gudang_id' => $this->gudang->id,
            'jumlah' => 100,
            'jumlah_minimum' => 1,
        ]);
    }

    private function proses(array $payload, ?string $uuid = null): array
    {
        return app(HybridSyncProcessorService::class)->process([
            'sync_uuid' => $uuid ?? (string) Str::uuid(),
            'event_type' => 'order.online_paid',
            'source_node' => 'CLOUD-VPS',
            'payload' => $payload,
        ]);
    }

    private function payload(array $overrides = [], ?array $items = null): array
    {
        return array_merge([
            'order_id' => 555,
            'nomor_order' => 'ORDER-C4-555',
            'cabang_id' => $this->cabang->id,
            'total' => 200000,
            'items' => $items ?? [
                ['produk_id' => $this->produk->id, 'qty' => 2, 'harga' => 100000],
            ],
        ], $overrides);
    }

    /** Item 2 box (konversi 12) @ Rp600.000/box, hpp cloud Rp600.000/box. */
    private function payloadBox(array $overrides = []): array
    {
        return $this->payload(array_merge(['total' => 1200000], $overrides), [
            [
                'produk_id' => $this->produk->id,
                'qty' => 2,
                'satuan' => 'box',
                'konversi' => 12,
                'harga' => 600000,
                'hpp' => 600000,
            ],
        ]);
    }

    private function stokJumlah(): int
    {
        return (int) StokItem::where('produk_id', $this->produk->id)->where('gudang_id', $this->gudang->id)->value('jumlah');
    }

    private function assertJurnalLine(string $kode, float $debit, float $kredit): void
    {
        $line = JurnalAkuntansi::whereHas('akun', fn ($q) => $q->where('kode', $kode))->first();
        $this->assertNotNull($line, "Baris jurnal akun {$kode} tidak ditemukan.");
        $this->assertSame($debit, (float) $line->debit, "Debit akun {$kode} salah.");
        $this->assertSame($kredit, (float) $line->kredit, "Kredit akun {$kode} salah.");
    }

    public function test_satuan_dan_konversi_asli_payload_tersimpan_di_transaksi_item(): void
    {
        $res = $this->proses($this->payloadBox());
        $this->assertTrue($res['success'], $res['message'] ?? '');

        $item = TransaksiItem::first();
        $this->assertNotNull($item);
        $this->assertSame(2, (int) $item->jumlah);
        $this->assertSame('box', $item->satuan);
        $this->assertSame(12.0, (float) $item->konversi);
        $this->assertSame(600000.0, (float) $item->hpp); // hpp per SATUAN (box)
    }

    public function test_stok_dipotong_qty_dasar_dan_jurnal_hpp_wajib_ada(): void
    {
        $res = $this->proses($this->payloadBox());
        $this->assertTrue($res['success'], $res['message'] ?? '');

        // qtyDasar = 2 box x 12 = 24 pcs
        $this->assertSame(76, $this->stokJumlah());

        // Total HPP = hpp per satuan x jumlah = 600.000 x 2 = 1.200.000
        $this->assertJurnalLine('510-02', 1200000.0, 0.0);
        $this->assertJurnalLine('130-01', 0.0, 1200000.0);
        $this->assertJurnalLine('120-02', 1200000.0, 0.0);
        $this->assertJurnalLine('410-03', 0.0, 1200000.0);
    }

    public function test_hpp_cloud_nol_fallback_harga_beli_lokal_kali_konversi(): void
    {
        $payload = $this->payloadBox();
        $payload['items'][0]['hpp'] = 0;

        $res = $this->proses($payload);
        $this->assertTrue($res['success'], $res['message'] ?? '');

        // harga_beli lokal 50.000 x konversi 12 = 600.000 per box
        $this->assertSame(600000.0, (float) TransaksiItem::first()->hpp);
        $this->assertJurnalLine('510-02', 1200000.0, 0.0);
        $this->assertJurnalLine('130-01', 0.0, 1200000.0);
    }

    public function test_hpp_cloud_negatif_fallback_harga_beli_lokal_kali_konversi(): void
    {
        $payload = $this->payloadBox();
        $payload['items'][0]['hpp'] = -500;

        $res = $this->proses($payload);
        $this->assertTrue($res['success'], $res['message'] ?? '');

        $this->assertSame(600000.0, (float) TransaksiItem::first()->hpp);
        $this->assertJurnalLine('510-02', 1200000.0, 0.0);
    }

    public function test_hpp_cloud_positif_dipakai_dan_jurnal_hpp_konsisten(): void
    {
        $payload = $this->payloadBox();
        $payload['items'][0]['hpp'] = 550000; // beda dari harga_beli lokal x konversi

        $res = $this->proses($payload);
        $this->assertTrue($res['success'], $res['message'] ?? '');

        $this->assertSame(550000.0, (float) TransaksiItem::first()->hpp);
        // Jurnal HPP = hpp tervalidasi x jumlah (konsisten dgn snapshot item utk void/retur)
        $this->assertJurnalLine('510-02', 1100000.0, 0.0);
        $this->assertJurnalLine('130-01', 0.0, 1100000.0);
    }

    public function test_jurnal_pisah_dpp_ppn_dari_payload(): void
    {
        $payload = $this->payload([
            'total' => 1110000,
            'dpp' => 1000000,
            'ppn_nominal' => 110000,
        ], [
            ['produk_id' => $this->produk->id, 'qty' => 1, 'harga' => 1000000],
        ]);

        $res = $this->proses($payload);
        $this->assertTrue($res['success'], $res['message'] ?? '');

        $this->assertJurnalLine('120-02', 1110000.0, 0.0);
        $this->assertJurnalLine('410-03', 0.0, 1000000.0); // pendapatan = DPP, bukan total
        $this->assertJurnalLine('220-01', 0.0, 110000.0);
        $this->assertJurnalLine('510-02', 50000.0, 0.0);
        $this->assertJurnalLine('130-01', 0.0, 50000.0);

        $this->assertDatabaseHas('transaksi', [
            'no_transaksi' => 'ORDER-C4-555',
            'dpp' => 1000000,
            'ppn_nominal' => 110000,
            'pajak_nominal' => 110000,
            'total_akhir' => 1110000,
        ]);
    }

    public function test_ppn_disimpulkan_dari_dpp_bila_ppn_payload_kosong(): void
    {
        $payload = $this->payload([
            'total' => 1110000,
            'dpp' => 1000000,
        ], [
            ['produk_id' => $this->produk->id, 'qty' => 1, 'harga' => 1000000],
        ]);

        $res = $this->proses($payload);
        $this->assertTrue($res['success'], $res['message'] ?? '');

        $this->assertJurnalLine('410-03', 0.0, 1000000.0);
        $this->assertJurnalLine('220-01', 0.0, 110000.0);
    }

    public function test_ongkir_masuk_titipan_210_04_bukan_pendapatan(): void
    {
        $payload = $this->payload([
            'total' => 1150000,
            'ongkir' => 40000,
            'dpp' => 1000000,
            'ppn_nominal' => 110000,
        ], [
            ['produk_id' => $this->produk->id, 'qty' => 1, 'harga' => 1000000],
        ]);

        $res = $this->proses($payload);
        $this->assertTrue($res['success'], $res['message'] ?? '');

        $this->assertJurnalLine('120-02', 1150000.0, 0.0);
        $this->assertJurnalLine('410-03', 0.0, 1000000.0);
        $this->assertJurnalLine('220-01', 0.0, 110000.0);
        $this->assertJurnalLine('210-04', 0.0, 40000.0);
    }

    public function test_produk_tidak_dikenal_ditolak_tanpa_auto_create(): void
    {
        $payload = $this->payload(['total' => 10000], [
            ['produk_id' => 999999, 'qty' => 1, 'harga' => 10000],
        ]);

        $res = $this->proses($payload);

        $this->assertFalse($res['success']);
        $this->assertStringContainsString('tidak ditemukan', strtolower($res['message']));
        $this->assertDatabaseMissing('produk', ['id' => 999999]);
        $this->assertDatabaseMissing('transaksi', ['no_transaksi' => 'ORDER-C4-555']);
        $this->assertSame(0, JurnalAkuntansi::count());
        $this->assertSame(100, $this->stokJumlah());
        $this->assertDatabaseHas('hybrid_sync_inbox', ['status' => 'failed']);
    }

    public function test_stok_tidak_cukup_fail_closed_rollback_transaksi_dan_jurnal(): void
    {
        StokItem::where('produk_id', $this->produk->id)->update(['jumlah' => 5]);

        $res = $this->proses($this->payloadBox()); // butuh 24 pcs, stok hanya 5

        $this->assertFalse($res['success']);
        $this->assertDatabaseMissing('transaksi', ['no_transaksi' => 'ORDER-C4-555']);
        $this->assertSame(0, TransaksiItem::count());
        $this->assertSame(5, $this->stokJumlah());
        $this->assertSame(0, JurnalAkuntansi::count());
        $this->assertDatabaseHas('hybrid_sync_inbox', ['status' => 'failed']);
    }

    public function test_jurnal_gagal_fail_closed_rollback_stok_dan_transaksi(): void
    {
        AkunCOA::where('kode', '410-03')->update(['is_active' => false]);

        $res = $this->proses($this->payload());

        $this->assertFalse($res['success']);
        $this->assertDatabaseMissing('transaksi', ['no_transaksi' => 'ORDER-C4-555']);
        $this->assertSame(100, $this->stokJumlah()); // stok tidak boleh terlanjur dipotong
        $this->assertSame(0, JurnalAkuntansi::count());
        $this->assertDatabaseHas('hybrid_sync_inbox', ['status' => 'failed']);
    }

    public function test_order_sama_uuid_berbeda_tidak_dobel_stok_dan_jurnal(): void
    {
        $res1 = $this->proses($this->payload());
        $this->assertTrue($res1['success'], $res1['message'] ?? '');

        $res2 = $this->proses($this->payload(), (string) Str::uuid());
        $this->assertTrue($res2['success'], $res2['message'] ?? '');

        $this->assertSame(98, $this->stokJumlah()); // hanya potong sekali
        $this->assertSame(1, TransaksiItem::count());
        $this->assertSame(1, JurnalAkuntansi::whereHas('akun', fn ($q) => $q->where('kode', '120-02'))->count());
    }

    /** HPP=0 tidak lagi menolak pesanan — transaksi tetap dibuat, jurnal HPP/Persediaan dilewati. */
    public function test_hpp_nol_karena_harga_beli_nol_ditolak_dengan_error_jelas(): void
    {
        $this->produk->update(['harga_beli' => 0]);

        $res = $this->proses($this->payload()); // tanpa hpp cloud

        // Pesanan online tetap diterima; hanya baris HPP/Persediaan yang dilewati
        $this->assertTrue($res['success'], $res['message'] ?? '');
        $this->assertDatabaseHas('transaksi', ['no_transaksi' => 'ORDER-C4-555']);
        // Stok tetap dipotong karena pesanan fisik sudah lunas
        $this->assertSame(98, $this->stokJumlah());
        // Jurnal pendapatan tetap dibuat; baris 510-02/130-01 tidak ada
        $this->assertSame(0, JurnalAkuntansi::whereHas('akun', fn ($q) => $q->where('kode', '510-02'))->count());
        $this->assertGreaterThan(0, JurnalAkuntansi::whereHas('akun', fn ($q) => $q->where('kode', '410-03'))->count());
    }

    public function test_konversi_nol_ditolak(): void
    {
        $payload = $this->payload([], [
            ['produk_id' => $this->produk->id, 'qty' => 2, 'harga' => 100000, 'konversi' => 0],
        ]);

        $res = $this->proses($payload);

        $this->assertFalse($res['success']);
        $this->assertStringContainsString('konversi', strtolower($res['message']));
        $this->assertDatabaseMissing('transaksi', ['no_transaksi' => 'ORDER-C4-555']);
        $this->assertSame(100, $this->stokJumlah());
    }

    public function test_jurnal_referensi_transaksi_untuk_reversal_void(): void
    {
        $res = $this->proses($this->payload());
        $this->assertTrue($res['success'], $res['message'] ?? '');

        $transaksi = Transaksi::where('no_transaksi', 'ORDER-C4-555')->first();
        $this->assertNotNull($transaksi);
        $this->assertSame(
            4, // 120-02, 410-03, 510-02, 130-01
            JurnalAkuntansi::where('referensi_tipe', Transaksi::class)->where('referensi_id', $transaksi->id)->count()
        );
        $this->assertNotNull(HybridSyncInbox::where('status', 'processed')->first());
    }
}
