<div class="space-y-6">
    <!-- Breadcrumbs -->
    <nav class="flex items-center gap-2 text-xs">
        @foreach($breadcrumbs as $i => $crumb)
            @if($i < count($breadcrumbs) - 1)
                <button wire:click="drillDown('{{ $crumb['model'] }}')" class="text-up-primary hover:underline">
                    {{ $crumb['label'] }}
                </button>
                <span class="text-ink-500">/</span>
            @else
                <span class="text-white font-semibold">{{ $crumb['label'] }}</span>
            @endif
        @endforeach
        @if(count($breadcrumbs) === 0)
            <span class="text-ink-400">Pilih sumber data</span>
        @endif
    </nav>

    @if($detail && !empty($detail))
        <!-- Detail View -->
        <x-prism.glass-card title="Detail: {{ $currentModel }} #{{ $selectedItemId }}">
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                @foreach($detail as $key => $value)
                    @if(!is_array($value) && !is_object($value))
                        <div class="p-3 rounded-xl bg-white/[0.03] border border-white/5">
                            <p class="text-[10px] uppercase text-ink-400 font-bold">{{ str_replace('_', ' ', $key) }}</p>
                            <p class="text-sm font-semibold text-white tabular-nums mt-1">{{ $value }}</p>
                        </div>
                    @endif
                @endforeach
            </div>
            <div class="mt-3">
                <button wire:click="back" class="text-xs text-up-primary hover:underline font-semibold">← Kembali ke daftar</button>
            </div>
        </x-prism.glass-card>
    @else
        <!-- List View -->
        <x-prism.glass-card title="{{ \App\Modules\Report\Services\ReportBuilderService::MODEL_WHITELIST[$currentModel] ?? $currentModel }}" :subtitle="count($items) . ' item ditemukan'">
            @if($currentModel === 'Transaksi')
                <!-- [F2-5] Export laporan transaksi (queue) -->
                <div class="flex items-center gap-2 mb-3">
                    @can('laporan.cabang')
                        <button type="button" wire:click="exportLaporan('xlsx')" class="px-3 py-2 rounded-xl bg-white/5 hover:bg-white/10 border border-white/10 text-ink-200 font-bold text-[11px] whitespace-nowrap cursor-pointer transition-all">Export Excel</button>
                        <button type="button" wire:click="exportLaporan('csv')" class="px-3 py-2 rounded-xl bg-white/5 hover:bg-white/10 border border-white/10 text-ink-200 font-bold text-[11px] whitespace-nowrap cursor-pointer transition-all">Export CSV</button>
                    @endcan
                </div>
            @endif
            @if(count($items) > 0)
                <div class="overflow-x-auto">
                    <table class="w-full text-xs text-left border-collapse">
                        <thead>
                            <tr class="bg-white/5">
                                @foreach($items[0] ?? [] as $key => $val)
                                    <th class="py-2 px-3 font-semibold text-ink-400 uppercase text-[10px]">{{ str_replace('_', ' ', $key) }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-white/5">
                            @foreach($items as $item)
                                <tr class="hover:bg-white/5 cursor-pointer transition" wire:click="drillDown('{{ $currentModel }}', {{ $item['id'] ?? 0 }})">
                                    @foreach($item as $key => $val)
                                        <td class="py-1.5 px-3 tabular-nums">{{ is_array($val) || is_object($val) ? '-' : $val }}</td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="text-center py-8 text-ink-500 text-sm">
                    Tidak ada data. Pilih sumber data dari atas.
                </div>
            @endif
        </x-prism.glass-card>
    @endif
</div>
