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
use Illuminate\Support\Collection;
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
            'neraca' => $this->dataNeraca($cabangId),
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

    private function dataNeraca(?int $cabangId): array
    {
        $j = $this->jurnals(now()->startOfMonth()->toDateString(), now()->toDateString(), $cabangId);
        $rows = [['NERACA'], []];
        $rows[] = ['AKUN', 'SALDO (Rp)'];

        foreach ($this->groupAkun($j) as $g) {
            $rows[] = [$g['nama'].' ['.$g['tipe'].']', $g['saldo']];
        }

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
        $q = JurnalAkuntansi::with('akun')->whereDate('tanggal', '>=', $dari)->whereDate('tanggal', '<=', $sampai);
        if ($cabangId) {
            $q->where('cabang_id', $cabangId);
        }

        return $q->get();
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
