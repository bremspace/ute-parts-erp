<?php

namespace Database\Seeders;

use App\Modules\Crm\Models\TierMembership;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class TierMembershipSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Default konfigurasi tier membership bawaan sistem Ute Parts.
     */
    public static function defaultTiers(): array
    {
        return [
            [
                'nama' => 'Silver',
                'kode' => 'silver',
                'min_belanja_12bulan' => 500000,
                'diskon_persen' => 3,
                'poin_multiplier' => 1.0,
                'urutan' => 1,
                'is_active' => true,
            ],
            [
                'nama' => 'Gold',
                'kode' => 'gold',
                'min_belanja_12bulan' => 2000000,
                'diskon_persen' => 5,
                'poin_multiplier' => 1.5,
                'urutan' => 2,
                'is_active' => true,
            ],
            [
                'nama' => 'Platinum',
                'kode' => 'platinum',
                'min_belanja_12bulan' => 5000000,
                'diskon_persen' => 10,
                'poin_multiplier' => 2.0,
                'urutan' => 3,
                'is_active' => true,
            ],
        ];
    }

    public function run(): void
    {
        $tiers = self::defaultTiers();

        foreach ($tiers as $data) {
            TierMembership::updateOrCreate(
                ['kode' => $data['kode']],
                $data
            );
        }
    }
}
