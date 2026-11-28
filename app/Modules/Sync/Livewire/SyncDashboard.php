<?php

namespace App\Modules\Sync\Livewire;

use App\Modules\Sync\Http\Controllers\SyncStatusController;
use App\Modules\Sync\Models\HybridSyncInbox;
use App\Modules\Sync\Models\HybridSyncOutbox;
use Illuminate\Support\Facades\Artisan;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.backoffice')]
class SyncDashboard extends Component
{
    public array $status = [];

    public array $outbox = [];

    public array $inbox = [];

    public array $tables = [];

    public string $message = '';

    public function mount(): void
    {
        $this->refreshStatus();
    }

    public function refreshStatus(): void
    {
        $this->status = app(SyncStatusController::class)->buildStatusData(probeRemote: true);

        $this->outbox = HybridSyncOutbox::query()
            ->latest()
            ->limit(12)
            ->get(['sync_uuid', 'event_type', 'status', 'attempts', 'last_error', 'created_at', 'synced_at'])
            ->map(fn ($row) => [
                'sync_uuid' => (string) $row->sync_uuid,
                'event_type' => (string) $row->event_type,
                'status' => (string) $row->status,
                'attempts' => (int) $row->attempts,
                'error' => (string) ($row->last_error ?? ''),
                'created_at' => optional($row->created_at)->format('d M H:i:s'),
                'synced_at' => optional($row->synced_at)->format('d M H:i:s'),
            ])
            ->all();

        $this->inbox = HybridSyncInbox::query()
            ->latest()
            ->limit(12)
            ->get(['sync_uuid', 'event_type', 'source_node', 'status', 'error_message', 'created_at', 'processed_at'])
            ->map(fn ($row) => [
                'sync_uuid' => (string) $row->sync_uuid,
                'event_type' => (string) $row->event_type,
                'source_node' => (string) $row->source_node,
                'status' => (string) $row->status,
                'error' => (string) ($row->error_message ?? ''),
                'created_at' => optional($row->created_at)->format('d M H:i:s'),
            ])
            ->all();

        try {
            $this->tables = app(SyncStatusController::class)->buildStatusData()['tables'] ?? [];
        } catch (\Throwable) {
            $this->tables = [];
        }
    }

    public function triggerSync(): void
    {
        $this->authorize('pengaturan.manage');

        if (! ($this->status['enabled'] ?? false)) {
            $this->message = 'Sinkronisasi tidak aktif.';

            return;
        }

        try {
            if (($this->status['node_role'] ?? '') === 'primary_store') {
                Artisan::call('sync:flush', ['--batch' => true, '--sync-now' => true]);
                Artisan::call('sync:pull', ['--all' => true]);
            } else {
                Artisan::call('sync:flush', ['--batch' => true, '--sync-now' => true]);
            }
            $this->message = 'Sinkronisasi manual berhasil dijalankan.';
        } catch (\Throwable $e) {
            $this->message = 'Gagal: '.$e->getMessage();
        }

        $this->refreshStatus();
    }

    public function bootstrap(): void
    {
        $this->authorize('pengaturan.manage');

        if (($this->status['node_role'] ?? '') !== 'primary_store') {
            $this->message = 'Bootstrap hanya untuk node client (primary_store).';

            return;
        }

        try {
            Artisan::call('sync:bootstrap', ['--limit' => 200]);
            $this->message = 'Bootstrap dari cloud selesai.';
        } catch (\Throwable $e) {
            $this->message = 'Gagal bootstrap: '.$e->getMessage();
        }

        $this->refreshStatus();
    }

    public function render()
    {
        return view('modules.sync.livewire.sync-dashboard');
    }
}
