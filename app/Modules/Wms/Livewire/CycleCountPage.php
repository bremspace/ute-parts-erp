<?php

namespace App\Modules\Wms\Livewire;

use App\Modules\Wms\Models\CycleCountSchedule;
use App\Modules\Wms\Models\CycleCountTask;
use App\Modules\Wms\Services\CycleCountService;
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

    public int $targetId = 0;

    public string $targetKategori = '';

    public string $frekuensi = 'mingguan';

    public int $hari = 1;

    public string $jam = '08:00';

    public int $sampleSize = 10;

    public int $thresholdUnit = 5;

    public int $thresholdPersen = 10;

    // Count form
    public ?int $selectedTaskId = null;

    public array $fisikPerItem = [];

    protected $listeners = ['taskGenerated', 'countSubmitted'];

    public function mount(): void
    {
        // Ensure cabang_id session
        if (! session('cabang_id')) {
            $this->dispatch('alert', ['type' => 'error', 'message' => 'Cabang aktif belum dipilih.']);
        }
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
        $this->thresholdUnit = 5;
        $this->thresholdPersen = 10;
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

        CycleCountSchedule::create([
            'cabang_id' => $cabangId,
            'nama' => $this->scheduleNama,
            'tipe_target' => $this->tipeTarget,
            'target_id' => $this->targetId ?: null,
            'target_kategori' => $this->targetKategori ?: null,
            'frekuensi' => $this->frekuensi,
            'hari' => $this->hari,
            'jam' => $this->jam,
            'sample_size' => $this->sampleSize,
            'threshold_unit' => $this->thresholdUnit,
            'threshold_persen' => $this->thresholdPersen,
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
        $tasks = $service->jalankanHarian();

        $this->dispatch('alert', ['type' => 'success', 'message' => $tasks->count().' task cycle count dibuat.']);
    }

    // ===== Count =====

    public function mulaiCount(int $taskId): void
    {
        $task = CycleCountTask::where('cabang_id', session('cabang_id'))
            ->where('status', 'menunggu_count')
            ->findOrFail($taskId);

        $this->selectedTaskId = $taskId;
        $this->fisikPerItem = [];

        // Initialize fisikPerItem dari sample_items
        foreach ($task->sample_items ?? [] as $item) {
            $this->fisikPerItem[$item['stok_item_id']] = '';
        }
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

    public function render()
    {
        return view('modules.wms.livewire.cycle-count', [
            // Legacy computed (`getSchedulesProperty()`) diakses sebagai `$this->schedules`,
            // bukan `$this->schedulesProperty` — nama property literal tidak ada di komponen.
            'schedules' => $this->schedules,
            'tasks' => $this->tasks,
            'raks' => $this->raks,
        ])->layout('layouts.backoffice', ['header' => 'Cycle Count Otomatis']);
    }
}
