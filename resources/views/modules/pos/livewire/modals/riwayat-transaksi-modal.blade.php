<!-- MODAL: RIWAYAT TRANSAKSI HARI INI -->
@if($showRiwayatTransaksiModal)
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-ink-950/80 backdrop-blur-md p-4 sm:p-6 overflow-y-auto">
        <div class="w-full max-w-4xl bg-white dark:bg-[#0B1120] border border-ink-200/80 dark:border-white/10 p-5 sm:p-6 rounded-3xl relative my-auto max-h-[85vh] flex flex-col shadow-2xl">
            <!-- Header -->
            <div class="flex items-center justify-between pb-3.5 mb-3 border-b border-ink-200/70 dark:border-white/10">
                <div class="flex items-center gap-2.5">
                    <div class="w-9 h-9 rounded-xl bg-up-primary/10 border border-up-primary/20 flex items-center justify-center text-up-primary font-bold">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-base font-bold text-ink-900 dark:text-white">Riwayat Transaksi & Retur Penjualan</h3>
                        <p class="text-[11px] text-ink-500 dark:text-ink-400">Cari nomor faktur atau pilih transaksi selesai untuk memproses retur/tukar produk</p>
                    </div>
                </div>
                <button
                    type="button"
                    wire:click="tutupRiwayatTransaksi"
                    class="p-2 rounded-xl text-ink-400 hover:text-ink-900 dark:hover:text-white hover:bg-ink-100 dark:hover:bg-white/5 transition-colors cursor-pointer"
                >✕</button>
            </div>

            <!-- Search Box Transaksi -->
            <div class="mb-3">
                <div class="relative">
                    <input
                        type="text"
                        wire:model.live.debounce.300ms="riwayatSearch"
                        placeholder="Ketik No. Transaksi (misal: TRX-...) atau Nama Pelanggan untuk mencari nota..."
                        class="w-full px-3.5 py-2.5 rounded-xl glass-input text-xs font-medium text-ink-900 dark:text-white min-h-[42px]"
                    />
                    @if($riwayatSearch)
                        <button
                            type="button"
                            wire:click="$set('riwayatSearch', '')"
                            class="absolute right-3 top-1/2 -translate-y-1/2 text-ink-400 hover:text-ink-600 text-xs"
                        >✕ Bersihkan</button>
                    @endif
                </div>
            </div>

            <!-- Content -->
            <div class="overflow-y-auto flex-1 pr-1">
                @if($riwayatTransaksiHariIni->isEmpty())
                    <div class="py-16 text-center text-ink-400 text-xs">
                        <div class="w-12 h-12 rounded-2xl bg-ink-100 dark:bg-white/5 border border-ink-200/80 dark:border-white/10 mx-auto mb-2 flex items-center justify-center text-ink-400">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                            </svg>
                        </div>
                        <p class="font-semibold text-ink-700 dark:text-ink-300">Belum ada transaksi</p>
                        <p class="text-[11px] text-ink-500 mt-0.5">Transaksi hari ini di cabang ini akan muncul di sini</p>
                    </div>
                @else
                    <div class="overflow-x-auto min-w-full rounded-2xl border border-ink-200/80 dark:border-white/5">
                        <table class="w-full text-left text-xs whitespace-nowrap">
                            <thead>
                                <tr class="border-b border-ink-200/80 dark:border-white/10 bg-ink-50/80 dark:bg-white/[0.02] text-ink-600 dark:text-ink-400 uppercase text-[10px] font-bold tracking-wider">
                                    <th class="py-3 px-3.5">No Transaksi</th>
                                    <th class="py-3 px-3.5">Waktu</th>
                                    <th class="py-3 px-3.5">Kasir</th>
                                    <th class="py-3 px-3.5">Pelanggan</th>
                                    <th class="py-3 px-3.5 text-right">Total</th>
                                    <th class="py-3 px-3.5 text-center">Metode</th>
                                    <th class="py-3 px-3.5 text-center">Status</th>
                                    <th class="py-3 px-3.5 text-right">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-ink-200/60 dark:divide-white/5">
                                @foreach($riwayatTransaksiHariIni as $trx)
                                    <tr class="hover:bg-ink-50 dark:hover:bg-white/[0.02] transition-colors">
                                        <td class="py-3 px-3.5 font-mono font-bold text-ink-900 dark:text-white">{{ $trx->no_transaksi }}</td>
                                        <td class="py-3 px-3.5 text-ink-600 dark:text-ink-300 tabular-nums">{{ $trx->created_at?->format('H:i:s') }}</td>
                                        <td class="py-3 px-3.5 text-ink-700 dark:text-ink-200">{{ $trx->kasir?->name ?? '-' }}</td>
                                        <td class="py-3 px-3.5 text-ink-700 dark:text-ink-200 font-medium">{{ $trx->pelanggan?->nama ?? 'Umum' }}</td>
                                        <td class="py-3 px-3.5 text-right font-black text-up-mint tabular-nums">Rp {{ number_format($trx->total_akhir, 0, ',', '.') }}</td>
                                        <td class="py-3 px-3.5 text-center uppercase text-[10px] text-ink-700 dark:text-ink-300 font-bold">{{ $trx->metode_bayar }}</td>
                                        <td class="py-3 px-3.5 text-center">
                                            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold {{ $trx->status === 'selesai' ? 'bg-up-mint/15 text-up-mint border border-up-mint/30' : ($trx->status === 'void' ? 'bg-purple-500/15 text-purple-400 border border-purple-500/30' : ($trx->status === 'dibatalkan' ? 'bg-up-red/15 text-up-red border border-up-red/30' : 'bg-up-amber/15 text-up-amber border border-up-amber/30')) }}">
                                                {{ strtoupper($trx->status) }}
                                            </span>
                                        </td>
                                        <td class="py-3 px-3.5 text-right whitespace-nowrap">
                                            <div class="flex items-center justify-end gap-1.5">
                                                <button
                                                    type="button"
                                                    wire:click="lihatDetailTransaksi({{ $trx->id }})"
                                                    class="px-2.5 py-1 rounded-lg bg-ink-100 hover:bg-ink-200 dark:bg-white/5 dark:hover:bg-white/10 text-ink-800 dark:text-ink-100 font-bold text-[11px] transition-colors border border-ink-200/80 dark:border-white/10 cursor-pointer"
                                                >
                                                    Detail
                                                </button>
                                                @if($trx->status === 'selesai')
                                                    <!-- Tombol Void Transaksi Kasir -->
                                                    <button
                                                        type="button"
                                                        wire:click="bukaVoidModal({{ $trx->id }})"
                                                        class="px-2.5 py-1 rounded-lg bg-up-red/10 hover:bg-up-red/20 text-up-red font-bold text-[11px] transition-colors border border-up-red/25 cursor-pointer flex items-center gap-1"
                                                        title="Batalkan & Void Transaksi Ini"
                                                    >
                                                        <span>Void</span>
                                                    </button>
                                                    <a
                                                        href="/app/pos/retur?transaksi_id={{ $trx->id }}"
                                                        class="px-2.5 py-1 rounded-lg bg-up-amber/10 hover:bg-up-amber/20 text-up-amber font-bold text-[11px] transition-colors border border-up-amber/25 cursor-pointer flex items-center"
                                                    >
                                                        Retur
                                                    </a>
                                                @endif
                                                @can('lihat-audit-log')
                                                    <button
                                                        type="button"
                                                        wire:click="bukaRiwayat('transaksi', {{ $trx->id }})"
                                                        class="px-2 py-1 rounded-lg bg-up-primary/10 hover:bg-up-primary/20 text-up-primary font-bold text-[11px] transition-colors border border-up-primary/20 cursor-pointer"
                                                    >
                                                        Audit
                                                    </button>
                                                @endcan
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>

            <!-- Footer -->
            <div class="pt-3.5 mt-2 border-t border-ink-200/70 dark:border-white/10 flex justify-end">
                <button
                    type="button"
                    wire:click="tutupRiwayatTransaksi"
                    class="px-5 py-2.5 rounded-xl bg-ink-100 hover:bg-ink-200 dark:bg-white/10 dark:hover:bg-white/15 text-ink-800 dark:text-white font-bold text-xs cursor-pointer min-h-[40px] transition-colors"
                >
                    Tutup
                </button>
            </div>
        </div>
    </div>
@endif
