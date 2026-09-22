<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * [T-42 fase 1] Staging RAW data POS SID Retail (latest.sql) di db_staging.
 *
 * Snapshot mentah per entitas inti — TIDAK menyentuh tabel Ute Parts existing.
 * Mapping final ke skema Ute Parts dikerjakan fase 2 (setelah skema produk
 * komprehensif T-44 rilis) dengan menandai `diimport_at`.
 *
 * Kolom:
 * - kode_sumber     : kunci natural dari dump (PK string SID), unik per baris.
 * - tabel_sumber    : nama tabel asal (utk arus_stok partisi bulanan 2..8_2026).
 * - payload_json    : nilai ASLI persis seperti di dump (untuk audit).
 * - payload_normal  : nilai ternormalisasi utk fase 2
 *                     (NULL/'True'/'False'/1899-12-30/jam 12-jam → tipe normal).
 * - diimport_at     : diisi saat fase 2 mengimpor baris ini ke tabel Ute Parts.
 */
return new class extends Migration
{
    private const TABLES = [
        'sid_retail_raw_barang',
        'sid_retail_raw_pelanggan',
        'sid_retail_raw_supplier',
        'sid_retail_raw_member',
        'sid_retail_raw_itempenjualan',
        'sid_retail_raw_arus_stok',
        'sid_retail_raw_servis',
        'sid_retail_raw_setup_perusahaan',
    ];

    public function up(): void
    {
        foreach (self::TABLES as $table) {
            Schema::create($table, function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('kode_sumber', 255)->unique();
                $table->string('tabel_sumber', 100)->nullable()->index();
                $table->longText('payload_json');
                $table->longText('payload_normal');
                $table->timestamp('diimport_at')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $table) {
            Schema::dropIfExists($table);
        }
    }
};