<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Daftar tabel master/operasional yang disinkronkan agar identifikasi
     * tidak bergantung pada auto-increment ID yang berpotensi bertabrakan antar node.
     *
     * @var array<int, string>
     */
    private array $tables = [
        'produk',
        'sku_variants',
        'pelanggan',
        'kategori_produk',
        'brands',
        'kualitas_produk',
        'satuan_unit',
        'tipe_hp',
        'tiket_servis',
        'harga_tier',
        'produk_satuan_konversi',
        'jurnal_header',
        'payroll_periode',
        'return_penjualan',
    ];

    public function up(): void
    {
        foreach ($this->tables as $tableName) {
            if (! Schema::hasTable($tableName) || Schema::hasColumn($tableName, 'sync_uuid')) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                $table->string('sync_uuid', 36)->nullable();
                $table->unique('sync_uuid', "{$tableName}_sync_uuid_unique");
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $tableName) {
            if (! Schema::hasTable($tableName) || ! Schema::hasColumn($tableName, 'sync_uuid')) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                $table->dropUnique("{$tableName}_sync_uuid_unique");
                $table->dropColumn('sync_uuid');
            });
        }
    }
};
