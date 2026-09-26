<?php

namespace App\Modules\Rbac\Livewire;

use App\Modules\Rbac\Models\DeviceSession;
use App\Modules\Rbac\Services\SessionManagementService;
use Livewire\Component;

/**
 * [F3-4] Session Management — device list, force logout per device.
 *
 * RBAC: permission kelola-sesi (admin+superadmin).
 * Desktop-first, Ute Prism.
 */
class SessionManagementPage extends Component
{
    public string $search = '';

    public int $perPage = 15;

    protected $listeners = ['deviceLoggedOut' => '$refresh'];

    public function getDevicesProperty()
    {
        $query = DeviceSession::query()
            ->with('user')
            ->orderBy('last_activity', 'desc');

        if ($this->search) {
            $query->whereHas('user', fn ($q) => $q->where('name', 'like', '%'.$this->search.'%'));
        }

        return $query->paginate($this->perPage);
    }

    public function logoutDevice(string $deviceToken): void
    {
        if (! auth()->user()?->can('kelola-sesi')) {
            $this->dispatch('alert', ['type' => 'error', 'message' => 'Tidak punya izin.']);

            return;
        }

        $service = app(SessionManagementService::class);
        $success = $service->forceLogoutDevice($deviceToken);

        if ($success) {
            $this->dispatch('alert', ['type' => 'success', 'message' => 'Device berhasil logout.']);
            $this->emitSelf('deviceLoggedOut');
        } else {
            $this->dispatch('alert', ['type' => 'error', 'message' => 'Device tidak ditemukan.']);
        }
    }

    public function logoutAll(): void
    {
        if (! auth()->user()?->can('kelola-sesi')) {
            $this->dispatch('alert', ['type' => 'error', 'message' => 'Tidak punya izin.']);

            return;
        }

        $service = app(SessionManagementService::class);
        $count = $service->forceLogoutAll(auth()->id());

        $this->dispatch('alert', ['type' => 'success', 'message' => "$count device berhasil logout."]);
    }

    public function render()
    {
        return view('modules.rbac.livewire.session-management', [
            // Legacy computed (`getDevicesProperty()`) diakses sebagai `$this->devices`,
            // bukan `$this->devicesProperty` — nama property literal tidak ada di komponen.
            'devices' => $this->devices,
        ])->layout('layouts.backoffice', ['header' => 'Session Management']);
    }
}
