<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('gudang') && ! Schema::hasColumn('gudang', 'is_default')) {
            Schema::table('gudang', function (Blueprint $table) {
                $table->boolean('is_default')->default(false)->after('is_active');
            });
        }

        // Tandai gudang default untuk setiap cabang
        if (Schema::hasTable('gudang')) {
            $cabangIds = DB::table('cabang')->pluck('id');
            foreach ($cabangIds as $cabangId) {
                $hasDefault = DB::table('gudang')
                    ->where('cabang_id', $cabangId)
                    ->where('is_default', true)
                    ->exists();

                if (! $hasDefault) {
                    // Cari gudang dengan kode GDG-01 atau gudang aktif pertama
                    $target = DB::table('gudang')
                        ->where('cabang_id', $cabangId)
                        ->where(function ($q) {
                            $q->where('kode', 'GDG-01')
                                ->orWhere('nama', 'LIKE', '%Utama%');
                        })
                        ->orderBy('id', 'asc')
                        ->first()
                        ?? DB::table('gudang')
                            ->where('cabang_id', $cabangId)
                            ->where('is_active', true)
                            ->orderBy('id', 'asc')
                            ->first()
                        ?? DB::table('gudang')
                            ->where('cabang_id', $cabangId)
                            ->orderBy('id', 'asc')
                            ->first();

                    if ($target) {
                        DB::table('gudang')->where('id', $target->id)->update([
                            'is_default' => true,
                            'is_active' => true,
                        ]);
                    }
                }
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('gudang') && Schema::hasColumn('gudang', 'is_default')) {
            Schema::table('gudang', function (Blueprint $table) {
                $table->dropColumn('is_default');
            });
        }
    }
};
