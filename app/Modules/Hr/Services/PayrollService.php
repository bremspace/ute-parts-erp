<?php

namespace App\Modules\Hr\Services;

use App\Modules\Akunting\Models\AkunCOA;
use App\Modules\Akunting\Services\JurnalService;
use App\Modules\Hr\Models\Karyawan;
use App\Modules\Hr\Models\KaryawanKomponenGaji;
use App\Modules\Hr\Models\KomisiTeknisiRule;
use App\Modules\Hr\Models\PayrollKomisiDetail;
use App\Modules\Hr\Models\PayrollPeriode;
use App\Modules\Hr\Models\PayrollSlip;
use App\Modules\Reseller\Models\Komisi;
use App\Modules\Servis\Models\TiketServis;
use App\Modules\Workflow\Models\ApprovalRequest;
use App\Modules\Workflow\Services\ApprovalService;
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

    const THRESHOLD_APPROVAL = 10000000;

    /**
     * Hitung draft payroll untuk periode.
     *
     * @return array{karyawan_diproses: int, total_gaji: float, periode: string}
     */
    public function hitungDraft(string $periode): array
    {
        // Pastikan periode ada dulu
        $periodeId = $this->getOrCreatePeriode($periode);
        $periodeRecord = PayrollPeriode::find($periodeId);

        if ($periodeRecord && $periodeRecord->status === PayrollPeriode::STATUS_DIBAYAR) {
            throw new \DomainException("Periode {$periode} sudah dibayar. Tidak dapat dihitung ulang.");
        }

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
     * Efek samping: flip status periode → 'selesai' (enum DB: draft/diproses/
     * selesai/dibayar — 'disetujui' bukan nilai valid) DAN post jurnal payroll
     * via approvePayroll (debit Beban Gaji/Komisi, kredit Hutang Gaji).
     * Total di atas THRESHOLD_APPROVAL wajib sudah ada ApprovalRequest
     * (entity_type payroll) berstatus 'disetujui' — §4.1 alur 2.
     */
    public function finalisasiDisetujui(string $periode): array
    {
        $periodeId = $this->getOrCreatePeriode($periode);
        $periodeRecord = PayrollPeriode::find($periodeId);
        if (! $periodeRecord) {
            throw new \Exception('Periode tidak ditemukan');
        }
        if ($periodeRecord->status !== PayrollPeriode::STATUS_DRAFT) {
            throw new \Exception('Periode sudah diproses');
        }

        // [F3-8] §4.1 alur 2 — total di atas threshold wajib approval F1-1 (guard, bukan auto-approve)
        $totalGaji = (float) PayrollSlip::where('payroll_periode_id', $periodeId)->sum('total_gaji');
        if ($totalGaji > self::THRESHOLD_APPROVAL) {
            $disetujui = ApprovalRequest::where('entity_type', 'payroll')
                ->where('entity_id', $periodeId)
                ->where('status', 'disetujui')
                ->exists();
            if (! $disetujui) {
                throw new \DomainException('Payroll belum disetujui via F1-1 approval engine.');
            }
        }

        // [B-10a / P0-4] Fail-closed: jurnal dulu, status periode SETELAH jurnal
        // commit. Sebelumnya status di-update ke 'selesai' DULU lalu approvePayroll()
        // mem-post jurnal → bila jurnal gagal, periode tetap 'selesai' tanpa akun.
        // Satu DB::transaction dipakai agar keduanya commit/rollback bersama.
        return DB::transaction(function () use ($periode, $periodeRecord) {
            // Post jurnal via approvePayroll
            $hasil = $this->approvePayroll($periode);

            $periodeRecord->update(['status' => PayrollPeriode::STATUS_SELESAI]);

            return $hasil;
        }, 3);
    }

    /**
     * Approve payroll periode → post jurnal.
     */
    public function approvePayroll(string $periode): array
    {
        $periodeId = $this->getOrCreatePeriode($periode);
        $slips = PayrollSlip::where('payroll_periode_id', $periodeId)->get();

        $totalGaji = (float) $slips->sum('total_gaji');
        $totalKomisi = (float) $slips->sum('total_komisi');
        $bebanGajiPokok = max(0, $totalGaji - $totalKomisi);

        // Pastikan COA 520-09 ada untuk Beban Komisi Teknisi / Karyawan
        AkunCOA::firstOrCreate(
            ['kode' => '520-09'],
            ['nama' => 'Beban Komisi Teknisi', 'tipe' => 'beban', 'kelompok' => 'beban_operasional', 'saldo_normal' => 'debit', 'is_active' => true]
        );

        // Post jurnal: debit Beban Gaji (520-01) + Beban Komisi (520-09) / kredit Hutang Gaji (210-02)
        // [AUDIT-FIX] total_gaji sudah memasukkan komisi, jadi 520-01 mencatat porsi gaji non-komisi
        // agar tidak double-counting dan total kredit Utang Gaji persis sama dengan total_gaji.
        $lines = [
            ['akun_kode' => '520-01', 'debit' => $bebanGajiPokok, 'kredit' => 0],
            ['akun_kode' => '520-09', 'debit' => $totalKomisi, 'kredit' => 0],
            ['akun_kode' => '210-02', 'debit' => 0, 'kredit' => $totalGaji],
        ];

        $noJurnal = sprintf('JRL-PR-%s', $periode);
        $jurnalRows = $this->jurnalService->post(
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

        // Update status slip; jurnal_id adalah FK id baris jurnal (bukan no_jurnal)
        PayrollSlip::where('payroll_periode_id', $periodeId)
            ->where('status', 'draft')
            ->update(['status' => 'approved', 'jurnal_id' => $jurnalRows[0]->id ?? null]);

        return ['no_jurnal' => $noJurnal, 'total_gaji' => $totalGaji, 'total_komisi' => $totalKomisi];
    }

    /**
     * Bayar payroll → update status dan post jurnal pembayaran.
     *
     * [B-10a / P0-4] Fail-closed: jurnal pembayaran diposting DULU dalam satu
     * DB::transaction, baru slip/perioda ditandai 'dibayar'. Jurnal gagal →
     * status tidak final (rollback total).
     */
    public function bayarPayroll(string $periode, string $akunKasKode = '110-01'): array
    {
        $periodeId = $this->getOrCreatePeriode($periode);
        $periodeRecord = PayrollPeriode::find($periodeId);

        if ($periodeRecord && $periodeRecord->status === PayrollPeriode::STATUS_DIBAYAR) {
            throw new \DomainException("Periode {$periode} sudah dibayar sebelumnya.");
        }

        return DB::transaction(function () use ($periode, $periodeId, $akunKasKode) {
            // Jurnal pembayaran: debit Utang Gaji (210-02) / kredit Kas ($akunKasKode)
            $slips = PayrollSlip::where('payroll_periode_id', $periodeId)->get();
            $total = $slips->sum('total_gaji');

            $lines = [
                ['akun_kode' => '210-02', 'debit' => $total, 'kredit' => 0],
                ['akun_kode' => $akunKasKode, 'debit' => 0, 'kredit' => $total],
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

            // Status baru final setelah jurnal sukses
            PayrollSlip::where('payroll_periode_id', $periodeId)
                ->where('status', 'approved')
                ->update(['status' => 'dibayar']);

            PayrollPeriode::where('id', $periodeId)->update(['status' => 'dibayar']);

            // Tandai komisi internal periode ini sebagai disetujui
            $mulai = $periode.'-01 00:00:00';
            $selesai = date('Y-m-t', strtotime($periode.'-01')).' 23:59:59';
            Komisi::where('aktor_tipe', 'karyawan')
                ->where('status', Komisi::STATUS_PENDING)
                ->whereBetween('created_at', [$mulai, $selesai])
                ->update(['status' => Komisi::STATUS_DISETUJUI]);

            return ['no_jurnal' => $noJurnal, 'total' => $total];
        }, 3);
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
            'tanggal_mulai' => $periode.'-01',
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
        $potonganAbsen = app(AbsensiService::class)->potonganAbsen($karyawan->id, $periode);
        $potongan = $this->hitungPotongan($karyawan->id, $periode);
        $resTeknisi = $this->hitungKomisiTeknisiWithDetails($karyawan, $periode);
        $komisiTeknisi = $resTeknisi['total'];
        $resInternal = $this->hitungKomisiInternalWithDetails($karyawan->id, $periode);
        $komisiInternal = $resInternal['total'];
        $komisi = $komisiTeknisi + $komisiInternal;

        $totalGaji = max(0, $pokok + $tunjangan - $potongan + $komisi);

        $periodeId = $this->getOrCreatePeriode($periode);

        $slip = PayrollSlip::updateOrCreate(
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
                    'potongan_absen' => $potonganAbsen,
                    'komisi' => $komisi,
                    'komisi_teknisi' => $komisiTeknisi,
                    'komisi_internal' => $komisiInternal,
                    'periode' => $periode,
                ]),
            ]
        );

        // Simpan rincian komisi ke payroll_komisi_detail
        PayrollKomisiDetail::where('slip_id', $slip->id)->delete();
        foreach (array_merge($resTeknisi['items'], $resInternal['items']) as $item) {
            PayrollKomisiDetail::create([
                'slip_id' => $slip->id,
                'tiket_servis_id' => $item['tiket_id'],
                'teknisi_id' => $karyawan->id,
                'jenis' => $item['jenis'],
                'nominal' => $item['nominal'],
            ]);
        }

        return $slip;
    }

    /**
     * Hitung total tunjangan komponen.
     */
    protected function hitungTunjangan(int $karyawanId): float
    {
        return (float) KaryawanKomponenGaji::where('karyawan_id', $karyawanId)
            ->whereIn('tipe', ['tunjangan', 'bonus'])
            ->where('is_aktif', true)
            ->sum('nominal_bulanan');
    }

    /**
     * Hitung total potongan komponen + potongan disiplin absen §4.2 alur 4.
     */
    protected function hitungPotongan(int $karyawanId, string $periode): float
    {
        $komponen = (float) KaryawanKomponenGaji::where('karyawan_id', $karyawanId)
            ->where('tipe', 'potongan')
            ->where('is_aktif', true)
            ->sum('nominal_bulanan');

        // [F3-8b] §4.2 alur 4 — potongan absen tanpa izin (opt-in per cabang)
        $potonganAbsen = app(AbsensiService::class)->potonganAbsen($karyawanId, $periode);

        return $komponen + $potonganAbsen;
    }

    /**
     * Hitung komisi teknisi dari tiket servis selesai dalam periode.
     */
    protected function hitungKomisiTeknisi(int $karyawanId, string $periode): float
    {
        $karyawan = Karyawan::find($karyawanId);
        if (! $karyawan) {
            return 0.0;
        }

        return $this->hitungKomisiTeknisiWithDetails($karyawan, $periode)['total'];
    }

    protected function hitungKomisiTeknisiWithDetails(Karyawan $karyawan, string $periode): array
    {
        if ($karyawan->jabatan !== 'teknisi') {
            return ['total' => 0.0, 'items' => []];
        }

        $rules = KomisiTeknisiRule::where('is_aktif', true)
            ->where(function ($q) {
                $q->whereNull('cabang_id')
                    ->orWhere('cabang_id', session('cabang_id'));
            })
            ->get();

        $totalKomisi = 0;
        $items = [];
        $tanggalMulai = $periode.'-01';
        $tanggalSelesai = date('Y-m-t', strtotime($periode.'-01'));

        // [F3-8c] 1 tiket = 1 komisi di slip; engine menang, KomisiTeknisiRule
        // hanya utk tiket tanpa catatan engine (status pending/disetujui).
        $tiketDibayarEngine = Komisi::where('aktor_tipe', 'karyawan')
            ->where('aktor_id', $karyawan->id)
            ->whereNotNull('tiket_servis_id')
            ->whereIn('status', [Komisi::STATUS_PENDING, Komisi::STATUS_DISETUJUI])
            ->whereBetween('created_at', [$periode.'-01 00:00:00', $tanggalSelesai.' 23:59:59'])
            ->pluck('tiket_servis_id');

        // tiket_servis.teknisi_id → users (bukan karyawan.id) — konsisten dgn KPI §4.2
        $tiket = TiketServis::where('teknisi_id', $karyawan->user_id)
            ->where('status', 'selesai')
            ->whereBetween('tanggal_selesai', [$tanggalMulai, $tanggalSelesai])
            ->get();

        foreach ($tiket as $t) {
            if ($tiketDibayarEngine->contains($t->id)) {
                continue;
            }
            foreach ($rules as $rule) {
                $nominal = 0.0;
                if ($rule->jenis === 'per_tiket') {
                    $nominal = (float) $rule->nominal;
                } elseif ($rule->jenis === 'persen_nilai_servis') {
                    $nominal = round((float) ($t->estimasi_biaya ?? 0) * ($rule->persen / 100), 2);
                }
                if ($nominal > 0) {
                    $totalKomisi += $nominal;
                    $items[] = [
                        'tiket_id' => $t->id,
                        'jenis' => 'servis',
                        'nominal' => $nominal,
                    ];
                }
            }
        }

        return ['total' => round($totalKomisi, 2), 'items' => $items];
    }

    /**
     * [F3-8c] Komisi internal (karyawan) dari engine multi-aktor (PRD §4.3):
     * trigger penjualan/lead_won/tiket_servis/target_kpi → komponen komisi payroll.
     * Status pending & disetujui ikut dihitung (payroll bisa diajukan sebelum
     * approval komisi selesai); status ditolak tidak dihitung.
     */
    protected function hitungKomisiInternal(int $karyawanId, string $periode): float
    {
        return $this->hitungKomisiInternalWithDetails($karyawanId, $periode)['total'];
    }

    protected function hitungKomisiInternalWithDetails(int $karyawanId, string $periode): array
    {
        $mulai = $periode.'-01';
        $selesai = date('Y-m-t', strtotime($mulai));

        $komisis = Komisi::where('aktor_tipe', 'karyawan')
            ->where('aktor_id', $karyawanId)
            ->whereIn('status', ['pending', 'disetujui'])
            ->whereBetween('created_at', [$mulai.' 00:00:00', $selesai.' 23:59:59'])
            ->get();

        $total = 0.0;
        $items = [];

        foreach ($komisis as $kom) {
            $nom = (float) $kom->nominal_komisi;
            $total += $nom;
            $items[] = [
                'tiket_id' => $kom->tiket_servis_id,
                'jenis' => $kom->tiket_servis_id ? 'servis_engine' : ($kom->lead_id ? 'lead_won' : 'penjualan'),
                'nominal' => $nom,
            ];
        }

        return ['total' => round($total, 2), 'items' => $items];
    }
}
