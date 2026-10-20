<!-- MODAL: DAFTAR TRANSAKSI DITAHAN (HOLD / PARK) -->
@if($showDitahanPanel)
    <div
        class="fixed inset-0 z-50 flex items-center justify-center bg-ink-950/80 backdrop-blur-md p-4 sm:p-6 overflow-y-auto"
        x-data="{}"
        @keydown.window.escape="$wire.tutupDitahanPanel()"
    >
        <div class="w-full max-w-2xl bg-white dark:bg-[#0B1120] border border-ink-200/80 dark:border-white/10 rounded-3xl shadow-2xl overflow-hidden my-auto flex flex-col max-h-[90vh]">
            <!-- Header Modal -->
            <div class="p-4 sm:p-5 border-b border-ink-200/70 dark:border-white/10 flex items-center justify-between gap-3 bg-ink-50/50 dark:bg-white/[0.02]">
                <div class="flex items-center gap-2.5">
                    <span class="w-3 h-3 rounded-full bg-up-amber ring-4 ring-up-amber/20"></span>
                    <div>
                        <h3 class="text-base sm:text-lg font-bold text-ink-900 dark:text-white flex items-center gap-2">
                            <span>Transaksi Ditahan (Park)</span>
                            <span class="px-2 py-0.5 rounded-full text-xs font-mono font-bold bg-up-amber/20 text-up-amber border border-up-amber/30">
                                {{ $ditahanList->count() }}
                            </span>
                        </h3>
                        <p class="text-[11px] sm:text-xs text-ink-500 dark:text-ink-400">
                            Pilih transaksi yang ingin dilanjutkan pembayarannya atau hapus transaksi yang batal
                        </p>
                    </div>
                </div>
                <button
                    type="button"
                    wire:click="tutupDitahanPanel"
                    class="p-2 rounded-xl text-ink-400 hover:text-ink-900 dark:hover:text-white hover:bg-ink-100 dark:hover:bg-white/5 transition-colors cursor-pointer"
                    title="Tutup (ESC)"
                >
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>

            <!-- List Transaksi Ditahan Body -->
            <div class="p-4 sm:p-5 overflow-y-auto space-y-3 flex-1">
                @forelse($ditahanList as $dt)
                    @php
                        $detail = $dt->split_detail ?? [];
                        $cartItems = $detail['cart'] ?? [];
                        $totalItemCount = collect($cartItems)->sum('qty') ?: $dt->items->count();
                    @endphp
                    <div class="p-3.5 sm:p-4 rounded-2xl bg-ink-50/70 dark:bg-white/[0.03] border border-ink-200/80 dark:border-white/10 hover:border-up-primary/40 transition-all flex flex-col gap-3">
                        <!-- Top Info: No Trx, Jam, Kasir & Pelanggan -->
                        <div class="flex items-start justify-between gap-2 flex-wrap">
                            <div class="space-y-1">
                                <div class="flex items-center gap-2 flex-wrap">
                                    <span class="font-mono text-xs sm:text-sm font-bold text-ink-900 dark:text-white bg-white dark:bg-ink-900/80 px-2 py-0.5 rounded-md border border-ink-200/60 dark:border-white/10">
                                        {{ $dt->no_transaksi }}
                                    </span>
                                    <span class="text-[11px] text-ink-400 flex items-center gap-1">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                        {{ $dt->created_at?->format('H:i') }} ({{ $dt->created_at?->diffForHumans() }})
                                    </span>
                                </div>
                                <div class="flex items-center gap-2 text-[11px] text-ink-500 dark:text-ink-400 flex-wrap">
                                    <span>Kasir: <strong class="text-ink-700 dark:text-ink-200">{{ $dt->kasir?->name ?? 'Kasir' }}</strong></span>
                                    <span>•</span>
                                    <span>Konsumen: <strong class="text-up-primary">{{ $dt->pelanggan?->nama ?? 'Pelanggan Umum' }}</strong></span>
                                </div>
                            </div>

                            <!-- Total Tagihan -->
                            <div class="text-right">
                                <span class="text-[10px] uppercase font-bold text-ink-400 tracking-wider block">Total Tagihan</span>
                                <span class="text-base sm:text-lg font-black font-mono text-up-mint tabular-nums">
                                    Rp {{ number_format($dt->total_akhir, 0, ',', '.') }}
                                </span>
                            </div>
                        </div>

                        <!-- Item Belanjaan Ringkas -->
                        <div class="p-2.5 rounded-xl bg-white/70 dark:bg-black/30 border border-ink-200/50 dark:border-white/5 text-xs">
                            <div class="font-semibold text-ink-700 dark:text-ink-300 mb-1 flex items-center justify-between">
                                <span>Rincian Barang ({{ $totalItemCount }} item):</span>
                            </div>
                            <div class="space-y-1 max-h-24 overflow-y-auto pr-1">
                                @forelse($cartItems as $cItem)
                                    <div class="flex items-center justify-between text-[11px] text-ink-600 dark:text-ink-400">
                                        <span class="truncate max-w-[70%]">
                                            {{ $cItem['nama'] ?? 'Item' }}
                                            <span class="text-ink-400 font-mono">({{ $cItem['qty'] ?? 1 }} {{ strtoupper($cItem['satuan'] ?? 'pcs') }})</span>
                                        </span>
                                        <span class="tabular-nums font-mono">
                                            Rp {{ number_format($cItem['subtotal'] ?? 0, 0, ',', '.') }}
                                        </span>
                                    </div>
                                @empty
                                    <span class="text-ink-400 italic text-[11px]">Rincian item tidak tersedia</span>
                                @endforelse
                            </div>
                        </div>

                        <!-- Tombol Aksi: Lanjut vs Hapus -->
                        <div class="flex items-center justify-between gap-2 pt-1 border-t border-ink-200/50 dark:border-white/5">
                            <button
                                type="button"
                                wire:click="hapusDitahan({{ $dt->id }})"
                                wire:confirm="Yakin ingin membatalkan dan menghapus transaksi ditahan #{{ $dt->no_transaksi }} ini?"
                                class="px-3 py-2 rounded-xl text-xs font-bold text-up-red bg-up-red/10 hover:bg-up-red/20 border border-up-red/30 transition cursor-pointer flex items-center gap-1.5 active:scale-95 min-h-[38px]"
                                title="Hapus transaksi ditahan ini"
                            >
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                </svg>
                                <span>Hapus Transaksi</span>
                            </button>

                            <button
                                type="button"
                                wire:click="resumeDitahan({{ $dt->id }})"
                                class="px-4 py-2 rounded-xl text-xs font-black text-white bg-gradient-to-r from-up-primary to-indigo-600 hover:opacity-95 shadow-md shadow-up-primary/20 transition cursor-pointer flex items-center gap-2 active:scale-95 min-h-[38px]"
                                title="Buka kembali keranjang dan lanjutkan pembayaran"
                            >
                                <span>Lanjutkan Transaksi</span>
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3" />
                                </svg>
                            </button>
                        </div>
                    </div>
                @empty
                    <div class="py-12 text-center flex flex-col items-center justify-center gap-3">
                        <div class="w-14 h-14 rounded-2xl bg-ink-100 dark:bg-white/5 border border-ink-200/80 dark:border-white/10 flex items-center justify-center text-ink-400">
                            <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                        <div class="space-y-1">
                            <h4 class="text-sm font-bold text-ink-900 dark:text-white">Tidak Ada Transaksi yang Ditahan</h4>
                            <p class="text-xs text-ink-500 dark:text-ink-400 max-w-sm mx-auto">
                                Keranjang aktif dapat ditahan sementara dengan menekan tombol <strong>Tahan (F6)</strong> di bagian bawah kasir.
                            </p>
                        </div>
                    </div>
                @endforelse
            </div>

            <!-- Footer Modal -->
            <div class="p-3 sm:p-4 border-t border-ink-200/70 dark:border-white/10 flex items-center justify-between bg-ink-50/50 dark:bg-white/[0.02]">
                <span class="text-[11px] text-ink-500">
                    Shortcut: Tekan <kbd class="px-1.5 py-0.5 rounded bg-ink-200 dark:bg-white/10 font-mono text-[10px]">ESC</kbd> untuk menutup
                </span>
                <button
                    type="button"
                    wire:click="tutupDitahanPanel"
                    class="px-4 py-2 rounded-xl text-xs font-bold text-ink-700 dark:text-ink-300 hover:bg-ink-100 dark:hover:bg-white/10 transition cursor-pointer"
                >
                    Tutup
                </button>
            </div>
        </div>
    </div>
@endif
