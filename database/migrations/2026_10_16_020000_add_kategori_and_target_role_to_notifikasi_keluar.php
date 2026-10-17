<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notifikasi_keluar', function (Blueprint $table) {
            $table->string('kategori', 50)->default('sistem')->after('tipe');
            $table->string('target_role', 50)->nullable()->after('cabang_id');

            $table->index(['kategori', 'read_at']);
            $table->index(['target_role', 'read_at']);
        });
    }

    public function down(): void
    {
        Schema::table('notifikasi_keluar', function (Blueprint $table) {
            $table->dropIndex(['kategori', 'read_at']);
            $table->dropIndex(['target_role', 'read_at']);

            $table->dropColumn(['kategori', 'target_role']);
        });
    }
};
