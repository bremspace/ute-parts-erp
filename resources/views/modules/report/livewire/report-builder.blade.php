<!-- [P2-4] Root tunggal — 2 glass-card sebelumnya = multi-root (Livewire 3 error saat savedReports tampil) -->
<div class="space-y-6">
<x-prism.glass-card title="Laporan Kustom" subtitle="Buat laporan dari sumber data terdaftar">
    <div class="space-y-4">
        <!-- Source Model Selection -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="text-xs font-bold text-ink-400 uppercase tracking-wider">Sumber Data</label>
                <select wire:model="sourceModel" class="w-full mt-1 p-2 rounded-lg bg-white/5 border border-white/10 text-white text-sm focus:border-up-primary outline-none">
                    <option value="">-- Pilih Model --</option>
                    @foreach($whitelist as $key => $label)
                        <option value="{{ $key }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="text-xs font-bold text-ink-400 uppercase tracking-wider">Nama Laporan</label>
                <input type="text" wire:model="reportName" placeholder="Nama laporan..."
                    class="w-full mt-1 p-2 rounded-lg bg-white/5 border border-white/10 text-white text-sm focus:border-up-primary outline-none">
            </div>
        </div>

        @if($sourceModel)
            <!-- Columns Selection -->
            <div>
                <label class="text-xs font-bold text-ink-400 uppercase tracking-wider">Kolom (pilih semua yang dibutuhkan)</label>
                <div class="flex flex-wrap gap-2 mt-1">
                    @foreach($availableColumns as $field => $label)
                        <label class="flex items-center gap-1 text-xs bg-white/5 border border-white/10 rounded-lg px-3 py-1.5 cursor-pointer hover:border-up-primary transition">
                            <input type="checkbox" wire:model="selectedColumns" value="{{ $field }}" class="accent-up-primary">
                            {{ $label }}
                        </label>
                    @endforeach
                </div>
            </div>

            <!-- Filters — kontrak baris {field, op, value}, field dari whitelist kolom -->
            <div>
                <label class="text-xs font-bold text-ink-400 uppercase tracking-wider">Filter</label>
                <div wire:key="filters" class="space-y-2 mt-1">
                    @foreach($filters as $i => $f)
                        <div class="flex gap-2">
                            <select wire:model="filters.{{ $i }}.field"
                                class="flex-1 p-1.5 rounded bg-white/5 border border-white/10 text-white text-xs focus:border-up-primary outline-none">
                                <option value="">-- Pilih Kolom --</option>
                                @foreach($availableColumns as $field => $label)
                                    <option value="{{ $field }}">{{ $label }}</option>
                                @endforeach
                            </select>
                            <select wire:model="filters.{{ $i }}.op"
                                class="p-1.5 rounded bg-white/5 border border-white/10 text-white text-xs focus:border-up-primary outline-none">
                                @foreach(\App\Modules\Report\Services\ReportBuilderService::FILTER_OPERATORS as $op)
                                    <option value="{{ $op }}">{{ $op }}</option>
                                @endforeach
                            </select>
                            <input type="text" wire:model="filters.{{ $i }}.value" placeholder="Nilai"
                                class="flex-1 p-1.5 rounded bg-white/5 border border-white/10 text-white text-xs focus:border-up-primary outline-none">
                            <button type="button" wire:click="removeFilter({{ $i }})" class="text-up-red text-xs px-2">×</button>
                        </div>
                    @endforeach
                </div>
                <button type="button" wire:click="addFilter" class="text-xs text-up-primary hover:underline mt-1">+ Tambah Filter</button>
            </div>

            <!-- Group By -->
            <div>
                <label class="text-xs font-bold text-ink-400 uppercase tracking-wider">Group By</label>
                <select wire:model="groupBy" multiple class="w-full mt-1 p-2 rounded-lg bg-white/5 border border-white/10 text-white text-sm focus:border-up-primary outline-none h-20">
                    @foreach($availableColumns as $field => $label)
                        <option value="{{ $field }}">{{ $label }}</option>
                    @endforeach
                </select>
                <p class="text-[10px] text-ink-500 mt-0.5">Ctrl+Click untuk multi-select</p>
            </div>

            <!-- Actions -->
            <div class="flex gap-2 flex-wrap">
                <x-prism.prism-button variant="primary" size="sm" wire:click="buildAndShow">
                    Tampilkan Data
                </x-prism.prism-button>
                <x-prism.prism-button variant="accent" size="sm" wire:click="saveReport">
                    Simpan Laporan
                </x-prism.prism-button>
                <!-- [P2-4] Bagikan laporan tersimpan ke cabang aktif (default aktif) -->
                <label class="flex items-center gap-1.5 text-xs bg-white/5 border border-white/10 rounded-lg px-3 py-2 cursor-pointer hover:border-up-primary transition">
                    <input type="checkbox" wire:model="shared" class="accent-up-primary">
                    <span class="text-ink-300">Bagikan ke cabang</span>
                </label>
                <div class="flex items-center gap-2">
                    <select wire:model="exportFormat" class="p-2 rounded bg-white/5 border border-white/10 text-white text-xs focus:border-up-primary outline-none">
                        <option value="xlsx">Excel (.xlsx)</option>
                        <option value="csv">CSV (.csv)</option>
                    </select>
                    <x-prism.prism-button variant="mint" size="sm" wire:click="export" :disabled="$exporting">
                        @if($exporting)
                            <span class="animate-pulse">Processing...</span>
                        @else
                            Export (Queue)
                        @endif
                    </x-prism.prism-button>
                </div>
            </div>
        @endif

        <!-- Results -->
        @if($showResults && count($queryResults) > 0)
            <div class="mt-4 overflow-x-auto">
                <table class="w-full text-xs text-left border-collapse">
                    <thead>
                        <tr class="bg-white/5">
                            @foreach($resultColumns as $col)
                                <th class="py-2 px-3 font-semibold text-ink-400 uppercase text-[10px]">{{ $availableColumns[$col] ?? $col }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-white/5">
                        @foreach($queryResults as $row)
                            <tr class="hover:bg-white/5 transition">
                                @foreach($resultColumns as $col)
                                    <td class="py-1.5 px-3 tabular-nums">{{ $row[$col] ?? '-' }}</td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        @if($showResults && count($queryResults) === 0)
            <div class="text-center py-8 text-ink-500 text-sm">
                Tidak ada data ditemukan untuk parameter yang dipilih.
            </div>
        @endif
    </div>
</x-prism.glass-card>

<!-- Saved Reports List -->
@if($savedReports->isNotEmpty())
    <x-prism.glass-card title="Laporan Tersimpan" subtitle="Milik Anda">
        <div class="space-y-2">
            @foreach($savedReports as $report)
                <div class="flex items-center justify-between py-2 border-b border-white/5 last:border-0">
                    <div>
                        <p class="text-xs font-semibold text-white">{{ $report->name }}</p>
                        <p class="text-[10px] text-ink-500">
                            {{ app(\App\Modules\Report\Services\ReportBuilderService::class)->getModelLabel($report->source_model) }} ·
                            {{ $report->created_at->format('d/m/Y H:i') }}
                        </p>
                    </div>
                    <div class="flex gap-2">
                        <button wire:click="drillDown('{{ $report->source_model }}')" class="text-xs text-up-primary hover:underline">Lihat</button>
                        <button wire:click="deleteReport({{ $report->id }})" class="text-xs text-up-red hover:underline">Hapus</button>
                    </div>
                </div>
            @endforeach
        </div>
    </x-prism.glass-card>
@endif
</div>
