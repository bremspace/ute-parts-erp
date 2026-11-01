<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('jurnal_header', function (Blueprint $table) {
            // Status approval untuk Segregation of Duties / Maker-Checker
            $table->string('status_approval', 20)->default('disetujui')->after('user_id'); // 'pending', 'disetujui', 'ditolak'
            $table->foreignId('approved_by_user_id')->nullable()->after('status_approval')->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable()->after('approved_by_user_id');
            $table->text('catatan_approval')->nullable()->after('approved_at');

            // Lampiran Dokumen Bukti Transaksi (Kuitansi / Invoice / Nota)
            $table->string('lampiran_path', 255)->nullable()->after('catatan_approval');
            $table->string('lampiran_nama', 255)->nullable()->after('lampiran_path');

            $table->index(['status_approval']);
        });
    }

    public function down(): void
    {
        Schema::table('jurnal_header', function (Blueprint $table) {
            $table->dropIndex(['status_approval']);
            $table->dropForeign(['approved_by_user_id']);
            $table->dropColumn([
                'status_approval',
                'approved_by_user_id',
                'approved_at',
                'catatan_approval',
                'lampiran_path',
                'lampiran_nama',
            ]);
        });
    }
};
