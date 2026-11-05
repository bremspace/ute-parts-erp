<!-- MODAL: RIWAYAT TRANSAKSI HARI INI -->
@if($showRiwayatTransaksiModal)
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-ink-950/80 backdrop-blur-md p-3 sm:p-6 overflow-y-auto">
        <div class="w-full max-w-4xl bg-white dark:bg-[#0B1120] border border-ink-200/80 dark:border-white/10 p-4 sm:p-6 rounded-3xl relative my-auto max-h-[90vh] sm:max-h-[85vh] flex flex-col shadow-2xl">
            <!-- Header -->
            <div class="flex items-center justify-between pb-3 mb-2 sm:mb-3 border-b border-ink-200/70 dark:border-white/10 gap-2 shrink-0">
                <div class="flex items-center gap-2.5 min-w-0">
                    <div class="w-10 h-10 rounded-2xl bg-up-primary/10 border border-up-primary/20 flex items-center justify-center text-up-primary font-bold shrink-0">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                        </svg>
                    </div>
                    <div class="min-w-0">
                        <h3 class="text-sm sm:text-base font-bold text-ink-900 dark:text-white truncate">Riwayat Transaksi & Retur Penjualan</h3>
                        <p class="text-[11px] text-ink-500 dark:text-ink-400 truncate">Cari no. faktur atau transaksi selesai untuk retur / cetak ulang struk</p>
                    </div>
                </div>
                <button
                    type="button"
                    wire:click="tutupRiwayatTransaksi"
                    class="p-2 rounded-xl text-ink-400 hover:text-ink-900 dark:hover:text-white hover:bg-ink-100 dark:hover:bg-white/5 transition-all duration-150 ease-[cubic-bezier(0.23,1,0.32,1)] active:scale-90 cursor-pointer min-w-[40px] min-h-[40px] flex items-center justify-center shrink-0"
                    aria-label="Tutup modal"
                >✕</button>
            </div>

            <!-- Sticky Search Box & Summary Toolbar -->
            <div class="mb-3 sticky top-0 z-10 bg-white/95 dark:bg-[#0B1120]/95 backdrop-blur-md pb-2 pt-0.5 border-b border-ink-100/80 dark:border-white/5 shrink-0 flex flex-col gap-2">
                <div class="relative flex items-center">
                    <input
                        type="text"
                        wire:model.live.debounce.300ms="riwayatSearch"
                        placeholder="Ketik No. Faktur (TRX-...) / Pelanggan / Kasir..."
                        class="w-full pl-10 pr-10 py-2.5 rounded-xl glass-input text-base sm:text-xs font-medium text-ink-900 dark:text-white min-h-[44px] focus:ring-2 focus:ring-up-primary/30"
                    />
                    <div class="absolute left-3 text-ink-400 pointer-events-none">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                    </div>
                    @if($riwayatSearch)
                        <button
                            type="button"
                            wire:click="$set('riwayatSearch', '')"
                            class="absolute right-2.5 p-1 rounded-lg text-ink-400 hover:text-ink-900 dark:hover:text-white hover:bg-black/5 dark:hover:bg-white/10 text-xs cursor-pointer min-h-[36px] min-w-[36px] flex items-center justify-center transition active:scale-90"
                            aria-label="Hapus pencarian"
                        >✕</button>
                    @endif
                </div>

                <!-- Summary Status Row -->
                <div class="flex items-center justify-between text-xs px-1 text-ink-500">
                    <div class="flex items-center gap-1.5 font-medium">
                        <span class="text-[11px] font-semibold text-ink-600 dark:text-ink-300">Transaksi Hari Ini:</span>
                        <span class="px-2 py-0.5 rounded-full bg-up-primary/10 text-up-primary font-bold tabular-nums text-[11px] border border-up-primary/20">
                            {{ $riwayatTransaksiHariIni->count() }}
                        </span>
                    </div>
                    @if($riwayatTransaksiHariIni->isNotEmpty())
                        <div class="text-[11px] text-ink-500 font-medium">
                            Total Selesai: <span class="font-bold text-up-mint tabular-nums">Rp {{ number_format($riwayatTransaksiHariIni->where('status', 'selesai')->sum('total_akhir'), 0, ',', '.') }}</span>
                        </div>
                    @endif
                </div>
            </div>

            <!-- Content Area -->
            <div class="overflow-y-auto flex-1 pr-1 overscroll-contain">
                @if($riwayatTransaksiHariIni->isEmpty())
                    <div class="py-16 text-center text-ink-400 text-xs">
                        <div class="w-12 h-12 rounded-2xl bg-ink-100 dark:bg-white/5 border border-ink-200/80 dark:border-white/10 mx-auto mb-2 flex items-center justify-center text-ink-400">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                            </svg>
                        </div>
                        <p class="font-semibold text-ink-700 dark:text-ink-300">Belum ada transaksi</p>
                        <p class="text-[11px] text-ink-500 mt-0.5">Transaksi hari ini di cabang ini akan muncul di sini</p>
                    </div>
                @else
                    {{-- Desktop Table View --}}
                    <div class="hidden sm:block overflow-x-auto min-w-full rounded-2xl border border-ink-200/80 dark:border-white/5">
                        <table class="w-full text-left text-xs whitespace-nowrap">
                            <thead>
                                <tr class="border-b border-ink-200/80 dark:border-white/10 bg-ink-50/80 dark:bg-white/[0.02] text-ink-600 dark:text-ink-400 uppercase text-[10px] font-bold tracking-wider">
                                    <th class="py-3 px-3.5">No Transaksi</th>
                                    <th class="py-3 px-3.5">Sumber</th>
                                    <th class="py-3 px-3.5">Waktu</th>
                                    <th class="py-3 px-3.5">Kasir</th>
                                    <th class="py-3 px-3.5">Pelanggan</th>
                                    <th class="py-3 px-3.5 text-right">Total</th>
                                    <th class="py-3 px-3.5 text-center">Metode</th>
                                    <th class="py-3 px-3.5 text-center">Status</th>
                                    <th class="py-3 px-3.5 text-right">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-ink-200/60 dark:divide-white/5">
                                @foreach($riwayatTransaksiHariIni as $trx)
                                    @php
                                        $sumber = $trx->sumber;
                                        $sumberBadge = match(true) {
                                            $sumber === 'marketplace' => ['label' => 'Web Ute', 'class' => 'bg-indigo-500/15 text-indigo-400 border border-indigo-500/30'],
                                            str_starts_with((string) $sumber, 'channel:') => ['label' => strtoupper(str_replace('channel:', '', $sumber)), 'class' => 'bg-up-accent/15 text-up-accent border border-up-accent/30'],
                                            $sumber === 'servis' => ['label' => 'Servis', 'class' => 'bg-teal-500/15 text-teal-400 border border-teal-500/30'],
                                            default => ['label' => 'POS', 'class' => 'bg-white/10 text-ink-300 border border-white/10'],
                                        };
                                    @endphp
                                    <tr class="hover:bg-ink-50 dark:hover:bg-white/[0.02] transition-colors">
                                        <td class="py-3 px-3.5 font-mono font-bold text-ink-900 dark:text-white">{{ $trx->no_transaksi }}</td>
                                        <td class="py-3 px-3.5">
                                            <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase {{ $sumberBadge['class'] }}">
                                                {{ $sumberBadge['label'] }}
                                            </span>
                                        </td>
                                        <td class="py-3 px-3.5 text-ink-600 dark:text-ink-300 tabular-nums">{{ $trx->created_at?->format('H:i:s') }}</td>
                                        <td class="py-3 px-3.5 text-ink-700 dark:text-ink-200">{{ $trx->kasir?->name ?? '-' }}</td>
                                        <td class="py-3 px-3.5 text-ink-700 dark:text-ink-200 font-medium">{{ $trx->pelanggan?->nama ?? 'Umum' }}</td>
                                        <td class="py-3 px-3.5 text-right font-black text-up-mint tabular-nums">Rp {{ number_format($trx->total_akhir, 0, ',', '.') }}</td>
                                        <td class="py-3 px-3.5 text-center uppercase text-[10px] text-ink-700 dark:text-ink-300 font-bold">{{ $trx->metode_bayar }}</td>
                                        <td class="py-3 px-3.5 text-center">
                                            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold {{ $trx->status === 'selesai' ? 'bg-up-mint/15 text-up-mint border border-up-mint/30' : ($trx->status === 'void' ? 'bg-purple-500/15 text-purple-400 border border-purple-500/30' : ($trx->status === 'dibatalkan' ? 'bg-up-red/15 text-up-red border border-up-red/30' : 'bg-up-amber/15 text-up-amber border border-up-amber/30')) }}">
                                                {{ strtoupper($trx->status) }}
                                            </span>
                                        </td>
                                        <td class="py-3 px-3.5 text-right whitespace-nowrap">
                                            <div class="flex items-center justify-end gap-1.5">
                                                <button
                                                    type="button"
                                                    wire:click="lihatDetailTransaksi({{ $trx->id }})"
                                                    class="px-2.5 py-1.5 rounded-lg bg-ink-100 hover:bg-ink-200 dark:bg-white/5 dark:hover:bg-white/10 text-ink-800 dark:text-ink-100 font-bold text-[11px] transition-all duration-150 ease-[cubic-bezier(0.23,1,0.32,1)] active:scale-95 border border-ink-200/80 dark:border-white/10 cursor-pointer"
                                                >
                                                    Detail
                                                </button>
                                                @if($trx->status === 'selesai')
                                                    @can('pos.void')
                                                    <button
                                                        type="button"
                                                        wire:click="bukaVoidModal({{ $trx->id }})"
                                                        class="px-2.5 py-1.5 rounded-lg bg-up-red/10 hover:bg-up-red/20 text-up-red font-bold text-[11px] transition-all duration-150 ease-[cubic-bezier(0.23,1,0.32,1)] active:scale-95 border border-up-red/25 cursor-pointer flex items-center gap-1"
                                                        title="Batalkan & Void Transaksi Ini"
                                                    >
                                                        <span>Void</span>
                                                    </button>
                                                    @endcan
                                                    @canany(['pos.view', 'pos.view-own', 'pos.create'])
                                                    <a
                                                        href="/app/pos/retur?no_faktur={{ urlencode($trx->no_transaksi) }}"
                                                        class="px-2.5 py-1.5 rounded-lg bg-up-amber/10 hover:bg-up-amber/20 text-up-amber font-bold text-[11px] transition-all duration-150 ease-[cubic-bezier(0.23,1,0.32,1)] active:scale-95 border border-up-amber/25 cursor-pointer flex items-center"
                                                    >
                                                        Retur
                                                    </a>
                                                    @endcanany
                                                @endif
                                                @can('lihat-audit-log')
                                                    <button
                                                        type="button"
                                                        wire:click="bukaRiwayat('transaksi', {{ $trx->id }})"
                                                        class="px-2 py-1.5 rounded-lg bg-up-primary/10 hover:bg-up-primary/20 text-up-primary font-bold text-[11px] transition-all duration-150 ease-[cubic-bezier(0.23,1,0.32,1)] active:scale-95 border border-up-primary/20 cursor-pointer"
                                                    >
                                                        Audit
                                                    </button>
                                                @endcan
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    {{-- Mobile Cards View (iOS Grade) --}}
                    <div class="block sm:hidden space-y-3">
                        @foreach($riwayatTransaksiHariIni as $trx)
                            @php
                                $sumber = $trx->sumber;
                                $sumberBadge = match(true) {
                                    $sumber === 'marketplace' => ['label' => 'Web Ute', 'class' => 'bg-indigo-500/15 text-indigo-400 border border-indigo-500/30'],
                                    str_starts_with((string) $sumber, 'channel:') => ['label' => strtoupper(str_replace('channel:', '', $sumber)), 'class' => 'bg-up-accent/15 text-up-accent border border-up-accent/30'],
                                    $sumber === 'servis' => ['label' => 'Servis', 'class' => 'bg-teal-500/15 text-teal-400 border border-teal-500/30'],
                                    default => ['label' => 'POS', 'class' => 'bg-white/10 text-ink-300 border border-white/10'],
                                };
                                $statusBadge = match($trx->status) {
                                    'selesai' => ['class' => 'bg-emerald-500/10 text-emerald-500 border-emerald-500/25', 'dot' => 'bg-emerald-500'],
                                    'void' => ['class' => 'bg-purple-500/10 text-purple-400 border-purple-500/25', 'dot' => 'bg-purple-400'],
                                    'dibatalkan' => ['class' => 'bg-rose-500/10 text-rose-500 border-rose-500/25', 'dot' => 'bg-rose-500'],
                                    default => ['class' => 'bg-amber-500/10 text-amber-500 border-amber-500/25', 'dot' => 'bg-amber-500'],
                                };
                            @endphp
                            <div class="p-3.5 rounded-2xl bg-white dark:bg-white/[0.02] border border-ink-200/80 dark:border-white/10 shadow-xs flex flex-col gap-2.5 transition-all duration-150 ease-[cubic-bezier(0.23,1,0.32,1)] active:scale-[0.99]">
                                <!-- Row 1: No Faktur, Channel & Status -->
                                <div class="flex items-center justify-between gap-2">
                                    <div class="flex items-center gap-1.5 flex-wrap min-w-0">
                                        <span class="font-mono font-bold text-ink-900 dark:text-white text-xs truncate">{{ $trx->no_transaksi }}</span>
                                        <span class="px-1.5 py-0.2 rounded text-[9px] font-bold uppercase {{ $sumberBadge['class'] }}">{{ $sumberBadge['label'] }}</span>
                                    </div>
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold shrink-0 border {{ $statusBadge['class'] }}">
                                        <span class="w-1.5 h-1.5 rounded-full {{ $statusBadge['dot'] }}"></span>
                                        <span>{{ strtoupper($trx->status) }}</span>
                                    </span>
                                </div>

                                <!-- Row 2: Customer, Kasir, Timestamp & Total Amount -->
                                <div class="flex items-end justify-between gap-2 pt-1 border-t border-ink-200/50 dark:border-white/5">
                                    <div class="min-w-0 flex-1 space-y-0.5">
                                        <div class="flex items-center gap-1 text-xs font-bold text-ink-900 dark:text-white truncate">
                                            <span>👤 {{ $trx->pelanggan?->nama ?? 'Pelanggan Umum' }}</span>
                                            @if($trx->pelanggan?->tierMembership)
                                                <span class="px-1.5 py-0.2 rounded text-[9px] font-semibold bg-up-primary/10 text-up-primary border border-up-primary/20">
                                                    {{ $trx->pelanggan->tierMembership->nama }}
                                                </span>
                                            @endif
                                        </div>
                                        <div class="flex items-center gap-2 text-[10px] text-ink-500 dark:text-ink-400">
                                            <span>Kasir: {{ $trx->kasir?->name ?? '-' }}</span>
                                            <span>•</span>
                                            <span class="tabular-nums font-mono">{{ $trx->created_at?->format('H:i:s') }}</span>
                                        </div>
                                    </div>

                                    <div class="text-right shrink-0">
                                        <div class="text-sm font-black text-up-mint tabular-nums font-mono">
                                            Rp {{ number_format($trx->total_akhir, 0, ',', '.') }}
                                        </div>
                                        <div class="inline-block px-1.5 py-0.2 rounded bg-ink-200/60 dark:bg-white/10 text-[9px] font-bold uppercase text-ink-600 dark:text-ink-300">
                                            {{ $trx->metode_bayar }}
                                        </div>
                                    </div>
                                </div>

                                <!-- Row 3: Action Buttons (Touch Friendly & Apple Tactile) -->
                                <div class="flex items-center gap-2 pt-1">
                                    <button
                                        type="button"
                                        wire:click="lihatDetailTransaksi({{ $trx->id }})"
                                        class="flex-1 py-2 px-3 rounded-xl bg-ink-100 hover:bg-ink-200 dark:bg-white/10 dark:hover:bg-white/15 text-ink-900 dark:text-white font-bold text-xs transition-all duration-150 ease-[cubic-bezier(0.23,1,0.32,1)] border border-ink-200/80 dark:border-white/10 cursor-pointer min-h-[42px] flex items-center justify-center gap-1.5 active:scale-[0.97]"
                                    >
                                        <svg class="w-4 h-4 text-ink-500 dark:text-ink-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                        </svg>
                                        <span>Detail / Struk</span>
                                    </button>

                                    @if($trx->status === 'selesai')
                                        @canany(['pos.view', 'pos.view-own', 'pos.create'])
                                        <a
                                            href="/app/pos/retur?no_faktur={{ urlencode($trx->no_transaksi) }}"
                                            class="py-2 px-3 rounded-xl bg-up-amber/10 hover:bg-up-amber/20 text-up-amber font-bold text-xs transition-all duration-150 ease-[cubic-bezier(0.23,1,0.32,1)] border border-up-amber/30 cursor-pointer min-h-[42px] flex items-center justify-center gap-1 active:scale-[0.97]"
                                        >
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6" />
                                            </svg>
                                            <span>Retur</span>
                                        </a>
                                        @endcanany

                                        @can('pos.void')
                                        <button
                                            type="button"
                                            wire:click="bukaVoidModal({{ $trx->id }})"
                                            class="py-2 px-3 rounded-xl bg-up-red/10 hover:bg-up-red/20 text-up-red font-bold text-xs transition-all duration-150 ease-[cubic-bezier(0.23,1,0.32,1)] border border-up-red/30 cursor-pointer min-h-[42px] flex items-center justify-center gap-1 active:scale-[0.97]"
                                        >
                                            <span>Void</span>
                                        </button>
                                        @endcan
                                    @endif

                                    @can('lihat-audit-log')
                                        <button
                                            type="button"
                                            wire:click="bukaRiwayat('transaksi', {{ $trx->id }})"
                                            class="p-2 rounded-xl bg-up-primary/10 hover:bg-up-primary/20 text-up-primary font-bold text-xs transition-all duration-150 ease-[cubic-bezier(0.23,1,0.32,1)] border border-up-primary/25 cursor-pointer min-h-[42px] min-w-[42px] flex items-center justify-center active:scale-[0.97]"
                                            title="Audit Log"
                                        >
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                            </svg>
                                        </button>
                                    @endcan
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            <!-- Footer -->
            <div class="pt-3 mt-2 border-t border-ink-200/70 dark:border-white/10 flex justify-end shrink-0">
                <button
                    type="button"
                    wire:click="tutupRiwayatTransaksi"
                    class="w-full sm:w-auto px-5 py-2.5 rounded-xl bg-ink-100 hover:bg-ink-200 dark:bg-white/10 dark:hover:bg-white/15 text-ink-800 dark:text-white font-bold text-xs cursor-pointer min-h-[44px] flex items-center justify-center transition-all duration-150 ease-[cubic-bezier(0.23,1,0.32,1)] active:scale-95"
                >
                    Tutup
                </button>
            </div>
        </div>
    </div>
@endif
