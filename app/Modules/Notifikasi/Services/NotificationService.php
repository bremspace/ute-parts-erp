<?php

namespace App\Modules\Notifikasi\Services;

use App\Models\User;
use App\Modules\Notifikasi\Jobs\KirimNotifikasiJob;
use App\Modules\Notifikasi\Models\NotifikasiKeluar;
use App\Modules\Pos\Models\Transaksi;
use App\Modules\Workflow\Models\ApprovalRequest;
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

    /**
     * Otomatis sesuaikan & selesaikan notifikasi terkait transaksi saat statusnya berubah.
     * Mengeliminasi kebutuhan staf/konsumen untuk konfirmasi/tandai selesai secara manual.
     */
    public function sinkronkanStatusTransaksi(Transaksi $transaksi, ?string $statusBaru = null): int
    {
        $status = $statusBaru ?? $transaksi->status;
        $transaksiId = $transaksi->id;
        $resolvedCount = 0;

        $query = NotifikasiKeluar::query()
            ->where('tipe', 'inapp')
            ->whereNull('read_at')
            ->where(function ($q) use ($transaksiId) {
                $q->where('payload->transaksi_id', $transaksiId)
                    ->orWhere('payload->transaksi_id', (string) $transaksiId);
            });

        $activeNotifs = $query->get();

        foreach ($activeNotifs as $notif) {
            $shouldResolve = false;
            $judul = strtolower($notif->judul ?? '');
            $konten = strtolower($notif->konten ?? '');
            $isMenungguBayarNotif = str_contains($judul, 'menunggu pembayaran')
                || str_contains($konten, 'menunggu pembayaran')
                || str_contains($judul, 'pesanan online baru')
                || str_contains($judul, 'pesanan berhasil didaftarkan')
                || str_contains($konten, 'selesaikan pembayaran')
                || ($notif->payload['stage'] ?? '') === 'menunggu_pembayaran';

            // 1. Jika sudah lunas atau tahapan setelahnya: notifikasi menunggu bayar otomatis diselesaikan
            if (in_array($status, ['lunas', 'diproses', 'dikirim', 'siap_diambil', 'selesai'], true) && $isMenungguBayarNotif) {
                $shouldResolve = true;
            }

            // 2. Jika sudah diproses/dikemas: notifikasi instruksi kemas/bayar diselesaikan
            if (in_array($status, ['diproses', 'dikirim', 'siap_diambil', 'selesai'], true)) {
                if (str_contains($judul, 'pembayaran') || str_contains($konten, 'segera siapkan dan kemas')) {
                    $shouldResolve = true;
                }
            }

            // 3. Jika sudah dikirim / siap diambil: notifikasi kemas diselesaikan
            if (in_array($status, ['dikirim', 'siap_diambil', 'selesai'], true)) {
                if (str_contains($judul, 'dikemas') || str_contains($konten, 'sedang dikemas')) {
                    $shouldResolve = true;
                }
            }

            // 4. Jika selesai atau batal: seluruh notifikasi aktif untuk transaksi ini diselesaikan
            if (in_array($status, ['selesai', 'batal', 'kadaluarsa'], true)) {
                $shouldResolve = true;
            }

            if ($shouldResolve) {
                $payload = $notif->payload ?? [];
                $payload['auto_resolved'] = true;
                $payload['resolved_by_status'] = $status;
                $payload['resolved_at'] = now()->toIso8601String();

                $notif->update([
                    'read_at' => now(),
                    'payload' => $payload,
                ]);
                $resolvedCount++;
            }
        }

        return $resolvedCount;
    }

    /**
     * Otomatis selesaikan notifikasi approval saat request telah disetujui atau ditolak.
     */
    public function sinkronkanStatusApproval(ApprovalRequest $request): int
    {
        $requestId = $request->id;
        $resolvedCount = 0;

        $activeNotifs = NotifikasiKeluar::query()
            ->where('tipe', 'inapp')
            ->whereNull('read_at')
            ->where(function ($q) use ($requestId) {
                $q->where('payload->approval_request_id', $requestId)
                    ->orWhere('payload->approval_request_id', (string) $requestId);
            })
            ->get();

        foreach ($activeNotifs as $notif) {
            $payload = $notif->payload ?? [];
            $payload['auto_resolved'] = true;
            $payload['resolved_by_status'] = $request->status;
            $payload['resolved_at'] = now()->toIso8601String();

            $notif->update([
                'read_at' => now(),
                'payload' => $payload,
            ]);
            $resolvedCount++;
        }

        return $resolvedCount;
    }

    /**
     * Self-healing auto-resolve sweep untuk notifikasi in-app yang entitasnya telah berpindah status.
     * Dipanggil secara periodik oleh polling NotificationCenter.
     */
    public function autoResolveOutdatedInapp(?User $user = null, ?int $cabangId = null): int
    {
        $query = NotifikasiKeluar::query()
            ->where('tipe', 'inapp')
            ->whereNull('read_at')
            ->whereNotNull('payload');

        if ($cabangId) {
            $query->where(fn ($q) => $q->whereNull('cabang_id')->orWhere('cabang_id', $cabangId));
        }

        $activeNotifs = $query->limit(50)->get();
        $resolvedCount = 0;

        foreach ($activeNotifs as $notif) {
            $transaksiId = $notif->payload['transaksi_id'] ?? null;
            if ($transaksiId) {
                $transaksi = Transaksi::find($transaksiId);
                if ($transaksi && $transaksi->status !== 'menunggu_pembayaran') {
                    $resolvedCount += $this->sinkronkanStatusTransaksi($transaksi);
                }

                continue;
            }

            $approvalId = $notif->payload['approval_request_id'] ?? null;
            if ($approvalId) {
                $approval = ApprovalRequest::find($approvalId);
                if ($approval && $approval->status !== 'pending') {
                    $resolvedCount += $this->sinkronkanStatusApproval($approval);
                }
            }
        }

        return $resolvedCount;
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
