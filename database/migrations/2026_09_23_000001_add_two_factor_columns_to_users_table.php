<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * [F1-3] Kolom 2FA TOTP — idempotent (aman dijalankan ulang).
     * - two_factor_secret: rahasia TOTP (disimpan ter-encrypt via cast model)
     * - two_factor_confirmed_at: timestamp konfirmasi kode pertama
     * - two_factor_backup_codes: JSON array hash backup code (8x), plaintext hanya sekali di UI
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'two_factor_secret')) {
                $table->text('two_factor_secret')->nullable()->after('theme_preference');
            }

            if (! Schema::hasColumn('users', 'two_factor_confirmed_at')) {
                $table->timestamp('two_factor_confirmed_at')->nullable()->after('two_factor_secret');
            }

            if (! Schema::hasColumn('users', 'two_factor_backup_codes')) {
                $table->text('two_factor_backup_codes')->nullable()->after('two_factor_confirmed_at');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            foreach (['two_factor_secret', 'two_factor_confirmed_at', 'two_factor_backup_codes'] as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
