<?php

namespace App\Modules\Akunting\Services;

use App\Modules\Akunting\Models\JurnalAkuntansi;
use App\Modules\Akunting\Models\JurnalHeader;
use App\Modules\Akunting\Models\PeriodeAkuntansi;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Service Tutup Buku & Pengendalian Periode Akuntansi (ISO 9001 / ISO 27001 / SAK).
 *
 * Mengelola siklus penutupan buku periodik (monthly/yearly close):
 * 1. Pra-cek kelayakan & keseimbangan neraca (Neraca Saldo balance).
 * 2. Generate jurnal penutup double-entry otomatis (tutup akun nominal ke 310-02 Laba Ditahan).
 * 3. Kunci periode buku (Hard Lock) untuk mencegah backdating transaksi.
 * 4. Pembukaan kembali periode (Re-open) terkontrol dengan reversal jurnal & audit trail.
 */
class TutupBukuService
{
    public const AKUN_LABA_DITAHAN = '310-02';

    public function __construct(
        protected JurnalService $jurnalService,
        protected ExportLaporanService $exportLaporanService
    ) {}

    /**
     * Dapatkan atau buat entri status periode akuntansi untuk cabang, tahun, bulan.
     */
    public function getPeriode(int $cabangId, int $tahun, int $bulan): PeriodeAkuntansi
    {
        return PeriodeAkuntansi::firstOrCreate(
            [
                'cabang_id' => $cabangId,
                'tahun' => $tahun,
                'bulan' => $bulan,
            ],
            [
                'status' => 'open',
            ]
        );
    }

    /**
     * Pra-cek kelayakan tutup buku periode tertentu.
     *
     * @return array{
     *     siap_tutup: bool,
     *     status_periode: string,
     *     neraca_balance: bool,
     *     selisih_neraca: float,
     *     total_pendapatan: float,
     *     total_beban: float,
     *     laba_rugi_bersih: float,
     *     jumlah_akun_ditutup: int,
     *     baris_jurnal_rencana: array,
     *     pesan: string
     * }
     */
    public function praCek(int $cabangId, int $tahun, int $bulan): array
    {
        $periode = $this->getPeriode($cabangId, $tahun, $bulan);

        if ($periode->isClosed()) {
            return [
                'siap_tutup' => false,
                'status_periode' => 'closed',
                'neraca_balance' => true,
                'selisih_neraca' => 0.0,
                'total_pendapatan' => 0.0,
                'total_beban' => 0.0,
                'laba_rugi_bersih' => 0.0,
                'jumlah_akun_ditutup' => 0,
                'baris_jurnal_rencana' => [],
                'pesan' => "Periode {$periode->label_periode} sudah dalam status ditutup.",
            ];
        }

        $akhirBulan = Carbon::create($tahun, $bulan, 1)->endOfMonth()->toDateString();

        // [C-1] Pengendalian urutan periode: tidak boleh menutup bulan N bila
        // periode sebelumnya yang terdaftar masih open, dan tidak boleh tutup
        // mundur bila saldo periode ini sudah tersapu penutup periode berikutnya.
        $pesanUrutan = $this->pesanUrutanPeriode($cabangId, $tahun, $bulan, $periode->label_periode);

        // 1. Verifikasi Keseimbangan Neraca
        $neraca = $this->exportLaporanService->neracaSaldo($cabangId, $akhirBulan);
        $selisih = round((float) ($neraca['selisih'] ?? 0), 2);
        $balance = abs($selisih) < 0.01;

        // 2. [C-1] Hitung SALDO KUMULATIF akun nominal (Pendapatan & Beban) s/d
        // akhir periode — bukan hanya mutasi bulan berjalan. Menjumlah SELURUH
        // baris jurnal disetujui (termasuk ayat penutup periode sebelumnya beserta
        // reversal-nya) menghasilkan saldo buku besar sebenarnya per tanggal tutup:
        // periode yang sudah ditutup bersaldo nol, sehingga hasil = mutasi bulan ini
        // + residual bulan terlewat. Ayat penutup memindahkan saldo ini ke Laba
        // Ditahan sehingga akun nominal benar-benar nol per tanggal tutup (SAK:
        // closing entries menolkan akun nominal, period-end control berurutan).
        // Hanya jurnal disetujui yang ditutup — konsisten dengan neracaSaldo
        // (maker-checker: jurnal pending/ditolak belum diakui di laporan).
        $jurnals = ExportLaporanService::hanyaDisetujui(
            JurnalAkuntansi::where('cabang_id', $cabangId)
                ->whereDate('tanggal', '<=', $akhirBulan)
        )->with('akun')->get();

        $pendapatanRows = $jurnals->where('akun.tipe', 'pendapatan')
            ->groupBy('akun_coa_id')
            ->map(function ($rows) {
                $akun = $rows->first()->akun;
                $saldo = round($rows->sum('kredit') - $rows->sum('debit'), 2);

                return [
                    'akun_id' => $akun->id,
                    'akun_kode' => $akun->kode,
                    'akun_nama' => $akun->nama,
                    'saldo' => $saldo,
                ];
            })
            ->filter(fn ($r) => abs($r['saldo']) > 0.009)
            ->values();

        $bebanRows = $jurnals->where('akun.tipe', 'beban')
            ->groupBy('akun_coa_id')
            ->map(function ($rows) {
                $akun = $rows->first()->akun;
                $saldo = round($rows->sum('debit') - $rows->sum('kredit'), 2);

                return [
                    'akun_id' => $akun->id,
                    'akun_kode' => $akun->kode,
                    'akun_nama' => $akun->nama,
                    'saldo' => $saldo,
                ];
            })
            ->filter(fn ($r) => abs($r['saldo']) > 0.009)
            ->values();

        $totalPendapatan = round($pendapatanRows->sum('saldo'), 2);
        $totalBeban = round($bebanRows->sum('saldo'), 2);
        $labaBersih = round($totalPendapatan - $totalBeban, 2);

        // Buat rencana baris jurnal penutup
        $lines = [];
        // Pendapatan ditutup di Debit
        foreach ($pendapatanRows as $p) {
            if ($p['saldo'] > 0) {
                $lines[] = ['akun_kode' => $p['akun_kode'], 'debit' => $p['saldo'], 'kredit' => 0.0];
            } else {
                $lines[] = ['akun_kode' => $p['akun_kode'], 'debit' => 0.0, 'kredit' => abs($p['saldo'])];
            }
        }
        // Beban ditutup di Kredit
        foreach ($bebanRows as $b) {
            if ($b['saldo'] > 0) {
                $lines[] = ['akun_kode' => $b['akun_kode'], 'debit' => 0.0, 'kredit' => $b['saldo']];
            } else {
                $lines[] = ['akun_kode' => $b['akun_kode'], 'debit' => abs($b['saldo']), 'kredit' => 0.0];
            }
        }
        // Selisih Laba / Rugi ditutup ke Laba Ditahan
        if ($labaBersih > 0) {
            $lines[] = ['akun_kode' => self::AKUN_LABA_DITAHAN, 'debit' => 0.0, 'kredit' => $labaBersih];
        } elseif ($labaBersih < 0) {
            $lines[] = ['akun_kode' => self::AKUN_LABA_DITAHAN, 'debit' => abs($labaBersih), 'kredit' => 0.0];
        }

        $siapTutup = $balance && $pesanUrutan === null;
        $pesan = $pesanUrutan ?? ($siapTutup
            ? 'Neraca seimbang. Periode siap ditutup.'
            : "Neraca tidak seimbang (selisih Rp {$selisih}). Tutup buku tidak diizinkan sebelum diagnosa neraca diselesaikan.");

        return [
            'siap_tutup' => $siapTutup,
            'status_periode' => 'open',
            'neraca_balance' => $balance,
            'selisih_neraca' => $selisih,
            'total_pendapatan' => $totalPendapatan,
            'total_beban' => $totalBeban,
            'laba_rugi_bersih' => $labaBersih,
            'jumlah_akun_ditutup' => count($pendapatanRows) + count($bebanRows),
            'baris_jurnal_rencana' => $lines,
            'pesan' => $pesan,
        ];
    }

    /**
     * [C-1] Pengendalian urutan periode tutup buku (period-end control berurutan).
     *
     * 1. Periode sebelumnya yang terdaftar masih open → tolak: tutup buku wajib
     *    berurutan agar saldo nominal tidak tersapu parsial.
     * 2. Ada periode SETELAHnya yang sudah closed → tolak tutup mundur: saldo
     *    kumulatif periode ini sudah dipindahkan ke Laba Ditahan oleh ayat penutup
     *    periode berikutnya (bulan terlewat yang tersapu), menutup lagi = transfer
     *    ganda. Kecuali periode ini pernah ditutup lalu di-reopen resmi (penutup
     *    di-reversal): saldonya muncul lagi dan wajib ditutup ulang.
     *
     * @return string|null pesan penolakan, atau null bila urutan layak
     */
    private function pesanUrutanPeriode(int $cabangId, int $tahun, int $bulan, string $labelPeriode): ?string
    {
        $sebelumnyaOpen = PeriodeAkuntansi::where('cabang_id', $cabangId)
            ->where('status', 'open')
            ->where(function ($q) use ($tahun, $bulan) {
                $q->where('tahun', '<', $tahun)
                    ->orWhere(function ($q2) use ($tahun, $bulan) {
                        $q2->where('tahun', $tahun)->where('bulan', '<', $bulan);
                    });
            })
            ->orderBy('tahun')
            ->orderBy('bulan')
            ->get();

        if ($sebelumnyaOpen->isNotEmpty()) {
            $label = $sebelumnyaOpen->map(fn (PeriodeAkuntansi $p) => $p->label_periode)->implode(', ');

            return "Tutup buku wajib berurutan: periode {$label} masih terbuka. Tutup periode tersebut terlebih dahulu sebelum menutup {$labelPeriode}.";
        }

        $sesudahClosed = PeriodeAkuntansi::where('cabang_id', $cabangId)
            ->where('status', 'closed')
            ->where(function ($q) use ($tahun, $bulan) {
                $q->where('tahun', '>', $tahun)
                    ->orWhere(function ($q2) use ($tahun, $bulan) {
                        $q2->where('tahun', $tahun)->where('bulan', '>', $bulan);
                    });
            })
            ->exists();

        if ($sesudahClosed) {
            // Pengecualian: periode ini pernah ditutup lalu di-reopen resmi
            // (ayat penutupnya di-reversal via bukaBukuKembali). Saldo nominalnya
            // muncul kembali di buku besar dan wajib ditutup ulang — penutup
            // periode berikutnya TIDAK pernah menyapu saldo ini (saat itu periode
            // ini sudah closed/nol), jadi tutup ulang bukan transfer ganda.
            $awalBulan = Carbon::create($tahun, $bulan, 1)->startOfMonth()->toDateString();
            $akhirBulan = Carbon::create($tahun, $bulan, 1)->endOfMonth()->toDateString();
            $pernahDitutupLaluReopen = JurnalHeader::where('cabang_key', $cabangId)
                ->where('sumber', 'tutup_buku')
                ->where('is_reversed', true)
                ->whereDate('tanggal', '>=', $awalBulan)
                ->whereDate('tanggal', '<=', $akhirBulan)
                ->exists();

            if (! $pernahDitutupLaluReopen) {
                return "Periode setelah {$labelPeriode} sudah ditutup; saldo nominal periode ini sudah dipindahkan ke Laba Ditahan oleh ayat penutup tersebut. Tutup mundur tidak diizinkan karena akan menyebabkan transfer ganda.";
            }
        }

        return null;
    }

    /**
     * Eksekusi proses Tutup Buku periode bulanan.
     *
     * @throws \Exception jika neraca tidak balance atau periode sudah ditutup
     */
    public function tutupBuku(
        int $cabangId,
        int $tahun,
        int $bulan,
        int $userId,
        ?string $catatan = null
    ): PeriodeAkuntansi {
        $periode = $this->getPeriode($cabangId, $tahun, $bulan);

        if ($periode->isClosed()) {
            throw new \Exception("Periode {$periode->label_periode} sudah ditutup sebelumnya.");
        }

        $praCek = $this->praCek($cabangId, $tahun, $bulan);

        if (! $praCek['siap_tutup']) {
            throw new \Exception($praCek['pesan']);
        }

        $lines = $praCek['baris_jurnal_rencana'];
        $akhirBulan = Carbon::create($tahun, $bulan, 1)->endOfMonth()->endOfDay();

        return DB::transaction(function () use ($periode, $lines, $cabangId, $userId, $akhirBulan, $catatan) {
            $noJurnal = null;

            if (! empty($lines)) {
                $noJurnal = $this->jurnalService->generateNoJurnal('tutup_buku', $cabangId);

                $this->jurnalService->post(
                    noJurnal: $noJurnal,
                    tanggal: $akhirBulan,
                    sumber: 'tutup_buku',
                    lines: $lines,
                    deskripsi: "Jurnal Penutup Periode {$periode->label_periode}",
                    cabangId: $cabangId,
                    userId: $userId
                );
            }

            $periode->update([
                'status' => 'closed',
                'closed_at' => now(),
                'closed_by' => $userId,
                'no_jurnal_penutup' => $noJurnal,
                'catatan' => $catatan,
            ]);

            activity()
                ->performedOn($periode)
                ->event('closed')
                ->withProperties([
                    'cabang_id' => $cabangId,
                    'tahun' => $periode->tahun,
                    'bulan' => $periode->bulan,
                    'no_jurnal_penutup' => $noJurnal,
                    'catatan' => $catatan,
                ])
                ->log("Tutup buku periode {$periode->label_periode} berhasil diselesaikan");

            return $periode;
        });
    }

    /**
     * Buka kembali (Re-open) periode akuntansi yang telah ditutup (khusus otorisasi super-admin/manager).
     *
     * @throws \Exception jika periode belum ditutup atau alasan tidak diberikan
     */
    public function bukaBukuKembali(
        int $cabangId,
        int $tahun,
        int $bulan,
        int $userId,
        string $alasan
    ): PeriodeAkuntansi {
        $alasan = trim($alasan);
        if ($alasan === '') {
            throw new \Exception('Alasan pembukaan kembali periode wajib diisi untuk rekam jejak audit (ISO).');
        }

        $periode = $this->getPeriode($cabangId, $tahun, $bulan);

        if (! $periode->isClosed()) {
            throw new \Exception("Periode {$periode->label_periode} belum ditutup.");
        }

        return DB::transaction(function () use ($periode, $cabangId, $userId, $alasan) {
            $noJurnalPenutup = $periode->no_jurnal_penutup;

            $catatanBaru = 'Dibuka kembali pada '.now()->format('d/m/Y H:i').": {$alasan}";
            if ($periode->catatan) {
                $catatanBaru .= " | Catatan lama: {$periode->catatan}";
            }

            // 1. Ubah status periode ke open terlebih dahulu agar guard cekPeriodeTerbuka mengizinkan posting reversal
            $periode->update([
                'status' => 'open',
                'closed_at' => null,
                'closed_by' => null,
                'no_jurnal_penutup' => null,
                'catatan' => $catatanBaru,
            ]);

            // 2. Jika ada jurnal penutup, posting jurnal pembalik (reversal) atomik dengan audit trail.
            // [F-05] Reversal bertanggal AKHIR BULAN yang direopen (bukan now()):
            // laporan periode yang dibuka benar-benar pulih dan bulan berjalan
            // tidak terdistorsi mutasi periode lain (PSAK 1 periodisitas).
            $noJurnalReversal = null;
            if ($noJurnalPenutup) {
                $akhirBulan = Carbon::create($periode->tahun, $periode->bulan, 1)->endOfMonth()->endOfDay();
                $reversal = $this->jurnalService->reversalJurnalPenutup(
                    noJurnalAsal: $noJurnalPenutup,
                    cabangId: $cabangId,
                    userId: $userId,
                    alasan: "Pembukaan kembali (Re-open) periode {$periode->label_periode}: {$alasan}",
                    tanggal: $akhirBulan
                );
                $noJurnalReversal = $reversal['no_jurnal_reversal'];
            }

            activity()
                ->performedOn($periode)
                ->event('reopened')
                ->withProperties([
                    'cabang_id' => $cabangId,
                    'tahun' => $periode->tahun,
                    'bulan' => $periode->bulan,
                    'reversal_no_jurnal' => $noJurnalReversal,
                    'alasan' => $alasan,
                ])
                ->log("Periode {$periode->label_periode} dibuka kembali oleh pengguna #{$userId}. Alasan: {$alasan}");

            return $periode;
        });
    }

    /**
     * Riwayat daftar periode penutupan buku per cabang.
     */
    public function riwayatPeriode(int $cabangId, int $limit = 24)
    {
        return PeriodeAkuntansi::where('cabang_id', $cabangId)
            ->with(['closedByUser'])
            ->orderByDesc('tahun')
            ->orderByDesc('bulan')
            ->limit($limit)
            ->get();
    }
}
