{{-- [F2-3] Scan/autocomplete nomor seri utk item keranjang sn=true — wajib = qty --}}
@if($item['sn'] ?? false)
    <div class="mt-2 pt-2 border-t border-white/5" wire:key="sn-{{ $itemKey }}">
        <div class="flex items-center justify-between mb-1 gap-2">
            <span class="text-[10px] font-bold text-up-accent uppercase">Nomor Seri (wajib {{ $item['qty'] }})</span>
            <span class="text-[10px] tabular-nums {{ count($item['sn_list'] ?? []) >= (int) $item['qty'] ? 'text-up-mint' : 'text-up-amber' }}">
                {{ count($item['sn_list'] ?? []) }}/{{ $item['qty'] }} SN
            </span>
        </div>

        {{-- daftar SN terpilih --}}
        @foreach(($item['sn_list'] ?? []) as $snIdx => $sn)
            <div class="flex items-center justify-between gap-2 py-0.5">
                <span class="text-[11px] font-mono text-white truncate">{{ $sn }}</span>
                <button
                    wire:click="hapusSn('{{ $itemKey }}', {{ $snIdx }})"
                    class="text-up-red hover:text-white text-xs cursor-pointer flex-shrink-0"
                    title="Hapus SN"
                >✕</button>
            </div>
        @endforeach

        @if(count($item['sn_list'] ?? []) < (int) $item['qty'])
            <div class="relative flex gap-1 mt-1">
                <input
                    type="text"
                    wire:model.live="snSearch"
                    wire:focus="setSnItemKey('{{ $itemKey }}')"
                    wire:keyup.enter="pilihSn('{{ $itemKey }}')"
                    placeholder="Scan / ketik SN lalu Enter…"
                    class="flex-1 px-2 py-1.5 rounded-lg glass-input text-[11px] font-mono"
                    aria-label="Input nomor seri"
                />
                <button
                    wire:click="pilihSn('{{ $itemKey }}')"
                    class="px-2.5 py-1.5 rounded-lg bg-up-accent/15 hover:bg-up-accent/25 text-up-accent font-bold text-[11px] cursor-pointer"
                    title="Tambah SN"
                >+</button>

                {{-- autocomplete: SN tersedia di cabang aktif --}}
                @if($snItemKey === $itemKey && trim($snSearch) !== '' && ($snCari ?? collect())->isNotEmpty())
                    <div class="absolute z-30 top-full left-0 right-0 mt-1 glass-panel border border-white/10 rounded-xl overflow-hidden shadow-xl">
                        @foreach($snCari as $s)
                            <button
                                wire:click="pilihSnLangsung('{{ $itemKey }}', '{{ $s->nomor_seri }}')"
                                class="block w-full text-left px-3 py-2 text-[11px] font-mono text-ink-100 hover:bg-white/10 transition-colors cursor-pointer"
                            >
                                {{ $s->nomor_seri }}
                                <span class="text-ink-400 font-sans">— {{ $s->produk?->nama }}</span>
                            </button>
                        @endforeach
                    </div>
                @endif
            </div>
        @endif
    </div>
@endif
