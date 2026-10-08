<div class="space-y-6">
    <!-- Breadcrumbs -->
    @php
        // [P2] Crumb "current" harus = model yang datanya benar-benar tampil
        // (currentModel), bukan crumb terakhir — anak di depan rantai belum dimuat
        // sehingga dilabeli biasa dulu; jadi "current" setelah datanya termuat.
        $currentCrumbIdx = null;
        foreach ($breadcrumbs as $ci => $cb) {
            if (($cb['model'] ?? null) === $currentModel) {
                $currentCrumbIdx = $ci;
                break;
            }
        }
        if ($currentCrumbIdx === null && $breadcrumbs !== []) {
            // Fallback: currentModel tak ada di rantai (breadcrumbs usang) → crumb terakhir
            $currentCrumbIdx = count($breadcrumbs) - 1;
        }

        // [ADR 0011] Pemisah ribuan Indonesia (titik) — pola $fmt riwayat-aktivitas.
        // id / *_id / kode / sku / barcode / no_* / *_persen tetap mentah (bukan nominal).
        $fmtVal = function ($v, $key) {
            if (is_bool($v)) {
                return $v ? 'Ya' : 'Tidak';
            }
            $key = (string) $key;
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
            if (is_string($v) && preg_match('/^\d{4}-\d{2}-\d{2}[T\s]\d{2}:\d{2}:\d{2}/', $v)) {
                return date('d/m/Y H:i', strtotime($v));
            }
            if (is_string($v) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $v)) {
                return date('d/m/Y', strtotime($v));
            }

            return $v;
        };
    @endphp
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <!-- Breadcrumbs -->
        <nav class="flex items-center gap-2 text-xs overflow-x-auto scrollbar-none py-1 flex-nowrap">
            @foreach($breadcrumbs as $i => $crumb)
                @if($i === $currentCrumbIdx)
                    <span class="text-white font-semibold" aria-current="page">{{ $crumb['label'] }}</span>
                @elseif($i < count($breadcrumbs) - 1)
                    <button wire:click="drillDown('{{ $crumb['model'] }}')" class="text-up-primary hover:underline">
                        {{ $crumb['label'] }}
                    </button>
                @else
                    <!-- Anak berikutnya: tampil sebagai label, jadi current hanya setelah datanya dimuat -->
                    <span class="text-ink-400">{{ $crumb['label'] }}</span>
                @endif
                @if($i < count($breadcrumbs) - 1)
                    <span class="text-ink-500">/</span>
                @endif
            @endforeach
            @if(count($breadcrumbs) === 0)
                <span class="text-ink-400">Pilih sumber data</span>
            @endif
        </nav>

        <!-- Selector Sumber Data & Filter Periode -->
        <div class="flex flex-wrap items-center gap-2 self-start sm:self-auto">
            <div class="flex items-center gap-1.5">
                <label for="select-source-model" class="text-xs text-ink-400 font-semibold whitespace-nowrap">Sumber:</label>
                <select id="select-source-model" wire:change="drillDown($event.target.value)" class="p-1.5 px-2.5 rounded-lg bg-white/5 border border-white/10 text-white text-xs focus:border-up-primary outline-none cursor-pointer">
                    @foreach(\App\Modules\Report\Services\ReportBuilderService::MODEL_WHITELIST as $modelKey => $modelLabel)
                        <option value="{{ $modelKey }}" class="bg-surface-800 text-white" @selected($currentModel === $modelKey)>{{ $modelLabel }}</option>
                    @endforeach
                </select>
            </div>

            <div class="flex items-center gap-1.5">
                <span class="text-xs text-ink-400 font-semibold whitespace-nowrap">Periode:</span>
                <input type="date" wire:model.live="periodeDari" title="Dari Tanggal" class="p-1.5 px-2 rounded-lg bg-white/5 border border-white/10 text-white text-xs focus:border-up-primary outline-none" />
                <span class="text-ink-500 text-xs">-</span>
                <input type="date" wire:model.live="periodeSampai" title="Sampai Tanggal" class="p-1.5 px-2 rounded-lg bg-white/5 border border-white/10 text-white text-xs focus:border-up-primary outline-none" />
                @if($periodeDari || $periodeSampai)
                    <button type="button" wire:click="resetPeriode" title="Reset filter periode" class="p-1.5 px-2 rounded-lg bg-white/10 hover:bg-white/15 text-ink-300 text-xs font-semibold cursor-pointer">✕</button>
                @endif
            </div>
        </div>
    </div>

    @if($detail && !empty($detail))
        <!-- Detail View -->
        <x-prism.glass-card title="Detail: {{ \App\Modules\Report\Services\ReportBuilderService::MODEL_WHITELIST[$currentModel] ?? $currentModel }} #{{ $selectedItemId }}">
            <div class="flex items-center justify-between gap-2 mb-4">
                <button wire:click="back" class="text-xs text-up-primary hover:underline font-semibold cursor-pointer">← Kembali ke daftar</button>
                @can('laporan.cabang')
                    <div class="flex items-center gap-2">
                        <button type="button" wire:click="exportLaporan('xlsx')" class="px-3 py-1.5 rounded-xl bg-white/5 hover:bg-white/10 border border-white/10 text-ink-200 font-bold text-[11px] whitespace-nowrap cursor-pointer transition-[transform,background-color] active:scale-[0.97]">Export Detail Excel</button>
                        <button type="button" wire:click="exportLaporan('csv')" class="px-3 py-1.5 rounded-xl bg-white/5 hover:bg-white/10 border border-white/10 text-ink-200 font-bold text-[11px] whitespace-nowrap cursor-pointer transition-[transform,background-color] active:scale-[0.97]">Export Detail CSV</button>
                    </div>
                @endcan
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                @foreach($detail as $key => $value)
                    @if(!is_array($value) && !is_object($value) && !in_array($key, \App\Modules\Report\Services\ReportBuilderService::SENSITIVE_EXPORT_COLUMNS, true))
                        <div class="p-3 rounded-xl bg-white/[0.03] border border-white/5">
                            <p class="text-[10px] uppercase text-ink-400 font-bold">{{ \App\Modules\Report\Services\ReportBuilderService::getColumnLabel((string) $key, $currentModel) }}</p>
                            <p class="text-sm font-semibold text-white tabular-nums mt-1">{{ $fmtVal($value, $key) }}</p>
                        </div>
                    @endif
                @endforeach
            </div>

            @if(!empty($childItems) && $childModel)
                <!-- Rincian Child Items (mis. TransaksiItem, TiketServisItem, PurchaseOrderItem) -->
                <div class="mt-6 pt-4 border-t border-white/10">
                    <h4 class="text-sm font-bold text-white mb-3">
                        Rincian {{ \App\Modules\Report\Services\ReportBuilderService::MODEL_WHITELIST[$childModel] ?? $childModel }} ({{ count($childItems) }})
                    </h4>
                    <x-prism.dual-scroll>
                        <table class="w-full text-xs text-left border-collapse">
                            <thead>
                                <tr class="bg-white/5">
                                    @foreach($childItems[0] ?? [] as $cKey => $cVal)
                                        @if(!in_array($cKey, \App\Modules\Report\Services\ReportBuilderService::SENSITIVE_EXPORT_COLUMNS, true))
                                            <th class="py-2 px-3 font-semibold text-ink-400 uppercase text-[10px]">{{ \App\Modules\Report\Services\ReportBuilderService::getColumnLabel((string) $cKey, $childModel) }}</th>
                                        @endif
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-white/5">
                                @foreach($childItems as $cRow)
                                    <tr class="hover:bg-white/5 transition">
                                        @foreach($cRow as $cKey => $cVal)
                                            @if(!in_array($cKey, \App\Modules\Report\Services\ReportBuilderService::SENSITIVE_EXPORT_COLUMNS, true))
                                                <td class="py-1.5 px-3 tabular-nums">{{ is_array($cVal) || is_object($cVal) ? '-' : $fmtVal($cVal, $cKey) }}</td>
                                            @endif
                                        @endforeach
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </x-prism.dual-scroll>
                </div>
            @endif

            <div class="mt-4">
                <button wire:click="back" class="text-xs text-up-primary hover:underline font-semibold cursor-pointer">← Kembali ke daftar</button>
            </div>
        </x-prism.glass-card>
    @else
        <!-- List View -->
        <x-prism.glass-card title="{{ \App\Modules\Report\Services\ReportBuilderService::MODEL_WHITELIST[$currentModel] ?? $currentModel }}" :subtitle="count($items) . ' item ditemukan'">
            <div class="flex items-center gap-2 mb-3 overflow-x-auto scrollbar-none py-1 -my-1 flex-nowrap">
                @can('laporan.cabang')
                    <button type="button" wire:click="exportLaporan('xlsx')" class="px-3 py-2 rounded-xl bg-white/5 hover:bg-white/10 border border-white/10 text-ink-200 font-bold text-[11px] whitespace-nowrap cursor-pointer transition-[transform,background-color] active:scale-[0.97] min-h-[44px]">Export Excel</button>
                    <button type="button" wire:click="exportLaporan('csv')" class="px-3 py-2 rounded-xl bg-white/5 hover:bg-white/10 border border-white/10 text-ink-200 font-bold text-[11px] whitespace-nowrap cursor-pointer transition-[transform,background-color] active:scale-[0.97] min-h-[44px]">Export CSV</button>
                @endcan
            </div>
            @if(count($items) > 0)
                <x-prism.dual-scroll>
                    <table class="w-full text-xs text-left border-collapse">
                        <thead>
                            <tr class="bg-white/5">
                                @foreach($items[0] ?? [] as $key => $val)
                                    @if(!in_array($key, \App\Modules\Report\Services\ReportBuilderService::SENSITIVE_EXPORT_COLUMNS, true))
                                        <th class="py-2 px-3 font-semibold text-ink-400 uppercase text-[10px]">{{ \App\Modules\Report\Services\ReportBuilderService::getColumnLabel((string) $key, $currentModel) }}</th>
                                    @endif
                                @endforeach
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-white/5">
                            @foreach($items as $item)
                                <tr class="hover:bg-white/5 cursor-pointer transition" wire:click="drillDown('{{ $currentModel }}', {{ $item['id'] ?? 0 }})">
                                    @foreach($item as $key => $val)
                                        @if(!in_array($key, \App\Modules\Report\Services\ReportBuilderService::SENSITIVE_EXPORT_COLUMNS, true))
                                            <td class="py-1.5 px-3 tabular-nums">{{ is_array($val) || is_object($val) ? '-' : $fmtVal($val, $key) }}</td>
                                        @endif
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </x-prism.dual-scroll>
            @else
                <div class="text-center py-8 text-ink-500 text-sm">
                    Tidak ada data. Pilih sumber data dari atas.
                </div>
            @endif
        </x-prism.glass-card>
    @endif
</div>
