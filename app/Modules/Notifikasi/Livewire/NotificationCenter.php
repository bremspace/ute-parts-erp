<?php

namespace App\Modules\Notifikasi\Livewire;

use App\Models\UserPreference;
use App\Modules\Notifikasi\Models\NotifikasiKeluar;
use App\Modules\Notifikasi\Services\NotificationService;
use App\Modules\Pos\Models\Transaksi;
use App\Modules\Workflow\Models\ApprovalRequest;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class NotificationCenter extends Component
{
    public string $filter = 'belum_proses'; // 'semua' | 'belum_proses' | 'approval' | 'pengaturan'

    public bool $open = false;

    public int $totalUnprocessedCount = 0;

    public int $unreadNotifCount = 0;

    public int $pendingApprovalCount = 0;

    public int $lastKnownMaxId = 0;

    public int $lastKnownPendingApprovalCount = 0;

    public array $userEvents = ['stok', 'transaksi', 'servis', 'approval', 'keuangan', 'sistem'];

    public static array $availableEvents = [
        'stok' => [
            'label' => 'Stok & Inventori',
            'desc' => 'Peringatan stok minimum, rekomendasi reorder, selisih opname.',
            'color' => 'text-up-primary',
        ],
        'transaksi' => [
            'label' => 'Penjualan & Kasir',
            'desc' => 'Pesanan marketplace baru, pembayaran POS, order masuk.',
            'color' => 'text-up-mint',
        ],
        'servis' => [
            'label' => 'Servis & Reparasi',
            'desc' => 'Booking servis baru konsumen, pengerjaan teknisi, unit selesai.',
            'color' => 'text-indigo-400',
        ],
        'approval' => [
            'label' => 'Persetujuan (Approval)',
            'desc' => 'Permohonan PO supplier, diskon kasir, retur barang, payroll.',
            'color' => 'text-up-amber',
        ],
        'keuangan' => [
            'label' => 'Keuangan & Akuntansi',
            'desc' => 'Penyusutan aset tetap, ekspor pembukuan, tagihan utang/piutang.',
            'color' => 'text-sky-400',
        ],
        'sistem' => [
            'label' => 'Sistem & Operasional',
            'desc' => 'Backup database, import katalog produk, aktivitas keamanan.',
            'color' => 'text-ink-400',
        ],
    ];

    public function mount(): void
    {
        $user = Auth::user();
        if ($user) {
            $this->userEvents = UserPreference::getPref(
                $user->id,
                'notification_events',
                ['stok', 'transaksi', 'servis', 'approval', 'keuangan', 'sistem']
            );
        }

        $this->lastKnownMaxId = (int) (NotifikasiKeluar::query()
            ->where('tipe', 'inapp')
            ->max('id') ?? 0);

        $this->refreshCounts();
        $this->lastKnownPendingApprovalCount = $this->pendingApprovalCount;
    }

    public function toggleEventPreference(string $kategori): void
    {
        if (in_array($kategori, $this->userEvents)) {
            $this->userEvents = array_values(array_diff($this->userEvents, [$kategori]));
        } else {
            $this->userEvents[] = $kategori;
        }

        $user = Auth::user();
        if ($user) {
            UserPreference::setPref($user->id, 'notification_events', $this->userEvents);
        }

        $this->refreshCounts();

        $this->dispatch('alert', [
            'type' => 'success',
            'message' => 'Preferensi notifikasi disimpan.',
            'sound' => false,
        ]);
    }

    public function selectAllEventPreferences(): void
    {
        $this->userEvents = array_keys(self::$availableEvents);
        $user = Auth::user();
        if ($user) {
            UserPreference::setPref($user->id, 'notification_events', $this->userEvents);
        }
        $this->refreshCounts();
    }

    public function refreshCounts(): void
    {
        $user = Auth::user();
        $cabangId = session('cabang_id');

        // Otomatis sinkronkan/selesaikan notifikasi usang jika entitasnya telah berubah status
        app(NotificationService::class)->autoResolveOutdatedInapp($user, $cabangId);

        $this->unreadNotifCount = NotifikasiKeluar::query()
            ->where('tipe', 'inapp')
            ->whereNull('read_at')
            ->forUserRoleAndCategory($user, $cabangId, $this->userEvents)
            ->count();

        $this->pendingApprovalCount = $this->getPendingApprovalCount();
        $this->totalUnprocessedCount = $this->unreadNotifCount + $this->pendingApprovalCount;
    }

    /**
     * Active polling hook (wire:poll.8s)
     */
    public function checkNewNotifications(): void
    {
        $user = Auth::user();
        $cabangId = session('cabang_id');

        // Check newest in-app notifications according to user role and preferred event categories
        $newest = NotifikasiKeluar::query()
            ->where('tipe', 'inapp')
            ->where('id', '>', $this->lastKnownMaxId)
            ->forUserRoleAndCategory($user, $cabangId, $this->userEvents)
            ->orderBy('id', 'asc')
            ->get();

        if ($newest->isNotEmpty()) {
            foreach ($newest as $item) {
                $this->dispatch('ute:notification-arrived',
                    id: $item->id,
                    judul: $item->judul ?? 'Notifikasi Baru',
                    konten: $item->konten,
                    tipe: $item->payload['level'] ?? $item->payload['type'] ?? 'info',
                    action_url: NotifikasiKeluar::safeActionUrlForStaff($item->action_url, $item->kategori)
                );
            }
        }

        // Advance lastKnownMaxId to the GLOBAL max id (scoped or not) so rows that don't
        // match this user's role/cabang/kategori don't get rescanned on every poll cycle.
        // Dispatch still uses only the scoped $newest above — this is just a watermark.
        $globalMaxId = (int) (NotifikasiKeluar::query()
            ->where('tipe', 'inapp')
            ->where('id', '>', $this->lastKnownMaxId)
            ->max('id') ?? 0);

        $this->lastKnownMaxId = max($this->lastKnownMaxId, $globalMaxId);

        // Check new pending approvals if approval event is enabled
        if (in_array('approval', $this->userEvents) && $user && $user->can('approve-workflow')) {
            $currentApprovalCount = $this->getPendingApprovalCount();
            if ($currentApprovalCount > $this->lastKnownPendingApprovalCount) {
                $diff = $currentApprovalCount - $this->lastKnownPendingApprovalCount;
                $this->dispatch('ute:notification-arrived',
                    id: 'approval-'.now()->timestamp,
                    judul: 'Permintaan Approval Baru',
                    konten: "Terdapat {$diff} permohonan persetujuan baru yang memerlukan tindakan Anda.",
                    tipe: 'approval',
                    action_url: '/app/approvals'
                );
            }
            $this->lastKnownPendingApprovalCount = $currentApprovalCount;
        }

        $this->refreshCounts();
    }

    public function toggleDropdown(): void
    {
        $this->open = ! $this->open;
    }

    public function closeDropdown(): void
    {
        $this->open = false;
    }

    public function setFilter(string $filter): void
    {
        $this->filter = $filter;
    }

    public function markAsRead(int $id): void
    {
        $notif = NotifikasiKeluar::where('tipe', 'inapp')
            ->forUserRoleAndCategory(Auth::user(), session('cabang_id'), $this->userEvents)
            ->find($id);
        if ($notif) {
            $notif->update(['read_at' => now()]);
            $this->refreshCounts();
            $this->dispatch('alert', [
                'type' => 'success',
                'message' => 'Notifikasi ditandai sudah diproses.',
                'sound' => false,
            ]);
        }
    }

    public function markAllAsRead(): void
    {
        $user = Auth::user();
        $cabangId = session('cabang_id');

        NotifikasiKeluar::query()
            ->where('tipe', 'inapp')
            ->whereNull('read_at')
            ->forUserRoleAndCategory($user, $cabangId, $this->userEvents)
            ->update(['read_at' => now()]);

        $this->refreshCounts();

        $this->dispatch('alert', [
            'type' => 'success',
            'message' => 'Semua notifikasi ditandai selesai.',
            'sound' => false,
        ]);
    }

    public function bukaAksi(int $id)
    {
        $notif = NotifikasiKeluar::where('tipe', 'inapp')
            ->forUserRoleAndCategory(Auth::user(), session('cabang_id'), $this->userEvents)
            ->find($id);
        if ($notif) {
            $notif->update(['read_at' => now()]);
            $this->refreshCounts();
            $url = NotifikasiKeluar::safeActionUrlForStaff($notif->action_url, $notif->kategori);
            if ($url) {
                return redirect($url);
            }
        }
    }

    protected function getPendingApprovalCount(): int
    {
        $user = Auth::user();
        if (! $user || ! $user->can('approve-workflow') || ! in_array('approval', $this->userEvents)) {
            return 0;
        }

        $cabangId = session('cabang_id');

        $query = ApprovalRequest::query()
            ->where('status', 'pending')
            ->where(fn ($q) => $q->whereNull('cabang_id')->orWhere('cabang_id', $cabangId));

        if (! $user->hasRole('super-admin') && ! $user->hasRole('owner')) {
            $userRoles = $user->getRoleNames()->toArray();
            $query->where(function ($q) use ($userRoles) {
                $q->whereNull('approver_role')->orWhereIn('approver_role', $userRoles);
            });
        }

        return $query->count();
    }

    public function render(): View
    {
        $user = Auth::user();
        $cabangId = session('cabang_id');

        $this->refreshCounts();

        // Query notification list based on user role and event preferences
        $query = NotifikasiKeluar::query()
            ->where('tipe', 'inapp')
            ->forUserRoleAndCategory($user, $cabangId, $this->userEvents)
            ->orderBy('id', 'desc');

        if ($this->filter === 'belum_proses') {
            $query->whereNull('read_at');
        }

        $notifications = $query->limit(20)->get();

        $transaksiIds = $notifications->pluck('payload.transaksi_id')->filter()->unique();
        $transaksiMap = $transaksiIds->isNotEmpty()
            ? Transaksi::whereIn('id', $transaksiIds)->get(['id', 'no_transaksi', 'status'])->keyBy('id')
            : collect();

        $pendingApprovals = collect();
        if (($this->filter === 'semua' || $this->filter === 'belum_proses' || $this->filter === 'approval')
            && in_array('approval', $this->userEvents)
            && $user && $user->can('approve-workflow')) {
            $approvalQuery = ApprovalRequest::query()
                ->where('status', 'pending')
                ->where(fn ($q) => $q->whereNull('cabang_id')->orWhere('cabang_id', $cabangId))
                ->orderBy('created_at', 'desc');

            if (! $user->hasRole('super-admin') && ! $user->hasRole('owner')) {
                $userRoles = $user->getRoleNames()->toArray();
                $approvalQuery->where(function ($q) use ($userRoles) {
                    $q->whereNull('approver_role')->orWhereIn('approver_role', $userRoles);
                });
            }

            $pendingApprovals = $approvalQuery->limit(5)->get();
        }

        return view('modules.notifikasi.livewire.notification-center', [
            'unreadNotifCount' => $this->unreadNotifCount,
            'pendingApprovalCount' => $this->pendingApprovalCount,
            'totalUnprocessedCount' => $this->totalUnprocessedCount,
            'notifications' => $notifications,
            'pendingApprovals' => $pendingApprovals,
            'filter' => $this->filter,
            'open' => $this->open,
            'userEvents' => $this->userEvents,
            'availableEvents' => self::$availableEvents,
            'transaksiMap' => $transaksiMap,
        ]);
    }
}
