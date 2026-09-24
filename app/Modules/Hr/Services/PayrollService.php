<?php

namespace App\Modules\Hr\Services;

use App\Modules\Hr\Models\Karyawan;
use App\Modules\Hr\Models\PayrollPeriode;
use App\Modules\Hr\Models\PayrollSlip;
use App\Modules\Hr\Models\PayrollKomisiDetail;
use App\Modules\Hr\Models\KomisiTeknisiRule;
use App\Modules\Hr\Models\KaryawanKomponenGaji;
use App\Modules\Servis\Models\TiketServis;
use App\Modules\Akunting\Services\JurnalService;
use Illuminate\Support\Facades\DB;

/**
 * [F3-8] Payroll Service — hitung gaji, komisi teknisi, posting jurnal.
 *
 * Formula payroll: gaji_pokok + total_tunjangan - total_potongan + total_komisi
 * Komisi teknisi: dari tiket servis selesai dalam periode, berdasarkan rule.
 */
class PayrollService
{
    public function __construct(
        protected JurnalService $jurnalService
    ) {}

    /**
     * Hitung draft payroll untuk periode.
     *
     * @return array{karyawan_diproses: int, total_gaji: float, periode: string}
     */
    public function hitungDraft(string $periode): array
    {
        $karyawans = Karyawan::where('status_aktif', true)->get();
        $totalGaji = 0;
        $diproses = 0;

        foreach ($karyawans as $karyawan) {
            $this->hitungKaryawan($karyawan, $periode);
            $diproses++;
        }

        // Hitung total
        $slips = PayrollSlip::where('payroll_periode_id', $this->getPeriodeId($periode))->get();
        $totalGaji = $slips->sum('total_gaji');

        return ['karyawan_diproses' => $diproses, 'total_gaji' => $totalGaji, 'periode' => $periode];
    }

    /**
     * Hitung gaji per karyawan.
     */
    protected function hitungKaryawan(Karyawan $karyawan, string $periode): PayrollSlip
    {
        $pokok = $karyawan->gaji_pokok;
        $tunjangan = $this->hitungTunjangan($karyawan->id);
        $potongan = $this->hitungPotongan($karyawan->id);
        $komisi = $this->hitungKomisiTeknisi($karyawan->id, $periode);

        $totalGaji = $pokok + $tunjangan - $potongan + $komisi;

        $periodeId = $this->getOrCreatePeriode($periode);

        return PayrollSlip::updateOrCreate(
            ['payroll_periode_id' => $periodeId, 'karyawan_id' => $karyawan->id],
            [
                'gaji_pokok' => $pokok,
                'total_tunjangan' => $tunjangan,
                'total_potongan' => $potongan,
                'total_komisi' => $komisi,
                'total_gaji' => $totalGaji,
                'rincian' => json_encode([
                    'pokok' => $pokok,
                    'tunjangan' => $tunjangan,
                    'potongan' => $potongan,
                    'komisi' => $komisi,
                    'periode' => $periode,
                ]),
            ]
        );
    }

    /**
     * Hitung total tunjangan komponen.
     */
    protected function hitungTunjangan(int $karyawanId): float
    {
        return KaryawanKomponenGaji::where('karyawan_id', $karyawanId)
            ->where('tipe', 'tunjangan')
            ->where('is_aktif', true)
            ->sum('nominal_bulanan');
    }

    /**
     * Hitung total potongan komponen.
     */
    protected function hitungPotongan(int $karyawanId): float
    {
        return KaryawanKomponenGaji::where('karyawan_id', $karyawanId)
            ->where('tipe', 'potongan')
            ->where('is_aktif', true)
            ->sum('nominal_bulanan');
    }

    /**
     * Hitung komisi teknisi dari tiket servis selesai dalam periode.
     */
    protected function hitungKomisiTeknisi(int $karyawanId, string $periode): float
    {
        $karyawan = Karyawan::find($karyawanId);
        if (! $karyawan || $karyawan->jabatan !== 'teknisi') {
            return 0;
        }

        $rules = KomisiTeknisiRule::where('is_aktif', true)
            ->where(function ($q) {
                $q->whereNull('cabang_id')
                    ->orWhere('cabang_id', session('cabang_id'));
            })
            ->get();

        $totalKomisi = 0;
        $tanggalMulai = substr($periode . '-01', 0, 7);
        $tanggalSelesai = date('Y-m-t', strtotime($periode . '-01'));

        $tiket = TiketServis::where('teknisi_id', $karyawanId)
            ->where('status', 'selesai')
            ->whereBetween('tanggal_selesai', [$tanggalMulai, $tanggalSelesai])
            ->get();

        foreach ($tiket as $t) {
            foreach ($rules as $rule) {
                if ($rule->jenis === 'per_tiket') {
                    $totalKomisi += $rule->nominal;
                } elseif ($rule->jenis === 'persen_nilai_servis') {
                    $totalKomisi += ($t->estimasi_biaya ?? 0) * ($rule->persen / 100);
                }
            }
        }

        return round($totalKomisi, 2);
    }

    /**
     * Approve payroll periode → post jurnal.
     */
    public function approvePayroll(string $periode): array
    {
        $periodeId = $this->getOrCreatePeriode($periode);
        $slips = PayrollSlip::where('payroll_periode_id', $periodeId)->get();

        $totalGaji = $slips->sum('total_gaji');
        $totalKomisi = $slips->sum('total_komisi');

        // Post jurnal: debit Beban Gaji (520-01) + Beban Komisi (520-02) / kredit Hutang Gaji (210-02)
        $lines = [
            ['akun_kode' => '520-01', 'debit' => $totalGaji, 'kredit' => 0],
            ['akun_kode' => '520-02', 'debit' => $totalKomisi, 'kredit' => 0],
            ['akun_kode' => '210-02', 'debit' => 0, 'kredit' => $totalGaji + $totalKomisi],
        ];

        $noJurnal = sprintf('JRL-PR-%s', $periode);
        $this->jurnalService->post(
            $noJurnal,
            now(),
            'payroll',
            $lines,
            "Payroll periode {$periode}",
            session('cabang_id'),
            auth()->id(),
            PayrollPeriode::class,
            $periodeId
        );

        return ['no_jurnal' => $noJurnal, 'total_gaji' => $totalGaji, 'total_komisi' => $totalKomisi];
    }

    /**
     * Bayar payroll → jurnal pembayaran.
     */
    public function bayarPayroll(string $periode): array
    {
        $periodeId = $this->getOrCreatePeriode($periode);
        PayrollSlip::where('payroll_periode_id', $periodeId)
            ->where('status', 'approved')
            ->update(['status' => 'dibayar']);

        // Jurnal pembayaran: debit Hutang Gaji (210-02) / kredit Kas (110-01)
        $slips = PayrollSlip::where('payroll_periode_id', $periodeId)->get();
        $total = $slips->sum('total_gaji');

        $lines = [
            ['akun_kode' => '210-02', 'debit' => $total, 'kredit' => 0],
            ['akun_kode' => '110-01', 'debit' => 0, 'kredit' => $total],
        ];

        $noJurnal = sprintf('JRL-PR-BAYAR-%s', $periode);
        $this->jurnalService->post(
            $noJurnal,
            now(),
            'payroll-bayar',
            $lines,
            "Pembayaran payroll periode {$periode}",
            session('cabang_id'),
            auth()->id(),
            PayrollPeriode::class,
            $periodeId
        );

        return ['no_jurnal' => $noJurnal, 'total' => $total];
    }

    /**
     * Get atau create payroll periode.
     */
    protected function getOrCreatePeriode(string $periode): int
    {
        $record = PayrollPeriode::where('periode', $periode)->first();
        if ($record) {
            return $record->id;
        }

        return PayrollPeriode::create([
            'periode' => $periode,
            'tanggal_mulai' => substr($periode . '-01', 0, 7),
            'tanggal_selesai' => date('Y-m-t', strtotime($periode . '-01')),
            'status' => 'draft',
        ])->id;
    }

    protected function getPeriodeId(string $periode): int
    {
        return PayrollPeriode::where('periode', $periode)->value('id');
    }
}
