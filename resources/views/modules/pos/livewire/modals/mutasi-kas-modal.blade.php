{{-- [KAS-LACI-MUTASI] MODAL MUTASI KAS LACI (IN/OUT NON-POS) --}}
@if($showMutasiKasModal)
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-ink-950/80 backdrop-blur-md p-4 sm:p-6 overflow-y-auto"
         x-data="{}"
         x-init="$nextTick(() => { $refs.mutasiNominalInput?.focus(); })">
        <div class="w-full max-w-md bg-white dark:bg-[#0B1120] border border-ink-200/80 dark:border-white/10 p-5 sm:p-6 rounded-3xl shadow-2xl relative max-h-[90vh] flex flex-col my-auto">
            <div class="flex items-center justify-between pb-3.5 mb-4 border-b border-ink-200/70 dark:border-white/10">
                <div class="flex items-center gap-2.5">
                    <span class="w-3 h-3 rounded-full {{ $mutasiJenis === 'masuk' ? 'bg-up-mint ring-4 ring-up-mint/20' : 'bg-up-accent ring-4 ring-up-accent/20' }}"></span>
                    <div>
                        <h3 class="text-base font-bold text-ink-900 dark:text-white">Mutasi Kas Laci</h3>
                        <p class="text-[11px] text-ink-500 dark:text-ink-400">Pemasukan / Pengeluaran uang fisik laci non-penjualan</p>
                    </div>
                </div>
                <button
                    type="button"
                    wire:click="$set('showMutasiKasModal', false)"
                    class="p-2 rounded-xl text-ink-400 hover:text-ink-900 dark:hover:text-white hover:bg-ink-100 dark:hover:bg-white/5 transition-colors cursor-pointer"
                >✕</button>
            </div>

            {{-- Tab Switcher: Masuk vs Keluar --}}
            <div class="grid grid-cols-2 gap-1.5 p-1 bg-ink-100 dark:bg-white/5 rounded-2xl mb-4 border border-ink-200/80 dark:border-white/5">
                <button
                    type="button"
                    wire:click="$set('mutasiJenis', 'keluar')"
                    class="py-2.5 text-xs font-bold rounded-xl transition-all cursor-pointer min-h-[40px] flex items-center justify-center gap-1.5 {{ $mutasiJenis === 'keluar' ? 'bg-up-accent text-white shadow-md' : 'text-ink-600 dark:text-ink-400 hover:text-ink-900 dark:hover:text-white' }}">
                    <span>Kas Keluar</span>
                </button>
                <button
                    type="button"
                    wire:click="$set('mutasiJenis', 'masuk')"
                    class="py-2.5 text-xs font-bold rounded-xl transition-all cursor-pointer min-h-[40px] flex items-center justify-center gap-1.5 {{ $mutasiJenis === 'masuk' ? 'bg-up-mint text-ink-950 shadow-md' : 'text-ink-600 dark:text-ink-400 hover:text-ink-900 dark:hover:text-white' }}">
                    <span>Kas Masuk</span>
                </button>
            </div>

            <div class="space-y-3.5 overflow-y-auto pr-1">
                {{-- Pilihan Kategori / Akun Lawan --}}
                <div>
                    <label class="block text-xs font-bold text-ink-700 dark:text-ink-300 uppercase tracking-wider mb-1.5">
                        Kategori Transaksi (Akun COA Lawan) *
                    </label>
                    <select
                        wire:model.live="mutasiAkunLawan"
                        class="w-full px-3.5 py-2.5 rounded-xl glass-input text-xs font-semibold text-ink-900 dark:text-white bg-white/50 dark:bg-ink-900/80 border border-ink-200/80 dark:border-white/10 focus:border-up-primary cursor-pointer min-h-[42px]">
                        @foreach($mutasiKategoriList as $kat)
                            <option value="{{ $kat['kode'] }}" class="bg-white dark:bg-ink-900 text-ink-900 dark:text-white">
                                [{{ $kat['kode'] }}] {{ $kat['nama'] }}
                            </option>
                        @endforeach
                    </select>
                </div>

                {{-- Nominal --}}
                <div>
                    <label class="block text-xs font-bold text-ink-700 dark:text-ink-300 uppercase tracking-wider mb-1.5">Nominal (Rp) *</label>
                    <input
                        type="text"
                        inputmode="numeric"
                        x-format-number
                        wire:model="mutasiNominalRaw"
                        min="0"
                        x-ref="mutasiNominalInput"
                        class="w-full px-4 py-3 rounded-2xl glass-input text-xl font-black tabular-nums text-ink-900 dark:text-white border-ink-200/80 dark:border-white/15 min-h-[48px] {{ $errors->has('mutasiNominalRaw') ? 'border-up-red/60' : '' }}"
                        placeholder="0"
                    />
                    @error('mutasiNominalRaw')
                        <p class="mt-1.5 text-[11px] font-semibold text-up-red">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Keterangan / Keperluan --}}
                <div>
                    <label class="block text-xs font-bold text-ink-700 dark:text-ink-300 uppercase tracking-wider mb-1.5">Keterangan / Keperluan *</label>
                    <input
                        type="text"
                        wire:model="mutasiKeterangan"
                        placeholder="Contoh: Beli lakban & kantong plastik / Uang kembalian"
                        class="w-full px-3.5 py-2.5 rounded-xl glass-input text-xs font-medium text-ink-900 dark:text-white min-h-[42px] {{ $errors->has('mutasiKeterangan') ? 'border-up-red/60' : '' }}"
                    />
                    @error('mutasiKeterangan')
                        <p class="mt-1.5 text-[11px] font-semibold text-up-red">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Info Jurnal Otomatis --}}
                <div class="p-3 rounded-2xl bg-ink-50 dark:bg-white/[0.03] border border-ink-200/80 dark:border-white/10 text-xs text-ink-600 dark:text-ink-400">
                    <span class="font-bold text-ink-700 dark:text-ink-300 uppercase text-[10px] tracking-wider block mb-0.5">Jurnal Akuntansi:</span>
                    @if($mutasiJenis === 'keluar')
                        <p class="font-mono text-[11px] text-amber-500 dark:text-amber-300">
                            D: [{{ $mutasiAkunLawan }}] · K: [110-04] Kas Laci
                        </p>
                    @else
                        <p class="font-mono text-[11px] text-up-mint">
                            D: [110-04] Kas Laci · K: [{{ $mutasiAkunLawan }}]
                        </p>
                    @endif
                </div>

                {{-- Riwayat Mutasi Sesi Ini --}}
                @if(!empty($riwayatMutasiSesi))
                    <div class="pt-2 border-t border-ink-200/60 dark:border-white/10">
                        <h4 class="text-[11px] font-bold text-ink-700 dark:text-ink-300 uppercase tracking-wider mb-2">Riwayat Shift Ini:</h4>
                        <div class="space-y-1.5 max-h-32 overflow-y-auto pr-1">
                            @foreach($riwayatMutasiSesi as $m)
                                <div class="flex items-center justify-between p-2.5 rounded-xl bg-ink-50 dark:bg-white/[0.02] border border-ink-200/60 dark:border-white/5 text-[11px]">
                                    <div class="flex items-center gap-2 min-w-0">
                                        <span class="px-2 py-0.5 rounded-md font-bold uppercase text-[9px] {{ $m['jenis'] === 'masuk' ? 'bg-up-mint/20 text-up-mint' : 'bg-up-accent/20 text-up-accent' }}">
                                            {{ $m['jenis'] }}
                                        </span>
                                        <span class="text-ink-800 dark:text-white truncate max-w-[160px]" title="{{ $m['keterangan'] }}">{{ $m['keterangan'] }}</span>
                                    </div>
                                    <div class="text-right flex-shrink-0">
                                        <span class="font-bold tabular-nums text-ink-900 dark:text-white">Rp {{ number_format($m['nominal'], 0, ',', '.') }}</span>
                                        <span class="block text-ink-400 font-mono text-[9px]">{{ $m['jam'] }}</span>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>

            <div class="flex gap-2.5 pt-3.5 border-t border-ink-200/70 dark:border-white/10 mt-4">
                <button
                    type="button"
                    wire:click="$set('showMutasiKasModal', false)"
                    class="flex-1 py-2.5 rounded-xl bg-ink-100 hover:bg-ink-200 dark:bg-white/5 dark:hover:bg-white/10 text-ink-700 dark:text-ink-300 font-bold text-xs cursor-pointer min-h-[42px] transition-colors"
                >
                    Batal
                </button>
                <button
                    type="button"
                    wire:click="simpanMutasiKas"
                    class="flex-1 py-2.5 rounded-xl {{ $mutasiJenis === 'masuk' ? 'bg-gradient-to-r from-up-mint to-teal-500 text-ink-950' : 'bg-gradient-to-r from-up-accent to-amber-600 text-white' }} font-black text-xs cursor-pointer min-h-[42px] transition-transform active:scale-[0.98] shadow-md"
                >
                    Simpan Mutasi
                </button>
            </div>
        </div>
    </div>
@endif
