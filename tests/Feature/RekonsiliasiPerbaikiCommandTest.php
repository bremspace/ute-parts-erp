<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Akunting\Models\AkunCOA;
use App\Modules\Crm\Models\Pelanggan;
use App\Modules\Hr\Models\PayrollPeriode;
use App\Modules\Rbac\Models\Cabang;
use App\Modules\Wms\Models\Grn;
use App\Modules\Wms\Models\Gudang;
use App\Modules\Wms\Models\Produk;
use App\Modules\Wms\Models\PurchaseOrder;
use App\Modules\Wms\Models\StokOpname;
use App\Modules\Wms\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * [B-13] Perintah `ute:rekonsiliasi-perbaiki` — dry-run default + 2 kategori aman.
 *
 * Kontrak yang dijaga test ini:
 * 1. Tanpa `--jalankan`/`--apply` TIDAK ADA satu baris pun yang berubah —
 *    dibuktikan lewat sidik jari (count + md5 isi) seluruh tabel relevan,
 *    sama pola yang dipakai `RekonsiliasiCommandTest`.
 * 2. `--jalankan` tanpa `--yes` → konfirmasi tidak bisa dibaca (input tidak
 *    interaktif di bawah test) sehingga penulisan DIBATALKAN, exit 1.
 * 3. `--jalankan --yes` hanya mengubah 2 kategori aman:
 *    `piutang-utang-cabang` (isi cabang_id) dan `utang-orphan` (tandai
 *    keterangan, tanpa menghapus baris dan tanpa mengubah referensi_id).
 * 4. Tiga kategori "hanya laporan" (PO / payroll / opname ganda) TIDAK PERNAH
 *    menulis apa pun, bahkan dengan `--jalankan --yes` — jurnal historis tidak
 *    boleh dibuat otomatis.
 * 5. Idempoten: dijalankan dua kali, putaran kedua mengubah 0 baris dan
 *    sidik jari tidak bergeser.
 */
class RekonsiliasiPerbaikiCommandTest extends TestCase
{
    use RefreshDatabase;

    private const PENANDA = '[REKONSILIASI ute:rekonsiliasi-perbaiki]';

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
            'kode' => 'CBG-PERB', 'nama' => 'Cabang Perbaiki',
            'alamat' => 'Jl. Perbaiki', 'telepon' => '08123456789', 'is_active' => true,
        ]);

        $this->gudang = Gudang::create([
            'cabang_id' => $this->cabang->id, 'nama' => 'Gudang Perbaiki',
            'kode' => 'GDG-PERB'.Str::random(3), 'is_active' => true,
        ]);

        $this->produk = Produk::create([
            'nama' => 'Produk Perbaiki',
            'slug' => 'produk-perbaiki-'.Str::random(5),
            'kategori' => 'LCD / Layar', 'kondisi' => 'oem',
            'harga_beli' => 100000, 'harga_jual_retail' => 150000,
            'is_active' => true, 'sn' => false,
        ]);

        $this->user = User::create([
            'name' => 'Akuntan Perbaiki', 'email' => 'akuntan-perb@test.com',
            'password' => Hash::make('password'), 'is_active' => true,
        ]);

        $this->pelanggan = Pelanggan::create(['nama' => 'Pelanggan Perbaiki']);
        $this->supplier = Supplier::create(['nama' => 'Supplier Perbaiki']);
    }

    public function test_perintah_terdaftar_otomatis_tanpa_daftar_manual(): void
    {
        $this->assertArrayHasKey('ute:rekonsiliasi-perbaiki', Artisan::all());
    }

    public function test_dry_run_tidak_mengubah_data_satu_pun(): void
    {
        $this->siapkanAnomali();

        $sebelum = $this->sidikJari();
        $keluar = Artisan::call('ute:rekonsiliasi-perbaiki');
        $sesudah = $this->sidikJari();

        $this->assertSame($sebelum, $sesudah, 'ute:rekonsiliasi-perbaiki tanpa --jalankan harus benar-benar dry-run.');
    }

    public function test_dry_run_menampilkan_rencana_tanpa_konfirmasi(): void
    {
        $this->siapkanAnomali();

        Artisan::call('ute:rekonsiliasi-perbaiki');
        $keluaran = Artisan::output();

        $this->assertStringContainsString('DRY-RUN', $keluaran);
        $this->assertStringContainsString('RENCANA', $keluaran);
        $this->assertStringContainsString('Akan mengubah 5 baris', $keluaran);
        $this->assertStringContainsString('Tambahkan --jalankan', $keluaran);
        $this->assertStringContainsString('RINGKASAN', $keluaran);
        $this->assertStringContainsString('TOTAL KESELURUHAN', $keluaran);
        $this->assertStringContainsString('tidak pernah menghapus baris', $keluaran);
    }

    public function test_rencana_menghitung_baris_dan_angka_gaya_indonesia(): void
    {
        $this->siapkanAnomali();

        Artisan::call('ute:rekonsiliasi-perbaiki', ['--kategori' => 'po-terima-tanpa-jurnal,payroll-tanpa-jurnal']);
        $keluaran = Artisan::output();

        // 1 PO + 1 periode payroll, angka gaya Indonesia (ADR 0011).
        $this->assertStringContainsString('Rp 7.500.000', $keluaran);
        $this->assertStringContainsString('Rp 54.500.000', $keluaran);
        $this->assertStringContainsString('INT-PO-20260924-0001', $keluaran);
        $this->assertStringContainsString('2026-09', $keluaran);
        $this->assertStringContainsString('Karyawan Perbaiki', $keluaran);
    }

    public function test_kategori_tidak_dikenal_ditolak(): void
    {
        $keluar = Artisan::call('ute:rekonsiliasi-perbaiki', ['--kategori' => 'utang-orphan,ngawur']);
        $keluaran = Artisan::output();

        $this->assertSame(1, $keluar);
        $this->assertStringContainsString('Kategori asing: ngawur', $keluaran);
    }

    public function test_filter_kategori_hanya_mencetak_kategori_yang_diminta(): void
    {
        $this->siapkanAnomali();

        Artisan::call('ute:rekonsiliasi-perbaiki', ['--kategori' => 'utang-orphan']);
        $keluaran = Artisan::output();

        $this->assertStringContainsString('UTANG ORPHAN', $keluaran);
        $this->assertStringNotContainsString('OPNAME DENGAN LEBIH DARI SATU JURNAL', $keluaran);
        $this->assertStringNotContainsString('PAYROLL SELESAI TANPA JURNAL', $keluaran);
    }

    public function test_limit_membatasi_jumlah_contoh_yang_ditetak(): void
    {
        $this->siapkanAnomali();

        Artisan::call('ute:rekonsiliasi-perbaiki', ['--kategori' => 'piutang-utang-cabang', '--limit' => 1]);
        $keluaran = Artisan::output();

        $this->assertStringContainsString('PIUT-BURUK-001', $keluaran);
        $this->assertStringNotContainsString('PIUT-BURUK-002', $keluaran);
        $this->assertStringContainsString('(+3 temuan lain)', $keluaran);
    }

    public function test_jalankan_tanpa_yes_dibatalkan_dan_tidak_menulis(): void
    {
        $this->siapkanAnomali();

        $sebelum = $this->sidikJari();
        $keluar = Artisan::call('ute:rekonsiliasi-perbaiki', ['--jalankan' => true]);
        $keluaran = Artisan::output();
        $sesudah = $this->sidikJari();

        $this->assertSame(1, $keluar, 'Penulisan tanpa konfirmasi harus keluar dengan FAILURE.');
        $this->assertStringContainsString('DIBATALKAN', $keluaran);
        $this->assertStringContainsString('--yes', $keluaran);
        $this->assertSame($sebelum, $sesudah, 'Penulisan yang dibatalkan tidak boleh mengubah data.');
        $this->assertNull(DB::table('piutang')->where('no_piutang', 'PIUT-BURUK-001')->value('cabang_id'));
    }

    public function test_jalankan_yes_mengisi_cabang_id_piutang_dan_utang(): void
    {
        $this->siapkanAnomali();

        $keluar = Artisan::call('ute:rekonsiliasi-perbaiki', [
            '--kategori' => 'piutang-utang-cabang', '--jalankan' => true, '--yes' => true,
        ]);
        $keluaran = Artisan::output();

        $this->assertSame(0, $keluar, 'Hanya kategori aman yang tersisa → exit code SUCCESS.');
        $this->assertStringContainsString('4 baris diperbaiki', $keluaran);
        $this->assertStringContainsString('piutang: 3 baris diisi cabang_id', $keluaran);
        $this->assertStringContainsString('utang: 1 baris diisi cabang_id', $keluaran);

        $this->assertSame(0, DB::table('piutang')->whereNull('cabang_id')->count());
        $this->assertSame(0, DB::table('utang')->whereNull('cabang_id')->count());
        $this->assertSame(
            $this->cabang->id,
            (int) DB::table('piutang')->where('no_piutang', 'PIUT-BURUK-001')->value('cabang_id')
        );

        // Kolom lain tidak boleh tersentuh (identik dengan migrasi 2026_09_25_000100).
        $piutang = DB::table('piutang')->where('no_piutang', 'PIUT-BURUK-001')->first();
        $this->assertSame(500000.0, (float) $piutang->jumlah);
        $this->assertSame('belum_lunas', $piutang->status);
    }

    public function test_jalankan_yes_menandai_utang_orphan_tanpa_menghapus_atau_mengubah_referensi(): void
    {
        $this->siapkanAnomali();

        $jumlahUtangSebelum = DB::table('utang')->count();
        $sebelum = $this->sidikJari(['utang']);

        $keluar = Artisan::call('ute:rekonsiliasi-perbaiki', [
            '--kategori' => 'utang-orphan', '--jalankan' => true, '--yes' => true,
        ]);
        $keluaran = Artisan::output();

        $this->assertSame(0, $keluar);
        $this->assertStringContainsString('1 baris diperbaiki', $keluaran);
        $this->assertStringContainsString('tidak dihapus, referensi_id tidak diubah', $keluaran);

        $baris = DB::table('utang')->where('no_utang', 'UTANG-BURUK-001')->first();

        $this->assertStringContainsString(self::PENANDA, (string) $baris->keterangan);
        $this->assertStringContainsString('Utang orphan', (string) $baris->keterangan, 'Keterangan lama harus dipertahankan.');
        $this->assertSame(0, (int) $baris->referensi_id, 'referensi_id tidak boleh diubah — kolomnya NOT NULL.');
        $this->assertSame('pembelian', $baris->referensi_tipe);
        $this->assertSame($jumlahUtangSebelum, DB::table('utang')->count(), 'Tidak boleh ada baris utang yang dihapus/tambah.');
        $this->assertNotSame('', $sebelum['utang']);
    }

    public function test_idempoten_dijalankan_berulang(): void
    {
        $this->siapkanAnomali();

        Artisan::call('ute:rekonsiliasi-perbaiki', ['--kategori' => 'piutang-utang-cabang,utang-orphan', '--jalankan' => true, '--yes' => true]);
        $setelahPertama = $this->sidikJari();

        $keluar = Artisan::call('ute:rekonsiliasi-perbaiki', ['--kategori' => 'piutang-utang-cabang,utang-orphan', '--jalankan' => true, '--yes' => true]);
        $keluaran = Artisan::output();
        $setelahKedua = $this->sidikJari();

        $this->assertSame(0, $keluar);
        $this->assertStringContainsString('0 baris diperbaiki', $keluaran);
        $this->assertStringNotContainsString('Akan mengubah', $keluaran);
        $this->assertSame($setelahPertama, $setelahKedua, 'Putaran kedua tidak boleh mengubah apa pun.');

        // Penanda tidak boleh ditumpuk.
        $keterangan = (string) DB::table('utang')->where('no_utang', 'UTANG-BURUK-001')->value('keterangan');
        $this->assertSame(1, substr_count($keterangan, self::PENANDA));
    }

    public function test_kategori_laporan_tidak_pernah_menulis_jurnal(): void
    {
        $this->siapkanAnomali();

        $jurnalSebelum = DB::table('jurnal_akuntansi')->count();
        $sebelum = $this->sidikJari();

        $keluar = Artisan::call('ute:rekonsiliasi-perbaiki', [
            '--kategori' => 'po-terima-tanpa-jurnal,payroll-tanpa-jurnal,opname-jurnal-ganda',
            '--jalankan' => true, '--yes' => true,
        ]);
        $keluaran = Artisan::output();
        $sesudah = $this->sidikJari();

        $this->assertSame(1, $keluar, 'Temuan manual harus keluar dengan exit code 1.');
        $this->assertStringContainsString('HANYA LAPORAN', $keluaran);
        $this->assertStringContainsString('tidak pernah membuat jurnal akuntansi secara otomatis', $keluaran);
        $this->assertSame($jurnalSebelum, DB::table('jurnal_akuntansi')->count(), 'Tidak boleh ada jurnal baru.');
        $this->assertSame($sebelum, $sesudah, 'Kategori laporan tidak boleh menulis apa pun.');

        // PO tetap tanpa jurnal, slip payroll tetap NULL.
        $this->assertSame(0, DB::table('jurnal_akuntansi')
            ->where('referensi_tipe', PurchaseOrder::class)->where('referensi_id', 1)->count());
        $this->assertSame(1, DB::table('payroll_slip')->whereNull('jurnal_id')->count());
    }

    public function test_utang_orphan_yang_referensinya_hilang_hanya_dilaporan(): void
    {
        $this->siapkanAnomali();
        // Tambahkan utang yang menunjuk GRN yang tidak ada.
        DB::table('utang')->insert([
            'no_utang' => 'UTANG-HILANG-001', 'referensi_tipe' => Grn::class,
            'referensi_id' => 987654, 'cabang_id' => $this->cabang->id,
            'jumlah' => 900000, 'jumlah_dibayar' => 0, 'status' => 'belum_lunas',
            'keterangan' => 'Utang dengan GRN yang tidak ada',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        Artisan::call('ute:rekonsiliasi-perbaiki', ['--kategori' => 'utang-orphan', '--jalankan' => true, '--yes' => true]);
        $keluaran = Artisan::output();

        $this->assertStringContainsString('UTANG-HILANG-001', $keluaran);
        $this->assertStringContainsString('grn #987.654 tidak ada', $keluaran);
        $this->assertStringContainsString('[hanya laporan]', $keluaran);
        $this->assertStringNotContainsString(
            self::PENANDA,
            (string) DB::table('utang')->where('no_utang', 'UTANG-HILANG-001')->value('keterangan')
        );
    }

    public function test_utang_dengan_tipe_referensi_tidak_dikenal_hanya_dilaporan(): void
    {
        $this->siapkanAnomali();
        DB::table('utang')->insert([
            'no_utang' => 'UTANG-ANEH-001', 'referensi_tipe' => 'entah',
            'referensi_id' => 5, 'cabang_id' => $this->cabang->id,
            'jumlah' => 700000, 'jumlah_dibayar' => 0, 'status' => 'belum_lunas',
            'keterangan' => null, 'created_at' => now(), 'updated_at' => now(),
        ]);

        Artisan::call('ute:rekonsiliasi-perbaiki', ['--kategori' => 'utang-orphan', '--jalankan' => true, '--yes' => true]);
        $keluaran = Artisan::output();

        $this->assertStringContainsString('tipe referensi "entah" tidak dikenal', $keluaran);
        $this->assertNull(DB::table('utang')->where('no_utang', 'UTANG-ANEH-001')->value('keterangan'));
    }

    public function test_kategori_cabang_dilewati_bila_belum_ada_cabang(): void
    {
        $this->siapkanAnomali();

        // Buang cabang beserta seluruh baris yang merujuknya supaya benar-benar
        // tidak ada cabang. FK dimatikan sebentar karena fixture punya PO/GRN/
        // opname/karyawan yang menunjuk gudang & cabang.
        Schema::disableForeignKeyConstraints();
        DB::table('stok_opname')->delete();
        DB::table('grn')->delete();
        DB::table('purchase_order')->delete();
        DB::table('karyawan')->delete();
        DB::table('gudang')->delete();
        DB::table('cabang')->delete();
        Schema::enableForeignKeyConstraints();

        Artisan::call('ute:rekonsiliasi-perbaiki', ['--kategori' => 'piutang-utang-cabang', '--jalankan' => true, '--yes' => true]);
        $keluaran = Artisan::output();

        $this->assertStringContainsString('[dilewati]', $keluaran);
        $this->assertStringContainsString('belum ada cabang', $keluaran);
        $this->assertNull(DB::table('piutang')->where('no_piutang', 'PIUT-BURUK-001')->value('cabang_id'));
    }

    public function test_po_yang_sudah_punya_jurnal_tidak_dilaporkan(): void
    {
        $this->siapkanAnomali();

        // PO kedua (yang ada GRN-nya) dibuat agar punya jurnal lewat GRN.
        $grnId = DB::table('grn')->where('no_grn', 'GRN-BURUK-001')->value('id');
        $waktu = now();
        DB::table('jurnal_akuntansi')->insert([
            'no_jurnal' => 'JRL-GRN-BURUK-1', 'tanggal' => $waktu, 'cabang_id' => $this->cabang->id,
            'akun_coa_id' => $this->kas->id, 'sumber' => 'pembelian', 'deskripsi' => 'Jurnal GRN',
            'debit' => 1000, 'kredit' => 0, 'referensi_tipe' => Grn::class, 'referensi_id' => $grnId,
            'user_id' => $this->user->id, 'created_at' => $waktu, 'updated_at' => $waktu,
        ]);

        Artisan::call('ute:rekonsiliasi-perbaiki', ['--kategori' => 'po-terima-tanpa-jurnal']);
        $keluaran = Artisan::output();

        $this->assertStringContainsString('INT-PO-20260924-0001', $keluaran);
        $this->assertStringNotContainsString('PO-BURUK-GRN', $keluaran);
    }

    public function test_data_bersih_keluar_dengan_exit_success(): void
    {
        // Tidak ada anomali sama sekali: semua tabel sudah kosong, cabang ada.
        Artisan::call('ute:rekonsiliasi-perbaiki');
        $keluaran = Artisan::output();
        $keluar = Artisan::call('ute:rekonsiliasi-perbaiki');

        $this->assertSame(0, $keluar);
        $this->assertStringContainsString('tidak ada anomali', $keluaran);
        $this->assertStringContainsString('Tidak ada temuan manual — exit code 0.', $keluaran);
        $this->assertStringContainsString('Tidak ada baris yang perlu ditulis ulang', $keluaran);
    }

    public function test_pemeriksaan_dilewati_saat_tabel_belum_ada(): void
    {
        DB::statement('DROP TABLE grn');

        $keluar = Artisan::call('ute:rekonsiliasi-perbaiki', ['--kategori' => 'po-terima-tanpa-jurnal']);
        $keluaran = Artisan::output();

        $this->assertSame(0, $keluar, 'Tabel yang hilang tidak boleh jadi error fatal.');
        $this->assertStringContainsString('tidak ada anomali', $keluaran);
    }

    // ------------------------------------------------------------------
    // Fixture
    // ------------------------------------------------------------------

    /**
     * Bangun satu anomali per kategori:
     * 3 piutang + 1 utang tanpa `cabang_id` (4 baris, kategori a);
     * 1 utang orphan `referensi_id = 0` (kategori b);
     * 1 PO `diterima` tanpa jurnal; 1 periode payroll `selesai` tanpa jurnal
     * dengan 1 slip; 1 opname dengan 2 nomor jurnal.
     *
     * Utang kategori (a) sengaja diberi referensi yang VALID supaya tidak
     * sekaligus terhitung sebagai orphan — jumlah tiap kategori jadi tak
     * tumpang tindih dan mudah diaudit.
     */
    private function siapkanAnomali(): void
    {
        // (c) PO lebih dulu: id-nya dipakai utang kategori (a).
        // PO-1 `diterima` tanpa jurnal sama sekali, PO-2 punya GRN.
        PurchaseOrder::create([
            'no_po' => 'INT-PO-20260924-0001', 'supplier_id' => $this->supplier->id,
            'gudang_tujuan_id' => $this->gudang->id, 'status' => 'diterima',
            'metode_bayar' => 'kredit', 'total' => 7500000,
        ]);
        $poGrn = PurchaseOrder::create([
            'no_po' => 'PO-BURUK-GRN', 'supplier_id' => $this->supplier->id,
            'gudang_tujuan_id' => $this->gudang->id, 'status' => 'diterima',
            'metode_bayar' => 'kredit', 'total' => 2500000,
        ]);
        Grn::create([
            'cabang_id' => $this->cabang->id, 'po_id' => $poGrn->id, 'gudang_id' => $this->gudang->id,
            'user_id' => $this->user->id, 'no_grn' => 'GRN-BURUK-001',
            'total_hpp' => 2500000, 'status' => 'draft', 'item_qty_received' => [],
        ]);

        // (a) piutang & utang tanpa cabang_id.
        foreach ([1, 2, 3] as $urut) {
            DB::table('piutang')->insert([
                'no_piutang' => 'PIUT-BURUK-00'.$urut, 'pelanggan_id' => $this->pelanggan->id,
                'cabang_id' => null, 'jumlah' => 500000 * $urut, 'jumlah_dibayar' => 0,
                'status' => 'belum_lunas', 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        DB::table('utang')->insert([
            'no_utang' => 'UTANG-CABANG-001', 'referensi_tipe' => 'pembelian',
            'referensi_id' => $poGrn->id, 'cabang_id' => null, 'kreditor_nama' => 'Supplier Perbaiki',
            'jumlah' => 750000, 'jumlah_dibayar' => 0, 'status' => 'belum_lunas',
            'keterangan' => 'Utang tanpa cabang', 'created_at' => now(), 'updated_at' => now(),
        ]);

        // (b) Utang orphan: referensi_id = 0.
        DB::table('utang')->insert([
            'no_utang' => 'UTANG-BURUK-001', 'referensi_tipe' => 'pembelian', 'referensi_id' => 0,
            'cabang_id' => $this->cabang->id, 'kreditor_nama' => 'Supplier Perbaiki',
            'jumlah' => 300000, 'jumlah_dibayar' => 0, 'status' => 'belum_lunas',
            'keterangan' => 'Utang orphan', 'created_at' => now(), 'updated_at' => now(),
        ]);

        // (d) Periode payroll `selesai` tanpa jurnal + 1 slip nyasar.
        $periode = PayrollPeriode::create([
            'periode' => '2026-09', 'tanggal_mulai' => '2026-09-01',
            'tanggal_selesai' => '2026-09-30', 'status' => 'selesai',
        ]);
        $karyawanId = DB::table('karyawan')->insertGetId([
            'nik' => 'NIK-PERB-'.Str::random(4), 'nama' => 'Karyawan Perbaiki',
            'jabatan' => 'teknisi', 'cabang_id' => $this->cabang->id,
            'tgl_masuk' => '2024-01-01', 'gaji_pokok' => 54500000,
            'status_aktif' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('payroll_slip')->insert([
            'payroll_periode_id' => $periode->id, 'karyawan_id' => $karyawanId,
            'gaji_pokok' => 54500000, 'total_gaji' => 54500000,
            'jurnal_id' => null, 'status' => 'disetujui',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        // (e) Opname dengan dua nomor jurnal distinct.
        $opnameId = StokOpname::create([
            'no_opname' => 'OPN-BURUK-001', 'gudang_id' => $this->gudang->id,
            'user_id' => $this->user->id, 'status' => 'disetujui',
        ])->id;
        $this->buatJurnal('JRL-OPNAME-BURUK-1', 10000, 10000, StokOpname::class, $opnameId);
        $this->buatJurnal('JRL-OPNAME-BURUK-2', 20000, 20000, StokOpname::class, $opnameId);
    }

    /**
     * Post jurnal double-entry lewat query builder.
     */
    private function buatJurnal(string $noJurnal, int $debit, int $kredit, ?string $referensiTipe = null, ?int $referensiId = null): void
    {
        $waktu = now();

        foreach ([[$this->kas->id, $debit, $kredit], [$this->pendapatan->id, $kredit, $debit]] as [$akun, $d, $k]) {
            DB::table('jurnal_akuntansi')->insert([
                'no_jurnal' => $noJurnal, 'tanggal' => $waktu, 'cabang_id' => $this->cabang->id,
                'akun_coa_id' => $akun, 'sumber' => 'opname', 'deskripsi' => "Jurnal {$noJurnal}",
                'debit' => $d, 'kredit' => $k,
                'referensi_tipe' => $referensiTipe, 'referensi_id' => $referensiId,
                'user_id' => $this->user->id, 'created_at' => $waktu, 'updated_at' => $waktu,
            ]);
        }
    }

    /**
     * Sidik jari seluruh tabel relevan — count + md5 isi.
     *
     * @param  array<int, string>|null  $hanya  batasi ke tabel tertentu.
     * @return array<string, string>
     */
    private function sidikJari(?array $hanya = null): array
    {
        $tabel = $hanya ?? [
            'jurnal_akuntansi', 'purchase_order', 'grn', 'stok_opname',
            'payroll_periode', 'payroll_slip', 'piutang', 'utang',
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
