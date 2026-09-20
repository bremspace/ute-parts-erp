<?php

namespace App\Modules\Reseller\Livewire;

use App\Modules\Crm\Models\Pelanggan;
use App\Modules\Reseller\Models\Komisi;
use App\Modules\Reseller\Models\SkemaKomisi;
use App\Modules\Reseller\Services\KomisiService;
use Livewire\Component;
use Livewire\WithPagination;

class ResellerDashboard extends Component
{
    use WithPagination;

    public string $activeTab = 'reseller'; // reseller, komisi

    // Komisi filter
    public string $filterStatus = '';
    public array $selectedKomisiIds = [];

    // Skema komisi modal
    public bool $showSkemaModal = false;
    public array $skemaForm = ['id' => null, 'nama' => '', 'kategori' => '', 'tipe' => 'persen', 'nilai' => 5, 'is_active' => true];

    public function getResellersProperty()
    {
        return Pelanggan::with(['tierMembership', 'komisi'])
            ->where('is_reseller', true)
            ->latest()
            ->get()
            ->map(function ($r) {
                return [
                    'id' => $r->id,
                    'nama' => $r->nama,
                    'telepon' => $r->telepon,
                    'total_belanja' => $r->total_belanja_12bulan,
                    'tier' => $r->tierMembership?->nama,
                    'komisi_terhutang' => $r->komisi->where('status', 'disetujui')->sum('nominal_komisi'),
                    'komisi_pending' => $r->komisi->where('status', 'pending')->sum('nominal_komisi'),
                ];
            });
    }

    public function getKomisiListProperty()
    {
        $query = Komisi::with(['pelanggan', 'approver', 'transaksi'])
            ->latest();

        if ($this->filterStatus) {
            $query->where('status', $this->filterStatus);
        }

        return $query->get();
    }

    public function getSkemaListProperty()
    {
        return SkemaKomisi::orderBy('id')->get();
    }

    public function toggleKomisi(int $id)
    {
        if (in_array($id, $this->selectedKomisiIds, true)) {
            $this->selectedKomisiIds = array_values(array_diff($this->selectedKomisiIds, [$id]));
        } else {
            $this->selectedKomisiIds[] = $id;
        }
    }

    public function prosesApproval(string $action)
    {
        if (empty($this->selectedKomisiIds)) {
            $this->dispatch('alert', ['type' => 'warning', 'message' => 'Pilih minimal satu komisi terlebih dahulu']);
            return;
        }

        try {
            $approved = app(KomisiService::class)->prosesApproval($this->selectedKomisiIds, $action, auth()->id());
            $this->selectedKomisiIds = [];
            $this->dispatch('alert', [
                'type' => 'success',
                'message' => $action === 'approve'
                    ? "{$approved} komisi disetujui — jurnal & utang dibuat otomatis"
                    : count($approved) . ' komisi ditolak',
            ]);
        } catch (\Exception $e) {
            $this->dispatch('alert', ['type' => 'error', 'message' => $e->getMessage()]);
        }
    }

    public function openSkemaModal()
    {
        $this->skemaForm = ['id' => null, 'nama' => '', 'kategori' => '', 'tipe' => 'persen', 'nilai' => 5, 'is_active' => true];
        $this->showSkemaModal = true;
    }

    public function simpanSkema()
    {
        $this->validate([
            'skemaForm.nama' => 'required|string|max:255',
            'skemaForm.tipe' => 'required|in:persen,nominal',
            'skemaForm.nilai' => 'required|numeric|min:0',
        ]);

        SkemaKomisi::create($this->skemaForm);
        $this->showSkemaModal = false;
        $this->dispatch('alert', ['type' => 'success', 'message' => 'Skema komisi ditambahkan']);
    }

    public function render()
    {
        return view('modules.reseller.livewire.reseller-dashboard', [
            'resellers' => $this->resellers,
            'komisiList' => $this->komisiList,
            'skemaList' => $this->skemaList,
        ])->layout('layouts.backoffice', ['header' => 'Reseller & Komisi']);
    }
}