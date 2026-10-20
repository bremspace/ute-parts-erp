<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('produk_satuan_konversi')) {
            Schema::table('produk_satuan_konversi', function (Blueprint $table) {
                $table->boolean('is_satuan_jual')->default(true)->change();
            });

            DB::table('produk_satuan_konversi')
                ->where('is_satuan_jual', false)
                ->update(['is_satuan_jual' => true]);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('produk_satuan_konversi')) {
            Schema::table('produk_satuan_konversi', function (Blueprint $table) {
                $table->boolean('is_satuan_jual')->default(false)->change();
            });
        }
    }
};
