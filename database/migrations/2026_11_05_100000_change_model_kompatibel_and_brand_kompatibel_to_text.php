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
                DB::statement('ALTER TABLE produk DROP INDEX produk_model_kompatibel_idx');
            } catch (Throwable $e) {
                // Ignore jika index belum ada
            }

            try {
                DB::statement('ALTER TABLE produk DROP INDEX produk_brand_kompatibel_idx');
            } catch (Throwable $e) {
                // Ignore jika index belum ada
            }

            DB::statement('ALTER TABLE produk MODIFY model_kompatibel TEXT NULL');
            DB::statement('ALTER TABLE produk MODIFY brand_kompatibel TEXT NULL');

            DB::statement('CREATE INDEX produk_model_kompatibel_idx ON produk (model_kompatibel(191))');
            DB::statement('CREATE INDEX produk_brand_kompatibel_idx ON produk (brand_kompatibel(191))');
        } else {
            Schema::table('produk', function (Blueprint $table) {
                $table->text('model_kompatibel')->nullable()->change();
                $table->text('brand_kompatibel')->nullable()->change();
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
                DB::statement('ALTER TABLE produk DROP INDEX produk_model_kompatibel_idx');
            } catch (Throwable $e) {
                // Ignore jika index belum ada
            }

            try {
                DB::statement('ALTER TABLE produk DROP INDEX produk_brand_kompatibel_idx');
            } catch (Throwable $e) {
                // Ignore jika index belum ada
            }

            DB::statement('ALTER TABLE produk MODIFY model_kompatibel VARCHAR(255) NULL');
            DB::statement('ALTER TABLE produk MODIFY brand_kompatibel VARCHAR(255) NULL');

            DB::statement('CREATE INDEX produk_model_kompatibel_idx ON produk (model_kompatibel)');
            DB::statement('CREATE INDEX produk_brand_kompatibel_idx ON produk (brand_kompatibel)');
        } else {
            Schema::table('produk', function (Blueprint $table) {
                $table->string('model_kompatibel', 255)->nullable()->change();
                $table->string('brand_kompatibel', 255)->nullable()->change();
            });
        }
    }
};
