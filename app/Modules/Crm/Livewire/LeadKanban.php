<?php

namespace App\Modules\Crm\Livewire;

use App\Models\User;
use App\Modules\Crm\Models\Lead;
use App\Modules\Crm\Services\LeadService;
use Livewire\Component;
use Livewire\WithPagination;

class LeadKanban extends Component
{
    use WithPagination;

    public string $search = '';

    public ?string $filterSumber = null;

    public ?int $filterAssignedTo = null;

    public bool $showCreateModal = false;

    public array $createForm = [
        'sumber' => 'walkin',
        'stage' => 'baru',
        'nama' => '',
        'telepon' => '',
        'email' => '',
        'nilai_estimasi' => 0,
        'assigned_to' => null,
        'catatan' => '',
    ];

    public ?int $editingLeadId = null;

    public array $editForm = [];

    public bool $showConvertModal = false;

    public ?int $convertLeadId = null;

    protected $listeners = ['leadCreated', 'leadUpdated', 'leadDeleted', 'refreshKanban' => '$refresh'];

    public function mount(): void
    {
        $this->resetCreateForm();
    }

    public function getStagesProperty(): array
    {
        return [
            'baru' => ['label' => 'Baru', 'color' => 'ink'],
            'kontak' => ['label' => 'Kontak', 'color' => 'primary'],
            'kualifikasi' => ['label' => 'Kualifikasi', 'color' => 'mint'],
            'negosiasi' => ['label' => 'Negosiasi', 'color' => 'amber'],
            'won' => ['label' => 'Menang', 'color' => 'mint'],
            'lost' => ['label' => 'Kalah', 'color' => 'red'],
        ];
    }

    public function getSalesUsersProperty()
    {
        return User::whereHas('roles', fn ($q) => $q->where('name', 'marketing'))
            ->orWhereHas('roles', fn ($q) => $q->where('name', 'admin-toko'))
            ->orWhereHas('roles', fn ($q) => $q->where('name', 'super-admin'))
            ->get(['id', 'name']);
    }

    public function getSumberOptionsProperty(): array
    {
        return [
            'walkin' => 'Walk-in',
            'phone' => 'Telepon',
            'website' => 'Website',
            'referral' => 'Referral',
            'social_media' => 'Media Sosial',
            'marketplace' => 'Marketplace',
            'lain' => 'Lainnya',
        ];
    }

    public function getLostReasonOptionsProperty(): array
    {
        return [
            'harga' => 'Harga',
            'kompetitor' => 'Kompetitor',
            'tidak_butuh' => 'Tidak Butuh',
            'lain' => 'Lainnya',
        ];
    }

    public function getKanbanDataProperty(): array
    {
        $query = Lead::with(['assignedTo', 'pelanggan'])
            ->forCabang()
            ->orderBy('stage')
            ->orderBy('created_at', 'desc');

        if ($this->search) {
            $query->where(function ($q) {
                $q->where('nama', 'like', "%{$this->search}%")
                    ->orWhere('telepon', 'like', "%{$this->search}%")
                    ->orWhere('email', 'like', "%{$this->search}%");
            });
        }

        if ($this->filterSumber) {
            $query->where('sumber', $this->filterSumber);
        }

        if ($this->filterAssignedTo) {
            $query->where('assigned_to', $this->filterAssignedTo);
        }

        $leads = $query->get();

        $grouped = [];
        foreach (array_keys($this->stages) as $stage) {
            $grouped[$stage] = $leads->where('stage', $stage)->values();
        }

        return $grouped;
    }

    public function getSummaryProperty(): array
    {
        $leads = collect($this->kanbanData)->flatten();
        $totalValue = $leads->sum('nilai_estimasi');
        $wonValue = $leads->where('stage', 'won')->sum('nilai_estimasi');
        $lostCount = $leads->where('stage', 'lost')->count();
        $wonCount = $leads->where('stage', 'won')->count();

        return [
            'total' => $leads->count(),
            'total_nilai' => $totalValue,
            'won_nilai' => $wonValue,
            'lost_count' => $lostCount,
            'conversion_rate' => $leads->count() > 0 ? round(($wonCount / $leads->count()) * 100, 1) : 0,
        ];
    }

    public function resetCreateForm(): void
    {
        $this->createForm = [
            'sumber' => 'walkin',
            'stage' => 'baru',
            'nama' => '',
            'telepon' => '',
            'email' => '',
            'nilai_estimasi' => 0,
            'assigned_to' => null,
            'catatan' => '',
        ];
    }

    public function openCreateModal(): void
    {
        $this->resetCreateForm();
        $this->showCreateModal = true;
    }

    public function createLead(): void
    {
        $this->validate([
            'createForm.nama' => 'required|string|max:255',
            'createForm.telepon' => 'nullable|string|max:20|unique:leads,telepon',
            'createForm.email' => 'nullable|email|unique:leads,email',
            'createForm.nilai_estimasi' => 'nullable|numeric|min:0',
            'createForm.assigned_to' => 'nullable|exists:users,id',
        ]);

        $data = $this->createForm;
        $data['cabang_id'] = session('cabang_id');

        app(LeadService::class)->create($data);

        $this->showCreateModal = false;
        $this->dispatch('alert', ['type' => 'success', 'message' => 'Lead baru berhasil dibuat']);
    }

    public function openEditModal(int $leadId): void
    {
        $lead = Lead::forCabang()->findOrFail($leadId);
        $this->editingLeadId = $leadId;
        $this->editForm = $lead->toArray();
    }

    public function updateLead(): void
    {
        $this->validate([
            'editForm.nama' => 'required|string|max:255',
            'editForm.telepon' => 'nullable|string|max:20|unique:leads,telepon,'.$this->editingLeadId,
            'editForm.email' => 'nullable|email|unique:leads,email,'.$this->editingLeadId,
            'editForm.nilai_estimasi' => 'nullable|numeric|min:0',
            'editForm.assigned_to' => 'nullable|exists:users,id',
        ]);

        $lead = Lead::forCabang()->findOrFail($this->editingLeadId);
        app(LeadService::class)->update($lead, $this->editForm);

        $this->editingLeadId = null;
        $this->editForm = [];
        $this->dispatch('alert', ['type' => 'success', 'message' => 'Lead berhasil diperbarui']);
    }

    public function updateStage(int $leadId, string $stage): void
    {
        if (! array_key_exists($stage, $this->stages)) {
            $this->dispatch('alert', ['type' => 'error', 'message' => 'Tahap tidak valid.']);

            return;
        }

        $lead = Lead::forCabang()->findOrFail($leadId);
        app(LeadService::class)->update($lead, ['stage' => $stage]);

        $this->dispatch('alert', ['type' => 'success', 'message' => "Lead dipindahkan ke tahap {$this->stages[$stage]['label']}"]);
    }

    public function confirmConvert(int $leadId): void
    {
        $lead = Lead::forCabang()->findOrFail($leadId);
        if (! $lead->canConvert()) {
            $this->dispatch('alert', ['type' => 'error', 'message' => 'Lead tidak dapat dikonversi: harus stage Won dan belum memiliki pelanggan.']);

            return;
        }
        $this->convertLeadId = $leadId;
        $this->showConvertModal = true;
    }

    public function convertLead(): void
    {
        $lead = Lead::forCabang()->findOrFail($this->convertLeadId);
        $pelanggan = app(LeadService::class)->convertToPelanggan($lead);

        $this->showConvertModal = false;
        $this->convertLeadId = null;
        $this->dispatch('alert', ['type' => 'success', 'message' => "Lead berhasil dikonversi ke pelanggan #{$pelanggan->id}"]);
    }

    public function confirmDelete(int $leadId): void
    {
        $this->dispatch('confirm-delete', [
            'leadId' => $leadId,
            'message' => 'Yakin hapus lead ini? Tindakan ini tidak bisa dibatalkan.',
        ]);
    }

    public function deleteLead(int $leadId): void
    {
        $lead = Lead::forCabang()->findOrFail($leadId);
        app(LeadService::class)->delete($lead);
        $this->dispatch('alert', ['type' => 'success', 'message' => 'Lead berhasil dihapus']);
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedFilterSumber(): void
    {
        $this->resetPage();
    }

    public function updatedFilterAssignedTo(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        return view('modules.crm.livewire.lead-kanban', [
            'kanbanData' => $this->kanbanData,
            'summary' => $this->summary,
            'stages' => $this->stages,
            'sumberOptions' => $this->sumberOptions,
            'salesUsers' => $this->salesUsers,
            'lostReasonOptions' => $this->lostReasonOptions,
        ])->layout('layouts.backoffice', ['header' => 'Lead Pipeline']);
    }
}
