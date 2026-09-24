<?php

namespace App\Modules\Hr\Services;

use App\Modules\Crm\Models\Lead;
use App\Modules\Hr\Models\Karyawan;
use App\Modules\Hr\Models\KpiHasil;
use App\Modules\Hr\Models\KpiMetric;
use App\Modules\Servis\Models\TiketServis;
use Illuminate\Support\Facades\DB;

/**
 * [F3-8b] KPI Service — hitung KPI bulanan dari data real (bukan input manual).
 *
 * Rumus WAJIB whitelist kode (BUKAN eval bebas). Whitelist:
 * - tiket_selesai        (teknisi)  : jumlah tiket servis selesai dalam periode
 * - transaksi_kasir      (kasir)    : jumlah transaksi POS selesai per kasir
 * - selisih_kas          (kasir)    : total selisih kas absolut sesi tutup (makin kecil makin baik)
 * - lead_won             (marketing): jumlah lead stage won dalam periode
 * - penjualan_lead_won   (marketing): total nilai_estimasi lead won
 */
class KpiService
{
    /**
     * Whitelist rumus → jabatan karyawan yang berlaku.
     */
    public const RUMUS_WHITELIST = [
        'tiket_selesai' => ['teknisi'],
        'transaksi_kasir' => ['kasir'],
        'selisih_kas' => ['kasir'],
        'lead_won' => ['marketing'],
        'penjualan_lead_won' => ['marketing'],
    ];

    /**
     * Hitung KPI semua metric aktif untuk semua karyawan aktif dalam periode.
     * Metric dengan rumus non-whitelist di-skip (safety).
     *
     * @return array{karyawan: int, hasil: int, metric_diproses: int}
     */
    public function hitung(string $periode): array
    {
        $metrics = KpiMetric::where('is_aktif', true)->get();
        $karyawans = Karyawan::where('status_aktif', true)->get();

        $karyawanDiproses = 0;
        $hasilDiproses = 0;
        $metricDiproses = 0;

        foreach ($metrics as $metric) {
            $jabatans = self::RUMUS_WHITELIST[$metric->rumus] ?? null;

            // Safety: rumus bukan whitelist → skip, jangan pernah eval bebas
            if ($jabatans === null) {
                continue;
            }
            $metricDiproses++;

            foreach ($karyawans as $karyawan) {
                if (! in_array($karyawan->jabatan, $jabatans, true)) {
                    continue;
                }

                $this->hitungKaryawan($karyawan, $metric, $periode);
                $karyawanDiproses++;
                $hasilDiproses++;
            }
        }

        return [
            'karyawan' => $karyawanDiproses,
            'hasil' => $hasilDiproses,
            'metric_diproses' => $metricDiproses,
        ];
    }

    /**
     * Hitung & simpan KPI satu karyawan untuk satu metric (idempotent).
     */
    public function hitungKaryawan(Karyawan $karyawan, KpiMetric $metric, string $periode): KpiHasil
    {
        $nilai = $this->nilaiAktual($metric->rumus, $karyawan, $periode);
        $persen = $this->persenCapaian($metric->rumus, $nilai, (float) $metric->target);

        return KpiHasil::updateOrCreate(
            ['karyawan_id' => $karyawan->id, 'kpi_metric_id' => $metric->id, 'periode' => $periode],
            ['nilai_aktual' => $nilai, 'persen_capaian' => $persen]
        );
    }

    /**
     * Nilai aktual metric dari data real.
     */
    public function nilaiAktual(string $rumus, Karyawan $karyawan, string $periode): float
    {
        ['mulai' => $mulai, 'selesai' => $selesai] = $this->rentangPeriode($periode);
        $userId = $karyawan->user_id;

        return match ($rumus) {
            'tiket_selesai' => TiketServis::where('teknisi_id', $userId)
                ->where('status', 'selesai')
                ->whereBetween('tanggal_selesai', [$mulai, $selesai])
                ->count(),
            'transaksi_kasir' => DB::table('transaksi')
                ->where('kasir_id', $userId)
                ->where('status', 'selesai')
                ->whereBetween('created_at', [$mulai.' 00:00:00', $selesai.' 23:59:59'])
                ->count(),
            'selisih_kas' => DB::table('kas_sesi')
                ->where('user_id', $userId)
                ->where('status', 'tutup')
                ->whereNotNull('selisih')
                ->whereBetween('ditutup_at', [$mulai.' 00:00:00', $selesai.' 23:59:59'])
                ->get()
                ->sum(fn ($s) => abs((float) $s->selisih)),
            'lead_won' => Lead::where('assigned_to', $userId)
                ->where('stage', 'won')
                ->whereBetween('won_at', [$mulai.' 00:00:00', $selesai.' 23:59:59'])
                ->count(),
            'penjualan_lead_won' => Lead::where('assigned_to', $userId)
                ->where('stage', 'won')
                ->whereBetween('won_at', [$mulai.' 00:00:00', $selesai.' 23:59:59'])
                ->sum('nilai_estimasi'),
            default => 0,
        };
    }

    /**
     * Persen capaian terhadap target.
     * - selisih_kas: dikonversi (makin kecil selisih makin baik, cap 100).
     * - target 0 = tanpa target: capaian 100 bila ada nilai, 0 bila nol.
     */
    public function persenCapaian(string $rumus, float $nilai, float $target): float
    {
        if ($rumus === 'selisih_kas') {
            if ($target <= 0) {
                return $nilai == 0 ? 100 : 0;
            }

            return round(max(0, 100 - ($nilai / $target * 100)), 2);
        }

        if ($target <= 0) {
            return $nilai > 0 ? 100 : 0;
        }

        return round($nilai / $target * 100, 2);
    }

    /**
     * @return array{mulai: string, selesai: string}
     */
    protected function rentangPeriode(string $periode): array
    {
        return [
            'mulai' => $periode.'-01',
            'selesai' => date('Y-m-t', strtotime($periode.'-01')),
        ];
    }
}
