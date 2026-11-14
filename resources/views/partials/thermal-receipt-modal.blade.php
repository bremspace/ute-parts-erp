{{-- [T-35] Modal Struk Thermal 58mm / 80mm Reusable (POS Kasir & Servis) --}}
@if($showReceiptModal && $receiptData)
    @php
        $strukConfig = app(\App\Modules\Crm\Services\KonfigurasiService::class)->getStrukConfig();
        $showQrRetur = $strukConfig['show_qr_retur'] ?? true;
        $qrSize = (int) ($strukConfig['qr_size'] ?? 4);
        $qrCaption = $strukConfig['qr_caption'] ?? 'Scan QR ini untuk retur & klaim garansi';
        $defaultPaperSize = str_contains($strukConfig['default_width'] ?? '', '80') ? '80' : '58';
        $autoPrint = !empty($strukConfig['auto_print']) || !empty($strukConfig['thermal_auto_print']);
        $qrSvg = (!empty($receiptData['no_transaksi']) && $showQrRetur)
            ? app(\App\Modules\Wms\Services\QrCodeService::class)->generateSvg($receiptData['no_transaksi'], 68, 0)
            : '';
    @endphp
    <script id="pos-receipt-data" type="application/json">
        {!! json_encode($receiptData, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) !!}
    </script>
    <div
        class="fixed inset-0 z-[80] flex items-center justify-center bg-black/80 backdrop-blur-sm p-4 overflow-y-auto"
        x-data="{
            ...thermalPrinter({{ \Illuminate\Support\Js::from(array_merge($strukConfig, $receiptData, ['qr_svg' => $qrSvg, 'qr_caption' => $qrCaption])) }}),
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

            <!-- Preview Struk Kertas Thermal (Rendered via JS EscPos for exact 1:1 print parity) -->
            <div class="overflow-y-auto pr-1 flex-1 flex justify-center py-1">
                <div class="thermal-receipt-container thermal-receipt-preview bg-white text-black p-3 rounded-xl font-mono text-[10px] shadow-md select-text transition-all duration-200 border border-gray-200"
                     id="thermalReceipt"
                     :style="paperSize === '58' ? 'width: 210px; max-width: 210px; margin: 0 auto;' : 'width: 302px; max-width: 302px; margin: 0 auto;'"
                     x-html="previewHtml">
                    <div class="thermal-receipt-body" style="width: 100%; font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace; font-size: 10px; line-height: 1.25; color: #000; background: #fff;">
                        <!-- HEADER (Centered) -->
                        <div style="text-align: center; padding-bottom: 2px;">
                            <div style="font-size: 14px; font-weight: 900; letter-spacing: 0.05em; text-transform: uppercase;">{{ $receiptData['nama_toko'] ?? $strukConfig['nama_toko'] ?? 'UTEParts' }}</div>
                            <div style="font-size: 9px; font-weight: 600; text-transform: uppercase; margin-top: 2px;">{{ $receiptData['slogan'] ?? $strukConfig['slogan'] ?? 'SPAREPARTS/ACCESORIES/SERVICE' }}</div>
                            @if(!empty($receiptData['alamat'] ?? $strukConfig['alamat'] ?? null))
                                <div style="font-size: 8px; color: #374151; margin-top: 2px; line-height: 1.2;">{{ $receiptData['alamat'] ?? $strukConfig['alamat'] }}</div>
                            @endif
                            @if(!empty($receiptData['telepon'] ?? $strukConfig['telepon'] ?? null))
                                <div style="font-size: 8.5px; color: #1f2937;">Telp/WA: {{ $receiptData['telepon'] ?? $strukConfig['telepon'] }}</div>
                            @endif
                        </div>

                        <!-- 2-COL: QR (Left) + URL/Date/Warranty Note (Right) -->
                        <div style="display: flex; flex-direction: row; align-items: center; justify-content: space-between; gap: 8px; padding: 4px 0;">
                            @if($showQrRetur && !empty($qrSvg))
                                <div style="flex-shrink: 0; display: flex; align-items: center; justify-content: center;">
                                    {!! $qrSvg !!}
                                </div>
                            @endif
                            <div style="flex: 1; text-align: left; font-size: 8.5px; line-height: 1.25;">
                                <div style="font-weight: 600; color: #111827;">{{ $receiptData['qr_url'] ?? $strukConfig['qr_url'] ?? 'www.uteparts.id' }}</div>
                                @if(!empty($receiptData['tanggal'] ?? $receiptData['waktu'] ?? null))
                                    <div style="color: #374151;">{{ $receiptData['tanggal'] ?? $receiptData['waktu'] }}</div>
                                @endif
                                <div style="font-size: 8px; color: #4b5563; margin-top: 2px;">{{ $qrCaption }}</div>
                            </div>
                        </div>

                        <div style="border-top: 1px dashed #000; margin: 4px 0;"></div>

                        <!-- TRANSACTION INFO (Left-Right) -->
                        <div style="font-size: 9px; line-height: 1.3;">
                            <div style="display: flex; justify-content: space-between;">
                                <span style="color: #4b5563;">No. TRX:</span>
                                <span style="font-weight: 700; color: #111827;">{{ $receiptData['no_transaksi'] ?? '-' }}</span>
                            </div>
                            @if(!empty($receiptData['sumber']) && !in_array(strtolower($receiptData['sumber']), ['pos', 'offline']))
                                <div style="display: flex; justify-content: space-between;">
                                    <span style="color: #4b5563;">Pesanan:</span>
                                    <span>{{ strtoupper($receiptData['sumber']) }}</span>
                                </div>
                            @endif
                            @if(!empty($receiptData['no_tiket']))
                                <div style="display: flex; justify-content: space-between;">
                                    <span style="color: #4b5563;">No. Tiket:</span>
                                    <span style="font-weight: 700;">{{ $receiptData['no_tiket'] }}</span>
                                </div>
                            @endif
                            @if(!empty($receiptData['jenis_hp']))
                                <div style="display: flex; justify-content: space-between;">
                                    <span style="color: #4b5563;">Unit:</span>
                                    <span>{{ $receiptData['jenis_hp'] }}</span>
                                </div>
                            @endif
                            @if(!empty($receiptData['kasir']))
                                <div style="display: flex; justify-content: space-between;">
                                    <span style="color: #4b5563;">Kasir:</span>
                                    <span>{{ $receiptData['kasir'] }}</span>
                                </div>
                            @endif
                            @if(!empty($receiptData['pelanggan']))
                                <div style="display: flex; justify-content: space-between;">
                                    <span style="color: #4b5563;">Pelanggan:</span>
                                    <span>{{ $receiptData['pelanggan'] }}</span>
                                </div>
                            @endif
                        </div>

                        <!-- ITEMS -->
                        <div style="margin: 4px 0;">
                            @foreach($receiptData['items'] ?? [] as $item)
                                @php
                                    $qtySatuan = ($item['qty'] ?? 1) . (!empty($item['satuan']) ? ' ' . $item['satuan'] : ' PCS');
                                    $hargaJual = (float)($item['harga'] ?? 0);
                                    $hargaRetail = (float)($item['harga_retail'] ?? 0);
                                @endphp
                                <div style="margin-bottom: 4px;">
                                    <div style="font-weight: 700; font-size: 9.5px; line-height: 1.2; word-break: break-word;">{{ $item['nama'] ?? 'Item' }}</div>
                                    <div style="display: flex; justify-content: space-between; align-items: baseline; font-size: 9px; margin-top: 1px;">
                                        <span>
                                            {{ $qtySatuan }} x
                                            @if($hargaRetail > $hargaJual)
                                                <span style="text-decoration: line-through; opacity: 0.6; margin-right: 2px;">Rp {{ number_format($hargaRetail, 0, ',', '.') }}</span>
                                                <span style="font-weight: 700;">Rp {{ number_format($hargaJual, 0, ',', '.') }}</span>
                                            @else
                                                <span>Rp {{ number_format($hargaJual, 0, ',', '.') }}</span>
                                            @endif
                                        </span>
                                        <span style="font-weight: 600; text-align: right; margin-left: 4px;">{{ number_format((float)($item['subtotal'] ?? 0), 0, ',', '.') }}</span>
                                    </div>
                                    @if(!empty($item['catatan']))
                                        <div style="font-size: 8px; color: #4b5563; font-style: italic; margin-top: 1px;">* {{ $item['catatan'] }}</div>
                                    @endif
                                </div>
                            @endforeach
                        </div>

                        <!-- TOTALS (Directly follows items) -->
                        <div style="width: 100%; font-size: 9px; line-height: 1.35;">
                            <div style="display: flex; justify-content: space-between; width: 100%;">
                                <span style="color: #4b5563;">Subtotal:</span>
                                <span>Rp {{ number_format((float)($receiptData['subtotal'] ?? $receiptData['total'] ?? 0), 0, ',', '.') }}</span>
                            </div>
                            <div style="display: flex; justify-content: space-between; align-items: baseline; width: 100%; font-size: 13px; font-weight: 900; padding: 2px 0;">
                                <span>TOTAL:</span>
                                <span>Rp {{ number_format((float)($receiptData['total'] ?? 0), 0, ',', '.') }}</span>
                            </div>
                            <div style="display: flex; justify-content: space-between; width: 100%;">
                                <span style="color: #4b5563;">BAYAR ({{ strtoupper($receiptData['metode_bayar'] ?? $receiptData['metode'] ?? 'TUNAI') }}):</span>
                                <span style="font-weight: 600;">Rp {{ number_format((float)($receiptData['bayar'] ?? $receiptData['total'] ?? 0), 0, ',', '.') }}</span>
                            </div>
                            <div style="display: flex; justify-content: space-between; width: 100%;">
                                <span style="color: #4b5563;">KEMBALI:</span>
                                <span style="font-weight: 600;">Rp {{ number_format((float)($receiptData['kembali'] ?? 0), 0, ',', '.') }}</span>
                            </div>
                            @if(isset($receiptData['saldo_poin']))
                                <div style="display: flex; justify-content: space-between; width: 100%;">
                                    <span style="color: #4b5563;">Sisa Saldo Poin:</span>
                                    <span>{{ number_format((float)$receiptData['saldo_poin'], 0, ',', '.') }} pts</span>
                                </div>
                            @endif
                        </div>

                        <div style="border-top: 1px dashed #000; width: 100%; margin: 4px 0;"></div>

                        <!-- FOOTER (Centered) -->
                        <div style="text-align: center; width: 100%; font-size: 8.5px; color: #374151; line-height: 1.3; padding-top: 2px;">
                            @if(!empty($receiptData['garansi']))
                                <div style="font-weight: 700; color: #111827;">Garansi: {{ $receiptData['garansi'] }}</div>
                            @endif
                            <div>{{ $receiptData['footer_text'] ?? $strukConfig['footer_text'] ?? 'Terima kasih atas kunjungan Anda!' }}</div>
                            <div style="font-size: 8px; color: #4b5563;">{{ $receiptData['kebijakan_retur'] ?? $strukConfig['kebijakan_retur'] ?? 'Segel toko wajib utuh & fisik barang tidak cacat.' }}</div>
                        </div>
                    </div>
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