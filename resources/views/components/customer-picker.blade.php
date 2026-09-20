@props([
    'wireModel' => 'pelangganSearch',      // property search (per component)
    'selectAction' => 'setPelanggan',       // method saat pilih
    'searchPlaceholder' => 'Cari pelanggan: nama / no HP...',
    'showAddButton' => true,
    'addAction' => 'openPelangganBaru',
    'results' => collect(),                  // hasil pencarian dari komponen induk
])

{{-- [T-18] Komponen reusable pencarian + tambah pelanggan (dipakai POS & Servis terima unit) --}}
<div class="relative">
    <div class="flex gap-2">
        <input
            type="text"
            wire:model.live.debounce.250ms="{{ $wireModel }}"
            placeholder="{{ $searchPlaceholder }}"
            class="w-full px-3 py-2 rounded-xl glass-input text-xs font-medium"
        />
        @if($showAddButton)
            <button
                wire:click="{{ $addAction }}"
                class="px-3 py-2 rounded-xl bg-up-primary/15 hover:bg-up-primary/25 text-up-primary border border-up-primary/30 text-[11px] font-bold whitespace-nowrap"
                title="Tambah Pelanggan Baru (sinkron ke CRM/pos/servis)"
            >+ Baru</button>
        @endif
    </div>

    @if($results->isNotEmpty())
        <div class="absolute z-20 mt-1 w-full glass-panel rounded-xl overflow-hidden text-xs">
            @foreach($results as $pc)
                <button
                    wire:click="{{ $selectAction }}({{ $pc->id }}); $set('{{ $wireModel }}', '')"
                    class="w-full text-left px-3 py-2 hover:bg-white/5 text-ink-200 cursor-pointer"
                >
                    <span class="font-semibold">{{ $pc->nama }}</span>
                    <span class="text-ink-400 font-mono ml-1">{{ $pc->telepon }}</span>
                    @if($pc->tierMembership?->nama)
                        <span class="text-[9px] text-up-accent ml-1">{{ $pc->tierMembership->nama }}</span>
                    @endif
                </button>
            @endforeach
        </div>
    @endif
</div>