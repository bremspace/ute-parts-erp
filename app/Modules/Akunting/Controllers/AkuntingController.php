<?php

namespace App\Modules\Akunting\Controllers;

use App\Modules\Akunting\Jobs\ExportLaporanJob;
use App\Modules\Akunting\Models\AkunCOA;
use App\Modules\Akunting\Models\JurnalAkuntansi;
use App\Modules\Akunting\Models\Piutang;
use App\Modules\Akunting\Models\Utang;
use App\Modules\Akunting\Services\ExportLaporanService;
use App\Modules\Akunting\Services\JurnalService;
use App\Modules\Akunting\Services\PembayaranSubledgerService;
use App\Modules\Akunting\Services\ValidasiBarisJurnal;
use App\Modules\Pos\Services\KasSesiState;
use App\Modules\Rbac\Services\AuditService;
use App\Traits\ApiResponse;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

class AkuntingController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected JurnalService $jurnalService,
        protected PembayaranSubledgerService $pembayaranService
    ) {}

    /**
     * [B-10a / P0-2] Scope cabang aktif untuk query baca.
     *
     * WAJIB dipakai setiap query jurnal/AR/AP/laporan. `session('cabang_id')`
     * null berarti "tidak boleh melihat apa pun" — sebelumnya filter hanya
     * dipasang bila session truthy sehingga null = bocor ke SEMUA cabang.
     */
    private function cabangScopeId(): int
    {
        return (int) (session('cabang_id') ?? 0);
    }

    /**
     * [B-10a / P0-2] Mutasi wajib punya cabang aktif (fail-closed, 403).
     */
    private function cabangAktif(): int
    {
        $cabangId = (int) (session('cabang_id') ?? 0);

        if ($cabangId <= 0) {
            abort(403, 'Cabang aktif belum dipilih.');
        }

        return $cabangId;
    }

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
        $cabangId = $this->cabangScopeId();

        $query = JurnalAkuntansi::with(['akun', 'cabang', 'user'])
            ->latest('tanggal')
            ->where('cabang_id', $cabangId);

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
            'deskripsi' => 'required|string|max:255',
            'lines' => 'required|array|min:2',
            // [B-10e / P2-1] `exists:akun_coa,kode` dihapus: pesan default Laravel
            // berbahasa Inggris. Existence + is_active + sisi vs saldo_normal
            // kini dicek ValidasiBarisJurnal (sumber aturan yang sama dgn UI).
            'lines.*.akun_kode' => 'required|string|max:20',
            'lines.*.debit' => 'nullable|numeric|min:0',
            'lines.*.kredit' => 'nullable|numeric|min:0',
        ]);

        // [B-10e / P2-1] Aturan yang sama dgn form UI jurnal manual: akun aktif,
        // nominal > 0, satu sisi, sisi sesuai saldo_normal akun.
        $lines = collect($request->input('lines', []))
            ->map(fn ($line) => [
                'akun_kode' => is_array($line) ? (string) ($line['akun_kode'] ?? '') : '',
                'debit' => is_array($line) ? (float) ($line['debit'] ?? 0) : 0.0,
                'kredit' => is_array($line) ? (float) ($line['kredit'] ?? 0) : 0.0,
            ])
            ->values()
            ->all();

        $galat = ValidasiBarisJurnal::cekBaris($lines);
        if ($galat !== []) {
            return $this->error(implode(' ', $galat), 422, ['errors' => $galat]);
        }

        try {
            $noJurnal = $this->jurnalService->generateNoJurnal('manual', $this->cabangAktif());
            $this->jurnalService->post(
                $noJurnal,
                $request->tanggal,
                'manual',
                $request->lines,
                $request->deskripsi,
                $this->cabangAktif(),
                auth()->id()
            );

            return $this->success(['no_jurnal' => $noJurnal], 'Jurnal manual berhasil diposting', 201);
        } catch (\Exception $e) {
            // Termasuk "Jurnal tidak balance" — wajib balance sebelum disimpan.
            return $this->error($e->getMessage(), 400);
        }
    }

    // [API: ACC-05] Laporan Laba Rugi (cache 15 menit)
    public function labaRugi(Request $request)
    {
        $dari = $request->query('dari', now()->startOfMonth()->toDateString());
        $sampai = $request->query('sampai', now()->toDateString());
        $cabangId = $this->cabangScopeId();

        $cacheKey = "laporan-labarugi-{$cabangId}-{$dari}-{$sampai}";

        $laporan = Cache::remember($cacheKey, 900, function () use ($dari, $sampai, $cabangId) {
            $query = JurnalAkuntansi::whereDate('tanggal', '>=', $dari)
                ->whereDate('tanggal', '<=', $sampai)
                ->where('cabang_id', $cabangId);

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
        $cabangId = $this->cabangScopeId();
        $sampai = $request->query('sampai', now()->toDateString());

        $cacheKey = "laporan-neraca-{$cabangId}-{$sampai}";

        // [B-10e / P1-7] Sumber tunggal dgn export Excel:
        // ExportLaporanService::neracaSaldo() → SALDO KUMULATIF s/d $sampai
        // (bukan perubahan periode) + Laba Periode Berjalan sebagai bagian
        // ekuitas supaya Total Aset = Total Kewajiban + Ekuitas.
        $neraca = Cache::remember(
            $cacheKey,
            900,
            fn () => app(ExportLaporanService::class)->neracaSaldo($cabangId, (string) $sampai)
        );

        return $this->success($neraca, 'Laporan Neraca berhasil diambil (cache 15 menit)');
    }

    // [API: ACC-07] Buku Besar per akun
    public function bukuBesar(Request $request, $akunId)
    {
        $dari = $request->query('dari', now()->startOfMonth()->toDateString());
        $sampai = $request->query('sampai', now()->toDateString());

        $akun = AkunCOA::findOrFail($akunId);
        // [B-10a / P0-2] buku besar WAJIB scoped cabang (sebelumnya tanpa filter sama sekali)
        $jurnals = JurnalAkuntansi::with(['cabang', 'user'])
            ->where('akun_coa_id', $akunId)
            ->where('cabang_id', $this->cabangScopeId())
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
            'jenis' => 'required|in:laba_rugi,neraca,buku_besar,arus_kas,stok,pelanggan,servis,piutang,utang,pajak,jurnal,transaksi,komisi',
            'periode_dari' => 'nullable|date',
            'periode_sampai' => 'nullable|date',
            'akun_id' => 'nullable|exists:akun_coa,id',
            'format' => 'nullable|in:xlsx,csv', // [F2-5]
        ]);

        // [P2-10b] Opportunistic prune berkas exports/ usia >7 hari — tidak boleh menggagalkan dispatch
        ExportLaporanService::pruneOldExports();

        dispatch(new ExportLaporanJob(
            jenis: $request->input('jenis'),
            periodeDari: $request->input('periode_dari'),
            periodeSampai: $request->input('periode_sampai'),
            cabangId: $this->cabangAktif(),
            akunId: $request->input('akun_id'),
            userId: auth()->id(),
            format: $request->input('format', 'xlsx')
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

        // [P2-10c] Ownership: prefix {userId}_ di nama berkas wajib milik user aktif
        $basename = basename($path);
        if (! preg_match('/^(\d+)_/', $basename, $m) || (int) $m[1] !== (int) auth()->id()) {
            return $this->error('Anda tidak berhak mengunduh berkas ekspor milik pengguna lain.', 403);
        }

        $full = Storage::disk('local')->path($path); // [F2-5] disk 'local' root = storage/app/private — path konsisten dgn lokasi tulis export
        if (! file_exists($full)) {
            return $this->error('File export tidak ditemukan atau belum siap', 404);
        }

        return response()->download($full);
    }

    // [API: ACC-08] Piutang (AR) list + reminder jatuh tempo
    public function indexPiutang(Request $request)
    {
        $status = $request->query('status');

        // [B-10a / P0-2] AR selalu scoped cabang aktif
        $query = Piutang::with('pelanggan')
            ->where('cabang_id', $this->cabangScopeId())
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

        // [B-10a / P0-2] AP selalu scoped cabang aktif
        $query = Utang::where('cabang_id', $this->cabangScopeId())->latest();

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
    // [B-10a / P0-1] Delegasikan ke PembayaranSubledgerService: subledger + jurnal
    // dalam SATU transaksi. Exception jurnal TIDAK lagi ditelan jadi 200 sukses.
    public function bayarUtang(Request $request, $id)
    {
        $request->validate([
            'jumlah' => 'required|numeric|min:1',
        ]);

        try {
            $hasil = $this->pembayaranService->bayarUtang(
                (int) $id,
                (float) $request->jumlah,
                $this->cabangAktif(),
                auth()->id(),
                $this->kunciIdempotensi($request)
            );
        } catch (ModelNotFoundException) {
            return $this->error('Data utang tidak ditemukan.', 404);
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 422);
        }

        return $this->success([
            'utang' => $hasil['model'],
            'no_jurnal' => $hasil['no_jurnal'],
        ], 'Pembayaran utang berhasil dicatat');
    }

    // [API: ACC-08b] Bayar piutang (pencatatan penerimaan AR)
    // [B-10a / P0-1] Sama seperti bayarUtang — satu transaksi, fail-closed.
    public function bayarPiutang(Request $request, $id)
    {
        $request->validate([
            'jumlah' => 'required|numeric|min:1',
        ]);

        try {
            $hasil = $this->pembayaranService->bayarPiutang(
                (int) $id,
                (float) $request->jumlah,
                $this->cabangAktif(),
                auth()->id(),
                $this->kunciIdempotensi($request)
            );
        } catch (ModelNotFoundException) {
            return $this->error('Data piutang tidak ditemukan.', 404);
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 422);
        }

        return $this->success([
            'piutang' => $hasil['model'],
            'no_jurnal' => $hasil['no_jurnal'],
        ], 'Pembayaran piutang berhasil dicatat');
    }

    /**
     * [B-10a / P0-1] Kunci idempotensi opsional dari header `Idempotency-Key`
     * agar retry/double-submit pembayaran tidak menghasilkan jurnal ganda.
     */
    private function kunciIdempotensi(Request $request): ?string
    {
        $kunci = trim((string) $request->header('Idempotency-Key', ''));

        return $kunci === '' ? null : mb_substr($kunci, 0, 120);
    }

    // [T-33] Riwayat sesi kas per cabang (untuk laporan Akunting)
    public function kasSesi(Request $request)
    {
        // [B-10a / P0-2] query param `cabang_id` sebelumnya bisa meminta sesi kas
        // cabang lain → riwayat hanya untuk cabang aktif.
        $sesi = app(KasSesiState::class)
            ->riwayat($this->cabangScopeId() ?: null, 50);

        return $this->success(['sesi' => $sesi], 'Riwayat kas sesi');
    }

    // [API: ACC-04b] Laporan Arus Kas — metode tidak langsung (PRD §4.6)
    public function arusKas(Request $request)
    {
        $dari = $request->query('dari', now()->startOfMonth()->toDateString());
        $sampai = $request->query('sampai', now()->toDateString());
        $cabangId = $this->cabangScopeId();

        $query = JurnalAkuntansi::whereDate('tanggal', '>=', $dari)
            ->whereDate('tanggal', '<=', $sampai)
            ->where('cabang_id', $cabangId);

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
