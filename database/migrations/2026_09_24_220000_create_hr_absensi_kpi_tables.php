<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // [F3-8b] Perluas enum jabatan karyawan: tambah 'marketing' (KPI §4.2).
        if (Schema::hasTable('karyawan')) {
            if (DB::getDriverName() === 'sqlite') {
                // SQLite: enum = varchar + CHECK → rebuild jadi string biasa (tanpa check)
                Schema::table('karyawan', function (Blueprint $table) {
                    $table->string('jabatan', 100)->default('other')->change();
                });
            } else {
                DB::statement("ALTER TABLE karyawan MODIFY jabatan ENUM('teknisi','kasir','admin','marketing','other')");
            }
        }

        // Shift kerja
        if (! Schema::hasTable('shift')) {
            Schema::create('shift', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->foreignId('cabang_id')->nullable()->constrained('cabang')->nullOnDelete();
                $table->string('nama');
                $table->time('jam_mulai');
                $table->time('jam_selesai'); // jam selesai < jam mulai = overnight
                $table->boolean('is_aktif')->default(true);
                $table->timestamps();

                $table->index(['cabang_id', 'is_aktif']);
            });
        }

        // Shift Jadwal (roster) — assignment kasir ↔ shift per tanggal
        if (! Schema::hasTable('shift_jadwal')) {
            Schema::create('shift_jadwal', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->foreignId('cabang_id')->constrained('cabang')->cascadeOnDelete();
                $table->foreignId('karyawan_id')->constrained('karyawan')->cascadeOnDelete();
                $table->foreignId('shift_id')->constrained('shift')->cascadeOnDelete();
                $table->date('tanggal');
                $table->timestamps();

                $table->unique(['cabang_id', 'karyawan_id', 'tanggal']);
            });
        }

        // Absensi Log — idempotent per karyawan+tanggal
        if (! Schema::hasTable('absensi_log')) {
            Schema::create('absensi_log', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->foreignId('karyawan_id')->constrained('karyawan')->cascadeOnDelete();
                $table->date('tanggal');
                $table->time('jam_masuk')->nullable();
                $table->time('jam_keluar')->nullable();
                $table->foreignId('shift_id')->nullable()->constrained('shift')->nullOnDelete();
                $table->enum('status', ['hadir', 'terlambat', 'absen', 'izin', 'cuti'])->default('hadir');
                $table->text('catatan')->nullable();
                $table->string('self_photo')->nullable();
                $table->string('lokasi')->nullable();
                $table->timestamps();

                $table->unique(['karyawan_id', 'tanggal']);
            });
        }

        // KPI Metric — rumus wajib whitelist kode (BUKAN eval bebas)
        if (! Schema::hasTable('kpi_metric')) {
            Schema::create('kpi_metric', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('kode')->unique();
                $table->string('nama');
                $table->string('rumus'); // whitelist: tiket_selesai, transaksi_kasir, selisih_kas, lead_won, penjualan_lead_won
                $table->decimal('target', 15, 2)->default(0);
                $table->string('satuan')->nullable();
                $table->string('periode')->default('bulanan');
                $table->boolean('is_aktif')->default(true);
                $table->timestamps();
            });
        }

        // KPI Hasil — dihitung service dari data real (bukan input manual)
        if (! Schema::hasTable('kpi_hasil')) {
            Schema::create('kpi_hasil', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->foreignId('karyawan_id')->constrained('karyawan')->cascadeOnDelete();
                $table->foreignId('kpi_metric_id')->constrained('kpi_metric')->cascadeOnDelete();
                $table->string('periode', 7); // YYYY-MM
                $table->decimal('nilai_aktual', 15, 2)->default(0);
                $table->decimal('persen_capaian', 7, 2)->default(0);
                $table->timestamps();

                $table->unique(['karyawan_id', 'kpi_metric_id', 'periode']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('kpi_hasil');
        Schema::dropIfExists('kpi_metric');
        Schema::dropIfExists('absensi_log');
        Schema::dropIfExists('shift_jadwal');
        Schema::dropIfExists('shift');

        // Kembalikan enum jabatan (MySQL saja; SQLite = string tanpa check)
        if (Schema::hasTable('karyawan') && DB::getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE karyawan MODIFY jabatan ENUM('teknisi','kasir','admin','other')");
        }
    }
};
