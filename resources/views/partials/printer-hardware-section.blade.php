{{-- Reusable Thermal Printer Hardware section --}}
{{-- Usage: @include('partials.printer-hardware-section', ['form' => 'strukForm']) --}}
@php
    $f = $form ?? 'strukForm';
    $label = $label ?? 'Cetak Dokumen';
@endphp
<div class="pt-4 border-t border-white/5 space-y-3">
    <h4 class="text-xs font-bold text-white uppercase tracking-wider flex items-center gap-2">
        <span class="w-2 h-2 rounded-full bg-up-cyan"></span>
        <span>Thermal Printer Hardware (CUPS / Bluetooth)</span>
    </h4>
    <p class="text-[11px] text-ink-400">Konfigurasi printer fisik untuk {{ $label }}. Gunakan nama printer CUPS (Linux/Windows) atau MAC Bluetooth.</p>

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
        <div>
            <label class="block text-xs font-semibold text-ink-300 mb-1">Nama Printer CUPS / Windows</label>
            <div class="flex gap-2">
                <select wire:model.live="{{ $f }}.thermal_printer_name"
                    class="flex-1 px-3 py-2 rounded-xl glass-input text-xs text-white bg-ink-900/60 border border-white/10">
                    <option value="">-- Pilih printer terdeteksi --</option>
                    @foreach($detectedPrinters as $pName)
                        <option value="{{ $pName }}" class="bg-ink-900 text-white">{{ $pName }}</option>
                    @endforeach
                </select>
                <button type="button" wire:click="deteksiPrinter" wire:loading.attr="disabled"
                    class="px-3 py-2 rounded-xl bg-up-primary hover:bg-up-primary/90 text-white text-xs font-bold cursor-pointer transition active:scale-95 disabled:opacity-50 whitespace-nowrap">
                    <span wire:loading.remove wire:target="deteksiPrinter">Deteksi</span>
                    <span wire:loading wire:target="deteksiPrinter">...</span>
                </button>
            </div>
            <p class="text-[10px] text-ink-400 mt-1">Klik "Deteksi" untuk scan printer terinstall. Atau ketik manual di bawah.</p>
            <input type="text"
                wire:model.live.debounce.300ms="{{ $f }}.thermal_printer_name"
                class="w-full mt-1.5 px-3 py-2 rounded-xl glass-input text-xs text-white"
                placeholder="Atau ketik nama printer manual: POS-58, TM-T20II, ..."
                title="Nama printer seperti yang terdaftar di sistem">
            @error($f . '.thermal_printer_name') <span class="text-[10px] text-up-red">{{ $message }}</span> @enderror
        </div>

        <div>
            <label class="block text-xs font-semibold text-ink-300 mb-1">MAC Address Bluetooth (Web Bluetooth)</label>
            <input type="text"
                wire:model.live.debounce.300ms="{{ $f }}.thermal_bluetooth_mac"
                class="w-full px-3 py-2 rounded-xl glass-input text-xs text-white font-mono"
                placeholder="Contoh: AA:BB:CC:DD:EE:FF"
                title="Format: XX:XX:XX:XX:XX:XX atau XX-XX-XX-XX-XX-XX">
            <p class="text-[10px] text-ink-400 mt-1">Dapatkan dari pengaturan Bluetooth printer atau halaman Pairing POS</p>
            @error($f . '.thermal_bluetooth_mac') <span class="text-[10px] text-up-red">{{ $message }}</span> @enderror
        </div>
    </div>

    <div class="flex items-center gap-2 p-2.5 rounded-xl border border-white/5 bg-white/[0.02]">
        <input type="checkbox" wire:model.live="{{ $f }}.thermal_auto_print" class="rounded border-white/20 text-up-primary focus:ring-up-primary/30 w-4 h-4">
        <label class="text-xs font-medium text-white cursor-pointer">
            <strong>Auto-Print</strong> — Cetak otomatis setelah proses berhasil
        </label>
    </div>

    @if($f === 'strukForm')
        <div>
            <label class="block text-xs font-semibold text-ink-300 mb-1">Mode Cetak Struk</label>
            <select wire:model.live="{{ $f }}.thermal_print_mode" class="w-full px-3 py-2 rounded-xl glass-input text-xs text-white bg-ink-900/60 border border-white/10">
                <option value="browser" class="bg-ink-900">Browser Print (dialog printer Windows) — paling stabil</option>
                <option value="direct" class="bg-ink-900">Direct Print (BLE / Serial / USB / CUPS)</option>
            </select>
            <p class="text-[10px] text-ink-400 mt-1">Pilih Browser Print agar sistem memakai dialog printer Windows yang sudah terinstall.</p>
        </div>
    @endif
</div>