<?php

namespace App\Modules\Wms\Services;

use App\Modules\Akunting\Services\JurnalService;
use App\Modules\Rbac\Services\AuditService;
use App\Modules\Wms\Models\CycleCountSchedule;
use App\Modules\Wms\Models\CycleCountTask;
use App\Modules\Wms\Models\Gudang;
use App\Modules\Wms\Models\Produk;
use App\Modules\Wms\Models\Rak;
use App\Modules\Wms\Models\StockMutationLog;
use App\Modules\Wms\Models\StokItem;
use App\Modules\Wms\Models\StokLog;
use App\Modules\Workflow\Services\ApprovalService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * [F3-7 / G-18] Cycle count otomatis.
 *
 * - jalankanHarian(): cek jadwal jatuh tempo (frekuensi hari/jam) → generate task
 *   dengan sample item acak CABANG-SCOPED (seed Fisher-Yates mt_rand + snapshot item).
 *   Idempoten: unique (schedule, tanggal) + last_run_at hari ini → double-run tidak dobel task.
 * - hitung(): input qty fisik per item → selisih diklasifikasi MINOR / MAJOR:
 *   - minor (|selisih| <= threshold_unit ATAU |selisih|/stok <= threshold_persen %):
 *     koreksi langsung tanpa pause (pola opname existing — StokItem + StockMutationLog
 *     + StokLog jenis 'cycle_count' + jurnal penyesuaian 130-01/520-08 persis T-14)
 *   - major: diajukan ke ApprovalService F1-1 (entity_type 'cycle_count');
 *     hook selesaikanEntity → disetujui: terapkanKoreksi; ditolak: tolakTask.
 * - Semua baca/tulis di-stroop ke cabang task (cabang_id scoping mutlak).
 */
class CycleCountService
{
    public function __construct(
        protected ApprovalService $approvalService,
        protected JurnalService $jurnalService
    ) {}

    /**
     * Pemicu harian (dipanggil CycleCountJob, queue database).
     *
     * @return Collection<int, CycleCountTask> task yang dibuat pada run ini
     */
    public function jalankanHarian(): Collection
    {
        $hariIni = now();

        $jatuhTempo = CycleCountSchedule::query()
            ->where('is_aktif', true)
            // Idempotensi: jadwal yang sudah dijalankan hari ini dilewati
            ->where(function ($q) {
                $q->whereNull('last_run_at')->orWhere('last_run_at', '<', now()->startOfDay());
            })
            ->get()
            ->filter(fn (CycleCountSchedule $s) => $this->jatuhTempo($s, $hariIni));

        $tasks = collect();
        foreach ($jatuhTempo as $schedule) {
            $task = $this->generateTask($schedule);
            if ($task) {
                $tasks->push($task);
            }
        }

        return $tasks;
    }

    /**
     * Jadwal jatuh tempo hari ini?
     * - mingguan: hari == ISO weekday hari ini (1 = Senin .. 7 = Minggu)
     * - bulanan: hari == tanggal bulan berjalan (31 di bulan 30 → jatuh tempo tgl 30)
     * - keduanya: waktu sekarang harus sudah melewati jam terjadwal
     * - last_run_at sudah hari ini → tidak jatuh tempo (anti dobel)
     */
    public function jatuhTempo(CycleCountSchedule $schedule, $hari = null): bool
    {
        $hari = $hari ?? now();

        if (! $schedule->is_aktif) {
            return false;
        }

        if ($schedule->last_run_at && $schedule->last_run_at->isSameDay($hari)) {
            return false;
        }

        if ($schedule->frekuensi === 'mingguan') {
            if ((int) $schedule->hari !== (int) $hari->format('N')) {
                return false;
            }
        } else {
            $target = min((int) $schedule->hari, (int) $hari->daysInMonth());
            if ((int) $hari->format('j') !== $target) {
                return false;
            }
        }

        return $hari->format('H:i') >= $schedule->jam;
    }

    /**
     * Generate task dengan sample item acak dari stok di target (cabang-scoped).
     * Idempoten: firstOrCreate per (schedule, tanggal) — unique index second layer.
     */
    public function generateTask(CycleCountSchedule $schedule): ?CycleCountTask
    {
        $kandidat = $this->kandidatItems($schedule);

        if ($kandidat->isEmpty()) {
            // Tandai sudah dijalankan agar job tidak retry tiap jam tanpa kandidat
            $schedule->update(['last_run_at' => now()]);

            return null;
        }

        // Sample acak deterministik: Fisher-Yates dengan mt_rand ber-seed
        // (Collection::shuffle pakai random_int — tidak bisa di-seed, jadi manual).
        $seed = random_int(1, 2147483647);
        $ids = $kandidat->pluck('id')->all();
        mt_srand($seed);
        $n = count($ids);
        for ($i = $n - 1; $i > 0; $i--) {
            $j = mt_rand(0, $i);
            [$ids[$i], $ids[$j]] = [$ids[$j], $ids[$i]];
        }
        $terpilih = array_slice($ids, 0, min((int) $schedule->sample_size, $n));

        $terpilihSet = array_flip($terpilih);
        $snapshot = $kandidat
            ->filter(fn (StokItem $item) => isset($terpilihSet[$item->id]))
            ->values()
            ->map(fn (StokItem $item) => [
                'stok_item_id' => $item->id,
                'produk_id' => $item->produk_id,
                'sku_variant_id' => $item->sku_variant_id,
                'gudang_id' => $item->gudang_id,
                'rak_id' => $item->rak_id,
                'nama' => $item->produk?->nama ?? ('Produk #'.$item->produk_id),
                'stok_sistem' => (int) $item->jumlah,
            ])
            ->all();

        $task = CycleCountTask::firstOrCreate(
            [
                'cycle_count_schedule_id' => $schedule->id,
                'tanggal' => now()->toDateString(),
            ],
            [
                'cabang_id' => $schedule->cabang_id,
                'no_task' => $this->generateNoTask(),
                'tipe_target' => $schedule->tipe_target,
                'target_id' => $schedule->target_id,
                'target_kategori' => $schedule->target_kategori,
                'target_label' => $schedule->target_label,
                'seed' => $seed,
                'sample_items' => $snapshot,
                'status' => 'menunggu_count',
                'threshold_unit' => $schedule->threshold_unit,
                'threshold_persen' => $schedule->threshold_persen,
            ]
        );

        $schedule->update(['last_run_at' => now()]);

        return $task;
    }

    /**
     * Kandidat sample: StokItem pada gudang cabang jadwal, sesuai target rak/kategori.
     * Kandidat stok cabang LAIN tidak pernah masuk (cabang_id scoping mutlak).
     *
     * @return Collection<int, StokItem>
     */
    protected function kandidatItems(CycleCountSchedule $schedule): Collection
    {
        $gudangIds = Gudang::where('cabang_id', $schedule->cabang_id)->pluck('id');

        if ($gudangIds->isEmpty()) {
            return collect();
        }

        return StokItem::query()
            ->whereIn('gudang_id', $gudangIds)
            ->when(
                $schedule->tipe_target === 'rak',
                fn ($q) => $q->where('rak_id', $schedule->target_id)
            )
            ->when(
                $schedule->tipe_target === 'kategori',
                fn ($q) => $q->whereHas('produk', fn ($p) => $p->where('kategori', $schedule->target_kategori))
            )
            ->with('produk:id,nama,kategori')
            ->get();
    }

    protected function generateNoTask(): string
    {
        $today = now()->format('Ymd');
        $count = CycleCountTask::whereDate('tanggal', now()->toDateString())->count() + 1;

        return sprintf('CCT-%s-%04d', $today, $count);
    }

    /**
     * Input hasil count fisik per item (key: stok_item_id => qty fisik).
     * Minor → koreksi langsung (status selesai). Major → approval F1-1 dulu.
     *
     * @param  array<int|string, int|float|string>  $fisikPerItem
     *
     * @throws \Exception pesan Indonesia bila validasi gagal
     */
    public function hitung(CycleCountTask $task, array $fisikPerItem, ?int $userId = null): CycleCountTask
    {
        if ($task->status !== 'menunggu_count') {
            throw new \Exception('Hanya task cycle count berstatus menunggu_count yang dapat dihitung');
        }

        $userId = $userId ?? auth()->id();
        $gudangIds = Gudang::where('cabang_id', $task->cabang_id)->pluck('id');

        $fisikNorm = [];
        foreach ($fisikPerItem as $key => $value) {
            $fisikNorm[(int) $key] = (int) $value;
        }

        $hasil = [];
        foreach ($task->sample_items ?? [] as $item) {
            $stokItemId = (int) ($item['stok_item_id'] ?? 0);

            if (! array_key_exists($stokItemId, $fisikNorm)) {
                throw new \Exception('Stok fisik belum diisi untuk semua item sample ('.($item['nama'] ?? $stokItemId).')');
            }

            if ($fisikNorm[$stokItemId] < 0) {
                throw new \Exception('Stok fisik tidak boleh negatif ('.($item['nama'] ?? $stokItemId).')');
            }

            // Stok sistem saat ini — hanya baris yang masih berada di cabang task
            $stok = StokItem::whereKey($stokItemId)->whereIn('gudang_id', $gudangIds)->first();
            $stokSistem = $stok ? (int) $stok->jumlah : (int) ($item['stok_sistem'] ?? 0);
            $fisik = $fisikNorm[$stokItemId];
            $selisih = $fisik - $stokSistem;

            $hasil[] = [
                'stok_item_id' => $stokItemId,
                'produk_id' => (int) ($item['produk_id'] ?? 0),
                'sku_variant_id' => $item['sku_variant_id'] ?? null,
                'gudang_id' => (int) ($item['gudang_id'] ?? 0),
                'nama' => $item['nama'] ?? '',
                'stok_snapshot' => (int) ($item['stok_sistem'] ?? 0), // stok saat sample diambil (audit)
                'stok_sistem' => $stokSistem, // stok sistem saat count
                'stok_fisik' => $fisik,
                'selisih' => $selisih,
                'klasifikasi' => $selisih === 0 ? 'cocok' : $this->klasifikasi($selisih, $stokSistem, $task),
            ];
        }

        if ($hasil === []) {
            throw new \Exception('Task cycle count tidak memiliki item sample');
        }

        $task->update([
            'hasil' => $hasil,
            'counted_at' => now(),
            'user_id' => $userId,
        ]);

        $adaMajor = collect($hasil)->contains('klasifikasi', 'major');

        if (! $adaMajor) {
            // MINOR: koreksi langsung tanpa pause (pola opname existing)
            return $this->koreksi($task, $userId, 'selisih minor');
        }

        // MAJOR: perlu approval → hook F1-1 (entity_type 'cycle_count')
        $task->update(['status' => 'menunggu_approval']);

        $approval = $this->approvalService->ajukan(
            'cycle_count',
            $task->id,
            $task->cabang_id,
            [
                'amount' => $this->nilaiSelisih($hasil),
                'no_task' => $task->no_task,
                'target_label' => $task->target_label,
                'jumlah_item_major' => collect($hasil)->where('klasifikasi', 'major')->count(),
                'total_selisih' => (int) collect($hasil)->sum('selisih'),
            ],
            $userId ?? ApprovalService::pemohon(null)
        );

        if ($approval === null) {
            // Rule approval tidak ada/disabled → koreksi langsung (pola GrnService F2-2)
            return $this->koreksi($task, $userId, 'major tanpa rule approval');
        }

        return $task->fresh();
    }

    /**
     * Klasifikasi selisih: MINOR = |selisih| <= threshold_unit ATAU |selisih|/stok <= threshold_persen %.
     * Threshold per task (snapshot dari jadwal — configurable).
     */
    protected function klasifikasi(int $selisih, int $stokSistem, CycleCountTask $task): string
    {
        $abs = abs($selisih);

        if ($abs <= (int) $task->threshold_unit) {
            return 'minor';
        }

        if ($stokSistem > 0 && ($abs / $stokSistem * 100) <= (float) $task->threshold_persen) {
            return 'minor';
        }

        return 'major';
    }

    /**
     * Nilai rupiah estimasi total selisih (|selisih| × harga_beli) — payload amount utk rule approval.
     *
     * @param  array<int, array<string, mixed>>  $hasil
     */
    protected function nilaiSelisih(array $hasil): float
    {
        $harga = Produk::whereIn('id', collect($hasil)->pluck('produk_id'))
            ->pluck('harga_beli', 'id');

        $nilai = 0.0;
        foreach ($hasil as $h) {
            if ((int) $h['selisih'] === 0) {
                continue;
            }
            $nilai += abs((int) $h['selisih']) * (float) ($harga[$h['produk_id']] ?? 0);
        }

        return round($nilai, 2);
    }

    /**
     * Hook ApprovalService (selesaikanEntity) — disetujui → terapkan koreksi.
     * Idempoten: hanya transisi dari status menunggu_approval.
     */
    public function terapkanKoreksi(int $taskId, ?int $userId): ?CycleCountTask
    {
        $task = CycleCountTask::find($taskId);

        if (! $task || $task->status !== 'menunggu_approval') {
            return $task; // sudah diterapkan / ditolak — tidak dobel koreksi
        }

        return $this->koreksi($task, $userId, 'disetujui approval');
    }

    /**
     * Hook ApprovalService (selesaikanEntity) — ditolak → tandai ditolak, stok tidak diubah.
     * Idempoten: hanya transisi dari status menunggu_approval.
     */
    public function tolakTask(int $taskId, ?int $userId, string $catatan): ?CycleCountTask
    {
        $task = CycleCountTask::find($taskId);

        if (! $task || $task->status !== 'menunggu_approval') {
            return $task;
        }

        $task->update([
            'status' => 'ditolak',
            'approver_id' => $userId,
            'catatan' => 'Ditolak: '.$catatan,
        ]);

        return $task->fresh();
    }

    /**
     * Terapkan koreksi stok untuk semua item berselisih — pola opname existing persis
     * (WmsController::approveOpname / OpnameTab.approveOpname):
     * - StokItem.jumlah ← stok_fisik (scoped ke gudang cabang task)
     * - StockMutationLog sumber 'cycle_count' + StokLog jenis 'cycle_count'
     *   dengan jumlah_sebelum / perubahan / jumlah_setelah (audit before/after)
     * - Jurnal penyesuaian 130-01 vs 520-08 (pola T-14 opname; opname EXISTING posting jurnal)
     * - Activity log via LogsActivity pada model + AuditService (F1-4)
     */
    protected function koreksi(CycleCountTask $task, ?int $userId, string $catatan): CycleCountTask
    {
        return DB::transaction(function () use ($task, $userId, $catatan) {
            $gudangIds = Gudang::where('cabang_id', $task->cabang_id)->pluck('id');
            $jurnalLines = [];
            $jumlahDikoreksi = 0;

            foreach ($task->hasil ?? [] as $h) {
                $selisih = (int) ($h['selisih'] ?? 0);
                if ($selisih === 0) {
                    continue;
                }

                // Guard scoping: hanya baris stok yang masih di gudang cabang task
                $stok = StokItem::whereKey((int) $h['stok_item_id'])->whereIn('gudang_id', $gudangIds)->first();
                if (! $stok) {
                    continue;
                }

                $sebelum = (int) $stok->jumlah;
                $setelah = (int) ($h['stok_fisik'] ?? $sebelum);
                if ($sebelum !== $setelah) {
                    $stok->update(['jumlah' => $setelah]);
                }

                StockMutationLog::create([
                    'produk_id' => (int) $h['produk_id'],
                    'sku_variant_id' => $h['sku_variant_id'] ?? null,
                    'gudang_id' => (int) $h['gudang_id'],
                    'delta' => $selisih,
                    'sumber' => 'cycle_count',
                    'referensi_tipe' => CycleCountTask::class,
                    'referensi_id' => $task->id,
                    'terjadi_at' => now(),
                ]);

                // Audit before/after eksplisit di StokLog (jenis cycle_count)
                StokLog::create([
                    'gudang_id' => (int) $h['gudang_id'],
                    'produk_id' => (int) $h['produk_id'],
                    'sku_variant_id' => $h['sku_variant_id'] ?? null,
                    'user_id' => $userId,
                    'jenis' => 'cycle_count',
                    'referensi_tipe' => CycleCountTask::class,
                    'referensi_id' => $task->id,
                    'jumlah_sebelum' => $sebelum,
                    'perubahan' => $selisih,
                    'jumlah_setelah' => $setelah,
                    'catatan' => "Cycle Count {$task->no_task}: ".($h['nama'] ?? '')." — {$catatan}",
                ]);

                // Jurnal penyesuaian persis pola opname existing (T-14):
                // selisih > 0 (fisik > sistem): 130-01 debit / 520-08 kredit
                // selisih < 0 (fisik < sistem): 130-01 kredit / 520-08 debit
                $abs = abs($selisih);
                $jurnalLines[] = $selisih > 0
                    ? ['akun_kode' => '130-01', 'debit' => $abs, 'kredit' => 0]
                    : ['akun_kode' => '130-01', 'debit' => 0, 'kredit' => $abs];
                $jurnalLines[] = $selisih > 0
                    ? ['akun_kode' => '520-08', 'debit' => 0, 'kredit' => $abs]
                    : ['akun_kode' => '520-08', 'debit' => $abs, 'kredit' => 0];

                $jumlahDikoreksi++;
            }

            if ($jurnalLines !== []) {
                $this->jurnalService->post(
                    $this->jurnalService->generateNoJurnal('opname', (int) $task->cabang_id),
                    now(),
                    'opname',
                    $jurnalLines,
                    "Penyesuaian Cycle Count {$task->no_task}",
                    (int) $task->cabang_id,
                    $userId,
                    CycleCountTask::class,
                    $task->id
                );
            }

            $task->update([
                'status' => 'selesai',
                'applied_at' => now(),
                'approver_id' => $userId,
                'catatan' => trim(
                    ($task->catatan ? $task->catatan.' | ' : '')
                    ."Koreksi diterapkan ({$jumlahDikoreksi} item): {$catatan}"
                ),
            ]);

            // Audit stok manual (PRD-Advanced §5.4 / pola WmsController::approveOpname)
            app(AuditService::class)->catat(
                'StokItem',
                'adjust',
                $task->id,
                "Cycle count {$task->no_task} dikoreksi — {$jumlahDikoreksi} item disesuaikan ({$catatan})",
                null,
                ['status' => 'selesai', 'target' => $task->target_label]
            );

            return $task->fresh();
        });
    }

    /**
     * Label rak utk form UI (cabang-scoped).
     *
     *
     * @return Collection<int, Rak>
     */
    public function raksCabang(int|string|null $cabangId): Collection
    {
        if ($cabangId === null || $cabangId === '') {
            return collect();
        }

        return Rak::whereHas('gudang', fn ($q) => $q->where('cabang_id', $cabangId))
            ->with('gudang')
            ->get();
    }
}
