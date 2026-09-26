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
            $sent = match ($log->tipe) {
                'email' => $this->kirimEmail($log),
                'wa' => $this->kirimWhatsApp($log),
                default => true, // inapp — handled by database notification, no outbound
            };

            // Metode channel yang gagal sudah menandai status failed. Jangan
            // menimpanya menjadi terkirim hanya karena match selesai.
            if ($sent) {
                $log->update(['status' => 'terkirim', 'error' => null]);
            }
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

    protected function kirimEmail(NotifikasiKeluar $log): bool
    {
        if (! filled($log->tujuan)) {
            $this->tandaiGagal($log, 'Tujuan email belum tersedia.');

            return false;
        }

        // Menggunakan mail driver dari config (log di local, SMTP di production)
        Mail::raw($log->konten, function ($message) use ($log) {
            $message->to($log->tujuan)
                ->subject($log->judul ?? 'Notifikasi Ute Parts');
        });

        return true;
    }

    protected function kirimWhatsApp(NotifikasiKeluar $log): bool
    {
        /**
         * Placeholder untuk integrasi WA gateway.
         * User akan memilih provider: Fonnte / Wablas / WA Business API.
         * Konfigurasi minimal disimpan di config/services.php dan .env.
         *
         * Tanpa kredensial, job HARUS gagal; status terkirim hanya boleh
         * ditetapkan setelah provider benar-benar dikonfigurasi.
         */
        $wa = config('services.wa', []);
        if (! is_array($wa) || blank($wa['url'] ?? null) || blank($wa['token'] ?? null)) {
            $this->tandaiGagal(
                $log,
                'gateway WA belum dikonfigurasi. Atur kredensial gateway WA sebelum mengirim.'
            );

            return false;
        }

        Log::channel('stack')->info('WA GATEWAY DISPATCH (placeholder)', [
            'tujuan' => $log->tujuan,
            'judul' => $log->judul,
            'konten' => $log->konten,
            'status' => 'akan_dikirim_ketika_provider_dikonfigurasi',
        ]);

        // Swap dengan HTTP client saat provider aktif:
        // Http::withHeaders([...])->post(config('services.wa.url'), [...]);
        return true;
    }

    protected function tandaiGagal(NotifikasiKeluar $log, string $error): void
    {
        $log->update([
            'status' => 'gagal',
            'error' => $error,
        ]);

        Log::warning("Notifikasi {$log->tipe} gagal: {$error}", [
            'notifikasi_id' => $log->id,
            'tujuan' => $log->tujuan,
        ]);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error("KirimNotifikasiJob permanent fail for notifikasi #{$this->notifikasiId}: {$exception->getMessage()}");
    }
}
