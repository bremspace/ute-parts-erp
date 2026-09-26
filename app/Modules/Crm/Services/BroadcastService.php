<?php

namespace App\Modules\Crm\Services;

use App\Modules\Crm\Models\KampanyeBroadcast;
use App\Modules\Crm\Models\Pelanggan;
use App\Modules\Notifikasi\Models\NotifikasiKeluar;
use App\Modules\Notifikasi\Services\NotificationService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * [T-23] Broadcast lengkap: segment, kirim sekarang/jadwal, log per penerima (anti dobel).
 * Prinsip: 1 kampanye → 1 notifikasi per pelanggan; duplikat (konten+target sama) ditolak.
 */
class BroadcastService
{
    /**
     * Segment types yang didukung oleh resolver.
     *
     * @var list<string>
     */
    public const SEGMENT_TYPES = [
        'tier',
        'reseller',
        'belum_belanja_hari',
        'birthday_month',
        'birthday_day',
    ];

    public function __construct(
        protected NotificationService $notifService
    ) {}

    /**
     * Bangun query target di SQL; caller dapat memproses hasilnya per chunk.
     * Eager load tier untuk menghindari N+1 saat mempersonalisasi pesan.
     */
    public function resolveTarget(KampanyeBroadcast $kampanye): Builder
    {
        $target = Pelanggan::query()
            ->with('tierMembership')
            ->orderBy('id');

        $segments = $kampanye->segment ?? [];

        foreach ($segments as $seg) {
            if (! is_array($seg)) {
                continue;
            }

            switch ($seg['tipe'] ?? '') {
                case 'tier':
                    if (array_key_exists('nilai', $seg) && $seg['nilai'] !== null && $seg['nilai'] !== '') {
                        $target->where('tier_membership_id', $seg['nilai']);
                    }
                    break;
                case 'reseller':
                    $isReseller = array_key_exists('nilai', $seg) && $seg['nilai'] !== null
                        ? filter_var($seg['nilai'], FILTER_VALIDATE_BOOLEAN)
                        : true;
                    $target->where('is_reseller', $isReseller);
                    break;
                case 'belum_belanja_hari':
                    if (! empty($seg['nilai'])) {
                        $target->whereDoesntHave(
                            'transaksi',
                            fn ($q) => $q->where('created_at', '>=', now()->subDays((int) $seg['nilai']))
                        );
                    }
                    break;
                case 'birthday_month': // [T-37] Promo ulang tahun: semua pelanggan yg lahir di bulan ini
                    if (! empty($seg['nilai'])) {
                        $target->whereMonth('tanggal_lahir', (int) $seg['nilai']);
                    }
                    break;
                case 'birthday_day': // [T-37] Kombinasi month+day = tepat tanggal lahir
                    if (! empty($seg['nilai'])) {
                        $target->whereDay('tanggal_lahir', (int) $seg['nilai']);
                    }
                    break;
            }
        }

        return $target;
    }

    public function kirimSekarang(KampanyeBroadcast $kampanye): KampanyeBroadcast
    {
        // Anti-dobel: kalau sudah terkirim sukses, jangan kirim ulang
        if ($kampanye->status === 'terkirim') {
            return $kampanye;
        }

        // Idempotency: cek apakah sudah ada notifikasi untuk kampanye ini
        if (NotifikasiKeluar::where('kampanye_broadcast_id', $kampanye->id)->exists()) {
            $kampanye->update(['status' => 'terkirim', 'dikirim_at' => now()]);

            return $kampanye;
        }

        $targets = $this->resolveTarget($kampanye);
        $totalTarget = 0;
        $terkirim = 0;
        $gagal = 0;

        // Jangan get() seluruh pelanggan: query difilter di SQL, lalu outbox ditulis per chunk.
        $targets->chunkById(100, function (Collection $pelangganList) use (
            $kampanye,
            &$totalTarget,
            &$terkirim,
            &$gagal
        ): void {
            foreach ($pelangganList as $pelanggan) {
                $totalTarget++;
                $konten = str_replace(
                    ['{nama}', '{tier}'],
                    [$pelanggan->nama, $pelanggan->tierMembership?->nama ?? 'Member'],
                    $kampanye->pesan
                );

                try {
                    $tujuan = match ($kampanye->channel) {
                        'wa' => $pelanggan->telepon,
                        'email' => $pelanggan->email,
                        default => null,
                    };

                    $this->notifService->kirim(
                        $kampanye->channel,
                        $tujuan,
                        $kampanye->judul,
                        $konten,
                        ['pelanggan_id' => $pelanggan->id, 'kampanye_id' => $kampanye->id],
                        $kampanye->id
                    );
                    $terkirim++;
                } catch (\Throwable) {
                    $gagal++;
                }
            }
        });

        $kampanye->update([
            'status' => $gagal === 0 ? 'terkirim' : 'terkirim_sebagian',
            'dikirim_at' => now(),
            'total_target' => $totalTarget,
            'total_terkirim' => $terkirim,
            'total_gagal' => $gagal,
        ]);

        return $kampanye;
    }

    public function logPengiriman(int $kampanyeId): LengthAwarePaginator
    {
        return NotifikasiKeluar::where('kampanye_broadcast_id', $kampanyeId)
            ->orderByDesc('id')
            ->paginate(100);
    }
}
