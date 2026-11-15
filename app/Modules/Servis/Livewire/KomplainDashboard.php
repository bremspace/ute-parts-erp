<?php

namespace App\Modules\Servis\Livewire;

use App\Modules\Servis\Models\TiketServis;
use Livewire\Component;

/**
 * Dashboard komplain pasca-servis: volume, garansi, gratis/goodwill,
 * SLA overdue, dan komplain berulang per teknisi / jenis HP.
 */
class KomplainDashboard extends Component
{
    public string $rentang = '30_hari';

    public function render()
    {
        $cabangId = session('cabang_id');

        $query = TiketServis::query()
            ->with(['tiketLama.teknisi', 'garansi'])
            ->where('sumber', 'komplain');

        if ($cabangId) {
            $query->where('cabang_id', $cabangId);
        }

        if ($this->rentang !== 'semua') {
            $hari = (int) str_replace('_hari', '', $this->rentang);
            $query->where('created_at', '>=', now()->subDays($hari)->startOfDay());
        }

        $semua = $query->latest()->get();

        // Komplain berulang: tiket servis asal yang mempunyai >1 komplain
        $berulangTeknisi = $semua
            ->groupBy(fn ($t) => $t->tiketLama?->teknisi?->name ?? 'Tanpa teknisi')
            ->map(fn ($rows) => [
                'nama' => $rows->first()->tiketLama?->teknisi?->name ?? 'Tanpa teknisi',
                'jumlah' => $rows->count(),
            ])
            ->filter(fn ($r) => $r['jumlah'] > 1)
            ->sortByDesc('jumlah')
            ->values();

        $perJenis = $semua->groupBy('jenis_hp')
            ->map(fn ($rows) => ['nama' => $rows->first()->jenis_hp, 'jumlah' => $rows->count()])
            ->sortByDesc('jumlah')
            ->values();

        $overdue = $semua->filter(function ($t) {
            return $t->pengerjaan_deadline_at
                && now()->greaterThan($t->pengerjaan_deadline_at)
                && in_array($t->status, ['dikerjakan', 'qc'], true);
        });

        return view('modules.servis.komplain-dashboard', [
            'total' => $semua->count(),
            'garansiBerlaku' => $semua->where('garansi_berlaku', true)->count(),
            'gratisDisetujui' => $semua->filter(fn ($t) => $t->komplain_gratis_approved_at)->count(),
            'goodwillDisetujui' => $semua->filter(fn ($t) => $t->goodwill_approved_at)->count(),
            'overdue' => $overdue->count(),
            'eskalasi' => $semua->filter(fn ($t) => $t->komplain_eskalasi_at !== null)->count(),
            'berulangTeknisi' => $berulangTeknisi,
            'perJenis' => $perJenis,
            'terbaru' => $semua->take(20)->values(),
        ])->layout('layouts.backoffice', ['header' => 'Servis HP — Dashboard Komplain Pasca-Servis']);
    }
}
