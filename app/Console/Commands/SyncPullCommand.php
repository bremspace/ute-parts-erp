<?php

namespace App\Console\Commands;

use App\Modules\Sync\Providers\SyncServiceProvider;
use App\Modules\Sync\Services\HybridSyncProcessorService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class SyncPullCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'sync:pull {--limit=50 : Jumlah maksimum event yang ditarik per batch} {--cabang_id= : Cabang ID spesifik} {--all : Tarik semua antrean hingga habis}';

    /**
     * The console command description.
     */
    protected $description = 'Tarik event baru dari VPS (mode pull untuk primary_store lokal tanpa IP publik)';

    /**
     * Execute the console command.
     */
    public function handle(HybridSyncProcessorService $processor): int
    {
        SyncServiceProvider::refreshRuntimeConfigIfChanged();

        if (! config('sync.enabled')) {
            $this->warn('Hybrid sync sedang dinonaktifkan (SYNC_ENABLED=false).');

            return Command::SUCCESS;
        }

        $remoteUrl = config('sync.remote_url');
        if (empty($remoteUrl)) {
            $this->error('SYNC_REMOTE_URL belum dikonfigurasi di file environment.');

            return Command::FAILURE;
        }

        $limit = max(1, (int) $this->option('limit'));
        $cabangId = $this->option('cabang_id');
        $pullAll = (bool) $this->option('all');
        $apiSecret = (string) config('sync.api_secret', '');

        $totalProcessed = 0;
        $totalFailed = 0;
        $maxIterations = 100;
        $iteration = 0;

        do {
            $iteration++;
            $queryParams = ['limit' => $limit];
            if (! empty($cabangId)) {
                $queryParams['cabang_id'] = $cabangId;
            }
            // Symfony Request::getQueryString() mengurutkan parameter secara alfabetis.
            ksort($queryParams);

            $queryString = http_build_query($queryParams);
            $endpoint = rtrim($remoteUrl, '/').'/api/internal/sync/pull?'.$queryString;
            $timestamp = time();
            $signature = hash_hmac('sha256', $timestamp.'.'.$queryString, $apiSecret);

            $this->info("Menghubungi VPS: {$endpoint} ...");

            try {
                $response = Http::timeout(15)
                    ->withHeaders([
                        'X-Sync-Node' => config('sync.node_id'),
                        'X-Sync-Signature' => $signature,
                        'X-Sync-Timestamp' => (string) $timestamp,
                        'Accept' => 'application/json',
                    ])
                    ->get($endpoint);

                if ($response->failed()) {
                    Cache::put('hybrid_sync:remote_reachable', false, now()->addMinutes(2));
                    $this->error("Gagal menarik data dari remote node (HTTP {$response->status()}): ".$response->body());

                    return Command::FAILURE;
                }

                Cache::put('hybrid_sync:remote_reachable', true, now()->addMinutes(5));

                $items = $response->json('data', []);
                $count = count($items);
                $this->info("Berhasil mengambil {$count} event dari remote node.");

                if ($count === 0) {
                    break;
                }

                $batchProcessed = 0;
                $batchFailed = 0;
                $processedUuids = [];

                foreach ($items as $item) {
                    $result = $processor->process($item);
                    if ($result['success']) {
                        $batchProcessed++;
                        $processedUuids[] = $item['sync_uuid'];
                    } else {
                        $batchFailed++;
                        $this->warn("Gagal memproses event {$item['sync_uuid']}: ".$result['message']);
                    }
                }

                $totalProcessed += $batchProcessed;
                $totalFailed += $batchFailed;

                // Kirim ACK ke VPS untuk item yang berhasil diproses agar tidak ditarik ulang
                $ackOk = true;
                if (! empty($processedUuids)) {
                    $ackPayload = json_encode(['sync_uuids' => $processedUuids]);
                    $ackTs = time();
                    $ackSig = hash_hmac('sha256', $ackTs.'.'.$ackPayload, $apiSecret);
                    $ackResponse = Http::timeout(10)->withHeaders([
                        'X-Sync-Node' => config('sync.node_id'),
                        'X-Sync-Signature' => $ackSig,
                        'X-Sync-Timestamp' => (string) $ackTs,
                        'Content-Type' => 'application/json',
                    ])->withBody($ackPayload, 'application/json')->post(rtrim($remoteUrl, '/').'/api/internal/sync/ack');

                    if ($ackResponse->failed()) {
                        $ackOk = false;
                        $this->warn('Gagal mengirim ACK batch (HTTP '.$ackResponse->status().') — menghentikan iterasi pull agar tidak berulang.');
                    }
                }

                // Hindari loop --all tanpa akhir bila batch penuh gagal semua atau ACK gagal.
                if (! $pullAll || $count < $limit || $batchProcessed === 0 || ! $ackOk) {
                    break;
                }
            } catch (\Throwable $e) {
                Cache::put('hybrid_sync:remote_reachable', false, now()->addMinutes(2));
                $this->error('Koneksi ke remote node terputus: '.$e->getMessage());

                return Command::FAILURE;
            }
        } while ($iteration < $maxIterations);

        $this->info("Sinkronisasi pull selesai: {$totalProcessed} diproses, {$totalFailed} gagal.");

        return Command::SUCCESS;
    }
}
