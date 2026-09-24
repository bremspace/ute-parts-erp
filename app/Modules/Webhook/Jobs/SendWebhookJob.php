<?php

namespace App\Modules\Webhook\Jobs;

use App\Modules\Pos\Models\Transaksi;
use App\Modules\Servis\Models\TiketServis;
use App\Modules\Webhook\Enums\WebhookEvent;
use App\Modules\Webhook\Models\WebhookDelivery;
use App\Modules\Webhook\Models\WebhookEndpoint;
use App\Modules\Wms\Models\StokLog;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * [F3-5] Kirim satu delivery webhook outbound (queue database driver — tidak pernah sync).
 *
 * - HMAC signature: X-Ute-Signature: sha256=hash_hmac('sha256', rawBody, secret)
 *   + X-Ute-Timestamp (anti-replay; konsumen wajib tolak selisih > 5 menit — sisi kita hanya sign).
 * - Retry exponential: $tries=5, backoff [15,30,60,120,240] detik.
 * - 4xx permanen (kecuali 408/429): TIDAK di-rethrow → job selesai, status failed.
 * - 5xx / koneksi gagal: rethrow → queue retry dgn backoff; habis percobaan → failed().
 */
class SendWebhookJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    /** @var array<int, int> Backoff exponential (detik) per percobaan. */
    public array $backoff = [15, 30, 60, 120, 240];

    public function __construct(
        public int $deliveryId
    ) {}

    public function handle(): void
    {
        $delivery = WebhookDelivery::find($this->deliveryId);

        // Row belum terlihat (rollback / race) → drop senyap; afterCommit push membuat ini jarang terjadi
        if (! $delivery || $delivery->status === 'delivered') {
            return;
        }

        $endpoint = $delivery->endpoint;
        if (! $endpoint || ! $endpoint->is_aktif) {
            $this->tandaiGagal($delivery, $endpoint, 'Endpoint sudah nonaktif atau dihapus', null);

            return; // permanen — tanpa throw (queue tidak retry)
        }

        $data = $this->bangunPayload($delivery);
        if ($data === null) {
            $this->tandaiGagal($delivery, $endpoint, 'Data sumber tidak ditemukan untuk payload webhook', null);

            return; // permanen — tanpa throw
        }

        $attempt = (int) $delivery->attempt + 1;
        $delivery->update(['attempt' => $attempt]);

        // Envelope canonical — inilah raw body yang di-sign
        $envelope = [
            'event' => $delivery->event,
            'delivery_id' => (int) $delivery->id,
            'occurred_at' => $delivery->created_at?->toIso8601String(),
            'cabang_id' => $data['cabang_id'] ?? null,
            'data' => $data,
        ];
        $raw = json_encode($envelope, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}';

        $timestamp = (string) now()->timestamp;
        $signature = 'sha256='.hash_hmac('sha256', $raw, (string) $endpoint->secret);

        try {
            $response = Http::withHeaders([
                'X-Ute-Signature' => $signature,
                'X-Ute-Timestamp' => $timestamp,
                'X-Ute-Event' => $delivery->event,
                'X-Ute-Delivery-Id' => (string) $delivery->id,
                'User-Agent' => 'UteParts-Webhook/1.0',
            ])->withBody($raw, 'application/json')->timeout(10)->post($endpoint->url);
        } catch (ConnectionException $e) {
            $delivery->update([
                'status' => 'retry',
                'payload_hash' => hash('sha256', $raw),
                'error' => Str::limit('Koneksi gagal: '.$e->getMessage(), 500),
            ]);
            $endpoint->update(['last_delivery_at' => now(), 'last_status' => 'retry']);

            throw $e; // retry dgn backoff exponential
        }

        $code = $response->status();
        $delivery->update([
            'payload_hash' => hash('sha256', $raw),
            'response_code' => $code,
        ]);

        if ($response->successful()) {
            $delivery->update(['status' => 'delivered', 'delivered_at' => now(), 'error' => null]);
            $endpoint->update(['last_delivery_at' => now(), 'last_status' => 'delivered']);

            return;
        }

        $error = Str::limit($response->body(), 500);
        $permanen = $response->clientError() && ! in_array($code, [408, 429], true);

        if ($permanen) {
            // 4xx permanen (kecuali 408/429) → TANPA throw: queue tidak boleh retry
            $this->tandaiGagal($delivery, $endpoint, 'HTTP '.$code.': '.$error, $code);

            Log::warning('[Webhook-outbound] 4xx permanen — tidak di-retry', [
                'delivery_id' => $delivery->id,
                'endpoint' => $endpoint->nama,
                'code' => $code,
            ]);

            return;
        }

        // 5xx / 429 / 408 → tandai retry, lempar agar queue coba lagi (backoff exponential)
        $delivery->update(['status' => 'retry', 'error' => 'HTTP '.$code.': '.$error]);
        $endpoint->update(['last_delivery_at' => now(), 'last_status' => 'retry']);

        throw new RuntimeException("Webhook HTTP {$code} (percobaan ke-{$attempt})");
    }

    /** Dipanggil queue setelah $tries habis → status final failed. */
    public function failed(\Throwable $exception): void
    {
        $delivery = WebhookDelivery::find($this->deliveryId);

        if (! $delivery || $delivery->status === 'delivered') {
            return;
        }

        $delivery->update([
            'status' => 'failed',
            'error' => Str::limit($exception->getMessage(), 500),
        ]);
        $delivery->endpoint?->update(['last_status' => 'failed', 'last_delivery_at' => now()]);
    }

    /**
     * Bangun payload data sesuai event (rowid dari delivery.context).
     * null = data sumber hilang → delivery gagal permanen.
     *
     * @return array<string, mixed>|null
     */
    protected function bangunPayload(WebhookDelivery $delivery): ?array
    {
        $context = $delivery->context ?? [];

        if (! empty($context['test_fire'])) {
            // Payload contoh dari tombol "Test Fire" admin UI
            return [
                'test_fire' => true,
                'pesan' => 'Payload contoh dari Ute Parts — endpoint terhubung dan siap menerima event.',
                'event' => $delivery->event,
                'cabang_id' => null,
            ];
        }

        return match ($delivery->event) {
            WebhookEvent::TransaksiSelesai->value => $this->dataTransaksi((int) ($context['transaksi_id'] ?? 0)),
            WebhookEvent::StokBerubah->value => $this->dataStok((int) ($context['stok_log_id'] ?? 0)),
            WebhookEvent::ServisSelesai->value => $this->dataServis((int) ($context['tiket_servis_id'] ?? 0)),
            default => null,
        };
    }

    /** @return array<string, mixed>|null */
    protected function dataTransaksi(int $transaksiId): ?array
    {
        $transaksi = $transaksiId > 0
            ? Transaksi::with('items')->find($transaksiId)
            : null;

        if (! $transaksi) {
            return null;
        }

        return [
            'transaksi_id' => (int) $transaksi->id,
            'no_transaksi' => $transaksi->no_transaksi,
            'cabang_id' => $transaksi->cabang_id !== null ? (int) $transaksi->cabang_id : null,
            'kasir_id' => $transaksi->kasir_id !== null ? (int) $transaksi->kasir_id : null,
            'sumber' => $transaksi->sumber,
            'status' => $transaksi->status,
            'metode_bayar' => $transaksi->metode_bayar,
            'subtotal' => (float) $transaksi->subtotal,
            'total_akhir' => (float) $transaksi->total_akhir,
            'items' => $transaksi->items->map(fn ($item) => [
                'produk_id' => (int) $item->produk_id,
                'sku_variant_id' => $item->sku_variant_id !== null ? (int) $item->sku_variant_id : null,
                'jumlah' => (int) $item->jumlah,
                'harga_satuan' => (float) $item->harga_satuan,
                'subtotal' => (float) $item->subtotal,
            ])->all(),
        ];
    }

    /** @return array<string, mixed>|null */
    protected function dataStok(int $stokLogId): ?array
    {
        $log = $stokLogId > 0 ? StokLog::with('gudang')->find($stokLogId) : null;

        if (! $log) {
            return null;
        }

        return [
            'stok_log_id' => (int) $log->id,
            'produk_id' => (int) $log->produk_id,
            'sku_variant_id' => $log->sku_variant_id !== null ? (int) $log->sku_variant_id : null,
            'gudang_id' => (int) $log->gudang_id,
            'cabang_id' => $log->gudang?->cabang_id !== null ? (int) $log->gudang->cabang_id : null,
            'jenis' => $log->jenis,
            'jumlah_sebelum' => (int) $log->jumlah_sebelum,
            'perubahan' => (int) $log->perubahan,
            'jumlah_setelah' => (int) $log->jumlah_setelah,
            'referensi_tipe' => $log->referensi_tipe,
            'referensi_id' => $log->referensi_id,
        ];
    }

    /** @return array<string, mixed>|null */
    protected function dataServis(int $tiketServisId): ?array
    {
        $tiket = $tiketServisId > 0 ? TiketServis::find($tiketServisId) : null;

        if (! $tiket) {
            return null;
        }

        return [
            'tiket_servis_id' => (int) $tiket->id,
            'no_tiket' => $tiket->no_tiket,
            'cabang_id' => $tiket->cabang_id !== null ? (int) $tiket->cabang_id : null,
            'pelanggan_id' => $tiket->pelanggan_id !== null ? (int) $tiket->pelanggan_id : null,
            'teknisi_id' => $tiket->teknisi_id !== null ? (int) $tiket->teknisi_id : null,
            'jenis_hp' => $tiket->jenis_hp,
            'status' => $tiket->status,
            'estimasi_biaya' => (float) ($tiket->estimasi_biaya ?? 0),
            'tanggal_selesai' => $tiket->tanggal_selesai?->toIso8601String(),
        ];
    }

    protected function tandaiGagal(WebhookDelivery $delivery, ?WebhookEndpoint $endpoint, string $error, ?int $code): void
    {
        $delivery->update([
            'status' => 'failed',
            'response_code' => $code,
            'error' => Str::limit($error, 500),
        ]);
        $endpoint?->update(['last_delivery_at' => now(), 'last_status' => 'failed']);
    }
}
