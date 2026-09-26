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
        $j = $this->jurnals($dari, $sampai, $cabangId);
        $rows = [['LAPORAN LABA RUGI', $dari.' s.d. '.$sampai], []];
        $rows[] = ['AKUN', 'JUMLAH (Rp)'];

        foreach ($this->groupAkun($j) as $g) {
            $rows[] = [$g['nama'], $g['saldo']];
        }
        $rows[] = [];
        $debit = $j->sum('debit');
        $kredit = $j->sum('kredit');
        $rows[] = ['TOTAL DEBIT', $debit];
        $rows[] = ['TOTAL KREDIT', $kredit];
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
            'aset' => $aset,
            'kewajiban' => $kewajiban,
            'ekuitas' => $ekuitas,
            'pendapatan' => $pendapatan,
            'beban' => $beban,
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

        $query = JurnalAkuntansi::with('akun')->whereDate('tanggal', '>=', $dari)->whereDate('tanggal', '<=', $sampai);
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
     * Arus kas sederhana (metode langsung): per hari, kas masuk (debit 110-01)
     * vs kas keluar (kredit 110-01) dari jurnal pada periode. [T-24]
     */
    private function dataArusKas(string $dari, string $sampai, ?int $cabangId): array
    {
        $rows = [['LAPORAN ARUS KAS', $dari.' s.d. '.$sampai], []];
        $rows[] = ['TANGGAL', 'MASUK (Rp)', 'KELUAR (Rp)', 'SELISIH (Rp)'];

        $perHari = $this->jurnals($dari, $sampai, $cabangId)
            ->filter(fn ($j) => $j->akun && $j->akun->kode === '110-01' && $j->akun->tipe === 'aset')
            ->groupBy(fn ($j) => $j->tanggal->toDateString());

        $totalMasuk = 0.0;
        $totalKeluar = 0.0;
        foreach ($perHari as $tgl => $baris) {
            $masuk = round((float) $baris->sum('debit'), 2);
            $keluar = round((float) $baris->sum('kredit'), 2);
            $totalMasuk += $masuk;
            $totalKeluar += $keluar;
            $rows[] = [$tgl, $masuk, $keluar, round($masuk - $keluar, 2)];
        }

        $rows[] = [];
        $rows[] = ['TOTAL', $totalMasuk, $totalKeluar, round($totalMasuk - $totalKeluar, 2)];

        return $rows;
    }

    private function dataStok(?int $cabangId): array
    {
        $rows = [['LAPORAN STOK'], []];
        $rows[] = ['PRODUK', 'GUDANG', 'QTY', 'MIN'];

        $stoks = StokItem::with('produk', 'gudang');
        if ($cabangId) {
            $stoks->whereHas('gudang', fn ($q) => $q->where('cabang_id', $cabangId));
        }
        foreach ($stoks->get() as $s) {
            $rows[] = [$s->produk?->nama, $s->gudang?->nama, $s->jumlah, $s->jumlah_minimum];
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
        $rows[] = ['NO TIKET', 'JENIS HP', 'STATUS', 'ESTIMASI', 'TANGGAL TERIMA'];

        $q = TiketServis::whereDate('created_at', '>=', $dari)->whereDate('created_at', '<=', $sampai);
        if ($cabangId) {
            $q->where('cabang_id', $cabangId);
        }
        foreach ($q->get() as $t) {
            $rows[] = [$t->no_tiket, $t->jenis_hp, $t->status, $t->estimasi_biaya, $t->tanggal_terima?->format('d/m/Y')];
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

    private function jurnals(string $dari, string $sampai, ?int $cabangId): Collection
    {
        return $this->queryJurnal($cabangId)
            ->whereDate('tanggal', '>=', $dari)
            ->whereDate('tanggal', '<=', $sampai)
            ->get();
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

        return $query->get();
    }

    private function queryJurnal(?int $cabangId): Builder
    {
        $q = JurnalAkuntansi::with('akun');

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
            $jMasukan = JurnalAkuntansi::with('akun')
                ->where('akun_coa_id', $masukanAkun->id)
                ->whereDate('tanggal', '>=', $dari)
                ->whereDate('tanggal', '<=', $sampai);
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
}
