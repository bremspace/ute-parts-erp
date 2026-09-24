<?php

namespace App\Modules\Webhook\Services;

use App\Modules\Webhook\Enums\WebhookEvent;
use App\Modules\Webhook\Jobs\SendWebhookJob;
use App\Modules\Webhook\Models\WebhookDelivery;
use App\Modules\Webhook\Models\WebhookEndpoint;
use Closure;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * [F3-5] Dispatcher event webhook OUTBOUND (G-16) — mirror rigor webhook Duitku:
 * whitelist event ketat, scoping cabang, delivery log, dispatch QUEUE (tidak pernah sync).
 *
 * Silent-safe: tabel webhook_endpoint kosong → no-op TANPA noise log;
 * kegagalan internal tidak pernah melempar exception ke request asal.
 */
class WebhookDispatcher
{
    /**
     * Kirim event ke semua endpoint aktif yang berlangganan.
     *
     * @param  array  $context  Rowid sumber (mis. ['transaksi_id' => 1]) — job membangun payload saat running (post-commit).
     * @param  int|Closure|null  $cabangId  Cabang asal event; Closure di-resolve hanya bila ada endpoint (hemat query saat tabel kosong).
     * @return int Jumlah delivery yang dibuat (0 = silent skip).
     */
    public function kirim(string $event, array $context = [], int|Closure|null $cabangId = null): int
    {
        try {
            // 1. Whitelist — string bebas di luar enum DITOLAK
            if (! WebhookEvent::valid($event)) {
                Log::warning('[Webhook-outbound] Event di luar whitelist — dispatch dibatalkan', ['event' => $event]);

                return 0;
            }

            // 2. Endpoint aktif berlangganan event ini (indexed query, hasil kosong → senyap)
            $endpoints = WebhookEndpoint::query()
                ->where('is_aktif', true)
                ->whereJsonContains('events', $event)
                ->get();

            if ($endpoints->isEmpty()) {
                return 0;
            }

            // 3. Scoping cabang: endpoint global (null) selalu cocok; endpoint cabang hanya utk cabangnya
            $resolvedCabangId = $cabangId instanceof Closure ? $cabangId() : $cabangId;
            $matched = $endpoints->filter(fn (WebhookEndpoint $ep) => $ep->cabang_id === null
                || ($resolvedCabangId !== null && (int) $ep->cabang_id === (int) $resolvedCabangId));

            if ($matched->isEmpty()) {
                return 0;
            }

            // 4. Buat delivery log (pending) + antre job (afterCommit — aman dari race commit)
            $created = 0;
            foreach ($matched as $endpoint) {
                $delivery = WebhookDelivery::create([
                    'webhook_endpoint_id' => $endpoint->id,
                    'event' => $event,
                    'context' => $context,
                    'status' => 'pending',
                    'attempt' => 0,
                ]);

                SendWebhookJob::dispatch((int) $delivery->id)->afterCommit();
                $created++;
            }

            return $created;
        } catch (Throwable $e) {
            // Silent failure aman: webhook TIDAK boleh merusak request asal
            Log::warning('[Webhook-outbound] Gagal menyiapkan delivery: '.$e->getMessage(), ['event' => $event]);

            return 0;
        }
    }
}
