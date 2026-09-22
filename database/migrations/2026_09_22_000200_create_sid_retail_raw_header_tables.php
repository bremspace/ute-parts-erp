<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * [M-09 fase 1] Staging RAW HEADER data POS SID Retail (latest.sql) di db_staging.
 *
 * Sumber tabel SID: penjualan, pembelian, itempembelian, piutang, hutang,
 * kas, kas_awal, header_return_penjualan, header_return_pembelian,
 * nomor_seri, hrgpergroup, grouphrgpelanggan, expired_barang, komplain,
 * koreksi, itemkoreksi, itemservis.
 *
 * Skema EAV IDENTIK dengan tabel raw inti (2026_09_21_000045) — prasyarat
 * fase 2B (piutang/utang/PO/return/serial/komplain/expired) & fase 3
 * (rekonstruksi transaksi dari header penjualan).
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
        'sid_retail_raw_penjualan',
        'sid_retail_raw_pembelian',
        'sid_retail_raw_itempembelian',
        'sid_retail_raw_piutang',
        'sid_retail_raw_hutang',
        'sid_retail_raw_kas',
        'sid_retail_raw_kas_awal',
        'sid_retail_raw_header_return_penjualan',
        'sid_retail_raw_header_return_pembelian',
        'sid_retail_raw_nomor_seri',
        'sid_retail_raw_hrgpergroup',
        'sid_retail_raw_grouphrgpelanggan',
        'sid_retail_raw_expired_barang',
        'sid_retail_raw_komplain',
        'sid_retail_raw_koreksi',
        'sid_retail_raw_itemkoreksi',
        'sid_retail_raw_itemservis',
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
