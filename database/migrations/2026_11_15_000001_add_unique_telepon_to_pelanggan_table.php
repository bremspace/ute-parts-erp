<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Unique index pada `telepon` sebagai jaring pengaman DB:
     * sebelumnya hanya `index`, sehingga akun ganda untuk nomor yang sama
     * bisa dibuat lewat jalur di luar PelangganService (Servis walk-in,
     * impor SID, seeder) — ini sumber "member tidak bisa login" karena
     * akun yang dipakai untuk login bukan akun yang dimaksud member.
     *
     * Migration sengaja GAGAL BERSIH bila masih ada duplikat, supaya
     * duplikat diselesaikan dulu, bukan dibiarkan lolos ke produksi.
     */
    public function up(): void
    {
        $duplikat = DB::table('pelanggan')
            ->select('telepon')
            ->whereNotNull('telepon')
            ->groupBy('telepon')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('telepon')
            ->all();

        if ($duplikat !== []) {
            throw new \RuntimeException(
                'Tidak dapat menambahkan unique index pelanggan.telepon: masih ada '.count($duplikat)
                .' nomor duplikat ('.implode(', ', array_slice($duplikat, 0, 10)).'). '
                .'Gabungkan/duplikat terlebih dahulu (mis. via SidMergePelangganDup) sebelum migrate.'
            );
        }

        Schema::table('pelanggan', function (Blueprint $table) {
            $table->unique('telepon');
        });
    }

    public function down(): void
    {
        Schema::table('pelanggan', function (Blueprint $table) {
            $table->dropUnique(['telepon']);
        });
    }
};
