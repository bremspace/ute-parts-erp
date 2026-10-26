@props([
    'placeholder' => 'Scan Barcode / QR atau ketik nama/SKU (F2)...',
    'model' => null,
    'continuous' => false,
    'title' => 'Scan Barcode / QR Produk',
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
    <!-- Left Icon / Scan Trigger: Click to open Camera Scanner -->
    <button
        type="button"
        @click="openCamera()"
        class="absolute left-2.5 p-1 rounded-lg text-up-primary hover:text-white hover:bg-up-primary/20 active:scale-95 transition-[transform,background-color,color] duration-150 flex items-center justify-center cursor-pointer z-10"
        title="Buka Kamera HP / Web untuk scan barcode (atau klik icon)"
        aria-label="Scan barcode kamera"
    >
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z" />
        </svg>
    </button>

    <!-- Input Box: text-base on mobile to avoid iOS Safari auto-zoom -->
    <input
        type="text"
        data-barcode-input="true"
        placeholder="{{ $placeholder }}"
        {{ $model ? "wire:model.live.debounce.350ms={$model}" : '' }}
        {{ $attributes->merge(['class' => 'w-full pl-11 pr-10 py-2.5 rounded-xl glass-input text-base sm:text-sm font-medium placeholder-ink-400 focus:ring-2 focus:ring-up-primary/30 transition-[border-color,box-shadow,background-color] duration-140']) }}
        x-ref="barcodeInput"
        @keydown.window.f2.prevent="$refs.barcodeInput.focus(); $refs.barcodeInput.select()"
    />

    <!-- Right Action Toolbar: F2 Badge (Desktop Only) -->
    <div class="absolute right-2.5 flex items-center gap-1.5 pointer-events-none">
        <div class="hidden sm:flex items-center gap-1 text-[11px] font-mono text-ink-400 bg-white/5 border border-white/10 px-1.5 py-0.5 rounded">
            <span>F2</span>
        </div>
    </div>
</div>
