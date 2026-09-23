<?php

namespace App\Modules\Akunting\Controllers;

use App\Modules\Akunting\Jobs\ExportLaporanJob;
use App\Modules\Akunting\Models\AkunCOA;
use App\Modules\Akunting\Models\JurnalAkuntansi;
use App\Modules\Akunting\Models\Piutang;
use App\Modules\Akunting\Models\Utang;
use App\Modules\Akunting\Services\JurnalService;
use App\Modules\Pos\Services\KasSesiState;
use App\Modules\Rbac\Services\AuditService;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class AkuntingController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected JurnalService $jurnalService
    ) {}

    // [API: ACC-01] Daftar COA (editable terbatas: super-admin/finance)
    public function indexCoa(Request $request)
    {
        $tipe = $request->query('tipe');

        $query = AkunCOA::orderBy('kode');

        if ($tipe) {
            $query->where('tipe', $tipe);
        }

        return $this->success($query->get(), 'Daftar Chart of Account berhasil diambil');
    }

    // [API: ACC-02] Create / update COA
    public function storeCoa(Request $request)
    {
        $request->validate([
            'kode' => 'required|string|max:20|unique:akun_coa,kode',
            'nama' => 'required|string|max:255',
            'tipe' => 'required|in:aset,kewajiban,ekuitas,pendapatan,beban',
            'kelompok' => 'required|string|max:100',
            'saldo_normal' => 'required|in:debit,kredit',
            'is_active' => 'sometimes|boolean',
        ]);

        $akun = AkunCOA::create($request->all());

        return $this->success($akun, 'Akun COA berhasil dibuat', 201);
    }

    public function updateCoa(Request $request, $id)
    {
        $akun = AkunCOA::findOrFail($id);

        $request->validate([
            'kode' => 'sometimes|string|max:20|unique:akun_coa,kode,'.$akun->id,
            'nama' => 'sometimes|string|max:255',
            'tipe' => 'sometimes|in:aset,kewajiban,ekuitas,pendapatan,beban',
            'kelompok' => 'sometimes|string|max:100',
            'saldo_normal' => 'sometimes|in:debit,kredit',
            'is_active' => 'sometimes|boolean',
        ]);

        $akun->update($request->all());

        app(AuditService::class)->catat(
            'AkunCOA', 'update', $akun->id,
            "Akun COA {$akun->kode} — {$akun->nama} diperbarui"
        );

        return $this->success($akun, 'Akun COA berhasil diperbarui');
    }

    // [API: ACC-03] Daftar jurnal (read-only)
    public function indexJurnal(Request $request)
    {
        $sumber = $request->query('sumber');
        $akunId = $request->query('akun_id');
        $dari = $request->query('dari');
        $sampai = $request->query('sampai');
        $cabangId = session('cabang_id');

        $query = JurnalAkuntansi::with(['akun', 'cabang', 'user'])
            ->latest('tanggal');

        if ($cabangId) {
            $query->where('cabang_id', $cabangId);
        }

        if ($sumber) {
            $query->where('sumber', $sumber);
        }

        if ($akunId) {
            $query->where('akun_coa_id', $akunId);
        }

        if ($dari) {
            $query->whereDate('tanggal', '>=', $dari);
        }

        if ($sampai) {
            $query->whereDate('tanggal', '<=', $sampai);
        }

        $jurnals = $query->paginate(30);

        // Agregat per no_jurnal untuk periksa balance
        $balanceCheck = $jurnals->getCollection()->groupBy('no_jurnal')->map(function ($rows) {
            return [
                'debit' => $rows->sum('debit'),
                'kredit' => $rows->sum('kredit'),
                'balance' => round($rows->sum('debit'), 2) === round($rows->sum('kredit'), 2),
            ];
        });

        return $this->success([
            'jurnal' => $jurnals,
            'balance_check' => $balanceCheck,
        ], 'Daftar jurnal berhasil diambil');
    }

    // [API: ACC-04] Jurnal manual (kasus khusus, super-admin/finance)
    public function storeJurnalManual(Request $request)
    {
        $request->validate([
            'tanggal' => 'required|date',
            'deskripsi' => 'required|string',
            'lines' => 'required|array|min:2',
            'lines.*.akun_kode' => 'required|exists:akun_coa,kode',
            'lines.*.debit' => 'nullable|numeric|min:0',
            'lines.*.kredit' => 'nullable|numeric|min:0',
        ]);

        try {
            $noJurnal = $this->jurnalService->generateNoJurnal('manual', session('cabang_id'));
            $this->jurnalService->post(
                $noJurnal,
                $request->tanggal,
                'manual',
                $request->lines,
                $request->deskripsi,
                session('cabang_id'),
                auth()->id()
            );

            return $this->success(['no_jurnal' => $noJurnal], 'Jurnal manual berhasil diposting', 201);
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 400);
        }
    }

    // [API: ACC-05] Laporan Laba Rugi (cache 15 menit)
    public function labaRugi(Request $request)
    {
        $dari = $request->query('dari', now()->startOfMonth()->toDateString());
        $sampai = $request->query('sampai', now()->toDateString());
        $cabangId = session('cabang_id');

        $cacheKey = "laporan-labarugi-{$cabangId}-{$dari}-{$sampai}";

        $laporan = Cache::remember($cacheKey, 900, function () use ($dari, $sampai, $cabangId) {
            $query = JurnalAkuntansi::whereDate('tanggal', '>=', $dari)
                ->whereDate('tanggal', '<=', $sampai);

            if ($cabangId) {
                $query->where('cabang_id', $cabangId);
            }

            $jurnals = $query->with('akun')->get();

            $pendapatan = $jurnals->where('akun.tipe', 'pendapatan')->groupBy('akun_coa_id')->map(fn ($rows) => [
                'akun' => $rows->first()->akun?->nama,
                'kode' => $rows->first()->akun?->kode,
                'total' => round($rows->sum('kredit') - $rows->sum('debit'), 2),
            ])->values();

            $beban = $jurnals->where('akun.tipe', 'beban')->groupBy('akun_coa_id')->map(fn ($rows) => [
                'akun' => $rows->first()->akun?->nama,
                'kode' => $rows->first()->akun?->kode,
                'total' => round($rows->sum('debit') - $rows->sum('kredit'), 2),
            ])->values();

            $totalPendapatan = $pendapatan->sum('total');
            $totalBeban = $beban->sum('total');

            return [
                'dari' => $dari,
                'sampai' => $sampai,
                'pendapatan' => $pendapatan,
                'beban' => $beban,
                'total_pendapatan' => $totalPendapatan,
                'total_beban' => $totalBeban,
                'laba_bersih' => round($totalPendapatan - $totalBeban, 2),
            ];
        });

        return $this->success($laporan, 'Laporan Laba Rugi berhasil diambil (cache 15 menit)');
    }

    // [API: ACC-06] Laporan Neraca
    public function neraca(Request $request)
    {
        $cabangId = session('cabang_id');
        $sampai = $request->query('sampai', now()->toDateString());

        $cacheKey = "laporan-neraca-{$cabangId}-{$sampai}";

        $neraca = Cache::remember($cacheKey, 900, function () use ($sampai, $cabangId) {
            $query = JurnalAkuntansi::whereDate('tanggal', '<=', $sampai);
            if ($cabangId) {
                $query->where('cabang_id', $cabangId);
            }

            $jurnals = $query->with('akun')->get();

            $grouped = $jurnals->groupBy('akun_coa_id')->map(function ($rows) {
                $akun = $rows->first()->akun;
                if (! $akun) {
                    return null;
                }

                $saldo = $akun->saldo_normal === 'debit'
                    ? $rows->sum('debit') - $rows->sum('kredit')
                    : $rows->sum('kredit') - $rows->sum('debit');

                return [
                    'tipe' => $akun->tipe,
                    'kelompok' => $akun->kelompok,
                    'kode' => $akun->kode,
                    'nama' => $akun->nama,
                    'saldo' => round($saldo, 2),
                ];
            })->filter()->values();

            return [
                'sampai_tanggal' => $sampai,
                'aset' => $grouped->where('tipe', 'aset')->values(),
                'kewajiban' => $grouped->where('tipe', 'kewajiban')->values(),
                'ekuitas' => $grouped->where('tipe', 'ekuitas')->values(),
                'total_aset' => round($grouped->where('tipe', 'aset')->sum('saldo'), 2),
                'total_kewajiban' => round($grouped->where('tipe', 'kewajiban')->sum('saldo'), 2),
                'total_ekuitas' => round($grouped->where('tipe', 'ekuitas')->sum('saldo'), 2),
            ];
        });

        return $this->success($neraca, 'Laporan Neraca berhasil diambil (cache 15 menit)');
    }

    // [API: ACC-07] Buku Besar per akun
    public function bukuBesar(Request $request, $akunId)
    {
        $dari = $request->query('dari', now()->startOfMonth()->toDateString());
        $sampai = $request->query('sampai', now()->toDateString());

        $akun = AkunCOA::findOrFail($akunId);
        $jurnals = JurnalAkuntansi::with(['cabang', 'user'])
            ->where('akun_coa_id', $akunId)
            ->whereDate('tanggal', '>=', $dari)
            ->whereDate('tanggal', '<=', $sampai)
            ->orderBy('tanggal')
            ->get();

        $saldoBerjalan = 0;
        $rows = $jurnals->map(function ($j) use ($akun, &$saldoBerjalan) {
            $saldoBerjalan += $akun->saldo_normal === 'debit'
                ? (float) $j->debit - (float) $j->kredit
                : (float) $j->kredit - (float) $j->debit;

            return [
                'tanggal' => $j->tanggal->format('d/m/Y'),
                'no_jurnal' => $j->no_jurnal,
                'deskripsi' => $j->deskripsi,
                'debit' => $j->debit,
                'kredit' => $j->kredit,
                'saldo' => round($saldoBerjalan, 2),
            ];
        });

        return $this->success([
            'akun' => $akun,
            'dari' => $dari,
            'sampai' => $sampai,
            'entries' => $rows,
            'saldo_akhir' => round($saldoBerjalan, 2),
        ], 'Buku besar berhasil diambil');
    }

    // [API: ACC-11][T-24] Export laporan → queue (async, RAM-heap friendly)
    // Method-agnostik: bekerja utk POST (form/JSON) maupun GET (?jenis=&periode_dari=&periode_sampai=).
    // Route GET /api/akunting/export belum didaftarkan di routes/api.php:148 (di luar scope fixer) —
    // tambahkan Route::match(['get','post']) agar frontend bisa memakai query param.
    public function export(Request $request)
    {
        $request->validate([
            'jenis' => 'required|in:laba_rugi,neraca,buku_besar,arus_kas,stok,pelanggan,servis,piutang,utang,pajak',
            'periode_dari' => 'nullable|date',
            'periode_sampai' => 'nullable|date',
            'akun_id' => 'nullable|exists:akun_coa,id',
        ]);

        dispatch(new ExportLaporanJob(
            jenis: $request->input('jenis'),
            periodeDari: $request->input('periode_dari'),
            periodeSampai: $request->input('periode_sampai'),
            cabangId: session('cabang_id'),
            akunId: $request->input('akun_id'),
            userId: auth()->id()
        ));

        return $this->success(
            ['status' => 'queued', 'pesan' => 'Export diproses via antrian — notifikasi + link download muncul setelah selesai'],
            'Export laporan dijadwalkan (async)'
        );
    }

    // [ACC-11b] Download hasil export yg sudah siap (path base64 dari session flash)
    public function exportDownload(Request $request)
    {
        $path = base64_decode((string) $request->query('path', ''));
        if (str_contains($path, '..') || ! str_starts_with($path, 'exports/')) {
            return $this->error('Path tidak valid', 400);
        }

        $full = storage_path('app/'.$path);
        if (! file_exists($full)) {
            return $this->error('File export tidak ditemukan atau belum siap', 404);
        }

        return response()->download($full);
    }

    // [API: ACC-08] Piutang (AR) list + reminder jatuh tempo
    public function indexPiutang(Request $request)
    {
        $status = $request->query('status');

        $query = Piutang::with('pelanggan')
            ->latest();

        if ($status) {
            $query->where('status', $status);
        }

        $piutang = $query->paginate(20)->through(function ($p) {
            return [
                'id' => $p->id,
                'no_piutang' => $p->no_piutang,
                'pelanggan' => $p->pelanggan?->nama,
                'jumlah' => $p->jumlah,
                'jumlah_dibayar' => $p->jumlah_dibayar,
                'sisa' => $p->sisa,
                'jatuh_tempo' => $p->jatuh_tempo?->format('d/m/Y'),
                'status' => $p->status,
                'jatuh_tempo_lewat' => $p->jatuh_tempo_lewat,
            ];
        });

        return $this->success($piutang, 'Daftar piutang berhasil diambil');
    }

    // [API: ACC-09] Utang (AP) list
    public function indexUtang(Request $request)
    {
        $status = $request->query('status');

        $query = Utang::latest();

        if ($status) {
            $query->where('status', $status);
        }

        $utang = $query->paginate(20)->through(function ($u) {
            return [
                'id' => $u->id,
                'no_utang' => $u->no_utang,
                'referensi_tipe' => $u->referensi_tipe,
                'kreditor' => $u->kreditor_nama ?? $u->pelanggan?->nama,
                'jumlah' => $u->jumlah,
                'jumlah_dibayar' => $u->jumlah_dibayar,
                'sisa' => $u->sisa,
                'jatuh_tempo' => $u->jatuh_tempo?->format('d/m/Y'),
                'status' => $u->status,
            ];
        });

        return $this->success($utang, 'Daftar utang berhasil diambil');
    }

    // [API: ACC-10] Bayar utang (pencatatan pembayaran)
    public function bayarUtang(Request $request, $id)
    {
        $request->validate([
            'jumlah' => 'required|numeric|min:1',
        ]);

        $utang = Utang::findOrFail($id);
        $dibayar = (float) $utang->jumlah_dibayar + (float) $request->jumlah;

        if ($dibayar > (float) $utang->jumlah) {
            return $this->error('Pembayaran melebihi sisa utang', 422);
        }

        $utang->update([
            'jumlah_dibayar' => $dibayar,
            'status' => $dibayar >= (float) $utang->jumlah ? 'lunas' : 'sebagian',
        ]);

        // Jurnal pembayaran utang: Debit Utang (210-03/210-01), Kredit Kas (110-01)
        try {
            $akunUtang = match ($utang->referensi_tipe) {
                'komisi' => '210-03',
                default => '210-01',
            };

            $noJurnal = $this->jurnalService->generateNoJurnal('pembayaran', session('cabang_id'));
            $this->jurnalService->post(
                $noJurnal,
                now(),
                'manual',
                [
                    ['akun_kode' => $akunUtang, 'debit' => (float) $request->jumlah, 'kredit' => 0],
                    ['akun_kode' => '110-01', 'debit' => 0, 'kredit' => (float) $request->jumlah],
                ],
                "Pembayaran utang {$utang->no_utang}",
                session('cabang_id'),
                auth()->id()
            );
        } catch (\Exception $e) {
            // Jurnal gagal jangan blokir pembayaran — log
            Log::warning("Jurnal bayar utang gagal: {$e->getMessage()}");
        }

        return $this->success($utang->fresh(), 'Pembayaran utang berhasil dicatat');
    }

    // [API: ACC-08b] Bayar piutang (pencatatan penerimaan AR)
    public function bayarPiutang(Request $request, $id)
    {
        $request->validate([
            'jumlah' => 'required|numeric|min:1',
        ]);

        $piutang = Piutang::findOrFail($id);
        $dibayar = (float) $piutang->jumlah_dibayar + (float) $request->jumlah;

        if ($dibayar > (float) $piutang->jumlah) {
            return $this->error('Pembayaran melebihi sisa piutang', 422);
        }

        $piutang->update([
            'jumlah_dibayar' => $dibayar,
            'status' => $dibayar >= (float) $piutang->jumlah ? 'lunas' : 'sebagian',
        ]);

        // Jurnal penerimaan piutang: Debit Kas (110-01), Kredit Piutang Usaha (120-01)
        try {
            $noJurnal = $this->jurnalService->generateNoJurnal('pembayaran', session('cabang_id'));
            $this->jurnalService->post(
                $noJurnal,
                now(),
                'manual',
                [
                    ['akun_kode' => '110-01', 'debit' => (float) $request->jumlah, 'kredit' => 0],   // Kas masuk
                    ['akun_kode' => '120-01', 'debit' => 0, 'kredit' => (float) $request->jumlah],  // Piutang turun
                ],
                "Penerimaan piutang {$piutang->no_piutang}",
                session('cabang_id'),
                auth()->id()
            );
        } catch (\Exception $e) {
            Log::warning("Jurnal bayar piutang gagal: {$e->getMessage()}");
        }

        return $this->success($piutang->fresh(), 'Pembayaran piutang berhasil dicatat');
    }

    // [T-33] Riwayat sesi kas per cabang (untuk laporan Akunting)
    public function kasSesi(Request $request)
    {
        $sesi = app(KasSesiState::class)
            ->riwayat($request->query('cabang_id') ?? session('cabang_id'), 50);

        return $this->success(['sesi' => $sesi], 'Riwayat kas sesi');
    }

    // [API: ACC-04b] Laporan Arus Kas — metode tidak langsung (PRD §4.6)
    public function arusKas(Request $request)
    {
        $dari = $request->query('dari', now()->startOfMonth()->toDateString());
        $sampai = $request->query('sampai', now()->toDateString());
        $cabangId = session('cabang_id');

        $query = JurnalAkuntansi::whereDate('tanggal', '>=', $dari)
            ->whereDate('tanggal', '<=', $sampai);
        if ($cabangId) {
            $query->where('cabang_id', $cabangId);
        }

        $jurnals = $query->with('akun')->get();

        // Laba bersih = total pendapatan - total beban (metode tidak langsung)
        $labaBersih = round(
            $jurnals->where('akun.tipe', 'pendapatan')->sum(fn ($j) => (float) $j->kredit - (float) $j->debit)
            - $jurnals->where('akun.tipe', 'beban')->sum(fn ($j) => (float) $j->debit - (float) $j->kredit),
            2
        );

        // Perubahan modal kerja dari saldo akun (kenaikan = arus keluar untuk aset, arus masuk untuk kewajiban)
        // Delta periode: total pergerakan POSITIF (debit untuk aset = kenaikan → pengurang arus kas operasi)
        $piutangDelta = round($jurnals->where('akun.kode', '120-01')->sum('debit') - $jurnals->where('akun.kode', '120-01')->sum('kredit'), 2);
        $persediaanDelta = round($jurnals->where('akun.kode', '130-01')->sum('debit') - $jurnals->where('akun.kode', '130-01')->sum('kredit'), 2);
        $utangDelta = round($jurnals->where('akun.kode', '210-01')->sum('kredit') - $jurnals->where('akun.kode', '210-01')->sum('debit')
            + $jurnals->where('akun.kode', '210-03')->sum('kredit') - $jurnals->where('akun.kode', '210-03')->sum('debit'), 2);

        $arusKasOperasi = round($labaBersih - $piutangDelta - $persediaanDelta + $utangDelta, 2);

        // Arus investasi (asets non-modal kerja) & pendanaan (ekuitas/modal)
        $arusInvestasi = round(
            -$jurnals->where('akun.kelompok', 'aset_tetap')->sum(fn ($j) => (float) $j->debit - (float) $j->kredit)
            - $jurnals->where('akun.kelompok', 'peralatan')->sum(fn ($j) => (float) $j->debit - (float) $j->kredit)
            - $jurnals->where('akun.kelompok', 'perlengkapan')->sum(fn ($j) => (float) $j->debit - (float) $j->kredit),
            2
        );
        $arusPendanaan = round(
            $jurnals->where('akun.kelompok', 'modal')->sum(fn ($j) => (float) $j->kredit - (float) $j->debit)
            + $jurnals->where('akun.kelompok', 'laba_ditahan')->sum(fn ($j) => (float) $j->kredit - (float) $j->debit),
            2
        );

        $kasNeto = round($arusKasOperasi + $arusInvestasi + $arusPendanaan, 2);

        return $this->success([
            'dari' => $dari,
            'sampai' => $sampai,
            'laba_bersih' => $labaBersih,
            'penyesuaian' => [
                'kenaikan_piutang' => -$piutangDelta,
                'kenaikan_persediaan' => -$persediaanDelta,
                'kenaikan_utang' => $utangDelta,
            ],
            'arus_kas_operasi' => $arusKasOperasi,
            'arus_kas_investasi' => $arusInvestasi,
            'arus_kas_pendanaan' => $arusPendanaan,
            'kenaikan_kas_neto' => $kasNeto,
        ], 'Laporan Arus Kas (metode tidak langsung) berhasil diambil');
    }
}
