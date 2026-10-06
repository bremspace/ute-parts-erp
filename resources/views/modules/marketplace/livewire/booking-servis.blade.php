<div class="max-w-3xl mx-auto px-4 sm:px-6 py-6 sm:py-10 space-y-8">
    <!-- Header -->
    <div class="text-center space-y-3">
        <div class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full bg-up-primary/10 text-up-primary text-[11px] font-bold uppercase tracking-wider">
            <span class="w-1.5 h-1.5 rounded-full bg-up-primary animate-pulse"></span>
            <span>Ute Parts Service Lab</span>
        </div>
        <h1 class="text-2xl sm:text-4xl font-black tracking-tight text-ink-900 dark:text-white leading-tight">
            Booking Servis Gadget Presisi
        </h1>
        <p class="text-xs sm:text-sm text-ink-500 dark:text-ink-400 max-w-lg mx-auto leading-relaxed">
            Diagnostik cepat oleh teknisi tersertifikasi dengan suku cadang bergaransi resmi. Tidak perlu antre, konfirmasi instan via sistem.
        </p>
    </div>

    <!-- Stepped Service Form Card -->
    <div class="bg-white dark:bg-ink-900 rounded-3xl border border-ink-100/90 dark:border-white/[0.08] shadow-[0_8px_32px_rgba(0,0,0,0.03)] dark:shadow-[0_8px_32px_rgba(0,0,0,0.4)] p-4 sm:p-8">
        <form wire:submit.prevent="simpanBooking" class="space-y-6 sm:space-y-8">
            <!-- Step 1: Lokasi Cabang Servis -->
            <div class="space-y-4">
                <div class="flex items-center gap-3">
                    <span class="w-6 h-6 rounded-full bg-ink-900 text-white dark:bg-white dark:text-ink-950 text-xs font-bold flex items-center justify-center flex-shrink-0">1</span>
                    <h3 class="text-sm font-bold uppercase tracking-wider text-ink-900 dark:text-white">Pilih Cabang Servis Terdekat</h3>
                </div>

                <div class="pl-0 sm:pl-9 space-y-1.5">
                    <label for="cabang_id" class="block text-xs font-semibold text-ink-700 dark:text-ink-300">
                        Pusat Layanan Ute Parts <span class="text-up-red">*</span>
                    </label>
                    <div class="relative w-full min-w-0">
                        <select id="cabang_id" wire:model="cabang_id"
                                class="w-full min-w-0 max-w-full truncate appearance-none px-4 py-3 pr-8 rounded-2xl bg-ink-50 dark:bg-ink-950 border border-ink-200/80 dark:border-white/10 text-base sm:text-sm font-medium text-ink-900 dark:text-white outline-none focus:border-up-primary focus:ring-2 focus:ring-up-primary/10 transition-all min-h-[44px] cursor-pointer">
                            <option value="">-- Pilih Cabang --</option>
                            @foreach($cabangs as $cabang)
                                <option value="{{ $cabang->id }}">{{ $cabang->nama }} ({{ $cabang->kota ?? $cabang->alamat ?? 'Utama' }})</option>
                            @endforeach
                        </select>
                        <div class="absolute inset-y-0 right-0 pr-3.5 flex items-center pointer-events-none text-ink-400">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                            </svg>
                        </div>
                    </div>
                    @error('cabang_id') <p class="text-xs text-up-red font-medium mt-1">{{ $message }}</p> @enderror
                </div>
            </div>

            <!-- Step 2: Detail Unit Gadget -->
            <div class="space-y-4 pt-4 border-t border-ink-100/80 dark:border-white/10">
                <div class="flex items-center gap-3">
                    <span class="w-6 h-6 rounded-full bg-ink-900 text-white dark:bg-white dark:text-ink-950 text-xs font-bold flex items-center justify-center flex-shrink-0">2</span>
                    <h3 class="text-sm font-bold uppercase tracking-wider text-ink-900 dark:text-white">Identifikasi Gadget</h3>
                </div>

                <div class="pl-0 sm:pl-9 grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div class="space-y-1.5 min-w-0">
                        <label for="jenis_hp" class="block text-xs font-semibold text-ink-700 dark:text-ink-300">
                            Merek & Model Gadget <span class="text-up-red">*</span>
                        </label>
                        <input type="text" id="jenis_hp" wire:model="jenis_hp"
                               placeholder="Contoh: iPhone 13 Pro / Samsung S22"
                               class="w-full min-w-0 px-4 py-3 rounded-2xl bg-ink-50 dark:bg-ink-950 border border-ink-200/80 dark:border-white/10 text-base sm:text-sm text-ink-900 dark:text-white placeholder-ink-400 outline-none focus:border-up-primary focus:ring-2 focus:ring-up-primary/10 transition-all min-h-[44px]" />
                        @error('jenis_hp') <p class="text-xs text-up-red font-medium mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div class="space-y-1.5 min-w-0">
                        <label for="seri_hp" class="block text-xs font-semibold text-ink-700 dark:text-ink-300">
                            Warna / Seri / Kapasitas (Opsional)
                        </label>
                        <input type="text" id="seri_hp" wire:model="seri_hp"
                               placeholder="Contoh: Sierra Blue 128GB"
                               class="w-full min-w-0 px-4 py-3 rounded-2xl bg-ink-50 dark:bg-ink-950 border border-ink-200/80 dark:border-white/10 text-base sm:text-sm text-ink-900 dark:text-white placeholder-ink-400 outline-none focus:border-up-primary focus:ring-2 focus:ring-up-primary/10 transition-all min-h-[44px]" />
                        @error('seri_hp') <p class="text-xs text-up-red font-medium mt-1">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div class="pl-0 sm:pl-9 space-y-1.5 pt-1">
                    <label for="keluhan" class="block text-xs font-semibold text-ink-700 dark:text-ink-300">
                        Deskripsi Kendala / Kerusakan <span class="text-up-red">*</span>
                    </label>
                    <textarea id="keluhan" wire:model="keluhan" rows="3"
                              placeholder="Ceritakan kendala: misal layar pecah & blank hitam, baterai cepat panas, port charging longgar..."
                              class="w-full min-w-0 px-4 py-3 rounded-2xl bg-ink-50 dark:bg-ink-950 border border-ink-200/80 dark:border-white/10 text-base sm:text-sm text-ink-900 dark:text-white placeholder-ink-400 outline-none focus:border-up-primary focus:ring-2 focus:ring-up-primary/10 transition-all"></textarea>
                    @error('keluhan') <p class="text-xs text-up-red font-medium mt-1">{{ $message }}</p> @enderror
                </div>
            </div>

            <!-- Step 3: Informasi Pemilik / Kontak -->
            <div class="space-y-4 pt-4 border-t border-ink-100/80 dark:border-white/10">
                <div class="flex items-center gap-3">
                    <span class="w-6 h-6 rounded-full bg-ink-900 text-white dark:bg-white dark:text-ink-950 text-xs font-bold flex items-center justify-center flex-shrink-0">3</span>
                    <h3 class="text-sm font-bold uppercase tracking-wider text-ink-900 dark:text-white">Data Pemilik & Kontak Notifikasi</h3>
                </div>

                <div class="pl-0 sm:pl-9 grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div class="space-y-1.5 min-w-0">
                        <label for="nama" class="block text-xs font-semibold text-ink-700 dark:text-ink-300">
                            Nama Pemilik <span class="text-up-red">*</span>
                        </label>
                        <input type="text" id="nama" wire:model="nama"
                               placeholder="Nama lengkap Anda"
                               class="w-full min-w-0 px-4 py-3 rounded-2xl bg-ink-50 dark:bg-ink-950 border border-ink-200/80 dark:border-white/10 text-base sm:text-sm text-ink-900 dark:text-white placeholder-ink-400 outline-none focus:border-up-primary focus:ring-2 focus:ring-up-primary/10 transition-all min-h-[44px]" />
                        @error('nama') <p class="text-xs text-up-red font-medium mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div class="space-y-1.5 min-w-0">
                        <label for="telepon" class="block text-xs font-semibold text-ink-700 dark:text-ink-300">
                            WhatsApp / Nomor Telepon Aktif <span class="text-up-red">*</span>
                        </label>
                        <input type="text" id="telepon" wire:model="telepon"
                               placeholder="Contoh: 081234567890"
                               class="w-full min-w-0 px-4 py-3 rounded-2xl bg-ink-50 dark:bg-ink-950 border border-ink-200/80 dark:border-white/10 text-base sm:text-sm text-ink-900 dark:text-white placeholder-ink-400 outline-none focus:border-up-primary focus:ring-2 focus:ring-up-primary/10 transition-all min-h-[44px]" />
                        @error('telepon') <p class="text-xs text-up-red font-medium mt-1">{{ $message }}</p> @enderror
                    </div>
                </div>
            </div>

            <!-- Submit Button & Guarantee Note -->
            <div class="pl-0 sm:pl-9 pt-4 space-y-4">
                <button
                    type="submit"
                    class="w-full py-4 rounded-2xl bg-up-primary hover:bg-up-primary-dark text-white font-bold text-sm tracking-tight shadow-[0_8px_24px_rgba(91,79,233,0.3)] transition-all duration-200 cursor-pointer active:scale-[0.98] min-h-[48px] flex items-center justify-center gap-2">
                    <span>Ajukan Tiket Servis Sekarang</span>
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3" />
                    </svg>
                </button>

                <div class="flex items-center justify-center gap-2 text-[11px] text-ink-500 dark:text-ink-400">
                    <svg class="w-3.5 h-3.5 text-up-mint" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                    </svg>
                    <span>Tidak ada biaya jika setelah dicek unit dibatalkan servis</span>
                </div>
            </div>
        </form>
    </div>
</div>
