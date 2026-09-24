<?php

namespace App\Modules\Hr\Livewire;

use App\Modules\Hr\Models\AbsensiLog;
use App\Modules\Hr\Models\Karyawan;
use App\Modules\Hr\Models\KpiHasil;
use App\Modules\Hr\Models\KpiMetric;
use App\Modules\Hr\Models\Shift;
use App\Modules\Hr\Models\ShiftJadwal;
use App\Modules\Hr\Services\AbsensiService;
use App\Modules\Hr\Services\KpiService;
use Livewire\Component;

/**
 * [F3-8b] HR Absensi, Shift & KPI — admin/finance (permission: kelola-hr).
 * Tabs: shift | roster | absensi | kpi.
 */
class HrAbsensiKpiPage extends Component
{
    public string $tab = 'shift';

    // Shift form
    public string $shiftNama = '';

    public string $shiftMulai = '08:00';

    public string $shiftSelesai = '16:00';

    public bool $shiftAktif = true;

    // Roster form
    public string $rosterTanggal = '';

    public ?int $rosterKaryawanId = null;

    public ?int $rosterShiftId = null;

    // Absensi override form
    public string $absensiTanggal = '';

    public ?int $absensiKaryawanId = null;

    public string $absensiStatus = 'izin';

    public string $absensiCatatan = '';

    public string $absensiFilter = 'semua';

    // KPI
    public string $kpiPeriode = '';

    protected function rules(): array
    {
        return [
            'shiftNama' => 'required|string|max:100',
            'shiftMulai' => 'required|date_format:H:i',
            'shiftSelesai' => 'required|date_format:H:i',
            'rosterTanggal' => 'required|date',
            'rosterKaryawanId' => 'required|exists:karyawan,id',
            'rosterShiftId' => 'required|exists:shift,id',
            'absensiTanggal' => 'required|date',
            'absensiKaryawanId' => 'required|exists:karyawan,id',
            'absensiStatus' => 'required|in:izin,cuti,absen',
            'kpiPeriode' => 'nullable|regex:/^\d{4}-\d{2}$/',
        ];
    }

    public function mount(): void
    {
        $this->rosterTanggal = now()->toDateString();
        $this->absensiTanggal = now()->toDateString();
        $this->kpiPeriode = now()->format('Y-m');
    }

    public function pilihTab(string $tab): void
    {
        $this->tab = in_array($tab, ['shift', 'roster', 'absensi', 'kpi'], true) ? $tab : 'shift';
    }

    // ===== Shift =====
    public function simpanShift(): void
    {
        $this->validate([
            'shiftNama' => 'required|string|max:100',
            'shiftMulai' => 'required|date_format:H:i',
            'shiftSelesai' => 'required|date_format:H:i',
        ]);

        Shift::updateOrCreate(
            ['cabang_id' => session('cabang_id'), 'nama' => trim($this->shiftNama)],
            [
                'jam_mulai' => $this->shiftMulai.':00',
                'jam_selesai' => $this->shiftSelesai.':00',
                'is_aktif' => $this->shiftAktif,
            ]
        );

        $this->reset('shiftNama', 'shiftMulai', 'shiftSelesai');
        $this->shiftMulai = '08:00';
        $this->shiftSelesai = '16:00';
        $this->dispatch('alert', ['type' => 'success', 'message' => 'Shift berhasil disimpan']);
    }

    public function toggleShift(int $id): void
    {
        $shift = Shift::find($id);
        if ($shift) {
            $shift->update(['is_aktif' => ! $shift->is_aktif]);
        }
    }

    // ===== Roster =====
    public function simpanRoster(): void
    {
        $this->validate([
            'rosterTanggal' => 'required|date',
            'rosterKaryawanId' => 'required|exists:karyawan,id',
            'rosterShiftId' => 'required|exists:shift,id',
        ]);

        ShiftJadwal::updateOrCreate(
            [
                'cabang_id' => session('cabang_id'),
                'karyawan_id' => $this->rosterKaryawanId,
                'tanggal' => $this->rosterTanggal,
            ],
            ['shift_id' => $this->rosterShiftId]
        );

        $this->dispatch('alert', ['type' => 'success', 'message' => 'Jadwal shift berhasil disimpan']);
    }

    public function hapusRoster(int $id): void
    {
        ShiftJadwal::where('id', $id)
            ->where('cabang_id', session('cabang_id'))
            ->delete();
    }

    // ===== Absensi =====
    public function tandaiStatusAbsensi(): void
    {
        $this->validate([
            'absensiTanggal' => 'required|date',
            'absensiKaryawanId' => 'required|exists:karyawan,id',
            'absensiStatus' => 'required|in:izin,cuti,absen',
        ]);

        app(AbsensiService::class)->tandaiStatus(
            $this->absensiKaryawanId,
            $this->absensiTanggal,
            $this->absensiStatus,
            $this->absensiCatatan ?: null
        );

        $this->reset('absensiCatatan');
        $this->dispatch('alert', ['type' => 'success', 'message' => 'Status absensi '.strtoupper($this->absensiStatus).' disimpan']);
    }

    public function autoAbsen(): void
    {
        $dibuat = app(AbsensiService::class)->autoTandaiAbsen(now()->format('Y-m'));
        $this->dispatch('alert', ['type' => 'success', 'message' => 'Auto absen selesai ('.$dibuat.' log dibuat)']);
    }

    // ===== KPI =====
    public function hitungKpi(): void
    {
        if (! $this->kpiPeriode) {
            $this->kpiPeriode = now()->format('Y-m');
        }
        $this->validate(['kpiPeriode' => 'required|regex:/^\d{4}-\d{2}$/']);

        $hasil = app(KpiService::class)->hitung($this->kpiPeriode);
        $this->dispatch('alert', ['type' => 'success', 'message' => 'KPI periode '.$this->kpiPeriode.' selesai dihitung ('.$hasil['hasil'].' hasil, '.$hasil['metric_diproses'].' metric)']);
    }

    public function render()
    {
        $cabangId = session('cabang_id');
        $shift = Shift::query();
        if ($cabangId) {
            $shift->where(fn ($q) => $q->where('cabang_id', $cabangId)->orWhereNull('cabang_id'));
        }
        $shifts = $shift->orderBy('jam_mulai')->get();

        $roster = ShiftJadwal::with('karyawan', 'shift')
            ->when($cabangId, fn ($q) => $q->where('cabang_id', $cabangId))
            ->whereBetween('tanggal', [$this->kpiPeriode.'-01', date('Y-m-t', strtotime($this->kpiPeriode.'-01'))])
            ->orderByDesc('tanggal')
            ->limit(100)
            ->get();

        $absensi = AbsensiLog::with('karyawan', 'shift')
            ->when($this->absensiFilter !== 'semua', fn ($q) => $q->where('status', $this->absensiFilter))
            ->whereBetween('tanggal', [$this->kpiPeriode.'-01', date('Y-m-t', strtotime($this->kpiPeriode.'-01'))])
            ->orderByDesc('tanggal')
            ->limit(100)
            ->get();

        $kpiHasil = KpiHasil::with('karyawan', 'metric')
            ->where('periode', $this->kpiPeriode)
            ->orderBy('karyawan_id')
            ->get();

        return view('modules.hr.absensi', [
            'shifts' => $shifts,
            'roster' => $roster,
            'absensi' => $absensi,
            'kpiHasil' => $kpiHasil,
            'karyawans' => Karyawan::where('status_aktif', true)->orderBy('nama')->get(),
            'metrics' => KpiMetric::where('is_aktif', true)->orderBy('kode')->get(),
        ]);
    }
}
