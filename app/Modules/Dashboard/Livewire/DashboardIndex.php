<?php

namespace App\Modules\Dashboard\Livewire;

use App\Modules\Akunting\Models\AkunCOA;
use App\Modules\Akunting\Models\JurnalAkuntansi;
use App\Modules\Akunting\Models\Piutang;
use App\Modules\Akunting\Models\Utang;
use App\Modules\Crm\Models\KampanyeBroadcast;
use App\Modules\Crm\Models\Pelanggan;
use App\Modules\Crm\Models\TierMembership;
use App\Modules\Notifikasi\Models\NotifikasiKeluar;
use App\Modules\Pos\Models\Transaksi;
use App\Modules\Pos\Models\TransaksiItem;
use App\Modules\Pos\Services\KasSesiState;
use App\Modules\Report\Services\ReportBuilderService;
use App\Modules\Reseller\Models\Komisi;
use App\Modules\Servis\Models\TiketServis;
use App\Modules\Wms\Models\PurchaseOrder;
use App\Modules\Wms\Models\StokItem;
use Illuminate\Support\Facades\DB;
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

        $items = $query->orderByRaw('(jumlah_minimum - jumlah) desc')->limit(10)->get();

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

    /** Generate drill-down URL for a given model + item id */
    public function getDrillDownUrl(string $model, int $id): string
    {
        return route('laporan.drill', ['model' => $model, 'id' => $id]);
    }

    /** Get available drill-down models from whitelist */
    public function getDrillDownModels(): array
    {
        return ReportBuilderService::MODEL_WHITELIST;
    }

    // ===== [T-27] Data siap-chart (server-computed, ringan untuk RAM 1GB) =====

    /** Tren omzet 30 hari (line chart): [tanggal, total] utk cabang aktif */
    public function getChartOmzet30HariProperty(): array
    {
        $cabangId = session('cabang_id');

        $rows = Transaksi::where('status', 'selesai')
            ->when($cabangId, fn ($q) => $q->where('cabang_id', $cabangId))
            ->where('created_at', '>=', now()->subDays(29)->startOfDay())
            ->selectRaw('DATE(created_at) as tgl, SUM(total_akhir) as total')
            ->groupBy('tgl')
            ->get()
            ->keyBy('tgl');

        $labels = [];
        $values = [];
        for ($i = 29; $i >= 0; $i--) {
            $d = now()->subDays($i)->toDateString();
            $labels[] = now()->subDays($i)->format('d/m');
            $values[] = round((float) ($rows[$d]->total ?? 0), 0);
        }

        return ['labels' => $labels, 'values' => $values];
    }

    /** Komposisi omzet per kategori produk (donut): [label, nilai] */
    public function getChartKategoriProperty(): array
    {
        $cabangId = session('cabang_id');

        $query = TransaksiItem::query()
            ->join('produk', 'produk.id', '=', 'transaksi_item.produk_id')
            ->join('transaksi', 'transaksi.id', '=', 'transaksi_item.transaksi_id')
            ->where('transaksi.status', 'selesai')
            ->when($cabangId, fn ($q) => $q->where('transaksi.cabang_id', $cabangId))
            ->where('transaksi.created_at', '>=', now()->startOfMonth())
            ->selectRaw('COALESCE(produk.kategori, \'Umum\') as kate, SUM(transaksi_item.subtotal) as total')
            ->groupBy('kate')
            ->orderByDesc('total')
            ->limit(6)
            ->get();

        return [
            'labels' => $query->pluck('kate')->values()->all(),
            'values' => $query->pluck('total')->map(fn ($v) => round((float) $v, 0))->values()->all(),
        ];
    }

    /** Status servis aktif per tahap kanban (bar chart) */
    public function getChartServisStatusProperty(): array
    {
        $statuses = ['diterima', 'diagnosa', 'menunggu_approval', 'disetujui', 'dikerjakan', 'qc', 'selesai'];

        $labels = collect($statuses)->map(fn ($s) => str_replace('_', ' ', $s))->values()->all();
        $values = collect($statuses)->map(function ($s) {
            $q = TiketServis::where('status', $s);
            if (session('cabang_id')) {
                $q->where('cabang_id', session('cabang_id'));
            }

            return $q->count();
        })->values()->all();

        return ['labels' => $labels, 'values' => $values];
    }

    /** Piutang aging (0-30, 31-60, 61-90, >90 hari) */
    public function getChartPiutangAgingProperty(): array
    {
        $semua = Piutang::where('status', '!=', 'lunas')->get();
        $now = now()->startOfDay();

        $buckets = ['0-30 hari' => 0, '31-60 hari' => 0, '61-90 hari' => 0, '>90 hari' => 0];
        foreach ($semua as $p) {
            // Umur = hari TERLEWAT dari jatuh_tempo (signed): bukan-yet-due → 0 (bucket 0-30).
            // diffInDays abs() lama salah bucket utk invoice yang belum jatuh tempo.
            $umur = $p->jatuh_tempo ? max(0, (int) $p->jatuh_tempo->diffInDays($now, false)) : 0;
            if ($umur <= 30) {
                $buckets['0-30 hari'] += (float) $p->sisa;
            } elseif ($umur <= 60) {
                $buckets['31-60 hari'] += (float) $p->sisa;
            } elseif ($umur <= 90) {
                $buckets['61-90 hari'] += (float) $p->sisa;
            } else {
                $buckets['>90 hari'] += (float) $p->sisa;
            }
        }

        return ['labels' => array_keys($buckets), 'values' => array_values($buckets)];
    }

    /** Stok kritis top-N (bar chart) */
    public function getChartStokKritisProperty(): array
    {
        $items = $this->stokKritis['items']->take(10);

        return [
            'labels' => $items->map(fn ($s) => $s->produk?->nama)->values()->all(),
            'values' => $items->map(fn ($s) => $s->jumlah)->values()->all(),
        ];
    }

    public function getRoleProperty(): string
    {
        return auth()->user()?->getRoleNames()->first() ?? 'super-admin';
    }

    // ===== [T-27] Widget per role (agregasi server-side, query ringan) =====

    /** Kasir: omzet shift SENDIRI dari KasSesi (sesi buka) — scoped kasir_id + cabang */
    public function getOmzetShiftKasirProperty(): array
    {
        $user = auth()->user();
        $sesi = app(KasSesiState::class)->sesiKasAktif();

        $query = Transaksi::where('status', 'selesai')
            ->where('kasir_id', $user?->id);

        if ($sesi) {
            $query->where('cabang_id', $sesi->cabang_id)->where('created_at', '>=', $sesi->dibuka_at);
        } else {
            $query->whereDate('created_at', now()->toDateString());
        }

        return [
            'omzet' => round((float) $query->sum('total_akhir'), 2),
            'jumlah_transaksi' => (clone $query)->count(),
            'sesi' => $sesi ? '#'.$sesi->id.' ('.$sesi->dibuka_at.')' : 'tanpa sesi (omzet hari ini)',
        ];
    }

    /** Finance: laba bersih bulan berjalan + total piutang belum lunas */
    public function getRingkasanKeuanganProperty(): array
    {
        $cabangId = session('cabang_id');
        $query = JurnalAkuntansi::with('akun')
            ->whereDate('tanggal', '>=', now()->startOfMonth())
            ->whereDate('tanggal', '<=', now()->toDateString())
            ->when($cabangId, fn ($q) => $q->where('cabang_id', $cabangId));

        $j = $query->get();

        $laba = round(
            $j->where('akun.tipe', 'pendapatan')->sum(fn ($x) => (float) $x->kredit - (float) $x->debit)
            - $j->where('akun.tipe', 'beban')->sum(fn ($x) => (float) $x->debit - (float) $x->kredit),
            2
        );

        return [
            'laba_bulan_ini' => $laba,
            'total_piutang' => round((float) Piutang::where('status', '!=', 'lunas')->when($cabangId, fn ($q) => $q->where('cabang_id', $cabangId))->selectRaw('SUM(jumlah - jumlah_dibayar) as sisa')->value('sisa'), 2),
            'piutang_lewat' => Piutang::where('status', '!=', 'lunas')
                ->whereDate('jatuh_tempo', '<', now()->toDateString())
                ->when($cabangId, fn ($q) => $q->where('cabang_id', $cabangId))
                ->count(),
        ];
    }

    /** F3-6 Treasury: proyeksi arus kas 30 hari = kas sekarang + piutang 30d - utang 30d */
    public function getTreasuryProjectionProperty(): array
    {
        $cabangId = session('cabang_id');

        // Kas sekarang: saldo akun 110-01 (Kas) dari jurnal balance — cari via kode COA
        $akunKasId = (int) AkunCOA::where('kode', '110-01')->value('id');
        $saldoKas = (float) JurnalAkuntansi::where('akun_coa_id', $akunKasId)
            ->when($cabangId, fn ($q) => $q->where('cabang_id', $cabangId))
            ->sum(DB::raw('debit - kredit'));

        // Piutang jatuh tempo ≤ 30 hari (status != lunas)
        $piutang30d = Piutang::where('status', '!=', 'lunas')
            ->whereDate('jatuh_tempo', '<=', now()->addDays(30)->toDateString())
            ->when($cabangId, fn ($q) => $q->where('cabang_id', $cabangId))
            ->sum(DB::raw('jumlah - jumlah_dibayar'));

        // Utang jatuh tempo ≤ 30 hari (status belum_lunas/sebagian)
        $utang30d = Utang::whereIn('status', ['belum_lunas', 'sebagian'])
            ->whereDate('jatuh_tempo', '<=', now()->addDays(30)->toDateString())
            ->when($cabangId, fn ($q) => $q->where('cabang_id', $cabangId))
            ->sum(DB::raw('jumlah - jumlah_dibayar'));

        $proyeksi30d = round($saldoKas + $piutang30d - $utang30d, 2);

        return [
            'saldo_kas' => round($saldoKas, 2),
            'piutang_30d' => round($piutang30d, 2),
            'utang_30d' => round($utang30d, 2),
            'proyeksi_30d' => $proyeksi30d,
            'drill_piutang' => route('laporan.drill', ['model' => 'Piutang']),
            'drill_utang' => route('laporan.drill', ['model' => 'Utang']),
        ];
    }

    /** Marketing: komposisi tier pelanggan + performa broadcast (dari notifikasi_keluar) */
    public function getMarketInsightProperty(): array
    {
        $tierRows = TierMembership::withCount('pelanggan')->orderBy('urutan')->get();
        $totalPelanggan = max(1, (int) Pelanggan::count());

        $notifikasi = NotifikasiKeluar::whereNotNull('kampanye_broadcast_id')
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return [
            'tier' => $tierRows->map(fn ($t) => [
                'nama' => $t->nama,
                'jumlah' => $t->pelanggan_count,
                'persen' => round($t->pelanggan_count / $totalPelanggan * 100, 1),
            ])->values(),
            'total_pelanggan' => Pelanggan::count(),
            'broadcast' => [
                'total_kampanye' => KampanyeBroadcast::count(),
                'terkirim' => (int) ($notifikasi['terkirim'] ?? 0),
                'pending' => (int) ($notifikasi['pending'] ?? 0),
                'gagal' => (int) ($notifikasi['gagal'] ?? 0),
            ],
        ];
    }

    /** Staff-gudang: PO pending (draft/menunggu) selain stok kritis */
    public function getPoPendingProperty(): array
    {
        $query = PurchaseOrder::with('supplier')->whereIn('status', ['draft', 'dikirim']);
        if (session('cabang_id')) {
            $query->whereHas('gudangTujuan', fn ($q) => $q->where('cabang_id', session('cabang_id')));
        }

        return [
            'total' => (clone $query)->count(),
            'total_nilai' => round((float) (clone $query)->sum('total'), 2),
            'items' => $query->orderByDesc('created_at')->limit(6)->get(),
        ];
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
            'chartOmzet30' => $this->chartOmzet30Hari,
            'chartKategori' => $this->chartKategori,
            'chartServisStatus' => $this->chartServisStatus,
            'chartPiutangAging' => $this->chartPiutangAging,
            'chartStokKritis' => $this->chartStokKritis,
            'currentRole' => $this->role,
            'drillDownModels' => $this->getDrillDownModels(),
        ] + match ($this->role) {
            // [T-27] Widget role-scoped: hanya properti role ini yg dievaluasi (query ringan, lazy via accessor)
            'kasir' => ['omzetShiftKasir' => $this->omzetShiftKasir],
            'finance' => ['ringkasanKeuangan' => $this->ringkasanKeuangan, 'treasuryProjection' => $this->treasuryProjection],
            'marketing' => ['marketInsight' => $this->marketInsight],
            'staff-gudang' => ['poPending' => $this->poPending],
            default => [
                'omzetShiftKasir' => $this->omzetShiftKasir,
                'ringkasanKeuangan' => $this->ringkasanKeuangan,
                'marketInsight' => $this->marketInsight,
                'poPending' => $this->poPending,
            ],
        })->layout('layouts.backoffice', ['header' => 'Dashboard']);
    }
}
