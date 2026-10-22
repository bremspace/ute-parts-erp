<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            try {
                DB::statement('ALTER TABLE produk DROP INDEX produk_nama_idx');
            } catch (Throwable $e) {
                // Ignore jika index belum ada
            }

            DB::statement('ALTER TABLE produk MODIFY nama TEXT NOT NULL');
            DB::statement('CREATE INDEX produk_nama_idx ON produk (nama(255))');
        } else {
            Schema::table('produk', function (Blueprint $table) {
                $table->text('nama')->change();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            try {
                DB::statement('ALTER TABLE produk DROP INDEX produk_nama_idx');
            } catch (Throwable $e) {
                // Ignore jika index belum ada
            }

            DB::statement('ALTER TABLE produk MODIFY nama VARCHAR(255) NOT NULL');
            DB::statement('CREATE INDEX produk_nama_idx ON produk (nama)');
        } else {
            Schema::table('produk', function (Blueprint $table) {
                $table->string('nama', 255)->change();
            });
        }
    }
};
