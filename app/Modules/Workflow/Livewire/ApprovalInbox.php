<?php

namespace App\Modules\Workflow\Livewire;

use App\Modules\Crm\Models\Pelanggan;
use App\Modules\Crm\Models\TierMembership;
use App\Modules\Workflow\Models\ApprovalRequest;
use App\Modules\Workflow\Services\ApprovalService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Livewire\WithPagination;

class ApprovalInbox extends Component
{
    use WithPagination;

    public $search = '';

    public $selectedRequest = null;

    public $catatan = '';

    public $showRejectModal = false;

    public $showApproveMemberModal = false;

    public $showApproveResetPasswordModal = false;

    public $resetPasswordApprovalForm = [
        'request_id' => null,
        'pelanggan_id' => null,
        'nama' => '',
        'telepon' => '',
        'username' => '',
        'new_password' => '',
        'alasan' => '',
        'catatan' => '',
    ];

    public $memberApprovalForm = [
        'request_id' => null,
        'pelanggan_id' => null,
        'nama' => '',
        'telepon' => '',
        'tipe_konsumen' => 'retail',
        'tier_membership_id' => '',
        'kode_agen' => '',
        'referral_kode' => '',
        'catatan' => '',
    ];

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
        $request = ApprovalRequest::find($requestId);
        if ($request && $request->entity_type === 'member') {
            $this->openApproveMemberModal($requestId);

            return;
        }

        if ($request && $request->entity_type === 'reset_password_pelanggan') {
            $this->openApproveResetPasswordModal($requestId);

            return;
        }

        $this->validate(['catatan' => 'nullable|string|max:255']);
        $this->processing = true;

        try {
            app(ApprovalService::class)->proses($requestId, 'disetujui', auth()->id(), $this->catatan);
            Cache::forget('backoffice-approval-badge-'.(session('cabang_id') ?? 'all'));
            Cache::forget('backoffice-approval-badge-all');
            $this->dispatch('alert', ['type' => 'success', 'message' => 'Permintaan disetujui']);
            $this->reset(['selectedRequest', 'catatan', 'showRejectModal']);
        } catch (ValidationException $e) {
            $msg = collect($e->errors())->flatten()->first() ?? $e->getMessage();
            $this->dispatch('alert', ['type' => 'error', 'message' => $msg]);
        } catch (\Exception $e) {
            $this->dispatch('alert', ['type' => 'error', 'message' => $e->getMessage()]);
        } finally {
            $this->processing = false;
        }
    }

    public function openApproveMemberModal($requestId)
    {
        $request = ApprovalRequest::findOrFail($requestId);
        $pelanggan = Pelanggan::find($request->entity_id);
        $defaultTier = TierMembership::where('is_active', true)->orderBy('urutan')->first();

        $this->memberApprovalForm = [
            'request_id' => $request->id,
            'pelanggan_id' => $pelanggan?->id,
            'nama' => $pelanggan?->nama ?? ($request->payload_json['nama'] ?? '-'),
            'telepon' => $pelanggan?->telepon ?? ($request->payload_json['telepon'] ?? '-'),
            'tipe_konsumen' => $pelanggan?->tipe_konsumen ?: 'retail',
            'tier_membership_id' => $pelanggan?->tier_membership_id ?: ($defaultTier?->id ?? ''),
            'kode_agen' => $pelanggan?->kode_agen ?? '',
            'referral_kode' => $pelanggan?->referral_kode ?? '',
            'catatan' => '',
        ];
        $this->showApproveMemberModal = true;
    }

    public function closeApproveMemberModal()
    {
        $this->showApproveMemberModal = false;
        $this->reset('memberApprovalForm');
    }

    public function openApproveResetPasswordModal($requestId)
    {
        $request = ApprovalRequest::findOrFail($requestId);
        $pelanggan = Pelanggan::find($request->entity_id);

        $this->resetPasswordApprovalForm = [
            'request_id' => $request->id,
            'pelanggan_id' => $pelanggan?->id,
            'nama' => $pelanggan?->nama ?? ($request->payload_json['nama'] ?? '-'),
            'telepon' => $pelanggan?->telepon ?? ($request->payload_json['telepon'] ?? '-'),
            'username' => $pelanggan?->username ?? ($request->payload_json['username'] ?? '-'),
            'new_password' => $request->payload_json['new_password'] ?? '',
            'alasan' => $request->payload_json['alasan'] ?? ($request->catatan ?? 'Lupa password via CS/Toko'),
            'catatan' => '',
        ];
        $this->showApproveResetPasswordModal = true;
    }

    public function closeApproveResetPasswordModal()
    {
        $this->showApproveResetPasswordModal = false;
        $this->reset('resetPasswordApprovalForm');
    }

    public function approveResetPasswordSubmit()
    {
        $this->validate([
            'resetPasswordApprovalForm.new_password' => 'required|string|min:6',
            'resetPasswordApprovalForm.catatan' => 'nullable|string|max:255',
        ], [
            'resetPasswordApprovalForm.new_password.required' => 'Kata sandi baru wajib diisi.',
            'resetPasswordApprovalForm.new_password.min' => 'Kata sandi baru minimal 6 karakter.',
        ]);

        $this->processing = true;

        try {
            app(ApprovalService::class)->proses(
                $this->resetPasswordApprovalForm['request_id'],
                'disetujui',
                auth()->id(),
                $this->resetPasswordApprovalForm['catatan'] ?: 'Reset password disetujui',
                ['new_password' => $this->resetPasswordApprovalForm['new_password']]
            );

            Cache::forget('backoffice-approval-badge-'.(session('cabang_id') ?? 'all'));
            Cache::forget('backoffice-approval-badge-all');

            $this->dispatch('alert', [
                'type' => 'success',
                'message' => "Reset kata sandi pelanggan '{$this->resetPasswordApprovalForm['nama']}' berhasil disetujui.",
            ]);

            $this->closeApproveResetPasswordModal();
        } catch (ValidationException $e) {
            $msg = collect($e->errors())->flatten()->first() ?? $e->getMessage();
            $this->dispatch('alert', ['type' => 'error', 'message' => $msg]);
        } catch (\Exception $e) {
            $this->dispatch('alert', ['type' => 'error', 'message' => $e->getMessage()]);
        } finally {
            $this->processing = false;
        }
    }

    public function confirmApproveMember()
    {
        $this->validate([
            'memberApprovalForm.tipe_konsumen' => 'required|in:retail,reseller,agen',
            'memberApprovalForm.tier_membership_id' => 'nullable|exists:tier_memberships,id',
            'memberApprovalForm.kode_agen' => 'nullable|string|max:40',
            'memberApprovalForm.referral_kode' => 'nullable|string|max:40',
            'memberApprovalForm.catatan' => 'nullable|string|max:255',
        ]);

        $this->processing = true;

        try {
            app(ApprovalService::class)->proses(
                $this->memberApprovalForm['request_id'],
                'disetujui',
                auth()->id(),
                $this->memberApprovalForm['catatan'] ?: null,
                $this->memberApprovalForm
            );

            Cache::forget('backoffice-approval-badge-'.(session('cabang_id') ?? 'all'));
            Cache::forget('backoffice-approval-badge-all');

            $label = match ($this->memberApprovalForm['tipe_konsumen']) {
                'agen' => 'Agen / Mitra B2B',
                'reseller' => 'Reseller',
                default => 'Member Biasa (Retail)',
            };

            $this->dispatch('alert', [
                'type' => 'success',
                'message' => "Pendaftaran member '{$this->memberApprovalForm['nama']}' disetujui sebagai {$label}",
            ]);

            $this->closeApproveMemberModal();
        } catch (ValidationException $e) {
            $msg = collect($e->errors())->flatten()->first() ?? $e->getMessage();
            $this->dispatch('alert', ['type' => 'error', 'message' => $msg]);
        } catch (\Exception $e) {
            $this->dispatch('alert', ['type' => 'error', 'message' => $e->getMessage()]);
        } finally {
            $this->processing = false;
        }
    }

    public function rejectRequest()
    {
        $this->validate(['catatan' => 'required|string|max:255'], [
            'catatan.required' => 'Alasan penolakan wajib diisi',
        ]);
        $this->processing = true;

        try {
            app(ApprovalService::class)->proses($this->selectedRequest, 'ditolak', auth()->id(), $this->catatan);
            Cache::forget('backoffice-approval-badge-'.(session('cabang_id') ?? 'all'));
            Cache::forget('backoffice-approval-badge-all');
            $this->dispatch('alert', ['type' => 'success', 'message' => 'Permintaan ditolak']);
            $this->reset(['selectedRequest', 'catatan', 'showRejectModal']);
        } catch (ValidationException $e) {
            $msg = collect($e->errors())->flatten()->first() ?? $e->getMessage();
            $this->dispatch('alert', ['type' => 'error', 'message' => $msg]);
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
        $this->showRejectModal = true;
    }

    public function closeRejectModal()
    {
        $this->reset(['selectedRequest', 'catatan', 'showRejectModal']);
    }

    public function render()
    {
        $user = auth()->user();
        $roles = $user?->getRoleNames() ?? collect();
        // Pemegang permission approve-workflow boleh melihat seluruh antrean cabang
        // (wajib: super-admin dapat menyetujui request role lain — lihat ApprovalService::proses)
        $seeAll = (bool) $user?->can('approve-workflow');

        $requests = ApprovalRequest::with(['rule', 'requestedBy', 'actionedBy'])
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
            'tiers' => TierMembership::where('is_active', true)->orderBy('urutan')->get(),
        ])->layout('layouts.backoffice', ['header' => 'Approval Inbox']);
    }
}
