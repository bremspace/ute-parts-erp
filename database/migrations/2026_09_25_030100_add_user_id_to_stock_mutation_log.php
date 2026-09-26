<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * [B-10b/P1-2] stock_mutation_log belum punya user_id sedangkan stok_log punya —
 * jejak siapa yang memotong stok tidak bisa dibaca dari SOT mutasi.
 * Additive & nullable (aman: baris lama + writer yang belum mengisinya tetap NULL).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_mutation_log', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('gudang_id')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('stock_mutation_log', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_id');
        });
    }
};
