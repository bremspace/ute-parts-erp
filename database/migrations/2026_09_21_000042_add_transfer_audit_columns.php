<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * [T-41] Audit trail transfer antar gudang:
     * - stok_transfer.approved_by / approved_at → user yg menyetujui pengiriman (kirim) + timestamp.
     *   (created_by = user_pengirim_id, received_by = user_penerima_id + tanggal_terima, sudah ada.)
     * - stok_transfer_item.created_by → pembuat draft per item (nullable, tidak breaking).
     */
    public function up(): void
    {
        Schema::table('stok_transfer', function (Blueprint $table) {
            $table->foreignId('approved_by')->nullable()->after('user_penerima_id')->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable()->after('tanggal_terima');
        });

        Schema::table('stok_transfer_item', function (Blueprint $table) {
            $table->foreignId('created_by')->nullable()->after('jumlah')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('stok_transfer_item', function (Blueprint $table) {
            $table->dropConstrainedForeignId('created_by');
        });

        Schema::table('stok_transfer', function (Blueprint $table) {
            $table->dropColumn('approved_at');
            $table->dropConstrainedForeignId('approved_by');
        });
    }
};