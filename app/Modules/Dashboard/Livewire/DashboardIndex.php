<?php

namespace App\Modules\Dashboard\Livewire;

use App\Modules\Akunting\Models\AkunCOA;
use App\Modules\Akunting\Models\JurnalAkuntansi;
use App\Modules\Akunting\Models\Piutang;
use App\Modules\Akunting\Models\Utang;
use App\Modules\Crm\Models\KampanyeBroadcast;
use App\Modules\Crm\Models\Pelanggan;
use App\Modules\Crm\Models\TierMembership;
use App\Modules\Pos\Models\Transaksi;
use App\Modules\Pos\Models\TransaksiItem;
use App\Modules\Pos\Services\KasSesiState;
use App\Modules\Report\Services\ReportBuilderService;
use App\Modules\Reseller\Models\Komisi;
use App\Modules\Servis\Models\TiketServis;
use App\Modules\Wms\Models\PurchaseOrder;
use App\Modules\Wms\Models\StokItem;
use Closure;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

/**
 * Backoffice Dashboard (DASH-01: widget sesuai role).
 * Semua data agregat ringan — jumlah kecil per query, session cabang.
 */
class DashboardIndex extends Component
{
    /**
     * Status transaksi yang DIHITUNG masuk omzet/klaim "selesai":
     * - 'selesai'  → transaksi POS / selesai pengiriman
     * - 'lunas'    → transaksi marketplace (PaymentController menutup order dgn 'lunas',
     *                jurnal tetap dibuat) — wajib ikut agar Omzet & Klaim "Laba Bersih" selaras.
     */
    private const STATUS_OMZET = ['selesai', 'lunas'];

    /**
     * [B-15d] TTL cache widget (detik).
     * - 60  → widget live/hari-ini; sama dgn interval `wire:poll.60s` di blade,
     *         jadi satu siklus poll = 1 compute penuh, siklus berikutnya free.
     * - 120 → widget operasional (PO pending) yang tidak detik-per-detik.
     * - 300 → agregat periode (tren 30 hari, komposisi bulanan, laba bulanan).
     */
    private const TTL_LIVE = 60;

    private const TTL_OPERASIONAL = 120;

    private const TTL_AGGREGAT = 300;

    /**
     * [B-15d] Cache per-widget (pola sama dgn AkuntingDashboard key `laporan-…`).
     *
     * SEBELUMNYA: ~22 query base + 5-16 query widget per-role, TANPA cache →
     * tiap interaksi Livewire (render awal + tiap wire:poll.60s) memutar ulang
     * seluruh agregasi ke MySQL.
     *
     * KEY WAJIB memuat: `cabang_id` (anti bocor antar cabang), role (widget
     * per-role), dan tanggal (anti-butuh "hari ini" pas tengah malam), plus
     * suffix per-user untuk widget yang benar-benar per-aktor (omzet shift
     * kasir → user_id + sesi kas). angka & label TIDAK diubah — hanya
     * di-cache, TTL konservatif supaya polling 60 dtk tetap segar.
     *
     * @param  Closure(): mixed  $hitung  closure yang menghitung nilai widget
     */
    private function cacheWidget(string $widget, int $ttl, Closure $hitung, string $suffix = ''): mixed
    {
        $user = auth()->user();
        $roles = $user?->getRoleNames()->sort()->implode('+');

        $key = 'dash-'.$widget
            .'-c'.(session('cabang_id') ?? 0)
            .'-r'.($roles === '' ? 'anon' : $roles)
            .'-d'.now()->toDateString()
            .$suffix;

        return Cache::remember($key, $ttl, $hitung);
    }

    public function getOmzetHariIniProperty(): array
    {
        return $this->cacheWidget('omzet-hari-ini', self::TTL_LIVE, function (): array {
            $query = Transaksi::whereDate('created_at', now()->toDateString())
                ->whereIn('status', self::STATUS_OMZET);

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
        });
    }

    public function getAntrianServisProperty(): array
    {
        return $this->cacheWidget('antrian-servis', self::TTL_LIVE, function (): array {
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
        });
    }

    public function getStokKritisProperty(): array
    {
        return $this->cacheWidget('stok-kritis', self::TTL_LIVE, function (): array {
            $cabangId = session('cabang_id');

            // Definisi selaras dgn ReorderService::jalankan() — stok kritis = jumlah < minimum
            // DAN minimum > 0 (item tanpa minimum tidak pernah dihitung kritis).
            $query = StokItem::whereColumn('jumlah', '<', 'jumlah_minimum')
                ->where('jumlah_minimum', '>', 0)
                ->with(['produk', 'gudang']);

            if ($cabangId) {
                $query->whereHas('gudang', fn ($q) => $q->where('cabang_id', $cabangId));
            }

            $items = $query->orderByRaw('(jumlah_minimum - jumlah) desc')->limit(10)->get();

            return [
                'total' => (clone $query)->count(),
                'items' => $items,
            ];
        });
    }

    /**
     * Piutang jatuh tempo ≤7 hari — SCOPED ke cabang aktif (konsisten dgn
     * Ringkasan Keuangan & Treasury; angka dalam 1 halaman harus sama).
     */
    public function getPiutangJatuhTempoProperty(): array
    {
        return $this->cacheWidget('piutang-jatuh-tempo', self::TTL_LIVE, function (): array {
            $cabangId = session('cabang_id');

            $dasar = fn () => Piutang::where('status', '!=', 'lunas')
                ->whereDate('jatuh_tempo', '<=', now()->addDays(7)->toDateString())
                ->when($cabangId, fn ($q) => $q->where('cabang_id', $cabangId));

            $items = $dasar()
                ->with('pelanggan')
                ->orderBy('jatuh_tempo')
                ->limit(8)
                ->get();

            return [
                'total' => $dasar()->count(),
                'items' => $items,
            ];
        });
    }

    /**
     * Komisi pending — GLOBAL by design: tabel `komisi` tidak punya `cabang_id`
     * (sama dgn halaman Reseller). Ditandai eksplisit "semua cabang" di blade.
     */
    public function getKomisiPendingProperty(): float
    {
        return (float) $this->cacheWidget('komisi-pending', self::TTL_LIVE, fn (): float => (float) Komisi::where('status', 'pending')->sum('nominal_komisi'));
    }

    public function getTransaksiTerbaruProperty()
    {
        return $this->cacheWidget('transaksi-terbaru', self::TTL_LIVE, fn () => Transaksi::with(['pelanggan', 'cabang'])
            ->when(session('cabang_id'), fn ($q) => $q->where('cabang_id', session('cabang_id')))
            ->latest()
            ->limit(6)
            ->get());
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
        return $this->cacheWidget('chart-omzet-30', self::TTL_AGGREGAT, function (): array {
            $cabangId = session('cabang_id');

            $rows = Transaksi::whereIn('status', self::STATUS_OMZET)
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
        });
    }

    /** Komposisi omzet per kategori produk (donut): [label, nilai] */
    public function getChartKategoriProperty(): array
    {
        return $this->cacheWidget('chart-kategori', self::TTL_AGGREGAT, function (): array {
            $cabangId = session('cabang_id');

            $query = TransaksiItem::query()
                ->join('produk', 'produk.id', '=', 'transaksi_item.produk_id')
                ->join('transaksi', 'transaksi.id', '=', 'transaksi_item.transaksi_id')
                ->whereIn('transaksi.status', self::STATUS_OMZET)
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
        });
    }

    /** Status servis aktif per tahap kanban (bar chart) — 1 GROUP BY (hemat query) */
    public function getChartServisStatusProperty(): array
    {
        return $this->cacheWidget('chart-servis-status', self::TTL_LIVE, function (): array {
            $statuses = ['diajukan_online', 'diterima', 'diagnosa', 'menunggu_approval', 'disetujui', 'dikerjakan', 'qc', 'selesai'];

            $hitung = TiketServis::query()
                ->when(session('cabang_id'), fn ($q) => $q->where('cabang_id', session('cabang_id')))
                ->whereIn('status', $statuses)
                ->groupBy('status')
                ->selectRaw('status, COUNT(*) as total')
                ->pluck('total', 'status');

            $labels = collect($statuses)->map(fn ($s) => str_replace('_', ' ', $s))->values()->all();
            $values = collect($statuses)
                ->map(fn ($s) => (int) ($hitung[$s] ?? 0))
                ->values()
                ->all();

            return ['labels' => $labels, 'values' => $values];
        });
    }

    /** Piutang aging (0-30, 31-60, 61-90, >90 hari) — scoped cabang aktif */
    public function getChartPiutangAgingProperty(): array
    {
        return $this->cacheWidget('chart-piutang-aging', self::TTL_AGGREGAT, function (): array {
            $semua = Piutang::where('status', '!=', 'lunas')
                ->when(session('cabang_id'), fn ($q) => $q->where('cabang_id', session('cabang_id')))
                ->get();
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
        });
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

        // [B-15d] Widget INI per-aktor: key wajib memuat user_id (kasir) + sesi kas
        // (batas omzet = waktu sesi dibuka), selain cabang + role.
        // `?? 0` (bukan `?->`) karena KasSesiState::sesiKasAktif() nullable —
        // bentuk `?->` di kiri `??` drying oleh PHPStan tapi tetap null di runtime.
        $suffix = '-u'.(int) ($user->id ?? 0).'-s'.(int) ($sesi->id ?? 0);

        return $this->cacheWidget('omzet-shift-kasir', self::TTL_LIVE, function () use ($user, $sesi): array {
            $query = Transaksi::whereIn('status', self::STATUS_OMZET)
                ->where('kasir_id', $user?->id);

            if ($sesi) {
                $query->where('cabang_id', $sesi->cabang_id)->where('created_at', '>=', $sesi->dibuka_at);
            } else {
                // Fallback tanpa sesi kas: tetap batasi ke cabang aktif (konsisten dg widget lain).
                $query->whereDate('created_at', now()->toDateString())
                    ->when(session('cabang_id'), fn ($q) => $q->where('cabang_id', session('cabang_id')));
            }

            return [
                'omzet' => round((float) $query->sum('total_akhir'), 2),
                'jumlah_transaksi' => (clone $query)->count(),
                'sesi' => $sesi ? '#'.$sesi->id.' ('.$sesi->dibuka_at.')' : 'tanpa sesi (omzet hari ini)',
            ];
        }, $suffix);
    }

    /** Finance: laba bersih bulan berjalan + total piutang belum lunas */
    public function getRingkasanKeuanganProperty(): array
    {
        return $this->cacheWidget('ringkasan-keuangan', self::TTL_AGGREGAT, function (): array {
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
                // Alias `total_sisa` (bukan `sisa`): accessor Piutang::getSisaAttribute
                // menimpa kolom alias `sisa` → angka total piutang selalu 0.
                'total_piutang' => round((float) Piutang::where('status', '!=', 'lunas')->when($cabangId, fn ($q) => $q->where('cabang_id', $cabangId))->selectRaw('SUM(jumlah - jumlah_dibayar) as total_sisa')->value('total_sisa'), 2),
                'piutang_lewat' => Piutang::where('status', '!=', 'lunas')
                    ->whereDate('jatuh_tempo', '<', now()->toDateString())
                    ->when($cabangId, fn ($q) => $q->where('cabang_id', $cabangId))
                    ->count(),
            ];
        });
    }

    /** F3-6 Treasury: proyeksi arus kas 30 hari = kas sekarang + piutang 30d - utang 30d */
    public function getTreasuryProjectionProperty(): array
    {
        return $this->cacheWidget('treasury', self::TTL_LIVE, function (): array {
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
        });
    }

    /** Marketing: komposisi tier pelanggan + status lifecycle broadcast campaign. */
    public function getMarketInsightProperty(): array
    {
        return $this->cacheWidget('market-insight', self::TTL_AGGREGAT, function (): array {
            $tierRows = TierMembership::withCount('pelanggan')->orderBy('urutan')->get();
            $totalPelanggan = max(1, (int) Pelanggan::count());

            // Semua angka memakai satu tabel/satuan: jumlah campaign per status.
            // Jangan mencampur campaign count dengan status recipient notifikasi.
            $campaignStatus = KampanyeBroadcast::query()
                ->selectRaw('status, count(*) as total')
                ->whereIn('status', ['draft', 'terjadwal', 'terkirim', 'terkirim_sebagian', 'gagal'])
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
                    'draft' => (int) ($campaignStatus['draft'] ?? 0),
                    'terjadwal' => (int) ($campaignStatus['terjadwal'] ?? 0),
                    'terkirim' => (int) ($campaignStatus['terkirim'] ?? 0),
                    'terkirim_sebagian' => (int) ($campaignStatus['terkirim_sebagian'] ?? 0),
                    'gagal' => (int) ($campaignStatus['gagal'] ?? 0),
                ],
            ];
        });
    }

    /** Staff-gudang: PO pending (usulan reorder/draft/dikirim) selain stok kritis */
    public function getPoPendingProperty(): array
    {
        return $this->cacheWidget('po-pending', self::TTL_OPERASIONAL, function (): array {
            $query = PurchaseOrder::with('supplier')->whereIn('status', ['usulan', 'draft', 'dikirim']);
            if (session('cabang_id')) {
                $query->whereHas('gudangTujuan', fn ($q) => $q->where('cabang_id', session('cabang_id')));
            }

            return [
                'total' => (clone $query)->count(),
                'total_nilai' => round((float) (clone $query)->sum('total'), 2),
                'items' => $query->orderByDesc('created_at')->limit(6)->get(),
            ];
        });
    }

    /**
     * Data aman (default) utk SEMUA widget role — dikirim di setiap cabang render
     * sehingga blade tidak pernah memproses undefined variable (aman utk multi-role
     * / role tanpa widget tertentu).
     *
     * @return array<string, mixed>
     */
    private function defaultWidgetData(): array
    {
        return [
            'omzetShiftKasir' => ['omzet' => 0, 'jumlah_transaksi' => 0, 'sesi' => 'tanpa sesi (omzet hari ini)'],
            'ringkasanKeuangan' => ['laba_bulan_ini' => 0, 'total_piutang' => 0, 'piutang_lewat' => 0],
            'treasuryProjection' => [
                'saldo_kas' => 0,
                'piutang_30d' => 0,
                'utang_30d' => 0,
                'proyeksi_30d' => 0,
                'drill_piutang' => route('laporan.drill', ['model' => 'Piutang']),
                'drill_utang' => route('laporan.drill', ['model' => 'Utang']),
            ],
            'marketInsight' => [
                'tier' => collect(),
                'total_pelanggan' => 0,
                'broadcast' => [
                    'total_kampanye' => 0,
                    'draft' => 0,
                    'terjadwal' => 0,
                    'terkirim' => 0,
                    'terkirim_sebagian' => 0,
                    'gagal' => 0,
                ],
            ],
            'poPending' => ['total' => 0, 'total_nilai' => 0, 'items' => collect()],
        ];
    }

    public function render()
    {
        $user = auth()->user();
        $widgetData = $this->defaultWidgetData();

        // Evaluasi HANYA widget yang benar-benar dirender blade utk role tsb (@role = hasRole,
        // jadi multi-role juga kebagi datanya). Sumber role disamakan: `hasRole` (Spatie),
        // bukan kombinasi getRoleNames()->first() vs @role — hindari 500 utk user multi-role.
        if ($user?->hasRole('kasir')) {
            $widgetData['omzetShiftKasir'] = $this->omzetShiftKasir;
        }
        if ($user?->hasRole('finance')) {
            $widgetData['ringkasanKeuangan'] = $this->ringkasanKeuangan;
            $widgetData['treasuryProjection'] = $this->treasuryProjection;
        }
        // [P1-A] kartu Treasury dirender utk finance DAN super-admin → keduanya wajib dimuat.
        if ($user?->hasRole('super-admin')) {
            $widgetData['treasuryProjection'] = $this->treasuryProjection;
        }
        if ($user?->hasRole('marketing')) {
            $widgetData['marketInsight'] = $this->marketInsight;
        }
        if ($user?->hasRole('staff-gudang')) {
            $widgetData['poPending'] = $this->poPending;
        }

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
        ] + $widgetData)->layout('layouts.backoffice', ['header' => 'Dashboard']);
    }
}
