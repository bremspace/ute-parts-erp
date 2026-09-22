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
        // Tambahkan baris default PPN jika tidak ada
        // ppn_enabled (global, cabang-agnostic)
        $existing = DB::table('konfigurasi')->where('kunci', 'ppn_enabled')->first();
        if (! $existing) {
            DB::table('konfigurasi')->insert([
                'kunci' => 'ppn_enabled',
                'nilai' => 'false',
                'deskripsi' => 'Aktifkan Pajak Otomatis per cabang',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // ppn_percent (global)
        $existing = DB::table('konfigurasi')->where('kunci', 'ppn_percent')->first();
        if (! $existing) {
            DB::table('konfigurasi')->insert([
                'kunci' => 'ppn_percent',
                'nilai' => '11',
                'deskripsi' => 'Persen PPN nasional default',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('konfigurasi')->whereIn('kunci', ['ppn_enabled', 'ppn_percent'])->delete();
    }
};