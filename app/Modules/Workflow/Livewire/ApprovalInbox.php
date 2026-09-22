<?php

namespace App\Modules\Workflow\Livewire;

use App\Modules\Workflow\Services\ApprovalService;
use Illuminate\Foundation\Validation\ValidationException;
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
            app(ApprovalService::class)->proses($requestId, 'approved', auth()->id(), $this->catatan);
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
            app(ApprovalService::class)->proses($requestId, 'rejected', auth()->id(), $this->catatan);
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
        $requests = ApprovalRequest::with(['rule', 'requestedBy'])
            ->where('approver_role', function ($query) {
                $query->whereIn('approver_role', auth()->user()->getRoleNames()->toArray())
                    ->orWhereHas('rule', function ($q) {
                        $q->where('approver_role', auth()->user()->getRoleNames()->first());
                    });
            })
            ->when($this->search, fn ($q) => $q->where('entity_type', 'like', "%{$this->search}%")
                ->orWhere('payload_json->amount', 'like', "%{$this->search}%")
                ->orWhere('payload_json->no_po', 'like', "%{$this->search}%")
            )
            ->latest()
            ->paginate(10);

        return view('modules.workflow.livewire.approval-inbox', [
            'requests' => $requests,
        ]);
    }
}