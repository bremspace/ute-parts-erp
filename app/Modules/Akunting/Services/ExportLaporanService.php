<?php

namespace App\Modules\Akunting\Services;

use App\Modules\Akunting\Models\AkunCOA;
use App\Modules\Akunting\Models\JurnalAkuntansi;
use App\Modules\Akunting\Models\Piutang;
use App\Modules\Akunting\Models\Utang;
use App\Modules\Crm\Models\Pelanggan;
use App\Modules\Pos\Models\Transaksi;
use App\Modules\Reseller\Models\Komisi;
use App\Modules\Servis\Models\TiketServis;
use App\Modules\Wms\Models\StokItem;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Facades\Excel;

/**
 * [T-24] Export laporan ke Excel (*.xlsx) / CSV via maatwebsite + fputcsv.
 * Dipanggil dari Queue Job utk laporan besar (RAM 1GB — jangan sinkron).
 * [F2-5] Jenis lengkap: stok, transaksi, jurnal, piutang, utang, servis, komisi
 * (+ laporan keuangan lama) — semua query cabang-scoped.
 */
class ExportLaporanService
{
    /**
     * [A-1/F-02] Filter laporan: hanya baris jurnal yang entri-nya DISETUJUI
     * (header status_approval='disetujui') atau baris legacy tanpa header.
     * Jurnal pending/ditolak tidak boleh mengubah laporan keuangan
     * (maker-checker). Cocok untuk Eloquent Builder maupun DB query builder
     * karena berupa klausa WHERE EXISTS statis (tanpa binding).
     *
     * @template TBuilder of \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Query\Builder
     *
     * @param  TBuilder  $query
     * @return TBuilder
     */
    public static function hanyaDisetujui($query)
    {
        return $query->whereRaw(
            "NOT EXISTS (SELECT 1 FROM jurnal_header h
                WHERE h.no_jurnal = jurnal_akuntansi.no_jurnal
                AND h.cabang_key = COALESCE(jurnal_akuntansi.cabang_id, 0)
                AND h.status_approval != 'disetujui')"
        );
    }

    /**
     * [A-6] Versioned cache key laporan: semua key laporan per cabang
     * (laba rugi, neraca, arus kas, trial balance, pajak — semua periode)
     * diakhiri versi. Posting/approval cukup menaikkan versi untuk
     * menginvalidasi SELURUH cache laporan cabang itu + konsolidasi,
     * tanpa perlu enumerasi key per periode. Entri lama kedaluwarsa via TTL.
     */
    public static function keyVersiLaporan(?int $cabangId): string
    {
        return 'laporan-versi-'.($cabangId ?? 'konsolidasi');
    }

    public static function versiCacheLaporan(?int $cabangId): int
    {
        return max(1, (int) Cache::get(self::keyVersiLaporan($cabangId), 1));
    }

    public static function bumpVersiCacheLaporan(?int $cabangId): void
    {
        Cache::forever(self::keyVersiLaporan($cabangId), self::versiCacheLaporan($cabangId) + 1);
    }

    /** Bungkus key cache laporan lama dengan versi aktif cabang/konsolidasi. */
    public static function keyCacheLaporan(string $baseKey, ?int $cabangId): string
    {
        return $baseKey.'-v'.self::versiCacheLaporan($cabangId);
    }

    /** @var array<string,true> dedupe invalidasi per no_jurnal (proses panjang di-cap 500) */
    private static array $invalidasiTerjadwal = [];

    /**
     * [A-6] Dipanggil saat baris jurnal ter-post (model created) — invalidasi
     * semua cache laporan cabang terkait + konsolidasi SETELAH commit,
     * supaya pembaca tidak mengisi cache baru dengan data pra-commit.
     */
    public static function invalidasiCachePosting(?int $cabangId, string $noJurnal): void
    {
        // Cache di-flush (mis. setUp uji dengan DB segar) → nomor jurnal bisa
        // berulang; dedupe lama tidak boleh menahan invalidasi.
        if (self::$invalidasiTerjadwal !== [] && ! Cache::has(self::keyVersiLaporan(null))) {
            self::$invalidasiTerjadwal = [];
        }

        $key = ($cabangId ?? 0).'|'.$noJurnal;
        if (isset(self::$invalidasiTerjadwal[$key])) {
            return;
        }
        if (count(self::$invalidasiTerjadwal) >= 500) {
            self::$invalidasiTerjadwal = [];
        }
        self::$invalidasiTerjadwal[$key] = true;

        // Bump langsung: versi baru berlaku seketika (juga di dalam transaksi —
        // RefreshDatabase menguji dalam transaksi yang tidak pernah commit).
        self::bumpVersiCacheLaporan($cabangId);
        self::bumpVersiCacheLaporan(null);

        // Bump ulang SETELAH commit: buang cache yang sempat terisi data
        // pra-commit di jendela antara insert dan commit transaksi posting.
        DB::afterCommit(function () use ($cabangId): void {
            self::bumpVersiCacheLaporan($cabangId);
            self::bumpVersiCacheLaporan(null);
        });
    }

    /**
     * [P2-10] Filename export selalu di-prefix {userId}_ (ownership download ACC-11b).
     * $userId eksplisit > auth() (konteks request); fallback 0 bila tak dikenal —
     * file pemilik tak dikenal tidak bisa diunduh siapa pun & terhapus prune.
     */
    public function export(
        string $jenis,
        ?string $dari,
        ?string $sampai,
        ?int $cabangId,
        ?int $akunId = null,
        string $format = 'xlsx',
        ?int $userId = null
    ): string {
        $dari = $dari ?: now()->startOfMonth()->toDateString();
        $sampai = $sampai ?: now()->toDateString();

        $data = match ($jenis) {
            'laba_rugi' => $this->dataLabaRugi($dari, $sampai, $cabangId),
            'neraca' => $this->dataNeraca($cabangId, $sampai),
            'buku_besar' => $this->dataBukuBesar($akunId, $dari, $sampai, $cabangId),
            'arus_kas' => $this->dataArusKas($dari, $sampai, $cabangId),
            'stok' => $this->dataStok($cabangId),
            'pelanggan' => $this->dataPelanggan(),
            'servis' => $this->dataServis($dari, $sampai, $cabangId),
            'piutang' => $this->dataPiutangUtang('piutang', $cabangId),
            'utang' => $this->dataPiutangUtang('utang', $cabangId),
            'pajak' => $this->recapsPpnPeriode($cabangId, $dari, $sampai), // [F1-2] e-Faktur-ready
            'jurnal' => $this->dataJurnal($dari, $sampai, $cabangId),       // [F2-5]
            'transaksi' => $this->dataTransaksi($dari, $sampai, $cabangId), // [F2-5]
            'komisi' => $this->dataKomisi($cabangId),                       // [F2-5]
            'neraca_saldo' => $this->dataNeracaSaldo($dari, $sampai, $cabangId), // [ISO/Audit] Trial Balance 4 Kolom
            default => throw new \Exception("Jenis laporan '{$jenis}' tidak dikenal"),
        };

        $juggled = array_map(
            fn ($row) => is_array($row) ? array_values($row) : $row,
            $data
        );

        return $this->write($userId ?? auth()->id() ?? 0, $jenis, $juggled, $format);
    }

    private function write(int $userId, string $jenis, array $rows, string $format = 'xlsx'): string
    {
        $ext = $format === 'csv' ? 'csv' : 'xlsx';
        // [P2-10] Skema {userId}_{jenis}_{Ymd-His}_{uniq}.{ext} — suffix uniqid(+entropi)
        // beresolusi mikrodetik: dua export dtk yg sama tdk pernah menimpa.
        $uniq = str_replace('.', '', uniqid('', true));
        $filename = $userId.'_'.$jenis.'_'.now()->format('Ymd-His').'_'.$uniq.'.'.$ext;
        $path = 'exports/'.$filename;

        if ($ext === 'csv') {
            // [F2-5] CSV tanpa PhpSpreadsheet — RAM-friendly utk 1GB server
            $out = fopen('php://memory', 'r+');
            foreach ($rows as $row) {
                $values = array_map(
                    fn ($v) => is_array($v) || is_object($v) ? json_encode($v) : $v,
                    array_values((array) $row)
                );
                fputcsv($out, $values);
            }
            rewind($out);
            $csv = stream_get_contents($out);
            fclose($out);
            Storage::disk('local')->put($path, $csv);

            return $path;
        }

        Excel::store(new class($rows) implements FromArray
        {
            public function __construct(private array $rows) {}

            public function array(): array
            {
                return $this->rows;
            }
        }, $path, 'local');

        return $path;
    }

    /**
     * [P2-10b] Hapus berkas ekspor lama (>7 hari) di disk `local` — dipanggil
     * opportunistic saat dispatch export. Selalu dibungkus try/catch: prune tidak
     * boleh pernah menggagalkan ekspor. Berkas muda (<7 hari) tidak disentuh.
     */
    public static function pruneOldExports(int $days = 7): void
    {
        try {
            $disk = Storage::disk('local');
            $cutoff = now()->subDays($days)->getTimestamp();

            foreach ($disk->files('exports') as $file) {
                try {
                    $full = $disk->path($file);
                    if (is_file($full) && (@filemtime($full) ?: 0) < $cutoff) {
                        $disk->delete($file);
                    }
                } catch (\Throwable) {
                    // lanjutkan ke berkas berikutnya
                }
            }
        } catch (\Throwable) {
            // prune bersifat opportunistic — tidak boleh menggagalkan ekspor
        }
    }

    private function dataLabaRugi(string $dari, string $sampai, ?int $cabangId): array
    {
        // [A-2] ayat penutup (tutup_buku) bukan transaksi periode — kecualikan.
        $j = $this->jurnals($dari, $sampai, $cabangId, true);
        $rows = [['LAPORAN LABA RUGI', $dari.' s.d. '.$sampai], []];

        $pendapatanRows = [];
        $bebanRows = [];
        $totalPendapatan = 0.0;
        $totalBeban = 0.0;

        foreach ($this->groupAkun($j) as $g) {
            if ($g['tipe'] === 'pendapatan') {
                $pendapatanRows[] = [$g['nama'], $g['saldo']];
                $totalPendapatan += (float) $g['saldo'];
            } elseif ($g['tipe'] === 'beban') {
                $bebanRows[] = [$g['nama'], $g['saldo']];
                $totalBeban += (float) $g['saldo'];
            }
        }

        $rows[] = ['=== PENDAPATAN ===', ''];
        $rows[] = ['AKUN', 'JUMLAH (Rp)'];
        foreach ($pendapatanRows as $r) {
            $rows[] = $r;
        }
        $rows[] = ['TOTAL PENDAPATAN', round($totalPendapatan, 2)];
        $rows[] = [];

        $rows[] = ['=== BEBAN OPERASIONAL ===', ''];
        $rows[] = ['AKUN', 'JUMLAH (Rp)'];
        foreach ($bebanRows as $r) {
            $rows[] = $r;
        }
        $rows[] = ['TOTAL BEBAN', round($totalBeban, 2)];
        $rows[] = [];

        $labaBersih = round($totalPendapatan - $totalBeban, 2);
        $rows[] = ['LABA BERSIH', $labaBersih];
        $rows[] = [];

        $debit = (float) $j->sum('debit');
        $kredit = (float) $j->sum('kredit');
        $rows[] = ['TOTAL DEBIT', round($debit, 2)];
        $rows[] = ['TOTAL KREDIT', round($kredit, 2)];
        $rows[] = ['LABA BERSIH (k- d)', round($kredit - $debit, 2)];

        return $rows;
    }

    /**
     * [B-10e / P1-7] Neraca = SALDO KUMULATIF s/d $sampai (bukan perubahan periode).
     *
     * DASAR LAPORAN (WAJIB sama dgn API ACC-06 & dashboard):
     *  - Neraca adalah foto posisi pada satu tanggal, jadi menjumlah SELURUH jurnal
     *    dari awal pembukuan s/d tanggal tersebut. Versi lama hanya menghitung
     *    mulai awal bulan berjalan sehingga saldo historis (dan akun yang tak
     *    pernah bergerak di bulan ini) hilang, sehingga neraca tidak balance.
     *  - Laporan PERUBAHAN periode (laba rugi, arus kas, jurnal umum) tetap
     *    memakai rentang $dari..$sampai; dua basis ini jangan tertukar.
     *  - Laba periode berjalan (pendapatan - beban kumulatif) dilaporkan di sisi
     *    ekuitas sebagai "LABA PERIODE BERJALAN" karena akun pendapatan & beban
     *    belum ditutup ke Laba Ditahan (310-02). Tanpa baris ini neraca tidak
     *    akan balance meski seluruh jurnal double-entry benar.
     *
     * @return array<string,mixed> akun per tipe + total + selisih + penanda balance
     */
    public function neracaSaldo(?int $cabangId, string $sampai): array
    {
        $akunSaldo = $this->saldoKumulatifPerAkun($cabangId, $sampai)
            ->map(function ($row) {
                // [B-15b] angka & pembulatan identik dgn versi lama (jumlahkan
                // seluruh baris jurnal per akun, lalu bulatkan 2 desimal) —
                // hanya sumber datanya yang berubah (agregat SQL, bukan get()).
                $debit = (float) $row->total_debit;
                $kredit = (float) $row->total_kredit;

                $saldo = (string) $row->saldo_normal === 'debit'
                    ? $debit - $kredit
                    : $kredit - $debit;

                return [
                    'kode' => (string) $row->kode,
                    'nama' => (string) $row->nama,
                    'tipe' => (string) $row->tipe,
                    'kelompok' => (string) $row->kelompok,
                    'saldo' => round($saldo, 2),
                ];
            })
            ->sortBy('kode')
            ->values();

        $aset = $akunSaldo->where('tipe', 'aset')->values();
        $kewajiban = $akunSaldo->where('tipe', 'kewajiban')->values();
        $ekuitas = $akunSaldo->where('tipe', 'ekuitas')->values();
        $pendapatan = $akunSaldo->where('tipe', 'pendapatan')->values();
        $beban = $akunSaldo->where('tipe', 'beban')->values();

        $labaPeriodeBerjalan = round($pendapatan->sum('saldo') - $beban->sum('saldo'), 2);
        $totalAset = round($aset->sum('saldo'), 2);
        $totalKewajiban = round($kewajiban->sum('saldo'), 2);
        $totalEkuitas = round($ekuitas->sum('saldo'), 2);
        $totalEkuitasLaba = round($totalEkuitas + $labaPeriodeBerjalan, 2);
        $selisih = round($totalAset - ($totalKewajiban + $totalEkuitasLaba), 2);

        return [
            'sampai_tanggal' => $sampai,
            'basis' => 'saldo_kumulatif',
            'aset' => $aset->all(),
            'kewajiban' => $kewajiban->all(),
            'ekuitas' => $ekuitas->all(),
            'pendapatan' => $pendapatan->all(),
            'beban' => $beban->all(),
            'laba_periode_berjalan' => $labaPeriodeBerjalan,
            'total_aset' => $totalAset,
            'total_kewajiban' => $totalKewajiban,
            'total_ekuitas' => $totalEkuitas,
            'total_ekuitas_bersama_laba' => $totalEkuitasLaba,
            'selisih' => $selisih,
            'balance' => abs($selisih) < 0.01,
        ];
    }

    /**
     * [B-10e / P1-7] Baris export neraca. Judul menyebut "saldo kumulatif"
     * supaya tidak disalahbaca sebagai laporan perubahan periode.
     */
    private function dataNeraca(?int $cabangId, string $sampai): array
    {
        $neraca = $this->neracaSaldo($cabangId, $sampai);

        $rows = [
            ['NERACA (SALDO KUMULATIF s/d '.$sampai.')'],
            ['Seluruh jurnal sejak awal pembukuan s/d tanggal di atas - bukan perubahan periode.'],
            [],
            ['KELOMPOK', 'KODE', 'SALDO (Rp)'],
            ['ASET'],
        ];

        foreach ($neraca['aset'] as $a) {
            $rows[] = [$a['nama'], $a['kode'], $a['saldo']];
        }
        $rows[] = ['TOTAL ASET', '', $neraca['total_aset']];
        $rows[] = [];
        $rows[] = ['KEWAJIBAN'];
        foreach ($neraca['kewajiban'] as $k) {
            $rows[] = [$k['nama'], $k['kode'], $k['saldo']];
        }
        $rows[] = ['TOTAL KEWAJIBAN', '', $neraca['total_kewajiban']];
        $rows[] = [];
        $rows[] = ['EKUITAS'];
        foreach ($neraca['ekuitas'] as $e) {
            $rows[] = [$e['nama'], $e['kode'], $e['saldo']];
        }
        $rows[] = ['LABA PERIODE BERJALAN (kumulatif s/d '.$sampai.')', '', $neraca['laba_periode_berjalan']];
        $rows[] = ['TOTAL EKUITAS + LABA BERJALAN', '', $neraca['total_ekuitas_bersama_laba']];
        $rows[] = [];
        $rows[] = ['SELISIH (ASET - KEWAJIBAN - EKUITAS)', '', $neraca['selisih']];
        $rows[] = ['STATUS', '', $neraca['balance'] ? 'SEIMBANG' : 'TIDAK SEIMBANG'];

        return $rows;
    }

    private function dataBukuBesar(?int $akunId, string $dari, string $sampai, ?int $cabangId = null): array
    {
        $akun = $akunId ? AkunCOA::find($akunId) : null;
        $rows = [['BUKU BESAR: '.($akun?->kode.' '.$akun?->nama ?? 'Semua')], []];
        $rows[] = ['TANGGAL', 'NO JURNAL', 'DESKRIPSI', 'DEBIT', 'KREDIT'];

        $query = self::hanyaDisetujui(
            JurnalAkuntansi::with('akun')->whereDate('tanggal', '>=', $dari)->whereDate('tanggal', '<=', $sampai)
        );
        if ($akunId) {
            $query->where('akun_coa_id', $akunId);
        }
        // [F2-5] cabang scoping — jangan bocorkan jurnal cabang lain
        if ($cabangId) {
            $query->where('cabang_id', $cabangId);
        }
        foreach ($query->orderBy('tanggal')->get() as $x) {
            $rows[] = [$x->tanggal->format('d/m/Y'), $x->no_jurnal, $x->deskripsi, $x->debit, $x->kredit];
        }

        return $rows;
    }

    /**
     * [A-1/A-2] Agregasi jurnal per akun COA langsung di SQL — sumber tunggal
     * utk laba rugi & arus kas (UI dashboard, API, export). Hanya jurnal
     * DISETUJUI dan TANPA ayat penutup (tutup_buku): penutup adalah transfer
     * saldo, bukan transaksi periode (P&L tidak boleh ±0 setelah tutup buku).
     *
     * Satu query memuat DUA basis (hemat query utk server RAM 1GB):
     *  - total_debit/kredit = mutasi PERIODE ($dari..$sampai, conditional SUM);
     *  - kumulatif_debit/kredit = mutasi sejak awal pembukuan s/d $sampai
     *    (dipakai arus kas menghitung saldo awal kas = kumulatif - periode).
     * Akun yang hanya bergerak SEBELUM periode ikut terbawa (total periode 0)
     * demi saldo awal kas; pemakai laporan periode menyaring total 0.
     * `MIN(id) conditional` menjaga urutan tampil akun sesuai jurnal pertama
     * dalam periode.
     *
     * @return Collection<int,array{akun_coa_id:int,kode:string,nama:string,tipe:string,kelompok:string,saldo_normal:string,total_debit:float,total_kredit:float,kumulatif_debit:float,kumulatif_kredit:float}>
     */
    public function agregatJurnalPeriode(?int $cabangId, string $dari, string $sampai): Collection
    {
        $query = DB::table('jurnal_akuntansi')
            ->join('akun_coa', 'akun_coa.id', '=', 'jurnal_akuntansi.akun_coa_id');

        if ($cabangId !== null) {
            $query->where('jurnal_akuntansi.cabang_id', $cabangId);
        }

        $query = self::hanyaDisetujui($query);

        return $query->where('jurnal_akuntansi.sumber', '!=', 'tutup_buku')
            ->whereDate('jurnal_akuntansi.tanggal', '<=', $sampai)
            ->select('jurnal_akuntansi.akun_coa_id')
            ->selectRaw('akun_coa.kode, akun_coa.nama, akun_coa.tipe, akun_coa.kelompok, akun_coa.saldo_normal')
            ->selectRaw(
                'SUM(CASE WHEN date(jurnal_akuntansi.tanggal) >= ? THEN jurnal_akuntansi.debit ELSE 0 END) as total_debit, '
                .'SUM(CASE WHEN date(jurnal_akuntansi.tanggal) >= ? THEN jurnal_akuntansi.kredit ELSE 0 END) as total_kredit',
                [$dari, $dari]
            )
            ->selectRaw('SUM(jurnal_akuntansi.debit) as kumulatif_debit, SUM(jurnal_akuntansi.kredit) as kumulatif_kredit')
            ->selectRaw('MIN(CASE WHEN date(jurnal_akuntansi.tanggal) >= ? THEN jurnal_akuntansi.id END) as urut_pertama', [$dari])
            ->groupBy(
                'jurnal_akuntansi.akun_coa_id',
                'akun_coa.kode',
                'akun_coa.nama',
                'akun_coa.tipe',
                'akun_coa.kelompok',
                'akun_coa.saldo_normal',
            )
            ->orderBy('urut_pertama')
            ->get()
            ->map(fn (\stdClass $a): array => [
                'akun_coa_id' => (int) $a->akun_coa_id,
                'kode' => (string) $a->kode,
                'nama' => (string) $a->nama,
                'tipe' => (string) $a->tipe,
                'kelompok' => (string) $a->kelompok,
                'saldo_normal' => (string) $a->saldo_normal,
                'total_debit' => (float) $a->total_debit,
                'total_kredit' => (float) $a->total_kredit,
                'kumulatif_debit' => (float) $a->kumulatif_debit,
                'kumulatif_kredit' => (float) $a->kumulatif_kredit,
            ]);
    }

    /**
     * [A-4/A-5] Arus kas metode TIDAK LANGSUNG — sumber tunggal UI dashboard,
     * API ACC-04b, dan export (sebelumnya export memakai metode langsung per
     * hari sehingga angka berbeda untuk laporan yang sama).
     *
     * Perbaikan atas formula lama:
     *  - add-back beban non-kas (depresiasi/amortisasi, kelompok beban_depresiasi);
     *  - delta piutang berbasis kelompok `piutang` (120-01..04, termasuk channel
     *    marketplace), bukan hanya kode 120-01;
     *  - delta utang = SEMUA akun kewajiban (utang usaha/gaji/komisi/lain, pajak),
     *    bukan hanya 210-01+210-03;
     *  - akun di luar bucket dikenal (PPN masukan, RAK, kelompok kustom) masuk
     *    `perubahan_akun_lain` agar neto rekonsiliasi ke kas; akumulasi
     *    depresiasi (130-02) dikecualikan karena pasangannya sudah di-add-back;
     *  - blok rekonsiliasi: saldo awal kas/bank + perubahan aktual = saldo akhir,
     *    dibandingkan dengan kenaikan_kas_neto (kontrol PSAK 2).
     *
     * @return array<string,mixed>
     */
    public function arusKasTidakLangsung(?int $cabangId, string $dari, string $sampai): array
    {
        $akun = $this->agregatJurnalPeriode($cabangId, $dari, $sampai);

        $delta = fn (callable $filter): float => round(
            $akun->filter($filter)->sum(fn (array $a) => $a['total_debit'] - $a['total_kredit']),
            2
        );

        $labaBersih = round(
            $akun->where('tipe', 'pendapatan')->sum(fn (array $a) => $a['total_kredit'] - $a['total_debit'])
            - $akun->where('tipe', 'beban')->sum(fn (array $a) => $a['total_debit'] - $a['total_kredit']),
            2
        );

        $addBackNonKas = $delta(fn (array $a) => $a['tipe'] === 'beban'
            && in_array($a['kelompok'], ['beban_depresiasi', 'beban_amortisasi'], true));
        $piutangDelta = $delta(fn (array $a) => $a['kelompok'] === 'piutang');
        $persediaanDelta = $delta(fn (array $a) => $a['kelompok'] === 'persediaan');
        $utangDelta = round($akun->where('tipe', 'kewajiban')->sum(fn (array $a) => $a['total_kredit'] - $a['total_debit']), 2);
        $arusInvestasi = round(-$akun->whereIn('kelompok', ['aset_tetap', 'peralatan', 'perlengkapan'])
            ->sum(fn (array $a) => $a['total_debit'] - $a['total_kredit']), 2);
        $arusPendanaan = round($akun->whereIn('kelompok', ['modal', 'laba_ditahan'])
            ->sum(fn (array $a) => $a['total_kredit'] - $a['total_debit']), 2);

        $lainDelta = $delta(function (array $a): bool {
            if (in_array($a['kelompok'], [
                'kas', 'bank', 'piutang', 'persediaan', 'aset_tetap', 'peralatan',
                'perlengkapan', 'modal', 'laba_ditahan', 'akumulasi_depresiasi',
            ], true)) {
                return false;
            }

            return ! in_array($a['tipe'], ['pendapatan', 'beban', 'kewajiban'], true);
        });

        $arusKasOperasi = round($labaBersih + $addBackNonKas - $piutangDelta - $persediaanDelta + $utangDelta - $lainDelta, 2);
        $kenaikanNeto = round($arusKasOperasi + $arusInvestasi + $arusPendanaan, 2);

        // Kontrol kas riil: mutasi akun kelompok kas/bank + saldo awal
        // (kumulatif s/d akhir periode dikurangi mutasi periode — satu query).
        $kasRows = $akun->filter(fn (array $a) => in_array($a['kelompok'], ['kas', 'bank'], true));
        $perubahanKas = round($kasRows->sum(fn (array $a) => $a['total_debit'] - $a['total_kredit']), 2);
        $saldoAwalKas = round($kasRows->sum(fn (array $a) => ($a['kumulatif_debit'] - $a['kumulatif_kredit']) - ($a['total_debit'] - $a['total_kredit'])), 2);
        $saldoAkhirKas = round($saldoAwalKas + $perubahanKas, 2);
        $selisih = round($kenaikanNeto - $perubahanKas, 2);

        return [
            'metode' => 'tidak_langsung',
            'laba_bersih' => $labaBersih,
            'penyesuaian' => [
                'beban_depresiasi' => $addBackNonKas,
                'kenaikan_piutang' => round(-$piutangDelta, 2),
                'kenaikan_persediaan' => round(-$persediaanDelta, 2),
                'kenaikan_utang' => $utangDelta,
                'perubahan_akun_lain' => round(-$lainDelta, 2),
            ],
            'arus_kas_operasi' => $arusKasOperasi,
            'arus_kas_investasi' => $arusInvestasi,
            'arus_kas_pendanaan' => $arusPendanaan,
            'kenaikan_kas_neto' => $kenaikanNeto,
            'rekonsiliasi' => [
                'saldo_awal_kas' => $saldoAwalKas,
                'perubahan_kas_aktual' => $perubahanKas,
                'saldo_akhir_kas' => $saldoAkhirKas,
                'selisih' => $selisih,
                'cocok' => abs($selisih) < 0.01,
            ],
        ];
    }

    /**
     * [A-5] Export arus kas = metode tidak langsung, angka SAMA dengan UI/API
     * (satu sumber: arusKasTidakLangsung), plus blok rekonsiliasi ke kas/bank.
     */
    private function dataArusKas(string $dari, string $sampai, ?int $cabangId): array
    {
        $ak = $this->arusKasTidakLangsung($cabangId, $dari, $sampai);

        $rows = [['LAPORAN ARUS KAS (METODE TIDAK LANGSUNG)', $dari.' s.d. '.$sampai], []];
        $rows[] = ['KOMPONEN', 'JUMLAH (Rp)'];
        $rows[] = ['Laba bersih', $ak['laba_bersih']];
        $rows[] = ['Penyesuaian: beban depresiasi & amortisasi (non-kas)', $ak['penyesuaian']['beban_depresiasi']];
        $rows[] = ['Penyesuaian: kenaikan piutang', $ak['penyesuaian']['kenaikan_piutang']];
        $rows[] = ['Penyesuaian: kenaikan persediaan', $ak['penyesuaian']['kenaikan_persediaan']];
        $rows[] = ['Penyesuaian: kenaikan utang', $ak['penyesuaian']['kenaikan_utang']];
        $rows[] = ['Penyesuaian: perubahan akun lain', $ak['penyesuaian']['perubahan_akun_lain']];
        $rows[] = ['ARUS KAS OPERASI', $ak['arus_kas_operasi']];
        $rows[] = ['ARUS KAS INVESTASI', $ak['arus_kas_investasi']];
        $rows[] = ['ARUS KAS PENDANAAN', $ak['arus_kas_pendanaan']];
        $rows[] = ['KENAIKAN KAS NETO', $ak['kenaikan_kas_neto']];
        $rows[] = [];
        $rows[] = ['REKONSILIASI KE KAS/BANK', ''];
        $rows[] = ['Saldo awal kas/bank', $ak['rekonsiliasi']['saldo_awal_kas']];
        $rows[] = ['Perubahan kas/bank aktual (GL)', $ak['rekonsiliasi']['perubahan_kas_aktual']];
        $rows[] = ['Saldo akhir kas/bank', $ak['rekonsiliasi']['saldo_akhir_kas']];
        $rows[] = ['Selisih (neto - aktual)', $ak['rekonsiliasi']['selisih']];
        $rows[] = ['STATUS', $ak['rekonsiliasi']['cocok'] ? 'COCOK' : 'SELISIH'];

        return $rows;
    }

    private function dataStok(?int $cabangId): array
    {
        $rows = [['LAPORAN STOK'], []];
        $rows[] = ['BARCODE', 'SKU', 'PRODUK', 'GUDANG', 'QTY', 'MIN'];

        $stoks = StokItem::with(['produk.skuVariants', 'gudang']);
        if ($cabangId) {
            $stoks->whereHas('gudang', fn ($q) => $q->where('cabang_id', $cabangId));
        }
        foreach ($stoks->get() as $s) {
            $rows[] = [
                $s->produk?->barcode ?? '',
                $s->produk?->skuVariants?->first()?->sku ?? '',
                $s->produk?->nama,
                $s->gudang?->nama,
                $s->jumlah,
                $s->jumlah_minimum,
            ];
        }

        return $rows;
    }

    private function dataPelanggan(): array
    {
        $rows = [['LAPORAN PELANGGAN'], []];
        $rows[] = ['NAMA', 'TELEPON', 'TIER', 'BELANJA 12 BLN', 'POIN'];

        foreach (Pelanggan::with('tierMembership')->get() as $p) {
            $rows[] = [$p->nama, $p->telepon, $p->tierMembership?->nama, $p->total_belanja_12bulan, $p->poin_loyalty];
        }

        return $rows;
    }

    private function dataServis(string $dari, string $sampai, ?int $cabangId): array
    {
        $rows = [['LAPORAN SERVIS'], []];
        $rows[] = ['NO TIKET', 'JENIS HP', 'STATUS', 'STATUS BAYAR', 'METODE', 'ESTIMASI', 'REALISASI', 'NO JURNAL BAYAR', 'TGL TERIMA', 'TGL SELESAI', 'TGL BAYAR'];

        $q = TiketServis::with(['items', 'spareparts'])
            ->whereDate('created_at', '>=', $dari)
            ->whereDate('created_at', '<=', $sampai);

        if ($cabangId) {
            $q->where('cabang_id', $cabangId);
        }

        foreach ($q->get() as $t) {
            $activeItems = $t->items->whereNull('dibatalkan_at');
            $activeSpareparts = $t->spareparts->whereNull('dibatalkan_at');

            if ($activeItems->isNotEmpty() || $activeSpareparts->isNotEmpty()) {
                $totalItem = $activeItems->sum(fn ($i) => (float) $i->harga * (int) $i->qty);
                $totalSparepart = $activeSpareparts->sum(fn ($sp) => (float) $sp->harga_satuan * (int) $sp->jumlah);
                $realisasi = $totalItem + $totalSparepart;
            } else {
                $realisasi = $t->estimasi_biaya;
            }

            $rows[] = [
                $t->no_tiket,
                $t->jenis_hp,
                $t->status,
                $t->status_pembayaran ?? 'belum_bayar',
                $t->metode_pembayaran ?? '-',
                $t->estimasi_biaya,
                $realisasi,
                $t->no_jurnal_bayar ?? '-',
                $t->tanggal_terima?->format('d/m/Y'),
                $t->tanggal_selesai?->format('d/m/Y'),
                $t->tanggal_bayar?->format('d/m/Y'),
            ];
        }

        return $rows;
    }

    private function dataPiutangUtang(string $tipe, ?int $cabangId = null): array
    {
        $rows = [[strtoupper($tipe)], []];
        $rows[] = ['NO', 'PELANGGAN/KREDITOR', 'JUMLAH', 'DIBAYAR', 'SISA', 'STATUS'];

        $query = $tipe === 'piutang' ? Piutang::with('pelanggan') : Utang::query();
        // [F2-5] cabang scoping ketat — cabang_id NULL (baris legacy) tidak diekspor
        // ke cabang mana pun agar data cabang lain tidak pernah bocor.
        if ($cabangId) {
            $query->where('cabang_id', $cabangId);
        }

        foreach ($query->get() as $i) {
            $nama = $tipe === 'piutang' ? $i->pelanggan?->nama : ($i->kreditor_nama ?? '-');
            $rows[] = [$i->no_piutang ?? $i->no_utang, $nama, $i->jumlah, $i->jumlah_dibayar, $i->sisa, $i->status];
        }

        return $rows;
    }

    /**
     * [F2-5] Jurnal umum periode — baris per akun, cabang-scoped via jurnals().
     */
    private function dataJurnal(string $dari, string $sampai, ?int $cabangId): array
    {
        $rows = [['JURNAL UMUM', $dari.' s.d. '.$sampai], []];
        $rows[] = ['TANGGAL', 'NO JURNAL', 'AKUN', 'DESKRIPSI', 'DEBIT', 'KREDIT'];

        foreach ($this->jurnals($dari, $sampai, $cabangId) as $j) {
            $akun = trim(($j->akun?->kode ?? '-').' '.($j->akun?->nama ?? ''));
            $rows[] = [$j->tanggal->format('d/m/Y'), $j->no_jurnal, $akun, $j->deskripsi, (float) $j->debit, (float) $j->kredit];
        }

        return $rows;
    }

    /**
     * [F2-5] Rekap transaksi POS/marketplace periode — cabang-scoped.
     */
    private function dataTransaksi(string $dari, string $sampai, ?int $cabangId): array
    {
        $rows = [['LAPORAN TRANSAKSI', $dari.' s.d. '.$sampai], []];
        $rows[] = ['TANGGAL', 'NO TRANSAKSI', 'PELANGGAN', 'KASIR', 'METODE BAYAR', 'SUBTOTAL', 'DISKON', 'TOTAL AKHIR', 'STATUS'];

        $query = Transaksi::with(['pelanggan', 'kasir'])
            ->whereDate('created_at', '>=', $dari)
            ->whereDate('created_at', '<=', $sampai);
        if ($cabangId) {
            $query->where('cabang_id', $cabangId);
        }

        foreach ($query->orderBy('created_at')->get() as $t) {
            $rows[] = [
                $t->created_at->format('d/m/Y H:i'),
                $t->no_transaksi,
                $t->pelanggan?->nama ?? 'Umum',
                $t->kasir?->name ?? '-',
                strtoupper($t->metode_bayar ?? '-'),
                (float) $t->subtotal,
                (float) $t->diskon_nominal,
                (float) $t->total_akhir,
                $t->status,
            ];
        }

        return $rows;
    }

    /**
     * [F2-5] Komisi reseller — komisi tidak punya kolom cabang,
     * scope lewat relasi transaksi (cabang_id milik transaksi sumber).
     */
    private function dataKomisi(?int $cabangId): array
    {
        $rows = [['LAPORAN KOMISI RESELLER'], []];
        $rows[] = ['NO KOMISI', 'RESELLER', 'NO TRANSAKSI', 'JUMLAH TRANSAKSI', 'NOMINAL KOMISI', 'STATUS', 'TANGGAL'];

        $query = Komisi::with(['pelanggan', 'transaksi'])->latest();
        if ($cabangId) {
            $query->whereHas('transaksi', fn ($q) => $q->where('cabang_id', $cabangId));
        }

        foreach ($query->get() as $k) {
            $rows[] = [
                $k->no_komisi,
                $k->pelanggan?->nama ?? '-',
                $k->transaksi?->no_transaksi ?? '-',
                (float) $k->jumlah_transaksi,
                (float) $k->nominal_komisi,
                $k->status,
                $k->created_at?->format('d/m/Y'),
            ];
        }

        return $rows;
    }

    private function jurnals(string $dari, string $sampai, ?int $cabangId, bool $tanpaPenutup = false): Collection
    {
        $q = $this->queryJurnal($cabangId)
            ->whereDate('tanggal', '>=', $dari)
            ->whereDate('tanggal', '<=', $sampai);
        if ($tanpaPenutup) {
            $q->where('sumber', '!=', 'tutup_buku');
        }

        return $q->get();
    }

    /**
     * [B-10e / P1-7] SALDO KUMULATIF per akun s/d $sampai (tanpa batas bawah) —
     * dipakai neraca. Laporan perubahan periode tetap pakai jurnals($dari, ...).
     *
     * [B-15b] SEBELUMNYA ini menarik SELURUH baris jurnal sejak awal pembukuan
     * (`with('akun')->get()`, produksi 150.000-300.000 baris/tahun) hanya
     * untuk menjumlah per akun. Sekarang `GROUP BY akun` di SQL: satu query,
     * hasil = jumlah akun COA yang bergerak. Join `akun_coa` membaca
     * kode/nama/tipe/kelompok/saldo_normal tanpa query per akun. Baris jurnal
     * yang akunnya sudah tidak ada tetap TIDAK ikut (versi lama membuangnya
     * lewat `filter()`; sekarang inner join tidak memunculkan baris itu).
     *
     * @return Collection<int,object>
     */
    private function saldoKumulatifPerAkun(?int $cabangId, string $sampai): Collection
    {
        $query = DB::table('jurnal_akuntansi')
            ->join('akun_coa', 'akun_coa.id', '=', 'jurnal_akuntansi.akun_coa_id')
            ->whereDate('jurnal_akuntansi.tanggal', '<=', $sampai)
            ->select('jurnal_akuntansi.akun_coa_id')
            ->selectRaw('akun_coa.kode, akun_coa.nama, akun_coa.tipe, akun_coa.kelompok, akun_coa.saldo_normal')
            ->selectRaw('SUM(jurnal_akuntansi.debit) as total_debit, SUM(jurnal_akuntansi.kredit) as total_kredit')
            ->groupBy(
                'jurnal_akuntansi.akun_coa_id',
                'akun_coa.kode',
                'akun_coa.nama',
                'akun_coa.tipe',
                'akun_coa.kelompok',
                'akun_coa.saldo_normal',
            );

        // [F2-5] cabang scoping — jangan bocorkan jurnal cabang lain
        if ($cabangId) {
            $query->where('jurnal_akuntansi.cabang_id', $cabangId);
        }

        // [A-1/F-02] hanya jurnal disetujui (atau baris legacy tanpa header).
        return self::hanyaDisetujui($query)->get();
    }

    private function queryJurnal(?int $cabangId): Builder
    {
        // [A-1/F-02] hanya jurnal disetujui (atau baris legacy tanpa header).
        $q = self::hanyaDisetujui(JurnalAkuntansi::with('akun'));

        // [F2-5] cabang scoping — jangan bocorkan jurnal cabang lain
        if ($cabangId) {
            $q->where('cabang_id', $cabangId);
        }

        return $q;
    }

    private function groupAkun(Collection $j): Collection
    {
        return $j->groupBy('akun_coa_id')->map(function ($rows) {
            $akun = $rows->first()->akun ?? null;
            $saldo = $akun
                ? ($akun->saldo_normal === 'debit'
                    ? $rows->sum('debit') - $rows->sum('kredit')
                    : $rows->sum('kredit') - $rows->sum('debit'))
                : 0;

            return [
                'nama' => $akun?->nama ?? '?',
                'tipe' => $akun?->tipe ?? '-',
                'saldo' => round($saldo, 2),
            ];
        })->values();
    }

    /**
     * Rekap data pajak per periode (e-Faktur-ready) untuk UI & export. [F1-2]
     * Keluaran: transaksi dgn ppn_nominal > 0 (fallback pajak_nominal) — kolom DPP + PPN.
     * Masukan: baris jurnal akun 110-03 (PPN Masukan) — cabang-scoped.
     * Periode: inclusive $dari -> $sampai (YYYY-MM-DD).
     */
    public function recapsPpnPeriode(?int $cabangId, string $dari, string $sampai): array
    {
        $rows = [['REKAP PPN (E-FAKTUR)', $dari.' s.d. '.$sampai], []];
        $rows[] = ['=== PPN KELUARAN ==='];
        $rows[] = ['TANGGAL', 'NO TRANSAKSI', 'NO JURNAL', 'DPP (Rp)', 'PPN PERCENT', 'PPN NOMINAL (Rp)'];

        $query = Transaksi::whereDate('created_at', '>=', $dari)
            ->whereDate('created_at', '<=', $sampai);

        if ($cabangId) {
            $query->where('cabang_id', $cabangId);
        }

        $totalDpp = 0;
        $totalPpn = 0;
        $ppnCount = 0;

        foreach ($query->get() as $t) {
            // [F1-2] pakai kolom ppn_nominal/dpp bila terisi; fallback kolom lama pajak_nominal
            $ppn = (float) ($t->ppn_nominal ?? 0) > 0 ? (float) $t->ppn_nominal : (float) $t->pajak_nominal;
            if ($ppn > 0) {
                $dpp = (float) ($t->dpp ?? 0) > 0 ? (float) $t->dpp : max(0, (float) $t->subtotal - (float) $t->diskon_nominal);
                $percent = $dpp > 0 ? round(($ppn / $dpp) * 100, 2) : 0;
                $rows[] = [
                    $t->created_at->format('d/m/Y'),
                    $t->no_transaksi,
                    $t->no_jurnal ?? '-',
                    $dpp,
                    $percent,
                    $ppn,
                ];
                $totalDpp += $dpp;
                $totalPpn += $ppn;
                $ppnCount++;
            }
        }

        $rows[] = ['TOTAL KELUARAN', '', '', $totalDpp, '', $totalPpn];
        $rows[] = [];
        $rows[] = ['=== PPN MASUKAN ==='];
        $rows[] = ['AKUN', 'KETERANGAN', '', 'DEBIT (Rp)', '', 'KREDIT (Rp)'];

        $masukanAkun = AkunCOA::where('kode', '110-03')->first();
        $totalMasukan = 0.0;
        if ($masukanAkun) {
            $jMasukan = self::hanyaDisetujui(
                JurnalAkuntansi::with('akun')
                    ->where('akun_coa_id', $masukanAkun->id)
                    ->whereDate('tanggal', '>=', $dari)
                    ->whereDate('tanggal', '<=', $sampai)
            );
            if ($cabangId) {
                $jMasukan->where('cabang_id', $cabangId);
            }
            foreach ($jMasukan->get() as $j) {
                $rows[] = ['110-03', $j->deskripsi, '', (float) $j->debit, '', (float) $j->kredit];
                $totalMasukan += (float) $j->debit - (float) $j->kredit;
            }
        }
        $rows[] = ['TOTAL MASUKAN', '', '', '', '', round($totalMasukan, 2)];
        $rows[] = [];
        $rows[] = ['PPN TERUTANG (Keluaran - Masukan)', '', '', '', '', round($totalPpn - $totalMasukan, 2)];
        $rows[] = [];
        $rows[] = ['KETERANGAN', 'Jumlah transaksi yang terkena PPN: '.$ppnCount];

        return $rows;
    }

    /**
     * [ISO / Audit] Neraca Saldo 4 Kolom (Trial Balance):
     * 1. Saldo Awal (kumulatif s/d $dari - 1 hari)
     * 2. Mutasi Debit (periode $dari s/d $sampai)
     * 3. Mutasi Kredit (periode $dari s/d $sampai)
     * 4. Saldo Akhir (kumulatif s/d $sampai)
     *
     * @return array{periode: array{dari: string, sampai: string}, akun: array, total: array{saldo_awal_debit: float, saldo_awal_kredit: float, mutasi_debit: float, mutasi_kredit: float, saldo_akhir_debit: float, saldo_akhir_kredit: float}, is_balanced: bool}
     */
    public function neracaSaldo4Kolom(?int $cabangId, string $dari, string $sampai): array
    {
        // [A-3] Semua akun (termasuk NONAKTIF) — hapusCoa menonaktifkan akun
        // ber-riwayat; saldo akun itu wajib tetap tampil agar trial balance
        // balance dan konsisten dengan neraca. Akun idle (semua komponen 0)
        // tetap dilewati di loop bawah.
        $akuns = AkunCOA::orderBy('kode')->get();

        // 1. Saldo Awal: akumulasi baris jurnal sebelum tanggal $dari (< $dari)
        $qAwal = $this->queryJurnal($cabangId)->whereDate('tanggal', '<', $dari);
        $saldoAwalMap = $qAwal->select(
            'akun_coa_id',
            DB::raw('SUM(debit) as total_debit'),
            DB::raw('SUM(kredit) as total_kredit')
        )->groupBy('akun_coa_id')->get()->keyBy('akun_coa_id');

        // 2. Mutasi Periode: antara $dari s/d $sampai
        $qMutasi = $this->queryJurnal($cabangId)
            ->whereDate('tanggal', '>=', $dari)
            ->whereDate('tanggal', '<=', $sampai);
        $mutasiMap = $qMutasi->select(
            'akun_coa_id',
            DB::raw('SUM(debit) as total_debit'),
            DB::raw('SUM(kredit) as total_kredit')
        )->groupBy('akun_coa_id')->get()->keyBy('akun_coa_id');

        $rows = [];
        $totalAwalDebit = 0.0;
        $totalAwalKredit = 0.0;
        $totalMutasiDebit = 0.0;
        $totalMutasiKredit = 0.0;
        $totalAkhirDebit = 0.0;
        $totalAkhirKredit = 0.0;

        foreach ($akuns as $akun) {
            $awalRow = $saldoAwalMap->get($akun->id);
            $mutasiRow = $mutasiMap->get($akun->id);

            $awalD = (float) ($awalRow?->total_debit ?? 0);
            $awalK = (float) ($awalRow?->total_kredit ?? 0);
            $mutD = (float) ($mutasiRow?->total_debit ?? 0);
            $mutK = (float) ($mutasiRow?->total_kredit ?? 0);

            // Hitung net saldo awal
            $netAwal = $akun->saldo_normal === 'debit' ? ($awalD - $awalK) : ($awalK - $awalD);
            if ($akun->saldo_normal === 'debit') {
                $saldoAwalDebit = $netAwal >= 0 ? $netAwal : 0;
                $saldoAwalKredit = $netAwal < 0 ? abs($netAwal) : 0;
            } else {
                $saldoAwalDebit = $netAwal < 0 ? abs($netAwal) : 0;
                $saldoAwalKredit = $netAwal >= 0 ? $netAwal : 0;
            }

            // Hitung net saldo akhir
            $akhirD = $awalD + $mutD;
            $akhirK = $awalK + $mutK;
            $netAkhir = $akun->saldo_normal === 'debit' ? ($akhirD - $akhirK) : ($akhirK - $akhirD);
            if ($akun->saldo_normal === 'debit') {
                $saldoAkhirDebit = $netAkhir >= 0 ? $netAkhir : 0;
                $saldoAkhirKredit = $netAkhir < 0 ? abs($netAkhir) : 0;
            } else {
                $saldoAkhirDebit = $netAkhir < 0 ? abs($netAkhir) : 0;
                $saldoAkhirKredit = $netAkhir >= 0 ? $netAkhir : 0;
            }

            // Jangan tampilkan jika seluruh komponen 0 (idle account)
            if ($netAwal == 0 && $mutD == 0 && $mutK == 0 && $netAkhir == 0) {
                continue;
            }

            $totalAwalDebit += $saldoAwalDebit;
            $totalAwalKredit += $saldoAwalKredit;
            $totalMutasiDebit += $mutD;
            $totalMutasiKredit += $mutK;
            $totalAkhirDebit += $saldoAkhirDebit;
            $totalAkhirKredit += $saldoAkhirKredit;

            $rows[] = [
                'akun_id' => $akun->id,
                'kode' => $akun->kode,
                'nama' => $akun->nama,
                'tipe' => $akun->tipe,
                'saldo_normal' => $akun->saldo_normal,
                'saldo_awal' => round($netAwal, 2),
                'saldo_awal_debit' => round($saldoAwalDebit, 2),
                'saldo_awal_kredit' => round($saldoAwalKredit, 2),
                'mutasi_debit' => round($mutD, 2),
                'mutasi_kredit' => round($mutK, 2),
                'saldo_akhir' => round($netAkhir, 2),
                'saldo_akhir_debit' => round($saldoAkhirDebit, 2),
                'saldo_akhir_kredit' => round($saldoAkhirKredit, 2),
            ];
        }

        return [
            'periode' => ['dari' => $dari, 'sampai' => $sampai],
            'akun' => $rows,
            'total' => [
                'saldo_awal_debit' => round($totalAwalDebit, 2),
                'saldo_awal_kredit' => round($totalAwalKredit, 2),
                'mutasi_debit' => round($totalMutasiDebit, 2),
                'mutasi_kredit' => round($totalMutasiKredit, 2),
                'saldo_akhir_debit' => round($totalAkhirDebit, 2),
                'saldo_akhir_kredit' => round($totalAkhirKredit, 2),
            ],
            'is_balanced' => abs($totalAwalDebit - $totalAwalKredit) < 0.01
                && abs($totalMutasiDebit - $totalMutasiKredit) < 0.01
                && abs($totalAkhirDebit - $totalAkhirKredit) < 0.01,
        ];
    }

    /**
     * Data tabular Neraca Saldo 4 Kolom untuk ekspor file Excel/CSV.
     */
    private function dataNeracaSaldo(string $dari, string $sampai, ?int $cabangId): array
    {
        $ns = $this->neracaSaldo4Kolom($cabangId, $dari, $sampai);

        $rows = [
            ['NERACA SALDO (TRIAL BALANCE) 4 KOLOM', $dari.' s.d. '.$sampai],
            [],
            [
                'KODE AKUN', 'NAMA AKUN', 'TIPE', 'SALDO NORMAL',
                'SALDO AWAL (DEBIT)', 'SALDO AWAL (KREDIT)',
                'MUTASI DEBIT', 'MUTASI KREDIT',
                'SALDO AKHIR (DEBIT)', 'SALDO AKHIR (KREDIT)',
            ],
        ];

        foreach ($ns['akun'] as $a) {
            $rows[] = [
                $a['kode'],
                $a['nama'],
                strtoupper($a['tipe']),
                strtoupper($a['saldo_normal']),
                $a['saldo_awal_debit'] ?: 0,
                $a['saldo_awal_kredit'] ?: 0,
                $a['mutasi_debit'] ?: 0,
                $a['mutasi_kredit'] ?: 0,
                $a['saldo_akhir_debit'] ?: 0,
                $a['saldo_akhir_kredit'] ?: 0,
            ];
        }

        $rows[] = [];
        $rows[] = [
            'TOTAL', '', '', '',
            $ns['total']['saldo_awal_debit'],
            $ns['total']['saldo_awal_kredit'],
            $ns['total']['mutasi_debit'],
            $ns['total']['mutasi_kredit'],
            $ns['total']['saldo_akhir_debit'],
            $ns['total']['saldo_akhir_kredit'],
        ];

        return $rows;
    }
}
