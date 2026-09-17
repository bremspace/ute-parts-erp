<?php

namespace App\Modules\Dashboard\Livewire;

use App\Modules\Pos\Models\Transaksi;
use App\Modules\Servis\Models\TiketServis;
use App\Modules\Wms\Models\StokItem;
use App\Modules\Akunting\Models\Piutang;
use App\Modules\Reseller\Models\Komisi;
use Livewire\Component;

/**
 * Backoffice Dashboard (DASH-01: widget sesuai role).
 * Semua data agregat ringan — jumlah kecil per query, session cabang.
 */
class DashboardIndex extends Component
{
    public function getOmzetHariIniProperty(): array
    {
        $query = Transaksi::whereDate('created_at', now()->toDateString())
            ->where('status', 'selesai');

        $semua = (clone $query)->sum('total_akhir');
        $jumlah = (clone $query)->count();

        $cabangAktif = (float) (session('cabang_id')
            ? (clone $query)->where('cabang_id', session('cabang_id'))->sum('total_akhir')
            : $semua);

        return [
            'cabang_aktif' => $cabangAktif,
            'semua_cabang' => (float) $semua,
            'jumlah_transaksi' => $jumlah,
        ];
    }

    public function getAntrianServisProperty(): array
    {
        $query = TiketServis::whereNotIn('status', ['selesai', 'diambil', 'ditolak']);
        if (session('cabang_id')) {
            $query->where('cabang_id', session('cabang_id'));
        }

        return [
            'aktif' => $query->count(),
            'menunggu_approval' => TiketServis::where('status', 'menunggu_approval')
                ->when(session('cabang_id'), fn ($q) => $q->where('cabang_id', session('cabang_id')))
                ->count(),
        ];
    }

    public function getStokKritisProperty(): array
    {
        $cabangId = session('cabang_id');

        $query = StokItem::whereColumn('jumlah', '<=', 'jumlah_minimum')
            ->with(['produk', 'gudang']);

        if ($cabangId) {
            $query->whereHas('gudang', fn ($q) => $q->where('cabang_id', $cabangId));
        }

        $items = $query->orderByRaw('(jumlah_minimum - jumlah) desc')->limit(8)->get();

        return [
            'total' => (clone $query)->count(),
            'items' => $items,
        ];
    }

    public function getPiutangJatuhTempoProperty(): array
    {
        $items = Piutang::where('status', '!=', 'lunas')
            ->where(function ($q) {
                $q->whereDate('jatuh_tempo', '<=', now()->addDays(7)->toDateString());
            })
            ->with('pelanggan')
            ->orderBy('jatuh_tempo')
            ->limit(8)
            ->get();

        return [
            'total' => Piutang::where('status', '!=', 'lunas')
                ->whereDate('jatuh_tempo', '<=', now()->addDays(7)->toDateString())
                ->count(),
            'items' => $items,
        ];
    }

    public function getKomisiPendingProperty(): float
    {
        return (float) Komisi::where('status', 'pending')->sum('nominal_komisi');
    }

    public function getTransaksiTerbaruProperty()
    {
        return Transaksi::with(['pelanggan', 'cabang'])
            ->latest()
            ->limit(6)
            ->get();
    }

    public function render()
    {
        return view('modules.dashboard.livewire.dashboard-index', [
            'serverStatus' => [
                'queue' => config('queue.default'),
                'cache' => config('cache.default'),
                'env' => app()->environment(),
            ],
            'omzetHariIni' => $this->omzetHariIni,
            'antrianServis' => $this->antrianServis,
            'stokKritis' => $this->stokKritis,
            'piutangJatuhTempo' => $this->piutangJatuhTempo,
            'komisiPending' => $this->komisiPending,
            'transaksiTerbaru' => $this->transaksiTerbaru,
        ])->layout('layouts.backoffice', ['header' => 'Dashboard']);
    }
}