{{-- [T-35] Modal Struk Thermal 58mm / 80mm Reusable (POS Kasir & Servis) --}}
@if($showReceiptModal && $receiptData)
    @php
        $strukConfig = app(\App\Modules\Crm\Services\KonfigurasiService::class)->getStrukConfig();
        $showQrRetur = $strukConfig['show_qr_retur'] ?? true;
        $qrSize = (int) ($strukConfig['qr_size'] ?? 80);
        $qrCaption = $strukConfig['qr_caption'] ?? 'Scan QR ini untuk retur & klaim garansi';
        $defaultPaperSize = str_contains($strukConfig['default_width'] ?? '', '80') ? '80' : '58';
        $autoPrint = !empty($strukConfig['auto_print']);
    @endphp
    <script id="pos-receipt-data" type="application/json">
        {!! json_encode($receiptData, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) !!}
    </script>
    <div
        class="fixed inset-0 z-[80] flex items-center justify-center bg-black/80 backdrop-blur-sm p-4 overflow-y-auto"
        x-data="{ ...thermalPrinter({{ \Illuminate\Support\Js::from(array_merge($strukConfig, $receiptData)) }}), paperSize: '{{ $defaultPaperSize }}', autoPrint: {{ $autoPrint ? 'true' : 'false' }} }"
        x-init="if (autoPrint) { setTimeout(() => { paperSize === '58' ? webPrint58() : webPrint80(); }, 400); }"
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
                <div class="thermal-receipt-container thermal-receipt-preview bg-white text-black p-4 rounded-2xl font-mono text-xs shadow-inner space-y-2 select-text transition-all duration-200" id="thermalReceipt" :style="paperSize === '58' ? 'max-width: 280px; margin: 0 auto;' : 'max-width: 340px; margin: 0 auto;'">
                    <!-- Brand Header -->
                    <div class="receipt-center pb-2 border-b border-dashed border-gray-400">
                        <h3 class="receipt-title text-sm font-black tracking-wider text-gray-900">{{ $strukConfig['nama_toko'] ?? 'UTE PARTS' }}</h3>
                        @if(!empty($strukConfig['slogan']))
                            <p class="text-[10px] text-gray-600 font-medium">{{ $strukConfig['slogan'] }}</p>
                        @endif
                        @if(!empty($strukConfig['alamat']))
                            <p class="text-[9px] text-gray-500">{{ $strukConfig['alamat'] }}</p>
                        @endif
                        @if(!empty($strukConfig['telepon']))
                            <p class="text-[9px] text-gray-500">Telp/WA: {{ $strukConfig['telepon'] }}</p>
                        @endif
                        @if(!empty($strukConfig['website_medsos']))
                            <p class="text-[9px] text-gray-400">{{ $strukConfig['website_medsos'] }}</p>
                        @endif
                        @if(($strukConfig['show_cabang'] ?? true) && !empty($receiptData['cabang']))
                            <p class="text-[9px] text-gray-500">{{ $receiptData['cabang'] }}</p>
                        @endif
                        @if($strukConfig['show_waktu'] ?? true)
                            <p class="text-[9px] text-gray-500 mt-0.5">{{ $receiptData['waktu'] }}</p>
                        @endif
                    </div>

                    <!-- Meta Transaksi -->
                    <div class="text-[10px] space-y-0.5 border-b border-dashed border-gray-300 pb-2">
                        @if(($receiptData['status'] ?? '') === 'void')
                            <div class="p-1.5 mb-1.5 text-center bg-red-100 border border-red-300 rounded text-red-700 font-bold uppercase text-xs tracking-wider">
                                *** TRANSAKSI VOID (BATAL) ***
                                @if(!empty($receiptData['void_alasan']))
                                    <div class="text-[9px] font-normal normal-case mt-0.5">{{ $receiptData['void_alasan'] }}</div>
                                @endif
                            </div>
                        @endif
                        @if(!empty($receiptData['is_piutang']))
                            <div class="p-1.5 mb-1.5 text-center bg-amber-50 border-2 border-dashed border-amber-600 rounded text-amber-900 font-black uppercase text-[11px] tracking-wider print:border-black print:text-black">
                                *** TAGIHAN PIUTANG / BELUM LUNAS ***
                            </div>
                        @endif
                        @if(!empty($receiptData['no_tiket']))
                            <div class="p-1.5 mb-1.5 text-center {{ !empty($receiptData['is_lunas']) ? 'bg-emerald-50 border-2 border-emerald-600 text-emerald-900' : 'bg-blue-50 border-2 border-dashed border-blue-600 text-blue-900' }} rounded font-black uppercase text-[11px] tracking-wider print:border-black print:text-black">
                                {{ $receiptData['label_struk'] ?? (!empty($receiptData['is_lunas']) ? '*** KWITANSI PELUNASAN SERVIS ***' : '*** SPK / TANDA TERIMA ESTIMASI ***') }}
                            </div>
                        @endif
                        <div class="receipt-row flex justify-between">
                            <span class="text-gray-600">No. TRX:</span>
                            <span class="font-bold text-gray-900">{{ $receiptData['no_transaksi'] }}</span>
                        </div>
                        @if(!empty($receiptData['sumber']) && !in_array(strtolower($receiptData['sumber']), ['pos', 'offline'], true))
                            <div class="receipt-row flex justify-between">
                                <span class="text-gray-600">Pesanan:</span>
                                <span class="font-bold text-up-primary uppercase">{{ $receiptData['sumber'] }}</span>
                            </div>
                        @endif
                        @if(!empty($receiptData['kurir']))
                            <div class="receipt-row flex justify-between">
                                <span class="text-gray-600">{{ !empty($receiptData['is_pickup']) ? 'Metode Ambil:' : 'Kurir / Resi:' }}</span>
                                <span class="text-gray-900 font-semibold text-right">
                                    {{ $receiptData['kurir'] }}{{ !empty($receiptData['resi']) && $receiptData['resi'] !== '-' ? ' (' . $receiptData['resi'] . ')' : '' }}
                                </span>
                            </div>
                        @endif
                        @if(!empty($receiptData['alamat']) && empty($receiptData['is_pickup']))
                            <div class="text-[9px] text-gray-500 text-left pt-0.5 leading-tight">
                                <span class="text-gray-600 font-medium">Alamat:</span> {{ $receiptData['alamat'] }}
                            </div>
                        @endif
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
                        @if($strukConfig['show_kasir'] ?? true)
                            <div class="receipt-row flex justify-between">
                                <span class="text-gray-600">Kasir:</span>
                                <span class="text-gray-900">{{ $receiptData['kasir'] }}</span>
                            </div>
                        @endif
                        @if($strukConfig['show_pelanggan'] ?? true)
                            <div class="receipt-row flex justify-between">
                                <span class="text-gray-600">Pelanggan:</span>
                                <span class="text-gray-900 font-medium">{{ $receiptData['pelanggan'] }}</span>
                            </div>
                            @if(!empty($receiptData['tier']) && $receiptData['tier'] !== 'Retail' && $receiptData['tier'] !== 'Umum')
                                <div class="receipt-row flex justify-between">
                                    <span class="text-gray-600">{{ in_array($receiptData['tipe_konsumen'] ?? '', ['reseller', 'agen'], true) ? 'Tipe Mitra:' : 'Tier Member:' }}</span>
                                    <span class="text-up-primary font-bold">{{ $receiptData['tier'] }}</span>
                                </div>
                            @endif
                            @if(isset($receiptData['saldo_poin']) && (int)$receiptData['saldo_poin'] > 0)
                                <div class="receipt-row flex justify-between">
                                    <span class="text-gray-600">Saldo Poin:</span>
                                    <span class="text-amber-600 font-bold tabular-nums">{{ number_format($receiptData['saldo_poin']) }} Poin</span>
                                </div>
                            @endif
                        @endif
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
                                    <div class="flex items-start gap-1 flex-1 min-w-0">
                                        @if($isJasa)
                                            <span class="no-print inline-block px-1 py-0.2 rounded text-[8px] font-bold bg-up-mint/10 text-up-mint border border-up-mint/20 shrink-0">JASA</span>
                                        @elseif($isPart)
                                            <span class="no-print inline-block px-1 py-0.2 rounded text-[8px] font-bold bg-up-primary/10 text-up-primary border border-up-primary/20 shrink-0">PART</span>
                                        @endif
                                        <span class="break-words whitespace-normal text-left">{{ $it['nama'] }}</span>
                                    </div>
                                </div>
                                @if(!empty($it['catatan']))
                                    <div class="text-[9px] text-gray-600 dark:text-gray-400 font-medium italic leading-tight mt-0.5 pl-0.5">
                                        * {{ $it['catatan'] }}
                                    </div>
                                @endif
                                <div class="receipt-row flex justify-between text-[10px] text-gray-600 mt-0.5">
                                    <span>
                                        {{ $it['qty'] }}{{ !empty($it['satuan']) ? ' ' . strtoupper($it['satuan']) : '' }} x
                                        @if(!empty($it['harga_retail']) && (float)$it['harga_retail'] > (float)$it['harga'])
                                            <span class="line-through text-gray-400">Rp {{ number_format($it['harga_retail'], 0, ',', '.') }}</span>
                                        @endif
                                        <span class="font-bold text-gray-900">Rp {{ number_format($it['harga'], 0, ',', '.') }}</span>
                                        @if(!empty($it['diskon_total']) && (float)$it['diskon_total'] > 0)
                                            <span class="text-[9px] font-semibold text-emerald-700">(Hemat Rp {{ number_format($it['diskon_total'], 0, ',', '.') }})</span>
                                        @endif
                                    </span>
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
                        @php
                            $showBreakdown = $strukConfig['show_subtotal_breakdown'] ?? true;
                        @endphp

                        @if($showBreakdown && !empty($receiptData['diskon_tier']) && (float)$receiptData['diskon_tier'] > 0)
                            <div class="receipt-row flex justify-between text-gray-600 font-normal text-[10px]">
                                <span>Subtotal Retail:</span>
                                <span>Rp {{ number_format($receiptData['subtotal_retail'] ?? ($receiptData['subtotal'] + $receiptData['diskon_tier']), 0, ',', '.') }}</span>
                            </div>
                            <div class="receipt-row flex justify-between text-emerald-700 font-bold text-[10px]">
                                <span>Potongan {{ !empty($receiptData['tier']) && $receiptData['tier'] !== 'Retail' ? $receiptData['tier'] : (in_array($receiptData['tipe_konsumen'] ?? '', ['reseller', 'agen'], true) ? 'Mitra' : 'Tier Member') }}:</span>
                                <span>-Rp {{ number_format($receiptData['diskon_tier'], 0, ',', '.') }}</span>
                            </div>
                            <div class="receipt-row flex justify-between text-gray-800 font-bold text-[10px] border-t border-dashed border-gray-200 pt-0.5">
                                <span>Subtotal:</span>
                                <span>Rp {{ number_format($receiptData['subtotal'], 0, ',', '.') }}</span>
                            </div>
                        @else
                            <div class="receipt-row flex justify-between text-gray-800 font-bold text-[10px]">
                                <span>Subtotal:</span>
                                <span>Rp {{ number_format($receiptData['subtotal'] ?? $receiptData['total'], 0, ',', '.') }}</span>
                            </div>
                        @endif

                        @if($showBreakdown && !empty($receiptData['diskon_partai']) && (float)$receiptData['diskon_partai'] > 0)
                            <div class="receipt-row flex justify-between text-emerald-700 font-bold text-[10px]">
                                <span>Diskon Partai (Member):</span>
                                <span>-Rp {{ number_format($receiptData['diskon_partai'], 0, ',', '.') }}</span>
                            </div>
                        @endif

                        @if($showBreakdown && !empty($receiptData['diskon_poin']) && (float)$receiptData['diskon_poin'] > 0)
                            <div class="receipt-row flex justify-between text-emerald-700 font-bold text-[10px]">
                                <span>Tukar Poin ({{ number_format($receiptData['poin_ditukar'] ?? 0) }} pts):</span>
                                <span>-Rp {{ number_format($receiptData['diskon_poin'], 0, ',', '.') }}</span>
                            </div>
                        @endif

                        @if(!empty($receiptData['diskon']) && (float)$receiptData['diskon'] > 0)
                            <div class="receipt-row flex justify-between text-emerald-700 font-bold text-[10px]">
                                <span>Diskon Kasir{{ !empty($receiptData['diskon_persen']) && (float)$receiptData['diskon_persen'] > 0 ? ' ('.$receiptData['diskon_persen'].'%)' : '' }}{{ !empty($receiptData['diskon_manual_authorized_by']) ? ' [Acc: '.$receiptData['diskon_manual_authorized_by'].']' : '' }}:</span>
                                <span>-Rp {{ number_format($receiptData['diskon'], 0, ',', '.') }}</span>
                            </div>
                        @endif

                        @if(!empty($receiptData['pajak']) && (float)$receiptData['pajak'] > 0)
                            <div class="receipt-row flex justify-between text-gray-600 font-normal text-[10px]">
                                <span>PPN {{ !empty($receiptData['ppn_persen']) ? '('.$receiptData['ppn_persen'].'%)' : '' }}:</span>
                                <span>+Rp {{ number_format($receiptData['pajak'], 0, ',', '.') }}</span>
                            </div>
                        @endif

                        @if(!empty($receiptData['ongkir']) && (float)$receiptData['ongkir'] > 0)
                            <div class="receipt-row flex justify-between text-gray-800 font-medium text-[10px]">
                                <span>Ongkos Kirim ({{ $receiptData['kurir'] ?? 'Ekspedisi' }}):</span>
                                <span>+Rp {{ number_format($receiptData['ongkir'], 0, ',', '.') }}</span>
                            </div>
                        @endif

                        <div class="receipt-row flex justify-between text-xs text-gray-950 border-t border-dashed border-gray-300 pt-1">
                            <span class="tracking-wide">TOTAL:</span>
                            <span class="font-black">Rp {{ number_format($receiptData['total'], 0, ',', '.') }}</span>
                        </div>
                        @if(!empty($receiptData['is_piutang']))
                            <div class="receipt-row flex justify-between text-black font-bold text-[10px]">
                                <span>STATUS BAYAR:</span>
                                <span class="text-amber-800 print:text-black font-black uppercase">BELUM LUNAS</span>
                            </div>
                            @if(!empty($receiptData['jatuh_tempo']))
                                <div class="receipt-row flex justify-between text-gray-800 font-bold text-[10px]">
                                    <span>JATUH TEMPO:</span>
                                    <span>{{ $receiptData['jatuh_tempo'] }}</span>
                                </div>
                            @endif
                            <div class="receipt-row flex justify-between text-black font-black text-[10px]">
                                <span>SISA PIUTANG:</span>
                                <span>Rp {{ number_format($receiptData['sisa_piutang'] ?? $receiptData['total'], 0, ',', '.') }}</span>
                            </div>
                        @elseif(!empty($receiptData['no_tiket']) && !empty($receiptData['is_spk']))
                            <div class="receipt-row flex justify-between text-black font-bold text-[10px]">
                                <span>STATUS:</span>
                                <span class="text-blue-800 print:text-black font-black uppercase">BELUM LUNAS (ESTIMASI)</span>
                            </div>
                            <div class="receipt-row flex justify-between text-gray-700 font-normal text-[10px]">
                                <span>SISA PEMBAYARAN:</span>
                                <span class="font-bold">Rp {{ number_format($receiptData['sisa_tagihan'] ?? $receiptData['total'], 0, ',', '.') }}</span>
                            </div>
                        @else
                            <div class="receipt-row flex justify-between text-gray-700 font-normal text-[10px]">
                                <span>BAYAR ({{ $receiptData['metode'] ?? 'TUNAI' }}):</span>
                                <span>Rp {{ number_format($receiptData['bayar'] ?? $receiptData['total'], 0, ',', '.') }}</span>
                            </div>
                            <div class="receipt-row flex justify-between text-gray-700 font-normal text-[10px]">
                                <span>KEMBALI:</span>
                                <span>Rp {{ number_format($receiptData['kembali'] ?? 0, 0, ',', '.') }}</span>
                            </div>
                        @endif

                        @if(!empty($receiptData['hemat_total']) && (float)$receiptData['hemat_total'] > 0)
                            <div class="receipt-row flex justify-between text-emerald-800 font-black text-[10px] pt-1 border-t border-dashed border-gray-300">
                                <span>TOTAL HEMAT BELANJA:</span>
                                <span>Rp {{ number_format($receiptData['hemat_total'], 0, ',', '.') }}</span>
                            </div>
                        @endif

                        @if(!empty($receiptData['komisi_mitra_nominal']) && (float)$receiptData['komisi_mitra_nominal'] > 0)
                            <div class="receipt-row flex justify-between text-amber-700 font-medium text-[9px] pt-0.5">
                                <span>Komisi Mitra (Masuk Saldo):</span>
                                <span>+Rp {{ number_format($receiptData['komisi_mitra_nominal'], 0, ',', '.') }}</span>
                            </div>
                        @endif

                        @if(isset($receiptData['saldo_poin']))
                            <div class="receipt-row flex justify-between text-gray-600 font-medium text-[9px]">
                                <span>Sisa Saldo Poin:</span>
                                <span>{{ number_format($receiptData['saldo_poin']) }} pts</span>
                            </div>
                        @endif
                    </div>

                    <!-- QR Code Faktur / Retur & Garansi Anti-Fraud -->
                    @if($showQrRetur && !empty($receiptData['no_transaksi']))
                        <div class="receipt-center py-2 flex flex-col items-center justify-center border-t border-dashed border-gray-300">
                            <div class="p-1 bg-white inline-block">
                                {!! app(\App\Modules\Wms\Services\QrCodeService::class)->generateSvg($receiptData['no_transaksi'], $qrSize, 0) !!}
                            </div>
                            <p class="font-mono text-[9px] text-gray-900 font-bold mt-1 tracking-wider">{{ $receiptData['no_transaksi'] }}</p>
                            <p class="text-[8px] text-gray-500 text-center mt-0.5">{{ $qrCaption }}</p>
                        </div>
                    @endif

                    @if(!empty($receiptData['is_piutang']))
                        <!-- Tanda Tangan Akad Piutang / Kasbon -->
                        <div class="receipt-center py-2.5 border-t border-dashed border-gray-300 text-[9px] text-gray-800">
                            <p class="font-medium text-center italic mb-2 text-gray-600">
                                "Barang telah diterima lengkap & berjanji melunasi tagihan sebelum tanggal jatuh tempo."
                            </p>
                            <div class="grid grid-cols-2 gap-4 text-center text-[9px] pt-1">
                                <div>
                                    <p class="text-gray-500">Penerima / Konsumen,</p>
                                    <div class="h-9"></div>
                                    <p class="font-bold border-t border-dotted border-gray-400 mx-2 pt-0.5 truncate">
                                        {{ $receiptData['pelanggan'] ?? 'Konsumen' }}
                                    </p>
                                </div>
                                <div>
                                    <p class="text-gray-500">Kasir Toko,</p>
                                    <div class="h-9"></div>
                                    <p class="font-bold border-t border-dotted border-gray-400 mx-2 pt-0.5 truncate">
                                        {{ $receiptData['kasir'] ?? 'Kasir' }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    @endif

                    <!-- Garansi & Footer -->
                    <div class="receipt-center pt-2 text-[9px] text-gray-500 border-t border-dashed border-gray-300 space-y-0.5">
                        @if(!empty($receiptData['garansi']))
                            <p class="font-bold text-gray-800">Garansi: {{ $receiptData['garansi'] }}</p>
                        @endif
                        <p class="font-medium text-gray-700">{{ $strukConfig['footer_text'] ?? 'Terima Kasih atas Kunjungan Anda!' }}</p>
                        @if(!empty($strukConfig['kebijakan_retur']))
                            <p class="text-[8px] text-gray-600 font-medium">{{ $strukConfig['kebijakan_retur'] }}</p>
                        @endif
                        @if(!empty($strukConfig['catatan_kaki']))
                            <p class="text-[8px] text-gray-400">{{ $strukConfig['catatan_kaki'] }}</p>
                        @endif
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
                            <span class="truncate">BLE Terhubung: <strong x-text="pairedPrinterName"></strong></span>
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

                <!-- Pilihan Ukuran Struk (Segmented Control Ringkas) -->
                <div class="flex items-center justify-between p-1 bg-white/5 rounded-xl border border-white/10 text-xs">
                    <span class="text-ink-400 text-[11px] font-semibold pl-2">Format Kertas:</span>
                    <div class="flex items-center gap-1">
                        <button
                            type="button"
                            @click="paperSize = '58'"
                            :class="paperSize === '58' ? 'bg-up-mint text-ink-950 font-bold shadow-sm' : 'text-ink-300 hover:text-white'"
                            class="px-3 py-1 rounded-lg text-xs transition cursor-pointer"
                        >
                            58mm (Kecil)
                        </button>
                        <button
                            type="button"
                            @click="paperSize = '80'"
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
                        @click="paperSize === '58' ? webPrint58() : webPrint80()"
                        class="flex-1 py-2.5 rounded-xl bg-gradient-to-r from-up-mint to-teal-500 hover:opacity-95 text-ink-950 font-black text-xs shadow-md shadow-up-mint/20 cursor-pointer min-h-[42px] flex items-center justify-center gap-2 transition active:scale-[0.98]"
                        :title="'Cetak struk ' + paperSize + 'mm via USB / Print Dialog Browser'"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
                        <span x-text="'Cetak Struk (' + paperSize + 'mm)'"></span>
                    </button>

                    <button
                        type="button"
                        @click="paperSize === '58' ? printStruk58() : printFaktur80()"
                        :disabled="isPrinting"
                        class="px-3.5 py-2.5 rounded-xl bg-white/5 hover:bg-white/10 text-up-mint font-semibold text-xs border border-up-mint/30 cursor-pointer min-h-[42px] flex items-center justify-center gap-1.5 transition active:scale-[0.98] disabled:opacity-50"
                        :title="'Cetak nirkabel via Bluetooth Thermal BLE (' + paperSize + 'mm)'"
                    >
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                        <span x-text="isPrinting ? 'Menghubungkan...' : 'BLE'"></span>
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
