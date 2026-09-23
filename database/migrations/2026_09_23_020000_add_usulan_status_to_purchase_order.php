<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * [F1-5] Extend enum status purchase_order dengan 'usulan' (reorder otomatis).
 *
 * MySQL: ALTER TABLE ... MODIFY (native change()).
 * SQLite: rebuild table — check constraint enum diganti daftar nilai baru.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('purchase_order')) {
            return;
        }

        Schema::table('purchase_order', function (Blueprint $table) {
            $table->enum('status', ['draft', 'dikirim', 'diterima', 'dibatalkan', 'usulan'])
                ->default('draft')
                ->change();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('purchase_order')) {
            return;
        }

        // Jangan rollback bila masih ada baris 'usulan' (akan melanggar enum lama)
        if (DB::table('purchase_order')->where('status', 'usulan')->exists()) {
            return;
        }

        Schema::table('purchase_order', function (Blueprint $table) {
            $table->enum('status', ['draft', 'dikirim', 'diterima', 'dibatalkan'])
                ->default('draft')
                ->change();
        });
    }
};
