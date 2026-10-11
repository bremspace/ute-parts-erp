<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Tambah SLA & auto-cancel konfigurasi pada tabel jenis_servis
        Schema::table('jenis_servis', function (Blueprint $table) {
            if (! Schema::hasColumn('jenis_servis', 'batas_waktu_menit')) {
                // Default batas waktu pengerjaan SLA (menit), default 120 menit (2 jam)
                $table->integer('batas_waktu_menit')->default(120)->after('estimasi_durasi');
            }
            if (! Schema::hasColumn('jenis_servis', 'peringatan_menit')) {
                // Waktu sisa (menit) sebelum batas waktu habis untuk memicu warning, default 30 menit
                $table->integer('peringatan_menit')->default(30)->after('batas_waktu_menit');
            }
            if (! Schema::hasColumn('jenis_servis', 'auto_cancel_jika_overdue')) {
                // Opsi otomatis cancel atau manual saat batas waktu habis
                $table->boolean('auto_cancel_jika_overdue')->default(false)->after('peringatan_menit');
            }
        });

        // 2. Tambah kolom batas waktu pengerjaan, deadline, dan KPI tracking pada tiket_servis
        Schema::table('tiket_servis', function (Blueprint $table) {
            if (! Schema::hasColumn('tiket_servis', 'batas_waktu_menit')) {
                $table->integer('batas_waktu_menit')->nullable()->after('alasan_estimasi');
            }
            if (! Schema::hasColumn('tiket_servis', 'auto_cancel_overdue')) {
                $table->boolean('auto_cancel_overdue')->default(false)->after('batas_waktu_menit');
            }
            if (! Schema::hasColumn('tiket_servis', 'pengerjaan_mulai_at')) {
                $table->timestamp('pengerjaan_mulai_at')->nullable()->after('auto_cancel_overdue');
            }
            if (! Schema::hasColumn('tiket_servis', 'pengerjaan_deadline_at')) {
                $table->timestamp('pengerjaan_deadline_at')->nullable()->after('pengerjaan_mulai_at');
            }
            if (! Schema::hasColumn('tiket_servis', 'pengerjaan_selesai_at')) {
                $table->timestamp('pengerjaan_selesai_at')->nullable()->after('pengerjaan_deadline_at');
            }
            if (! Schema::hasColumn('tiket_servis', 'durasi_pengerjaan_menit')) {
                $table->integer('durasi_pengerjaan_menit')->nullable()->after('pengerjaan_selesai_at');
            }
            if (! Schema::hasColumn('tiket_servis', 'status_sla_kpi')) {
                // 'on_time', 'overdue', 'cancelled_overdue'
                $table->string('status_sla_kpi', 30)->nullable()->after('durasi_pengerjaan_menit');
            }
            if (! Schema::hasColumn('tiket_servis', 'warning_overdue_sent_at')) {
                $table->timestamp('warning_overdue_sent_at')->nullable()->after('status_sla_kpi');
            }
        });

        // 3. Tabel log progress pengerjaan teknisi (termasuk upload foto & pesan transparan ke konsumen)
        if (! Schema::hasTable('tiket_servis_progress')) {
            Schema::create('tiket_servis_progress', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->foreignId('tiket_servis_id')->constrained('tiket_servis')->cascadeOnDelete();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->integer('persentase')->default(0); // 0-100%
                $table->string('judul');
                $table->text('deskripsi')->nullable();
                $table->json('foto')->nullable(); // array URL / path foto progress
                $table->boolean('tampilkan_ke_konsumen')->default(true);
                $table->timestamps();

                $table->index(['tiket_servis_id', 'created_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('tiket_servis_progress');

        Schema::table('tiket_servis', function (Blueprint $table) {
            $table->dropColumn([
                'batas_waktu_menit',
                'auto_cancel_overdue',
                'pengerjaan_mulai_at',
                'pengerjaan_deadline_at',
                'pengerjaan_selesai_at',
                'durasi_pengerjaan_menit',
                'status_sla_kpi',
                'warning_overdue_sent_at',
            ]);
        });

        Schema::table('jenis_servis', function (Blueprint $table) {
            $table->dropColumn([
                'batas_waktu_menit',
                'peringatan_menit',
                'auto_cancel_jika_overdue',
            ]);
        });
    }
};
