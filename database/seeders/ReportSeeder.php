<?php

namespace Database\Seeders;

use App\Models\User;
use App\Modules\Rbac\Models\Cabang;
use App\Modules\Report\Models\SavedReport;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ReportSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        // Create sample saved reports for demo users (idempotent)
        $users = User::whereIn('email', ['admin@uteparts.test', 'finance@uteparts.test'])->get();

        // Fallback global bila user belum punya relasi cabang
        $fallbackCabangId = Cabang::query()->first()?->id;

        foreach ($users as $user) {
            // [P2-7] session('cabang_id') selalu null di console seeding →
            // turunkan cabang dari pivot user_cabang, fallback cabang pertama.
            $cabangId = $user->cabangs()->first()?->id ?? $fallbackCabangId;

            SavedReport::updateOrCreate(
                [
                    'name' => 'Laporan Transaksi Harian',
                    'source_model' => 'Transaksi',
                    'user_id' => $user->id,
                ],
                [
                    'columns' => ['no_transaksi', 'total_akhir', 'status', 'created_at'],
                    'filters' => ['status' => 'selesai'],
                    'export_format' => 'xlsx',
                    'cabang_id' => $cabangId,
                    'shared' => true, // [P2-4] seeded = dibagikan ke cabang oleh desain
                ]
            );

            SavedReport::updateOrCreate(
                [
                    'name' => 'Rekap Piutang Belum Lunas',
                    'source_model' => 'Piutang',
                    'user_id' => $user->id,
                ],
                [
                    'columns' => ['no_piutang', 'jumlah', 'jatuh_tempo', 'status'],
                    'filters' => ['status' => 'belum_lunas'],
                    'export_format' => 'csv',
                    'cabang_id' => $cabangId,
                    'shared' => true, // [P2-4] seeded = dibagikan ke cabang oleh desain
                ]
            );
        }
    }
}
