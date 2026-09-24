<?php

namespace App\Modules\Hr\Services;

use App\Modules\Akunting\Services\JurnalService;
use App\Modules\Hr\Models\Karyawan;
use App\Modules\Hr\Models\KaryawanKomponenGaji;
use App\Modules\Hr\Models\KomisiTeknisiRule;
use App\Modules\Hr\Models\PayrollPeriode;
use App\Modules\Hr\Models\PayrollSlip;
use App\Modules\Reseller\Models\Komisi;
use App\Modules\Servis\Models\TiketServis;
use App\Modules\Workflow\Services\ApprovalService;

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

    const THRESHOLD_APPROVAL = 10000000;

    /**
     * Hitung draft payroll untuk periode.
     *
     * @return array{karyawan_diproses: int, total_gaji: float, periode: string}
     */
    public function hitungDraft(string $periode): array
    {
        // Pastikan periode ada dulu
        $this->getOrCreatePeriode($periode);

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
     * Ajukan approval payroll via F1-1 engine.
     * Return apakah perlu approval dan detail request.
     */
    public function ajukanApproval(string $periode, int $userId, string $reason = ''): array
    {
        $result = $this->hitungDraft($periode);
        $totalGaji = $result['total_gaji'];
        $needsApproval = $totalGaji > self::THRESHOLD_APPROVAL;
        $approvalRequestId = null;

        if ($needsApproval) {
            $periodeId = $this->getOrCreatePeriode($periode);
            $approvalRequestId = app(ApprovalService::class)
                ->ajukan('payroll', $periodeId, session('cabang_id'), [
                    'amount' => $totalGaji,
                    'reason' => $reason ?: "Payroll periode {$periode} perlu approval",
                ], $userId)
                ?->id;
        }

        return [
            'needs_approval' => $needsApproval,
            'approval_request_id' => $approvalRequestId,
            'total_gaji' => $totalGaji,
            'threshold' => self::THRESHOLD_APPROVAL,
        ];
    }

    /**
     * Finalisasi setelah F1-1 approval dikonfirmasi.
     */
    public function finalizasiDisetujui(string $periode): array
    {
        $periodeId = $this->getOrCreatePeriode($periode);
        $periodeRecord = PayrollPeriode::find($periodeId);
        if (! $periodeRecord) {
            throw new \Exception('Periode tidak ditemukan');
        }
        if ($periodeRecord->status !== 'draft') {
            throw new \Exception('Periode sudah diproses');
        }

        // Update status periode
        $periodeRecord->update(['status' => 'disetujui']);

        // Post jurnal via approvePayroll
        return $this->approvePayroll($periode);
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

        // Post jurnal: debit Beban Gaji (520-01) + Beban Komisi (520-08) / kredit Hutang Gaji (210-02)
        $lines = [
            ['akun_kode' => '520-01', 'debit' => $totalGaji, 'kredit' => 0],
            ['akun_kode' => '520-08', 'debit' => $totalKomisi, 'kredit' => 0],
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

        // Update status slip
        PayrollSlip::where('payroll_periode_id', $periodeId)
            ->where('status', 'draft')
            ->update(['status' => 'approved', 'jurnal_id' => $noJurnal]);

        return ['no_jurnal' => $noJurnal, 'total_gaji' => $totalGaji, 'total_komisi' => $totalKomisi];
    }

    /**
     * Bayar payroll → update status dan post jurnal pembayaran.
     */
    public function bayarPayroll(string $periode): array
    {
        $periodeId = $this->getOrCreatePeriode($periode);
        PayrollSlip::where('payroll_periode_id', $periodeId)
            ->where('status', 'approved')
            ->update(['status' => 'dibayar']);

        PayrollPeriode::where('periode', $periode)->update(['status' => 'dibayar']);

        // Jurnal pembayaran: debit Utang Gaji (210-02) / kredit Kas (110-01)
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
            'tanggal_mulai' => substr($periode.'-01', 0, 7),
            'tanggal_selesai' => date('Y-m-t', strtotime($periode.'-01')),
            'status' => 'draft',
        ])->id;
    }

    protected function getPeriodeId(string $periode): int
    {
        return PayrollPeriode::where('periode', $periode)->value('id') ?? 0;
    }

    /**
     * Hitung gaji per karyawan.
     */
    protected function hitungKaryawan(Karyawan $karyawan, string $periode): PayrollSlip
    {
        $pokok = $karyawan->gaji_pokok;
        $tunjangan = $this->hitungTunjangan($karyawan->id);
        $potongan = $this->hitungPotongan($karyawan->id);
        $komisiTeknisi = $this->hitungKomisiTeknisi($karyawan->id, $periode);
        $komisiInternal = $this->hitungKomisiInternal($karyawan->id, $periode);
        $komisi = $komisiTeknisi + $komisiInternal;

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
                    'komisi_teknisi' => $komisiTeknisi,
                    'komisi_internal' => $komisiInternal,
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
        $tanggalMulai = substr($periode.'-01', 0, 7);
        $tanggalSelesai = date('Y-m-t', strtotime($periode.'-01'));

        // tiket_servis.teknisi_id → users (bukan karyawan.id) — konsisten dgn KPI §4.2
        $tiket = TiketServis::where('teknisi_id', $karyawan->user_id)
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
     * [F3-8c] Komisi internal (karyawan) dari engine multi-aktor (PRD §4.3):
     * trigger penjualan/lead_won/tiket_servis/target_kpi → komponen komisi payroll.
     * Status pending & disetujui ikut dihitung (payroll bisa diajukan sebelum
     * approval komisi selesai); status ditolak tidak dihitung.
     */
    protected function hitungKomisiInternal(int $karyawanId, string $periode): float
    {
        $mulai = $periode.'-01';
        $selesai = date('Y-m-t', strtotime($mulai));

        return round((float) Komisi::where('aktor_tipe', 'karyawan')
            ->where('aktor_id', $karyawanId)
            ->whereIn('status', ['pending', 'disetujui'])
            ->whereBetween('created_at', [$mulai.' 00:00:00', $selesai.' 23:59:59'])
            ->sum('nominal_komisi'), 2);
    }
}
