<?php

namespace App\Modules\Crm\Services;

use App\Modules\Crm\Models\KampanyeBroadcast;
use App\Modules\Crm\Models\Pelanggan;
use App\Modules\Notifikasi\Models\NotifikasiKeluar;
use App\Modules\Notifikasi\Services\NotificationService;

/**
 * [T-23] Broadcast lengkap: segment, kirim sekarang/jadwal, log per penerima (anti dobel).
 * Prinsip: 1 kampanye → 1 notifikasi per pelanggan; duplikat (konten+target sama) ditolak.
 */
class BroadcastService
{
    public function __construct(
        protected NotificationService $notifService
    ) {}

    public function resolveTarget(KampanyeBroadcast $kampanye): \Illuminate\Support\Collection
    {
        $target = Pelanggan::query()->where('is_active', true);
        $segments = $kampanye->segment ?? [];

        foreach ($segments as $seg) {
            switch ($seg['tipe'] ?? '') {
                case 'tier':
                    if (!empty($seg['nilai'])) {
                        $target->where('tier_membership_id', $seg['nilai']);
                    }
                    break;
                case 'reseller':
                    $target->where('is_reseller', true);
                    break;
                case 'belum_belanja_hari':
                    if (!empty($seg['nilai'])) {
                        $target->whereDoesntHave('transaksi', fn ($q) => $q->where('created_at', '>=', now()->subDays((int) $seg['nilai'])));
                    }
                    break;
            }
        }

        return $target->get();
    }

    public function kirimSekarang(KampanyeBroadcast $kampanye): KampanyeBroadcast
    {
        // Anti-dobel: kalau sudah terkirim sukses, jangan kirim ulang
        if ($kampanye->status === 'terkirim') {
            return $kampanye;
        }

        // Idempotency: cek apakah sudah ada notifikasi utk kampanye ini & channel ini
        if (NotifikasiKeluar::where('kampanye_broadcast_id', $kampanye->id)->exists()) {
            $kampanye->update(['status' => 'terkirim', 'dikirim_at' => now()]);
            return $kampanye;
        }

        $targets = $this->resolveTarget($kampanye);

        $terkirim = 0;
        $gagal = 0;

        foreach ($targets as $pelanggan) {
            $konten = str_replace(
                ['{nama}', '{tier}'],
                [$pelanggan->nama, $pelanggan->tierMembership?->nama ?? 'Member'],
                $kampanye->pesan
            );

            try {
                $log = $this->notifService->kirim(
                    $kampanye->channel,
                    $kampanye->channel === 'wa' ? $pelanggan->telepon : $pelanggan->email,
                    $kampanye->judul,
                    $konten,
                    ['pelanggan_id' => $pelanggan->id, 'kampanye_id' => $kampanye->id]
                );
                $log->update(['kampanye_broadcast_id' => $kampanye->id]);
                $terkirim++;
            } catch (\Throwable) {
                $gagal++;
            }
        }

        $kampanye->update([
            'status' => $gagal === 0 ? 'terkirim' : 'terkirim_sebagian',
            'dikirim_at' => now(),
            'total_target' => $targets->count(),
            'total_terkirim' => $terkirim,
            'total_gagal' => $gagal,
        ]);

        return $kampanye;
    }

    public function logPengiriman(int $kampanyeId): \Illuminate\Support\Collection
    {
        return NotifikasiKeluar::where('kampanye_broadcast_id', $kampanyeId)
            ->orderBy('id')
            ->get();
    }
}