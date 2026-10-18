<!-- MODAL: KAS SESI (Buka / Tutup Kas Shift) -->
@if($showKasModal)
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-ink-950/80 backdrop-blur-md p-4 sm:p-6 overflow-y-auto"
         x-data="{}"
         x-init="$nextTick(() => { const el = $refs.kasSaldoInput; if (el) el.focus(); el?.select(); })">
        <div class="w-full max-w-md bg-white dark:bg-[#0B1120] border border-ink-200/80 dark:border-white/10 p-5 sm:p-6 rounded-3xl shadow-2xl relative my-auto">
            <!-- Header -->
            <div class="flex items-center justify-between pb-3.5 mb-4 border-b border-ink-200/70 dark:border-white/10">
                <div class="flex items-center gap-2.5">
                    <span class="w-3 h-3 rounded-full {{ $kasModeBuka ? 'bg-up-mint ring-4 ring-up-mint/20' : 'bg-up-accent ring-4 ring-up-accent/20' }}"></span>
                    <div>
                        <h3 class="text-base font-bold text-ink-900 dark:text-white">
                            {{ $kasModeBuka ? 'Buka Kas (Shift Baru)' : 'Tutup Kas (Rekap Shift)' }}
                        </h3>
                        <p class="text-[11px] text-ink-500 dark:text-ink-400">
                            {{ $kasModeBuka ? 'Tentukan modal kas laci awal' : 'Pencocokan saldo fisik laci kasir' }}
                        </p>
                    </div>
                </div>
                <button
                    type="button"
                    wire:click="$set('showKasModal', false)"
                    class="p-2 rounded-xl text-ink-400 hover:text-ink-900 dark:hover:text-white hover:bg-ink-100 dark:hover:bg-white/5 transition-colors cursor-pointer"
                >✕</button>
            </div>

            @if(!$kasHasil)
                <div class="space-y-4">
                    @if($kasModeBuka)
                        {{-- Pemilihan Sumber Dana --}}
                        <div>
                            <label for="selectedAkunSumber" class="block text-xs font-bold text-ink-700 dark:text-ink-300 uppercase tracking-wider mb-1.5">
                                Sumber Dana Kas *
                            </label>
                            <select
                                id="selectedAkunSumber"
                                wire:model.live="selectedAkunSumber"
                                class="w-full px-3.5 py-2.5 rounded-xl glass-input text-xs font-semibold text-ink-900 dark:text-white bg-white/50 dark:bg-ink-900/80 border border-ink-200/80 dark:border-white/10 focus:border-up-primary cursor-pointer min-h-[42px]"
                            >
                                @foreach($akunSumberList as $akun)
                                    <option value="{{ $akun['kode'] }}" class="bg-white dark:bg-ink-900 text-ink-900 dark:text-white">
                                        {{ $akun['kode'] }} — {{ $akun['nama'] }} (Saldo: Rp {{ number_format($akun['saldo'], 0, ',', '.') }})
                                    </option>
                                @endforeach
                            </select>
                            @php
                                $selectedAkun = collect($akunSumberList)->firstWhere('kode', $selectedAkunSumber);
                            @endphp
                            @if($selectedAkun)
                                <p class="mt-1.5 text-[11px] text-ink-500">
                                    Saldo tersedia: <span class="tabular-nums font-bold {{ $selectedAkun['saldo'] > 0 ? 'text-up-mint' : 'text-up-red' }}">Rp {{ number_format($selectedAkun['saldo'], 0, ',', '.') }}</span>
                                </p>
                            @endif
                        </div>

                        <div>
                            <label for="kasSaldoAwal" class="block text-xs font-bold text-ink-700 dark:text-ink-300 uppercase tracking-wider mb-1.5">
                                Saldo Awal Laci (Rp) *
                            </label>
                            <input
                                id="kasSaldoAwal"
                                type="text"
                                inputmode="numeric"
                                x-format-number
                                wire:model.debounce.300ms="kasSaldoAwalRaw"
                                min="0"
                                x-ref="kasSaldoInput"
                                @keydown.enter.prevent="$wire.prosesKas()"
                                @blur="$wire.formatKasSaldoAwal()"
                                class="w-full px-4 py-3 rounded-2xl glass-input text-xl font-black tabular-nums text-ink-900 dark:text-white border-ink-200/80 dark:border-white/15 min-h-[48px] {{ $errors->has('kasSaldoAwalRaw') ? 'border-up-red/60' : '' }}"
                                placeholder="0"
                            />
                            @error('kasSaldoAwalRaw')
                                <p class="mt-1.5 text-[11px] font-semibold text-up-red">{{ $message }}</p>
                            @enderror
                        </div>
                    @else
                        {{-- BLIND COUNT: Kasir menginput saldo fisik tanpa melihat saldo sistem --}}
                        <div class="p-3.5 rounded-2xl bg-ink-50 dark:bg-white/[0.03] border border-ink-200/80 dark:border-white/10 text-xs">
                            <p class="text-[11px] text-ink-600 dark:text-ink-400 leading-relaxed">
                                Hitung seluruh uang tunai fisik di laci kasir dan masukkan nominalnya di bawah. Sistem akan mencocokkan dengan catatan transaksi penjualan & mutasi.
                            </p>
                        </div>
                        <div>
                            <label for="kasSaldoFisik" class="block text-xs font-bold text-ink-700 dark:text-ink-300 uppercase tracking-wider mb-1.5">
                                Saldo Fisik Akhir (Rp) *
                            </label>
                            <input
                                id="kasSaldoFisik"
                                type="text"
                                inputmode="numeric"
                                x-format-number
                                wire:model.debounce.300ms="kasSaldoFisikRaw"
                                min="0"
                                step="500"
                                x-ref="kasSaldoInput"
                                @keydown.enter.prevent="$wire.prosesKas()"
                                @blur="$wire.formatKasSaldoFisik()"
                                class="w-full px-4 py-3 rounded-2xl glass-input text-xl font-black tabular-nums text-ink-900 dark:text-white border-ink-200/80 dark:border-white/15 min-h-[48px] {{ $errors->has('kasSaldoFisikRaw') ? 'border-up-red/60' : '' }}"
                                placeholder="0"
                            />
                            @error('kasSaldoFisikRaw')
                                <p class="mt-1.5 text-[11px] font-semibold text-up-red">{{ $message }}</p>
                            @enderror
                        </div>
                        <p class="text-[10px] text-ink-500">Bila terjadi selisih, penutupan shift memerlukan persetujuan owner / manager toko.</p>
                    @endif
                </div>
            @else
                <div class="space-y-2.5 text-xs">
                    <div class="flex justify-between p-3 rounded-xl bg-ink-50 dark:bg-white/[0.03] border border-ink-200/70 dark:border-white/5">
                        <span class="text-ink-600 dark:text-ink-400 font-medium">{{ $kasModeBuka ? 'Saldo awal' : 'Saldo sistem' }}</span>
                        <span class="font-bold text-ink-900 dark:text-white tabular-nums">Rp {{ number_format($kasHasil['saldo_awal'] ?? $kasHasil['saldo_sistem'], 0, ',', '.') }}</span>
                    </div>
                    @if(!$kasModeBuka)
                        <div class="flex justify-between p-3 rounded-xl bg-ink-50 dark:bg-white/[0.03] border border-ink-200/70 dark:border-white/5">
                            <span class="text-ink-600 dark:text-ink-400 font-medium">Saldo fisik terhitung</span>
                            <span class="font-bold text-ink-900 dark:text-white tabular-nums">Rp {{ number_format($kasHasil['saldo_fisik'], 0, ',', '.') }}</span>
                        </div>
                        <div class="flex justify-between p-3 rounded-xl {{ $kasHasil['selisih'] == 0 ? 'bg-up-mint/15 text-up-mint border border-up-mint/30' : 'bg-up-amber/15 text-up-amber border border-up-amber/30' }}">
                            <span class="font-bold">Selisih Kas</span>
                            <span class="font-black text-sm tabular-nums">Rp {{ number_format($kasHasil['selisih'], 0, ',', '.') }}</span>
                        </div>
                    @endif
                </div>
            @endif

            <div class="flex gap-2.5 pt-4 border-t border-ink-200/70 dark:border-white/10 mt-5">
                <button
                    type="button"
                    wire:click="$set('showKasModal', false)"
                    class="flex-1 py-3 rounded-xl bg-ink-100 hover:bg-ink-200 dark:bg-white/5 dark:hover:bg-white/10 text-ink-700 dark:text-ink-300 font-bold text-xs cursor-pointer min-h-[44px] transition-colors"
                >
                    Tutup
                </button>
                @if(!$kasHasil)
                    <button
                        type="button"
                        wire:click="prosesKas"
                        class="flex-1 py-3 rounded-xl {{ $kasModeBuka ? 'bg-gradient-to-r from-up-mint to-teal-500 text-ink-950' : 'bg-gradient-to-r from-up-accent to-amber-600 text-white' }} font-black text-xs cursor-pointer min-h-[44px] transition-transform active:scale-[0.98] shadow-md"
                    >
                        {{ $kasModeBuka ? 'Buka Kas' : 'Tutup & Rekap' }}
                    </button>
                @endif
            </div>
        </div>
    </div>
@endif
