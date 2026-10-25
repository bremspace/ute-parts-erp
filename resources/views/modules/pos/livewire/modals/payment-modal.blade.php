<!-- PAYMENT MODAL -->
@if($showPaymentModal)
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-ink-950/80 backdrop-blur-md p-4 sm:p-6 overflow-y-auto"
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
                    <div class="mt-1 text-[10px] text-up-amber font-bold">
                        💰 Mode: Komisi Akumulasi Mitra (Harga Retail)
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
                        <label class="block text-xs font-bold text-ink-700 dark:text-ink-300 uppercase tracking-wider mb-1">Nominal Non-Tunai (Transfer / QRIS)</label>
                        <input
                            type="text"
                            inputmode="numeric"
                            x-format-number
                            wire:model.live.debounce.300ms="splitNonTunai"
                            class="w-full px-3.5 py-2.5 rounded-xl glass-input text-sm font-bold tabular-nums min-h-[44px]"
                            placeholder="0"
                        />
                    </div>
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
                        <div class="mt-3 p-2 rounded-xl bg-white/5 text-ink-800 dark:text-ink-200">
                            Pelanggan Terpilih: <strong class="text-ink-900 dark:text-white">{{ $customer?->nama }}</strong>
                        </div>
                    @endif
                </div>
            @else
                <div class="p-5 rounded-2xl bg-ink-50 dark:bg-white/[0.03] border border-ink-200/80 dark:border-white/10 text-center text-xs text-ink-600 dark:text-ink-300 mb-5">
                    <span>Silakan scan QRIS atau konfirmasi bukti transfer sebesar</span>
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
    </div>
@endif
