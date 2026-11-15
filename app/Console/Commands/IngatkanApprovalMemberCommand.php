<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Modules\Notifikasi\Services\NotificationService;
use App\Modules\Workflow\Models\ApprovalRequest;
use Illuminate\Console\Command;

/**
 * Pengingat harian: pendaftaran member yang masih `pending` terlalu lama
 * membuat member tidak bisa login (login diblokir untuk status non-aktif).
 * Perintah ini mengingatkan pemilik toko agar memprosesnya.
 */
class IngatkanApprovalMemberCommand extends Command
{
    protected $signature = 'ute:ingatkan-approval-member';

    protected $description = 'Ingatkan pemilik toko atas pendaftaran member yang masih menunggu approval (mencegah member tak bisa login berhari-hari)';

    public function handle(): int
    {
        $pending = ApprovalRequest::query()
            ->where('entity_type', 'member')
            ->where('status', 'pending')
            ->where('created_at', '<', now()->subHours(12))
            ->get();

        if ($pending->isEmpty()) {
            $this->line('Tidak ada pendaftaran member yang tertunda.');

            return self::SUCCESS;
        }

        $penerima = User::query()
            ->where(function ($query) {
                $query->whereHas('roles', fn ($roles) => $roles->whereIn('name', ['owner', 'super-admin']))
                    ->orWhereHas('permissions', fn ($perms) => $perms->where('name', 'approve-workflow'));
            })
            ->get();

        foreach ($penerima as $user) {
            app(NotificationService::class)->kirim(
                'inapp',
                $user->email,
                $pending->count().' Pendaftaran Member Menunggu Approval',
                'Ada '.$pending->count().' akun member baru yang belum disetujui sehingga belum bisa login. Segera proses di Approval Inbox.',
                [
                    'user_id' => $user->id,
                    'kategori' => 'approval',
                    'action_url' => '/app/approvals',
                    'cabang_id' => null,
                ]
            );
        }

        $this->line("Pengingat terkirim ke {$penerima->count()} penerima untuk {$pending->count()} pendaftaran pending.");

        return self::SUCCESS;
    }
}
