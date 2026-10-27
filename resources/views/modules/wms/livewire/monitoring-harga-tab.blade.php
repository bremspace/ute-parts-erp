<div class="space-y-6">
    <!-- Top Summary Banner & Parameter Kalkulator Terbuka -->
    <div class="glass-card rounded-2xl p-5 border border-black/10 dark:border-white/10 space-y-4">
        <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
            <div>
                <div class="flex items-center gap-2">
                    <span class="text-xl">🛡️</span>
                    <h2 class="text-base sm:text-lg font-bold text-ink-900 dark:text-white">Monitoring Margin & Guard Anti-Rugi Master Produk</h2>
                </div>
                <p class="text-xs text-ink-500 dark:text-ink-400 mt-1">
                    Sistem mendeteksi dan memonitor harga beli (HPP) vs harga jual retail secara otomatis sebelum terjadi transaksi, mencegah harga jual tergerus tumpukan diskon & komisi.
                </p>
            </div>

            <!-- Bulk Actions -->
            <div class="flex items-center gap-2 flex-wrap sm:flex-nowrap">
                @if(count($selectedIds) > 0)
                    <button
                        wire:click="terapkanTerpilih"
                        wire:confirm="Sesuaikan {{ count($selectedIds) }} produk terpilih ke harga jual rekomendasi aman?"
                        class="px-3.5 py-2 rounded-xl bg-up-primary hover:bg-up-primary/90 text-white font-bold text-xs shadow-md shadow-up-primary/20 transition active:scale-95 cursor-pointer flex items-center gap-1.5"
                    >
                        <span>⚡ Terapkan Terpilih ({{ count($selectedIds) }})</span>
                    </button>
                @endif

                @if($scanSummary['total_bahaya'] > 0 || $scanSummary['total_peringatan'] > 0)
                    <button
                        wire:click="terapkanSemuaBerisiko"
                        wire:confirm="Yakin ingin menyesuaikan semua produk berisiko ({{ $scanSummary['total_bahaya'] + $scanSummary['total_peringatan'] }} item) ke harga aman rekomendasi sistem?"
                        class="px-3.5 py-2 rounded-xl bg-up-red/15 hover:bg-up-red/25 text-up-red border border-up-red/30 font-bold text-xs transition active:scale-95 cursor-pointer flex items-center gap-1.5"
                        title="Sesuaikan otomatis semua produk berisiko rugi ke harga rekomendasi"
                    >
                        <span>🛡️ Auto-Fix Semua Berisiko ({{ $scanSummary['total_bahaya'] + $scanSummary['total_peringatan'] }})</span>
                    </button>
                @endif
            </div>
        </div>

        <!-- Rincian Parameter Guard Sistem (Transparan) -->
        <div class="p-3.5 rounded-xl bg-black/5 dark:bg-white/[0.03] border border-black/10 dark:border-white/5 text-xs text-ink-600 dark:text-ink-300">
            <div class="flex flex-wrap items-center gap-y-2 gap-x-4">
                <span class="font-bold text-ink-800 dark:text-white flex items-center gap-1">
                    <span>⚙️</span> Parameter Guard Aktif:
                </span>
                <span class="inline-flex items-center gap-1 bg-white/50 dark:bg-white/5 px-2 py-0.5 rounded-md">
                    <span class="text-ink-400">Diskon Member Max:</span> <strong class="text-up-primary">{{ $params['max_mitra_diskon'] }}%</strong>
                </span>
                <span class="inline-flex items-center gap-1 bg-white/50 dark:bg-white/5 px-2 py-0.5 rounded-md">
                    <span class="text-ink-400">Komisi Agen:</span> <strong class="text-up-amber">{{ $params['komisi_persen'] }}%</strong>
                </span>
                <span class="inline-flex items-center gap-1 bg-white/50 dark:bg-white/5 px-2 py-0.5 rounded-md">
                    <span class="text-ink-400">Buffer Biaya & Error:</span> <strong class="text-up-mint">{{ $params['buffer_biaya_persen'] }}%</strong>
                </span>
                <span class="inline-flex items-center gap-1 bg-up-primary/10 text-up-primary px-2.5 py-0.5 rounded-md font-bold">
                    Total Beban Potongan Wajib Ditutup: {{ $params['total_potongan_persen'] }}% (Min. Markup: +{{ $params['min_markup_persen'] }}%)
                </span>
            </div>
        </div>
    </div>

    <!-- 4 Stat Metric Cards -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4">
        <!-- Total Discan -->
        <div class="glass-card rounded-2xl p-4 border border-black/10 dark:border-white/10">
            <p class="text-[11px] font-semibold text-ink-400 uppercase tracking-wider">Total Produk Discan</p>
            <p class="text-2xl font-bold font-mono text-ink-900 dark:text-white tabular-nums mt-1">{{ number_format($scanSummary['total'], 0, ',', '.') }}</p>
            <div class="flex items-center gap-1 text-[11px] text-ink-500 mt-2">
                <span>Kesehatan Master:</span>
                <strong class="{{ $scanSummary['persen_sehat'] >= 80 ? 'text-up-mint' : ($scanSummary['persen_sehat'] >= 50 ? 'text-up-amber' : 'text-up-red') }}">
                    {{ $scanSummary['persen_sehat'] }}%
                </strong>
            </div>
        </div>

        <!-- Margin Aman -->
        <div class="glass-card rounded-2xl p-4 border border-up-mint/30 bg-up-mint/[0.04]">
            <div class="flex items-center justify-between">
                <p class="text-[11px] font-semibold text-up-mint uppercase tracking-wider">Margin Sehat (Aman)</p>
                <span class="w-2 h-2 rounded-full bg-up-mint"></span>
            </div>
            <p class="text-2xl font-bold font-mono text-up-mint tabular-nums mt-1">{{ number_format($scanSummary['total_aman'], 0, ',', '.') }}</p>
            <p class="text-[11px] text-ink-500 dark:text-ink-400 mt-2">Harga menutup modal & potongan</p>
        </div>

        <!-- Margin Tipis -->
        <div class="glass-card rounded-2xl p-4 border border-up-amber/30 bg-up-amber/[0.04]">
            <div class="flex items-center justify-between">
                <p class="text-[11px] font-semibold text-up-amber uppercase tracking-wider">Margin Tipis (Peringatan)</p>
                <span class="w-2 h-2 rounded-full bg-up-amber"></span>
            </div>
            <p class="text-2xl font-bold font-mono text-up-amber tabular-nums mt-1">{{ number_format($scanSummary['total_peringatan'], 0, ',', '.') }}</p>
            <p class="text-[11px] text-ink-500 dark:text-ink-400 mt-2">Berisiko terpotong safety floor</p>
        </div>

        <!-- Potensi Rugi -->
        <div class="glass-card rounded-2xl p-4 border border-up-red/30 bg-up-red/[0.04]">
            <div class="flex items-center justify-between">
                <p class="text-[11px] font-semibold text-up-red uppercase tracking-wider">Potensi Rugi Keras</p>
                <span class="w-2 h-2 rounded-full bg-up-red animate-pulse"></span>
            </div>
            <p class="text-2xl font-bold font-mono text-up-red tabular-nums mt-1">{{ number_format($scanSummary['total_bahaya'], 0, ',', '.') }}</p>
            <p class="text-[11px] text-up-red/80 mt-2">Harga jual &le; HPP modal barang</p>
        </div>
    </div>

    <!-- Filter & Search Controls -->
    <div class="glass-card rounded-2xl p-4 border border-black/10 dark:border-white/10 flex flex-col md:flex-row items-center justify-between gap-3">
        <div class="flex items-center gap-2 w-full md:w-auto flex-1">
            <div class="relative flex-1 max-w-md">
                <input
                    type="text"
                    wire:model.live.debounce.300ms="search"
                    placeholder="Cari SKU, Barcode, atau Nama Produk..."
                    class="w-full px-3.5 py-2 rounded-xl glass-input text-xs pl-9"
                />
                <span class="absolute left-3 top-2.5 text-ink-400 text-xs">🔍</span>
            </div>

            <select wire:model.live="filterKategori" class="px-3 py-2 rounded-xl glass-input text-xs">
                <option value="" class="bg-ink-900">Semua Kategori</option>
                @foreach($kategoriList as $kat)
                    <option value="{{ $kat->nama }}" class="bg-ink-900">{{ $kat->nama }}</option>
                @endforeach
            </select>
        </div>

        <!-- Status Filter Pills -->
        <div class="flex items-center gap-1 p-1 bg-black/5 dark:bg-white/[0.04] rounded-xl border border-black/10 dark:border-white/5 w-full md:w-auto overflow-x-auto">
            @foreach([
                'semua' => 'Semua ('.$scanSummary['total'].')',
                'bahaya' => '🔴 Rugi ('.$scanSummary['total_bahaya'].')',
                'peringatan' => '🟡 Tipis ('.$scanSummary['total_peringatan'].')',
                'aman' => '🟢 Aman ('.$scanSummary['total_aman'].')'
            ] as $stKey => $stLabel)
                <button
                    type="button"
                    wire:click="$set('filterStatus', '{{ $stKey }}')"
                    class="px-3 py-1.5 rounded-lg text-xs font-bold transition whitespace-nowrap cursor-pointer {{ $filterStatus === $stKey ? 'bg-up-primary text-white shadow-sm' : 'text-ink-600 dark:text-ink-400 hover:text-ink-900 dark:hover:text-white' }}"
                >
                    {{ $stLabel }}
                </button>
            @endforeach
        </div>
    </div>

    <!-- Data Table -->
    <div class="glass-card rounded-2xl border border-black/10 dark:border-white/10 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs border-collapse">
                <thead>
                    <tr class="border-b border-black/10 dark:border-white/10 bg-black/[0.02] dark:bg-white/[0.02] text-ink-500 uppercase tracking-wider text-[10px]">
                        <th class="py-3 px-4 w-10">
                            <input
                                type="checkbox"
                                wire:click="toggleSelectAll"
                                {{ $selectAll ? 'checked' : '' }}
                                class="rounded accent-up-primary cursor-pointer w-4 h-4"
                            />
                        </th>
                        <th class="py-3 px-4">SKU / Barcode</th>
                        <th class="py-3 px-4">Nama Produk</th>
                        <th class="py-3 px-4">Kategori</th>
                        <th class="py-3 px-4 text-right">Harga Beli (HPP)</th>
                        <th class="py-3 px-4 text-right">Harga Jual Retail</th>
                        <th class="py-3 px-4 text-center">Margin Kotor</th>
                        <th class="py-3 px-4 text-right">Rekomendasi Aman</th>
                        <th class="py-3 px-4 text-center">Status</th>
                        <th class="py-3 px-4 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-black/5 dark:divide-white/5">
                    @forelse($items as $row)
                        <tr class="hover:bg-black/[0.02] dark:hover:bg-white/[0.02] transition {{ $row['status'] === 'bahaya' ? 'bg-up-red/[0.02]' : ($row['status'] === 'peringatan' ? 'bg-up-amber/[0.02]' : '') }}">
                            <td class="py-3 px-4">
                                <input
                                    type="checkbox"
                                    wire:model.live="selectedIds"
                                    value="{{ $row['produk_id'] }}"
                                    class="rounded accent-up-primary cursor-pointer w-4 h-4"
                                />
                            </td>
                            <td class="py-3 px-4 font-mono font-semibold text-ink-800 dark:text-ink-200">
                                {{ $row['sku'] }}
                            </td>
                            <td class="py-3 px-4 font-medium text-ink-900 dark:text-white max-w-xs truncate" title="{{ $row['nama_produk'] }}">
                                {{ $row['nama_produk'] }}
                            </td>
                            <td class="py-3 px-4 text-ink-500">
                                {{ $row['kategori'] }}
                            </td>
                            <td class="py-3 px-4 text-right font-mono font-medium text-ink-700 dark:text-ink-300 tabular-nums">
                                Rp {{ number_format($row['harga_beli'], 0, ',', '.') }}
                            </td>
                            <td class="py-3 px-4 text-right font-mono font-bold text-ink-900 dark:text-white tabular-nums">
                                Rp {{ number_format($row['harga_jual'], 0, ',', '.') }}
                            </td>
                            <td class="py-3 px-4 text-center font-mono tabular-nums">
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold {{ $row['margin_persen'] >= 25 ? 'bg-up-mint/15 text-up-mint' : ($row['margin_persen'] > 0 ? 'bg-up-amber/15 text-up-amber' : 'bg-up-red/15 text-up-red') }}">
                                    {{ $row['margin_persen'] > 0 ? '+' : '' }}{{ $row['margin_persen'] }}%
                                </span>
                            </td>
                            <td class="py-3 px-4 text-right font-mono font-bold text-up-primary tabular-nums">
                                Rp {{ number_format($row['rekomendasi_harga'], 0, ',', '.') }}
                            </td>
                            <td class="py-3 px-4 text-center">
                                @if($row['status'] === 'aman')
                                    <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-up-mint/15 text-up-mint border border-up-mint/30">
                                        🟢 Aman
                                    </span>
                                @elseif($row['status'] === 'peringatan')
                                    <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-up-amber/15 text-up-amber border border-up-amber/30" title="{{ $row['pesan'] }}">
                                        🟡 Tipis
                                    </span>
                                @else
                                    <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-up-red/15 text-up-red border border-up-red/30 animate-pulse" title="{{ $row['pesan'] }}">
                                        🔴 Rugi
                                    </span>
                                @endif
                            </td>
                            <td class="py-3 px-4 text-right">
                                @if($row['status'] !== 'aman')
                                    <button
                                        type="button"
                                        wire:click="terapkanSatu({{ $row['produk_id'] }})"
                                        class="px-2.5 py-1 rounded-lg bg-up-primary/15 hover:bg-up-primary/25 text-up-primary border border-up-primary/30 font-bold text-[10px] transition active:scale-95 cursor-pointer"
                                        title="Ubah harga jual ke rekomendasi aman: Rp {{ number_format($row['rekomendasi_harga'], 0, ',', '.') }}"
                                    >
                                        Auto-Fix
                                    </button>
                                @else
                                    <span class="text-ink-400 text-[10px]">Optimal</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10" class="py-12 text-center text-ink-400">
                                Tidak ada produk ditemukan dengan filter saat ini.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        @if($totalPages > 1)
            <div class="p-4 border-t border-black/10 dark:border-white/10 flex items-center justify-between text-xs text-ink-500">
                <span>Halaman {{ $currentPage }} dari {{ $totalPages }} (Total {{ $totalItems }} produk)</span>
                <div class="flex items-center gap-1">
                    <button
                        type="button"
                        wire:click="setPage({{ $currentPage - 1 }})"
                        @if($currentPage <= 1) disabled @endif
                        class="px-3 py-1 rounded-lg border border-black/10 dark:border-white/10 disabled:opacity-30 disabled:cursor-not-allowed hover:bg-black/5 dark:hover:bg-white/5"
                    >
                        &laquo; Prev
                    </button>
                    @for($i = max(1, $currentPage - 2); $i <= min($totalPages, $currentPage + 2); $i++)
                        <button
                            type="button"
                            wire:click="setPage({{ $i }})"
                            class="px-3 py-1 rounded-lg border {{ $i === $currentPage ? 'bg-up-primary text-white border-up-primary font-bold' : 'border-black/10 dark:border-white/10 hover:bg-black/5 dark:hover:bg-white/5' }}"
                        >
                            {{ $i }}
                        </button>
                    @endfor
                    <button
                        type="button"
                        wire:click="setPage({{ $currentPage + 1 }})"
                        @if($currentPage >= $totalPages) disabled @endif
                        class="px-3 py-1 rounded-lg border border-black/10 dark:border-white/10 disabled:opacity-30 disabled:cursor-not-allowed hover:bg-black/5 dark:hover:bg-white/5"
                    >
                        Next &raquo;
                    </button>
                </div>
            </div>
        @endif
    </div>
</div>
