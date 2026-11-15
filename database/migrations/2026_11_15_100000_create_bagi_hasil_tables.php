<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * [BH] Bagi Hasil Jasa Teknisi — teknisi non-gaji (mitra) mendapat porsi
 * persentase dari nilai JASA tiket servis yang selesai.
 *
 * - `karyawan.metode_penghasilan` (gaji|bagi_hasil) menandai teknisi non-gaji.
 * - `bagi_hasil_rules` — aturan persen (prioritas: per teknisi > cabang > global).
 * - `bagi_hasil` — 1 baris per tiket (idempoten), status pending/disetujui/cair/dibatalkan.
 * - `bagi_hasil_periode` — siklus payout bulanan (draft → selesai → dibayar).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('karyawan', 'metode_penghasilan')) {
            Schema::table('karyawan', function (Blueprint $table) {
                $table->enum('metode_penghasilan', ['gaji', 'bagi_hasil'])->default('gaji');
            });
        }

        if (! Schema::hasTable('bagi_hasil_rules')) {
            Schema::create('bagi_hasil_rules', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('nama');
                $table->foreignId('cabang_id')->nullable()->constrained('cabang')->nullOnDelete();
                $table->foreignId('karyawan_id')->nullable()->constrained('karyawan')->nullOnDelete();
                $table->decimal('persen', 5, 2); // 0 - 100
                $table->decimal('min_jasa', 15, 2)->default(0); // ambang minimal total jasa tiket
                $table->boolean('is_aktif')->default(true);
                $table->timestamps();

                $table->index(['cabang_id', 'karyawan_id', 'is_aktif']);
            });
        }

        if (! Schema::hasTable('bagi_hasil')) {
            Schema::create('bagi_hasil', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('no_bagi_hasil', 40);
                $table->foreignId('tiket_servis_id')->unique()->constrained('tiket_servis')->cascadeOnDelete();
                $table->foreignId('karyawan_id')->constrained('karyawan')->cascadeOnDelete();
                $table->foreignId('teknisi_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('cabang_id')->nullable()->constrained('cabang')->nullOnDelete();
                $table->string('periode', 7); // YYYY-MM dari tanggal_selesai tiket
                $table->decimal('total_jasa', 15, 2);
                $table->decimal('persen', 5, 2);
                $table->decimal('nominal', 15, 2);
                $table->enum('status', ['pending', 'disetujui', 'cair', 'dibatalkan'])->default('pending');
                $table->timestamp('paid_at')->nullable();
                $table->text('keterangan')->nullable();
                $table->timestamps();

                $table->index(['periode', 'status']);
                $table->index(['karyawan_id', 'periode', 'status']);
            });
        }

        if (! Schema::hasTable('bagi_hasil_periode')) {
            Schema::create('bagi_hasil_periode', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('periode', 7);
                $table->foreignId('cabang_id')->nullable()->constrained('cabang')->nullOnDelete();
                $table->date('tanggal_mulai');
                $table->date('tanggal_selesai');
                $table->enum('status', ['draft', 'selesai', 'dibayar'])->default('draft');
                $table->decimal('total', 15, 2)->default(0);
                $table->unsignedInteger('jumlah_tiket')->default(0);
                $table->string('no_jurnal_accrue', 60)->nullable();
                $table->string('no_jurnal_bayar', 60)->nullable();
                $table->timestamp('paid_at')->nullable();
                $table->text('catatan')->nullable();
                $table->timestamps();

                $table->index(['periode', 'cabang_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('bagi_hasil_periode');
        Schema::dropIfExists('bagi_hasil');
        Schema::dropIfExists('bagi_hasil_rules');

        if (Schema::hasColumn('karyawan', 'metode_penghasilan')) {
            Schema::table('karyawan', function (Blueprint $table) {
                $table->dropColumn('metode_penghasilan');
            });
        }
    }
};
