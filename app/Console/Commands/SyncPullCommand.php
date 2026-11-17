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
    protected $signature = 'sync:pull {--limit=50 : Jumlah maksimum event yang ditarik}';

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

        $limit = (int) $this->option('limit');
        $endpoint = rtrim($remoteUrl, '/').'/api/internal/sync/pull?limit='.$limit;

        $apiSecret = config('sync.api_secret');
        $signature = hash_hmac('sha256', '', $apiSecret);

        $this->info("Menghubungi VPS: {$endpoint} ...");

        try {
            $response = Http::timeout(15)
                ->withHeaders([
                    'X-Sync-Node' => config('sync.node_id'),
                    'X-Sync-Signature' => $signature,
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

            $processed = 0;
            $failed = 0;

            $processedUuids = [];
            foreach ($items as $item) {
                $result = $processor->process($item);
                if ($result['success'] ?? false) {
                    $processed++;
                    $processedUuids[] = $item['sync_uuid'];
                } else {
                    $failed++;
                    $this->warn("Gagal memproses event {$item['sync_uuid']}: ".($result['message'] ?? 'Unknown error'));
                }
            }

            // Kirim ACK ke VPS untuk item yang berhasil diproses agar tidak ditarik ulang
            if (! empty($processedUuids)) {
                $ackPayload = json_encode(['sync_uuids' => $processedUuids]);
                $ackSig = hash_hmac('sha256', $ackPayload, $apiSecret);
                Http::timeout(10)->withHeaders([
                    'X-Sync-Node' => config('sync.node_id'),
                    'X-Sync-Signature' => $ackSig,
                    'Content-Type' => 'application/json',
                ])->withBody($ackPayload, 'application/json')->post(rtrim($remoteUrl, '/').'/api/internal/sync/ack');
            }

            $this->info("Sinkronisasi pull selesai: {$processed} diproses, {$failed} gagal.");

            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('Koneksi ke remote node terputus: '.$e->getMessage());

            return Command::FAILURE;
        }
    }
}
