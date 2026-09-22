<?php

namespace App\Modules\Notifikasi\Jobs;

use App\Modules\Notifikasi\Models\NotifikasiKeluar;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Queue job untuk kirim notifikasi outbound (WA/email/inapp).
 * Provider WA (Fonnte/Wablas) placeholder — user pilih provider, hook siap.
 *
 * PRD §4.9 & §7: notifikasi wajib lewat queue (database driver + Supervisor).
 */
class KirimNotifikasiJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 60;

    public function __construct(
        public int $notifikasiId
    ) {}

    public function handle(): void
    {
        $log = NotifikasiKeluar::find($this->notifikasiId);

        if (! $log || $log->status === 'terkirim') {
            return;
        }

        try {
            match ($log->tipe) {
                'email' => $this->kirimEmail($log),
                'wa' => $this->kirimWhatsApp($log),
                default => null, // inapp — handled by database notification, no outbound
            };

            $log->update(['status' => 'terkirim']);
        } catch (\Exception $e) {
            $log->update([
                'status' => 'gagal',
                'error' => $e->getMessage(),
            ]);

            Log::error("Notifikasi {$log->tipe} gagal: {$e->getMessage()}", [
                'notifikasi_id' => $log->id,
                'tujuan' => $log->tujuan,
            ]);

            throw $e; // re-throw agar queue retry
        }
    }

    protected function kirimEmail(NotifikasiKeluar $log): void
    {
        if (! $log->tujuan) {
            return;
        }

        // Menggunakan mail driver dari config (log di local, SMTP di production)
        Mail::raw($log->konten, function ($message) use ($log) {
            $message->to($log->tujuan)
                ->subject($log->judul ?? 'Notifikasi Ute Parts');
        });
    }

    protected function kirimWhatsApp(NotifikasiKeluar $log): void
    {
        /**
         * Placeholder untuk integrasi WA gateway.
         * User akan memilih provider: Fonnte / Wablas / WA Business API.
         * Konfigurasi via .env: WA_GATEWAY_URL, WA_GATEWAY_TOKEN, WA_GATEWAY_DEVICE_ID
         *
         * Saat provider belum dikonfigurasi, hanya log output.
         * Struktur request siap, tinggal swap HTTP client + auth:
         *
         * POST https://{WA_GATEWAY_URL}/api/v1/messages
         * Headers: Authorization: Bearer {WA_GATEWAY_TOKEN}
         * Body: { phone: $log->tujuan, message: $log->konten, device_id: env('WA_GATEWAY_DEVICE_ID') }
         */
        Log::channel('stack')->info('WA GATEWAY DISPATCH (placeholder)', [
            'tujuan' => $log->tujuan,
            'judul' => $log->judul,
            'konten' => $log->konten,
            'status' => 'akan_dikirim_ketika_provider_dikonfigurasi',
        ]);

        // Swap dengan HTTP client saat provider aktif:
        // Http::withHeaders([...])->post(env('WA_GATEWAY_URL') . '/api/v1/messages', [...]);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error("KirimNotifikasiJob permanent fail for notifikasi #{$this->notifikasiId}: {$exception->getMessage()}");
    }
}
