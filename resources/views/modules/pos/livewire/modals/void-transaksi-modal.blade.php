<!-- MODAL: OTORISASI VOID TRANSAKSI POS -->
@if($showVoidModal)
    <div
        class="fixed inset-0 z-50 flex items-center justify-center bg-ink-950/80 backdrop-blur-md p-4 sm:p-6 overflow-y-auto"
        x-data="{}"
        @keydown.window.escape="$wire.tutupVoidModal()"
    >
        <div class="w-full max-w-md bg-white dark:bg-[#0B1120] border border-ink-200/80 dark:border-white/10 rounded-3xl shadow-2xl overflow-hidden my-auto flex flex-col">
            <!-- Header Modal -->
            <div class="p-4 sm:p-5 border-b border-ink-200/70 dark:border-white/10 flex items-center justify-between gap-3 bg-up-red/10">
                <div class="flex items-center gap-2.5">
                    <div class="w-9 h-9 rounded-xl bg-up-red/20 border border-up-red/30 flex items-center justify-center text-up-red font-bold">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-base font-bold text-ink-900 dark:text-white">Void / Batalkan Transaksi</h3>
                        <p class="text-[11px] text-ink-500 dark:text-ink-400">Pembatalan struk penjualan & reversal pembukuan</p>
                    </div>
                </div>
                <button
                    type="button"
                    wire:click="tutupVoidModal"
                    class="p-2 rounded-xl text-ink-400 hover:text-ink-900 dark:hover:text-white hover:bg-ink-100 dark:hover:bg-white/5 transition-colors cursor-pointer"
                >✕</button>
            </div>

            <!-- Body Form Void -->
            <div class="p-4 sm:p-5 space-y-4">
                <!-- Info Transaksi Terpilih -->
                <div class="p-3 rounded-2xl bg-ink-50 dark:bg-white/[0.03] border border-ink-200/70 dark:border-white/5 flex items-center justify-between text-xs">
                    <div>
                        <span class="text-[10px] text-ink-400 uppercase font-bold block">No. Transaksi</span>
                        <span class="font-mono font-bold text-ink-900 dark:text-white">{{ $voidNoTransaksi }}</span>
                    </div>
                    <div class="text-right">
                        <span class="text-[10px] text-ink-400 uppercase font-bold block">Total Tagihan</span>
                        <span class="font-mono font-black text-up-mint tabular-nums">Rp {{ number_format($voidTotalAkhir, 0, ',', '.') }}</span>
                    </div>
                </div>

                <div class="p-3 rounded-xl bg-up-red/10 border border-up-red/20 text-[11px] text-up-red space-y-1">
                    <p class="font-bold flex items-center gap-1">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        <span>Dampak Pembatalan (Void):</span>
                    </p>
                    <ul class="list-disc list-inside space-y-0.5 text-[10px] pl-1 text-ink-600 dark:text-ink-300">
                        <li>Stok barang otomatis dikembalikan ke gudang cabang</li>
                        <li>Jurnal pendapatan & HPP dibalikkan di Akunting</li>
                        <li>Piutang atau komisi terkait akan dibatalkan</li>
                        <li>Poin & total belanja loyalitas konsumen dikurangi</li>
                    </ul>
                </div>

                <!-- Alasan Void -->
                <div>
                    <label class="block text-xs font-bold text-ink-700 dark:text-ink-300 mb-1">
                        Alasan Void / Pembatalan *
                    </label>
                    <textarea
                        wire:model="voidAlasan"
                        rows="2"
                        placeholder="Contoh: Konsumen membatalkan pembelian / salah input nominal"
                        class="w-full px-3 py-2 rounded-xl glass-input text-xs font-medium text-ink-900 dark:text-white border border-ink-200/80 dark:border-white/10 focus:border-up-red focus:ring-1 focus:ring-up-red"
                    ></textarea>
                    @error('voidAlasan') <span class="text-[10px] text-up-red font-semibold mt-1 block">{{ $message }}</span> @enderror
                </div>

                <!-- Otorisasi Supervisor -->
                <div class="pt-3 border-t border-ink-200/70 dark:border-white/10 space-y-3">
                    <div class="flex items-center gap-1.5 text-xs font-bold text-ink-700 dark:text-ink-300">
                        <svg class="w-4 h-4 text-up-amber" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                        </svg>
                        <span>Otorisasi Supervisor / Admin Toko</span>
                    </div>

                    <div>
                        <label class="block text-[11px] font-semibold text-ink-500 dark:text-ink-400 mb-1">
                            Email / Username Supervisor *
                        </label>
                        <input
                            type="text"
                            wire:model="voidSupervisorAuth"
                            placeholder="admin@uteparts.id"
                            class="w-full px-3 py-2 rounded-xl glass-input text-xs font-medium text-ink-900 dark:text-white border border-ink-200/80 dark:border-white/10"
                        />
                        @error('voidSupervisorAuth') <span class="text-[10px] text-up-red font-semibold mt-1 block">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-[11px] font-semibold text-ink-500 dark:text-ink-400 mb-1">
                            Password Supervisor *
                        </label>
                        <input
                            type="password"
                            wire:model="voidSupervisorPassword"
                            placeholder="••••••••"
                            class="w-full px-3 py-2 rounded-xl glass-input text-xs font-medium text-ink-900 dark:text-white border border-ink-200/80 dark:border-white/10"
                        />
                        @error('voidSupervisorPassword') <span class="text-[10px] text-up-red font-semibold mt-1 block">{{ $message }}</span> @enderror
                    </div>
                </div>
            </div>

            <!-- Footer Modal -->
            <div class="p-4 border-t border-ink-200/70 dark:border-white/10 flex items-center justify-end gap-2 bg-ink-50/50 dark:bg-white/[0.02]">
                <button
                    type="button"
                    wire:click="tutupVoidModal"
                    class="px-4 py-2 rounded-xl text-xs font-bold text-ink-600 dark:text-ink-400 hover:bg-ink-100 dark:hover:bg-white/10 transition cursor-pointer min-h-[38px]"
                >
                    Batal
                </button>
                <button
                    type="button"
                    wire:click="prosesVoidTransaksi"
                    class="px-4 py-2 rounded-xl text-xs font-bold text-white bg-up-red hover:bg-red-600 shadow-md shadow-up-red/20 transition cursor-pointer flex items-center gap-1.5 min-h-[38px] active:scale-95"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                    </svg>
                    <span>Konfirmasi Void</span>
                </button>
            </div>
        </div>
    </div>
@endif
