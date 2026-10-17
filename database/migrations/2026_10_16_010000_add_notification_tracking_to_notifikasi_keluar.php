<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notifikasi_keluar', function (Blueprint $table) {
            $table->timestamp('read_at')->nullable()->after('status');
            $table->unsignedBigInteger('user_id')->nullable()->after('read_at');
            $table->unsignedBigInteger('cabang_id')->nullable()->after('user_id');
            $table->string('action_url', 500)->nullable()->after('cabang_id');

            $table->index(['tipe', 'read_at']);
            $table->index(['user_id', 'read_at']);
            $table->index(['cabang_id', 'read_at']);
        });
    }

    public function down(): void
    {
        Schema::table('notifikasi_keluar', function (Blueprint $table) {
            $table->dropIndex(['tipe', 'read_at']);
            $table->dropIndex(['user_id', 'read_at']);
            $table->dropIndex(['cabang_id', 'read_at']);

            $table->dropColumn(['read_at', 'user_id', 'cabang_id', 'action_url']);
        });
    }
};
