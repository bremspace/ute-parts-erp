<?php

namespace App\Modules\Hr\Livewire;

use App\Modules\Hr\Models\PayrollPeriode;
use App\Modules\Hr\Models\PayrollSlip;
use App\Modules\Hr\Services\PayrollService;
use Livewire\Component;
use Livewire\WithPagination;

class PayrollPage extends Component
{
    use WithPagination;

    public string $periode = '';

    public array $periodeList = [];

    public array $slips = [];

    public float $totalGaji = 0;

    public bool $showApproveModal = false;

    public bool $showBayarModal = false;

    public string $statusFilter = 'semua';

    protected $listeners = ['periodeRefreshed' => 'mount'];

    public function mount(): void
    {
        $this->periodeList = PayrollPeriode::orderByDesc('periode')->get()->pluck('periode', 'id')->toArray();
        if (empty($this->periode) && ! empty($this->periodeList)) {
            $this->periode = collect($this->periodeList)->first();
        }
        $this->loadSlips();
    }

    public function updatedPeriode(): void
    {
        $this->loadSlips();
    }

    public function loadSlips(): void
    {
        if (! $this->periode) {
            $this->slips = [];
            $this->totalGaji = 0;

            return;
        }

        $query = PayrollSlip::with('karyawan')
            ->whereHas('periode', fn ($q) => $q->where('periode', $this->periode));

        if ($this->statusFilter !== 'semua') {
            $query->where('status', $this->statusFilter);
        }

        $this->slips = $query->get()->map(fn ($s) => [
            'id' => $s->id,
            'karyawan_nama' => $s->karyawan?->nama ?? '-',
            'karyawan_jabatan' => $s->karyawan?->jabatan ?? '-',
            'gaji_pokok' => $s->gaji_pokok,
            'total_tunjangan' => $s->total_tunjangan,
            'total_potongan' => $s->total_potongan,
            'total_komisi' => $s->total_komisi,
            'total_gaji' => $s->total_gaji,
            'status' => $s->status,
            'rincian' => json_decode($s->rincian, true),
        ])->toArray();

        $this->totalGaji = collect($this->slips)->sum('total_gaji');
    }

    public function buatPeriode(): void
    {
        $this->validate(['periode' => 'required|string|size:7|regex:/^\d{4}-\d{2}$/']);

        $existing = PayrollPeriode::where('periode', $this->periode)->first();
        if ($existing) {
            $this->dispatch('alert', ['type' => 'error', 'message' => 'Periode sudah ada']);

            return;
        }

        PayrollPeriode::create([
            'periode' => $this->periode,
            'tanggal_mulai' => $this->periode.'-01',
            'tanggal_selesai' => date('Y-m-t', strtotime($this->periode.'-01')),
            'status' => 'draft',
        ]);

        $this->dispatch('alert', ['type' => 'success', 'message' => 'Periode '.$this->periode.' berhasil dibuat']);
        $this->periodeList = PayrollPeriode::orderByDesc('periode')->get()->pluck('periode', 'id')->toArray();
        $this->mount();
    }

    public function hitungDraft(): void
    {
        if (! $this->periode) {
            $this->dispatch('alert', ['type' => 'error', 'message' => 'Pilih periode terlebih dahulu']);

            return;
        }

        $result = app(PayrollService::class)->hitungDraft($this->periode);
        $this->loadSlips();
        $this->dispatch('alert', ['type' => 'success', 'message' => 'Draft payroll periode '.$this->periode.' berhasil dihitung ('.$result['karyawan_diproses'].' karyawan)']);
    }

    public function ajukanApproval(): void
    {
        if (! $this->periode) {
            $this->dispatch('alert', ['type' => 'error', 'message' => 'Pilih periode terlebih dahulu']);

            return;
        }

        $result = app(PayrollService::class)->ajukanApproval($this->periode, auth()->id());

        if ($result['needs_approval']) {
            $this->showApproveModal = true;
            $this->dispatch('alert', ['type' => 'warning', 'message' => 'Approval diperlukan (total > Rp '.number_format($result['threshold'], 0, ',', '.').')']);
        } else {
            $this->finalisasiDisetujui();
        }
    }

    public function finalisasiDisetujui(): void
    {
        try {
            $result = app(PayrollService::class)->finalisasiDisetujui($this->periode);
            $this->showApproveModal = false;
            $this->loadSlips();
            $this->dispatch('alert', ['type' => 'success', 'message' => 'Payroll periode '.$this->periode.' disetujui. Jurnal: '.$result['no_jurnal']]);
        } catch (\Exception $e) {
            $this->dispatch('alert', ['type' => 'error', 'message' => $e->getMessage()]);
        }
    }

    public function bayarPayroll(): void
    {
        if (! $this->periode) {
            $this->dispatch('alert', ['type' => 'error', 'message' => 'Pilih periode terlebih dahulu']);

            return;
        }

        try {
            $result = app(PayrollService::class)->bayarPayroll($this->periode);
            $this->loadSlips();
            $this->showBayarModal = false;
            $this->dispatch('alert', ['type' => 'success', 'message' => 'Pembayaran payroll periode '.$this->periode.' berhasil. Jurnal: '.$result['no_jurnal']]);
        } catch (\Exception $e) {
            $this->dispatch('alert', ['type' => 'error', 'message' => $e->getMessage()]);
        }
    }

    public function render()
    {
        return view('modules.hr.payroll', [
            'slips' => $this->slips,
            'totalGaji' => $this->totalGaji,
            'periodeList' => $this->periodeList,
            'statusFilter' => $this->statusFilter,
        ])->layout('layouts.backoffice', ['header' => 'HR & Payroll', 'title' => 'HR & Payroll']);
    }
}
