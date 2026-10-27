<!-- PAYMENT MODAL -->
@if($showPaymentModal)
    @php
        $qrisSlug = !empty($qrisMerchantName) ? \Illuminate\Support\Str::slug($qrisMerchantName) : 'toko';
    @endphp
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-ink-950/80 backdrop-blur-md p-4 sm:p-6 overflow-y-auto"
         x-data="{
             showQrisZoom: false,
             downloadQris(targetId = 'qris-svg-render-main', format = 'png') {
                 const svgContainer = document.getElementById(targetId) || document.querySelector('.qris-svg-source');
                 if (!svgContainer) return;
                 const svg = svgContainer.querySelector('svg');
                 if (!svg) return;
                 const serializer = new XMLSerializer();
                 const svgStr = serializer.serializeToString(svg);

                 if (format === 'svg') {
                     const blob = new Blob([svgStr], { type: 'image/svg+xml;charset=utf-8' });
                     const url = URL.createObjectURL(blob);
                     const a = document.createElement('a');
                     a.href = url;
                     a.download = 'QRIS-{{ $qrisSlug }}-' + Date.now() + '.svg';
                     document.body.appendChild(a);
                     a.click();
                     document.body.removeChild(a);
                     URL.revokeObjectURL(url);
                     return;
                 }

                 const img = new Image();
                 const svgBlob = new Blob([svgStr], { type: 'image/svg+xml;charset=utf-8' });
                 const url = URL.createObjectURL(svgBlob);
                 img.onload = () => {
                     const canvas = document.createElement('canvas');
                     canvas.width = 600;
                     canvas.height = 600;
                     const ctx = canvas.getContext('2d');
                     ctx.fillStyle = '#FFFFFF';
                     ctx.fillRect(0, 0, 600, 600);
                     ctx.drawImage(img, 40, 40, 520, 520);
                     URL.revokeObjectURL(url);
                     const pngUrl = canvas.toDataURL('image/png');
                     const a = document.createElement('a');
                     a.href = pngUrl;
                     a.download = 'QRIS-{{ $qrisSlug }}-' + Date.now() + '.png';
                     document.body.appendChild(a);
                     a.click();
                     document.body.removeChild(a);
                 };
                 img.src = url;
             }
         }"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0">
        <div class="w-full max-w-lg bg-white dark:bg-[#0B1120] border border-ink-200/80 dark:border-white/10 p-5 sm:p-6 rounded-3xl shadow-2xl relative my-auto"
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0 scale-95"
             x-transition:enter-end="opacity-100 scale-100"
             x-transition:leave="transition ease-in duration-150"
             x-transition:leave-start="opacity-100 scale-100"
             x-transition:leave-end="opacity-0 scale-95">
            <!-- Header -->
            <div class="flex items-center justify-between pb-3.5 mb-4 border-b border-ink-200/70 dark:border-white/10">
                <div class="flex items-center gap-2.5">
                    <div class="w-9 h-9 rounded-xl bg-up-primary/10 border border-up-primary/25 flex items-center justify-center text-up-primary">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z" />
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-base font-bold text-ink-900 dark:text-white">Pembayaran Kasir</h3>
                        <p class="text-[11px] text-ink-500 dark:text-ink-400">Pilih metode bayar & proses cetak struk</p>
                    </div>
                </div>
                <button
                    type="button"
                    wire:click="$set('showPaymentModal', false)"
                    class="p-2 rounded-xl text-ink-400 hover:text-ink-900 dark:hover:text-white hover:bg-ink-100 dark:hover:bg-white/5 transition-colors cursor-pointer"
                >✕</button>
            </div>

            <!-- Total Amount Highlight Banner -->
            <div class="p-4 rounded-2xl bg-gradient-to-br from-up-mint/10 via-up-mint/5 to-transparent border border-up-mint/25 text-center mb-4">
                <span class="text-[11px] uppercase tracking-wider text-ink-500 dark:text-ink-400 font-bold block mb-0.5">Total Tagihan</span>
                <span class="text-3xl font-black text-up-mint tabular-nums tracking-tight">
                    Rp {{ number_format($totalAkhir, 0, ',', '.') }}
                </span>
                @if($diskonTotal > 0 || ($diskonTierTotal ?? 0) > 0)
                    <div class="mt-2 pt-2 border-t border-up-mint/20 flex flex-wrap items-center justify-center gap-x-3 gap-y-1 text-[11px]">
                        @if(($diskonTierTotal ?? 0) > 0)
                            <span class="text-up-mint font-bold">Tier ({{ $customer?->tierMembership?->nama ?? ($customer?->tipe_konsumen === 'reseller' ? 'Reseller' : 'Member') }}): -Rp {{ number_format($diskonTierTotal, 0, ',', '.') }}</span>
                        @endif
                        @if($diskonPartai > 0)
                            <span class="text-up-mint font-semibold">Partai: -Rp {{ number_format($diskonPartai, 0, ',', '.') }}</span>
                        @endif
                        @if($diskonPoin > 0)
                            <span class="text-emerald-500 font-semibold">Poin ({{ number_format($poinDitukar) }} pts): -Rp {{ number_format($diskonPoin, 0, ',', '.') }}</span>
                        @endif
                        @if($diskonNominal > 0 || $diskonPersen > 0)
                            <button type="button" wire:click="openDiskonModal" class="text-up-amber font-semibold hover:underline cursor-pointer">
                                Diskon Kasir: -Rp {{ number_format($diskonNominal + round(($subtotal * $diskonPersen)/100, 2), 0, ',', '.') }} ✏️
                            </button>
                        @endif
                    </div>
                @else
                    <div class="mt-2 pt-2 border-t border-up-mint/20 flex justify-center">
                        <button
                            type="button"
                            wire:click="openDiskonModal"
                            class="text-[11px] font-bold text-up-amber hover:underline cursor-pointer inline-flex items-center gap-1"
                        >
                            <span>🏷️ Tambah Diskon Langsung</span>
                        </button>
                    </div>
                @endif
                @if($modeRewardMitra === 'komisi_akumulasi' && ($customer?->is_reseller || in_array($customer?->tipe_konsumen, ['reseller', 'agen'], true)))
                    <div class="mt-2 p-2 rounded-xl bg-up-amber/15 border border-up-amber/30 text-[11px] text-up-amber font-bold flex items-center justify-between">
                        <span>💰 Mode: Simpan Komisi Mitra (Harga Retail)</span>
                        <span class="tabular-nums">+Rp {{ number_format($potensiKomisiTotal ?? 0, 0, ',', '.') }}</span>
                    </div>
                @endif
            </div>

            <!-- Payment Method Selector -->
            <div class="mb-4">
                <label class="block text-xs font-bold text-ink-700 dark:text-ink-300 uppercase tracking-wider mb-2">Metode Pembayaran</label>
                <div class="grid grid-cols-3 sm:grid-cols-5 gap-2">
                    @foreach(['tunai' => 'Tunai', 'transfer' => 'Transfer', 'qris' => 'QRIS', 'split' => 'Split', 'piutang' => 'Kasbon'] as $val => $label)
                        <button
                            type="button"
                            wire:click="$set('metodeBayar', '{{ $val }}')"
                            class="py-2.5 px-2 rounded-xl text-xs font-bold border transition-all active:scale-[0.97] min-h-[44px] flex items-center justify-center cursor-pointer {{ $metodeBayar === $val ? 'bg-up-primary text-white border-up-primary shadow-md shadow-up-primary/30' : 'bg-ink-100/80 dark:bg-white/5 text-ink-700 dark:text-ink-300 border-ink-200/80 dark:border-white/10 hover:bg-ink-200 dark:hover:bg-white/10' }}"
                        >
                            {{ $label }}
                        </button>
                    @endforeach
                </div>
            </div>

            <!-- Method Detail Inputs -->
            @if($metodeBayar === 'tunai')
                <div class="space-y-3 mb-5">
                    <div>
                        <div class="flex items-center justify-between mb-1.5">
                            <label class="text-xs font-bold text-ink-700 dark:text-ink-300 uppercase tracking-wider">Uang Diterima (Rp)</label>
                            <span class="text-[11px] text-ink-500">Ketik atau pilih pecahan cepat</span>
                        </div>
                        <input
                            type="text"
                            inputmode="numeric"
                            x-format-number
                            wire:model.live.debounce.300ms="jumlahBayar"
                            class="w-full px-4 py-3 rounded-2xl glass-input text-xl font-black tabular-nums text-ink-900 dark:text-white border-ink-200/80 dark:border-white/15 min-h-[48px] focus:ring-2 focus:ring-up-mint/30"
                            placeholder="0"
                            autofocus
                        />
                    </div>

                    <!-- Quick Cash Shortcuts -->
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 pt-0.5">
                        <button
                            type="button"
                            wire:click="setQuickCash({{ $totalAkhir }})"
                            class="px-3 py-2.5 rounded-xl bg-ink-100 hover:bg-ink-200 dark:bg-white/5 dark:hover:bg-white/10 text-xs font-bold text-ink-800 dark:text-ink-200 border border-ink-200/80 dark:border-white/10 transition-transform active:scale-[0.96] min-h-[42px] flex items-center justify-center cursor-pointer"
                        >
                            Uang Pas
                        </button>
                        <button
                            type="button"
                            wire:click="setQuickCash(50000)"
                            class="px-3 py-2.5 rounded-xl bg-ink-100 hover:bg-ink-200 dark:bg-white/5 dark:hover:bg-white/10 text-xs font-bold tabular-nums text-ink-800 dark:text-ink-200 border border-ink-200/80 dark:border-white/10 transition-transform active:scale-[0.96] min-h-[42px] flex items-center justify-center cursor-pointer"
                        >
                            Rp 50.000
                        </button>
                        <button
                            type="button"
                            wire:click="setQuickCash(100000)"
                            class="px-3 py-2.5 rounded-xl bg-ink-100 hover:bg-ink-200 dark:bg-white/5 dark:hover:bg-white/10 text-xs font-bold tabular-nums text-ink-800 dark:text-ink-200 border border-ink-200/80 dark:border-white/10 transition-transform active:scale-[0.96] min-h-[42px] flex items-center justify-center cursor-pointer"
                        >
                            Rp 100.000
                        </button>
                        <button
                            type="button"
                            wire:click="setQuickCash(200000)"
                            class="px-3 py-2.5 rounded-xl bg-ink-100 hover:bg-ink-200 dark:bg-white/5 dark:hover:bg-white/10 text-xs font-bold tabular-nums text-ink-800 dark:text-ink-200 border border-ink-200/80 dark:border-white/10 transition-transform active:scale-[0.96] min-h-[42px] flex items-center justify-center cursor-pointer"
                        >
                            Rp 200.000
                        </button>
                    </div>

                    <!-- Kembalian Display -->
                    <div class="flex justify-between items-center p-3.5 rounded-2xl bg-ink-50 dark:bg-white/[0.03] border border-ink-200/80 dark:border-white/10">
                        <span class="text-xs font-bold text-ink-600 dark:text-ink-400 uppercase tracking-wider">Kembalian</span>
                        <span class="font-black text-xl tabular-nums {{ $kembalian > 0 ? 'text-up-mint' : 'text-ink-900 dark:text-white' }}">
                            Rp {{ number_format($kembalian, 0, ',', '.') }}
                        </span>
                    </div>
                </div>
            @elseif($metodeBayar === 'split')
                <div class="space-y-3 mb-5">
                    <!-- Quick Split Shortcut Buttons -->
                    <div class="grid grid-cols-3 gap-2">
                        <button
                            type="button"
                            wire:click="quickSplitTunaiPas"
                            class="px-2.5 py-1.5 rounded-xl bg-ink-100 hover:bg-ink-200 dark:bg-white/5 dark:hover:bg-white/10 text-[11px] font-bold text-ink-800 dark:text-ink-200 border border-ink-200/80 dark:border-white/10 transition-transform active:scale-[0.96] flex items-center justify-center cursor-pointer"
                        >
                            Semua Tunai
                        </button>
                        <button
                            type="button"
                            wire:click="quickSplitHalf"
                            class="px-2.5 py-1.5 rounded-xl bg-ink-100 hover:bg-ink-200 dark:bg-white/5 dark:hover:bg-white/10 text-[11px] font-bold text-ink-800 dark:text-ink-200 border border-ink-200/80 dark:border-white/10 transition-transform active:scale-[0.96] flex items-center justify-center cursor-pointer"
                        >
                            50 : 50
                        </button>
                        <button
                            type="button"
                            wire:click="quickSplitNonTunaiPas"
                            class="px-2.5 py-1.5 rounded-xl bg-ink-100 hover:bg-ink-200 dark:bg-white/5 dark:hover:bg-white/10 text-[11px] font-bold text-ink-800 dark:text-ink-200 border border-ink-200/80 dark:border-white/10 transition-transform active:scale-[0.96] flex items-center justify-center cursor-pointer"
                        >
                            Semua Non-Tunai
                        </button>
                    </div>

                    <!-- Pilihan Jalur Non-Tunai -->
                    <div>
                        <label class="block text-[11px] font-bold text-ink-600 dark:text-ink-400 uppercase tracking-wider mb-1.5">Metode Porsi Non-Tunai</label>
                        <div class="grid grid-cols-2 gap-2">
                            <label class="flex items-center gap-2 p-2.5 rounded-xl border cursor-pointer transition {{ $splitMetodeNonTunai === 'qris' ? 'border-up-primary bg-up-primary/10 text-up-primary font-bold' : 'border-ink-200/80 dark:border-white/10 text-ink-600 dark:text-ink-300' }}">
                                <input type="radio" value="qris" wire:model.live="splitMetodeNonTunai" class="text-up-primary focus:ring-0">
                                <span class="text-xs">QRIS</span>
                            </label>
                            <label class="flex items-center gap-2 p-2.5 rounded-xl border cursor-pointer transition {{ $splitMetodeNonTunai === 'transfer' ? 'border-up-primary bg-up-primary/10 text-up-primary font-bold' : 'border-ink-200/80 dark:border-white/10 text-ink-600 dark:text-ink-300' }}">
                                <input type="radio" value="transfer" wire:model.live="splitMetodeNonTunai" class="text-up-primary focus:ring-0">
                                <span class="text-xs">Transfer Bank</span>
                            </label>
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-ink-700 dark:text-ink-300 uppercase tracking-wider mb-1">Nominal Tunai</label>
                        <input
                            type="text"
                            inputmode="numeric"
                            x-format-number
                            wire:model.live.debounce.300ms="splitTunai"
                            class="w-full px-3.5 py-2.5 rounded-xl glass-input text-sm font-bold tabular-nums min-h-[44px]"
                            placeholder="0"
                        />
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-ink-700 dark:text-ink-300 uppercase tracking-wider mb-1">Nominal Non-Tunai ({{ strtoupper($splitMetodeNonTunai) }})</label>
                        <input
                            type="text"
                            inputmode="numeric"
                            x-format-number
                            wire:model.live.debounce.300ms="splitNonTunai"
                            class="w-full px-3.5 py-2.5 rounded-xl glass-input text-sm font-bold tabular-nums min-h-[44px]"
                            placeholder="0"
                        />
                    </div>

                    @php
                        $parsedTunai = (float) str_replace(['.', ','], '', (string) $splitTunai);
                        $parsedNonTunai = (float) str_replace(['.', ','], '', (string) $splitNonTunai);
                        $totalBayarSplit = $parsedTunai + $parsedNonTunai;
                        $sisaTagihanSplit = max(0.0, (float) $totalAkhir - $totalBayarSplit);
                    @endphp

                    <!-- Summary & Kembalian Split -->
                    <div class="p-3 rounded-2xl bg-ink-50 dark:bg-white/[0.03] border border-ink-200/80 dark:border-white/10 space-y-1.5 text-xs">
                        <div class="flex justify-between items-center text-ink-500 dark:text-ink-400">
                            <span>Total Diterima (Tunai + Non-Tunai)</span>
                            <span class="font-bold tabular-nums text-ink-800 dark:text-ink-200">Rp {{ number_format($totalBayarSplit, 0, ',', '.') }}</span>
                        </div>
                        @if($sisaTagihanSplit > 0)
                            <div class="flex justify-between items-center text-up-amber font-bold pt-1 border-t border-ink-200/50 dark:border-white/5">
                                <span>Kurang Bayar</span>
                                <span class="tabular-nums">-Rp {{ number_format($sisaTagihanSplit, 0, ',', '.') }}</span>
                            </div>
                        @else
                            <div class="flex justify-between items-center pt-1 border-t border-ink-200/50 dark:border-white/5">
                                <span class="text-ink-600 dark:text-ink-400 font-bold uppercase tracking-wider">Kembalian Tunai</span>
                                <span class="font-black text-lg tabular-nums {{ $kembalian > 0 ? 'text-up-mint' : 'text-ink-900 dark:text-white' }}">
                                    Rp {{ number_format($kembalian, 0, ',', '.') }}
                                </span>
                            </div>
                        @endif
                    </div>

                    <!-- Mini Dynamic QRIS untuk Porsi Non-Tunai Split -->
                    @if($splitMetodeNonTunai === 'qris' && $parsedNonTunai > 0)
                        <div class="p-3.5 rounded-2xl bg-up-primary/5 border border-up-primary/20 space-y-3" wire:poll.3s="cekStatusQrisOtomatis">
                            <div class="flex items-center justify-between">
                                <span class="text-xs font-bold text-up-primary flex items-center gap-1.5">
                                    <span>📱 QRIS Dinamis Porsi Non-Tunai</span>
                                </span>
                                <span class="text-xs font-black text-ink-900 dark:text-white tabular-nums">
                                    Rp {{ number_format($parsedNonTunai, 0, ',', '.') }}
                                </span>
                            </div>

                            @if($qrisTerbayarOtomatis)
                                <div class="p-2.5 rounded-xl bg-up-mint/15 border border-up-mint/30 text-xs text-up-mint font-bold flex items-center gap-2">
                                    <span>✅ Pembayaran QRIS porsi non-tunai terdeteksi LUNAS!</span>
                                </div>
                            @endif

                            @if($qrisKasirSvg)
                                <div class="flex flex-col sm:flex-row items-center gap-4 bg-white dark:bg-white/[0.04] p-3 rounded-xl border border-ink-200 dark:border-white/10">
                                    <!-- QR Display with Zoom Trigger -->
                                    <div
                                        id="qris-svg-render-split"
                                        @click="showQrisZoom = true"
                                        class="p-2 bg-white rounded-xl shadow-sm shrink-0 w-32 h-32 flex items-center justify-center cursor-pointer group relative border border-ink-200/80 hover:ring-2 hover:ring-up-primary transition"
                                        title="Klik untuk memperbesar tampilan QRIS"
                                    >
                                        <div class="w-full h-full flex items-center justify-center pointer-events-none">
                                            {!! $qrisKasirSvg !!}
                                        </div>
                                        <div class="absolute inset-0 bg-black/40 rounded-xl opacity-0 group-hover:opacity-100 flex items-center justify-center transition-opacity text-white text-[10px] font-bold">
                                            🔍 Perbesar
                                        </div>
                                    </div>

                                    <div class="space-y-2 text-left w-full">
                                        <div>
                                            <span class="text-[11px] font-bold text-ink-900 dark:text-white block uppercase">
                                                {{ $qrisMerchantName ?: 'QRIS TOKO' }}
                                            </span>
                                            <p class="text-[10px] text-ink-500 dark:text-ink-400">
                                                Nominal terkunci otomatis ke porsi non-tunai.
                                            </p>
                                        </div>

                                        <!-- Quick Action Toolbar -->
                                        <div class="flex flex-wrap gap-1.5">
                                            <button
                                                type="button"
                                                @click="showQrisZoom = true"
                                                class="px-2.5 py-1.5 rounded-lg bg-up-primary/10 hover:bg-up-primary/20 text-up-primary text-[11px] font-bold transition flex items-center gap-1 cursor-pointer"
                                            >
                                                🔍 Perbesar
                                            </button>
                                            <button
                                                type="button"
                                                @click="downloadQris('qris-svg-render-split', 'png')"
                                                class="px-2.5 py-1.5 rounded-lg bg-ink-100 hover:bg-ink-200 dark:bg-white/10 dark:hover:bg-white/15 text-ink-800 dark:text-ink-200 text-[11px] font-bold transition flex items-center gap-1 cursor-pointer"
                                                title="Unduh QR Code sebagai file gambar PNG"
                                            >
                                                📥 Unduh PNG
                                            </button>
                                            <button
                                                type="button"
                                                wire:click="cekStatusQrisManual"
                                                wire:loading.attr="disabled"
                                                class="px-2.5 py-1.5 rounded-lg bg-ink-100 hover:bg-ink-200 dark:bg-white/10 dark:hover:bg-white/15 text-[11px] font-bold text-ink-700 dark:text-ink-200 transition cursor-pointer disabled:opacity-50"
                                            >
                                                <span wire:loading.remove wire:target="cekStatusQrisManual">🔄 Cek Bayar</span>
                                                <span wire:loading wire:target="cekStatusQrisManual">⏳ Memeriksa...</span>
                                            </button>
                                        </div>

                                        <!-- Direct WhatsApp Bar -->
                                        <div class="pt-1 flex items-center gap-1.5">
                                            <input
                                                type="tel"
                                                wire:model.live.debounce.400ms="qrisCustomerPhone"
                                                placeholder="No WA (08xxx)"
                                                class="px-2.5 py-1.5 rounded-lg border border-ink-200 dark:border-white/10 bg-white dark:bg-black/20 text-[11px] text-ink-900 dark:text-white font-mono w-32 focus:ring-1 focus:ring-up-primary"
                                            />
                                            <button
                                                type="button"
                                                wire:click="kirimQrisViaWhatsApp"
                                                class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-[11px] shadow-sm transition active:scale-[0.97] cursor-pointer"
                                            >
                                                <span>📲 Kirim WA</span>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            @endif
                        </div>
                    @endif
                </div>
            @elseif($metodeBayar === 'piutang')
                <div class="p-4 rounded-2xl bg-up-amber/10 text-center text-xs text-up-amber mb-5 border border-up-amber/30">
                    <svg class="w-7 h-7 mx-auto mb-2 text-up-amber" fill="currentColor" viewBox="0 0 24 24">
                        <path fill-rule="evenodd" d="M4 4a2 2 0 00-2 2v12a2 2 0 002 2h16a2 2 0 002-2V6a2 2 0 00-2-2H4zm0 2h16v12H4V6zm5 2a1 1 0 100 2h6a1 1 0 100-2H9zm-1 6a1 1 0 011-1h6a1 1 0 110 2H9a1 1 0 01-1-1zm1-4a1 1 0 100 2h2a1 1 0 100-2H9z" clip-rule="evenodd" />
                    </svg>
                    <strong class="block text-sm font-bold mb-1">Kasbon / Piutang Usaha</strong>
                    <span class="text-ink-700 dark:text-ink-300">Transaksi dicatat sebagai piutang pelanggan (jatuh tempo 30 hari). Akun <strong>Piutang Usaha</strong> otomatis ter-debit di jurnal akuntansi.</span>
                    @if(!$selectedCustomerId)
                        <div class="mt-3 p-2.5 rounded-xl bg-up-red/15 text-up-red font-bold border border-up-red/30 text-left flex items-center gap-2">
                            <svg class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                            </svg>
                            <span>Pelanggan belum dipilih! Pilih pelanggan sebelum kasbon.</span>
                        </div>
                    @else
                        @php
                            $saldoPiutang = (float) ($customer?->saldo_piutang ?? 0);
                            $maxPiutang = (float) ($customer?->max_piutang ?? 0);
                            $totalSetelahTrx = $saldoPiutang + (float) $totalAkhir;
                            $overLimit = $maxPiutang > 0 && $totalSetelahTrx > $maxPiutang;
                        @endphp
                        <div class="mt-3 p-3 rounded-xl bg-white/5 border border-white/10 text-left space-y-1.5 text-xs text-ink-800 dark:text-ink-200">
                            <div class="flex justify-between items-center">
                                <span>Pelanggan:</span>
                                <strong class="text-ink-900 dark:text-white">{{ $customer?->nama }}</strong>
                            </div>
                            <div class="flex justify-between items-center text-ink-600 dark:text-ink-300">
                                <span>Saldo Piutang Saat Ini:</span>
                                <span class="font-bold tabular-nums">Rp {{ number_format($saldoPiutang, 0, ',', '.') }}</span>
                            </div>
                            @if($maxPiutang > 0)
                                <div class="flex justify-between items-center text-ink-600 dark:text-ink-300">
                                    <span>Plafon Limit Piutang:</span>
                                    <span class="font-bold tabular-nums">Rp {{ number_format($maxPiutang, 0, ',', '.') }}</span>
                                </div>
                                <div class="flex justify-between items-center font-bold pt-1 border-t border-white/10 {{ $overLimit ? 'text-up-red' : 'text-up-mint' }}">
                                    <span>Estimasi Saldo Setelah Trx:</span>
                                    <span class="tabular-nums">Rp {{ number_format($totalSetelahTrx, 0, ',', '.') }}</span>
                                </div>
                                @if($overLimit)
                                    <div class="p-2 rounded-lg bg-up-red/15 text-up-red text-[11px] font-bold border border-up-red/25 mt-1">
                                        ⚠️ Transaksi ini melebihi plafon limit kasbon pelanggan (Maks: Rp {{ number_format($maxPiutang, 0, ',', '.') }}).
                                    </div>
                                @endif
                            @endif
                        </div>
                    @endif
                </div>
            @elseif($metodeBayar === 'qris')
                <div class="p-5 rounded-2xl bg-ink-50 dark:bg-white/[0.03] border border-ink-200/80 dark:border-white/10 text-center mb-5 space-y-3"
                     wire:poll.3s="cekStatusQrisOtomatis">
                    @if($qrisTerbayarOtomatis)
                        <div class="p-3 rounded-xl bg-up-mint/20 border border-up-mint/40 text-up-mint font-bold text-xs flex items-center justify-center gap-2">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                            </svg>
                            <span>PEMBAYARAN QRIS TERDETEKSI LUNAS!</span>
                        </div>
                    @endif

                    @if($qrisKasirSvg)
                        <div class="flex flex-col items-center">
                            <span class="text-[11px] uppercase tracking-wider font-extrabold text-ink-500 dark:text-ink-400 block mb-1">
                                {{ $qrisMerchantName ?: 'QRIS TOKO' }}
                            </span>

                            <!-- QR Code SVG Container with Zoom Affordance -->
                            <div
                                id="qris-svg-render-main"
                                @click="showQrisZoom = true"
                                class="p-3.5 bg-white rounded-2xl shadow-md border border-ink-200/80 inline-block my-1 cursor-pointer group relative hover:ring-2 hover:ring-up-primary transition"
                                title="Klik untuk memperbesar tampilan QRIS di layar"
                            >
                                <div class="w-48 h-48 flex items-center justify-center pointer-events-none">
                                    {!! $qrisKasirSvg !!}
                                </div>
                                <div class="absolute inset-0 bg-black/40 rounded-2xl opacity-0 group-hover:opacity-100 flex items-center justify-center transition-opacity text-white text-xs font-bold gap-1.5">
                                    <span>🔍 Perbesar Layar</span>
                                </div>
                            </div>

                            <span class="text-xl font-black text-up-primary tabular-nums mt-1 block">
                                Rp {{ number_format($totalAkhir, 0, ',', '.') }}
                            </span>
                            <span class="text-[11px] text-ink-500 dark:text-ink-400">
                                Nominal terkunci otomatis (konsumen tidak perlu ketik nominal)
                            </span>

                            <!-- Live Webhook Listening Indicator -->
                            <div class="mt-2 inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-up-primary/10 border border-up-primary/20 text-up-primary text-[10px] font-semibold">
                                <span class="relative flex h-2 w-2">
                                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-up-primary opacity-75"></span>
                                    <span class="relative inline-flex rounded-full h-2 w-2 bg-up-primary"></span>
                                </span>
                                <span>Menunggu notifikasi pembayaran via Webhook (auto-check aktif)</span>
                            </div>

                            <!-- Quick Action Toolbar -->
                            <div class="mt-3 flex flex-wrap items-center justify-center gap-2">
                                <button
                                    type="button"
                                    @click="showQrisZoom = true"
                                    class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-up-primary text-white font-bold text-xs shadow-sm hover:opacity-95 transition active:scale-[0.97] cursor-pointer"
                                >
                                    <span>🔍 Layar Penuh (Popup)</span>
                                </button>

                                <button
                                    type="button"
                                    @click="downloadQris('qris-svg-render-main', 'png')"
                                    class="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl bg-ink-200/80 hover:bg-ink-300 dark:bg-white/10 dark:hover:bg-white/15 text-xs font-bold text-ink-800 dark:text-ink-200 transition cursor-pointer"
                                    title="Unduh QR Code sebagai file gambar PNG"
                                >
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                                    </svg>
                                    <span>Unduh PNG</span>
                                </button>

                                <button
                                    type="button"
                                    wire:click="cekStatusQrisManual"
                                    wire:loading.attr="disabled"
                                    class="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl bg-ink-200/80 hover:bg-ink-300 dark:bg-white/10 dark:hover:bg-white/15 text-xs font-bold text-ink-800 dark:text-ink-200 transition cursor-pointer disabled:opacity-50"
                                >
                                    <span wire:loading.remove wire:target="cekStatusQrisManual">🔄 Cek Status</span>
                                    <span wire:loading wire:target="cekStatusQrisManual" class="inline-flex items-center gap-1">
                                        <svg class="animate-spin -ml-0.5 mr-1 h-3.5 w-3.5 text-ink-800 dark:text-ink-200" fill="none" viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                        </svg>
                                        Memeriksa...
                                    </span>
                                </button>
                            </div>

                            <!-- Direct WhatsApp Send Bar -->
                            <div class="mt-3 pt-3 border-t border-ink-200/60 dark:border-white/10 w-full max-w-sm">
                                <span class="text-[11px] font-semibold text-ink-600 dark:text-ink-400 block mb-1.5">
                                    📲 Kirim QRIS & Rincian Tagihan ke WhatsApp Pelanggan:
                                </span>
                                <div class="flex items-center gap-2">
                                    <input
                                        type="tel"
                                        wire:model.live.debounce.400ms="qrisCustomerPhone"
                                        placeholder="Nomor WA (misal: 08123456789)"
                                        class="flex-1 px-3 py-2 rounded-xl border border-ink-200 dark:border-white/10 bg-white dark:bg-black/20 text-xs text-ink-900 dark:text-white font-mono focus:ring-1 focus:ring-up-primary"
                                    />
                                    <button
                                        type="button"
                                        wire:click="kirimQrisViaWhatsApp"
                                        class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs shadow-sm transition active:scale-[0.97] cursor-pointer shrink-0"
                                    >
                                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                                            <path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448l-6.305 1.654zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884-.001 2.225.651 3.891 1.746 5.634l-.999 3.648 3.742-.981zm11.387-5.464c-.074-.124-.272-.198-.57-.347-.297-.149-1.758-.868-2.031-.967-.272-.099-.47-.149-.669.149-.198.297-.768.967-.941 1.165-.173.198-.347.223-.644.074-.297-.149-1.255-.462-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.297-.347.446-.521.151-.172.2-.296.3-.495.099-.198.05-.372-.025-.521-.075-.148-.669-1.611-.916-2.206-.242-.579-.487-.501-.669-.51l-.57-.01c-.198 0-.52.074-.792.372s-1.04 1.016-1.04 2.479 1.065 2.876 1.213 3.074c.149.198 2.095 3.2 5.076 4.487.709.306 1.263.489 1.694.626.712.226 1.36.194 1.872.118.571-.085 1.758-.719 2.006-1.413.248-.695.248-1.29.173-1.414z"/>
                                        </svg>
                                        <span>Kirim WA</span>
                                    </button>
                                </div>
                            </div>
                        </div>
                    @else
                        <div class="py-4 text-center">
                            <span class="text-3xl mb-2 block">📱</span>
                            <span class="text-xs font-bold text-ink-800 dark:text-ink-200 block">QRIS Toko Belum Dikonfigurasi</span>
                            <p class="text-[11px] text-ink-500 dark:text-ink-400 mt-1 max-w-sm mx-auto">
                                Unggah foto QRIS toko di menu <strong>Pengaturan &gt; QRIS Toko</strong> agar QR dinamis otomatis muncul di sini.
                            </p>
                        </div>
                    @endif
                </div>
            @else
                <div class="p-5 rounded-2xl bg-ink-50 dark:bg-white/[0.03] border border-ink-200/80 dark:border-white/10 text-center text-xs text-ink-600 dark:text-ink-300 mb-5">
                    <span>Silakan konfirmasi bukti transfer sebesar</span>
                    <strong class="text-ink-900 dark:text-white block mt-1.5 text-lg font-black tabular-nums">
                        Rp {{ number_format($totalAkhir, 0, ',', '.') }}
                    </strong>
                </div>
            @endif

            <!-- Pengaturan Garansi Sparepart Manual -->
            <div class="mb-5 p-3.5 rounded-2xl bg-ink-50 dark:bg-white/[0.02] border border-ink-200/80 dark:border-white/10 space-y-2.5">
                <div class="flex items-center justify-between">
                    <label class="text-xs font-bold text-ink-700 dark:text-ink-300 uppercase tracking-wider flex items-center gap-1.5">
                        <span class="w-2 h-2 rounded-full bg-up-primary"></span>
                        <span>Garansi Sparepart Toko</span>
                    </label>
                    <span class="text-[10px] text-ink-400">Atur manual per transaksi</span>
                </div>

                <div class="grid grid-cols-3 sm:grid-cols-6 gap-1.5">
                    @foreach([
                        '3_hari' => '3 Hari',
                        '7_hari' => '7 Hari',
                        '14_hari' => '14 Hari',
                        '30_hari' => '30 Hari',
                        'custom' => 'Custom',
                        'tanpa_garansi' => 'Tanpa Grs',
                    ] as $gKey => $gLabel)
                        <button
                            type="button"
                            wire:click="setGaransiPilihan('{{ $gKey }}')"
                            class="py-2 px-1.5 rounded-xl text-xs font-bold border transition text-center cursor-pointer min-h-[38px] flex items-center justify-center {{ $garansiPilihan === $gKey ? 'bg-up-primary text-white border-up-primary shadow-sm shadow-up-primary/30' : 'bg-white dark:bg-white/5 border-ink-200/80 dark:border-white/10 text-ink-700 dark:text-ink-300 hover:bg-ink-100 dark:hover:bg-white/10' }}"
                        >
                            {{ $gLabel }}
                        </button>
                    @endforeach
                </div>

                @if($garansiPilihan === 'custom')
                    <div class="flex items-center gap-2 pt-1">
                        <span class="text-xs text-ink-400 whitespace-nowrap">Durasi Garansi:</span>
                        <input
                            type="number"
                            min="1"
                            max="365"
                            wire:model.live="garansiCustomHari"
                            class="w-24 px-3 py-1.5 rounded-xl glass-input text-xs font-bold text-ink-900 dark:text-white"
                            placeholder="Hari"
                        />
                        <span class="text-xs text-ink-400">Hari Kalender</span>
                    </div>
                @endif

                <div class="text-[10px] text-ink-400 flex items-center gap-1.5 pt-0.5">
                    <svg class="w-3.5 h-3.5 text-up-mint shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <span>T&C: Segel utuh, tidak ada cacat fisik/bekas lem, wajib membawa struk ber-QR saat klaim.</span>
                </div>
            </div>

            <!-- Submit Button Grid -->
            <div class="flex flex-col-reverse sm:flex-row gap-2.5">
                <button
                    type="button"
                    wire:click="$set('showPaymentModal', false)"
                    class="w-full sm:w-1/3 py-3 rounded-xl bg-ink-100 hover:bg-ink-200 dark:bg-white/5 dark:hover:bg-white/10 text-ink-700 dark:text-ink-300 font-bold text-xs transition-colors border border-ink-200/80 dark:border-white/10 cursor-pointer min-h-[44px] active:scale-[0.98]"
                >
                    Batal
                </button>
                <button
                    type="button"
                    wire:click="processTransaction"
                    class="w-full sm:w-2/3 py-3 rounded-xl bg-gradient-to-r from-up-mint to-teal-500 hover:opacity-95 text-ink-950 font-black text-xs shadow-lg shadow-up-mint/25 transition-transform active:scale-[0.98] cursor-pointer min-h-[44px] flex items-center justify-center gap-2"
                >
                    <span>Konfirmasi & Cetak Struk</span>
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M14 5l7 7m0 0l-7 7m7-7H3" />
                    </svg>
                </button>
            </div>
        </div>

        <!-- FULLSCREEN POPUP ZOOM QRIS (TAMPILAN BESAR DI LAYAR) -->
        <div
            x-show="showQrisZoom"
            x-cloak
            class="fixed inset-0 z-[70] flex items-center justify-center bg-black/90 backdrop-blur-md p-4 sm:p-6"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0 scale-95"
            x-transition:enter-end="opacity-100 scale-100"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100 scale-100"
            x-transition:leave-end="opacity-0 scale-95"
            @keydown.escape.window="showQrisZoom = false"
        >
            <div
                @click.away="showQrisZoom = false"
                class="w-full max-w-md bg-white rounded-3xl p-6 sm:p-8 text-center shadow-2xl relative border-4 border-up-primary/40 space-y-4 text-ink-900"
            >
                <!-- Close Button -->
                <button
                    type="button"
                    @click="showQrisZoom = false"
                    class="absolute top-4 right-4 w-9 h-9 rounded-full bg-ink-100 hover:bg-ink-200 text-ink-700 font-black text-sm flex items-center justify-center transition cursor-pointer"
                >
                    ✕
                </button>

                <!-- Brand / Header -->
                <div class="space-y-1">
                    <div class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-up-primary/10 text-up-primary font-black text-[11px] tracking-wider uppercase">
                        <span>QRIS DINAMIS • STANDAR ASPI</span>
                    </div>
                    <h3 class="text-xl font-black text-ink-900 uppercase tracking-tight">
                        {{ $qrisMerchantName ?: 'QRIS TOKO' }}
                    </h3>
                    <span class="text-xs text-ink-500 font-mono block">
                        No. Transaksi: {{ $qrisInvoice ?: 'TRX-POS' }}
                    </span>
                </div>

                <!-- Big QR Code Display -->
                <div id="qris-svg-zoom-render" class="w-72 h-72 sm:w-80 sm:h-80 mx-auto p-4 bg-white rounded-2xl border-2 border-ink-200 shadow-inner flex items-center justify-center">
                    {!! $qrisKasirSvg !!}
                </div>

                <!-- Total Price Tag -->
                <div>
                    <span class="text-xs text-ink-500 font-semibold block uppercase tracking-wider">Total Tagihan Pembayaran</span>
                    <span class="text-3xl font-black text-up-primary tabular-nums tracking-tight block">
                        Rp {{ number_format($metodeBayar === 'split' ? $parsedNonTunai : $totalAkhir, 0, ',', '.') }}
                    </span>
                    <span class="text-[11px] text-ink-500 block mt-0.5">
                        Nominal otomatis terkunci • Konsumen tidak perlu mengetik nominal
                    </span>
                </div>

                <!-- Actions in Zoom Popup -->
                <div class="pt-2 flex flex-wrap items-center justify-center gap-2">
                    <button
                        type="button"
                        @click="downloadQris('qris-svg-zoom-render', 'png')"
                        class="px-4 py-2.5 rounded-xl bg-ink-900 hover:bg-black text-white font-bold text-xs flex items-center gap-2 shadow-sm transition active:scale-[0.98] cursor-pointer"
                        title="Unduh file gambar QR Code"
                    >
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                        </svg>
                        <span>Unduh PNG</span>
                    </button>

                    <button
                        type="button"
                        wire:click="kirimQrisViaWhatsApp"
                        class="px-4 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs flex items-center gap-2 shadow-sm transition active:scale-[0.98] cursor-pointer"
                    >
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448l-6.305 1.654zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884-.001 2.225.651 3.891 1.746 5.634l-.999 3.648 3.742-.981zm11.387-5.464c-.074-.124-.272-.198-.57-.347-.297-.149-1.758-.868-2.031-.967-.272-.099-.47-.149-.669.149-.198.297-.768.967-.941 1.165-.173.198-.347.223-.644.074-.297-.149-1.255-.462-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.297-.347.446-.521.151-.172.2-.296.3-.495.099-.198.05-.372-.025-.521-.075-.148-.669-1.611-.916-2.206-.242-.579-.487-.501-.669-.51l-.57-.01c-.198 0-.52.074-.792.372s-1.04 1.016-1.04 2.479 1.065 2.876 1.213 3.074c.149.198 2.095 3.2 5.076 4.487.709.306 1.263.489 1.694.626.712.226 1.36.194 1.872.118.571-.085 1.758-.719 2.006-1.413.248-.695.248-1.29.173-1.414z"/>
                        </svg>
                        <span>Kirim WA</span>
                    </button>

                    <button
                        type="button"
                        @click="showQrisZoom = false"
                        class="px-4 py-2.5 rounded-xl bg-ink-100 hover:bg-ink-200 text-ink-700 font-bold text-xs transition cursor-pointer"
                    >
                        Tutup
                    </button>
                </div>
            </div>
        </div>
    </div>
@endif
