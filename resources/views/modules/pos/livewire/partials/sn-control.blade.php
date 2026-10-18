{{-- [F2-3] Scan/autocomplete nomor seri utk item keranjang sn=true — wajib = qty --}}
@if($item['sn'] ?? false)
    <div class="mt-2.5 pt-2.5 border-t border-ink-200/60 dark:border-white/5" wire:key="sn-{{ $itemKey }}">
        <div class="flex items-center justify-between mb-1.5 gap-2">
            <span class="text-[10px] font-bold text-up-accent tracking-wider uppercase flex items-center gap-1">
                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z" />
                </svg>
                Nomor Seri (wajib {{ $item['qty'] }})
            </span>
            <span class="text-[10px] font-semibold tabular-nums px-2 py-0.5 rounded-full {{ count($item['sn_list'] ?? []) >= (int) $item['qty'] ? 'bg-up-mint/15 text-up-mint border border-up-mint/30' : 'bg-up-amber/15 text-up-amber border border-up-amber/30' }}">
                {{ count($item['sn_list'] ?? []) }}/{{ $item['qty'] }} SN
            </span>
        </div>

        {{-- daftar SN terpilih --}}
        @if(!empty($item['sn_list']))
            <div class="flex flex-wrap gap-1.5 mb-2">
                @foreach(($item['sn_list'] ?? []) as $snIdx => $sn)
                    <div class="inline-flex items-center gap-1.5 px-2 py-1 rounded-lg bg-ink-100 dark:bg-white/5 border border-ink-200/80 dark:border-white/10 text-ink-900 dark:text-white text-[11px] font-mono">
                        <span class="truncate max-w-[140px]">{{ $sn }}</span>
                        <button
                            type="button"
                            wire:click="hapusSn('{{ $itemKey }}', {{ $snIdx }})"
                            class="text-ink-400 hover:text-up-red transition-colors cursor-pointer p-0.5 active:scale-90"
                            title="Hapus SN"
                        >✕</button>
                    </div>
                @endforeach
            </div>
        @endif

        @if(count($item['sn_list'] ?? []) < (int) $item['qty'])
            <div class="relative flex items-center gap-1.5">
                <input
                    type="text"
                    wire:model.live="snSearch"
                    wire:focus="setSnItemKey('{{ $itemKey }}')"
                    wire:keyup.enter="pilihSn('{{ $itemKey }}')"
                    placeholder="Scan / ketik SN lalu Enter…"
                    class="flex-1 px-3 py-1.5 rounded-xl glass-input text-xs font-mono text-ink-900 dark:text-white placeholder:text-ink-400 min-h-[36px]"
                    aria-label="Input nomor seri"
                />
                <button
                    type="button"
                    wire:click="pilihSn('{{ $itemKey }}')"
                    class="px-3 py-1.5 rounded-xl bg-up-accent/20 hover:bg-up-accent/30 text-up-accent border border-up-accent/30 font-bold text-xs cursor-pointer min-h-[36px] transition-transform active:scale-95"
                    title="Tambah SN"
                >+</button>

                {{-- autocomplete: SN tersedia di cabang aktif --}}
                @if($snItemKey === $itemKey && trim($snSearch) !== '' && ($snCari ?? collect())->isNotEmpty())
                    <div class="absolute z-30 top-full left-0 right-0 mt-1 glass-panel border border-ink-200/80 dark:border-white/10 rounded-2xl overflow-hidden shadow-2xl divide-y divide-ink-200/50 dark:divide-white/5 max-h-48 overflow-y-auto">
                        @foreach($snCari as $s)
                            <button
                                type="button"
                                wire:click="pilihSnLangsung('{{ $itemKey }}', '{{ $s->nomor_seri }}')"
                                class="w-full text-left px-3.5 py-2.5 text-xs font-mono text-ink-900 dark:text-ink-100 hover:bg-ink-100 dark:hover:bg-white/10 transition-colors cursor-pointer flex items-center justify-between"
                            >
                                <span class="font-bold text-up-primary dark:text-up-mint">{{ $s->nomor_seri }}</span>
                                <span class="text-ink-500 dark:text-ink-400 font-sans text-[11px] truncate max-w-[150px]">{{ $s->produk?->nama }}</span>
                            </button>
                        @endforeach
                    </div>
                @endif
            </div>
        @endif
    </div>
@endif
