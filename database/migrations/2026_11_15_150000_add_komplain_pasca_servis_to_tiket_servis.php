<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Komplain pasca-servis: konsumen kembali ke toko karena keluhan setelah
 * servis selesai (mis. LCD bermasalah beberapa hari kemudian). Tiket komplain
 * ditautkan ke tiket asal + menandai apakah garansi servis masih berlaku.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tiket_servis', function (Blueprint $table) {
            if (! Schema::hasColumn('tiket_servis', 'tiket_servis_lama_id')) {
                $table->foreignId('tiket_servis_lama_id')->nullable()->index();
            }

            if (! Schema::hasColumn('tiket_servis', 'garansi_berlaku')) {
                $table->boolean('garansi_berlaku')->default(false);
            }

            if (! Schema::hasColumn('tiket_servis', 'komplain_catatan')) {
                $table->string('komplain_catatan')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('tiket_servis', function (Blueprint $table) {
            if (Schema::hasColumn('tiket_servis', 'komplain_catatan')) {
                $table->dropColumn('komplain_catatan');
            }

            if (Schema::hasColumn('tiket_servis', 'garansi_berlaku')) {
                $table->dropColumn('garansi_berlaku');
            }

            if (Schema::hasColumn('tiket_servis', 'tiket_servis_lama_id')) {
                $table->dropIndex('tiket_servis_tiket_servis_lama_id_index');
                $table->dropColumn('tiket_servis_lama_id');
            }
        });
    }
};
