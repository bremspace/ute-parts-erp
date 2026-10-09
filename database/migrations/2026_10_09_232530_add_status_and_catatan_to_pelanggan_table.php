<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pelanggan', function (Blueprint $table) {
            // Status registrasi member: pending (menunggu approval owner), aktif, ditolak
            $table->string('status', 20)->default('aktif')->after('password');
            $table->text('catatan_approval')->nullable()->after('status');
            $table->timestamp('approved_at')->nullable()->after('catatan_approval');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete()->after('approved_at');
            $table->index(['status']);
        });
    }

    public function down(): void
    {
        Schema::table('pelanggan', function (Blueprint $table) {
            $table->dropForeign(['approved_by']);
            $table->dropColumn(['status', 'catatan_approval', 'approved_at', 'approved_by']);
        });
    }
};
