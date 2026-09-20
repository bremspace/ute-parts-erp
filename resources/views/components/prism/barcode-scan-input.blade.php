@props([
    'placeholder' => 'Scan Barcode atau ketik nama/SKU (F2)...',
    'model' => null,
])

<div class="relative flex items-center w-full">
    <div class="absolute left-3.5 text-up-primary pointer-events-none flex items-center">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z" />
        </svg>
    </div>

    <input
        type="text"
        placeholder="{{ $placeholder }}"
        {{ $model ? "wire:model.live.debounce.250ms={$model}" : '' }}
        {{ $attributes->merge(['class' => 'w-full pl-11 pr-10 py-2.5 rounded-xl glass-input text-sm font-medium placeholder-ink-400']) }}
        x-ref="barcodeInput"
        @keydown.window.f2.prevent="$refs.barcodeInput.focus(); $refs.barcodeInput.select()"
    />

    <div class="absolute right-3 hidden sm:flex items-center gap-1 text-[11px] font-mono text-ink-400 bg-white/5 border border-white/10 px-1.5 py-0.5 rounded">
        <span>F2</span>
    </div>
</div>
