<div class="max-w-2xl mx-auto px-4 py-8">
    <div class="mb-6 text-center">
        <h1 class="text-2xl sm:text-3xl font-bold tracking-tight text-ink-900 dark:text-white">
            Booking Servis HP — Dari Rumah Tanpa Antre
        </h1>
        <p class="text-sm text-ink-500 dark:text-ink-400 mt-2">
            Isi formulir pengajuan di bawah ini. Tim teknisi Ute Parts siap memeriksa dan memperbaiki gadget Anda.
        </p>
    </div>

    <x-prism.glass-card circuit="true" padding="p-6 sm:p-8">
        <form wire:submit.prevent="simpanBooking" class="space-y-5">
            <!-- Pilihan Cabang -->
            <div>
                <label for="cabang_id" class="block text-xs font-semibold uppercase tracking-wider text-ink-500 dark:text-ink-300 mb-1.5">
                    Pilih Cabang Ute Parts Terdekat <span class="text-up-red">*</span>
                </label>
                <select id="cabang_id" wire:model="cabang_id" class="w-full px-3.5 py-2.5 rounded-xl border border-ink-200 dark:border-white/10 bg-white dark:bg-ink-900 text-ink-900 dark:text-ink-100 text-sm focus:outline-none focus:ring-2 focus:ring-up-primary">
                    <option value="">-- Pilih Cabang --</option>
                    @foreach($cabangs as $cabang)
                        <option value="{{ $cabang->id }}">{{ $cabang->nama }} ({{ $cabang->kota ?? $cabang->alamat ?? 'Utama' }})</option>
                    @endforeach
                </select>
                @error('cabang_id') <p class="text-xs text-up-red mt-1">{{ $message }}</p> @enderror
            </div>

            <!-- Nama & Telepon -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label for="nama" class="block text-xs font-semibold uppercase tracking-wider text-ink-500 dark:text-ink-300 mb-1.5">
                        Nama Lengkap <span class="text-up-red">*</span>
                    </label>
                    <input type="text" id="nama" wire:model="nama" placeholder="Contoh: Budi Santoso"
                           class="w-full px-3.5 py-2.5 rounded-xl border border-ink-200 dark:border-white/10 bg-white dark:bg-ink-900 text-ink-900 dark:text-ink-100 text-sm focus:outline-none focus:ring-2 focus:ring-up-primary" />
                    @error('nama') <p class="text-xs text-up-red mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="telepon" class="block text-xs font-semibold uppercase tracking-wider text-ink-500 dark:text-ink-300 mb-1.5">
                        Nomor WhatsApp / Telepon <span class="text-up-red">*</span>
                    </label>
                    <input type="text" id="telepon" wire:model="telepon" placeholder="Contoh: 081234567890"
                           class="w-full px-3.5 py-2.5 rounded-xl border border-ink-200 dark:border-white/10 bg-white dark:bg-ink-900 text-ink-900 dark:text-ink-100 text-sm focus:outline-none focus:ring-2 focus:ring-up-primary" />
                    @error('telepon') <p class="text-xs text-up-red mt-1">{{ $message }}</p> @enderror
                </div>
            </div>

            <!-- Jenis HP & Seri HP -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label for="jenis_hp" class="block text-xs font-semibold uppercase tracking-wider text-ink-500 dark:text-ink-300 mb-1.5">
                        Tipe / Merek HP <span class="text-up-red">*</span>
                    </label>
                    <input type="text" id="jenis_hp" wire:model="jenis_hp" placeholder="Contoh: iPhone 13, Samsung A52"
                           class="w-full px-3.5 py-2.5 rounded-xl border border-ink-200 dark:border-white/10 bg-white dark:bg-ink-900 text-ink-900 dark:text-ink-100 text-sm focus:outline-none focus:ring-2 focus:ring-up-primary" />
                    @error('jenis_hp') <p class="text-xs text-up-red mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="seri_hp" class="block text-xs font-semibold uppercase tracking-wider text-ink-500 dark:text-ink-300 mb-1.5">
                        Seri / Warna / Varian (Opsional)
                    </label>
                    <input type="text" id="seri_hp" wire:model="seri_hp" placeholder="Contoh: 128GB Midnight Blue"
                           class="w-full px-3.5 py-2.5 rounded-xl border border-ink-200 dark:border-white/10 bg-white dark:bg-ink-900 text-ink-900 dark:text-ink-100 text-sm focus:outline-none focus:ring-2 focus:ring-up-primary" />
                    @error('seri_hp') <p class="text-xs text-up-red mt-1">{{ $message }}</p> @enderror
                </div>
            </div>

            <!-- Keluhan Kerusakan -->
            <div>
                <label for="keluhan" class="block text-xs font-semibold uppercase tracking-wider text-ink-500 dark:text-ink-300 mb-1.5">
                    Keluhan / Kerusakan <span class="text-up-red">*</span>
                </label>
                <textarea id="keluhan" wire:model="keluhan" rows="4" placeholder="Jelaskan kendala HP Anda (contoh: layar bergaris setelah jatuh, baterai cepat habis, dsb.)"
                          class="w-full px-3.5 py-2.5 rounded-xl border border-ink-200 dark:border-white/10 bg-white dark:bg-ink-900 text-ink-900 dark:text-ink-100 text-sm focus:outline-none focus:ring-2 focus:ring-up-primary"></textarea>
                @error('keluhan') <p class="text-xs text-up-red mt-1">{{ $message }}</p> @enderror
            </div>

            <!-- Tombol Submit -->
            <div class="pt-2">
                <x-prism.prism-button type="submit" variant="primary" size="lg" class="w-full">
                    Kirim Pengajuan Servis
                </x-prism.prism-button>
            </div>
        </form>
    </x-prism.glass-card>
</div>
