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

            <button type="button" onclick="window.print()" class="p-1.5 px-3 rounded-lg bg-white/5 hover:bg-white/10 border border-white/10 text-ink-300 text-xs font-semibold flex items-center gap-1.5 cursor-pointer transition">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                <span>Cetak ISO</span>
            </button>
        </div>
    </div>

    <!-- [ISO 9001 Clause 7.5 / ISO 27001] ISO Controlled Document Header & Letterhead -->
    @if(!empty($isoMetadata))
        <div class="p-4 rounded-2xl bg-white/[0.04] border border-white/10 backdrop-blur-md shadow-sm">
            <div class="flex flex-col md:flex-row items-start md:items-center justify-between gap-4 pb-3 border-b border-white/10">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-up-primary/20 border border-up-primary/40 flex items-center justify-center text-up-primary font-black text-sm">
                        ISO
                    </div>
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="text-xs font-black tracking-widest uppercase text-up-primary font-mono">{{ $isoMetadata['doc_code'] }}</span>
                            <span class="text-[10px] px-2 py-0.5 rounded-full bg-up-mint/10 text-up-mint border border-up-mint/30 font-bold uppercase tracking-wider">{{ $isoMetadata['status'] }}</span>
                        </div>
                        <h2 class="text-base font-black text-white tracking-tight mt-0.5">{{ $isoMetadata['doc_name'] }}</h2>
                    </div>
                </div>
                <div class="text-right text-[11px] text-ink-400 self-end md:self-auto space-y-0.5">
                    <div>Standar: <span class="font-semibold text-ink-200">{{ $isoMetadata['iso_std'] }}</span></div>
                    <div>Klasifikasi: <span class="font-semibold text-up-amber">{{ $isoMetadata['classification'] }}</span></div>
                </div>
            </div>
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 pt-3 text-xs">
                <div>
                    <span class="block text-[10px] uppercase font-bold text-ink-500">Cabang / Unit Kerja</span>
                    <span class="font-semibold text-white">{{ $isoMetadata['cabang_nama'] }} ({{ $isoMetadata['cabang_kode'] }})</span>
                </div>
                <div>
                    <span class="block text-[10px] uppercase font-bold text-ink-500">Operator Pelapor</span>
                    <span class="font-semibold text-white">{{ $isoMetadata['operator'] }} <span class="text-ink-400 font-normal">({{ $isoMetadata['operator_role'] }})</span></span>
                </div>
                <div>
                    <span class="block text-[10px] uppercase font-bold text-ink-500">Waktu Penarikan Data</span>
                    <span class="font-semibold text-white tabular-nums">{{ $isoMetadata['generated_at'] }}</span>
                </div>
                <div>
                    <span class="block text-[10px] uppercase font-bold text-ink-500">Integritas Data</span>
                    <span class="font-semibold text-up-mint flex items-center gap-1">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        <span>Verified Cabang-Scoped</span>
                    </span>
                </div>
            </div>
        </div>
    @endif

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
                    <div class="hidden sm:block">
                        <x-prism.dual-scroll>
                            <table class="w-full text-xs text-left border-collapse">
                                <thead>
                                    <tr class="bg-white/5 border-b border-white/10">
                                        @foreach($childItems[0] ?? [] as $cKey => $cVal)
                                            @if(!in_array($cKey, \App\Modules\Report\Services\ReportBuilderService::SENSITIVE_EXPORT_COLUMNS, true))
                                                @php
                                                    $isNumericCol = in_array((string) $cKey, ['harga_beli', 'harga_jual', 'harga_satuan', 'subtotal', 'diskon_nominal', 'hpp', 'qty', 'qty_dipesan', 'qty_diterima', 'stok', 'debit', 'kredit'], true);
                                                @endphp
                                                <th class="py-2.5 px-3 font-semibold text-ink-400 uppercase text-[10px] whitespace-nowrap {{ $isNumericCol ? 'text-right' : 'text-left' }}">{{ \App\Modules\Report\Services\ReportBuilderService::getColumnLabel((string) $cKey, $childModel) }}</th>
                                            @endif
                                        @endforeach
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-white/5">
                                    @foreach($childItems as $cRow)
                                        <tr class="hover:bg-white/5 transition">
                                            @foreach($cRow as $cKey => $cVal)
                                                @if(!in_array($cKey, \App\Modules\Report\Services\ReportBuilderService::SENSITIVE_EXPORT_COLUMNS, true))
                                                    @php
                                                        $isNumericCol = in_array((string) $cKey, ['harga_beli', 'harga_jual', 'harga_satuan', 'subtotal', 'diskon_nominal', 'hpp', 'qty', 'qty_dipesan', 'qty_diterima', 'stok', 'debit', 'kredit'], true);
                                                    @endphp
                                                    <td class="py-2 px-3 tabular-nums whitespace-nowrap {{ $isNumericCol ? 'text-right' : 'text-left' }}">{{ is_array($cVal) || is_object($cVal) ? '-' : $fmtVal($cVal, $cKey) }}</td>
                                                @endif
                                            @endforeach
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </x-prism.dual-scroll>
                    </div>

                    <div class="block sm:hidden space-y-2">
                        @foreach($childItems as $cIdx => $cRow)
                            <div class="p-3 rounded-xl bg-white/[0.03] border border-white/5 space-y-1.5 text-xs">
                                <div class="text-[10px] font-mono text-ink-400 pb-1 border-b border-white/5">
                                    Item #{{ $cIdx + 1 }}
                                </div>
                                <div class="grid grid-cols-2 gap-x-2 gap-y-1">
                                    @foreach($cRow as $cKey => $cVal)
                                        @if(!in_array($cKey, \App\Modules\Report\Services\ReportBuilderService::SENSITIVE_EXPORT_COLUMNS, true))
                                            <div>
                                                <div class="text-[9px] uppercase font-bold text-ink-500 truncate">{{ \App\Modules\Report\Services\ReportBuilderService::getColumnLabel((string) $cKey, $childModel) }}</div>
                                                <div class="text-white font-medium tabular-nums truncate">{{ is_array($cVal) || is_object($cVal) ? '-' : $fmtVal($cVal, $cKey) }}</div>
                                            </div>
                                        @endif
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </div>
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
                {{-- Desktop & Tablet View --}}
                <div class="hidden sm:block">
                    <x-prism.dual-scroll>
                        <table class="w-full text-xs text-left border-collapse">
                            <thead>
                                <tr class="bg-white/5 border-b border-white/10">
                                    @foreach($items[0] ?? [] as $key => $val)
                                        @if(!in_array($key, \App\Modules\Report\Services\ReportBuilderService::SENSITIVE_EXPORT_COLUMNS, true))
                                            @php
                                                $isNumCol = in_array((string) $key, [
                                                    'total_akhir', 'total_kotor', 'total_diskon', 'total_pajak', 'subtotal',
                                                    'harga_satuan', 'hpp', 'debit', 'kredit', 'jumlah', 'jumlah_dibayar',
                                                    'sisa', 'total', 'total_dibayar', 'estimasi_biaya', 'biaya_akhir',
                                                    'jumlah_bayar', 'kembalian', 'stok', 'qty', 'qty_dipesan', 'qty_diterima',
                                                    'selisih', 'jumlah_masuk', 'jumlah_keluar'
                                                ], true);
                                            @endphp
                                            <th class="py-2.5 px-3 font-semibold text-ink-400 uppercase text-[10px] whitespace-nowrap {{ $isNumCol ? 'text-right' : 'text-left' }}">{{ \App\Modules\Report\Services\ReportBuilderService::getColumnLabel((string) $key, $currentModel) }}</th>
                                        @endif
                                    @endforeach
                                    <th class="py-2.5 px-3 text-right font-semibold text-ink-400 uppercase text-[10px]">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-white/5">
                                @foreach($items as $item)
                                    <tr class="hover:bg-white/5 cursor-pointer transition" wire:click="drillDown('{{ $currentModel }}', {{ $item['id'] ?? 0 }})">
                                        @foreach($item as $key => $val)
                                            @if(!in_array($key, \App\Modules\Report\Services\ReportBuilderService::SENSITIVE_EXPORT_COLUMNS, true))
                                                @php
                                                    $isNumCol = in_array((string) $key, [
                                                        'total_akhir', 'total_kotor', 'total_diskon', 'total_pajak', 'subtotal',
                                                        'harga_satuan', 'hpp', 'debit', 'kredit', 'jumlah', 'jumlah_dibayar',
                                                        'sisa', 'total', 'total_dibayar', 'estimasi_biaya', 'biaya_akhir',
                                                        'jumlah_bayar', 'kembalian', 'stok', 'qty', 'qty_dipesan', 'qty_diterima',
                                                        'selisih', 'jumlah_masuk', 'jumlah_keluar'
                                                    ], true);
                                                @endphp
                                                <td class="py-2 px-3 tabular-nums whitespace-nowrap {{ $isNumCol ? 'text-right' : 'text-left' }}">{{ is_array($val) || is_object($val) ? '-' : $fmtVal($val, $key) }}</td>
                                            @endif
                                        @endforeach
                                        <td class="py-2 px-3 text-right">
                                            <span class="text-xs text-up-primary hover:underline font-bold">Detail ↗</span>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                            {{-- [ISO 9001 / ISO/IEC 25010] Table Footer Rekonsiliasi & Total Agregat --}}
                            @if(!empty($aggregates))
                                <tfoot>
                                    <tr class="bg-white/[0.06] border-t-2 border-white/20 font-bold text-ink-200">
                                        @php $isFirst = true; @endphp
                                        @foreach($items[0] ?? [] as $key => $val)
                                            @if(!in_array($key, \App\Modules\Report\Services\ReportBuilderService::SENSITIVE_EXPORT_COLUMNS, true))
                                                @php
                                                    $isNumCol = array_key_exists((string) $key, $aggregates);
                                                @endphp
                                                <td class="py-2.5 px-3 tabular-nums whitespace-nowrap {{ $isNumCol ? 'text-right text-up-mint font-black' : 'text-left' }}">
                                                    @if($isNumCol)
                                                        Rp {{ number_format($aggregates[$key], 0, ',', '.') }}
                                                    @elseif($isFirst)
                                                        <span class="text-[10px] uppercase tracking-wider text-ink-400">TOTAL HALAMAN:</span>
                                                        @php $isFirst = false; @endphp
                                                    @else
                                                        -
                                                    @endif
                                                </td>
                                            @endif
                                        @endforeach
                                        <td class="py-2.5 px-3 text-right text-[10px] text-ink-400">SELESAI</td>
                                    </tr>
                                </tfoot>
                            @endif
                        </table>
                    </x-prism.dual-scroll>
                </div>

                {{-- Pagination Controls ISO --}}
                <div class="mt-4 pt-3 border-t border-white/5 flex flex-col sm:flex-row items-center justify-between gap-3 text-xs text-ink-400">
                    <div>
                        Menampilkan halaman <span class="font-bold text-white">{{ $currentPage }}</span> dari <span class="font-bold text-white">{{ $totalPages }}</span> (Total <span class="font-bold text-white tabular-nums">{{ number_format($totalRecords, 0, ',', '.') }}</span> baris data)
                    </div>
                    <div class="flex items-center gap-1.5">
                        <button
                            type="button"
                            wire:click="previousPage"
                            @disabled($currentPage <= 1)
                            class="px-2.5 py-1.5 rounded-lg bg-white/5 hover:bg-white/10 disabled:opacity-30 disabled:cursor-not-allowed border border-white/10 text-white font-semibold transition"
                        >
                            ← Sebelumnya
                        </button>
                        <span class="px-2 font-mono text-ink-300">{{ $currentPage }} / {{ $totalPages }}</span>
                        <button
                            type="button"
                            wire:click="nextPage"
                            @disabled($currentPage >= $totalPages)
                            class="px-2.5 py-1.5 rounded-lg bg-white/5 hover:bg-white/10 disabled:opacity-30 disabled:cursor-not-allowed border border-white/10 text-white font-semibold transition"
                        >
                            Berikutnya →
                        </button>
                    </div>
                </div>

                {{-- Mobile Phone Card View --}}
                <div class="block sm:hidden space-y-2.5">
                    @foreach($items as $mIdx => $item)
                        <div wire:click="drillDown('{{ $currentModel }}', {{ $item['id'] ?? 0 }})" class="p-3.5 rounded-xl bg-white/[0.03] hover:bg-white/[0.06] border border-white/5 cursor-pointer transition active:scale-[0.99] space-y-2">
                            <div class="flex items-center justify-between pb-1.5 border-b border-white/5">
                                <span class="font-mono text-xs font-bold text-up-primary">#{{ $item['id'] ?? ($mIdx + 1) }}</span>
                                <span class="text-xs text-ink-400 flex items-center gap-1 font-semibold">
                                    Lihat Detail <span class="text-up-primary">↗</span>
                                </span>
                            </div>
                            <div class="grid grid-cols-2 gap-x-3 gap-y-1.5 text-xs">
                                @foreach($item as $key => $val)
                                    @if(!in_array($key, \App\Modules\Report\Services\ReportBuilderService::SENSITIVE_EXPORT_COLUMNS, true) && $key !== 'id')
                                        <div>
                                            <div class="text-[9px] uppercase font-bold text-ink-500 truncate">{{ \App\Modules\Report\Services\ReportBuilderService::getColumnLabel((string) $key, $currentModel) }}</div>
                                            <div class="text-white font-medium tabular-nums truncate">{{ is_array($val) || is_object($val) ? '-' : $fmtVal($val, $key) }}</div>
                                        </div>
                                    @endif
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="text-center py-12 text-ink-500 text-xs space-y-2">
                    <svg class="w-8 h-8 mx-auto opacity-30 text-ink-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                    </svg>
                    <p>Tidak ada data. Silakan pilih sumber data atau ubah rentang tanggal periode di atas.</p>
                </div>
            @endif
        </x-prism.glass-card>
    @endif
</div>
