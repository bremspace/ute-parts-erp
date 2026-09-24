<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Karyawan
        if (! Schema::hasTable('karyawan')) {
            Schema::create('karyawan', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('nik')->nullable()->unique();
                $table->string('nama');
                $table->enum('jabatan', ['teknisi', 'kasir', 'admin', 'other']);
                $table->foreignId('cabang_id')->nullable()->constrained('cabang')->nullOnDelete();
                $table->date('tgl_masuk');
                $table->decimal('gaji_pokok', 15, 2)->default(0);
                $table->boolean('status_aktif')->default(true);
                $table->string('rekening_bank')->nullable();
                $table->timestamps();
            });
        }

        // Karyawan Komponen Gaji
        if (! Schema::hasTable('karyawan_komponen_gaji')) {
            Schema::create('karyawan_komponen_gaji', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->foreignId('karyawan_id')->constrained('karyawan')->cascadeOnDelete();
                $table->enum('tipe', ['tunjangan', 'potongan', 'bonus']);
                $table->string('nama');
                $table->decimal('nominal_bulanan', 15, 2)->default(0);
                $table->boolean('is_aktif')->default(true);
                $table->timestamps();
            });
        }

        // Komisi Teknisi Rule
        if (! Schema::hasTable('komisi_teknisi_rules')) {
            Schema::create('komisi_teknisi_rules', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->foreignId('cabang_id')->nullable()->constrained('cabang')->nullOnDelete();
                $table->string('jabatan_target');
                $table->enum('jenis', ['per_tiket', 'persen_nilai_servis']);
                $table->decimal('nominal', 15, 2)->nullable();
                $table->decimal('persen', 5, 2)->nullable();
                $table->string('min_status_tiket')->nullable();
                $table->boolean('is_aktif')->default(true);
                $table->timestamps();
            });
        }

        // Payroll Periode
        if (! Schema::hasTable('payroll_periode')) {
            Schema::create('payroll_periode', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('periode');
                $table->date('tanggal_mulai');
                $table->date('tanggal_selesai');
                $table->enum('status', ['draft', 'diproses', 'selesai', 'dibayar'])->default('draft');
                $table->text('catatan')->nullable();
                $table->timestamps();
                $table->unique('periode');
            });
        }

        // Payroll Slip
        if (! Schema::hasTable('payroll_slip')) {
            Schema::create('payroll_slip', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->foreignId('payroll_periode_id')->constrained('payroll_periode')->cascadeOnDelete();
                $table->foreignId('karyawan_id')->constrained('karyawan')->cascadeOnDelete();
                $table->decimal('gaji_pokok', 15, 2)->default(0);
                $table->decimal('total_tunjangan', 15, 2)->default(0);
                $table->decimal('total_potongan', 15, 2)->default(0);
                $table->decimal('total_komisi', 15, 2)->default(0);
                $table->decimal('total_gaji', 15, 2)->default(0);
                $table->json('rincian')->nullable();
                $table->foreignId('jurnal_id')->nullable()->constrained('jurnal_akuntansi')->nullOnDelete();
                $table->string('status')->default('draft');
                $table->timestamps();
            });
        }

        // Payroll Komisi Detail
        if (! Schema::hasTable('payroll_komisi_detail')) {
            Schema::create('payroll_komisi_detail', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->foreignId('slip_id')->constrained('payroll_slip')->cascadeOnDelete();
                $table->foreignId('tiket_servis_id')->constrained('tiket_servis')->cascadeOnDelete();
                $table->foreignId('teknisi_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('jenis');
                $table->decimal('nominal', 15, 2)->default(0);
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_komisi_detail');
        Schema::dropIfExists('payroll_slip');
        Schema::dropIfExists('payroll_periode');
        Schema::dropIfExists('komisi_teknisi_rules');
        Schema::dropIfExists('karyawan_komponen_gaji');
        Schema::dropIfExists('karyawan');
    }
};
