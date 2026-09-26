<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Akunting\Models\AkunCOA;
use App\Modules\Crm\Models\Pelanggan;
use App\Modules\Hr\Models\PayrollPeriode;
use App\Modules\Pos\Models\Transaksi;
use App\Modules\Rbac\Models\Cabang;
use App\Modules\Wms\Models\Grn;
use App\Modules\Wms\Models\Gudang;
use App\Modules\Wms\Models\Produk;
use App\Modules\Wms\Models\PurchaseOrder;
use App\Modules\Wms\Models\StokItem;
use App\Modules\Wms\Models\StokOpname;
use App\Modules\Wms\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * [B-10c] Perintah `ute:rekonsiliasi` — laporan anomali READ-ONLY.
 *
 * Dua skenario utama:
 * (a) data bersih → exit `SUCCESS` (0) dan tiap kategori menampilkan
 *     "tidak ada anomali";
 * (b) data anomali buatan → exit `FAILURE` (1) karena ada kategori KRITIS,
 *     label kategori dan angka bergaya Indonesia (ADR 0011) tampil di output.
 *
 * Ditambah uji tambahan: filter `--kategori`, batas contoh `--limit`, guard
 * kategori asing, dan bukti bahwa tidak ada satupun baris yang berubah.
 */
class RekonsiliasiCommandTest extends TestCase
{
    use RefreshDatabase;

    private Cabang $cabang;

    private Gudang $gudang;

    private Produk $produk;

    private User $user;

    private AkunCOA $kas;

    private AkunCOA $pendapatan;

    private Pelanggan $pelanggan;

    private Supplier $supplier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->kas = AkunCOA::create([
            'kode' => '110-01', 'nama' => 'Kas', 'tipe' => 'aset',
            'kelompok' => 'kas', 'saldo_normal' => 'debit', 'is_active' => true,
        ]);
        $this->pendapatan = AkunCOA::create([
            'kode' => '410-01', 'nama' => 'Pendapatan Penjualan', 'tipe' => 'pendapatan',
            'kelompok' => 'pendapatan_penjualan', 'saldo_normal' => 'kredit', 'is_active' => true,
        ]);

        $this->cabang = Cabang::create([
            'kode' => 'CBG-REC', 'nama' => 'Cabang Rekonsiliasi',
            'alamat' => 'Jl. Rekonsiliasi', 'telepon' => '08123456789', 'is_active' => true,
        ]);

        $this->gudang = Gudang::create([
            'cabang_id' => $this->cabang->id, 'nama' => 'Gudang Rekonsiliasi',
            'kode' => 'GDG-REC'.Str::random(3), 'is_active' => true,
        ]);

        $this->produk = Produk::create([
            'nama' => 'Produk Rekonsiliasi',
            'slug' => 'produk-rekonsiliasi-'.Str::random(5),
            'kategori' => 'LCD / Layar', 'kondisi' => 'oem',
            'harga_beli' => 100000, 'harga_jual_retail' => 150000,
            'is_active' => true, 'sn' => false,
        ]);

        $this->user = User::create([
            'name' => 'Akuntan Rekonsiliasi', 'email' => 'akuntan-rec@test.com',
            'password' => Hash::make('password'), 'is_active' => true,
        ]);

        $this->pelanggan = Pelanggan::create(['nama' => 'Pelanggan Rekonsiliasi']);
        $this->supplier = Supplier::create(['nama' => 'Supplier Rekonsiliasi']);
    }

    public function test_perintah_terdaftar_otomatis_tanpa_daftar_manual(): void
    {
        $this->assertArrayHasKey('ute:rekonsiliasi', Artisan::all());
    }

    public function test_data_bersih_keluar_dengan_exit_success(): void
    {
        $this->siapkanDataBersih();

        $keluar = Artisan::call('ute:rekonsiliasi');
        $keluaran = Artisan::output();

        $this->assertSame(0, $keluar, 'Data bersih harus keluar dengan exit code SUCCESS.');
        $this->assertStringContainsString('tidak ada anomali', $keluaran);
        $this->assertStringContainsString('RINGKASAN', $keluaran);
        $this->assertStringContainsString('TOTAL KESELURUHAN', $keluaran);
        $this->assertStringContainsString('BERSIH', $keluaran);
        $this->assertStringContainsString('Tidak ada anomali KRITIS — exit code 0.', $keluaran);

        // Semua blok kategori wajib muncul, dan tidak boleh ada severity KRITIS.
        foreach (['JURNAL', 'TRANSAKSI', 'PURCHASE ORDER', 'PAYROLL', 'OPNAME', 'PIUTANG / UTANG', 'STOK TRAIL', 'AUDIT', 'MIGRASI TERTUNDA'] as $judul) {
            $this->assertStringContainsString($judul, $keluaran, "Blok kategori {$judul} harus tampil.");
        }
    }

    public function test_data_anomali_keluar_dengan_exit_failure_dan_label_kategori(): void
    {
        $this->siapkanDataAnomali();

        $keluar = Artisan::call('ute:rekonsiliasi');
        $keluaran = Artisan::output();

        $this->assertSame(1, $keluar, 'Adanya kategori KRITIS harus menghasilkan exit code FAILURE.');
        $this->assertStringContainsString('ADA anomali KRITIS', $keluaran);
        $this->assertStringContainsString('KRITIS', $keluaran);

        // Label pemeriksaan per kategori.
        $this->assertStringContainsString('Jurnal tidak balance', $keluaran);
        $this->assertStringContainsString('Nomor jurnal duplikat', $keluaran);
        $this->assertStringContainsString('Jurnal tanpa cabang_id', $keluaran);
        $this->assertStringContainsString('Transaksi final tanpa jurnal', $keluaran);
        $this->assertStringContainsString('Purchase order diterima tanpa jurnal', $keluaran);
        $this->assertStringContainsString('tanpa jurnal (beban gaji tidak tercatat)', $keluaran);
        $this->assertStringContainsString('lebih dari satu jurnal', $keluaran);
        $this->assertStringContainsString('status tidak sinkron', $keluaran);
        $this->assertStringContainsString('Utang orphan', $keluaran);
        $this->assertStringContainsString('Saldo stok_items negatif', $keluaran);
        $this->assertStringContainsString('audit_logs tanpa snapshot', $keluaran);
        $this->assertStringContainsString('activity_log tanpa attribute_changes', $keluaran);

        // Angka gaya Indonesia (ADR 0011): total transaksi 1.500.000.
        $this->assertStringContainsString('Rp 1.500.000', $keluaran);
        $this->assertStringContainsString('1.500.000', $keluaran);

        // Judul blok kategori tetap tampil.
        foreach (['JURNAL', 'TRANSAKSI', 'PURCHASE ORDER', 'PAYROLL', 'OPNAME', 'PIUTANG / UTANG', 'STOK TRAIL', 'AUDIT'] as $judul) {
            $this->assertStringContainsString($judul, $keluaran, "Blok kategori {$judul} harus tampil.");
        }
    }

    public function test_ekspektasi_output_dan_exit_code_melalui_pending_command(): void
    {
        $this->siapkanDataAnomali();

        $this->artisan('ute:rekonsiliasi', ['--kategori' => 'jurnal,po'])
            ->expectsOutputToContain('JURNAL')
            ->expectsOutputToContain('Jurnal tidak balance')
            ->expectsOutputToContain('Purchase order diterima tanpa jurnal')
            ->assertExitCode(1);
    }

    public function test_filter_kategori_hanya_mencetak_kategori_yang_diminta(): void
    {
        $this->siapkanDataAnomali();

        Artisan::call('ute:rekonsiliasi', ['--kategori' => 'jurnal,po']);
        $keluaran = Artisan::output();

        $this->assertStringContainsString('JURNAL', $keluaran);
        $this->assertStringContainsString('PURCHASE ORDER', $keluaran);
        $this->assertStringNotContainsString('PAYROLL (--kategori=payroll)', $keluaran);
        $this->assertStringNotContainsString('MIGRASI TERTUNDA', $keluaran);
    }

    public function test_limit_membatasi_jumlah_contoh_yang_ditetak(): void
    {
        // Empat jurnal tidak balance pada nomor berbeda.
        for ($i = 1; $i <= 4; $i++) {
            $this->buatJurnalTidakBalance('JRL-BURUK-'.$i, 1000, 400);
        }

        Artisan::call('ute:rekonsiliasi', ['--kategori' => 'jurnal', '--limit' => 1]);
        $keluaran = Artisan::output();

        $this->assertStringContainsString('Jurnal tidak balance', $keluaran);
        $this->assertStringContainsString('JRL-BURUK-1', $keluaran);
        $this->assertStringNotContainsString('JRL-BURUK-2', $keluaran);
        $this->assertStringContainsString('(+3 baris lain)', $keluaran);
    }

    public function test_kategori_tidak_dikenal_ditolak(): void
    {
        $keluar = Artisan::call('ute:rekonsiliasi', ['--kategori' => 'jurnal,ngawur']);
        $keluaran = Artisan::output();

        $this->assertSame(1, $keluar);
        $this->assertStringContainsString('Kategori asing: ngawur', $keluaran);
    }

    public function test_perintah_tidak_mengubah_data_satu_pun(): void
    {
        $this->siapkanDataAnomali();

        $sebelum = $this->sidikJariTabel();
        Artisan::call('ute:rekonsiliasi');
        $sesudah = $this->sidikJariTabel();

        $this->assertSame($sebelum, $sesudah, 'ute:rekonsiliasi harus benar-benar READ-ONLY.');
    }

    public function test_pemeriksaan_dilewati_saat_tabel_belum_ada(): void
    {
        // Skema database/migrations yang ada, tapi tabel audit_logs belum pernah dibuat.
        DB::statement('DROP TABLE audit_logs');

        $keluar = Artisan::call('ute:rekonsiliasi', ['--kategori' => 'audit']);
        $keluaran = Artisan::output();

        $this->assertSame(0, $keluar, 'Pemeriksaan yang dilewati tidak boleh jadi error fatal.');
        $this->assertStringContainsString('[dilewati]', $keluaran);
        $this->assertStringContainsString('audit_logs', $keluaran);
    }

    // ------------------------------------------------------------------
    // Fixture
    // ------------------------------------------------------------------

    /**
     * Bangun data yang seluruh kategori harus lolos bersih.
     */
    private function siapkanDataBersih(): void
    {
        // Transaksi final + jurnal yang sealed via referensi_tipe.
        $transaksi = Transaksi::create([
            'no_transaksi' => 'TRX-BERSIH-001', 'cabang_id' => $this->cabang->id,
            'sumber' => 'pos', 'total_akhir' => 1500000, 'jumlah_bayar' => 1500000,
            'metode_bayar' => 'tunai', 'status' => 'selesai',
        ]);
        $this->buatJurnal(
            'JRL-BERSIH-1',
            debit: 1500000,
            kredit: 1500000,
            referensiTipe: Transaksi::class,
            referensiId: $transaksi->id,
        );

        // Purchase order diterima + jurnal penerimaan.
        $po = PurchaseOrder::create([
            'no_po' => 'PO-BERSIH-001', 'supplier_id' => $this->supplier->id,
            'gudang_tujuan_id' => $this->gudang->id, 'status' => 'diterima',
            'metode_bayar' => 'kredit', 'total' => 750000,
        ]);
        $jurnalPo = $this->buatJurnal(
            'JRL-BELI-BERSIH-1',
            debit: 750000,
            kredit: 750000,
            referensiTipe: PurchaseOrder::class,
            referensiId: $po->id,
        );

        // Payroll selesai + jurnal gaji.
        $periode = PayrollPeriode::create([
            'periode' => '2026-08', 'tanggal_mulai' => '2026-08-01',
            'tanggal_selesai' => '2026-08-31', 'status' => 'selesai',
        ]);
        $jurnalPayroll = $this->buatJurnal(
            'JRL-PR-2026-08',
            debit: 3000000,
            kredit: 3000000,
            referensiTipe: PayrollPeriode::class,
            referensiId: $periode->id,
        );

        $karyawanId = DB::table('karyawan')->insertGetId([
            'nik' => 'NIK-BERSIH-'.Str::random(4), 'nama' => 'Karyawan Bersih',
            'jabatan' => 'teknisi', 'cabang_id' => $this->cabang->id,
            'tgl_masuk' => '2024-01-01', 'gaji_pokok' => 3000000,
            'status_aktif' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        // Slip approved dengan jurnal_id terisi → tidak boleh dilaporkan.
        DB::table('payroll_slip')->insert([
            'payroll_periode_id' => $periode->id, 'karyawan_id' => $karyawanId,
            'gaji_pokok' => 3000000, 'total_gaji' => 3000000,
            'jurnal_id' => $jurnalPayroll, 'status' => 'approved',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        // Slip draft: jurnal_id NULL memang normal → tidak boleh dilaporkan.
        DB::table('payroll_slip')->insert([
            'payroll_periode_id' => $periode->id, 'karyawan_id' => $karyawanId,
            'gaji_pokok' => 3000000, 'total_gaji' => 3000000,
            'jurnal_id' => null, 'status' => 'draft',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        // Opname: tepat satu jurnal distinct per referensi.
        $opnameId = StokOpname::create([
            'no_opname' => 'OPN-BERSIH-001', 'gudang_id' => $this->gudang->id,
            'user_id' => $this->user->id, 'status' => 'disetujui',
        ])->id;
        $this->buatJurnal(
            'JRL-OPNAME-BERSIH-1',
            debit: 50000,
            kredit: 50000,
            referensiTipe: StokOpname::class,
            referensiId: $opnameId,
        );

        // Piutang & Utang konsisten.
        DB::table('piutang')->insert([
            'no_piutang' => 'PIUT-BERSIH-001', 'pelanggan_id' => $this->pelanggan->id,
            'cabang_id' => $this->cabang->id, 'jumlah' => 1500000, 'jumlah_dibayar' => 1500000,
            'status' => 'lunas', 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('utang')->insert([
            'no_utang' => 'UTANG-BERSIH-001', 'referensi_tipe' => PurchaseOrder::class,
            'referensi_id' => $po->id, 'kreditor_nama' => 'Supplier Rekonsiliasi',
            'cabang_id' => $this->cabang->id, 'jumlah' => 750000, 'jumlah_dibayar' => 750000,
            'status' => 'lunas', 'created_at' => now(), 'updated_at' => now(),
        ]);

        // Jejak stok berpasangan: satu stok_log + satu stock_mutation_log.
        StokItem::create([
            'produk_id' => $this->produk->id, 'gudang_id' => $this->gudang->id,
            'jumlah' => 8, 'jumlah_minimum' => 2,
        ]);
        $referensiTipe = Transaksi::class;
        $referensiId = $transaksi->id;
        DB::table('stok_log')->insert([
            'gudang_id' => $this->gudang->id, 'produk_id' => $this->produk->id,
            'user_id' => $this->user->id, 'jenis' => 'penjualan',
            'referensi_tipe' => $referensiTipe, 'referensi_id' => $referensiId,
            'jumlah_sebelum' => 10, 'perubahan' => -2, 'jumlah_setelah' => 8,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('stock_mutation_log')->insert([
            'produk_id' => $this->produk->id, 'gudang_id' => $this->gudang->id,
            'delta' => -2, 'sumber' => 'pos', 'referensi_tipe' => $referensiTipe,
            'referensi_id' => $referensiId, 'terjadi_at' => now(),
            'created_at' => now(), 'updated_at' => now(),
        ]);

        // Audit trail + activity log yang lengkap snapshotnya.
        DB::table('audit_logs')->insert([
            'user_id' => $this->user->id, 'entitas' => 'Produk', 'aksi' => 'update',
            'entitas_id' => $this->produk->id, 'deskripsi' => 'Ubah harga jual',
            'sebelum' => json_encode(['harga_jual_retail' => 140000]),
            'sesudah' => json_encode(['harga_jual_retail' => 150000]),
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('activity_log')->insert([
            'log_name' => 'default', 'description' => 'Produk diperbarui',
            'event' => 'updated', 'attribute_changes' => json_encode(['harga_jual_retail' => ['old' => 140000, 'new' => 150000]]),
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    /**
     * Bangun data anomali untuk setiap kategori yang punya temuan KRITIS/SEDANG/RINGAN.
     */
    private function siapkanDataAnomali(): void
    {
        // Jurnal tidak balance (satu baris satu sisi).
        $this->buatJurnalTidakBalance('JRL-BURUK-BALANCE', 1000, 400);

        // Nomor jurnal duplikat dalam satu cabang (tetap balance).
        $this->buatJurnal('JRL-DUP-1', debit: 100, kredit: 100);
        $this->buatJurnal('JRL-DUP-1', debit: 200, kredit: 200);

        // Jurnal tanpa cabang_id.
        $jurnalTanpaCabang = $this->buatJurnal('JRL-NO-CABANG', debit: 100, kredit: 100);
        DB::table('jurnal_akuntansi')->where('id', $jurnalTanpaCabang)->update(['cabang_id' => null]);

        // Jurnal tanpa referensi_tipe / user_id.
        $tanpaRef = $this->buatJurnal('JRL-NO-REF', debit: 100, kredit: 100, tanpaReferensi: true);
        DB::table('jurnal_akuntansi')->where('id', $tanpaRef)->update(['referensi_tipe' => null, 'referensi_id' => null]);
        $jurnalTanpaUser = $this->buatJurnal('JRL-NO-USER', debit: 100, kredit: 100);
        DB::table('jurnal_akuntansi')->where('id', $jurnalTanpaUser)->update(['user_id' => null]);

        // Transaksi final tanpa jurnal.
        Transaksi::create([
            'no_transaksi' => 'TRX-BURUK-001', 'cabang_id' => $this->cabang->id,
            'sumber' => 'pos', 'total_akhir' => 1500000, 'jumlah_bayar' => 1500000,
            'metode_bayar' => 'tunai', 'status' => 'selesai',
        ]);

        // PO diterima tanpa jurnal. GRN milik PO sengaja dibuat supaya jalur
        // pencocokan lewat Grn::class ikut teruji.
        $po = PurchaseOrder::create([
            'no_po' => 'PO-BURUK-001', 'supplier_id' => $this->supplier->id,
            'gudang_tujuan_id' => $this->gudang->id, 'status' => 'diterima',
            'metode_bayar' => 'kredit', 'total' => 2500000,
        ]);
        Grn::create([
            'cabang_id' => $this->cabang->id, 'po_id' => $po->id, 'gudang_id' => $this->gudang->id,
            'user_id' => $this->user->id, 'no_grn' => 'GRN-BURUK-001',
            'total_hpp' => 2500000, 'status' => 'draft', 'item_qty_received' => [],
        ]);

        // Payroll selesai tanpa jurnal + slip nyasar.
        $periode = PayrollPeriode::create([
            'periode' => '2026-07', 'tanggal_mulai' => '2026-07-01',
            'tanggal_selesai' => '2026-07-31', 'status' => 'selesai',
        ]);
        $karyawanId = DB::table('karyawan')->insertGetId([
            'nik' => 'NIK-BURUK-'.Str::random(4), 'nama' => 'Karyawan Bermasalah',
            'jabatan' => 'teknisi', 'cabang_id' => $this->cabang->id,
            'tgl_masuk' => '2024-01-01', 'gaji_pokok' => 4000000,
            'status_aktif' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('payroll_slip')->insert([
            'payroll_periode_id' => $periode->id, 'karyawan_id' => $karyawanId,
            'gaji_pokok' => 4000000, 'total_gaji' => 4000000,
            'jurnal_id' => null, 'status' => 'approved',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        // Opname dengan dua jurnal distinct untuk satu referensi.
        $opnameId = StokOpname::create([
            'no_opname' => 'OPN-BURUK-001', 'gudang_id' => $this->gudang->id,
            'user_id' => $this->user->id, 'status' => 'disetujui',
        ])->id;
        $this->buatJurnal('JRL-OPNAME-BURUK-1', debit: 10000, kredit: 10000, referensiTipe: StokOpname::class, referensiId: $opnameId);
        $this->buatJurnal('JRL-OPNAME-BURUK-2', debit: 20000, kredit: 20000, referensiTipe: StokOpname::class, referensiId: $opnameId);

        // Piutang: status lunas tapi belum lunas + cabang NULL.
        DB::table('piutang')->insert([
            'no_piutang' => 'PIUT-BURUK-001', 'pelanggan_id' => $this->pelanggan->id,
            'cabang_id' => null, 'jumlah' => 1500000, 'jumlah_dibayar' => 500000,
            'status' => 'lunas', 'created_at' => now(), 'updated_at' => now(),
        ]);

        // Utang orphan: referensi_tipe pembelian dengan referensi_id 0.
        DB::table('utang')->insert([
            'no_utang' => 'UTANG-BURUK-001', 'referensi_tipe' => 'pembelian',
            'referensi_id' => 0, 'cabang_id' => $this->cabang->id,
            'jumlah' => 300000, 'jumlah_dibayar' => 0,
            'status' => 'belum_lunas', 'created_at' => now(), 'updated_at' => now(),
        ]);

        // Saldo stok negatif + jejak stok tidak berpasangan.
        StokItem::create([
            'produk_id' => $this->produk->id, 'gudang_id' => $this->gudang->id,
            'jumlah' => -5, 'jumlah_minimum' => 2,
        ]);
        DB::table('stok_log')->insert([
            'gudang_id' => $this->gudang->id, 'produk_id' => $this->produk->id,
            'user_id' => $this->user->id, 'jenis' => 'penjualan',
            'referensi_tipe' => Transaksi::class, 'referensi_id' => 999999,
            'jumlah_sebelum' => 0, 'perubahan' => -7, 'jumlah_setelah' => -7,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        // Audit tanpa snapshot + activity_log tanpa attribute_changes.
        DB::table('audit_logs')->insert([
            'user_id' => $this->user->id, 'entitas' => 'Produk', 'aksi' => 'delete',
            'entitas_id' => $this->produk->id, 'deskripsi' => 'Hapus produk tanpa snapshot',
            'sebelum' => null, 'sesudah' => null,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('activity_log')->insert([
            'log_name' => 'default', 'description' => 'Produk dihapus tanpa attribute_changes',
            'event' => 'deleted', 'attribute_changes' => null,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    /**
     * Post dua baris jurnal double-entry langsung lewat query builder.
     *
     * Sengaja tidak memakai JurnalService supaya fixture bisa memuat jurnal yang
     * tidak balance maupun journal duplikat — justru kondisi yang dicari report.
     */
    private function buatJurnal(
        string $noJurnal,
        int $debit,
        int $kredit,
        ?string $referensiTipe = null,
        ?int $referensiId = null,
        bool $tanpaReferensi = false,
    ): int {
        $waktu = now();

        $id = DB::table('jurnal_akuntansi')->insertGetId([
            'no_jurnal' => $noJurnal, 'tanggal' => $waktu, 'cabang_id' => $this->cabang->id,
            'akun_coa_id' => $this->kas->id, 'sumber' => 'manual', 'deskripsi' => "Jurnal {$noJurnal}",
            'debit' => $debit, 'kredit' => $kredit,
            'referensi_tipe' => $tanpaReferensi ? null : $referensiTipe,
            'referensi_id' => $tanpaReferensi ? null : $referensiId,
            'user_id' => $this->user->id, 'created_at' => $waktu, 'updated_at' => $waktu,
        ]);

        DB::table('jurnal_akuntansi')->insert([
            'no_jurnal' => $noJurnal, 'tanggal' => $waktu, 'cabang_id' => $this->cabang->id,
            'akun_coa_id' => $this->pendapatan->id, 'sumber' => 'manual', 'deskripsi' => "Jurnal {$noJurnal}",
            'debit' => $kredit, 'kredit' => $debit,
            'referensi_tipe' => $tanpaReferensi ? null : $referensiTipe,
            'referensi_id' => $tanpaReferensi ? null : $referensiId,
            'user_id' => $this->user->id, 'created_at' => $waktu, 'updated_at' => $waktu,
        ]);

        return $id;
    }

    /**
     * Post satu baris jurnal yang SENGAJA tidak balance.
     *
     * `buatJurnal()` selalu menulis dua baris cermin sehingga selalu balance;
     * fixture anomali butuh jurnal satu-sisi yang tidak balance.
     */
    private function buatJurnalTidakBalance(string $noJurnal, int $debit, int $kredit): int
    {
        $waktu = now();

        return DB::table('jurnal_akuntansi')->insertGetId([
            'no_jurnal' => $noJurnal, 'tanggal' => $waktu, 'cabang_id' => $this->cabang->id,
            'akun_coa_id' => $this->kas->id, 'sumber' => 'manual', 'deskripsi' => "Jurnal {$noJurnal}",
            'debit' => $debit, 'kredit' => $kredit,
            'referensi_tipe' => null, 'referensi_id' => null,
            'user_id' => $this->user->id, 'created_at' => $waktu, 'updated_at' => $waktu,
        ]);
    }

    /**
     * Sidik jari seluruh tabel relevan untuk membuktikan perintah READ-ONLY.
     *
     * @return array<string, string>
     */
    private function sidikJariTabel(): array
    {
        $tabel = [
            'jurnal_akuntansi', 'transaksi', 'purchase_order', 'grn',
            'payroll_periode', 'payroll_slip', 'piutang', 'utang',
            'stok_log', 'stock_mutation_log', 'stok_items',
            'audit_logs', 'activity_log', 'migrations',
        ];

        $sidik = [];
        foreach ($tabel as $satu) {
            $sidik[$satu] = DB::table($satu)->count().'#'.md5(
                (string) DB::table($satu)->get()->toJson()
            );
        }

        return $sidik;
    }
}
