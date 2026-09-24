<?php

use App\Modules\Akunting\Models\AkunCOA;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * [F3-3] Register Aset Tetap + COA depresiasi (idempotent).
     *
     * - tabel `aset_tetap`: master aset, penyusutan garis lurus
     *   (depresiasi bulanan = harga_perolehan / umur_bulan), status
     *   aktif | fully_dep | disposal, guard idempoten `depresiasi_terakhir_bulan`.
     * - COA: 130-02 Akumulasi Depresiasi + 530-01 Beban Depresiasi (firstOrCreate
     *   pola AkunCoaSeeder — cari dulu by kode, jangan duplikat).
     * - 160-01 Aktiva Tetap & 520-05 Beban Lain-lain dipastikan ada sebagai
     *   akun write-off disposal (firstOrCreate — tidak menimpa yang sudah ada).
     *
     * Deviasi dicatat: tidak ada akun "Rugi Pelepasan Aset" khusus di COA
     * retail existing → kerugian disposal memakai 520-05 Beban Lain-lain
     * (fallback terakhir 530-01). Lihat DepresiasiService::akunKerugian().
     */
    public function up(): void
    {
        if (! Schema::hasTable('aset_tetap')) {
            Schema::create('aset_tetap', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->foreignId('cabang_id')->constrained('cabang')->cascadeOnDelete();
                $table->string('nama');
                $table->string('kategori')->default('lainnya');
                $table->decimal('harga_perolehan', 15, 2);
                $table->date('tanggal_perolehan');
                $table->unsignedInteger('umur_bulan');
                $table->string('metode')->default('garis_lurus');
                $table->decimal('akumulasi_depresiasi', 15, 2)->default(0);
                // Guard idempoten per periode (YYYY-MM) — 1 jurnal depresiasi per aset per bulan
                $table->string('depresiasi_terakhir_bulan', 7)->nullable();
                $table->enum('status', ['aktif', 'disposal', 'fully_dep'])->default('aktif');
                $table->text('catatan')->nullable();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->index(['cabang_id', 'status']);
                $table->index('depresiasi_terakhir_bulan');
            });
        }

        // ===== COA idempotent (pola firstOrCreate AkunCoaSeeder) =====
        if (Schema::hasTable('akun_coa')) {
            // [F3-01] Akumulasi Depresiasi — kontra-aset.
            // CATATAN: saldo_normal 'debit' (bukan kredit semantik) karena Neraca
            // existing menjumlah saldo per tipe; debit-normal membuat akumulasi
            // mengurangi total aset secara net (lihat laporan neraca AkuntingController).
            AkunCOA::firstOrCreate(
                ['kode' => '130-02'],
                [
                    'nama' => 'Akumulasi Depresiasi',
                    'tipe' => 'aset',
                    'kelompok' => 'akumulasi_depresiasi',
                    'saldo_normal' => 'debit',
                    'is_active' => true,
                ]
            );

            // [F3-01] Beban Depresiasi — debit bulanan.
            AkunCOA::firstOrCreate(
                ['kode' => '530-01'],
                [
                    'nama' => 'Beban Depresiasi',
                    'tipe' => 'beban',
                    'kelompok' => 'beban_depresiasi',
                    'saldo_normal' => 'debit',
                    'is_active' => true,
                ]
            );

            // Target write-off disposal — dipastikan tersedia (tidak menimpa existing)
            AkunCOA::firstOrCreate(
                ['kode' => '160-01'],
                [
                    'nama' => 'Aktiva Tetap',
                    'tipe' => 'aset',
                    'kelompok' => 'aset_tetap',
                    'saldo_normal' => 'debit',
                    'is_active' => true,
                ]
            );

            AkunCOA::firstOrCreate(
                ['kode' => '520-05'],
                [
                    'nama' => 'Beban Lain-lain',
                    'tipe' => 'beban',
                    'kelompok' => 'beban_operasional',
                    'saldo_normal' => 'debit',
                    'is_active' => true,
                ]
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('aset_tetap');

        // COA tidak dihapus agar tidak mempengaruhi jurnal existing (pola migrasi PPN).
    }
};
