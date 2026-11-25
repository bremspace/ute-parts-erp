<?php

use App\Support\NomorTeleponIndonesia;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Standarisasi seluruh nomor HP tersimpan ke format Indonesia 62xxxxx.
     * Aman dijalankan berulang: hanya baris yang berubah yang di-update.
     */
    public function up(): void
    {
        $targets = [
            'pelanggan' => 'telepon',
            'leads' => 'telepon',
            'karyawans' => 'telepon',
            'suppliers' => 'telepon',
            'cabang' => 'telepon',
            'tiket_servis' => 'telepon_pelanggan',
            'pengiriman' => 'telepon_tujuan',
            'users' => 'phone',
        ];

        foreach ($targets as $table => $column) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
                continue;
            }

            DB::table($table)
                ->select('id', $column)
                ->whereNotNull($column)
                ->where($column, '!=', '')
                ->chunkById(200, function ($rows) use ($table, $column) {
                    foreach ($rows as $row) {
                        $standar = NomorTeleponIndonesia::standar((string) $row->{$column});
                        if ($standar !== '' && $standar !== (string) $row->{$column}) {
                            DB::table($table)->where('id', $row->id)->update([$column => $standar]);
                        }
                    }
                });
        }
    }

    public function down(): void
    {
        // Tidak ada rollback otomatis: format lama sengaja tidak dipulihkan
        // karena dapat menciptakan duplikat nomor.
    }
};
