<?php

namespace App\Modules\Akunting\Livewire;

use App\Modules\Akunting\Jobs\ExportLaporanJob;
use App\Modules\Akunting\Models\AkunCOA;
use App\Modules\Akunting\Models\JurnalAkuntansi;
use App\Modules\Akunting\Services\PajakService;
use App\Modules\Pos\Models\Transaksi;
use Illuminate\Support\Facades\Cache;
use Livewire\Component;

/**
 * [F1-2] Laporan Pajak Bulanan — rekap PPN keluaran/masukan → e-Faktur-ready.
 * RBAC: permission laporan.cabang (route middleware). Scope: cabang aktif (session).
 * Rekap + detail baris di-cache 15 menit; export via queue (ExportLaporanJob) — RAM 1GB.
 */
class LaporanPajak extends Component
{
    public string $periodeDari = '';

    public string $periodeSampai = '';

    public function mount(): void
    {
        $this->periodeDari = now()->startOfMonth()->toDateString();
        $this->periodeSampai = now()->toDateString();
    }

    /** Konfigurasi PPN cabang aktif (display). */
    public function getConfigProperty(): array
    {
        $cabangId = session('cabang_id');
        $svc = app(PajakService::class);

        return [
            'enabled' => $cabangId ? $svc->enabled($cabangId) : false,
            'percent' => $svc->getPercent($cabangId),
        ];
    }

    /**
     * Rekap ringkas (di-cache 15 menit per cabang+periode — pola implement-plan Fase RAM).
     *
     * @return array{keluaran_dpp: float, keluaran_ppn: float, masukan: float, terutang: float, jumlah_transaksi: int}
     */
    public function getRekapProperty(): array
    {
        $cabangId = session('cabang_id');
        $cacheKey = sprintf('laporan-pajak:%s:%s:%s', $cabangId ?? 'all', $this->periodeDari, $this->periodeSampai);

        return Cache::remember($cacheKey, now()->addMinutes(15), function () use ($cabangId) {
            $dpp = 0.0;
            $ppn = 0.0;
            $count = 0;

            $q = Transaksi::whereDate('created_at', '>=', $this->periodeDari)
                ->whereDate('created_at', '<=', $this->periodeSampai);
            if ($cabangId) {
                $q->where('cabang_id', $cabangId);
            }
            foreach ($q->get() as $t) {
                $nilai = (float) ($t->ppn_nominal ?? 0) > 0 ? (float) $t->ppn_nominal : (float) $t->pajak_nominal;
                if ($nilai > 0) {
                    $dpp += (float) ($t->dpp ?? 0) > 0 ? (float) $t->dpp : max(0, (float) $t->subtotal - (float) $t->diskon_nominal);
                    $ppn += $nilai;
                    $count++;
                }
            }

            $masukan = 0.0;
            $akunMasukan = AkunCOA::where('kode', '110-03')->first();
            if ($akunMasukan) {
                $j = JurnalAkuntansi::where('akun_coa_id', $akunMasukan->id)
                    ->whereDate('tanggal', '>=', $this->periodeDari)
                    ->whereDate('tanggal', '<=', $this->periodeSampai);
                if ($cabangId) {
                    $j->where('cabang_id', $cabangId);
                }
                $masukan = round((float) $j->sum('debit') - (float) $j->sum('kredit'), 2);
            }

            return [
                'keluaran_dpp' => round($dpp, 2),
                'keluaran_ppn' => round($ppn, 2),
                'masukan' => $masukan,
                'terutang' => round($ppn - $masukan, 2),
                'jumlah_transaksi' => $count,
            ];
        });
    }

    /**
     * Detail baris keluaran utk DataTable (scope cabang, periode).
     *
     * [B-15b] Di-cache 15 menit memakai pola yang sama dengan
     * `getRekapProperty()` (TTL 15 menit, key berawalan `laporan-pajak:`).
     * SEBELUMNYA `foreach ($q->get())` menarik SELURUH transaksi periode ke
     * memori tiap render halaman, padahal isinya sama persis dengan yang
     * sudah dipakai `getRekapProperty()`. Kolom baris (DPP/PPN/percent) dan
     * scope cabang tidak berubah — hanya sumber datanya.
     */
    public function getKeluaranRowsProperty(): array
    {
        $cabangId = session('cabang_id');
        $cacheKey = sprintf('laporan-pajak-rows:%s:%s:%s', $cabangId ?? 'all', $this->periodeDari, $this->periodeSampai);

        return Cache::remember($cacheKey, now()->addMinutes(15), function () use ($cabangId) {
            $q = Transaksi::whereDate('created_at', '>=', $this->periodeDari)
                ->whereDate('created_at', '<=', $this->periodeSampai)
                ->orderBy('created_at');
            if ($cabangId) {
                $q->where('cabang_id', $cabangId);
            }

            $rows = [];
            foreach ($q->get() as $t) {
                $ppn = (float) ($t->ppn_nominal ?? 0) > 0 ? (float) $t->ppn_nominal : (float) $t->pajak_nominal;
                if ($ppn <= 0) {
                    continue;
                }
                $dpp = (float) ($t->dpp ?? 0) > 0 ? (float) $t->dpp : max(0, (float) $t->subtotal - (float) $t->diskon_nominal);
                $rows[] = [
                    'tanggal' => $t->created_at->format('d/m/Y'),
                    'no_transaksi' => $t->no_transaksi,
                    'dpp' => $dpp,
                    'percent' => $dpp > 0 ? round(($ppn / $dpp) * 100, 2) : 0,
                    'ppn' => $ppn,
                ];
            }

            return $rows;
        });
    }

    /** [F1-2] Export e-Faktur-ready via queue (async — jangan sinkron di request). */
    public function exportPajak(): void
    {
        dispatch(new ExportLaporanJob(
            jenis: 'pajak',
            periodeDari: $this->periodeDari,
            periodeSampai: $this->periodeSampai,
            cabangId: session('cabang_id'),
            akunId: null,
            userId: auth()->id()
        ));

        $this->dispatch('alert', [
            'type' => 'success',
            'message' => 'Export laporan pajak dijadwalkan — notifikasi + link unduh muncul setelah selesai.',
        ]);
    }

    public function render()
    {
        return view('modules.akunting.livewire.laporan-pajak', [
            'config' => $this->config,
            'rekap' => $this->rekap,
            'keluaranRows' => $this->keluaranRows,
        ])->layout('layouts.backoffice', ['header' => 'Laporan Pajak Bulanan']);
    }
}
