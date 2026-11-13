{{-- [T-35] Modal Struk Thermal 58mm / 80mm Reusable (POS Kasir & Servis) --}}
@if($showReceiptModal && $receiptData)
    @php
        $strukConfig = app(\App\Modules\Crm\Services\KonfigurasiService::class)->getStrukConfig();
        $showQrRetur = $strukConfig['show_qr_retur'] ?? true;
        $qrSize = (int) ($strukConfig['qr_size'] ?? 4);
        $qrCaption = $strukConfig['qr_caption'] ?? 'Scan QR untuk Retur & Garansi';
        $defaultPaperSize = str_contains($strukConfig['default_width'] ?? '', '80') ? '80' : '58';
        $autoPrint = !empty($strukConfig['auto_print']);
    @endphp
    <script id="pos-receipt-data" type="application/json">
        {!! json_encode($receiptData, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) !!}
    </script>
    <div
        class="fixed inset-0 z-[80] flex items-center justify-center bg-black/80 backdrop-blur-sm p-4 overflow-y-auto"
        x-data="{
            ...thermalPrinter({{ \Illuminate\Support\Js::from(array_merge($strukConfig, $receiptData)) }}),
            paperSize: localStorage.getItem('ute_pos_paper_size') || '{{ $defaultPaperSize }}',
            autoPrint: {{ $autoPrint ? 'true' : 'false' }},
            setPaperSize(size) {
                this.paperSize = size;
                localStorage.setItem('ute_pos_paper_size', size);
            },
            get previewHtml() {
                if (!this.data) return '';
                const paperWidth = this.paperSize === '80' ? 80 : 58;
                if (typeof window.EscPos === 'function') {
                    const esc = new window.EscPos(paperWidth);
                    return esc.buildPreviewHtml(this.data);
                }
                return '';
            }
        }"
        x-init="
            if (autoDirectPrint || autoPrint) {
                setTimeout(() => { directPrint(paperSize); }, 350);
            }
        "
    >
        <div class="w-full max-w-sm glass-panel p-5 rounded-3xl border border-white/10 shadow-2xl relative my-auto max-h-[95vh] flex flex-col">
            <!-- Header Modal -->
            <div class="flex items-center justify-between pb-3 mb-3 border-b border-white/10 flex-shrink-0">
                <div class="flex items-center gap-2">
                    <span class="w-2.5 h-2.5 rounded-full bg-up-mint animate-pulse"></span>
                    <h4 class="text-sm font-bold text-white">Struk Transaksi Selesai</h4>
                </div>
                <div class="flex items-center gap-2">
                    @if(!empty($completedTransactionId) && auth()->user()?->can('lihat-audit-log'))
                        <button
                            type="button"
                            wire:click="bukaRiwayat('transaksi', {{ $completedTransactionId }})"
                            class="px-2 py-1 rounded-lg bg-white/5 hover:bg-white/10 border border-white/10 text-ink-300 font-bold text-[10px] cursor-pointer"
                        >
                            Audit
                        </button>
                    @endif
                    <button type="button" wire:click="$set('showReceiptModal', false)" class="text-ink-400 hover:text-white cursor-pointer p-1">✕</button>
                </div>
            </div>

            <!-- Preview Struk Kertas Thermal (Rendered via JS EscPos for exact print parity) -->
            <div class="overflow-y-auto pr-1 flex-1">
                <div class="thermal-receipt-container thermal-receipt-preview bg-white text-black p-4 rounded-2xl font-mono text-xs shadow-inner space-y-2 select-text transition-all duration-200"
                     id="thermalReceipt"
                     :style="paperSize === '58' ? 'max-width: 210px; margin: 0 auto;' : 'max-width: 302px; margin: 0 auto;'"
                     x-html="previewHtml">
                </div>
            </div>

            <!-- Tombol Aksi Cetak & Tutup (Sederhana, Bersih & Presisi) -->
            <div class="mt-3 pt-3 border-t border-white/10 space-y-2 flex-shrink-0 no-print">
                <!-- Status Bluetooth Printer jika terhubung -->
                <template x-if="pairedPrinterName">
                    <div class="flex items-center justify-between px-3 py-1.5 rounded-xl bg-up-mint/10 border border-up-mint/20 text-[11px] text-up-mint">
                        <span class="flex items-center gap-1.5 truncate">
                            <span class="w-2 h-2 rounded-full bg-up-mint animate-pulse"></span>
                            <span class="truncate">Printer Aktif: <strong x-text="pairedPrinterName"></strong></span>
                        </span>
                        <button
                            type="button"
                            @click="disconnectPrinter()"
                            class="text-ink-400 hover:text-white underline text-[10px] ml-2 shrink-0 cursor-pointer"
                        >
                            Ganti
                        </button>
                    </div>
                </template>

                <!-- Toggle Opsi Cetak Langsung (Direct Print) -->
                <div class="flex items-center justify-between px-3 py-2 rounded-xl bg-white/5 border border-white/10 text-xs">
                    <div class="flex flex-col">
                        <span class="text-white font-medium text-[11px] flex items-center gap-1.5">
                            <svg class="w-3.5 h-3.5 text-up-mint" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                            Cetak Langsung Struk
                        </span>
                        <span class="text-[10px] text-ink-400">Otomatis kirim ke printer tersambung</span>
                    </div>
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" :checked="autoDirectPrint" @change="toggleAutoDirectPrint()" class="sr-only peer">
                        <div class="w-9 h-5 bg-ink-700 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-ink-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-up-mint"></div>
                    </label>
                </div>

                <!-- Pilihan Ukuran Struk (Segmented Control Ringkas) -->
                <div class="flex items-center justify-between p-1 bg-white/5 rounded-xl border border-white/10 text-xs">
                    <span class="text-ink-400 text-[11px] font-semibold pl-2">Format Kertas:</span>
                    <div class="flex items-center gap-1">
                        <button
                            type="button"
                            @click="setPaperSize('58')"
                            :class="paperSize === '58' ? 'bg-up-mint text-ink-950 font-bold shadow-sm' : 'text-ink-300 hover:text-white'"
                            class="px-3 py-1 rounded-lg text-xs transition cursor-pointer"
                        >
                            58mm (Kecil)
                        </button>
                        <button
                            type="button"
                            @click="setPaperSize('80')"
                            :class="paperSize === '80' ? 'bg-up-mint text-ink-950 font-bold shadow-sm' : 'text-ink-300 hover:text-white'"
                            class="px-3 py-1 rounded-lg text-xs transition cursor-pointer"
                        >
                            80mm (Lebar)
                        </button>
                    </div>
                </div>

                <!-- Tombol Cetak Utama & Bluetooth BLE -->
                <div class="flex gap-2">
                    <button
                        type="button"
                        @click="directPrint(paperSize)"
                        :disabled="isPrinting"
                        class="flex-1 py-2.5 rounded-xl bg-gradient-to-r from-up-mint to-teal-500 hover:opacity-95 text-ink-950 font-black text-xs shadow-md shadow-up-mint/20 cursor-pointer min-h-[42px] flex items-center justify-center gap-2 transition active:scale-[0.98] disabled:opacity-50"
                        :title="'Cetak struk langsung ke printer aktif'"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
                        <span x-text="isPrinting ? 'Mencetak...' : 'Cetak Struk (' + paperSize + 'mm)'"></span>
                    </button>

                    <button
                        type="button"
                        @click="paperSize === '58' ? printStruk58(true) : printFaktur80(true)"
                        :disabled="isPrinting"
                        class="px-3.5 py-2.5 rounded-xl bg-white/5 hover:bg-white/10 text-up-mint font-semibold text-xs border border-up-mint/30 cursor-pointer min-h-[42px] flex items-center justify-center gap-1.5 transition active:scale-[0.98] disabled:opacity-50"
                        :title="'Pilih / pasangkan printer Bluetooth baru (' + paperSize + 'mm)'"
                    >
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                        <span x-text="isPrinting ? '...' : (pairedPrinterName ? 'Pair Baru' : 'Pilih BLE')"></span>
                    </button>
                </div>

                @php
                    $waTelepon = $receiptData['telepon'] ?? $receiptData['telepon_pelanggan'] ?? null;
                    $waDraft = \App\Support\WhatsAppHelper::draftStrukPos($receiptData);
                    $waLink = \App\Support\WhatsAppHelper::buatLink($waTelepon, $waDraft);
                @endphp

                <!-- Tombol Kirim WhatsApp Direct -->
                @if($waLink)
                    <a
                        href="{{ $waLink }}"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="w-full py-2.5 rounded-xl bg-[#25D366] hover:bg-[#20ba59] text-white font-bold text-xs shadow-md shadow-[#25D366]/20 cursor-pointer min-h-[40px] flex items-center justify-center gap-2 transition active:scale-[0.98] no-underline"
                        title="Buka WhatsApp dengan draft pesan struk pembelian siap kirim"
                    >
                        <svg class="w-4 h-4 fill-current" viewBox="0 0 24 24"><path d="M12.031 6.172c-3.181 0-5.767 2.586-5.768 5.766-.001 1.298.38 2.27 1.019 3.287l-.582 2.128 2.182-.573c.978.58 1.911.928 3.145.929 3.178 0 5.767-2.587 5.768-5.766.001-3.187-2.575-5.77-5.764-5.771zm3.392 8.244c-.144.405-.837.774-1.17.824-.299.045-.677.063-1.092-.069-.252-.08-.575-.187-.988-.365-1.739-.751-2.874-2.502-2.961-2.617-.087-.116-.708-.94-.708-1.793s.448-1.273.607-1.446c.159-.173.346-.217.462-.217l.332.006c.106.005.249-.04.39.298.144.347.491 1.2.534 1.287.043.087.072.188.014.304-.058.116-.087.188-.173.289l-.26.304c-.087.086-.177.18-.076.354.101.174.449.741.964 1.201.662.591 1.221.774 1.394.86.174.086.275.073.376-.044.101-.116.433-.506.549-.68.116-.173.231-.144.39-.086s1.011.477 1.184.564.289.13.332.203c.043.072.043.419-.101.824z"/></svg>
                        <span>Kirim Struk via WhatsApp</span>
                    </a>
                @endif

                <!-- Tombol Tutup -->
                <button
                    type="button"
                    wire:click="$set('showReceiptModal', false)"
                    class="w-full py-2 rounded-xl bg-white/5 hover:bg-white/10 text-ink-400 hover:text-white font-medium text-xs cursor-pointer transition-colors"
                >
                    Tutup
                </button>
            </div>
        </div>
    </div>
@endif