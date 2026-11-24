<?php

namespace App\Console\Commands;

use App\Modules\Sync\Services\HybridSyncProcessorService;
use Illuminate\Console\Command;
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
        if (! config('sync.enabled')) {
            $this->warn('Hybrid sync sedang dinonaktifkan (SYNC_ENABLED=false).');

            return Command::SUCCESS;
        }

        if (config('sync.node_role') !== 'primary_store') {
            $this->warn('Command sync:pull hanya dijalankan pada node primary_store (toko lokal).');

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
        $apiSecret = config('sync.api_secret');

        $totalProcessed = 0;
        $totalFailed = 0;
        $iteration = 0;

        do {
            $iteration++;
            $queryParams = ['limit' => $limit];
            if (! empty($cabangId)) {
                $queryParams['cabang_id'] = $cabangId;
            }

            $endpoint = rtrim($remoteUrl, '/').'/api/internal/sync/pull?'.http_build_query($queryParams);
            $timestamp = time();
            $signature = hash_hmac('sha256', $timestamp.'.', $apiSecret);

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
                    $this->error("Gagal menarik data dari remote node (HTTP {$response->status()}): ".$response->body());

                    return Command::FAILURE;
                }

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
                    if ($result['success'] ?? false) {
                        $batchProcessed++;
                        $processedUuids[] = $item['sync_uuid'];
                    } else {
                        $batchFailed++;
                        $this->warn("Gagal memproses event {$item['sync_uuid']}: ".($result['message'] ?? 'Unknown error'));
                    }
                }

                $totalProcessed += $batchProcessed;
                $totalFailed += $batchFailed;

                // Kirim ACK ke VPS untuk item yang berhasil diproses agar tidak ditarik ulang
                if (! empty($processedUuids)) {
                    $ackPayload = json_encode(['sync_uuids' => $processedUuids]);
                    $ackTs = time();
                    $ackSig = hash_hmac('sha256', $ackTs.'.'.$ackPayload, $apiSecret);
                    Http::timeout(10)->withHeaders([
                        'X-Sync-Node' => config('sync.node_id'),
                        'X-Sync-Signature' => $ackSig,
                        'X-Sync-Timestamp' => (string) $ackTs,
                        'Content-Type' => 'application/json',
                    ])->withBody($ackPayload, 'application/json')->post(rtrim($remoteUrl, '/').'/api/internal/sync/ack');
                }

                // Jika tidak memakai opsi --all atau jumlah yang ditarik kurang dari limit, hentikan loop
                if (! $pullAll || $count < $limit) {
                    break;
                }
            } catch (\Throwable $e) {
                $this->error('Koneksi ke remote node terputus: '.$e->getMessage());

                return Command::FAILURE;
            }
        } while ($pullAll);

        $this->info("Sinkronisasi pull selesai: {$totalProcessed} diproses, {$totalFailed} gagal.");

        return Command::SUCCESS;
    }
}
