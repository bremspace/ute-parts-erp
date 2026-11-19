<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Akunting\Models\JurnalAkuntansi;
use App\Modules\Akunting\Models\JurnalHeader;
use App\Modules\Akunting\Models\Piutang;
use App\Modules\Akunting\Models\Utang;
use App\Modules\Akunting\Services\JurnalService;
use App\Modules\Crm\Models\Pelanggan;
use App\Modules\Pos\Models\ReturnPenjualan;
use App\Modules\Pos\Models\Transaksi;
use App\Modules\Pos\Models\TransaksiItem;
use App\Modules\Pos\Services\TransaksiVoidService;
use App\Modules\Rbac\Models\Cabang;
use App\Modules\Reseller\Models\Komisi;
use App\Modules\Reseller\Services\KomisiService;
use App\Modules\Wms\Models\Gudang;
use App\Modules\Wms\Models\KategoriProduk;
use App\Modules\Wms\Models\Produk;
use App\Modules\Wms\Models\StokItem;
use App\Modules\Wms\Models\StokLog;
use Database\Seeders\AkunCoaSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Integritas void POS — audit H5 (guard retur + fallback gudang),
 * F-10/B-2 (metadata reversal jurnal + guard piutang dibayar),
 * B-4 (enum utang valid saat batal komisi + reversal akrual komisi).
 */
class TransaksiVoidIntegrityTest extends TestCase
{
    use RefreshDatabase;

    private Cabang $cabang;

    private User $kasirUser;

    private User $supervisorUser;

    private Pelanggan $pelanggan;

    private Produk $produk;

    private Gudang $gudang;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(AkunCoaSeeder::class);

        $this->cabang = Cabang::create([
            'nama' => 'Cabang Void Test',
            'kode' => 'CBG-VOID',
            'is_active' => true,
        ]);

        $this->kasirUser = User::create([
            'name' => 'Kasir Void Test',
            'email' => 'kasir-void@uteparts.id',
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);
        $this->kasirUser->assignRole('kasir');
        $this->kasirUser->cabangs()->attach($this->cabang->id);

        $this->supervisorUser = User::create([
            'name' => 'Supervisor Void Test',
            'email' => 'supervisor-void@uteparts.id',
            'password' => Hash::make('supervisorSecret123'),
            'is_active' => true,
        ]);
        $this->supervisorUser->assignRole('admin-toko');
        $this->supervisorUser->cabangs()->attach($this->cabang->id);

        $this->pelanggan = Pelanggan::create([
            'cabang_id' => $this->cabang->id,
            'nama' => 'Pelanggan Void Test',
            'telepon' => '0899999999',
        ]);

        $kategori = KategoriProduk::create([
            'nama' => 'Kategori Void Test',
            'kode' => 'KV-TEST',
            'cabang_id' => $this->cabang->id,
        ]);

        $this->produk = Produk::create([
            'kategori_id' => $kategori->id,
            'nama' => 'Produk Void Test',
            'harga_beli' => 100000,
            'harga_jual_retail' => 200000,
            'satuan' => 'pcs',
            'is_active' => true,
        ]);

        $this->gudang = Gudang::create([
            'cabang_id' => $this->cabang->id,
            'nama' => 'Gudang Void Test',
            'kode' => 'GUD-VOID-01',
            'is_active' => true,
        ]);

        StokItem::create([
            'produk_id' => $this->produk->id,
            'gudang_id' => $this->gudang->id,
            'jumlah' => 100,
        ]);
    }

    private function buatTransaksi(array $overrides = [], array $itemOverrides = []): Transaksi
    {
        $trx = Transaksi::create(array_merge([
            'no_transaksi' => 'TRX-VOID-'.uniqid(),
            'cabang_id' => $this->cabang->id,
            'kasir_id' => $this->kasirUser->id,
            'pelanggan_id' => $this->pelanggan->id,
            'gudang_id' => $this->gudang->id,
            'sumber' => 'pos',
            'subtotal' => 200000,
            'total_akhir' => 200000,
            'metode_bayar' => 'tunai',
            'status' => 'selesai',
        ], $overrides));

        TransaksiItem::create(array_merge([
            'transaksi_id' => $trx->id,
            'produk_id' => $this->produk->id,
            'jumlah' => 1,
            'satuan' => 'pcs',
            'konversi' => 1,
            'harga_satuan' => 200000,
            'hpp' => 100000,
            'subtotal' => 200000,
        ], $itemOverrides));

        return $trx;
    }

    private function buatRetur(Transaksi $trx, string $status, string $noReturn): ReturnPenjualan
    {
        $retur = ReturnPenjualan::create([
            'no_return' => $noReturn,
            'transaksi_id' => $trx->id,
            'pelanggan_id' => $this->pelanggan->id,
            'cabang_id' => $this->cabang->id,
            'user_id' => $this->kasirUser->id,
            'tanggal' => now(),
            'jumlah' => 200000,
            'status' => $status,
            'metode_pengembalian' => 'kas',
            'alasan' => 'Barang tidak sesuai',
        ]);

        $retur->items()->create([
            'produk_id' => $this->produk->id,
            'jumlah' => 1,
            'harga_satuan' => 200000,
            'subtotal' => 200000,
            'kondisi_barang' => 'bagus',
            'alasan' => 'Tidak sesuai pesanan',
        ]);

        return $retur;
    }

    private function postJurnalPenjualan(Transaksi $trx, string $akunDebit = '110-01'): string
    {
        $jurnalService = app(JurnalService::class);
        $total = (float) $trx->total_akhir;

        return DB::transaction(function () use ($jurnalService, $trx, $akunDebit, $total) {
            $noJurnal = $jurnalService->generateNoJurnal('pos', $this->cabang->id);
            $jurnalService->post(
                noJurnal: $noJurnal,
                tanggal: now(),
                sumber: 'pos',
                lines: [
                    ['akun_kode' => $akunDebit, 'debit' => $total, 'kredit' => 0],
                    ['akun_kode' => '410-01', 'debit' => 0, 'kredit' => $total],
                ],
                deskripsi: "Penjualan POS {$trx->no_transaksi}",
                cabangId: $this->cabang->id,
                userId: $this->kasirUser->id,
                referensiTipe: Transaksi::class,
                referensiId: $trx->id,
            );

            return $noJurnal;
        });
    }

    private function stokSaatIni(): int
    {
        return (int) StokItem::where('produk_id', $this->produk->id)
            ->where('gudang_id', $this->gudang->id)
            ->value('jumlah');
    }

    // =====================================================================
    // H5 — Guard retur & fallback gudang
    // =====================================================================

    public function test_void_ditolak_bila_ada_retur_selesai(): void
    {
        $trx = $this->buatTransaksi(['no_transaksi' => 'TRX-VOID-RETUR-01'], ['jumlah' => 10]);
        $this->buatRetur($trx, 'selesai', 'RET-VOID-001');

        try {
            app(TransaksiVoidService::class)->voidTransaksi($trx, $this->supervisorUser, 'Coba void setelah retur');
            $this->fail('Void seharusnya ditolak karena ada retur selesai.');
        } catch (\Exception $e) {
            $this->assertStringContainsString('retur', strtolower($e->getMessage()));
            $this->assertStringContainsString('RET-VOID-001', $e->getMessage());
        }

        $this->assertEquals('selesai', $trx->fresh()->status);
        $this->assertEquals(100, $this->stokSaatIni(), 'Stok tidak boleh berubah saat void ditolak.');
    }

    public function test_void_ditolak_bila_retur_masih_aktif_draft_atau_disetujui(): void
    {
        foreach (['draft', 'disetujui'] as $i => $status) {
            $trx = $this->buatTransaksi(['no_transaksi' => "TRX-VOID-RETUR-AKTIF-{$i}"]);
            $this->buatRetur($trx, $status, "RET-VOID-AKTIF-{$i}");

            try {
                app(TransaksiVoidService::class)->voidTransaksi($trx, $this->supervisorUser, 'Coba void saat retur aktif');
                $this->fail("Void seharusnya ditolak karena ada retur berstatus '{$status}'.");
            } catch (\Exception $e) {
                $this->assertStringContainsString('retur', strtolower($e->getMessage()));
            }

            $this->assertEquals('selesai', $trx->fresh()->status);
        }
    }

    public function test_void_boleh_bila_retur_ditolak(): void
    {
        $trx = $this->buatTransaksi(['no_transaksi' => 'TRX-VOID-RETUR-DITOLAK']);
        $this->buatRetur($trx, 'ditolak', 'RET-VOID-DITOLAK');

        app(TransaksiVoidService::class)->voidTransaksi($trx, $this->supervisorUser, 'Retur sudah ditolak, void sah');

        $this->assertEquals('void', $trx->fresh()->status);
        $this->assertEquals(101, $this->stokSaatIni());
    }

    public function test_void_tanpa_gudang_tidak_menciptakan_stok_di_gudang_fallback(): void
    {
        // Jual tanpa gudang (checkout tidak memotong stok) -> void tidak boleh
        // menciptakan stok di gudang fallback (dulu ?? 1).
        $trx = $this->buatTransaksi([
            'no_transaksi' => 'TRX-VOID-NO-GUDANG',
            'gudang_id' => null,
        ], ['jumlah' => 5]);

        app(TransaksiVoidService::class)->voidTransaksi($trx, $this->supervisorUser, 'Void transaksi tanpa gudang');

        $this->assertEquals('void', $trx->fresh()->status);
        $this->assertEquals(100, $this->stokSaatIni(), 'Stok gudang lain tidak boleh bertambah.');
        $this->assertEquals(0, StokLog::where('referensi_tipe', Transaksi::class)
            ->where('referensi_id', $trx->id)->count(), 'Tidak boleh ada mutasi stok untuk void tanpa gudang.');
    }

    public function test_void_restore_qty_dasar_sesuai_konversi_satuan(): void
    {
        // Kontrak: qtyDasar = round(jumlah x konversi). 2 box x 12 = 24 pcs.
        $trx = $this->buatTransaksi([
            'no_transaksi' => 'TRX-VOID-BOX',
            'subtotal' => 4800000,
            'total_akhir' => 4800000,
        ], ['jumlah' => 2, 'satuan' => 'box', 'konversi' => 12, 'harga_satuan' => 2400000, 'subtotal' => 4800000]);

        app(TransaksiVoidService::class)->voidTransaksi($trx, $this->supervisorUser, 'Void penjualan box');

        $this->assertEquals(124, $this->stokSaatIni());
    }

    public function test_void_transaksi_tukar_barang_ditolak(): void
    {
        // Jurnal transaksi tukar ber-referensi ReturnPenjualan, bukan Transaksi:
        // void langsung akan restore stok tanpa membalik pendapatan.
        $trx = $this->buatTransaksi([
            'no_transaksi' => 'TRX-TUKAR-VOID-01',
            'metode_bayar' => 'tukar_barang',
        ]);

        try {
            app(TransaksiVoidService::class)->voidTransaksi($trx, $this->supervisorUser, 'Coba void trx tukar');
            $this->fail('Void transaksi tukar_barang seharusnya ditolak.');
        } catch (\Exception $e) {
            $this->assertStringContainsString('tukar', strtolower($e->getMessage()));
        }

        $this->assertEquals('selesai', $trx->fresh()->status);
        $this->assertEquals(100, $this->stokSaatIni());
    }

    // =====================================================================
    // F-10 / B-2 — Metadata reversal jurnal & guard piutang
    // =====================================================================

    public function test_void_menandai_jurnal_asal_reversed_dan_membuat_reversal_tertaut(): void
    {
        $trx = $this->buatTransaksi(['no_transaksi' => 'TRX-VOID-JURNAL-01']);
        $noAsal = $this->postJurnalPenjualan($trx);

        app(TransaksiVoidService::class)->voidTransaksi($trx, $this->supervisorUser, 'Salah input kasir');

        // Header asal wajib ditandai reversed + tertaut ke jurnal pembalik.
        $headerAsal = JurnalHeader::where('no_jurnal', $noAsal)
            ->where('cabang_key', $this->cabang->id)->first();
        $this->assertNotNull($headerAsal);
        $this->assertTrue((bool) $headerAsal->is_reversed, 'Jurnal asal harus ditandai is_reversed.');
        $this->assertNotEmpty($headerAsal->reversed_by_no_jurnal);

        // Jurnal pembalik: sumber 'reversal', tertaut ke asal, D/K tertukar.
        $headerReversal = JurnalHeader::where('no_jurnal', $headerAsal->reversed_by_no_jurnal)
            ->where('cabang_key', $this->cabang->id)->first();
        $this->assertNotNull($headerReversal);
        $this->assertEquals('reversal', $headerReversal->sumber);
        $this->assertEquals($noAsal, $headerReversal->reversal_of_no_jurnal);

        $linesReversal = JurnalAkuntansi::with('akun')->where('no_jurnal', $headerReversal->no_jurnal)
            ->where('cabang_id', $this->cabang->id)->get();
        $this->assertEquals(200000.0, (float) $linesReversal->firstWhere('akun.kode', '410-01')->debit);
        $this->assertEquals(200000.0, (float) $linesReversal->firstWhere('akun.kode', '110-01')->kredit);

        // Jurnal asal tidak boleh bisa di-reversal lagi lewat jalur resmi.
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('sudah pernah dibalik');
        app(JurnalService::class)->reversalJurnal($noAsal, $this->cabang->id, $this->supervisorUser->id, 'Coba reversal ganda');
    }

    public function test_void_kasbon_yang_piutangnya_sudah_dibayar_sebagian_ditolak(): void
    {
        $trx = $this->buatTransaksi([
            'no_transaksi' => 'TRX-VOID-KASBON-SEBAGIAN',
            'metode_bayar' => 'piutang',
        ]);
        Piutang::create([
            'no_piutang' => 'AR-VOID-001',
            'pelanggan_id' => $this->pelanggan->id,
            'transaksi_id' => $trx->id,
            'jumlah' => 200000,
            'jumlah_dibayar' => 50000,
            'status' => 'sebagian',
        ]);

        try {
            app(TransaksiVoidService::class)->voidTransaksi($trx, $this->supervisorUser, 'Coba void kasbon dibayar sebagian');
            $this->fail('Void seharusnya ditolak karena piutang sudah dibayar sebagian.');
        } catch (\Exception $e) {
            $this->assertStringContainsString('piutang', strtolower($e->getMessage()));
        }

        $this->assertEquals('selesai', $trx->fresh()->status);
        $this->assertEquals(100, $this->stokSaatIni());
    }

    public function test_void_kasbon_belum_dibayar_tetap_berhasil(): void
    {
        $trx = $this->buatTransaksi([
            'no_transaksi' => 'TRX-VOID-KASBON-BELUM',
            'metode_bayar' => 'piutang',
        ]);
        $this->postJurnalPenjualan($trx, '120-01');
        $piutang = Piutang::create([
            'no_piutang' => 'AR-VOID-002',
            'pelanggan_id' => $this->pelanggan->id,
            'transaksi_id' => $trx->id,
            'jumlah' => 200000,
            'jumlah_dibayar' => 0,
            'status' => 'belum_lunas',
        ]);

        app(TransaksiVoidService::class)->voidTransaksi($trx, $this->supervisorUser, 'Batal kasbon belum dibayar');

        $this->assertEquals('void', $trx->fresh()->status);
        $piutang->refresh();
        $this->assertEquals('lunas', $piutang->status);
        $this->assertStringContainsString('Void', (string) $piutang->keterangan);
    }

    // =====================================================================
    // B-4 — Enum utang valid + reversal akrual komisi
    // =====================================================================

    private function buatKomisiDisetujui(Transaksi $trx, string $noKomisi): Komisi
    {
        $reseller = Pelanggan::create([
            'cabang_id' => $this->cabang->id,
            'nama' => 'Reseller Void Test',
            'telepon' => '0877777777',
            'is_reseller' => true,
            'tipe_konsumen' => 'reseller',
        ]);

        $komisi = Komisi::create([
            'no_komisi' => $noKomisi,
            'pelanggan_id' => $reseller->id,
            'transaksi_id' => $trx->id,
            'nominal_komisi' => 25000,
            'jumlah_transaksi' => (float) $trx->total_akhir,
            'status' => Komisi::STATUS_PENDING,
        ]);

        app(KomisiService::class)->prosesApproval([$komisi->id], 'approve', $this->supervisorUser->id);

        return $komisi->fresh();
    }

    public function test_void_komisi_disetujui_membatalkan_utang_dan_membalik_jurnal_akrual(): void
    {
        $trx = $this->buatTransaksi(['no_transaksi' => 'TRX-VOID-KOMISI-01']);
        $komisi = $this->buatKomisiDisetujui($trx, 'KMS-VOID-0001');
        $this->assertEquals(Komisi::STATUS_DISETUJUI, $komisi->status);

        $utang = Utang::where('referensi_tipe', 'komisi')->where('referensi_id', $komisi->id)->first();
        $this->assertNotNull($utang);
        $this->assertEquals('belum_lunas', $utang->status);

        $noAkrual = JurnalAkuntansi::where('referensi_tipe', Komisi::class)
            ->where('referensi_id', $komisi->id)->value('no_jurnal');
        $this->assertNotEmpty($noAkrual);

        // Sebelum fix: update status 'batal' melanggar enum (MySQL strict /
        // SQLite check constraint) -> seluruh void rollback.
        app(TransaksiVoidService::class)->voidTransaksi($trx, $this->supervisorUser, 'Batal transaksi berkomisi');

        $this->assertEquals('void', $trx->fresh()->status);
        $this->assertEquals(Komisi::STATUS_DITOLAK, $komisi->fresh()->status);
        $this->assertEquals('batal', $utang->fresh()->status, 'Status utang harus nilai enum yang valid.');

        // Jurnal akrual komisi (510-01 / 210-03) harus dibalik & ditandai.
        $headerAkrual = JurnalHeader::where('no_jurnal', $noAkrual)
            ->where('cabang_key', $this->cabang->id)->first();
        $this->assertTrue((bool) $headerAkrual->is_reversed, 'Jurnal akrual komisi harus ditandai is_reversed.');

        $saldoUtangKomisi = (float) JurnalAkuntansi::where('cabang_id', $this->cabang->id)
            ->whereHas('akun', fn ($q) => $q->where('kode', '210-03'))
            ->selectRaw('COALESCE(SUM(kredit),0) - COALESCE(SUM(debit),0) AS saldo')
            ->value('saldo');
        $this->assertEquals(0.0, $saldoUtangKomisi, 'GL utang komisi harus nol setelah void (akrual dibalik).');
    }

    public function test_void_ditolak_bila_utang_komisi_sudah_dibayar_sebagian(): void
    {
        $trx = $this->buatTransaksi(['no_transaksi' => 'TRX-VOID-KOMISI-SEBAGIAN']);
        $komisi = $this->buatKomisiDisetujui($trx, 'KMS-VOID-0002');

        $utang = Utang::where('referensi_tipe', 'komisi')->where('referensi_id', $komisi->id)->first();
        $utang->update(['jumlah_dibayar' => 10000, 'status' => 'sebagian']);

        try {
            app(TransaksiVoidService::class)->voidTransaksi($trx, $this->supervisorUser, 'Coba void komisi dibayar sebagian');
            $this->fail('Void seharusnya ditolak karena utang komisi sudah dibayar sebagian.');
        } catch (\Exception $e) {
            $this->assertStringContainsString('komisi', strtolower($e->getMessage()));
        }

        $this->assertEquals('selesai', $trx->fresh()->status);
        $this->assertEquals(Komisi::STATUS_DISETUJUI, $komisi->fresh()->status);
        $this->assertEquals('sebagian', $utang->fresh()->status);
    }
}
