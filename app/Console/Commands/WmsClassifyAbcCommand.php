<?php

namespace App\Console\Commands;

use App\Modules\Wms\Services\ProcurementService;
use Illuminate\Console\Command;

class WmsClassifyAbcCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'wms:classify-abc {--cabang= : ID Cabang opsional} {--periode=90 : Periode analisis dalam hari (default 90)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Hitung dan terapkan klasifikasi kelas ABC dan parameter pengadaan (ROP, Min, Max) secara otomatis berbasis kriteria WMS Pareto';

    /**
     * Execute the console command.
     */
    public function handle(ProcurementService $service): int
    {
        $cabangId = $this->option('cabang') ? (int) $this->option('cabang') : null;
        $periodeHari = (int) $this->option('periode');

        $this->info("Menghitung klasifikasi WMS otomatis (Pareto 80/20) untuk periode {$periodeHari} hari terakhir...");

        $hasil = $service->sinkronisasiKelasAbcOtomatis($cabangId, [], $periodeHari);

        $this->table(
            ['Metrik', 'Nilai'],
            [
                ['Total Produk Diperbarui', $hasil['total_updated']],
                ['Kelas A (Fast Moving - Pareto 80%)', $hasil['breakdown']['A'] ?? 0],
                ['Kelas B (Medium Moving - Pareto 15%)', $hasil['breakdown']['B'] ?? 0],
                ['Kelas C (Slow Moving / Long-tail)', $hasil['breakdown']['C'] ?? 0],
                ['Periode Analisis', "{$hasil['periode_hari']} hari"],
            ]
        );

        $this->info('Klasifikasi produk otomatis selesai diterapkan!');

        return Command::SUCCESS;
    }
}
