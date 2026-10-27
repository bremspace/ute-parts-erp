@props([
    'chips' => [],
    'resetAction' => 'resetSingleFilter',
    'resetAllAction' => 'resetAllFilters',
    'labelPrefix' => 'Filter Aktif:',
])

@if(!empty($chips))
    <div {{ $attributes->merge(['class' => 'flex items-center gap-1.5 flex-wrap py-1.5']) }}>
        <span class="text-[11px] font-semibold text-ink-500 dark:text-ink-400 flex items-center gap-1 shrink-0">
            <svg class="w-3.5 h-3.5 text-up-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z" />
            </svg>
            {{ $labelPrefix }}
        </span>

        @foreach($chips as $chip)
            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-xs font-medium bg-up-primary/10 hover:bg-up-primary/15 text-up-primary border border-up-primary/20 transition-all shadow-xs">
                <span>{{ $chip['label'] }}</span>
                <button
                    type="button"
                    wire:click="{{ $chip['action'] ?? ($resetAction . '(\'' . $chip['key'] . '\')') }}"
                    class="ml-0.5 p-0.5 rounded-full hover:bg-up-primary/20 text-up-primary hover:text-up-red transition cursor-pointer"
                    title="Hapus filter {{ $chip['label'] }}"
                >
                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </span>
        @endforeach

        @if(!empty($resetAllAction))
            <button
                type="button"
                wire:click="{{ $resetAllAction }}"
                class="inline-flex items-center gap-1 px-2 py-1 rounded-lg text-xs font-semibold text-up-red hover:bg-up-red/10 transition cursor-pointer"
                title="Reset semua filter aktif"
            >
                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                </svg>
                <span>Reset Semua</span>
            </button>
        @endif
    </div>
@endif
