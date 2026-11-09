<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('tiket_servis_item', function (Blueprint $table) {
            if (! Schema::hasColumn('tiket_servis_item', 'alasan_batal')) {
                $table->string('alasan_batal', 255)->nullable()->after('dibatalkan_at');
            }
            if (! Schema::hasColumn('tiket_servis_item', 'dibatalkan_by')) {
                $table->unsignedBigInteger('dibatalkan_by')->nullable()->after('alasan_batal');
                $table->foreign('dibatalkan_by')->references('id')->on('users')->nullOnDelete();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tiket_servis_item', function (Blueprint $table) {
            if (Schema::hasColumn('tiket_servis_item', 'dibatalkan_by')) {
                $table->dropForeign(['dibatalkan_by']);
                $table->dropColumn('dibatalkan_by');
            }
            if (Schema::hasColumn('tiket_servis_item', 'alasan_batal')) {
                $table->dropColumn('alasan_batal');
            }
        });
    }
};
