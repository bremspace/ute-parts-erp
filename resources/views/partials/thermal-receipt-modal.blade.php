{{-- [T-35] Modal Struk Thermal 58mm / 80mm Reusable (POS Kasir & Servis) --}}
@if($showReceiptModal && $receiptData)
    @php
        $strukConfig = app(\App\Modules\Crm\Services\KonfigurasiService::class)->getStrukConfig();
        $showQrRetur = $strukConfig['show_qr_retur'] ?? true;
        $qrSize = \App\Modules\Pos\Services\EscPosWriter::effectiveQrSize($strukConfig['qr_size'] ?? 3);
        $qrPx = $qrSize * 12; // module 3 → 36px di preview
        $qrCaption = $strukConfig['qr_caption'] ?? 'Scan QR ini untuk retur & klaim garansi';
        $defaultPaperSize = str_contains($strukConfig['default_width'] ?? '', '80') ? '80' : '58';
        $autoPrint = !empty($strukConfig['auto_print']) || !empty($strukConfig['thermal_auto_print']);
        $qrSvg = (!empty($receiptData['no_transaksi']) && $showQrRetur)
            ? app(\App\Modules\Wms\Services\QrCodeService::class)->generateSvg($receiptData['no_transaksi'], $qrPx, 2)
            : '';

        // Preserve pelanggan's phone/address before merging so template
        // config (toko phone/address) is not overwritten by receipt data.
        $receiptSafe = $receiptData;
        if (isset($receiptSafe['telepon'])) {
            $receiptSafe['telepon_pelanggan'] = $receiptSafe['telepon'];
            unset($receiptSafe['telepon']);
        }
        if (isset($receiptSafe['alamat'])) {
            $receiptSafe['alamat_pelanggan'] = $receiptSafe['alamat'];
            unset($receiptSafe['alamat']);
        }

        // Merge order: strukConfig (template) first, then receipt transaction
        // data (minus colliding keys), then explicit overrides.
        // Template display keys that must always come from settings:
        $templateKeys = ['nama_toko','slogan','alamat','telepon','website_medsos',
            'show_cabang','show_kasir','show_pelanggan','show_waktu','show_subtotal_breakdown',
            'show_qr_retur','qr_size','qr_caption','footer_text','kebijakan_retur','catatan_kaki',
            'default_width','font_size','thermal_printer_name','thermal_bluetooth_mac','thermal_auto_print'];
        // After merging receiptSafe on top, re-apply template keys so settings always win for display.
        $mergedData = array_merge($strukConfig, $receiptSafe, ['qr_svg' => $qrSvg, 'paper_width' => (int) ($defaultPaperSize === '80' ? 80 : 58)]);
        foreach ($templateKeys as $tk) {
            if (array_key_exists($tk, $strukConfig)) {
                $mergedData[$tk] = $strukConfig[$tk];
            }
        }
    @endphp
    <script id="pos-receipt-data" type="application/json">
        {!! json_encode($mergedData, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) !!}
    </script>
    <div
        class="fixed inset-0 z-[80] flex items-center justify-center bg-black/80 backdrop-blur-sm p-4 overflow-y-auto"
        x-data="{
            ...thermalPrinter({{ \Illuminate\Support\Js::from($mergedData) }}),
            paperSize: (() => {
                const templateDefault = '{{ $defaultPaperSize }}';
                const lastTemplate = localStorage.getItem('ute_pos_template_width');
                if (lastTemplate !== templateDefault) {
                    localStorage.setItem('ute_pos_template_width', templateDefault);
                    localStorage.setItem('ute_pos_paper_size', templateDefault);
                    return templateDefault;
                }
                return localStorage.getItem('ute_pos_paper_size') || templateDefault;
            })(),
            autoPrint: {{ $autoPrint ? 'true' : 'false' }},
            get paperSizeNum() { return this.paperSize === '80' ? 80 : 58; },
            setPaperSize(size) {
                this.paperSize = size;
                localStorage.setItem('ute_pos_paper_size', size);
            },
            get previewHtml() {
                if (!this.data) return '';
                if (typeof window.EscPos !== 'function') {
                    return 'Memuat pratinjau…';
                }
                const esc = new window.EscPos(this.paperSizeNum);
                return esc.buildPreviewHtml(this.data, this.paperSizeNum);
            }
        }"
    >
        <div class="w-full max-w-sm glass-panel p-3 sm:p-5 rounded-3xl border border-white/10 shadow-2xl relative my-auto max-h-[95vh] flex flex-col">
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

            <!-- Preview Struk Kertas Thermal (Rendered via JS EscPos for exact 1:1 print parity) -->
            <div class="overflow-y-auto pr-1 flex-1 flex justify-center py-1">
                <div class="thermal-receipt-container thermal-receipt-preview bg-white text-black rounded-xl font-mono text-[10px] shadow-md select-text transition-all duration-200 border border-gray-200"
                     :class="paperSize === '58' ? 'px-2.5 py-3' : 'p-3.5'"
                     id="thermalReceipt"
                     :style="paperSize === '58' ? 'width: 216px; max-width: 100%; margin: 0 auto;' : 'width: 302px; max-width: 100%; margin: 0 auto;'"
                     x-html="previewHtml">
                    <div class="thermal-receipt-body" style="width: 100%; padding: 10px 0; text-align: center; color: #6b7280; font-size: 9px;">Memuat pratinjau…</div>
                </div>
            </div>

            <!-- Tombol Aksi Cetak & Tutup (Sederhana, Bersih & Presisi) -->
            <div class="mt-3 pt-3 border-t border-white/10 space-y-2 flex-shrink-0 no-print">
                <!-- Status printer default (ringkas) -->
                <div class="flex items-center gap-2 px-3 py-1.5 rounded-xl bg-white/[0.03] border border-white/10 text-[11px]">
                    <span class="w-2 h-2 rounded-full shrink-0" :class="pairedPrinterName ? 'bg-up-mint animate-pulse' : 'bg-ink-500'"></span>
                    <span class="truncate text-ink-300">
                        Printer: <strong class="text-white" x-text="pairedPrinterName || data.thermal_printer_name || 'Belum dipilih'"></strong>
                    </span>
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

                <!-- Tombol aksi: 1 cetak, 1 pilih printer -->
                <div class="flex items-center gap-2">
                    <button
                        type="button"
                        @click="directPrint(paperSize)"
                        :disabled="isPrinting"
                        class="flex-1 py-2.5 px-3 rounded-xl bg-gradient-to-r from-up-mint to-teal-500 hover:opacity-95 text-ink-950 font-black text-xs shadow-md shadow-up-mint/20 cursor-pointer min-h-[42px] flex items-center justify-center gap-2 whitespace-nowrap transition active:scale-[0.98] disabled:opacity-50"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
                        <span x-text="isPrinting ? 'Mencetak...' : 'Cetak Struk'"></span>
                    </button>
                    <button
                        type="button"
                        @click="choosePrinter()"
                        :disabled="isPrinting"
                        class="py-2.5 px-3 rounded-xl bg-white/5 hover:bg-white/10 text-ink-200 font-semibold text-xs border border-white/10 cursor-pointer min-h-[42px] whitespace-nowrap transition active:scale-[0.98] disabled:opacity-50"
                        title="Pilih printer Bluetooth dan jadikan default"
                    >
                        <span x-text="(pairedPrinterName || data.thermal_printer_name) ? 'Ganti Printer' : 'Pilih Printer'"></span>
                    </button>
                </div>

                @php
                    $waTelepon = $mergedData['telepon_pelanggan'] ?? $receiptData['telepon_pelanggan'] ?? $receiptData['telepon'] ?? null;
                    $waDraft = \App\Support\WhatsAppHelper::draftStrukPos($mergedData);
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
