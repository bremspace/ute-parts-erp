<?php

namespace App\Console\Commands;

use App\Modules\Sync\Services\HybridSyncProcessorService;
use Illuminate\Console\Command;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

class SyncBootstrapCommand extends Command
{
    protected $signature = 'sync:bootstrap
        {--table= : Hanya bootstrap satu tabel}
        {--limit=200 : Jumlah baris per halaman}
        {--force : Paksa seed penuh (transaksi + snapshot stok) walau data lokal sudah ada}';

    protected $description = 'Tarik data canonical dari cloud ke client (primary_store) sekali saat awal';

    public function handle(HybridSyncProcessorService $processor): int
    {
        if (! config('sync.enabled')) {
            $this->warn('Hybrid sync sedang dinonaktifkan (SYNC_ENABLED=false).');

            return Command::SUCCESS;
        }

        if (config('sync.node_role') !== 'primary_store') {
            $this->warn('sync:bootstrap hanya dijalankan pada node client (primary_store).');

            return Command::SUCCESS;
        }

        $remoteUrl = rtrim((string) config('sync.remote_url'), '/');
        if ($remoteUrl === '') {
            $this->error('SYNC_REMOTE_URL belum dikonfigurasi.');

            return Command::FAILURE;
        }

        $secret = (string) config('sync.api_secret', '');
        $nodeId = (string) config('sync.node_id', 'STORE-01');
        $limit = max(1, min(500, (int) $this->option('limit')));

        $tablesResponse = $this->signedGet($remoteUrl.'/api/internal/sync/bootstrap', [], $nodeId, $secret);
        if ($tablesResponse === null || ! $tablesResponse->successful()) {
            $this->error('Gagal mengambil daftar tabel bootstrap dari cloud.');

            return Command::FAILURE;
        }

        $tables = collect($tablesResponse->json('data.tables', []));
        $onlyTable = $this->option('table');
        $force = (bool) $this->option('force');
        $isFresh = ! Schema::hasTable('produk') || DB::table('produk')->count() === 0;
        $fullSeed = $force || $isFresh;

        if ($fullSeed && $onlyTable === null) {
            $seedTables = $tables->whereIn('table', ['transaksi', 'stok_items'])->pluck('table')->all();
            if (! in_array('transaksi', $seedTables, true) || ! in_array('stok_items', $seedTables, true)) {
                $this->warn('Cloud belum menyediakan seed transaksi/stok. Deploy kode sync terbaru ke cloud agar seed penuh bekerja.');
            }
        }

        if (! $fullSeed && $onlyTable === null) {
            $this->info('Data lokal sudah ada — bootstrap hanya master data. Gunakan --force untuk seed penuh.');
            $tables = $tables->whereNotIn('table', ['transaksi', 'stok_items']);
        }

        if ($onlyTable) {
            $tables = $tables->where('table', $onlyTable);
        }

        if ($tables->isEmpty()) {
            $this->warn('Tidak ada tabel canonical untuk di-bootstrap.');

            return Command::SUCCESS;
        }

        $total = 0;
        foreach ($tables as $info) {
            $table = (string) $info['table'];
            $count = (int) ($info['count'] ?? 0);
            $offset = 0;
            $tableProcessed = 0;

            $this->info("Bootstrap {$table} ({$count} baris)...");

            do {
                $query = ['table' => $table, 'limit' => $limit, 'offset' => $offset];
                $queryString = http_build_query($query);
                $response = $this->signedGet($remoteUrl.'/api/internal/sync/bootstrap?'.$queryString, $query, $nodeId, $secret);
                if ($response === null || ! $response->successful()) {
                    $this->error("Gagal mengambil {$table} offset {$offset}.");
                    break;
                }

                $events = $response->json('data.events', []);
                if (! is_array($events) || $events === []) {
                    break;
                }

                foreach ($events as $event) {
                    // Cloud lama bisa mengirim sync_uuid kosong pada master lawas.
                    // Beri UUID deterministik agar event tetap idempotent saat di-seed.
                    if (! is_array($event) || empty($event['sync_uuid'])) {
                        if (! is_array($event)) {
                            continue;
                        }
                        $event['sync_uuid'] = 'seed-'.substr(
                            sha1($table.'|'.json_encode($event['payload'] ?? [])),
                            0,
                            31
                        );
                    }

                    $result = $processor->process($event);
                    if ($result['success']) {
                        $tableProcessed++;
                        $total++;
                    } else {
                        $this->warn('Gagal proses '.(string) ($event['sync_uuid'] ?? '-').': '.$result['message']);
                    }
                }

                $offset += count($events);
            } while ($offset < $count);

            $this->info("  {$table}: {$tableProcessed} baris tersinkron.");
        }

        Cache::put('hybrid_sync:remote_reachable', true, now()->addMinutes(5));
        $this->info("Bootstrap selesai: {$total} record tersinkron dari cloud.");

        if ($fullSeed && $onlyTable === null) {
            File::put(storage_path('.synced_from_cloud'), now()->toIso8601String());
        }

        return Command::SUCCESS;
    }

    /**
     * @param  array<string, mixed>  $query
     */
    private function signedGet(string $url, array $query, string $nodeId, string $secret): ?Response
    {
        $timestamp = (string) time();
        // Cloud menyortir parameter query sebelum HMAC (lihat SyncPullCommand).
        ksort($query);
        $queryString = http_build_query($query);
        $signature = hash_hmac('sha256', $timestamp.'.'.$queryString, $secret);

        try {
            return Http::timeout(30)
                ->withHeaders([
                    'Accept' => 'application/json',
                    'X-Sync-Node' => $nodeId,
                    'X-Sync-Timestamp' => $timestamp,
                    'X-Sync-Signature' => $signature,
                ])
                ->get($url);
        } catch (\Throwable $e) {
            $this->error('Koneksi cloud gagal: '.$e->getMessage());

            return null;
        }
    }
}
