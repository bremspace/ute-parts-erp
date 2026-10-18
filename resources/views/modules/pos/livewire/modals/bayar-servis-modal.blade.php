<!-- ===== [POS-SERVIS] MODAL: PEMBAYARAN SERVIS DI KASIR POS ===== -->
@if($showBayarServisModal)
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-ink-950/80 backdrop-blur-md p-4 sm:p-6 overflow-y-auto">
        <div class="w-full max-w-2xl bg-white dark:bg-[#0B1120] border border-ink-200/80 dark:border-white/10 p-5 sm:p-6 rounded-3xl relative max-h-[90vh] flex flex-col shadow-2xl my-auto">
            <!-- Header -->
            <div class="flex items-center justify-between pb-3.5 mb-4 border-b border-ink-200/70 dark:border-white/10">
                <div class="flex items-center gap-2.5">
                    <div class="w-9 h-9 rounded-xl bg-blue-500/10 border border-blue-500/20 flex items-center justify-center text-blue-500 font-bold">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-base font-bold text-ink-900 dark:text-white">Pembayaran Servis di Kasir POS</h3>
                        <p class="text-[11px] text-ink-500 dark:text-ink-400">Penyatuan kas masuk laci kasir untuk pelunasan tiket servis</p>
                    </div>
                </div>
                <button
                    type="button"
                    wire:click="tutupBayarServisModal"
                    class="p-2 rounded-xl text-ink-400 hover:text-ink-900 dark:hover:text-white hover:bg-ink-100 dark:hover:bg-white/5 transition-colors cursor-pointer"
                >✕</button>
            </div>

            <div class="overflow-y-auto flex-1 space-y-4 pr-1">
                @if(! $selectedServisDetail)
                    {{-- STATE 1: PILIH TIKET SERVIS --}}
                    <div class="space-y-3">
                        <div class="relative">
                            <input
                                type="text"
                                wire:model.live.debounce.300ms="searchServis"
                                placeholder="Cari no tiket / nama / no hp / tipe hp..."
                                class="w-full px-4 py-2.5 rounded-xl glass-input text-xs font-semibold text-ink-900 dark:text-white border-ink-200/80 dark:border-white/10"
                                autofocus
                            />
                            @if($searchServis)
                                <button type="button" wire:click="$set('searchServis', '')" class="absolute right-3 top-2.5 text-xs text-ink-400 hover:text-ink-900 dark:hover:text-white">✕</button>
                            @endif
                        </div>

                        <div class="space-y-2 max-h-[55vh] overflow-y-auto pr-1">
                            @forelse($daftarServisSiapBayar as $ts)
                                <div class="p-3.5 rounded-2xl bg-ink-50/70 dark:bg-white/[0.02] hover:bg-ink-100 dark:hover:bg-white/[0.05] border border-ink-200/80 dark:border-white/5 transition flex items-center justify-between gap-3">
                                    <div class="min-w-0">
                                        <div class="flex items-center gap-2">
                                            <span class="font-mono font-bold text-ink-900 dark:text-white text-xs">{{ $ts->no_tiket }}</span>
                                            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-blue-500/15 text-blue-500 dark:text-blue-400 border border-blue-500/20">
                                                {{ ucfirst($ts->status) }}
                                            </span>
                                        </div>
                                        <p class="text-xs text-ink-700 dark:text-ink-200 font-medium mt-1">
                                            {{ $ts->pelanggan?->nama ?? $ts->nama_pelanggan ?? 'Pelanggan Umum' }}
                                            <span class="text-ink-400">({{ $ts->pelanggan?->telepon ?? $ts->telepon_pelanggan ?? '-' }})</span>
                                        </p>
                                        <p class="text-[11px] text-ink-500 dark:text-ink-400 mt-0.5 truncate">
                                            {{ $ts->jenis_hp }}{{ $ts->seri_hp ? ' · '.$ts->seri_hp : '' }} — {{ $ts->keluhan }}
                                        </p>
                                    </div>
                                    <div class="text-right flex flex-col items-end gap-1.5 flex-shrink-0">
                                        <span class="font-black text-sm text-up-mint tabular-nums">
                                            Rp {{ number_format($ts->estimasi_biaya ?? 0, 0, ',', '.') }}
                                        </span>
                                        <button
                                            type="button"
                                            wire:click="pilihTiketServis({{ $ts->id }})"
                                            class="px-3.5 py-1.5 rounded-xl bg-up-primary hover:bg-indigo-600 text-white text-xs font-bold transition active:scale-[0.98] cursor-pointer">
                                            Pilih
                                        </button>
                                    </div>
                                </div>
                            @empty
                                <div class="py-12 text-center text-ink-400 text-xs">
                                    Tidak ada tiket servis siap bayar (status selesai/diambil belum lunas) di cabang ini.
                                </div>
                            @endforelse
                        </div>
                    </div>
                @else
                    {{-- STATE 2: DETAIL TAGIHAN & PEMBAYARAN --}}
                    <div class="space-y-4">
                        <!-- Card Info Tiket -->
                        <div class="p-3.5 rounded-2xl bg-ink-50 dark:bg-white/[0.03] border border-ink-200/80 dark:border-white/5 flex items-center justify-between">
                            <div>
                                <div class="flex items-center gap-2">
                                    <span class="font-mono font-bold text-sm text-ink-900 dark:text-white">{{ $selectedServisDetail['no_tiket'] }}</span>
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-blue-500/15 text-blue-500 dark:text-blue-400">
                                        {{ ucfirst($selectedServisDetail['status']) }}
                                    </span>
                                </div>
                                <p class="text-xs text-ink-700 dark:text-ink-200 mt-1 font-medium">
                                    {{ $selectedServisDetail['pelanggan_nama'] }} · {{ $selectedServisDetail['pelanggan_telepon'] }}
                                </p>
                                <p class="text-[11px] text-ink-500 dark:text-ink-400">
                                    Unit: <span class="text-ink-900 dark:text-white font-medium">{{ $selectedServisDetail['jenis_hp'] }}</span>
                                </p>
                            </div>
                            <button
                                type="button"
                                wire:click="$set('selectedServisDetail', null)"
                                class="px-3 py-1.5 rounded-xl bg-ink-100 hover:bg-ink-200 dark:bg-white/5 dark:hover:bg-white/10 text-ink-700 dark:text-ink-300 text-[11px] font-bold cursor-pointer">
                                Ganti Tiket
                            </button>
                        </div>

                        <!-- Rincian Biaya Servis -->
                        <div>
                            <h4 class="text-xs font-bold text-ink-700 dark:text-ink-300 uppercase tracking-wider mb-2">Rincian Pekerjaan & Suku Cadang:</h4>
                            <div class="rounded-2xl border border-ink-200/80 dark:border-white/5 overflow-hidden">
                                <table class="w-full text-left text-xs">
                                    <thead>
                                        <tr class="border-b border-ink-200/80 dark:border-white/10 text-ink-500 text-[10px] bg-ink-50 dark:bg-white/[0.02] uppercase font-bold">
                                            <th class="py-2.5 px-3.5">Item</th>
                                            <th class="py-2.5 px-3.5 text-center">Qty</th>
                                            <th class="py-2.5 px-3.5 text-right">Harga</th>
                                            <th class="py-2.5 px-3.5 text-right">Subtotal</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-ink-200/60 dark:divide-white/5">
                                        @foreach($selectedServisDetail['items'] as $it)
                                            <tr>
                                                <td class="py-2.5 px-3.5 text-ink-900 dark:text-white font-medium">{{ $it['nama'] }}</td>
                                                <td class="py-2.5 px-3.5 text-center text-ink-700 dark:text-white tabular-nums">{{ $it['qty'] }}</td>
                                                <td class="py-2.5 px-3.5 text-right text-ink-600 dark:text-ink-300 tabular-nums">Rp {{ number_format($it['harga'], 0, ',', '.') }}</td>
                                                <td class="py-2.5 px-3.5 text-right text-ink-900 dark:text-white font-bold tabular-nums">Rp {{ number_format($it['subtotal'], 0, ',', '.') }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Total Tagihan Banner -->
                        <div class="p-4 rounded-2xl bg-up-mint/10 border border-up-mint/25 flex items-center justify-between">
                            <span class="text-xs font-bold text-ink-700 dark:text-ink-200 uppercase tracking-wider">Total Tagihan Servis</span>
                            <span class="text-2xl font-black text-up-mint tabular-nums">
                                Rp {{ number_format($selectedServisDetail['total'], 0, ',', '.') }}
                            </span>
                        </div>

                        <!-- Metode Pembayaran -->
                        <div>
                            <label class="block text-xs font-bold text-ink-700 dark:text-ink-300 uppercase tracking-wider mb-2">Metode Pembayaran</label>
                            <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
                                @foreach(['tunai' => 'Tunai (Kas Laci)', 'transfer' => 'Transfer Bank', 'qris' => 'QRIS', 'split' => 'Split'] as $m => $lbl)
                                    <button
                                        type="button"
                                        wire:click="$set('servisMetodeBayar', '{{ $m }}')"
                                        class="py-2.5 px-2 rounded-xl border text-center text-xs font-bold transition cursor-pointer min-h-[42px] {{ $servisMetodeBayar === $m ? 'bg-up-primary text-white border-up-primary shadow-sm shadow-up-primary/30' : 'bg-ink-100/80 dark:bg-white/[0.02] border-ink-200/80 dark:border-white/5 text-ink-700 dark:text-ink-300 hover:bg-ink-200 dark:hover:bg-white/5' }}">
                                        {{ $lbl }}
                                    </button>
                                @endforeach
                            </div>
                        </div>

                        <!-- Input Pembayaran -->
                        @if($servisMetodeBayar === 'tunai')
                            <div class="space-y-2">
                                <div class="flex items-center justify-between text-xs font-semibold text-ink-700 dark:text-ink-300">
                                    <span>Jumlah Bayar (Tunai)</span>
                                    <div class="flex gap-1.5">
                                        <button type="button" wire:click="$set('servisJumlahBayar', {{ $selectedServisDetail['total'] }})" class="px-2 py-0.5 rounded-md bg-ink-100 hover:bg-ink-200 dark:bg-white/5 dark:hover:bg-white/10 text-[10px] font-bold text-ink-600 dark:text-ink-300 cursor-pointer">Uang Pas</button>
                                        <button type="button" wire:click="$set('servisJumlahBayar', 50000)" class="px-2 py-0.5 rounded-md bg-ink-100 hover:bg-ink-200 dark:bg-white/5 dark:hover:bg-white/10 text-[10px] font-bold text-ink-600 dark:text-ink-300 cursor-pointer">50k</button>
                                        <button type="button" wire:click="$set('servisJumlahBayar', 100000)" class="px-2 py-0.5 rounded-md bg-ink-100 hover:bg-ink-200 dark:bg-white/5 dark:hover:bg-white/10 text-[10px] font-bold text-ink-600 dark:text-ink-300 cursor-pointer">100k</button>
                                        <button type="button" wire:click="$set('servisJumlahBayar', 200000)" class="px-2 py-0.5 rounded-md bg-ink-100 hover:bg-ink-200 dark:bg-white/5 dark:hover:bg-white/10 text-[10px] font-bold text-ink-600 dark:text-ink-300 cursor-pointer">200k</button>
                                    </div>
                                </div>
                                <input
                                    type="text"
                                    wire:model.live="servisJumlahBayar"
                                    class="w-full px-4 py-3 rounded-2xl glass-input text-lg font-black text-ink-900 dark:text-white tabular-nums border-ink-200/80 dark:border-white/10 min-h-[48px]"
                                    placeholder="0"
                                />
                                @if($servisKembalian > 0)
                                    <div class="p-3 rounded-xl bg-ink-50 dark:bg-white/[0.03] border border-ink-200/80 dark:border-white/5 flex items-center justify-between text-xs">
                                        <span class="text-ink-500 font-medium">Kembalian:</span>
                                        <span class="font-black text-base text-up-mint tabular-nums">Rp {{ number_format($servisKembalian, 0, ',', '.') }}</span>
                                    </div>
                                @endif
                            </div>
                        @elseif($servisMetodeBayar === 'split')
                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-xs font-bold text-ink-700 dark:text-ink-300 mb-1">Porsi Tunai (Kas Laci)</label>
                                    <input
                                        type="text"
                                        wire:model.live="servisSplitTunai"
                                        class="w-full px-3.5 py-2.5 rounded-xl glass-input text-xs font-bold text-ink-900 dark:text-white tabular-nums border-ink-200/80 dark:border-white/10 min-h-[42px]"
                                    />
                                </div>
                                <div>
                                    <label class="block text-xs font-bold text-ink-700 dark:text-ink-300 mb-1">Porsi Non-Tunai</label>
                                    <input
                                        type="text"
                                        wire:model.live="servisSplitNonTunai"
                                        class="w-full px-3.5 py-2.5 rounded-xl glass-input text-xs font-bold text-ink-900 dark:text-white tabular-nums border-ink-200/80 dark:border-white/10 min-h-[42px]"
                                    />
                                </div>
                            </div>
                        @endif

                        <!-- Checkbox Serahkan Unit -->
                        @if($selectedServisDetail['status'] === 'selesai')
                            <label class="flex items-center gap-2.5 p-3 rounded-xl bg-ink-50 dark:bg-white/[0.02] border border-ink-200/80 dark:border-white/5 cursor-pointer">
                                <input
                                    type="checkbox"
                                    wire:model="servisUbahStatusDiambil"
                                    class="w-4 h-4 rounded text-up-primary focus:ring-0 bg-transparent border-ink-300 dark:border-white/20"
                                />
                                <span class="text-xs text-ink-800 dark:text-white font-medium">
                                    Serahkan unit ke pelanggan sekarang (status tiket berubah ke <strong class="text-up-mint">Diambil</strong>)
                                </span>
                            </label>
                        @endif

                        <!-- Catatan -->
                        <div>
                            <label class="block text-xs font-bold text-ink-700 dark:text-ink-300 mb-1">Catatan Tambahan (opsional)</label>
                            <input
                                type="text"
                                wire:model="servisCatatan"
                                placeholder="Contoh: No ref transfer / garansi khusus..."
                                class="w-full px-3.5 py-2.5 rounded-xl glass-input text-xs text-ink-900 dark:text-white border-ink-200/80 dark:border-white/10 min-h-[42px]"
                            />
                        </div>
                    </div>
                @endif
            </div>

            <div class="flex gap-2.5 pt-3.5 border-t border-ink-200/70 dark:border-white/10 mt-4">
                <button
                    type="button"
                    wire:click="tutupBayarServisModal"
                    class="flex-1 py-2.5 rounded-xl bg-ink-100 hover:bg-ink-200 dark:bg-white/5 dark:hover:bg-white/10 text-ink-700 dark:text-ink-300 font-bold text-xs cursor-pointer min-h-[42px] transition-colors"
                >
                    Tutup
                </button>
                @if($selectedServisDetail)
                    <button
                        type="button"
                        wire:click="prosesBayarServis"
                        class="flex-1 py-2.5 rounded-xl bg-gradient-to-r from-up-primary to-indigo-600 hover:opacity-95 text-white font-black text-xs cursor-pointer min-h-[42px] shadow-lg shadow-up-primary/25 transition-transform active:scale-[0.98]"
                    >
                        Proses Bayar & Struk POS
                    </button>
                @endif
            </div>
        </div>
    </div>
@endif
