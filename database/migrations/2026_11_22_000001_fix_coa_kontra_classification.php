<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * [F-06/F-07/F-08] Koreksi klasifikasi akun kontra + flag is_kontra.
     *
     * F-06: 410-02 Retur Penjualan = kontra-pendapatan → saldo normal DEBIT
     *       (PSAK 72/IFRS 15). Deklarasi lama 'kredit' adalah hack netting:
     *       laporan "kebetulan benar" karena groupAkun menghitung kredit−debit,
     *       sementara jurnal manual debit yang benar DITOLAK aturan sisi.
     * F-07: 130-02 Akumulasi Depresiasi = kontra-aset → saldo normal KREDIT
     *       (PSAK 16/IAS 16). Deklarasi lama 'debit' adalah hack netting yang
     *       sama (neraca menjumlah saldo per tipe; debit-normal membuat saldo
     *       negatif mengurangi aset).
     * F-08: kolom is_kontra = penanda eksplisit akun kontra; dipakai netting
     *       laporan (ExportLaporanService::saldoLaporan) dan validasi CRUD
     *       (konsistensi tipe ↔ saldo_normal; penyimpangan hanya boleh bila
     *       is_kontra=true).
     *
     * KESELAMATAN LAPORAN: netting kontra di ExportLaporanService sadar-flag
     * dan INVARIAN terhadap deklarasi saldo_normal (sisi natural kontra
     * diturunkan dari tipe, bukan deklarasi) — angka neraca/laba rugi/trial
     * balance/arus kas IDENTIK sebelum & sesudah koreksi (dibuktikan test
     * AkunCoaKlasifikasiKontraTest). Koreksi deklarasi memakai raw DB dengan
     * sengaja mem-bypass guard F-09 (Eloquent) karena ini koreksi data
     * terencana, bukan reklasifikasi retroaktif diam-diam: penyajian laporan
     * tidak berubah, hanya semantik deklarasi + aturan sisi jurnal manual
     * yang menjadi benar.
     *
     * Kode 130-02 TIDAK dipindah ke 160-02 (rekomendasi audit F-07): rename
     * kode akun ber-jurnal melanggar immutability baris jurnal & guard F-09;
     * reklasifikasi kode butuh jurnal reklasifikasi terpisah (di luar scope).
     * Penyajian netterhadap aset tetap dijamin flag is_kontra + netting laporan.
     *
     * [F-08] Akun liar dorman 907024506 "KAS BCA" (tipe beban, kelompok STOK,
     * 0 baris jurnal, dibuat di luar aplikasi): dinonaktifkan bila ada dan
     * tidak ber-jurnal. Akun ber-jurnal TIDAK disentuh (no retroactive rewrite).
     */
    public function up(): void
    {
        if (! Schema::hasColumn('akun_coa', 'is_kontra')) {
            Schema::table('akun_coa', function (Blueprint $table) {
                $table->boolean('is_kontra')->default(false)->after('saldo_normal');
            });
        }

        // Koreksi deklarasi (raw DB — bypass guard F-09 secara sengaja;
        // laporan invarian, lihat docblock & test invariansi).
        DB::table('akun_coa')->where('kode', '410-02')->update([
            'saldo_normal' => 'debit',
            'is_kontra' => true,
        ]);
        DB::table('akun_coa')->where('kode', '130-02')->update([
            'saldo_normal' => 'kredit',
            'is_kontra' => true,
        ]);

        // Nonaktifkan akun liar dorman (guarded: hanya bila tanpa jurnal).
        $liar = DB::table('akun_coa')->where('kode', '907024506')->first();
        if ($liar && ! DB::table('jurnal_akuntansi')->where('akun_coa_id', $liar->id)->exists()) {
            DB::table('akun_coa')->where('id', $liar->id)->update(['is_active' => false]);
        }
    }

    public function down(): void
    {
        // Kembalikan deklarasi hack lama (laporan tetap balance — netting
        // invarian). Akun liar yang dinonaktifkan TIDAK diaktifkan kembali
        // (deaktivasi adalah koreksi data aman; mengaktifkan kembali akun
        // salah klasifikasi justru berbahaya).
        DB::table('akun_coa')->where('kode', '410-02')->update([
            'saldo_normal' => 'kredit',
            'is_kontra' => false,
        ]);
        DB::table('akun_coa')->where('kode', '130-02')->update([
            'saldo_normal' => 'debit',
            'is_kontra' => false,
        ]);

        if (Schema::hasColumn('akun_coa', 'is_kontra')) {
            Schema::table('akun_coa', function (Blueprint $table) {
                $table->dropColumn('is_kontra');
            });
        }
    }
};
