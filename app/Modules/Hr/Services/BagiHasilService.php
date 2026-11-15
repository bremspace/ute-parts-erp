<?php

namespace App\Modules\Hr\Services;

use App\Modules\Akunting\Models\AkunCOA;
use App\Modules\Akunting\Services\JurnalService;
use App\Modules\Hr\Models\BagiHasil;
use App\Modules\Hr\Models\BagiHasilPeriode;
use App\Modules\Hr\Models\BagiHasilRule;
use App\Modules\Hr\Models\Karyawan;
use App\Modules\Servis\Models\TiketServis;
use App\Modules\Workflow\Models\ApprovalRequest;
use App\Modules\Workflow\Services\ApprovalService;
use Illuminate\Support\Facades\DB;

/**
 * [BH] Bagi Hasil Jasa Teknisi — engine perhitungan, payout, & jurnal.
 *
 * Bisnis: teknisi non-gaji (`karyawan.metode_penghasilan = bagi_hasil`) mendapat
 * porsi persentase dari nilai JASA tiket servis yang selesai. Part/sparepart
 * tidak masuk dasar hitung.
 *
 * Alur:
 *  1. Tiket servis `selesai` → accru 1 baris `bagi_hasil` (idempoten per tiket).
 *  2. Payout periode bulanan: hitung → proses (jurnal accrual) → bayar (jurnal kas).
 *
 * Akunting (sama pola dengan PayrollService):
 *  - Accrual: debit 520-11 Beban Bagi Hasil Jasa / kredit 210-06 Utang Bagi Hasil Teknisi.
 *  - Bayar : debit 210-06 / kredit akun kas.
 *  - Fail-closed: jurnal diposting dulu dalam satu transaksi, baru status final.
 */
class BagiHasilService
{
    /** Total payout di atas nominal ini wajib lewat F1-1 Approval Engine (seperti payroll). */
    public const THRESHOLD_APPROVAL = 10000000;

    public const AKUN_BEBAN = '520-11'; // Beban Bagi Hasil Jasa Teknisi

    public const AKUN_UTANG = '210-06'; // Utang Bagi Hasil Teknisi

    public function __construct(
        protected JurnalService $jurnalService
    ) {}

    /**
     * Total jasa tiket (tanpa part/sparepart) — memakai rincian resmi tiket;
     * fallback `estimasi_biaya` hanya untuk tiket legacy tanpa item jasa.
     */
    public function totalJasaTiket(TiketServis $tiket): float
    {
        return (float) $tiket->getRincianBiayaLengkap()['total_jasa'];
    }

    /**
     * Rule terbaik untuk karyawan: per teknisi > per cabang > global.
     * Hanya rule aktif; null bila teknisi non-gaji tanpa rule (tidak dapat bagi hasil).
     */
    public function ruleUntuk(?Karyawan $karyawan): ?BagiHasilRule
    {
        if (! $karyawan) {
            return null;
        }

        $cabangId = $karyawan->cabang_id;

        return BagiHasilRule::where('is_aktif', true)
            ->where(function ($q) use ($karyawan, $cabangId) {
                $q->where('karyawan_id', $karyawan->id)
                    ->orWhere(fn ($q2) => $q2->whereNull('karyawan_id')->where('cabang_id', $cabangId))
                    ->orWhere(fn ($q2) => $q2->whereNull('karyawan_id')->whereNull('cabang_id'));
            })
            ->orderByDesc('karyawan_id') // spesifik dulu, null terakhir
            ->orderByDesc('cabang_id')   // lalu cabang, global terakhir
            ->first();
    }

    /**
     * Accru bagi hasil untuk tiket yang selesai. Dipanggil dari ServisService::onSelesai.
     *
     * Idempoten per tiket: 1 tiket = 1 baris. Baris yang sudah dibatalkan bisa
     * dihidupkan lagi bila tiket diselesaikan ulang (override batal → selesai).
     */
    public function accru(TiketServis $tiket): ?BagiHasil
    {
        if (($tiket->status ?? null) !== 'selesai') {
            return null;
        }

        $teknisiId = $tiket->teknisi_id;
        if (! $teknisiId) {
            return null;
        }

        $karyawan = Karyawan::where('user_id', $teknisiId)
            ->where('status_aktif', true)
            ->first();
        if (! $karyawan || $karyawan->metode_penghasilan !== 'bagi_hasil') {
            return null;
        }

        $rule = $this->ruleUntuk($karyawan);
        if (! $rule) {
            return null;
        }

        $totalJasa = $this->totalJasaTiket($tiket);
        if ($totalJasa <= 0) {
            return null;
        }

        if ($totalJasa < (float) $rule->min_jasa) {
            return null;
        }

        $nominal = round($totalJasa * (float) $rule->persen / 100, 2);
        if ($nominal <= 0) {
            return null;
        }

        $periode = optional($tiket->tanggal_selesai)->format('Y-m') ?? now()->format('Y-m');

        return BagiHasil::updateOrCreate(
            ['tiket_servis_id' => $tiket->id],
            [
                'no_bagi_hasil' => 'BH-'.$tiket->no_tiket,
                'karyawan_id' => $karyawan->id,
                'teknisi_id' => $teknisiId,
                'cabang_id' => $tiket->cabang_id,
                'periode' => $periode,
                'total_jasa' => $totalJasa,
                'persen' => (float) $rule->persen,
                'nominal' => $nominal,
                'status' => BagiHasil::STATUS_PENDING,
                'keterangan' => 'Bagi hasil jasa '.$tiket->no_tiket,
            ]
        );
    }

    /**
     * Batalkan bagi hasil yang sudah diakui saat tiket selesai lalu dibatalkan/ditolak.
     * Baris yang sudah cair TIDAK disentuh (pembatalan setelah cair = pengembalian dana manual).
     */
    public function reversal(TiketServis $tiket): void
    {
        BagiHasil::where('tiket_servis_id', $tiket->id)
            ->whereIn('status', [BagiHasil::STATUS_PENDING, BagiHasil::STATUS_DISETUJUI])
            ->update([
                'status' => BagiHasil::STATUS_DIBATALKAN,
                'keterangan' => 'Dibatalkan — tiket '.$tiket->no_tiket.' batal/ditolak',
            ]);
    }

    public function getOrCreatePeriode(string $periode, ?int $cabangId = null): BagiHasilPeriode
    {
        $target = $cabangId ?? currentCabangId();

        $query = BagiHasilPeriode::withoutCabangScope()->where('periode', $periode);
        if ($target) {
            $query->where('cabang_id', $target);
        } else {
            $query->whereNull('cabang_id');
        }

        $record = $query->first();
        if ($record) {
            return $record;
        }

        return BagiHasilPeriode::create([
            'periode' => $periode,
            'cabang_id' => $target,
            'tanggal_mulai' => $periode.'-01',
            'tanggal_selesai' => date('Y-m-t', strtotime($periode.'-01')),
            'status' => BagiHasilPeriode::STATUS_DRAFT,
        ]);
    }

    /**
     * Hitung ulang total payout periode dari baris pending (idempoten).
     */
    public function hitungPeriode(string $periode, ?int $cabangId = null): array
    {
        $target = $cabangId ?? currentCabangId();
        $periodeRecord = $this->getOrCreatePeriode($periode, $target);

        if ($periodeRecord->status === BagiHasilPeriode::STATUS_DIBAYAR) {
            throw new \DomainException("Periode {$periode} sudah dibayar. Tidak dapat dihitung ulang.");
        }

        $rows = $this->queryPendingPeriode($periode, $target)->get();

        $periodeRecord->update([
            'total' => (float) $rows->sum('nominal'),
            'jumlah_tiket' => $rows->count(),
        ]);

        return [
            'periode' => $periode,
            'total' => (float) $rows->sum('nominal'),
            'jumlah_tiket' => $rows->count(),
            'cabang_id' => $target,
        ];
    }

    /**
     * Ajukan approval via F1-1 (total > threshold). Return kebutuhan + request id.
     */
    public function ajukanApproval(string $periode, int $userId, string $reason = '', ?int $cabangId = null): array
    {
        $target = $cabangId ?? currentCabangId();
        $this->hitungPeriode($periode, $target);

        $periodeRecord = $this->getOrCreatePeriode($periode, $target);
        $total = (float) $periodeRecord->total;
        $needsApproval = $total > self::THRESHOLD_APPROVAL;
        $approvalRequestId = null;

        if ($needsApproval) {
            $approvalRequestId = app(ApprovalService::class)
                ->ajukan('bagi_hasil', $periodeRecord->id, $target, [
                    'amount' => $total,
                    'reason' => $reason ?: "Payout bagi hasil periode {$periode} perlu approval",
                ], $userId)
                ?->id;
        }

        return [
            'needs_approval' => $needsApproval,
            'approval_request_id' => $approvalRequestId,
            'total' => $total,
            'threshold' => self::THRESHOLD_APPROVAL,
        ];
    }

    /**
     * Proses periode → posting jurnal accrual (debit beban / kredit utang) dan
     * kunci baris pending menjadi `disetujui`. Fail-closed + idempoten per periode.
     */
    public function prosesPeriode(string $periode, ?int $cabangId = null): array
    {
        $target = $cabangId ?? currentCabangId();
        $periodeRecord = $this->getOrCreatePeriode($periode, $target);

        if ($periodeRecord->status === BagiHasilPeriode::STATUS_DIBAYAR) {
            throw new \DomainException("Periode {$periode} sudah dibayar.");
        }

        if ($periodeRecord->status === BagiHasilPeriode::STATUS_SELESAI) {
            return [
                'no_jurnal' => $periodeRecord->no_jurnal_accrue,
                'total' => (float) $periodeRecord->total,
                'sudah_diproses' => true,
            ];
        }

        $this->hitungPeriode($periode, $target);
        $periodeRecord->refresh();
        $total = (float) $periodeRecord->total;

        if ($total <= 0) {
            throw new \DomainException("Tidak ada bagi hasil pending pada periode {$periode}.");
        }

        if ($total > self::THRESHOLD_APPROVAL) {
            $disetujui = ApprovalRequest::where('entity_type', 'bagi_hasil')
                ->where('entity_id', $periodeRecord->id)
                ->where('status', 'disetujui')
                ->exists();
            if (! $disetujui) {
                throw new \DomainException('Bagi hasil periode ini belum disetujui via F1-1 approval engine.');
            }
        }

        return DB::transaction(function () use ($periode, $periodeRecord, $target, $total) {
            $this->pastikanCoa();

            $noJurnal = 'JRL-BH-'.$periode;

            $this->jurnalService->post(
                noJurnal: $noJurnal,
                tanggal: now(),
                sumber: 'bagi_hasil',
                lines: [
                    ['akun_kode' => self::AKUN_BEBAN, 'debit' => $total, 'kredit' => 0],
                    ['akun_kode' => self::AKUN_UTANG, 'debit' => 0, 'kredit' => $total],
                ],
                deskripsi: "Accrual bagi hasil jasa teknisi periode {$periode}".($target ? " (Cabang #{$target})" : ''),
                cabangId: $target,
                userId: auth()->id(),
                referensiTipe: BagiHasilPeriode::class,
                referensiId: $periodeRecord->id,
                idempotencyKey: 'bagi-hasil-accrue:'.$periode.':'.($target ?? '0'),
            );

            $this->queryPendingPeriode($periode, $target)
                ->update(['status' => BagiHasil::STATUS_DISETUJUI]);

            $periodeRecord->update([
                'status' => BagiHasilPeriode::STATUS_SELESAI,
                'no_jurnal_accrue' => $noJurnal,
            ]);

            return ['no_jurnal' => $noJurnal, 'total' => $total, 'sudah_diproses' => false];
        }, 3);
    }

    /**
     * Bayar periode → posting jurnal pembayaran (debit utang / kredit kas) dan
     * kunci baris `disetujui` menjadi `cair`. Fail-closed + idempoten per periode.
     */
    public function bayarPeriode(string $periode, string $akunKasKode = '110-01', ?int $cabangId = null): array
    {
        $target = $cabangId ?? currentCabangId();
        $periodeRecord = $this->getOrCreatePeriode($periode, $target);

        if ($periodeRecord->status === BagiHasilPeriode::STATUS_DIBAYAR) {
            throw new \DomainException("Periode {$periode} sudah dibayar sebelumnya.");
        }

        if ($periodeRecord->status !== BagiHasilPeriode::STATUS_SELESAI) {
            throw new \DomainException("Periode {$periode} belum diproses (jurnal accrual belum ada).");
        }

        $total = (float) $periodeRecord->total;

        return DB::transaction(function () use ($periode, $periodeRecord, $target, $total, $akunKasKode) {
            $this->pastikanCoa();

            $noJurnal = 'JRL-BH-BAYAR-'.$periode;

            $this->jurnalService->post(
                noJurnal: $noJurnal,
                tanggal: now(),
                sumber: 'bagi_hasil',
                lines: [
                    ['akun_kode' => self::AKUN_UTANG, 'debit' => $total, 'kredit' => 0],
                    ['akun_kode' => $akunKasKode, 'debit' => 0, 'kredit' => $total],
                ],
                deskripsi: "Pembayaran bagi hasil jasa teknisi periode {$periode}".($target ? " (Cabang #{$target})" : ''),
                cabangId: $target,
                userId: auth()->id(),
                referensiTipe: BagiHasilPeriode::class,
                referensiId: $periodeRecord->id,
                idempotencyKey: 'bagi-hasil-bayar:'.$periode.':'.($target ?? '0'),
            );

            BagiHasil::where('periode', $periode)
                ->where('cabang_id', $target)
                ->where('status', BagiHasil::STATUS_DISETUJUI)
                ->update(['status' => BagiHasil::STATUS_CAIR, 'paid_at' => now()]);

            $periodeRecord->update([
                'status' => BagiHasilPeriode::STATUS_DIBAYAR,
                'no_jurnal_bayar' => $noJurnal,
                'paid_at' => now(),
            ]);

            return ['no_jurnal' => $noJurnal, 'total' => $total];
        }, 3);
    }

    /**
     * Ringkasan bagi hasil karyawan untuk periode (widget portal karyawan).
     */
    public function ringkasanTeknisi(int $karyawanId, string $periode): array
    {
        $rows = BagiHasil::where('karyawan_id', $karyawanId)
            ->where('periode', $periode)
            ->get();

        return [
            'periode' => $periode,
            'jumlah_tiket' => $rows->count(),
            'total_jasa' => (float) $rows->sum('total_jasa'),
            'pending' => (float) $rows->where('status', BagiHasil::STATUS_PENDING)->sum('nominal'),
            'disetujui' => (float) $rows->where('status', BagiHasil::STATUS_DISETUJUI)->sum('nominal'),
            'cair' => (float) $rows->where('status', BagiHasil::STATUS_CAIR)->sum('nominal'),
            'dibatalkan' => (float) $rows->where('status', BagiHasil::STATUS_DIBATALKAN)->sum('nominal'),
        ];
    }

    public function riwayatTeknisi(int $karyawanId, int $limit = 10)
    {
        return BagiHasil::with('tiketServis')
            ->where('karyawan_id', $karyawanId)
            ->orderByDesc('id')
            ->limit($limit)
            ->get();
    }

    /**
     * Rekap per teknisi untuk tab payout (hanya baris pending/disetujui).
     */
    public function rekapTeknisi(string $periode, ?int $cabangId = null): array
    {
        $target = $cabangId ?? currentCabangId();

        return $this->queryPendingPeriode($periode, $target)
            ->get()
            ->groupBy('karyawan_id')
            ->map(fn ($rows) => [
                'karyawan_id' => (int) $rows->first()->karyawan_id,
                'nama' => $rows->first()->karyawan?->nama ?? 'Karyawan #'.$rows->first()->karyawan_id,
                'jumlah_tiket' => $rows->count(),
                'total_jasa' => (float) $rows->sum('total_jasa'),
                'total_nominal' => (float) $rows->sum('nominal'),
            ])
            ->values()
            ->toArray();
    }

    protected function queryPendingPeriode(string $periode, ?int $cabangId)
    {
        $query = BagiHasil::where('periode', $periode)
            ->whereIn('status', [BagiHasil::STATUS_PENDING, BagiHasil::STATUS_DISETUJUI]);

        if ($cabangId) {
            $query->where('cabang_id', $cabangId);
        } else {
            $query->whereNull('cabang_id');
        }

        return $query;
    }

    /**
     * Pastikan COA bagi hasil ada & aktif (idempoten) — pola sama dengan
     * PayrollService::approvePayroll yang menjamin 520-09.
     */
    protected function pastikanCoa(): void
    {
        AkunCOA::firstOrCreate(
            ['kode' => self::AKUN_UTANG],
            ['nama' => 'Utang Bagi Hasil Teknisi', 'tipe' => 'kewajiban', 'kelompok' => 'utang_gaji', 'saldo_normal' => 'kredit', 'is_active' => true]
        );

        AkunCOA::firstOrCreate(
            ['kode' => self::AKUN_BEBAN],
            ['nama' => 'Beban Bagi Hasil Jasa Teknisi', 'tipe' => 'beban', 'kelompok' => 'beban_operasional', 'saldo_normal' => 'debit', 'is_active' => true]
        );
    }
}
