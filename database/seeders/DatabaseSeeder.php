<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

/**
 * Standard Production Database Seeder.
 *
 * Menginisialisasi SELURUH pondasi master data standar & akun operasional resmi
 * UteParts tanpa data demo / transaksi tiruan (zero dummy).
 * Kondisi hasil seeder ini identik dengan hasil Factory Reset.
 */
class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $this->call([
            RolesAndPermissionsSeeder::class,
            CabangSeeder::class,
            MasterDataSeeder::class,
            TierMembershipSeeder::class,
            KategoriProdukSeeder::class,
            AdminUserSeeder::class,
            RoleUserSeeder::class,
            JenisServisSeeder::class,
            AkunCoaSeeder::class,
            KomisiSkemaSeeder::class,
            WebKonsumenSeeder::class,
        ]);
    }
}
