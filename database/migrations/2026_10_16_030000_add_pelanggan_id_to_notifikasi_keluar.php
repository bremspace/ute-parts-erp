<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notifikasi_keluar', function (Blueprint $table) {
            $table->unsignedBigInteger('pelanggan_id')->nullable()->after('user_id');
            $table->index(['pelanggan_id', 'read_at']);
        });
    }

    public function down(): void
    {
        Schema::table('notifikasi_keluar', function (Blueprint $table) {
            $table->dropIndex(['pelanggan_id', 'read_at']);
            $table->dropColumn('pelanggan_id');
        });
    }
};
