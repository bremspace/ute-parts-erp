<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

/**
 * Seeder Khusus Lingkungan Dev / Demo.
 *
 * Menambahkan data produk tiruan, dummy invoice, transaksi pos demo,
 * dan tiket servis pengujian.
 * JANGAN dijalankan pada lingkungan produksi.
 */
class DemoDataSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $this->call([
            ProdukDanStokSeeder::class,
            TransaksiDemoSeeder::class,
            ReportSeeder::class,
            HrSeeder::class,
            IntegrationSeeder::class,
        ]);
    }
}
