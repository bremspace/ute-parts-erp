<div class="space-y-6">
    <!-- [F3-7] Cycle Count Otomatis (G-18) -->
    <x-prism.glass-card title="Cycle Count Otomatis" subtitle="Jadwal & tugas hitung stok berkala">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-lg font-semibold text-ink-100">Jadwal</h3>
            @can('wms.approve-opname')
                <button wire:click="bukaFormSchedule" class="px-4 py-2 bg-teal-600 hover:bg-teal-700 text-white rounded-lg text-sm transition">
                    + Jadwal Baru
                </button>
            @endcan
        </div>

        {{-- Form tambah jadwal --}}
        @if($showScheduleForm)
            <div class="p-4 rounded-xl bg-white/[0.05] border border-white/10 space-y-3 mb-4">
                <input type="text" wire:model="scheduleNama" placeholder="Nama jadwal" class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-ink-100 text-sm">
                <div class="grid grid-cols-2 gap-3">
                    <select wire:model="tipeTarget" class="px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-ink-100 text-sm">
                        <option value="rak">Per Rak</option>
                        <option value="kategori">Per Kategori</option>
                    </select>
                    <select wire:model="frekuensi" class="px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-ink-100 text-sm">
                        <option value="mingguan">Mingguan</option>
                        <option value="bulanan">Bulanan</option>
                    </select>
                </div>
                <div class="grid grid-cols-3 gap-3">
                    <input type="number" wire:model="sampleSize" placeholder="Sample size" class="px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-ink-100 text-sm">
                    <input type="number" wire:model="thresholdUnit" placeholder="Threshold unit" class="px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-ink-100 text-sm">
                    <input type="number" wire:model="thresholdPersen" placeholder="Threshold %" class="px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-ink-100 text-sm">
                </div>
                <div class="flex gap-2">
                    <button wire:click="simpanSchedule" class="px-4 py-2 bg-teal-600 text-white rounded-lg text-sm">Simpan</button>
                    <button wire:click="showScheduleForm=false" class="px-4 py-2 bg-white/10 text-ink-300 rounded-lg text-sm">Batal</button>
                </div>
            </div>
        @endif

        <div class="space-y-2">
            @foreach($schedules as $schedule)
                <div class="flex items-center justify-between p-3 rounded-lg bg-white/[0.03] border border-white/5">
                    <div>
                        <span class="font-medium text-ink-100">{{ $schedule->nama }}</span>
                        <span class="text-xs text-ink-400 ml-2">{{ $schedule->frekuensi }} | {{ $schedule->jam }}</span>
                    </div>
                    @can('wms.approve-opname')
                        <button wire:click="jalankanSchedule({{ $schedule->id }})" class="px-3 py-1 bg-blue-600 hover:bg-blue-700 text-white rounded text-xs">Jalankan</button>
                    @endcan
                </div>
            @endforeach
        </div>
    </x-prism.glass-card>

    <x-prism.glass-card title="Tugas Cycle Count" subtitle="Hasil count dan status persetujuan">
        <div class="flex gap-2 mb-4">
            <input type="text" wire:model="search" placeholder="Cari tugas..." class="flex-1 px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-ink-100 text-sm">
            <select wire:model="filterStatus" class="px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-ink-100 text-sm">
                <option value="semua">Semua</option>
                <option value="menunggu_count">Menunggu Count</option>
                <option value="menunggu_approval">Menunggu Approval</option>
                <option value="selesai">Selesai</option>
                <option value="ditolak">Ditolak</option>
            </select>
        </div>

        @foreach($tasks as $task)
            <div class="p-3 rounded-lg bg-white/[0.03] border border-white/5 mb-2">
                <div class="flex justify-between items-center">
                    <div>
                        <span class="font-mono text-sm text-ink-100">{{ $task->no_task }}</span>
                        <span class="text-xs text-ink-400 ml-2">
                            <x-prism.status-pill :status="$task->status" />
                        </span>
                    </div>
                    @if($task->status === 'menunggu_count')
                        <button wire:click="mulaiCount({{ $task->id }})" class="px-3 py-1 bg-teal-600 hover:bg-teal-700 text-white rounded text-xs">Input Count</button>
                    @endif
                </div>
            </div>
        @endforeach

        {{-- Count form --}}
        @if($selectedTaskId)
            <div class="mt-4 p-4 rounded-xl bg-white/[0.05] border border-white/10 space-y-3">
                <h4 class="font-semibold text-ink-100">Input Stok Fisik</h4>
                @foreach($tasks->where('id', $selectedTaskId)->first()->sample_items ?? [] as $item)
                    <div class="flex items-center gap-3">
                        <span class="text-sm text-ink-200 w-48">{{ $item['nama'] ?? 'Produk #' . $item['stok_item_id'] }}</span>
                        <input type="number" wire:model="fisikPerItem.{{ $item['stok_item_id'] }}" placeholder="Qty fisik" class="w-32 px-3 py-1 bg-white/5 border border-white/10 rounded-lg text-ink-100 text-sm">
                        <span class="text-xs text-ink-400">Sistem: {{ $item['stok_sistem'] ?? '-' }}</span>
                    </div>
                @endforeach
                <button wire:click="submitCount" class="px-4 py-2 bg-teal-600 hover:bg-teal-700 text-white rounded-lg text-sm">Kirim Count</button>
            </div>
        @endif
    </x-prism.glass-card>
</div>
