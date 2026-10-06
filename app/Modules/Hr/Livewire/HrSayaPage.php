<?php

namespace App\Modules\Hr\Livewire;

use App\Modules\Hr\Models\AbsensiLog;
use App\Modules\Hr\Models\Karyawan;
use App\Modules\Hr\Models\KpiHasil;
use App\Modules\Hr\Models\PayrollSlip;
use App\Modules\Hr\Services\AbsensiService;
use Livewire\Component;

/**
 * [F3-8b] Absensi, KPI, & Slip Gaji karyawan — tampilkan data milik sendiri saja
 * (permission: hr.lihat-sendiri). Karyawan dipetakan via user_id.
 */
class HrSayaPage extends Component
{
    public string $periode = '';

    public ?Karyawan $karyawan = null;

    public ?AbsensiLog $logHariIni = null;

    public string $catatanAbsen = '';

    public bool $showDetailSlipModal = false;

    public ?array $detailSlip = null;

    public function mount(): void
    {
        $this->periode = now()->format('Y-m');
        $this->karyawan = Karyawan::where('user_id', auth()->id())
            ->where('status_aktif', true)
            ->first();

        $this->refreshLogHariIni();
    }

    public function refreshLogHariIni(): void
    {
        if ($this->karyawan) {
            $this->logHariIni = app(AbsensiService::class)->logHariIni($this->karyawan->id);
        }
    }

    public function clockIn(): void
    {
        if (! $this->karyawan) {
            $this->dispatch('alert', ['type' => 'error', 'message' => 'Akun karyawan tidak ditemukan']);

            return;
        }

        try {
            $log = app(AbsensiService::class)->clockIn(
                karyawanId: $this->karyawan->id,
                catatan: $this->catatanAbsen ?: null
            );
            $this->catatanAbsen = '';
            $this->refreshLogHariIni();
            $this->dispatch('alert', ['type' => 'success', 'message' => 'Berhasil Clock-In pukul '.substr($log->jam_masuk, 0, 5)]);
        } catch (\Throwable $e) {
            $this->dispatch('alert', ['type' => 'error', 'message' => 'Gagal Clock-In: '.$e->getMessage()]);
        }
    }

    public function clockOut(): void
    {
        if (! $this->karyawan) {
            $this->dispatch('alert', ['type' => 'error', 'message' => 'Akun karyawan tidak ditemukan']);

            return;
        }

        try {
            $log = app(AbsensiService::class)->clockOut(
                karyawanId: $this->karyawan->id,
                catatan: $this->catatanAbsen ?: null
            );
            $this->catatanAbsen = '';
            $this->refreshLogHariIni();
            $this->dispatch('alert', ['type' => 'success', 'message' => 'Berhasil Clock-Out pukul '.substr($log->jam_keluar, 0, 5)]);
        } catch (\Throwable $e) {
            $this->dispatch('alert', ['type' => 'error', 'message' => 'Gagal Clock-Out: '.$e->getMessage()]);
        }
    }

    public function bukaDetailSlip(): void
    {
        if (! $this->karyawan) {
            return;
        }

        $slip = PayrollSlip::with(['karyawan.cabang', 'periode', 'komisiDetails.tiketServis'])
            ->where('karyawan_id', $this->karyawan->id)
            ->whereHas('periode', fn ($q) => $q->where('periode', $this->periode))
            ->first();

        if (! $slip) {
            $this->dispatch('alert', ['type' => 'error', 'message' => 'Slip gaji periode '.$this->periode.' belum tersedia']);

            return;
        }

        $rincian = json_decode($slip->rincian, true) ?? [];

        $this->detailSlip = [
            'id' => $slip->id,
            'karyawan_nama' => $slip->karyawan?->nama ?? '-',
            'karyawan_nik' => $slip->karyawan?->nik ?? '-',
            'karyawan_jabatan' => $slip->karyawan?->jabatan ?? '-',
            'karyawan_cabang' => $slip->karyawan?->cabang?->nama ?? 'Pusat',
            'rekening_bank' => $slip->karyawan?->rekening_bank ?? '-',
            'periode' => $this->periode,
            'status' => $slip->status,
            'gaji_pokok' => (float) $slip->gaji_pokok,
            'total_tunjangan' => (float) $slip->total_tunjangan,
            'total_potongan' => (float) $slip->total_potongan,
            'total_komisi' => (float) $slip->total_komisi,
            'total_gaji' => (float) $slip->total_gaji,
            'potongan_absen' => (float) ($rincian['potongan_absen'] ?? 0),
            'komisi_teknisi' => (float) ($rincian['komisi_teknisi'] ?? 0),
            'komisi_internal' => (float) ($rincian['komisi_internal'] ?? 0),
            'jurnal_id' => $slip->jurnal_id,
            'komisi_details' => $slip->komisiDetails->map(fn ($kd) => [
                'no_tiket' => $kd->tiketServis?->no_tiket ?? '-',
                'jenis' => $kd->jenis,
                'nominal' => (float) $kd->nominal,
            ])->toArray(),
        ];

        $this->showDetailSlipModal = true;
    }

    public function tutupDetailSlip(): void
    {
        $this->showDetailSlipModal = false;
        $this->detailSlip = null;
    }

    public function render()
    {
        if (! $this->karyawan) {
            return view('modules.hr.saya', [
                'karyawan' => null,
                'rekap' => [],
                'logs' => collect(),
                'kpi' => collect(),
                'slip' => null,
            ])->layout('layouts.backoffice', ['header' => 'Absensi & KPI Saya', 'title' => 'Absensi & KPI Saya']);
        }

        $this->refreshLogHariIni();

        $rekap = app(AbsensiService::class)->rekapBulanan($this->karyawan->id, $this->periode);
        $logs = AbsensiLog::with('shift')
            ->where('karyawan_id', $this->karyawan->id)
            ->whereBetween('tanggal', [$this->periode.'-01', date('Y-m-t', strtotime($this->periode.'-01'))])
            ->orderByDesc('tanggal')
            ->get();

        $kpi = KpiHasil::with('metric')
            ->where('karyawan_id', $this->karyawan->id)
            ->where('periode', $this->periode)
            ->orderBy('kpi_metric_id')
            ->get();

        $slip = PayrollSlip::with('periode')
            ->where('karyawan_id', $this->karyawan->id)
            ->whereHas('periode', fn ($q) => $q->where('periode', $this->periode))
            ->first();

        return view('modules.hr.saya', [
            'karyawan' => $this->karyawan,
            'rekap' => $rekap,
            'logs' => $logs,
            'kpi' => $kpi,
            'slip' => $slip,
        ])->layout('layouts.backoffice', ['header' => 'Absensi & KPI Saya', 'title' => 'Absensi & KPI Saya']);
    }
}
