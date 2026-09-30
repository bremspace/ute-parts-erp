@props([
    'placeholder' => 'Scan Barcode atau ketik nama/SKU (F2)...',
    'model' => null,
    'continuous' => false,
    'title' => 'Scan Barcode Produk',
])

<div
    class="relative flex items-center w-full"
    x-data="{
        handleScannedCode(code) {
            const cleanCode = (code || '').trim();
            if (!cleanCode) return;

            if ($refs.barcodeInput) {
                $refs.barcodeInput.value = cleanCode;
            }

            // Jika Livewire memiliki handler scan terdedikasi (seperti PosKasir)
            if (typeof $wire !== 'undefined' && typeof $wire.scanBarcodeDirect === 'function') {
                $wire.scanBarcodeDirect(cleanCode);
                return;
            }

            @if($model)
                if (typeof $wire !== 'undefined') {
                    $wire.set('{{ $model }}', cleanCode);
                }
            @endif

            if ($refs.barcodeInput) {
                $refs.barcodeInput.dispatchEvent(new Event('input', { bubbles: true }));
                $nextTick(() => {
                    $refs.barcodeInput.dispatchEvent(new KeyboardEvent('keydown', {
                        key: 'Enter',
                        code: 'Enter',
                        keyCode: 13,
                        which: 13,
                        bubbles: true
                    }));
                });
            }
        },
        openCamera() {
            if (window.uteBarcode && window.uteBarcode.openCameraScanner) {
                window.uteBarcode.openCameraScanner({
                    title: '{{ addslashes($title) }}',
                    continuous: {{ $continuous ? 'true' : 'false' }},
                    onScan: (code) => {
                        this.handleScannedCode(code);
                    }
                });
            } else {
                alert('Modul barcode scanner sedang disiapkan...');
            }
        }
    }"
    @ute:barcode-scanned.window="handleScannedCode($event.detail.code)"
    @ute:barcode-not-found.window="if (window.uteBarcode) { window.uteBarcode.playBeep(false); window.uteBarcode.triggerHaptic(); }"
>
    <!-- Left Icon (Barcode/QR Icon) -->
    <div class="absolute left-3.5 text-up-primary pointer-events-none flex items-center">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z" />
        </svg>
    </div>

    <!-- Input Box -->
    <input
        type="text"
        data-barcode-input="true"
        placeholder="{{ $placeholder }}"
        {{ $model ? "wire:model.live.debounce.250ms={$model}" : '' }}
        {{ $attributes->merge(['class' => 'w-full pl-11 pr-20 py-2.5 rounded-xl glass-input text-base sm:text-sm font-medium placeholder-ink-400 focus:ring-2 focus:ring-up-primary/30 transition-all']) }}
        x-ref="barcodeInput"
        @keydown.window.f2.prevent="$refs.barcodeInput.focus(); $refs.barcodeInput.select()"
    />

    <!-- Right Action Toolbar: Camera Scanner Button & F2 Badge -->
    <div class="absolute right-2.5 flex items-center gap-1.5">
        <!-- Camera Scanner Trigger Button (Mobile / Web Camera) -->
        <button
            type="button"
            @click="openCamera()"
            class="px-2 py-1 rounded-lg bg-up-primary/20 hover:bg-up-primary/30 text-up-primary hover:text-white border border-up-primary/30 flex items-center gap-1 text-[11px] font-semibold cursor-pointer active:scale-95 transition-all shadow-sm"
            title="Buka Kamera HP / Web untuk scan barcode"
        >
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z" />
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z" />
            </svg>
            <span class="hidden xxs:inline">Scan</span>
        </button>

        <!-- F2 Keyboard Shortcut Badge (Desktop Only) -->
        <div class="hidden sm:flex items-center gap-1 text-[11px] font-mono text-ink-400 bg-white/5 border border-white/10 px-1.5 py-0.5 rounded pointer-events-none">
            <span>F2</span>
        </div>
    </div>
</div>
