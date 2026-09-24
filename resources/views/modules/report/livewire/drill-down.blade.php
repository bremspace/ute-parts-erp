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

            return $v;
        };
    @endphp
    <nav class="flex items-center gap-2 text-xs">
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

    @if($detail && !empty($detail))
        <!-- Detail View -->
        <x-prism.glass-card title="Detail: {{ $currentModel }} #{{ $selectedItemId }}">
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                @foreach($detail as $key => $value)
                    @if(!is_array($value) && !is_object($value))
                        <div class="p-3 rounded-xl bg-white/[0.03] border border-white/5">
                            <p class="text-[10px] uppercase text-ink-400 font-bold">{{ str_replace('_', ' ', $key) }}</p>
                            <p class="text-sm font-semibold text-white tabular-nums mt-1">{{ $fmtVal($value, $key) }}</p>
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
                                        <td class="py-1.5 px-3 tabular-nums">{{ is_array($val) || is_object($val) ? '-' : $fmtVal($val, $key) }}</td>
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
