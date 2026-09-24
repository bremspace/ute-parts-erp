<?php

namespace App\Modules\Hr\Livewire;

use App\Modules\Hr\Models\AbsensiLog;
use App\Modules\Hr\Models\Karyawan;
use App\Modules\Hr\Models\KpiHasil;
use App\Modules\Hr\Services\AbsensiService;
use Livewire\Component;

/**
 * [F3-8b] Absensi & KPI karyawan — tampilkan data milik sendiri saja
 * (permission: hr.lihat-sendiri). Karyawan dipetakan via user_id.
 */
class HrSayaPage extends Component
{
    public string $periode = '';

    public ?Karyawan $karyawan = null;

    public function mount(): void
    {
        $this->periode = now()->format('Y-m');
        $this->karyawan = Karyawan::where('user_id', auth()->id())
            ->where('status_aktif', true)
            ->first();
    }

    public function render()
    {
        if (! $this->karyawan) {
            return view('modules.hr.saya', [
                'karyawan' => null,
                'rekap' => [],
                'logs' => collect(),
                'kpi' => collect(),
            ]);
        }

        $rekap = app(AbsensiService::class)->rekapBulanan($this->karyawan->id, $this->periode);
        $logs = AbsensiLog::with('shift')
            ->where('karyawan_id', $this->karyawan->id)
            ->whereBetween('tanggal', [$this->periode.'-01', date('Y-m-t', strtotime($this->periode.'-01'))])
            ->orderByDesc('tanggal')
            ->get();

        $kpi = KpiHasil::with('metric')
            ->where('karyawan_id', $this->karyawan->id)
            ->where('periode', $this->periode)
            ->orderBy('metric_id')
            ->get();

        return view('modules.hr.saya', [
            'karyawan' => $this->karyawan,
            'rekap' => $rekap,
            'logs' => $logs,
            'kpi' => $kpi,
        ]);
    }
}
