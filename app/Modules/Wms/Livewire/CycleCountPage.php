<?php

namespace App\Modules\Wms\Livewire;

use App\Modules\Wms\Models\CycleCountSchedule;
use App\Modules\Wms\Models\CycleCountTask;
use App\Modules\Wms\Models\KategoriProduk;
use App\Modules\Wms\Models\Produk;
use App\Modules\Wms\Models\StokItem;
use App\Modules\Wms\Models\StokLog;
use App\Modules\Wms\Services\CycleCountService;
use App\Modules\Workflow\Models\ApprovalRequest;
use App\Modules\Workflow\Services\ApprovalService;
use Illuminate\Support\Collection;
use Livewire\Component;

/**
 * [F3-7] Cycle Count Otomatis — lengkapi placeholder component.
 *
 * Fitur: schedule management, task generation, count input, approval status.
 * Scoping KETAT: semua query scope cabang_id dari session.
 */
class CycleCountPage extends Component
{
    public string $search = '';

    public string $filterStatus = 'semua';

    public int $perPage = 10;

    // Schedule form
    public bool $showScheduleForm = false;

    public string $scheduleNama = '';

    public string $tipeTarget = 'rak';

    public int|string $targetId = 0;

    public string $targetKategori = '';

    public string $frekuensi = 'mingguan';

    public int|string $hari = 1;

    public string $jam = '08:00';

    public int|string $sampleSize = 10;

    public int|string $thresholdUnit = 0;

    public int|string $thresholdPersen = 0;

    // Count form
    public ?int $selectedTaskId = null;

    public ?CycleCountTask $activeTask = null;

    public array $fisikPerItem = [];

    protected $listeners = ['taskGenerated', 'countSubmitted'];

    public function mount(): void
    {
        // Ensure cabang_id session
        if (! session('cabang_id')) {
            $this->dispatch('alert', ['type' => 'error', 'message' => 'Cabang aktif belum dipilih.']);
        }
    }

    // Detail Task state
    public ?int $detailTaskId = null;

    public bool $showDetailModal = false;

    public function bukaDetail(int $taskId): void
    {
        $this->detailTaskId = $taskId;
        $this->showDetailModal = true;
    }

    public function tutupDetail(): void
    {
        $this->detailTaskId = null;
        $this->showDetailModal = false;
    }

    // ===== Schedule =====

    public function bukaFormSchedule(): void
    {
        if (! auth()->user()?->can('wms.approve-opname')) {
            $this->dispatch('alert', ['type' => 'error', 'message' => 'Tidak punya izin mengelola jadwal cycle count.']);

            return;
        }
        $this->resetScheduleForm();
        $this->showScheduleForm = true;
    }

    public function resetScheduleForm(): void
    {
        $this->scheduleNama = '';
        $this->tipeTarget = 'rak';
        $this->targetId = 0;
        $this->targetKategori = '';
        $this->frekuensi = 'mingguan';
        $this->hari = 1;
        $this->jam = '08:00';
        $this->sampleSize = 10;
        $this->thresholdUnit = 0;
        $this->thresholdPersen = 0;
    }

    public function simpanSchedule(): void
    {
        if (! auth()->user()?->can('wms.approve-opname')) {
            $this->dispatch('alert', ['type' => 'error', 'message' => 'Tidak punya izin.']);

            return;
        }

        $this->validate([
            'scheduleNama' => 'required|string|max:255',
            'tipeTarget' => 'required|string|in:rak,kategori',
            'frekuensi' => 'required|string|in:mingguan,bulanan',
            'jam' => 'required|date_format:H:i',
            'sampleSize' => 'required|integer|min:1|max:100',
            'thresholdUnit' => 'required|integer|min:0',
            'thresholdPersen' => 'required|integer|min:0|max:100',
        ], [
            'scheduleNama.required' => 'Nama jadwal wajib.',
            'jam.required' => 'Jam wajib diisi.',
        ]);

        $cabangId = session('cabang_id');

        $targetId = ! empty($this->targetId) ? (int) $this->targetId : null;

        CycleCountSchedule::create([
            'cabang_id' => $cabangId,
            'nama' => $this->scheduleNama,
            'tipe_target' => $this->tipeTarget,
            'target_id' => $targetId,
            'target_kategori' => $this->targetKategori ?: null,
            'frekuensi' => $this->frekuensi,
            'hari' => (int) $this->hari,
            'jam' => $this->jam,
            'sample_size' => (int) $this->sampleSize,
            'threshold_unit' => (int) $this->thresholdUnit,
            'threshold_persen' => (int) $this->thresholdPersen,
            'is_aktif' => true,
        ]);

        $this->showScheduleForm = false;
        $this->dispatch('alert', ['type' => 'success', 'message' => 'Jadwal cycle count berhasil dibuat.']);
    }

    // ===== Task =====

    public function jalankanSchedule(int $scheduleId): void
    {
        if (! auth()->user()?->can('wms.approve-opname')) {
            $this->dispatch('alert', ['type' => 'error', 'message' => 'Tidak punya izin.']);

            return;
        }

        $schedule = CycleCountSchedule::where('cabang_id', session('cabang_id'))->findOrFail($scheduleId);
        $service = app(CycleCountService::class);
        $task = $service->generateTask($schedule);

        if ($task) {
            $this->dispatch('alert', ['type' => 'success', 'message' => "Tugas cycle count {$task->no_task} siap dihitung."]);
        } else {
            $this->dispatch('alert', ['type' => 'info', 'message' => 'Tidak ada kandidat produk stok untuk jadwal ini di cabang aktif.']);
        }
    }

    public function approveTask(int $taskId): void
    {
        if (! auth()->user()?->can('wms.approve-opname')) {
            $this->dispatch('alert', ['type' => 'error', 'message' => 'Anda tidak memiliki izin menyetujui cycle count.']);

            return;
        }

        $task = CycleCountTask::where('cabang_id', session('cabang_id'))
            ->where('status', 'menunggu_approval')
            ->findOrFail($taskId);

        $actionedBy = auth()->id() ?? 1;

        $pending = ApprovalRequest::where('entity_type', 'cycle_count')
            ->where('entity_id', $task->id)
            ->where('status', 'pending')
            ->first();

        if ($pending) {
            app(ApprovalService::class)->proses($pending->id, 'disetujui', $actionedBy, 'Disetujui supervisor dari Cycle Count');
        } else {
            app(CycleCountService::class)->terapkanKoreksi($task->id, $actionedBy);
        }

        $this->dispatch('alert', [
            'type' => 'success',
            'message' => "Tugas cycle count {$task->no_task} disetujui & stok fisik berhasil diselaraskan.",
        ]);
    }

    public function tolakTask(int $taskId, ?string $alasan = null): void
    {
        if (! auth()->user()?->can('wms.approve-opname')) {
            $this->dispatch('alert', ['type' => 'error', 'message' => 'Anda tidak memiliki izin menolak cycle count.']);

            return;
        }

        $task = CycleCountTask::where('cabang_id', session('cabang_id'))
            ->where('status', 'menunggu_approval')
            ->findOrFail($taskId);

        $actionedBy = auth()->id() ?? 1;
        $catatan = $alasan ?: 'Ditolak supervisor dari Cycle Count';

        $pending = ApprovalRequest::where('entity_type', 'cycle_count')
            ->where('entity_id', $task->id)
            ->where('status', 'pending')
            ->first();

        if ($pending) {
            app(ApprovalService::class)->proses($pending->id, 'ditolak', $actionedBy, $catatan);
        } else {
            app(CycleCountService::class)->tolakTask($task->id, $actionedBy, $catatan);
        }

        $this->dispatch('alert', [
            'type' => 'warning',
            'message' => "Tugas cycle count {$task->no_task} ditolak. Stok fisik tidak diubah.",
        ]);
    }

    // ===== Count =====

    public function mulaiCount(int $taskId): void
    {
        $task = CycleCountTask::where('cabang_id', session('cabang_id'))
            ->where('status', 'menunggu_count')
            ->findOrFail($taskId);

        // Backfill nama produk & varian jika kosong pada data lama
        $sampleItems = $task->sample_items ?? [];
        $needUpdate = false;

        $missingItemIds = collect($sampleItems)->filter(function ($item) {
            $nama = $item['nama'] ?? '';

            return empty($nama) || str_starts_with($nama, 'Produk #') || str_starts_with($nama, 'Item #');
        })->pluck('stok_item_id')->filter()->all();

        if (! empty($missingItemIds)) {
            $stokItems = StokItem::with(['produk:id,nama', 'skuVariant:id,nama_varian', 'rak:id,kode,nama'])
                ->whereIn('id', $missingItemIds)
                ->get()
                ->keyBy('id');

            foreach ($sampleItems as &$sItem) {
                $sId = $sItem['stok_item_id'] ?? null;
                if ($sId && isset($stokItems[$sId])) {
                    $stk = $stokItems[$sId];
                    if ($stk->produk) {
                        $namaLengkap = $stk->produk->nama;
                        if ($stk->skuVariant?->nama_varian) {
                            $namaLengkap .= ' ('.$stk->skuVariant->nama_varian.')';
                        }
                        $sItem['nama'] = $namaLengkap;
                        $sItem['rak_nama'] = $stk->rak ? ($stk->rak->kode.' - '.$stk->rak->nama) : ($sItem['rak_nama'] ?? null);
                        $needUpdate = true;
                    }
                }
            }
            unset($sItem);
        }

        if ($needUpdate) {
            $task->update(['sample_items' => $sampleItems]);
            $task->refresh();
        }

        $this->selectedTaskId = $taskId;
        $this->activeTask = $task;
        $this->fisikPerItem = [];

        // Initialize fisikPerItem dari sample_items
        foreach ($task->sample_items ?? [] as $item) {
            $this->fisikPerItem[$item['stok_item_id']] = '';
        }
    }

    public function batalCount(): void
    {
        $this->selectedTaskId = null;
        $this->activeTask = null;
        $this->fisikPerItem = [];
    }

    public function submitCount(): void
    {
        $task = CycleCountTask::where('cabang_id', session('cabang_id'))
            ->where('id', $this->selectedTaskId)
            ->where('status', 'menunggu_count')
            ->firstOrFail();

        // Validate fisikPerItem
        foreach ($this->fisikPerItem as $itemId => $qty) {
            $this->validate([
                "fisikPerItem.{$itemId}" => 'required|integer|min:0',
            ]);
        }

        $service = app(CycleCountService::class);
        $task = $service->hitung($task, $this->fisikPerItem);

        $this->dispatch('alert', [
            'type' => 'success',
            'message' => "Count tugas {$task->no_task} selesai — status: {$task->status}",
        ]);

        $this->selectedTaskId = null;
        $this->activeTask = null;
        $this->fisikPerItem = [];
    }

    // ===== Display =====

    public function getSchedulesProperty()
    {
        return CycleCountSchedule::where('cabang_id', session('cabang_id'))
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function getTasksProperty()
    {
        $query = CycleCountTask::query()
            ->where('cabang_id', session('cabang_id'))
            ->with('schedule');

        if ($this->filterStatus !== 'semua') {
            $query->where('status', $this->filterStatus);
        }

        if ($this->search) {
            $query->where('no_task', 'like', '%'.$this->search.'%');
        }

        return $query->orderBy('created_at', 'desc')->paginate($this->perPage);
    }

    public function getRaksProperty(): Collection
    {
        return app(CycleCountService::class)
            ->raksCabang(session('cabang_id'));
    }

    public function getKategorisProperty(): Collection
    {
        $fromMaster = KategoriProduk::where('is_active', true)->pluck('nama');
        $fromProduk = Produk::whereNotNull('kategori')->where('kategori', '!=', '')->distinct()->pluck('kategori');

        return $fromMaster->merge($fromProduk)->filter()->unique()->sort()->values();
    }

    public function render()
    {
        $detailTask = null;
        $mutasiLogs = collect();
        if ($this->showDetailModal && $this->detailTaskId) {
            $detailTask = CycleCountTask::with(['schedule', 'cabang', 'pembuat', 'approver'])
                ->where('cabang_id', session('cabang_id'))
                ->find($this->detailTaskId);

            $mutasiLogs = StokLog::with(['gudang', 'produk', 'skuVariant', 'user'])
                ->where('referensi_tipe', CycleCountTask::class)
                ->where('referensi_id', $this->detailTaskId)
                ->orderBy('created_at')
                ->get();
        }

        return view('modules.wms.livewire.cycle-count', [
            // Legacy computed (`getSchedulesProperty()`) diakses sebagai `$this->schedules`,
            // bukan `$this->schedulesProperty` — nama property literal tidak ada di komponen.
            'schedules' => $this->schedules,
            'tasks' => $this->tasks,
            'raks' => $this->raks,
            'kategoris' => $this->kategoris,
            'detailTask' => $detailTask,
            'mutasiLogs' => $mutasiLogs,
        ])->layout('layouts.backoffice', ['header' => 'Cycle Count Otomatis']);
    }
}
