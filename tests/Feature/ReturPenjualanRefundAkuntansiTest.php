<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Akunting\Models\AkunCOA;
use App\Modules\Akunting\Models\JurnalAkuntansi;
use App\Modules\Akunting\Models\JurnalHeader;
use App\Modules\Akunting\Models\Piutang;
use App\Modules\Crm\Models\Pelanggan;
use App\Modules\Pos\Models\ReturnPenjualan;
use App\Modules\Pos\Models\Transaksi;
use App\Modules\Pos\Models\TransaksiItem;
use App\Modules\Pos\Services\KasSesiState;
use App\Modules\Pos\Services\ReturnPenjualanService;
use App\Modules\Rbac\Models\Cabang;
use App\Modules\Wms\Models\Gudang;
use App\Modules\Wms\Models\Produk;
use App\Modules\Wms\Models\StokItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * [REGRESSION][FINANSIAL] Akuntansi refund retur penjualan (audit H1-H4).
 *
 * H1: refund neto diskon item/header + balik PPN keluaran (Dr 220-01).
 * H2: refund kas kredit Kas Laci (110-04) + mutasi laci 'keluar'; hormati
 *     metode bayar asal (bank/QRIS/split); 'saldo' -> deposit pelanggan.
 * H3: lookup piutang via referensi_tipe+referensi_id (fallback transaksi_id),
 *     increment jumlah_dibayar, decrement Pelanggan.saldo_piutang.
 * H4: barang rusak -> write-down NRV (Dr 520-08 / Cr 510-02), bukan
 *     Dr 130-01 penuh; hanya barang bagus yang restore stok + persediaan.
 */
class ReturPenjualanRefundAkuntansiTest extends TestCase
{
    use RefreshDatabase;

    protected Cabang $cabang;

    protected Gudang $gudang;

    protected Produk $produk;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cabang = Cabang::create(['kode' => 'CAB-RFA', 'nama' => 'Cabang Refund', 'is_pusat' => true, 'status' => 'aktif']);
        $this->gudang = Gudang::create(['kode' => 'GDG-RFA', 'nama' => 'Gudang Refund', 'cabang_id' => $this->cabang->id, 'is_default' => true, 'status' => 'aktif']);
        $this->produk = Produk::create(['kode' => 'PRD-RFA', 'nama' => 'Produk Refund', 'harga_beli' => 100000, 'harga_jual' => 150000, 'status' => 'aktif', 'has_sn' => false, 'satuan' => 'pcs']);

        $coas = [
            ['kode' => '110-01', 'nama' => 'Kas Toko', 'tipe' => 'aset', 'kelompok' => 'kas', 'saldo_normal' => 'debit'],
            ['kode' => '110-02', 'nama' => 'Bank', 'tipe' => 'aset', 'kelompok' => 'bank', 'saldo_normal' => 'debit'],
            ['kode' => '110-04', 'nama' => 'Kas Laci', 'tipe' => 'aset', 'kelompok' => 'kas', 'saldo_normal' => 'debit'],
            ['kode' => '120-01', 'nama' => 'Piutang Usaha', 'tipe' => 'aset', 'kelompok' => 'piutang', 'saldo_normal' => 'debit'],
            ['kode' => '130-01', 'nama' => 'Persediaan Barang Dagang', 'tipe' => 'aset', 'kelompok' => 'persediaan', 'saldo_normal' => 'debit'],
            ['kode' => '210-07', 'nama' => 'Deposit Pelanggan', 'tipe' => 'kewajiban', 'kelompok' => 'utang_lain', 'saldo_normal' => 'kredit'],
            ['kode' => '220-01', 'nama' => 'PPN Keluaran', 'tipe' => 'kewajiban', 'kelompok' => 'pajak', 'saldo_normal' => 'kredit'],
            ['kode' => '410-01', 'nama' => 'Pendapatan Penjualan', 'tipe' => 'pendapatan', 'kelompok' => 'pendapatan_penjualan', 'saldo_normal' => 'kredit'],
            ['kode' => '410-02', 'nama' => 'Retur Penjualan', 'tipe' => 'pendapatan', 'kelompok' => 'retur_penjualan', 'saldo_normal' => 'kredit'],
            ['kode' => '510-02', 'nama' => 'HPP', 'tipe' => 'beban', 'kelompok' => 'hpp', 'saldo_normal' => 'debit'],
            ['kode' => '520-08', 'nama' => 'Selisih Stok (Opname)', 'tipe' => 'beban', 'kelompok' => 'hpp', 'saldo_normal' => 'debit'],
        ];
        foreach ($coas as $coa) {
            AkunCOA::firstOrCreate(['kode' => $coa['kode']], $coa + ['is_active' => true]);
        }

        Permission::firstOrCreate(['name' => 'pos.view', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'pos.create', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'wms.view', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'wms.create', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'approve-workflow', 'guard_name' => 'web']);
        $role = Role::firstOrCreate(['name' => 'superadmin', 'guard_name' => 'web']);
        $this->user = User::factory()->create();
        $this->user->assignRole($role);
        $this->user->givePermissionTo(['pos.view', 'pos.create', 'wms.view', 'wms.create', 'approve-workflow']);
        $this->user->cabangs()->attach($this->cabang->id, ['is_default' => true]);
        session(['cabang_id' => $this->cabang->id]);
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    protected function buatTransaksi(array $attrs = [], array $items = []): Transaksi
    {
        $pelanggan = $attrs['pelanggan'] ?? Pelanggan::create(['nama' => 'Pelanggan RFA', 'no_telepon' => '0812000999', 'tipe' => 'retail']);
        unset($attrs['pelanggan']);

        $transaksi = Transaksi::create(array_merge([
            'no_transaksi' => 'TRX-RFA-'.uniqid(),
            'cabang_id' => $this->cabang->id,
            'gudang_id' => $this->gudang->id,
            'kasir_id' => $this->user->id,
            'pelanggan_id' => $pelanggan->id,
            'subtotal' => 100000,
            'dpp' => 100000,
            'total_akhir' => 100000,
            'jumlah_bayar' => 100000,
            'metode_bayar' => 'tunai',
            'status' => 'selesai',
        ], $attrs));

        if (empty($items)) {
            $items = [['jumlah' => 1, 'harga_satuan' => 100000, 'subtotal' => 100000, 'hpp' => 100000]];
        }
        foreach ($items as $item) {
            TransaksiItem::create(array_merge([
                'transaksi_id' => $transaksi->id,
                'produk_id' => $this->produk->id,
                'satuan' => 'pcs',
                'konversi' => 1,
            ], $item));
        }

        StokItem::firstOrCreate(
            ['gudang_id' => $this->gudang->id, 'produk_id' => $this->produk->id, 'sku_variant_id' => null, 'rak_id' => null],
            ['jumlah' => 10]
        );

        return $transaksi->fresh(['items', 'pelanggan']);
    }

    protected function bukaSesiLaci(float $saldoAwal = 50000): object
    {
        $id = DB::table('kas_sesi')->insertGetId([
            'cabang_id' => $this->cabang->id,
            'user_id' => $this->user->id,
            'saldo_awal' => $saldoAwal,
            'status' => 'buka',
            'dibuka_at' => now()->subMinute(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return DB::table('kas_sesi')->where('id', $id)->first();
    }

    /**
     * Agregasi baris jurnal retur per kode akun: [kode => ['debit'=>x,'kredit'=>y]].
     */
    protected function linesFor(ReturnPenjualan $retur): array
    {
        $rows = JurnalAkuntansi::where('referensi_tipe', ReturnPenjualan::class)
            ->where('referensi_id', $retur->id)
            ->get();
        $out = [];
        foreach ($rows as $r) {
            $kode = AkunCOA::find($r->akun_coa_id)->kode;
            $out[$kode] ??= ['debit' => 0.0, 'kredit' => 0.0];
            $out[$kode]['debit'] += (float) $r->debit;
            $out[$kode]['kredit'] += (float) $r->kredit;
        }

        return $out;
    }

    protected function assertJurnalBalance(ReturnPenjualan $retur): void
    {
        $header = JurnalHeader::where('referensi_tipe', ReturnPenjualan::class)
            ->where('referensi_id', $retur->id)
            ->first();
        $this->assertNotNull($header, 'Jurnal retur harus terbentuk');
        $this->assertEquals((float) $header->total_debit, (float) $header->total_kredit, 'Jurnal retur harus balance');
        $this->assertGreaterThan(0, (float) $header->total_debit);
    }

    // ------------------------------------------------------------------
    // H1 — refund neto diskon + PPN
    // ------------------------------------------------------------------

    public function test_h1_refund_memakai_harga_final_neto_diskon_item(): void
    {
        // harga_satuan 100rb, diskon item 10rb -> harga_final neto 90rb (ditulis checkout).
        $transaksi = $this->buatTransaksi(
            ['subtotal' => 90000, 'dpp' => 90000, 'total_akhir' => 90000, 'jumlah_bayar' => 90000],
            [['jumlah' => 1, 'harga_satuan' => 100000, 'diskon_nominal' => 10000, 'harga_final' => 90000, 'subtotal' => 90000, 'hpp' => 100000]]
        );

        $retur = app(ReturnPenjualanService::class)->buatRetur(
            $transaksi,
            [['transaksi_item_id' => $transaksi->items->first()->id, 'jumlah' => 1]],
            'Salah beli',
            'kas',
            $this->user->id
        );

        $this->assertEquals(90000.0, (float) $retur->jumlah, 'Refund harus neto diskon item (90rb), bukan harga kotor 100rb');
        $this->assertEquals(90000.0, (float) $retur->items->first()->subtotal);
        $this->assertJurnalBalance($retur);
        $lines = $this->linesFor($retur);
        $this->assertEquals(90000.0, $lines['410-02']['debit']);
    }

    public function test_h1_refund_fallback_subtotal_per_jumlah_saat_harga_final_null(): void
    {
        // Data lama: harga_final NULL -> fallback subtotal_item / jumlah (neto diskon item).
        $transaksi = $this->buatTransaksi(
            ['subtotal' => 90000, 'dpp' => 90000, 'total_akhir' => 90000, 'jumlah_bayar' => 90000],
            [['jumlah' => 1, 'harga_satuan' => 100000, 'diskon_nominal' => 10000, 'subtotal' => 90000, 'hpp' => 100000]]
        );

        $retur = app(ReturnPenjualanService::class)->buatRetur(
            $transaksi,
            [['transaksi_item_id' => $transaksi->items->first()->id, 'jumlah' => 1]],
            'Salah beli',
            'kas',
            $this->user->id
        );

        $this->assertEquals(90000.0, (float) $retur->jumlah, 'Fallback neto = subtotal/jumlah = 90rb');
        $this->assertJurnalBalance($retur);
    }

    public function test_h1_refund_alokasi_proporsional_diskon_header(): void
    {
        // 2 item @100rb, diskon header nominal 20rb -> DPP 180rb (rasio neto 0.9).
        $transaksi = $this->buatTransaksi(
            ['subtotal' => 200000, 'diskon_nominal' => 20000, 'dpp' => 180000, 'total_akhir' => 180000, 'jumlah_bayar' => 180000],
            [
                ['jumlah' => 1, 'harga_satuan' => 100000, 'subtotal' => 100000, 'hpp' => 100000],
                ['jumlah' => 1, 'harga_satuan' => 100000, 'subtotal' => 100000, 'hpp' => 100000],
            ]
        );

        $retur = app(ReturnPenjualanService::class)->buatRetur(
            $transaksi,
            [['transaksi_item_id' => $transaksi->items->first()->id, 'jumlah' => 1]],
            'Retur sebagian',
            'kas',
            $this->user->id
        );

        // 100rb x (180rb/200rb) = 90rb.
        $this->assertEquals(90000.0, (float) $retur->jumlah, 'Diskon header harus dialokasikan proporsional ke item retur');
        $item = $retur->items->first();
        $this->assertEquals(90000.0, (float) $item->subtotal);
        $this->assertEquals(90000.0, (float) $item->harga_satuan, 'harga_satuan retur = neto teralokasi (kontrak refund = harga_satuan x jumlah)');
        $this->assertJurnalBalance($retur);
    }

    public function test_h1_refund_membalik_ppn_keluaran(): void
    {
        // DPP 100rb + PPN 11rb = total 111rb.
        $transaksi = $this->buatTransaksi(
            ['subtotal' => 100000, 'dpp' => 100000, 'pajak_nominal' => 11000, 'ppn_nominal' => 11000, 'total_akhir' => 111000, 'jumlah_bayar' => 111000],
            [['jumlah' => 1, 'harga_satuan' => 100000, 'subtotal' => 100000, 'hpp' => 100000]]
        );
        $this->bukaSesiLaci();

        $retur = app(ReturnPenjualanService::class)->buatRetur(
            $transaksi,
            [['transaksi_item_id' => $transaksi->items->first()->id, 'jumlah' => 1]],
            'Retur penuh',
            'kas',
            $this->user->id
        );

        $this->assertEquals(111000.0, (float) $retur->jumlah, 'Refund termasuk PPN yang dulu dibayar pelanggan');
        $this->assertEquals(11000.0, (float) $retur->items->first()->ppn_nominal, 'Porsi PPN retur tersimpan per item');
        $this->assertJurnalBalance($retur);
        $lines = $this->linesFor($retur);
        $this->assertEquals(100000.0, $lines['410-02']['debit'], 'Retur penjualan (DPP) di debit');
        $this->assertEquals(11000.0, $lines['220-01']['debit'], 'PPN keluaran dibalik (Dr 220-01)');
        $this->assertEquals(111000.0, $lines['110-04']['kredit'], 'Kas laci kredit sebesar total refund');
        $this->assertEquals(100000.0, $lines['130-01']['debit']);
        $this->assertEquals(100000.0, $lines['510-02']['kredit']);
    }

    public function test_h1_retur_sebagian_ppn_proporsional(): void
    {
        // 2 item @100rb, PPN 11% = 22rb -> total 222rb. Retur 1 item -> 111rb.
        $transaksi = $this->buatTransaksi(
            ['subtotal' => 200000, 'dpp' => 200000, 'pajak_nominal' => 22000, 'ppn_nominal' => 22000, 'total_akhir' => 222000, 'jumlah_bayar' => 222000],
            [
                ['jumlah' => 1, 'harga_satuan' => 100000, 'subtotal' => 100000, 'hpp' => 100000],
                ['jumlah' => 1, 'harga_satuan' => 100000, 'subtotal' => 100000, 'hpp' => 100000],
            ]
        );

        $retur = app(ReturnPenjualanService::class)->buatRetur(
            $transaksi,
            [['transaksi_item_id' => $transaksi->items->first()->id, 'jumlah' => 1]],
            'Retur sebagian',
            'kas',
            $this->user->id
        );

        $this->assertEquals(111000.0, (float) $retur->jumlah, 'Refund sebagian = DPP item + porsi PPN-nya');
        $this->assertEquals(11000.0, (float) $retur->items->first()->ppn_nominal);
        $this->assertJurnalBalance($retur);
        $lines = $this->linesFor($retur);
        $this->assertEquals(100000.0, $lines['410-02']['debit']);
        $this->assertEquals(11000.0, $lines['220-01']['debit'], 'Hanya porsi PPN item retur yang dibalik');
    }

    // ------------------------------------------------------------------
    // H2 — akun kas refund + drawer
    // ------------------------------------------------------------------

    public function test_h2_refund_tunai_tanpa_sesi_buka_kredit_kas_toko(): void
    {
        // Tidak ada sesi kas buka -> refund tunai tidak mungkin dari laci;
        // kredit Kas Toko (110-01), tanpa mutasi laci.
        $transaksi = $this->buatTransaksi();

        $retur = app(ReturnPenjualanService::class)->buatRetur(
            $transaksi,
            [['transaksi_item_id' => $transaksi->items->first()->id, 'jumlah' => 1]],
            'Retur',
            'kas',
            $this->user->id
        );

        $lines = $this->linesFor($retur);
        $this->assertEquals(100000.0, $lines['110-01']['kredit'] ?? 0, 'Tanpa sesi buka, refund tunai kredit Kas Toko');
        $this->assertArrayNotHasKey('110-04', $lines);
        $this->assertEquals(0, DB::table('kas_mutasi_laci')->count());
        $this->assertJurnalBalance($retur);
    }

    public function test_h2_refund_tunai_kredit_kas_laci_dan_buat_mutasi_keluar(): void
    {
        $sesi = $this->bukaSesiLaci();
        $transaksi = $this->buatTransaksi();

        $retur = app(ReturnPenjualanService::class)->buatRetur(
            $transaksi,
            [['transaksi_item_id' => $transaksi->items->first()->id, 'jumlah' => 1]],
            'Rusak ringan',
            'kas',
            $this->user->id
        );

        $lines = $this->linesFor($retur);
        $this->assertEquals(100000.0, $lines['110-04']['kredit'] ?? 0, 'Refund tunai harus kredit Kas Laci (110-04), bukan Kas Toko');
        $this->assertArrayNotHasKey('110-01', $lines, 'Tidak boleh kredit Kas Toko 110-01 untuk refund laci');

        $mutasi = DB::table('kas_mutasi_laci')->where('kas_sesi_id', $sesi->id)->where('jenis', 'keluar')->first();
        $this->assertNotNull($mutasi, 'Mutasi kas laci keluar harus dibuat agar drawer sinkron');
        $this->assertEquals(100000.0, (float) $mutasi->nominal);
        $this->assertEquals('410-02', $mutasi->akun_lawan_kode);
        $this->assertJurnalBalance($retur);
    }

    public function test_h2_hitung_saldo_sistem_berkurang_setelah_refund(): void
    {
        $sesi = $this->bukaSesiLaci(50000);
        $transaksi = $this->buatTransaksi(); // tunai 100rb, kasir = user sesi

        $state = app(KasSesiState::class);
        $this->assertEquals(150000.0, $state->hitungSaldoSistem($sesi), 'Saldo awal 50rb + penjualan tunai 100rb');

        app(ReturnPenjualanService::class)->buatRetur(
            $transaksi,
            [['transaksi_item_id' => $transaksi->items->first()->id, 'jumlah' => 1]],
            'Retur',
            'kas',
            $this->user->id
        );

        $this->assertEquals(50000.0, $state->hitungSaldoSistem($sesi), 'Refund 100rb harus mengurangi saldo sistem laci');
    }

    public function test_h2_refund_asal_bank_kredit_bank_tanpa_mutasi_laci(): void
    {
        $this->bukaSesiLaci();
        $transaksi = $this->buatTransaksi(['metode_bayar' => 'transfer']);

        $retur = app(ReturnPenjualanService::class)->buatRetur(
            $transaksi,
            [['transaksi_item_id' => $transaksi->items->first()->id, 'jumlah' => 1]],
            'Retur',
            'kas',
            $this->user->id
        );

        $lines = $this->linesFor($retur);
        $this->assertEquals(100000.0, $lines['110-02']['kredit'] ?? 0, 'Refund penjualan bank/QRIS harus kredit Bank (110-02)');
        $this->assertArrayNotHasKey('110-04', $lines);
        $this->assertEquals(0, DB::table('kas_mutasi_laci')->where('jenis', 'keluar')->count(), 'Tidak ada mutasi laci untuk refund non-tunai');
        $this->assertJurnalBalance($retur);
    }

    public function test_h2_refund_asal_split_alokasi_tunai_dan_bank(): void
    {
        $sesi = $this->bukaSesiLaci();
        $transaksi = $this->buatTransaksi([
            'metode_bayar' => 'split',
            'split_detail' => ['tunai' => 60000, 'non_tunai' => 40000],
        ]);

        $retur = app(ReturnPenjualanService::class)->buatRetur(
            $transaksi,
            [['transaksi_item_id' => $transaksi->items->first()->id, 'jumlah' => 1]],
            'Retur',
            'kas',
            $this->user->id
        );

        $lines = $this->linesFor($retur);
        $this->assertEquals(60000.0, $lines['110-04']['kredit'] ?? 0, 'Porsi tunai split refund dari laci');
        $this->assertEquals(40000.0, $lines['110-02']['kredit'] ?? 0, 'Porsi non-tunai split refund dari bank');
        $mutasi = DB::table('kas_mutasi_laci')->where('kas_sesi_id', $sesi->id)->where('jenis', 'keluar')->first();
        $this->assertNotNull($mutasi);
        $this->assertEquals(60000.0, (float) $mutasi->nominal, 'Mutasi laci hanya porsi tunai');
        $this->assertJurnalBalance($retur);
    }

    public function test_h2_refund_saldo_kredit_deposit_pelanggan(): void
    {
        $pelanggan = Pelanggan::create(['nama' => 'Deposit Customer', 'no_telepon' => '0812000777', 'tipe' => 'retail', 'saldo_deposit' => 0]);
        $transaksi = $this->buatTransaksi(['pelanggan' => $pelanggan]);

        $retur = app(ReturnPenjualanService::class)->buatRetur(
            $transaksi,
            [['transaksi_item_id' => $transaksi->items->first()->id, 'jumlah' => 1]],
            'Retur',
            'saldo',
            $this->user->id
        );

        $lines = $this->linesFor($retur);
        $this->assertEquals(100000.0, $lines['210-07']['kredit'] ?? 0, "Refund 'saldo' harus kredit liabilitas Deposit Pelanggan (210-07)");
        $this->assertArrayNotHasKey('110-01', $lines);
        $this->assertArrayNotHasKey('110-04', $lines);
        $this->assertEquals(100000.0, (float) $pelanggan->fresh()->saldo_deposit, 'Saldo deposit pelanggan bertambah');
        $this->assertJurnalBalance($retur);
    }

    // ------------------------------------------------------------------
    // H3 — piutang lookup + saldo_piutang
    // ------------------------------------------------------------------

    public function test_h3_retur_piutang_lookup_referensi_tipe_dan_id(): void
    {
        $pelanggan = Pelanggan::create(['nama' => 'Kasbon Customer', 'no_telepon' => '0812000555', 'tipe' => 'retail', 'saldo_piutang' => 100000, 'max_piutang' => 500000]);
        $transaksi = $this->buatTransaksi(['pelanggan' => $pelanggan, 'metode_bayar' => 'piutang']);

        // Piutang gaya baru: referensi_tipe + referensi_id di-set checkout.
        $piutang = Piutang::create([
            'no_piutang' => 'AR-RFA-0001',
            'pelanggan_id' => $pelanggan->id,
            'transaksi_id' => $transaksi->id,
            'cabang_id' => $this->cabang->id,
            'referensi_tipe' => Transaksi::class,
            'referensi_id' => $transaksi->id,
            'jumlah' => 100000,
            'jumlah_dibayar' => 0,
            'sisa' => 100000,
            'jatuh_tempo' => now()->addDays(30)->toDateString(),
            'status' => 'belum_lunas',
        ]);

        $retur = app(ReturnPenjualanService::class)->buatRetur(
            $transaksi,
            [['transaksi_item_id' => $transaksi->items->first()->id, 'jumlah' => 1]],
            'Retur kasbon',
            'piutang',
            $this->user->id
        );

        $piutang->refresh();
        $this->assertEquals(100000.0, (float) $piutang->jumlah_dibayar, 'jumlah_dibayar piutang harus bertambah sebesar refund');
        $this->assertEquals('lunas', $piutang->status);
        $this->assertEquals(0.0, (float) $piutang->sisa);
        $this->assertEquals(0.0, (float) $pelanggan->fresh()->saldo_piutang, 'saldo_piutang pelanggan harus turun saat retur piutang');

        $lines = $this->linesFor($retur);
        $this->assertEquals(100000.0, $lines['120-01']['kredit'] ?? 0, 'GL Piutang Usaha dikredit');
        $this->assertJurnalBalance($retur);
    }

    public function test_h3_retur_piutang_fallback_lookup_transaksi_id_legacy(): void
    {
        $pelanggan = Pelanggan::create(['nama' => 'Legacy Kasbon', 'no_telepon' => '0812000444', 'tipe' => 'retail', 'saldo_piutang' => 100000, 'max_piutang' => 500000]);
        $transaksi = $this->buatTransaksi(['pelanggan' => $pelanggan, 'metode_bayar' => 'piutang']);

        // Piutang legacy POS: hanya transaksi_id, referensi_tipe NULL.
        $piutang = Piutang::create([
            'no_piutang' => 'AR-RFA-0002',
            'pelanggan_id' => $pelanggan->id,
            'transaksi_id' => $transaksi->id,
            'cabang_id' => $this->cabang->id,
            'jumlah' => 100000,
            'jumlah_dibayar' => 0,
            'sisa' => 100000,
            'jatuh_tempo' => now()->addDays(30)->toDateString(),
            'status' => 'belum_lunas',
        ]);

        $retur = app(ReturnPenjualanService::class)->buatRetur(
            $transaksi,
            [['transaksi_item_id' => $transaksi->items->first()->id, 'jumlah' => 1]],
            'Retur kasbon legacy',
            'piutang',
            $this->user->id
        );

        $piutang->refresh();
        $this->assertEquals(100000.0, (float) $piutang->jumlah_dibayar, 'Lookup fallback transaksi_id harus menemukan piutang legacy');
        $this->assertEquals('lunas', $piutang->status);
        $this->assertEquals(0.0, (float) $pelanggan->fresh()->saldo_piutang);
        $this->assertJurnalBalance($retur);
    }

    public function test_h3_retur_piutang_sebagian_increment_dan_saldo_proporsional(): void
    {
        $pelanggan = Pelanggan::create(['nama' => 'Partial Kasbon', 'no_telepon' => '0812000333', 'tipe' => 'retail', 'saldo_piutang' => 100000, 'max_piutang' => 500000]);
        $transaksi = $this->buatTransaksi(
            ['pelanggan' => $pelanggan, 'metode_bayar' => 'piutang', 'subtotal' => 100000, 'dpp' => 100000, 'total_akhir' => 100000],
            [
                ['jumlah' => 1, 'harga_satuan' => 50000, 'subtotal' => 50000, 'hpp' => 50000],
                ['jumlah' => 1, 'harga_satuan' => 50000, 'subtotal' => 50000, 'hpp' => 50000],
            ]
        );

        $piutang = Piutang::create([
            'no_piutang' => 'AR-RFA-0003',
            'pelanggan_id' => $pelanggan->id,
            'transaksi_id' => $transaksi->id,
            'cabang_id' => $this->cabang->id,
            'referensi_tipe' => Transaksi::class,
            'referensi_id' => $transaksi->id,
            'jumlah' => 100000,
            'jumlah_dibayar' => 0,
            'sisa' => 100000,
            'jatuh_tempo' => now()->addDays(30)->toDateString(),
            'status' => 'belum_lunas',
        ]);

        $retur = app(ReturnPenjualanService::class)->buatRetur(
            $transaksi,
            [['transaksi_item_id' => $transaksi->items->first()->id, 'jumlah' => 1]],
            'Retur sebagian',
            'piutang',
            $this->user->id
        );

        $piutang->refresh();
        $this->assertEquals(50000.0, (float) $piutang->jumlah_dibayar);
        $this->assertEquals('belum_lunas', $piutang->status, 'Retur sebagian tidak melunasi piutang');
        $this->assertEquals(50000.0, (float) $piutang->sisa);
        $this->assertEquals(50000.0, (float) $pelanggan->fresh()->saldo_piutang, 'saldo_piutang turun sebesar porsi retur');
        $this->assertJurnalBalance($retur);
    }

    // ------------------------------------------------------------------
    // H4 — barang rusak write-down NRV
    // ------------------------------------------------------------------

    public function test_h4_retur_rusak_write_down_nrv_tanpa_debit_persediaan(): void
    {
        $transaksi = $this->buatTransaksi(
            ['subtotal' => 200000, 'dpp' => 200000, 'total_akhir' => 200000, 'jumlah_bayar' => 200000],
            [['jumlah' => 2, 'harga_satuan' => 100000, 'subtotal' => 200000, 'hpp' => 100000]]
        );
        $stok = StokItem::where('gudang_id', $this->gudang->id)->where('produk_id', $this->produk->id)->first();

        $retur = app(ReturnPenjualanService::class)->buatRetur(
            $transaksi,
            [['transaksi_item_id' => $transaksi->items->first()->id, 'jumlah' => 2, 'kondisi_barang' => 'rusak']],
            'Cacat produksi',
            'kas',
            $this->user->id
        );

        $this->assertEquals(10, (int) $stok->fresh()->jumlah, 'Barang rusak tidak menambah stok siap jual');
        $this->assertEquals('karantina', $retur->items->first()->status_karantina);

        $lines = $this->linesFor($retur);
        $this->assertArrayNotHasKey('130-01', $lines, 'Barang rusak (NRV=0) tidak boleh debit Persediaan 130-01');
        $this->assertEquals(200000.0, $lines['520-08']['debit'] ?? 0, 'Kerugian write-down NRV diakui di 520-08');
        $this->assertEquals(200000.0, $lines['510-02']['kredit'] ?? 0, 'HPP penjualan dibalik');
        $this->assertEquals(200000.0, $lines['410-02']['debit'] ?? 0);
        $this->assertJurnalBalance($retur);
    }

    public function test_h4_retur_campur_bagus_dan_rusak_jurnal_terpisah(): void
    {
        $transaksi = $this->buatTransaksi(
            ['subtotal' => 200000, 'dpp' => 200000, 'total_akhir' => 200000, 'jumlah_bayar' => 200000],
            [
                ['jumlah' => 1, 'harga_satuan' => 100000, 'subtotal' => 100000, 'hpp' => 100000],
                ['jumlah' => 1, 'harga_satuan' => 100000, 'subtotal' => 100000, 'hpp' => 100000],
            ]
        );
        $stok = StokItem::where('gudang_id', $this->gudang->id)->where('produk_id', $this->produk->id)->first();

        $retur = app(ReturnPenjualanService::class)->buatRetur(
            $transaksi,
            [
                ['transaksi_item_id' => $transaksi->items[0]->id, 'jumlah' => 1, 'kondisi_barang' => 'bagus'],
                ['transaksi_item_id' => $transaksi->items[1]->id, 'jumlah' => 1, 'kondisi_barang' => 'rusak'],
            ],
            'Campur',
            'kas',
            $this->user->id
        );

        $this->assertEquals(11, (int) $stok->fresh()->jumlah, 'Hanya barang bagus yang restore stok (+1)');

        $lines = $this->linesFor($retur);
        $this->assertEquals(100000.0, $lines['130-01']['debit'] ?? 0, 'Persediaan hanya untuk barang bagus');
        $this->assertEquals(100000.0, $lines['520-08']['debit'] ?? 0, 'Write-down hanya untuk barang rusak');
        $this->assertEquals(200000.0, $lines['510-02']['kredit'] ?? 0, 'HPP dibalik penuh (bagus + rusak)');
        $this->assertEquals(200000.0, $lines['410-02']['debit'] ?? 0);
        $this->assertJurnalBalance($retur);
    }
}
