<?php

namespace App\Modules\Akunting\Services;

use App\Modules\Akunting\Models\AkunCOA;
use App\Modules\Akunting\Models\JurnalAkuntansi;
use App\Modules\Crm\Models\Pelanggan;
use App\Modules\Servis\Models\TiketServis;
use Maatwebsite\Excel\Facades\Excel;

/**
 * [T-24] Export laporan ke Excel (*.xlsx) via maatwebsite/laravel-excel.
 * Dipanggil dari Queue Job utk laporan besar (RAM 1GB — jangan sinkron).
 */
class ExportLaporanService
{
    public function export(
        string $jenis,
        ?string $dari,
        ?string $sampai,
        ?int $cabangId,
        ?int $akunId = null
    ): string {
        $dari = $dari ?: now()->startOfMonth()->toDateString();
        $sampai = $sampai ?: now()->toDateString();

        $data = match ($jenis) {
            'laba_rugi' => $this->dataLabaRugi($dari, $sampai, $cabangId),
            'neraca' => $this->dataNeraca($cabangId),
            'buku_besar' => $this->dataBukuBesar($akunId, $dari, $sampai),
            'stok' => $this->dataStok($cabangId),
            'pelanggan' => $this->dataPelanggan(),
            'servis' => $this->dataServis($dari, $sampai, $cabangId),
            'piutang' => $this->dataPiutangUtang('piutang'),
            'utang' => $this->dataPiutangUtang('utang'),
            default => throw new \Exception("Jenis laporan '{$jenis}' tidak dikenal"),
        };

        $juggled = array_map(
            fn ($row) => is_array($row) ? array_values($row) : $row,
            $data
        );

        return $this->write($jenis, $juggled);
    }

    private function write(string $jenis, array $rows): string
    {
        $filename = 'export-' . $jenis . '-' . now()->format('Ymd-His') . '.xlsx';
        $path = 'exports/' . $filename;

        Excel::store(new class($rows) implements \Maatwebsite\Excel\Concerns\FromArray {
            public function __construct(private array $rows) {}
            public function array(): array { return $this->rows; }
        }, $path, 'local');

        return $path;
    }

    private function dataLabaRugi(string $dari, string $sampai, ?int $cabangId): array
    {
        $j = $this->jurnals($dari, $sampai, $cabangId);
        $rows = [['LAPORAN LABA RUGI', $dari . ' s.d. ' . $sampai], []];
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
            $rows[] = [$g['nama'] . ' [' . $g['tipe'] . ']', $g['saldo']];
        }

        return $rows;
    }

    private function dataBukuBesar(?int $akunId, string $dari, string $sampai): array
    {
        $akun = $akunId ? AkunCOA::find($akunId) : null;
        $rows = [['BUKU BESAR: ' . ($akun?->kode . ' ' . $akun?->nama ?? 'Semua')], []];
        $rows[] = ['TANGGAL', 'NO JURNAL', 'DESKRIPSI', 'DEBIT', 'KREDIT'];

        $query = JurnalAkuntansi::with('akun')->whereDate('tanggal', '>=', $dari)->whereDate('tanggal', '<=', $sampai);
        if ($akunId) {
            $query->where('akun_coa_id', $akunId);
        }
        foreach ($query->orderBy('tanggal')->get() as $x) {
            $rows[] = [$x->tanggal->format('d/m/Y'), $x->no_jurnal, $x->deskripsi, $x->debit, $x->kredit];
        }

        return $rows;
    }

    private function dataStok(?int $cabangId): array
    {
        $rows = [['LAPORAN STOK'], []];
        $rows[] = ['PRODUK', 'GUDANG', 'QTY', 'MIN'];

        $stoks = \App\Modules\Wms\Models\StokItem::with('produk', 'gudang');
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

    private function dataPiutangUtang(string $tipe): array
    {
        $rows = [[strtoupper($tipe)], []];
        $rows[] = ['NO', 'PELANGGAN/KREDITOR', 'JUMLAH', 'DIBAYAR', 'SISA', 'STATUS'];

        $items = $tipe === 'piutang'
            ? \App\Modules\Akunting\Models\Piutang::with('pelanggan')->get()
            : \App\Modules\Akunting\Models\Utang::get();

        foreach ($items as $i) {
            $nama = $tipe === 'piutang' ? $i->pelanggan?->nama : ($i->kreditor_nama ?? '-');
            $rows[] = [$i->no_piutang ?? $i->no_utang, $nama, $i->jumlah, $i->jumlah_dibayar, $i->sisa, $i->status];
        }

        return $rows;
    }

    private function jurnals(string $dari, string $sampai, ?int $cabangId): \Illuminate\Support\Collection
    {
        $q = JurnalAkuntansi::with('akun')->whereDate('tanggal', '>=', $dari)->whereDate('tanggal', '<=', $sampai);
        if ($cabangId) {
            $q->where('cabang_id', $cabangId);
        }
        return $q->get();
    }

    private function groupAkun(\Illuminate\Support\Collection $j): \Illuminate\Support\Collection
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
}