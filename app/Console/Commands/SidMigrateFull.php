<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

/**
 * Migrasi SID Retail → Ute Parts SEKALI JALAN (pipeline penuh).
 *
 * Urutan = MIGRASI-SID.md §5. Semua sub-command idempotent (2× run = hasil sama).
 * Gunakan --dry-run utk simulasi penuh tanpa menulis data.
 *
 * Example:
 *   php artisan sid:migrate-full --dry-run          # simulasi semua fase
 *   php artisan sid:migrate-full                    # eksekusi semua fase (jurnal pembuka dry-run)
 *   php artisan sid:migrate-full --commit-jurnal    # + post jurnal pembuka SID-OPENING
 */
class SidMigrateFull extends Command
{
    protected $signature = 'sid:migrate-full
        {--dry-run : Simulasi semua fase tanpa menulis data}
        {--commit-jurnal : Posting jurnal pembuka (SID-OPENING) setelah semua fase}
        {--step= : Jalankan mulai dari step ini (default: 1)}
        {--only= : Hanya jalankan step tertentu, koma: seed,backfill,migrate2b,transaksi,stok,servis,merge,jurnal}';

    protected $description = 'Pipeline migrasi SID Retail → Ute Parts (fase M→A→B→C→D + V), idempotent';

    protected array $steps = [
        'seed'       => ['cmd' => 'sid:seed-master',        'desc' => 'M-04..M-07 master: brands, gudang, tier, profil', 'dry' => true],
        'backfill'   => ['cmd' => 'sid:backfill-2a',        'desc' => 'A-02..A-05 backfill produk/pelanggan/supplier/member', 'dry' => false],
        'migrate2b'  => ['cmd' => 'sid:migrate-2b',         'desc' => 'B-01/B-02/B-04 piutang, PO, return', 'dry' => true],
        'transaksi'  => ['cmd' => 'sid:rekonstruksi-transaksi', 'desc' => 'C-02..C-04 cleanup artefak + rekonstruksi transaksi', 'dry' => true],
        'stok'       => ['cmd' => 'sid:rekonstruksi-stok',  'desc' => 'C-05/C-06 stok_log + stok_items', 'dry' => true],
        'servis'     => ['cmd' => 'sid:backfill-servis',    'desc' => 'D-01/D-02 tiket_servis + item + status_log', 'dry' => true],
        'merge'      => ['cmd' => 'sid:merge-pelanggan-dup','desc' => 'A-03 dedup pelanggan + remap FK', 'dry' => true],
        'jurnal'     => ['cmd' => 'sid:jurnal-pembuka',     'desc' => 'C-08 jurnal pembuka (dry-run kecuali --commit-jurnal)', 'dry' => true],
    ];

    public function handle(): int
    {
        $only = $this->option('only');
        $from = (int) $this->option('step') ?: 1;
        $names = array_keys($this->steps);

        $selected = $only
            ? array_values(array_intersect(explode(',', $only), $names))
            : array_slice($names, $from - 1);

        if (! $selected) {
            $this->error('Tidak ada step valid. Step: ' . implode(',', $names));

            return self::FAILURE;
        }

        $dry = (bool) $this->option('dry-run');

        $this->line('==============================================');
        $this->line('MIGRASI SID → UTE PARTS' . ($dry ? ' (DRY-RUN)' : ''));
        $this->line('==============================================');

        foreach ($selected as $name) {
            $s = $this->steps[$name];
            $this->line('');
            $this->info("[{$name}] {$s['desc']}");

            if ($dry && ! ($s['dry'] ?? false)) {
                $this->warn('  → SKIP dry-run: ' . $s['cmd'] . ' tidak mendukung --dry-run (jalankan tanpa --dry-run utk eksekusi)');
                continue;
            }

            $args = [];
            if ($s['cmd'] === 'sid:jurnal-pembuka') {
                if ($dry) {
                    $args['--dry-run'] = true;
                } elseif (! $this->option('commit-jurnal')) {
                    $this->warn('  → JURNAL PEMBUKA hanya dry-run (keputusan akuntansi). Pakai --commit-jurnal utk posting.');
                    $args['--dry-run'] = true;
                }
            } elseif ($dry) {
                $args['--dry-run'] = true;
            }

            $exit = Artisan::call($s['cmd'], $args);
            $out = trim(Artisan::output());

            // Jurnal skip saat dry-run dipropagasi sbg ini
            if ($s['cmd'] === 'sid:jurnal-pembuka' && ! $dry && ! $this->option('commit-jurnal')) {
                $this->warn('  → SKIP POSTING (tanpa --commit-jurnal)');
            } else {
                foreach (preg_split('/\R/', $out) as $line) {
                    if ($line !== '' && str_contains($line, '|')) {
                        $this->line('  ' . $line);
                    }
                }
                if ($exit !== 0) {
                    $this->error("  ✗ {$s['cmd']} GAGAL (exit {$exit})");
                    $this->line($out);

                    return self::FAILURE;
                }
            }
        }

        $this->line('');
        $this->info('SELESAI' . ($dry ? ' (dry-run — tidak ada data ditulis)' : ' — semua fase idempotent, jalankan ulang aman'));

        return self::SUCCESS;
    }
}