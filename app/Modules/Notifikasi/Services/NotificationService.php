<?php

namespace App\Modules\Notifikasi\Services;

use App\Modules\Notifikasi\Jobs\KirimNotifikasiJob;
use App\Modules\Notifikasi\Models\NotifikasiKeluar;
use Illuminate\Support\Facades\Queue;

class NotificationService
{
    /**
     * Dispatch notifikasi via queue (database driver).
     * Channel: wa, email, inapp — provider WA keputusan user (Fonnte/Wablas placeholder siap).
     *
     * @param  string  $tipe  wa | email | inapp
     * @param  string|null  $tujuan  nomor HP / email
     * @param  int|null  $kampanyeBroadcastId  campaign log relationship, when applicable
     */
    public function kirim(
        string $tipe,
        ?string $tujuan,
        string $judul,
        string $konten,
        array $payload = [],
        ?int $kampanyeBroadcastId = null
    ): NotifikasiKeluar {
        $audience = $this->resolveAudience($payload);

        $kategori = $payload['kategori'] ?? $this->deteksiKategori($judul, $konten, $payload);
        $cabangId = $payload['cabang_id'] ?? (session()->has('cabang_id') ? session('cabang_id') : null);

        $payloadUrl = $payload['action_url'] ?? $payload['url'] ?? null;
        $payloadUrlIsString = is_string($payloadUrl) && $payloadUrl !== '';

        if ($audience === 'pelanggan') {
            $pelangganId = $payload['pelanggan_id'] ?? (auth('customer')->check() ? auth('customer')->id() : null);
            $userId = null;
            $targetRole = null;

            if ($payloadUrlIsString && ! $this->isStaffZoneUrl($payloadUrl)) {
                $actionUrl = $payloadUrl;
            } else {
                $actionUrl = '/account';
            }
        } else {
            $pelangganId = null;
            $userId = $payload['user_id'] ?? (auth()->check() ? auth()->id() : null);
            $targetRole = $payload['target_role'] ?? $payload['role'] ?? $this->deteksiTargetRole($kategori);

            if ($payloadUrlIsString && ! NotifikasiKeluar::isConsumerZoneUrl($payloadUrl)) {
                $actionUrl = $payloadUrl;
            } else {
                $actionUrl = $this->deteksiActionUrl($kategori, $payload, $judul);
            }
        }

        $log = NotifikasiKeluar::create([
            'tipe' => $tipe,
            'kategori' => $kategori,
            'tujuan' => $tujuan,
            'judul' => $judul,
            'konten' => $konten,
            'payload' => $payload,
            'status' => 'pending',
            'kampanye_broadcast_id' => $kampanyeBroadcastId,
            'user_id' => $userId,
            'pelanggan_id' => $pelangganId,
            'cabang_id' => $cabangId,
            'target_role' => $targetRole,
            'action_url' => $actionUrl,
        ]);

        // Dispatch via queue — database driver + Supervisor (PRD §4.9 §7)
        Queue::later(0, new KirimNotifikasiJob($log->id));

        return $log;
    }

    /**
     * Resolve audience notifikasi: 'staf' (backoffice) atau 'pelanggan' (marketplace).
     * Harus dipanggil SEBELUM nilai payload diturunkan, karena `??` pada key
     * dengan nilai literal null jatuh ke default.
     */
    protected function resolveAudience(array $payload): string
    {
        // 1. Explicit payload
        $audience = $payload['audience'] ?? null;
        if (in_array($audience, ['staf', 'pelanggan'], true)) {
            return $audience;
        }

        // 2. Pelanggan id dari payload
        if (($payload['pelanggan_id'] ?? null) !== null) {
            return 'pelanggan';
        }

        // 3. Dari action_url / url
        $url = $payload['action_url'] ?? $payload['url'] ?? null;
        if (is_string($url) && $url !== '') {
            if (NotifikasiKeluar::isConsumerZoneUrl($url)) {
                return 'pelanggan';
            }
            if (str_starts_with($url, 'http') || str_starts_with($url, '/app')) {
                return 'staf';
            }
        }

        // 4. Konteks login customer tanpa login staf
        if (auth('customer')->check() && ! auth()->check()) {
            return 'pelanggan';
        }

        // 5. Default
        return 'staf';
    }

    protected function isStaffZoneUrl(string $url): bool
    {
        return str_starts_with($url, '/app') || str_starts_with($url, 'http');
    }

    protected function deteksiKategori(string $judul, string $konten, array $payload): string
    {
        $text = strtolower($judul.' '.$konten.' '.($payload['tipe'] ?? ''));

        if (str_contains($text, 'stok') || str_contains($text, 'reorder') || str_contains($text, 'opname') || str_contains($text, 'po ') || str_contains($text, 'gudang')) {
            return 'stok';
        }

        if (str_contains($text, 'servis') || str_contains($text, 'tiket') || str_contains($text, 'teknisi') || str_contains($text, 'garansi')) {
            return 'servis';
        }

        if (str_contains($text, 'transaksi') || str_contains($text, 'kasir') || str_contains($text, 'pesanan') || str_contains($text, 'bayar') || str_contains($text, 'order')) {
            return 'transaksi';
        }

        if (str_contains($text, 'approval') || str_contains($text, 'persetujuan') || str_contains($text, 'workflow')) {
            return 'approval';
        }

        if (str_contains($text, 'jurnal') || str_contains($text, 'neraca') || str_contains($text, 'depresiasi') || str_contains($text, 'keuangan') || str_contains($text, 'pajak') || str_contains($text, 'aset')) {
            return 'keuangan';
        }

        return 'sistem';
    }

    protected function deteksiTargetRole(string $kategori): ?string
    {
        return match ($kategori) {
            'stok' => 'staff-gudang',
            'servis' => 'teknisi',
            'transaksi' => 'kasir',
            'approval' => 'admin-toko',
            'keuangan' => 'finance',
            default => null,
        };
    }

    protected function deteksiActionUrl(string $kategori, array $payload, string $judul = ''): ?string
    {
        if ($kategori === 'transaksi') {
            $isOnline = ($payload['sumber'] ?? '') === 'marketplace'
                || ($payload['is_online'] ?? false)
                || str_contains(strtolower($judul), 'online')
                || str_contains(strtolower($judul), 'marketplace');

            return $isOnline ? '/app/pos/online-orders' : '/app/pos/riwayat';
        }

        return match ($kategori) {
            'stok' => '/app/wms',
            'servis' => '/app/servis',
            'approval' => '/app/approvals',
            'keuangan' => '/app/akunting',
            'sistem' => '/app/pengaturan',
            default => null,
        };
    }
}
