<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('tipe_hp')) {
            $existingIndexes = collect(Schema::getIndexes('tipe_hp'))->pluck('name')->all();

            Schema::table('tipe_hp', function (Blueprint $table) use ($existingIndexes) {
                if (! Schema::hasColumn('tipe_hp', 'kode_produk')) {
                    $table->string('kode_produk', 100)->nullable()->after('model')->index();
                }

                if (in_array('tipe_hp_merk_model_unique', $existingIndexes, true)) {
                    $table->dropUnique('tipe_hp_merk_model_unique');
                }

                if (! in_array('tipe_hp_merk_model_index', $existingIndexes, true)) {
                    $table->index(['merk', 'model'], 'tipe_hp_merk_model_index');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('tipe_hp')) {
            $existingIndexes = collect(Schema::getIndexes('tipe_hp'))->pluck('name')->all();

            Schema::table('tipe_hp', function (Blueprint $table) use ($existingIndexes) {
                if (in_array('tipe_hp_merk_model_index', $existingIndexes, true)) {
                    $table->dropIndex('tipe_hp_merk_model_index');
                }

                if (! in_array('tipe_hp_merk_model_unique', $existingIndexes, true)) {
                    $table->unique(['merk', 'model'], 'tipe_hp_merk_model_unique');
                }

                if (Schema::hasColumn('tipe_hp', 'kode_produk')) {
                    $table->dropColumn('kode_produk');
                }
            });
        }
    }
};
