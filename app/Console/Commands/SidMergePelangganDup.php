<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * [MIGRASI-SID FASE 2A] A-03 merge dedup pelanggan (idempotent, chunked, aman).
 *
 * Dedup 10 grup/23 baris by (UPPER(nama), telepon) dari staging pelanggan (SOT).
 * Canonical = baris dgn join_date terlama; tiebreak kode_lama numerik terkecil
 * (verifikasi 2026-09-22: hasil = laporan A-02, kode canonical 0012/0116/0001/0156/
 * 0158/0006/0064/0009/0014/0043).
 *
 * Alur per grup:
 *  1) remap SEMUA FK pelanggan (tiket_servis/piutang/return_penjualan/transaksi/
 *     komisi/komplain_pelanggan/skema_komisi_reseller/utang) dari id dup → id canonical
 *  2) remap sid_import_map (entity_type='pelanggan') id dup → canonical
 *  3) hapus baris dup pelanggan
 *  4) verifikasi 0 referensi tersisa ke id dup + hitung pelanggan final
 *
 * Idempotent: run ulang → grup sudah merger (dup sudah terhapus) → 0 aksi.
 * Data operasional app non-SID tidak disentuh.
 */
class SidMergePelangganDup extends Command
{
    protected $signature = 'sid:merge-pelanggan-dup {--dry-run : hanya hitung & laporan, tanpa perubahan DB}';

    protected $description = 'FASE 2A: merge dedup pelanggan (10 grup/23 baris) + remap FK (idempotent)';

    private const FK_TABLES = [
        'tiket_servis', 'piutang', 'return_penjualan', 'transaksi',
        'komisi', 'komplain_pelanggan', 'skema_komisi_reseller', 'utang',
    ];

    private const SENTINEL = ['1899-12-30', '0000-00-00', '1900-01-01', ''];

    private array $counts = [
        'grup' => 0, 'baris_dup' => 0, 'remap_fk' => 0, 'remap_map' => 0, 'hapus' => 0, 'grup_error' => 0,
    ];

    /** @var array<string,array> group key → detail (canonical kode/id, dup kode/id list) */
    private array $groups = [];

    /** @var array<string,int> remap dihitung per tabel */
    private array $remapPerTabel = [];

    /** @var array<string,string> error per grup */
    private array $grupErrors = [];

    public function handle(): int
    {
        $dry = (bool) $this->option('dry-run');
        if ($dry) {
            $this->warn('DRY-RUN: tidak ada perubahan DB');
        }

        // kode_lama → id pelanggan app
        $idByKode = [];
        DB::table('pelanggan')->select(['id', 'kode_lama', 'nama'])->whereNotNull('kode_lama')->orderBy('id')->chunk(500, function ($chunk) use (&$idByKode) {
            foreach ($chunk as $r) {
                $idByKode[(string) $r->kode_lama] = ['id' => (int) $r->id, 'nama' => (string) $r->nama];
            }
        });

        $this->buildGroups($idByKode);
        $this->mergePerGrup($dry, $idByKode);

        $this->tulisLaporan($dry);
        $this->printSummary($dry);

        return 0;
    }

    /** Kelompokkan staging pelanggan by (UPPER(nama), telepon); canonical = join terlama, tiebreak kode terkecil. */
    private function buildGroups(array $idByKode): void
    {
        $rows = [];
        DB::table('sid_retail_raw_pelanggan')->select(['id', 'kode_sumber', 'payload_normal'])->orderBy('id')->chunk(200, function ($chunk) use (&$rows) {
            foreach ($chunk as $r) {
                $p = json_decode($r->payload_normal, true);
                if (! is_array($p)) {
                    continue;
                }
                $rows[] = [
                    'kode' => trim((string) $r->kode_sumber),
                    'nama' => strtoupper(trim((string) ($p['nama'] ?? ''))),
                    'telp' => trim((string) ($p['telp'] ?? '')),
                    'join' => $this->normalDate($p['join_date'] ?? null) ?? '9999-12-31',
                    'nama_raw' => trim((string) ($p['nama'] ?? '')),
                ];
            }
        });

        $byKey = [];
        foreach ($rows as $r) {
            $byKey[$r['nama'].'|'.$r['telp']][] = $r;
        }

        foreach ($byKey as $key => $list) {
            if (count($list) < 2) {
                continue;
            }
            usort($list, function ($a, $b) {
                $c = strcmp($a['join'], $b['join']);
                if ($c !== 0) {
                    return $c;
                }

                return (int) $a['kode'] <=> (int) $b['kode'];
            });

            $canonKode = $list[0]['kode'];
            $dupKodes = array_values(array_map(fn ($r) => $r['kode'], array_slice($list, 1)));

            // resolve app ids (canonical + dup harus ada di app; jika tidak → grup error, skip)
            if (! isset($idByKode[$canonKode])) {
                $this->grupErrors[$key] = "canonical kode `{$canonKode}` tidak ada di pelanggan.kode_lama";

                continue;
            }
            $canonId = $idByKode[$canonKode]['id'];
            $dupIds = [];
            foreach ($dupKodes as $k) {
                if (! isset($idByKode[$k])) {
                    $this->grupErrors[$key] = "dup kode `{$k}` tidak ada di pelanggan.kode_lama";

                    continue 2;
                }
                $dupIds[$k] = $idByKode[$k]['id'];
            }
            if (! $dupIds) {
                continue;
            }

            $this->groups[$key] = [
                'nama_raw' => $list[0]['nama_raw'],
                'telp' => (string) $list[0]['telp'],
                'canon_kode' => $canonKode,
                'canon_id' => $canonId,
                'dup' => $dupIds, // kode → id
            ];
            $this->counts['grup']++;
            $this->counts['baris_dup'] += count($dupIds);
        }
    }

    private function mergePerGrup(bool $dry, array $idByKode): void
    {
        foreach ($this->groups as $key => $g) {
            $dupIds = array_values($g['dup']);
            $canonId = $g['canon_id'];

            // 1) remap FK
            foreach (self::FK_TABLES as $tabel) {
                $n = DB::table($tabel)->whereIn('pelanggan_id', $dupIds)->count();
                if ($n > 0) {
                    $this->counts['remap_fk'] += $n;
                    $this->remapPerTabel[$tabel] = ($this->remapPerTabel[$tabel] ?? 0) + $n;
                    if (! $dry) {
                        DB::table($tabel)->whereIn('pelanggan_id', $dupIds)->update(['pelanggan_id' => $canonId]);
                    }
                }
            }

            // 2) remap sid_import_map (entity_type='pelanggan')
            $n = DB::table('sid_import_map')->where('entity_type', 'pelanggan')->whereIn('entity_id', $dupIds)->count();
            if ($n > 0) {
                $this->counts['remap_map'] += $n;
                if (! $dry) {
                    DB::table('sid_import_map')->where('entity_type', 'pelanggan')->whereIn('entity_id', $dupIds)
                        ->update(['entity_id' => $canonId, 'updated_at' => now()]);
                }
            }

            // 3) hapus dup
            $this->counts['hapus'] += count($dupIds);
            if (! $dry) {
                DB::table('pelanggan')->whereIn('id', $dupIds)->delete();
            }
        }
    }

    // ---------------- LAPORAN ----------------

    private function tulisLaporan(bool $dry): void
    {
        $dir = storage_path('app/migrasi-sid');
        if (! is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $file = $dir.'/A03-merge-pelanggan-laporan-'.date('Ymd-His').'.md';

        $lines = [];
        foreach ($this->groups as $key => $g) {
            $dups = [];
            foreach ($g['dup'] as $kode => $id) {
                $dups[] = "`{$kode}` (id {$id})";
            }
            $lines[] = sprintf('- **%s** (telp `%s`) — canonical `%s` (id %d) ← dup: %s',
                $g['nama_raw'], $g['telp'], $g['canon_kode'], $g['canon_id'], implode(', ', $dups));
        }

        $body = "# LAPORAN A-03 MERGE DEDUP PELANGGAN — ".date('Y-m-d H:i:s')."\n\n"
            . 'Perintah: `php artisan sid:merge-pelanggan-dup'.($dry ? ' --dry-run' : '')."` (idempotent, remap FK + hapus dup)\n\n"
            . "## Ringkasan\n\n"
            . "- Grup dedup (nama, telepon): **{$this->counts['grup']}**\n"
            . "- Baris dup dihapus: **{$this->counts['hapus']}**\n"
            . "- FK remap ke canonical: **{$this->counts['remap_fk']}** (".$this->remapDetail().")\n"
            . "- sid_import_map remap: **{$this->counts['remap_map']}**\n"
            . "- Grup error (skip): **{$this->counts['grup_error']}**\n\n"
            . "## Daftar grup\n\n"
            . implode("\n", $lines)."\n\n"
            . "## Error\n\n"
            . ($this->grupErrors ? implode("\n", array_map(fn ($k, $v) => "- **{$k}**: {$v}", array_keys($this->grupErrors), array_values($this->grupErrors)))."\n" : "- (tidak ada)\n");

        file_put_contents($file, $body);
        $this->info("Laporan: {$file}");
        $this->info("\nCatatan: khusus `PELANGGAN UMUM` ×4 → canonical `0001` + 3 remap & hapus (tercakup daftar di atas).");
    }

    private function remapDetail(): string
    {
        if (! $this->remapPerTabel) {
            return '0';
        }
        $parts = [];
        foreach ($this->remapPerTabel as $t => $n) {
            $parts[] = "{$t} {$n}";
        }

        return implode(', ', $parts);
    }

    private function printSummary(bool $dry): void
    {
        $this->info("\n=== SUMMARY A-03 ===");
        foreach ($this->counts as $k => $v) {
            $this->line("  {$k}: {$v}");
        }

        $this->info("\n=== VERIFIKASI (state DB) ===");
        $this->line('  pelanggan total: '.DB::table('pelanggan')->count());
        $this->line('  pelanggan kode_lama: '.DB::table('pelanggan')->whereNotNull('kode_lama')->count());
        $this->line('  pelanggan canonical kode_lama masih ada: '.DB::table('pelanggan')->whereIn('kode_lama', ['0012', '0116', '0001', '0156', '0158', '0006', '0064', '0009', '0014', '0043'])->count());

        $dupIds = [];
        foreach ($this->groups as $g) {
            foreach ($g['dup'] as $id) {
                $dupIds[] = (int) $id;
            }
        }
        $dupIds = array_unique($dupIds);
        $sisa = 0;
        foreach (self::FK_TABLES as $tabel) {
            if ($dupIds) {
                $sisa += DB::table($tabel)->whereIn('pelanggan_id', $dupIds)->count();
            }
        }
        $this->line('  FK tersisa ke id dup (semua tabel): '.$sisa);
        $this->line('  pelanggan id dup masih ada: '.($dupIds ? DB::table('pelanggan')->whereIn('id', $dupIds)->count() : 0));
    }

    private function normalDate(mixed $v): ?string
    {
        if ($v === null || $v === '') {
            return null;
        }
        $s = trim((string) $v);
        if (in_array($s, self::SENTINEL, true)) {
            return null;
        }
        try {
            return \Carbon\Carbon::parse($s)->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
    }
}