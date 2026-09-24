<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sid_import_map', function (Blueprint $table) {
            // Drop unique key lama (kode_sumber + tabel_sumber)
            $table->dropUnique('sid_import_map_unique');
            // Add unique key baru: kode_sumber + tabel_sumber + entity_type
            $table->unique(['kode_sumber', 'tabel_sumber', 'entity_type'], 'sid_import_map_unique');
        });
    }

    public function down(): void
    {
        Schema::table('sid_import_map', function (Blueprint $table) {
            $table->dropUnique('sid_import_map_unique');
            $table->unique(['kode_sumber', 'tabel_sumber'], 'sid_import_map_unique');
        });
    }
};
