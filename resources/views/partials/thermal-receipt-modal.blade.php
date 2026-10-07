{{-- [T-35] Modal Struk Thermal 58mm / 80mm Reusable (POS Kasir & Servis) --}}
@if($showReceiptModal && $receiptData)
    <script id="pos-receipt-data" type="application/json">
        {!! json_encode($receiptData, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) !!}
    </script>
    <div
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 backdrop-blur-sm p-4 overflow-y-auto"
        x-data="thermalPrinter({{ \Illuminate\Support\Js::from($receiptData) }})"
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

            <!-- Preview Struk Kertas Thermal (Juga sebagai target print) -->
            <div class="overflow-y-auto pr-1 flex-1">
                <div class="thermal-receipt-container thermal-receipt-preview bg-white text-black p-4 rounded-2xl font-mono text-xs shadow-inner space-y-2 select-text" id="thermalReceipt">
                    <!-- Brand Header -->
                    <div class="receipt-center pb-2 border-b border-dashed border-gray-400">
                        <h3 class="receipt-title text-sm font-black tracking-wider text-gray-900">UTE PARTS</h3>
                        <p class="text-[10px] text-gray-600 font-medium">Pusat Sparepart & Servis HP</p>
                        @if(!empty($receiptData['cabang']))
                            <p class="text-[9px] text-gray-500">{{ $receiptData['cabang'] }}</p>
                        @endif
                        <p class="text-[9px] text-gray-500 mt-0.5">{{ $receiptData['waktu'] }}</p>
                    </div>

                    <!-- Meta Transaksi -->
                    <div class="text-[10px] space-y-0.5 border-b border-dashed border-gray-300 pb-2">
                        <div class="receipt-row flex justify-between">
                            <span class="text-gray-600">No. TRX:</span>
                            <span class="font-bold text-gray-900">{{ $receiptData['no_transaksi'] }}</span>
                        </div>
                        @if(!empty($receiptData['no_tiket']))
                            <div class="receipt-row flex justify-between">
                                <span class="text-gray-600">No. Tiket:</span>
                                <span class="font-bold text-up-primary print:text-black">{{ $receiptData['no_tiket'] }}</span>
                            </div>
                        @endif
                        @if(!empty($receiptData['jenis_hp']))
                            <div class="receipt-row flex justify-between">
                                <span class="text-gray-600">Unit / HP:</span>
                                <span class="font-bold text-gray-900">{{ $receiptData['jenis_hp'] }}</span>
                            </div>
                        @endif
                        <div class="receipt-row flex justify-between">
                            <span class="text-gray-600">Kasir:</span>
                            <span class="text-gray-900">{{ $receiptData['kasir'] }}</span>
                        </div>
                        <div class="receipt-row flex justify-between">
                            <span class="text-gray-600">Pelanggan:</span>
                            <span class="text-gray-900 font-medium">{{ $receiptData['pelanggan'] }}</span>
                        </div>
                    </div>

                    <!-- Daftar Item (Jasa & Part) -->
                    <div class="space-y-1.5 border-b border-dashed border-gray-300 pb-2 pt-1">
                        @foreach($receiptData['items'] as $it)
                            @php
                                $isJasa = ($it['tipe'] ?? '') === 'jasa' || str_starts_with($it['nama'] ?? '', '[Jasa]');
                                $isPart = ($it['tipe'] ?? '') === 'part' || str_starts_with($it['nama'] ?? '', '[Part]');
                            @endphp
                            <div class="text-[11px] leading-tight">
                                <div class="flex items-start justify-between gap-1 font-semibold text-gray-900">
                                    <div class="flex items-center gap-1 flex-1 min-w-0">
                                        @if($isJasa)
                                            <span class="no-print inline-block px-1 py-0.2 rounded text-[8px] font-bold bg-up-mint/10 text-up-mint border border-up-mint/20">JASA</span>
                                        @elseif($isPart)
                                            <span class="no-print inline-block px-1 py-0.2 rounded text-[8px] font-bold bg-up-primary/10 text-up-primary border border-up-primary/20">PART</span>
                                        @endif
                                        <span class="truncate">{{ $it['nama'] }}</span>
                                    </div>
                                </div>
                                <div class="receipt-row flex justify-between text-[10px] text-gray-600 mt-0.5">
                                    <span>{{ $it['qty'] }} x {{ number_format($it['harga'], 0, ',', '.') }}</span>
                                    <span class="font-bold text-gray-900">{{ number_format($it['subtotal'], 0, ',', '.') }}</span>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <!-- Subtotal Pisahan (Bila ada Jasa & Part) -->
                    @if(!empty($receiptData['subtotal_jasa']) && !empty($receiptData['subtotal_part']) && (float)$receiptData['subtotal_jasa'] > 0 && (float)$receiptData['subtotal_part'] > 0)
                        <div class="text-[10px] space-y-0.5 border-b border-dashed border-gray-300 pb-1.5 text-gray-700">
                            <div class="receipt-row flex justify-between">
                                <span>Subtotal Jasa:</span>
                                <span>Rp {{ number_format($receiptData['subtotal_jasa'], 0, ',', '.') }}</span>
                            </div>
                            <div class="receipt-row flex justify-between">
                                <span>Subtotal Part:</span>
                                <span>Rp {{ number_format($receiptData['subtotal_part'], 0, ',', '.') }}</span>
                            </div>
                        </div>
                    @endif

                    <!-- Total & Pembayaran -->
                    <div class="space-y-1 text-[11px] pt-1 font-bold">
                        <div class="receipt-row flex justify-between text-xs text-gray-950">
                            <span class="tracking-wide">TOTAL:</span>
                            <span class="font-black">Rp {{ number_format($receiptData['total'], 0, ',', '.') }}</span>
                        </div>
                        <div class="receipt-row flex justify-between text-gray-700 font-normal text-[10px]">
                            <span>BAYAR ({{ $receiptData['metode'] ?? 'TUNAI' }}):</span>
                            <span>Rp {{ number_format($receiptData['bayar'] ?? $receiptData['total'], 0, ',', '.') }}</span>
                        </div>
                        <div class="receipt-row flex justify-between text-gray-700 font-normal text-[10px]">
                            <span>KEMBALI:</span>
                            <span>Rp {{ number_format($receiptData['kembali'] ?? 0, 0, ',', '.') }}</span>
                        </div>
                    </div>

                    <!-- Garansi & Footer -->
                    <div class="receipt-center pt-2 text-[9px] text-gray-500 border-t border-dashed border-gray-300 space-y-0.5">
                        @if(!empty($receiptData['garansi']))
                            <p class="font-bold text-gray-800">Garansi: {{ $receiptData['garansi'] }}</p>
                        @endif
                        <p>Terima Kasih atas Kunjungan Anda!</p>
                        <p class="text-[8px] text-gray-400">Garansi part & servis sesuai ketentuan nota toko.</p>
                    </div>
                </div>
            </div>

            <!-- Tombol Aksi Cetak & Tutup -->
            <div class="mt-3 pt-3 border-t border-white/10 space-y-2 flex-shrink-0 no-print">
                <!-- Status Bluetooth Printer jika terhubung -->
                <template x-if="pairedPrinterName">
                    <div class="flex items-center justify-between px-3 py-1.5 rounded-xl bg-up-mint/10 border border-up-mint/20 text-[11px] text-up-mint">
                        <span class="flex items-center gap-1.5 truncate">
                            <span class="w-2 h-2 rounded-full bg-up-mint animate-pulse"></span>
                            <span class="truncate">Printer BLE: <strong x-text="pairedPrinterName"></strong></span>
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

                <!-- Tombol Cetak Utama: 58mm Web Print (USB / OS driver) -->
                <button
                    type="button"
                    @click="webPrint58()"
                    class="w-full py-2.5 rounded-xl bg-gradient-to-r from-up-mint to-teal-500 hover:opacity-95 text-ink-950 font-bold text-xs shadow-lg shadow-up-mint/20 cursor-pointer min-h-[42px] flex items-center justify-center gap-2 transition-all"
                    title="Cetak struk 58mm presisi tanpa margin via USB / Browser dialog"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
                    <span>Cetak Struk (58mm)</span>
                </button>

                <!-- Pilihan Cetak Alternatif: Bluetooth BLE 58mm & Faktur 80mm -->
                <div class="flex gap-2">
                    <button
                        type="button"
                        @click="printStruk58()"
                        :disabled="isPrinting"
                        class="flex-1 py-2 px-2 rounded-xl bg-white/5 hover:bg-white/10 text-up-mint font-semibold text-[11px] border border-up-mint/30 cursor-pointer min-h-[38px] flex items-center justify-center gap-1 transition-all disabled:opacity-50"
                        title="Cetak via Bluetooth BLE tanpa driver"
                    >
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                        <span x-text="isPrinting ? 'Menghubungkan...' : 'Bluetooth BLE'">Bluetooth BLE</span>
                    </button>

                    <button
                        type="button"
                        @click="webPrint80()"
                        class="flex-1 py-2 px-2 rounded-xl bg-white/5 hover:bg-white/10 text-ink-300 font-semibold text-[11px] border border-white/10 cursor-pointer min-h-[38px] flex items-center justify-center gap-1 transition-all"
                        title="Cetak format lebar 80mm"
                    >
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                        <span>Faktur 80mm</span>
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
                        class="w-full py-2.5 rounded-xl bg-[#25D366] hover:bg-[#20ba59] text-white font-bold text-xs shadow-md shadow-[#25D366]/20 cursor-pointer min-h-[40px] flex items-center justify-center gap-2 transition-all no-underline"
                        title="Buka WhatsApp langsung (Aplikasi di HP / WhatsApp Web di PC) dengan draft pesan struk belanja siap kirim"
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
