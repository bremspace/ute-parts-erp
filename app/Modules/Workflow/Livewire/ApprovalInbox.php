<?php

namespace App\Modules\Workflow\Livewire;

use App\Modules\Workflow\Models\ApprovalRequest;
use App\Modules\Workflow\Services\ApprovalService;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Livewire\WithPagination;

class ApprovalInbox extends Component
{
    use WithPagination;

    public $search = '';

    public $selectedRequest = null;

    public $catatan = '';

    public $processing = false;

    protected $rules = [
        'catatan' => 'nullable|string|max:255',
    ];

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function approveRequest($requestId)
    {
        $this->validate(['catatan' => 'nullable|string|max:255']);
        $this->processing = true;

        try {
            app(ApprovalService::class)->proses($requestId, 'disetujui', auth()->id(), $this->catatan);
            $this->dispatch('alert', ['type' => 'success', 'message' => 'Permintaan disetujui']);
            $this->reset(['selectedRequest', 'catatan']);
        } catch (ValidationException $e) {
            $this->dispatch('alert', ['type' => 'error', 'message' => $e->getMessage()]);
        } catch (\Exception $e) {
            $this->dispatch('alert', ['type' => 'error', 'message' => $e->getMessage()]);
        } finally {
            $this->processing = false;
        }
    }

    public function rejectRequest($requestId)
    {
        $this->validate(['catatan' => 'required|string|max:255']);
        $this->processing = true;

        try {
            app(ApprovalService::class)->proses($requestId, 'ditolak', auth()->id(), $this->catatan);
            $this->dispatch('alert', ['type' => 'success', 'message' => 'Permintaan ditolak']);
            $this->reset(['selectedRequest', 'catatan']);
        } catch (ValidationException $e) {
            $this->dispatch('alert', ['type' => 'error', 'message' => $e->getMessage()]);
        } catch (\Exception $e) {
            $this->dispatch('alert', ['type' => 'error', 'message' => $e->getMessage()]);
        } finally {
            $this->processing = false;
        }
    }

    public function openRejectModal($requestId)
    {
        $this->selectedRequest = $requestId;
        $this->catatan = '';
        $this->dispatch('open-reject-modal');
    }

    public function render()
    {
        $user = auth()->user();
        $roles = $user?->getRoleNames() ?? collect();
        // Pemegang permission approve-workflow boleh melihat seluruh antrean cabang
        // (wajib: super-admin dapat menyetujui request role lain — lihat ApprovalService::proses)
        $seeAll = (bool) $user?->can('approve-workflow');

        $requests = ApprovalRequest::with(['rule', 'requestedBy'])
            ->when(! $seeAll, fn ($q) => $q->whereIn('approver_role', $roles))
            // Scope cabang: request global (null) + cabang aktif sesi — PRD §2.2
            ->where(fn ($q) => $q->whereNull('cabang_id')->orWhere('cabang_id', session('cabang_id')))
            ->when($this->search !== '', fn ($q) => $q->where(function ($w) {
                $w->where('entity_type', 'like', "%{$this->search}%")
                    ->orWhere('payload_json->amount', 'like', "%{$this->search}%")
                    ->orWhere('payload_json->no_po', 'like', "%{$this->search}%")
                    ->orWhere('payload_json->no_return', 'like', "%{$this->search}%")
                    ->orWhere('payload_json->no_transaksi', 'like', "%{$this->search}%");
            }))
            ->latest()
            ->paginate(10);

        return view('modules.workflow.livewire.approval-inbox', [
            'requests' => $requests,
        ])->layout('layouts.backoffice', ['header' => 'Approval Inbox']);
    }
}
