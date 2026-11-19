<?php

namespace App\Console\Commands;

use App\Modules\Wms\Services\ProdukService;
use Illuminate\Console\Command;

/**
 * [H9] Rekonsiliasi GL persediaan (130-01) vs subledger Σ(stok x biaya cabang).
 * Memposting jurnal selisih pembulatan (Dr/Cr 130-01 vs 520-08) agar GL =
 * subledger. Idempoten: tanpa drift tidak ada jurnal. --dry-run hanya laporan.
 */
class RekonsiliasiPersediaanCommand extends Command
{
    protected $signature = 'ute:rekonsiliasi-persediaan
        {--cabang= : ID cabang (kosong = semua cabang)}
        {--dry-run : Hanya tampilkan selisih tanpa memposting jurnal}';

    protected $description = 'Rekonsiliasi valuasi persediaan GL 130-01 vs subledger stok (H9)';

    public function handle(ProdukService $produkService): int
    {
        $cabangId = $this->option('cabang') !== null ? (int) $this->option('cabang') : null;
        $dryRun = (bool) $this->option('dry-run');

        $hasil = $produkService->rekonsiliasiPersediaan($cabangId, null, ! $dryRun);

        $this->line('Nilai subledger : Rp '.number_format($hasil['nilai_subledger'], 2, ',', '.'));
        $this->line('Nilai GL 130-01 : Rp '.number_format($hasil['nilai_gl'], 2, ',', '.'));
        $this->line('Selisih         : Rp '.number_format($hasil['selisih'], 2, ',', '.'));

        if ($hasil['jurnal_diposting']) {
            $this->info("Jurnal revaluasi diposting: {$hasil['no_jurnal']}");
        } elseif ($dryRun) {
            $this->comment('Dry-run — tidak ada jurnal diposting.');
        } else {
            $this->info('Tidak ada drift material — GL = subledger.');
        }

        return self::SUCCESS;
    }
}
