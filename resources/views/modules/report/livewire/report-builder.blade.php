<!-- [P2-4] Root tunggal — 2 glass-card sebelumnya = multi-root (Livewire 3 error saat savedReports tampil) -->
<div class="space-y-6">
<x-prism.glass-card title="Laporan Kustom" subtitle="Buat laporan dari sumber data terdaftar">
    <div class="space-y-4">
        <!-- Source Model Selection -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="text-xs font-bold text-ink-400 uppercase tracking-wider">Sumber Data</label>
                <select wire:model.live="sourceModel" class="w-full mt-1 p-2 rounded-lg bg-white/5 border border-white/10 text-white text-sm focus:border-up-primary outline-none">
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
                        <div class="flex flex-col sm:flex-row gap-2 p-2 sm:p-0 rounded-xl bg-white/[0.02] sm:bg-transparent border sm:border-0 border-white/5">
                            <select wire:model="filters.{{ $i }}.field"
                                class="flex-1 p-2 sm:p-1.5 rounded-lg bg-white/5 border border-white/10 text-white text-xs focus:border-up-primary outline-none min-h-[38px]">
                                <option value="">-- Pilih Kolom --</option>
                                @foreach($availableColumns as $field => $label)
                                    <option value="{{ $field }}">{{ $label }}</option>
                                @endforeach
                            </select>
                            <select wire:model="filters.{{ $i }}.op"
                                class="w-full sm:w-28 p-2 sm:p-1.5 rounded-lg bg-white/5 border border-white/10 text-white text-xs focus:border-up-primary outline-none min-h-[38px]">
                                @foreach(\App\Modules\Report\Services\ReportBuilderService::FILTER_OPERATORS as $op)
                                    <option value="{{ $op }}">{{ $op }}</option>
                                @endforeach
                            </select>
                            <div class="flex items-center gap-2 flex-1">
                                <input type="text" wire:model="filters.{{ $i }}.value" placeholder="Nilai filter..."
                                    class="flex-1 p-2 sm:p-1.5 rounded-lg bg-white/5 border border-white/10 text-white text-xs focus:border-up-primary outline-none min-h-[38px]">
                                <button type="button" wire:click="removeFilter({{ $i }})" title="Hapus Filter" class="text-up-red hover:bg-up-red/10 p-2 rounded-lg text-sm transition min-h-[38px] min-w-[38px] flex items-center justify-center">✕</button>
                            </div>
                        </div>
                    @endforeach
                </div>
                <button type="button" wire:click="addFilter" class="inline-flex items-center gap-1 text-xs text-up-primary hover:underline mt-1 font-semibold cursor-pointer">
                    <span>+</span>
                    <span>Tambah Filter</span>
                </button>
            </div>

            <!-- Group By -->
            <div>
                <label class="text-xs font-bold text-ink-400 uppercase tracking-wider">Group By (Pengelompokan)</label>
                <select wire:model="groupBy" multiple class="w-full mt-1 p-2 rounded-lg bg-white/5 border border-white/10 text-white text-xs focus:border-up-primary outline-none h-24">
                    @foreach($availableColumns as $field => $label)
                        <option value="{{ $field }}" class="p-1">{{ $label }}</option>
                    @endforeach
                </select>
                <p class="text-[10px] text-ink-500 mt-1">Tahan tombol Ctrl (Windows) atau Cmd (Mac) untuk memilih lebih dari satu pengelompokan.</p>
            </div>

            <!-- Actions -->
            <div class="flex items-center gap-2 overflow-x-auto scrollbar-none py-1 -my-1 flex-wrap sm:flex-nowrap">
                <x-prism.prism-button variant="primary" size="sm" wire:click="buildAndShow" wire:loading.attr="disabled" class="whitespace-nowrap min-h-[44px]">
                    <span wire:loading.remove wire:target="buildAndShow">Tampilkan Data</span>
                    <span wire:loading wire:target="buildAndShow">Memuat...</span>
                </x-prism.prism-button>
                <x-prism.prism-button variant="accent" size="sm" wire:click="saveReport" class="whitespace-nowrap min-h-[44px]">
                    Simpan Laporan
                </x-prism.prism-button>

                @can('laporan.cabang')
                    <div class="flex items-center gap-1 bg-white/[0.03] border border-white/10 rounded-xl p-0.5 min-h-[44px]">
                        <select wire:model="exportFormat" class="px-2.5 py-1.5 rounded-lg bg-transparent text-white text-xs focus:outline-none">
                            <option value="xlsx" class="bg-ink-900">Excel (.xlsx)</option>
                            <option value="csv" class="bg-ink-900">CSV (.csv)</option>
                        </select>
                        <button type="button" wire:click="export" wire:loading.attr="disabled" class="px-3 py-1.5 rounded-lg bg-white/10 hover:bg-white/15 text-white font-semibold text-xs whitespace-nowrap cursor-pointer transition active:scale-95 flex items-center gap-1.5">
                            <span wire:loading.remove wire:target="export">📥 Export</span>
                            <span wire:loading wire:target="export">Mengunduh...</span>
                        </button>
                    </div>
                @endcan

                <!-- [P2-4] Bagikan laporan tersimpan ke cabang aktif (default aktif) -->
                <label class="flex items-center gap-1.5 text-xs bg-white/5 border border-white/10 rounded-xl px-3 py-2 cursor-pointer hover:border-up-primary transition whitespace-nowrap min-h-[44px]">
                    <input type="checkbox" wire:model="shared" class="accent-up-primary">
                    <span class="text-ink-300">Bagikan ke cabang</span>
                </label>
            </div>
        @endif

        <!-- Results -->
        @if($showResults && count($queryResults) > 0)
            @php
                $fmtCell = function ($v, $key) {
                    if (is_bool($v)) {
                        return $v ? 'Ya' : 'Tidak';
                    }
                    if (is_array($v) || is_object($v)) {
                        return '-';
                    }
                    $key = (string) $key;
                    $kosong = ($v === null || $v === '');
                    // 1. Cek dulu di COLUMN_LABELS yang sudah didefinisikan di ReportBuilderService
                    // HANYA kembalikan label jika nilai $v kosong/null (belum ada data).
                    // Jika ada data asli, tampilkan data tersebut agar tidak menimpa nilai nyata dengan nama header.
                    $label = \App\Modules\Report\Services\ReportBuilderService::COLUMN_LABELS[$key] ?? null;
                    if ($label && $kosong) {
                        return $label;
                    }
                    // 2. Untuk kolom _id tanpa label eksplisit, coba mapping nama relasi statis
                    // HANYA sebagai placeholder saat nilai kosong — bila ada data (sudah diterjemahkan
                    // formatRowsForDisplay menjadi nama entitas), tampilkan data tersebut.
                    if ($kosong && str_ends_with($key, '_id')) {
                        $modelShort = str_replace('_id', '', $key);
                        $modelLabels = [
                            'cabang' => 'Cabang',
                            'gudang' => 'Gudang',
                            'pelanggan' => 'Pelanggan',
                            'supplier' => 'Supplier',
                            'produk' => 'Produk',
                            'akun' => 'Akun COA',
                            'rak' => 'Lokasi Rak',
                            'kategori' => 'Kategori',
                            'brand' => 'Brand',
                            'kualitas' => 'Kualitas',
                            'tier' => 'Tier Pelanggan',
                        ];
                        if (isset($modelLabels[$modelShort])) {
                            return $modelLabels[$modelShort];
                        }
                    }
                    $bukanNominal = $key === 'id'
                        || str_ends_with($key, '_id')
                        || $key === 'kode'
                        || str_ends_with($key, '_kode')
                        || $key === 'sku'
                        || $key === 'barcode'
                        || str_starts_with($key, 'no_')
                        || str_ends_with($key, '_persen');
                    if (! $bukanNominal && is_numeric($v)) {
                        return number_format((float) $v, 0, ',', '.');
                    }
                    return $v ?? '-';
                };
            @endphp
            <div class="mt-4 space-y-3">
                <div class="flex items-center justify-between text-xs text-ink-400 pb-1 border-b border-white/5">
                    <span>Menampilkan <strong>{{ count($queryResults) }}</strong> baris data (maks. 500)</span>
                    <span class="text-[11px] font-mono text-ink-500">{{ count($resultColumns) }} kolom</span>
                </div>

                {{-- Desktop & Tablet: Wide Dual-Scroll Table --}}
                <div class="hidden sm:block">
                    <x-prism.dual-scroll>
                        <table class="w-full text-xs text-left border-collapse">
                            <thead>
                                <tr class="bg-white/5">
                                    @foreach($resultColumns as $col)
                                        <th class="py-2.5 px-3 font-semibold text-ink-400 uppercase text-[10px] whitespace-nowrap">{{ $availableColumns[$col] ?? \App\Modules\Report\Services\ReportBuilderService::COLUMN_LABELS[$col] ?? str_replace('_', ' ', $col) }}</th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-white/5">
                                @foreach($queryResults as $row)
                                    <tr class="hover:bg-white/5 transition">
                                        @foreach($resultColumns as $col)
                                            <td class="py-2 px-3 tabular-nums whitespace-nowrap">{{ $fmtCell($row[$col] ?? null, $col) }}</td>
                                        @endforeach
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </x-prism.dual-scroll>
                </div>

                {{-- Mobile Phone: Responsive Card Fallback --}}
                <div class="block sm:hidden space-y-2.5">
                    @foreach($queryResults as $rIdx => $row)
                        <div class="p-3 rounded-xl bg-white/[0.03] border border-white/5 space-y-1.5 text-xs">
                            <div class="flex items-center justify-between pb-1 border-b border-white/5 font-mono text-[10px] text-ink-400">
                                <span>#{{ $rIdx + 1 }}</span>
                                @if(isset($row['id']))
                                    <span>ID: {{ $row['id'] }}</span>
                                @endif
                            </div>
                            <div class="grid grid-cols-2 gap-x-2 gap-y-1 text-xs">
                                @foreach($resultColumns as $col)
                                    @if($col !== 'id')
                                        <div>
                                            <div class="text-[9px] uppercase font-bold text-ink-500 truncate">{{ $availableColumns[$col] ?? \App\Modules\Report\Services\ReportBuilderService::COLUMN_LABELS[$col] ?? str_replace('_', ' ', $col) }}</div>
                                            <div class="text-white font-medium tabular-nums truncate">{{ $fmtCell($row[$col] ?? null, $col) }}</div>
                                        </div>
                                    @endif
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        @if($showResults && count($queryResults) === 0)
            <div class="text-center py-10 text-ink-500 text-xs space-y-2">
                <svg class="w-8 h-8 mx-auto opacity-30 text-ink-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
                <p>Tidak ada data ditemukan untuk kriteria dan filter yang dipilih.</p>
            </div>
        @endif
    </div>
</x-prism.glass-card>

<!-- Saved Reports List -->
@if($savedReports->isNotEmpty())
    <x-prism.glass-card title="Laporan Tersimpan" subtitle="Daftar laporan yang dapat diakses">
        <div class="space-y-2">
            @foreach($savedReports as $report)
                @php
                    $user = auth()->user();
                    $isOwner = (int) $report->user_id === (int) auth()->id();
                    $canDelete = $isOwner
                        || ($user && $user->hasRole('super-admin'))
                        || ($user && $user->hasRole('admin-toko') && (int) $report->cabang_id === (int) session('cabang_id'));
                @endphp
                <div class="flex items-center justify-between py-2 border-b border-white/5 last:border-0">
                    <div>
                        <div class="flex items-center gap-2">
                            <p class="text-xs font-semibold text-white">{{ $report->name }}</p>
                            @if($isOwner)
                                <span class="px-1.5 py-0.5 rounded bg-up-primary/20 text-up-primary text-[9px] font-bold">Milik Saya</span>
                            @elseif($report->shared)
                                <span class="px-1.5 py-0.5 rounded bg-up-mint/20 text-up-mint text-[9px] font-bold">Dibagikan Cabang</span>
                            @endif
                        </div>
                        <p class="text-[10px] text-ink-500 mt-0.5">
                            {{ app(\App\Modules\Report\Services\ReportBuilderService::class)->getModelLabel($report->source_model) }} ·
                            {{ $report->created_at->format('d/m/Y H:i') }}
                        </p>
                    </div>
                    <div class="flex gap-2 items-center">
                        <button wire:click="drillDown('{{ $report->source_model }}')" class="text-xs text-up-primary hover:underline font-semibold cursor-pointer">Lihat</button>
                        @if($canDelete)
                            <button wire:click="deleteReport({{ $report->id }})" class="text-xs text-up-red hover:underline font-semibold cursor-pointer" onclick="confirm('Yakin ingin menghapus laporan tersimpan ini?') || event.stopImmediatePropagation()">Hapus</button>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    </x-prism.glass-card>
@endif
</div>
