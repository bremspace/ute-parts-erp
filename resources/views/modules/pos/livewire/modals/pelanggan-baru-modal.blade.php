<!-- MODAL: TAMBAH PELANGGAN BARU -->
@if($showPelangganBaruModal)
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-ink-950/80 backdrop-blur-md p-4 sm:p-6 overflow-y-auto">
        <div class="w-full max-w-sm bg-white dark:bg-[#0B1120] border border-ink-200/80 dark:border-white/10 p-5 sm:p-6 rounded-3xl relative my-auto shadow-2xl">
            <div class="flex items-center justify-between pb-3.5 mb-4 border-b border-ink-200/70 dark:border-white/10">
                <div class="flex items-center gap-2.5">
                    <div class="w-9 h-9 rounded-xl bg-up-primary/10 border border-up-primary/20 flex items-center justify-center text-up-primary">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z" />
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-base font-bold text-ink-900 dark:text-white">Pelanggan Baru</h3>
                        <p class="text-[11px] text-ink-500 dark:text-ink-400">Sinkronisasi otomatis ke CRM, POS & Servis</p>
                    </div>
                </div>
                <button
                    type="button"
                    wire:click="$set('showPelangganBaruModal', false)"
                    class="p-2 rounded-xl text-ink-400 hover:text-ink-900 dark:hover:text-white hover:bg-ink-100 dark:hover:bg-white/5 transition-colors cursor-pointer"
                >✕</button>
            </div>

            <div class="space-y-3.5 text-xs">
                <div>
                    <label class="block font-bold text-ink-700 dark:text-ink-300 uppercase tracking-wider text-[11px] mb-1.5">Nama Pelanggan *</label>
                    <input
                        type="text"
                        wire:model="pelangganBaruForm.nama"
                        placeholder="Contoh: Budi Pratama"
                        class="w-full px-3.5 py-2.5 rounded-xl glass-input text-xs font-semibold text-ink-900 dark:text-white min-h-[42px]"
                        autofocus
                    />
                </div>
                <div>
                    <label class="block font-bold text-ink-700 dark:text-ink-300 uppercase tracking-wider text-[11px] mb-1.5">No. WhatsApp / HP *</label>
                    <input
                        type="text"
                        wire:model="pelangganBaruForm.telepon"
                        class="w-full px-3.5 py-2.5 rounded-xl glass-input text-xs font-semibold text-ink-900 dark:text-white min-h-[42px]"
                        placeholder="08xx-xxxx-xxxx"
                    />
                </div>
                <div>
                    <label class="block font-bold text-ink-700 dark:text-ink-300 uppercase tracking-wider text-[11px] mb-1.5">Email (opsional)</label>
                    <input
                        type="email"
                        wire:model="pelangganBaruForm.email"
                        placeholder="nama@email.com"
                        class="w-full px-3.5 py-2.5 rounded-xl glass-input text-xs font-medium text-ink-900 dark:text-white min-h-[42px]"
                    />
                </div>
                <div>
                    <label class="block font-bold text-ink-700 dark:text-ink-300 uppercase tracking-wider text-[11px] mb-1.5">Alamat</label>
                    <textarea
                        wire:model="pelangganBaruForm.alamat"
                        rows="2"
                        placeholder="Alamat domisili atau toko..."
                        class="w-full px-3.5 py-2.5 rounded-xl glass-input text-xs font-medium text-ink-900 dark:text-white"
                    ></textarea>
                </div>
                <div>
                    <label class="block font-bold text-ink-700 dark:text-ink-300 uppercase tracking-wider text-[11px] mb-1.5">Tanggal Lahir (opsional)</label>
                    <input
                        type="date"
                        wire:model="pelangganBaruForm.tanggal_lahir"
                        class="w-full px-3.5 py-2.5 rounded-xl glass-input text-xs font-medium text-ink-900 dark:text-white min-h-[42px]"
                    />
                </div>
            </div>

            <div class="flex gap-2.5 pt-4 border-t border-ink-200/70 dark:border-white/10 mt-5">
                <button
                    type="button"
                    wire:click="$set('showPelangganBaruModal', false)"
                    class="flex-1 py-3 rounded-xl bg-ink-100 hover:bg-ink-200 dark:bg-white/5 dark:hover:bg-white/10 text-ink-700 dark:text-ink-300 font-bold text-xs cursor-pointer min-h-[44px] transition-colors"
                >
                    Batal
                </button>
                <button
                    type="button"
                    wire:click="simpanPelangganBaru"
                    class="flex-1 py-3 rounded-xl bg-gradient-to-r from-up-primary to-indigo-600 text-white font-black text-xs cursor-pointer min-h-[44px] shadow-lg shadow-up-primary/25 transition-transform active:scale-[0.98]"
                >
                    Simpan Pelanggan
                </button>
            </div>
        </div>
    </div>
@endif
