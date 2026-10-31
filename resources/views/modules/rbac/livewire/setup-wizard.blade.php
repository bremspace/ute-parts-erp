<div class="max-w-5xl mx-auto px-3 py-4 sm:px-6 sm:py-6 space-y-4 sm:space-y-6">
    <!-- Header & Stepper -->
    <div class="glass-panel p-4 sm:p-6 rounded-2xl border border-ink-200/60 dark:border-white/10 relative overflow-hidden">
        <div class="circuit-line absolute top-0 left-0 w-full h-[1px]"></div>
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-3 sm:gap-4 mb-4 sm:mb-6">
            <div class="min-w-0">
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] sm:text-xs font-semibold bg-up-primary/10 text-up-primary border border-up-primary/20">
                    <span class="w-1.5 h-1.5 rounded-full bg-up-primary animate-pulse"></span>
                    Inisialisasi Sistem Baru
                </span>
                <h1 class="text-lg sm:text-2xl font-bold text-ink-900 dark:text-white mt-1.5 sm:mt-2 truncate sm:whitespace-normal">Setup Wizard Toko & Operasional</h1>
                <p class="text-xs sm:text-sm text-ink-500 dark:text-ink-400 mt-1 line-clamp-2 sm:line-clamp-none">Konfigurasi data awal pasca-instalasi atau factory reset agar aplikasi langsung siap digunakan bertransaksi.</p>
            </div>
            @if($isAlreadyCompleted)
                <div class="self-start md:self-auto px-3 py-1.5 sm:py-2 rounded-xl bg-up-mint/10 border border-up-mint/20 text-up-mint text-xs flex items-center gap-2 flex-shrink-0">
                    <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    <span>Setup awal sudah aktif</span>
                </div>
            @endif
        </div>

        <!-- Progress Steps: Horizontal Scrollable on Mobile with Snap, Grid on Desktop -->
        <div class="flex items-center gap-2 overflow-x-auto pb-2 sm:pb-0 sm:grid sm:grid-cols-6 scrollbar-none snap-x touch-pan-x -mx-1 px-1">
            @php
                $steps = [
                    1 => ['no' => '1', 'title' => 'Profil Toko'],
                    2 => ['no' => '2', 'title' => 'Modal Awal'],
                    3 => ['no' => '3', 'title' => 'Akun Staf'],
                    4 => ['no' => '4', 'title' => 'Migrasi Produk'],
                    5 => ['no' => '5', 'title' => 'Hardware POS'],
                    6 => ['no' => '6', 'title' => 'Aktivasi'],
                ];
            @endphp
            @foreach($steps as $idx => $s)
                <button type="button"
                    wire:click="goToStep({{ $idx }})"
                    class="flex-shrink-0 w-32 sm:w-auto p-2 sm:p-2.5 rounded-xl border text-left transition-all cursor-pointer snap-start {{ $currentStep === $idx ? 'bg-up-primary/15 border-up-primary text-up-primary ring-1 ring-up-primary/30' : ($currentStep > $idx ? 'bg-up-mint/10 border-up-mint/30 text-up-mint' : 'bg-ink-100/40 dark:bg-white/[0.02] border-ink-200/50 dark:border-white/5 text-ink-500') }}">
                    <div class="flex items-center gap-1.5 sm:gap-2">
                        <span class="w-5 h-5 rounded-full flex-shrink-0 flex items-center justify-center text-[10px] sm:text-xs font-bold {{ $currentStep === $idx ? 'bg-up-primary text-white' : ($currentStep > $idx ? 'bg-up-mint text-white' : 'bg-ink-200 dark:bg-white/10 text-ink-600 dark:text-ink-300') }}">
                            @if($currentStep > $idx) ✓ @else {{ $s['no'] }} @endif
                        </span>
                        <span class="text-[11px] sm:text-xs font-semibold truncate">{{ $s['title'] }}</span>
                    </div>
                </button>
            @endforeach
        </div>
    </div>

    @if($errorMessage)
        <div class="p-3.5 sm:p-4 rounded-xl bg-up-red/10 border border-up-red/30 text-up-red text-xs sm:text-sm flex items-start gap-2.5 sm:gap-3">
            <svg class="w-4 h-4 sm:w-5 sm:h-5 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            <div class="break-words">{{ $errorMessage }}</div>
        </div>
    @endif

    <!-- STEP 1: Profil Toko & Cabang -->
    @if($currentStep === 1)
        <div class="glass-panel p-4 sm:p-6 rounded-2xl border border-ink-200/60 dark:border-white/10 space-y-4 sm:space-y-5">
            <div class="border-b border-ink-200/60 dark:border-white/10 pb-3 sm:pb-4">
                <h2 class="text-base sm:text-lg font-bold text-ink-900 dark:text-white">Langkah 1: Identitas Toko & Cabang Pusat</h2>
                <p class="text-xs text-ink-500 dark:text-ink-400 mt-0.5">Sesuaikan nama brand toko fisik dan informasi kontak pada struk transaksi.</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-3 sm:gap-4">
                <div>
                    <label class="block text-xs font-medium text-ink-700 dark:text-ink-300 mb-1">Nama Toko Fisik <span class="text-up-red">*</span></label>
                    <input type="text" wire:model="namaToko" class="w-full px-3 sm:px-3.5 py-2 sm:py-2.5 rounded-xl border border-ink-300 dark:border-white/10 bg-white dark:bg-[#0B1120] text-xs sm:text-sm focus:border-up-primary focus:ring-1 focus:ring-up-primary text-ink-900 dark:text-white" placeholder="Contoh: Ute Parts Sentral">
                    @error('namaToko') <span class="text-xs text-up-red">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label class="block text-xs font-medium text-ink-700 dark:text-ink-300 mb-1">Nama Cabang Utama <span class="text-up-red">*</span></label>
                    <input type="text" wire:model="namaCabang" class="w-full px-3 sm:px-3.5 py-2 sm:py-2.5 rounded-xl border border-ink-300 dark:border-white/10 bg-white dark:bg-[#0B1120] text-xs sm:text-sm focus:border-up-primary focus:ring-1 focus:ring-up-primary text-ink-900 dark:text-white" placeholder="Contoh: Cabang Pusat">
                    @error('namaCabang') <span class="text-xs text-up-red">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label class="block text-xs font-medium text-ink-700 dark:text-ink-300 mb-1">No. WhatsApp / Telepon Toko</label>
                    <input type="text" wire:model="teleponToko" class="w-full px-3 sm:px-3.5 py-2 sm:py-2.5 rounded-xl border border-ink-300 dark:border-white/10 bg-white dark:bg-[#0B1120] text-xs sm:text-sm focus:border-up-primary focus:ring-1 focus:ring-up-primary text-ink-900 dark:text-white" placeholder="Contoh: 081234567890">
                    @error('teleponToko') <span class="text-xs text-up-red">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label class="block text-xs font-medium text-ink-700 dark:text-ink-300 mb-1">Header Struk Kasir <span class="text-up-red">*</span></label>
                    <input type="text" wire:model="strukHeader" class="w-full px-3 sm:px-3.5 py-2 sm:py-2.5 rounded-xl border border-ink-300 dark:border-white/10 bg-white dark:bg-[#0B1120] text-xs sm:text-sm focus:border-up-primary focus:ring-1 focus:ring-up-primary text-ink-900 dark:text-white" placeholder="Contoh: UTE PARTS">
                    @error('strukHeader') <span class="text-xs text-up-red">{{ $message }}</span> @enderror
                </div>

                <div class="md:col-span-2">
                    <label class="block text-xs font-medium text-ink-700 dark:text-ink-300 mb-1">Alamat Lengkap Toko</label>
                    <textarea wire:model="alamatToko" rows="2" class="w-full px-3 sm:px-3.5 py-2 sm:py-2.5 rounded-xl border border-ink-300 dark:border-white/10 bg-white dark:bg-[#0B1120] text-xs sm:text-sm focus:border-up-primary focus:ring-1 focus:ring-up-primary text-ink-900 dark:text-white" placeholder="Jl. Raya Utama No. 123..."></textarea>
                    @error('alamatToko') <span class="text-xs text-up-red">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label class="block text-xs font-medium text-ink-700 dark:text-ink-300 mb-1">Slogan / Subtitle Struk</label>
                    <input type="text" wire:model="strukSlogan" class="w-full px-3 sm:px-3.5 py-2 sm:py-2.5 rounded-xl border border-ink-300 dark:border-white/10 bg-white dark:bg-[#0B1120] text-xs sm:text-sm focus:border-up-primary focus:ring-1 focus:ring-up-primary text-ink-900 dark:text-white" placeholder="Pusat Sparepart & Servis HP">
                </div>

                <div>
                    <label class="block text-xs font-medium text-ink-700 dark:text-ink-300 mb-1">Catatan Kaki (Footer Struk)</label>
                    <input type="text" wire:model="strukFooter" class="w-full px-3 sm:px-3.5 py-2 sm:py-2.5 rounded-xl border border-ink-300 dark:border-white/10 bg-white dark:bg-[#0B1120] text-xs sm:text-sm focus:border-up-primary focus:ring-1 focus:ring-up-primary text-ink-900 dark:text-white" placeholder="Terima kasih atas kunjungan Anda!">
                </div>
            </div>
        </div>
    @endif

    <!-- STEP 2: Modal Kas & Bank Awal -->
    @if($currentStep === 2)
        <div class="glass-panel p-4 sm:p-6 rounded-2xl border border-ink-200/60 dark:border-white/10 space-y-4 sm:space-y-5">
            <div class="border-b border-ink-200/60 dark:border-white/10 pb-3 sm:pb-4">
                <h2 class="text-base sm:text-lg font-bold text-ink-900 dark:text-white">Langkah 2: Saldo Kas & Bank Pembukaan Toko</h2>
                <p class="text-xs text-ink-500 dark:text-ink-400 mt-0.5">Inisialisasi uang tunai brankas dan saldo rekening operasional awal. Jurnal akuntansi double-entry otomatis dibentuk.</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-3 sm:gap-4">
                <div>
                    <label class="block text-xs font-medium text-ink-700 dark:text-ink-300 mb-1">Kas Tunai Toko (Akun 110-01)</label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-xs font-bold text-ink-500">Rp</span>
                        <input type="number" wire:model.live="saldoKas" class="w-full pl-9 sm:pl-10 pr-3 sm:pr-3.5 py-2 sm:py-2.5 rounded-xl border border-ink-300 dark:border-white/10 bg-white dark:bg-[#0B1120] text-xs sm:text-sm font-semibold tabular-nums focus:border-up-primary focus:ring-1 focus:ring-up-primary text-ink-900 dark:text-white" min="0" step="1000">
                    </div>
                    <span class="text-[11px] text-ink-500 mt-1 block">Terbilang: Rp {{ number_format($kasNominal, 0, ',', '.') }}</span>
                </div>

                <div>
                    <label class="block text-xs font-medium text-ink-700 dark:text-ink-300 mb-1">Saldo Bank Operasional (Akun 110-02)</label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-xs font-bold text-ink-500">Rp</span>
                        <input type="number" wire:model.live="saldoBank" class="w-full pl-9 sm:pl-10 pr-3 sm:pr-3.5 py-2 sm:py-2.5 rounded-xl border border-ink-300 dark:border-white/10 bg-white dark:bg-[#0B1120] text-xs sm:text-sm font-semibold tabular-nums focus:border-up-primary focus:ring-1 focus:ring-up-primary text-ink-900 dark:text-white" min="0" step="1000">
                    </div>
                    <span class="text-[11px] text-ink-500 mt-1 block">Terbilang: Rp {{ number_format($bankNominal, 0, ',', '.') }}</span>
                </div>

                <div>
                    <label class="block text-xs font-medium text-ink-700 dark:text-ink-300 mb-1">Tanggal Cut-off Pembukuan <span class="text-up-red">*</span></label>
                    <input type="date" wire:model="tanggalModal" class="w-full px-3 sm:px-3.5 py-2 sm:py-2.5 rounded-xl border border-ink-300 dark:border-white/10 bg-white dark:bg-[#0B1120] text-xs sm:text-sm focus:border-up-primary focus:ring-1 focus:ring-up-primary text-ink-900 dark:text-white">
                </div>

                <div>
                    <label class="block text-xs font-medium text-ink-700 dark:text-ink-300 mb-1">Keterangan Jurnal</label>
                    <input type="text" wire:model="keteranganModal" class="w-full px-3 sm:px-3.5 py-2 sm:py-2.5 rounded-xl border border-ink-300 dark:border-white/10 bg-white dark:bg-[#0B1120] text-xs sm:text-sm focus:border-up-primary focus:ring-1 focus:ring-up-primary text-ink-900 dark:text-white">
                </div>
            </div>

            <!-- Jurnal Preview Card -->
            <div class="p-3 sm:p-4 rounded-xl bg-ink-100/60 dark:bg-white/[0.02] border border-ink-200/60 dark:border-white/5 space-y-2">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-1 text-xs font-bold text-ink-700 dark:text-ink-200">
                    <span>Pratinjau Jurnal Pembuka:</span>
                    <span class="text-up-primary font-semibold">Total Modal: Rp {{ number_format($totalModalNominal, 0, ',', '.') }}</span>
                </div>
                <div class="text-[11px] sm:text-xs text-ink-600 dark:text-ink-400 font-mono space-y-1 overflow-x-auto pb-1">
                    @if($kasNominal > 0)
                        <div>[Debit] 110-01 Kas &mdash; Rp {{ number_format($kasNominal, 0, ',', '.') }}</div>
                    @endif
                    @if($bankNominal > 0)
                        <div>[Debit] 110-02 Bank &mdash; Rp {{ number_format($bankNominal, 0, ',', '.') }}</div>
                    @endif
                    <div>[Kredit] 310-01 Modal Pemilik &mdash; Rp {{ number_format($totalModalNominal, 0, ',', '.') }}</div>
                </div>
                <label class="flex items-start sm:items-center gap-2 mt-3 cursor-pointer pt-2 border-t border-ink-200/40 dark:border-white/5">
                    <input type="checkbox" wire:model="buatJurnalModal" class="mt-0.5 sm:mt-0 rounded border-ink-300 text-up-primary focus:ring-up-primary flex-shrink-0">
                    <span class="text-[11px] sm:text-xs text-ink-700 dark:text-ink-300">Posting jurnal saldo awal kas & bank ini secara otomatis saat aktivasi</span>
                </label>
            </div>
        </div>
    @endif

    <!-- STEP 3: Akun & Keamanan Staf -->
    @if($currentStep === 3)
        <div class="glass-panel p-4 sm:p-6 rounded-2xl border border-ink-200/60 dark:border-white/10 space-y-4 sm:space-y-6">
            <div class="border-b border-ink-200/60 dark:border-white/10 pb-3 sm:pb-4">
                <h2 class="text-base sm:text-lg font-bold text-ink-900 dark:text-white">Langkah 3: Akun Staf & Ganti Sandi Bawaan</h2>
                <p class="text-xs text-ink-500 dark:text-ink-400 mt-0.5">Amankan sistem dengan mengganti password default (Password123!) dan sesuaikan nama staf operasional.</p>
            </div>

            <!-- Super Admin Card -->
            <div class="p-3.5 sm:p-4 rounded-xl border border-up-primary/20 bg-up-primary/[0.03] space-y-3">
                <div class="flex flex-wrap items-center gap-2">
                    <span class="w-2 h-2 rounded-full bg-up-primary flex-shrink-0"></span>
                    <h3 class="text-xs sm:text-sm font-bold text-ink-900 dark:text-white">Super Administrator / Owner</h3>
                    <span class="text-[10px] sm:text-[11px] text-ink-500 font-mono break-all">({{ $superAdminEmail }})</span>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-2.5 sm:gap-3">
                    <div>
                        <label class="block text-xs font-medium text-ink-700 dark:text-ink-300 mb-1">Nama Pemilik / Admin</label>
                        <input type="text" wire:model="superAdminNama" class="w-full px-3 py-2 rounded-lg border border-ink-300 dark:border-white/10 bg-white dark:bg-[#0B1120] text-xs sm:text-sm text-ink-900 dark:text-white">
                        @error('superAdminNama') <span class="text-xs text-up-red">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-ink-700 dark:text-ink-300 mb-1">Sandi Baru (Kosongkan jika tetap)</label>
                        <input type="password" wire:model="superAdminPassword" class="w-full px-3 py-2 rounded-lg border border-ink-300 dark:border-white/10 bg-white dark:bg-[#0B1120] text-xs sm:text-sm text-ink-900 dark:text-white" placeholder="Minimal 8 karakter">
                        @error('superAdminPassword') <span class="text-xs text-up-red">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-ink-700 dark:text-ink-300 mb-1">Konfirmasi Sandi Baru</label>
                        <input type="password" wire:model="superAdminPasswordConfirmation" class="w-full px-3 py-2 rounded-lg border border-ink-300 dark:border-white/10 bg-white dark:bg-[#0B1120] text-xs sm:text-sm text-ink-900 dark:text-white" placeholder="Ulangi sandi baru">
                    </div>
                </div>
            </div>

            <!-- Kasir Card -->
            <div class="p-3.5 sm:p-4 rounded-xl border border-ink-200/60 dark:border-white/10 bg-ink-100/40 dark:bg-white/[0.02] space-y-3">
                <div class="flex flex-wrap items-center gap-2">
                    <span class="w-2 h-2 rounded-full bg-up-mint flex-shrink-0"></span>
                    <h3 class="text-xs sm:text-sm font-bold text-ink-900 dark:text-white">Akun Kasir Utama</h3>
                    <span class="text-[10px] sm:text-[11px] text-ink-500 font-mono break-all">({{ $kasirEmail }})</span>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-2.5 sm:gap-3">
                    <div>
                        <label class="block text-xs font-medium text-ink-700 dark:text-ink-300 mb-1">Nama Staf Kasir</label>
                        <input type="text" wire:model="kasirNama" class="w-full px-3 py-2 rounded-lg border border-ink-300 dark:border-white/10 bg-white dark:bg-[#0B1120] text-xs sm:text-sm text-ink-900 dark:text-white">
                        @error('kasirNama') <span class="text-xs text-up-red">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-ink-700 dark:text-ink-300 mb-1">Sandi Kasir (Kosongkan jika tetap)</label>
                        <input type="password" wire:model="kasirPassword" class="w-full px-3 py-2 rounded-lg border border-ink-300 dark:border-white/10 bg-white dark:bg-[#0B1120] text-xs sm:text-sm text-ink-900 dark:text-white" placeholder="Sandi login kasir">
                        @error('kasirPassword') <span class="text-xs text-up-red">{{ $message }}</span> @enderror
                    </div>
                </div>
            </div>

            <!-- Teknisi Card -->
            <div class="p-3.5 sm:p-4 rounded-xl border border-ink-200/60 dark:border-white/10 bg-ink-100/40 dark:bg-white/[0.02] space-y-3">
                <div class="flex flex-wrap items-center gap-2">
                    <span class="w-2 h-2 rounded-full bg-up-accent flex-shrink-0"></span>
                    <h3 class="text-xs sm:text-sm font-bold text-ink-900 dark:text-white">Akun Teknisi Servis</h3>
                    <span class="text-[10px] sm:text-[11px] text-ink-500 font-mono break-all">({{ $teknisiEmail }})</span>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-2.5 sm:gap-3">
                    <div>
                        <label class="block text-xs font-medium text-ink-700 dark:text-ink-300 mb-1">Nama Teknisi</label>
                        <input type="text" wire:model="teknisiNama" class="w-full px-3 py-2 rounded-lg border border-ink-300 dark:border-white/10 bg-white dark:bg-[#0B1120] text-xs sm:text-sm text-ink-900 dark:text-white">
                        @error('teknisiNama') <span class="text-xs text-up-red">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-ink-700 dark:text-ink-300 mb-1">Sandi Teknisi (Kosongkan jika tetap)</label>
                        <input type="password" wire:model="teknisiPassword" class="w-full px-3 py-2 rounded-lg border border-ink-300 dark:border-white/10 bg-white dark:bg-[#0B1120] text-xs sm:text-sm text-ink-900 dark:text-white" placeholder="Sandi login teknisi">
                        @error('teknisiPassword') <span class="text-xs text-up-red">{{ $message }}</span> @enderror
                    </div>
                </div>
            </div>
        </div>
    @endif

    <!-- STEP 4: Migrasi Produk & Stok Awal (Opsional) -->
    @if($currentStep === 4)
        <div class="glass-panel p-4 sm:p-6 rounded-2xl border border-ink-200/60 dark:border-white/10 space-y-4 sm:space-y-5">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-ink-200/60 dark:border-white/10 pb-3 sm:pb-4">
                <div>
                    <h2 class="text-base sm:text-lg font-bold text-ink-900 dark:text-white">Langkah 4: Migrasi Data Produk & Stok Fisik (Opsional)</h2>
                    <p class="text-xs text-ink-500 dark:text-ink-400 mt-0.5">Pindahkan katalog SKU dan stok awal dari aplikasi lama. Jika toko baru, Anda dapat melewati langkah ini.</p>
                </div>
                <button type="button" wire:click="downloadTemplate" class="self-start sm:self-auto inline-flex items-center gap-1.5 px-3 py-2 rounded-xl text-xs font-semibold bg-ink-200 dark:bg-white/10 hover:bg-ink-300 text-ink-800 dark:text-ink-100 transition-colors cursor-pointer flex-shrink-0">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                    Unduh Template Excel
                </button>
            </div>

            <!-- Upload Area -->
            <div class="p-4 sm:p-6 border-2 border-dashed border-ink-300 dark:border-white/15 rounded-2xl text-center space-y-2.5 sm:space-y-3 bg-white/40 dark:bg-[#0B1120]/40">
                <svg class="w-8 h-8 sm:w-10 sm:h-10 mx-auto text-ink-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/></svg>
                <div class="text-xs sm:text-sm font-medium text-ink-800 dark:text-ink-200">
                    Pilih file migrasi produk (.xlsx, .xls, .csv)
                </div>
                <p class="text-[11px] sm:text-xs text-ink-500">Maksimal 25MB. Sistem otomatis membaca format Standar Ute Parts & SidRetail.</p>
                <div class="pt-1 sm:pt-2 overflow-hidden">
                    <input type="file" wire:model="fileMigrasi" accept=".xlsx,.xls,.csv" class="max-w-full text-xs text-ink-500 file:mr-2 sm:file:mr-4 file:py-1.5 sm:file:py-2 file:px-3 sm:file:px-4 file:rounded-xl file:border-0 file:text-[11px] sm:file:text-xs file:font-semibold file:bg-up-primary file:text-white hover:file:opacity-90 cursor-pointer">
                </div>
                <div wire:loading wire:target="fileMigrasi" class="text-xs text-up-primary font-semibold mt-2 animate-pulse">
                    Mengunggah dan menganalisis file...
                </div>
            </div>

            @if($sudahPreview)
                <div class="p-3.5 sm:p-4 rounded-xl bg-up-mint/10 border border-up-mint/30 space-y-2.5 sm:space-y-3">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-1">
                        <span class="text-xs font-bold text-up-mint flex items-center gap-1.5">
                            <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                            File Berhasil Dianalisis
                        </span>
                        <span class="text-xs font-mono font-bold text-ink-900 dark:text-white">Estimasi Persediaan: Rp {{ number_format($previewSummary['estimasi_nilai'], 0, ',', '.') }}</span>
                    </div>
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 text-center text-xs">
                        <div class="p-2 rounded-lg bg-white dark:bg-[#0B1120] border border-ink-200/50 dark:border-white/5">
                            <div class="text-[11px] text-ink-500">Total Baris</div>
                            <div class="font-bold text-ink-900 dark:text-white mt-0.5">{{ $previewSummary['total_baris'] }}</div>
                        </div>
                        <div class="p-2 rounded-lg bg-white dark:bg-[#0B1120] border border-ink-200/50 dark:border-white/5">
                            <div class="text-[11px] text-up-mint font-semibold">Baris Valid</div>
                            <div class="font-bold text-up-mint mt-0.5">{{ $previewSummary['total_valid'] }}</div>
                        </div>
                        <div class="p-2 rounded-lg bg-white dark:bg-[#0B1120] border border-ink-200/50 dark:border-white/5">
                            <div class="text-[11px] text-up-red font-semibold">Peringatan</div>
                            <div class="font-bold text-up-red mt-0.5">{{ $previewSummary['total_invalid'] }}</div>
                        </div>
                        <div class="p-2 rounded-lg bg-white dark:bg-[#0B1120] border border-ink-200/50 dark:border-white/5">
                            <div class="text-[11px] text-ink-500">Total SKU</div>
                            <div class="font-bold text-ink-900 dark:text-white mt-0.5">{{ $previewSummary['total_sku'] }}</div>
                        </div>
                    </div>
                </div>
            @endif

            <div class="flex items-center justify-between pt-1 sm:pt-2">
                <button type="button" wire:click="lewatiMigrasi" class="text-xs font-medium text-ink-500 hover:text-ink-800 dark:hover:text-ink-200 cursor-pointer underline text-left">
                    Lewati langkah ini (Saya akan input manual nanti via menu WMS) &rarr;
                </button>
            </div>
        </div>
    @endif

    <!-- STEP 5: Konfigurasi POS & QRIS -->
    @if($currentStep === 5)
        <div class="glass-panel p-4 sm:p-6 rounded-2xl border border-ink-200/60 dark:border-white/10 space-y-4 sm:space-y-6">
            <div class="border-b border-ink-200/60 dark:border-white/10 pb-3 sm:pb-4">
                <h2 class="text-base sm:text-lg font-bold text-ink-900 dark:text-white">Langkah 5: Konfigurasi Kasir POS & Pembayaran QRIS</h2>
                <p class="text-xs text-ink-500 dark:text-ink-400 mt-0.5">Atur ukuran kertas printer thermal kasir dan sediakan QRIS statis untuk transaksi non-tunai.</p>
            </div>

            <!-- Printer Thermal Width -->
            <div>
                <label class="block text-xs font-medium text-ink-700 dark:text-ink-300 mb-2">Ukuran Kertas Printer Kasir (Thermal ESC/POS)</label>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5 sm:gap-3">
                    <label class="flex items-start sm:items-center gap-3 p-3 sm:p-3.5 rounded-xl border cursor-pointer transition-all {{ $lebarKertas === '58mm' ? 'border-up-primary bg-up-primary/10 text-up-primary' : 'border-ink-200/60 dark:border-white/10 text-ink-700 dark:text-ink-300' }}">
                        <input type="radio" wire:model="lebarKertas" value="58mm" class="mt-0.5 sm:mt-0 text-up-primary focus:ring-up-primary flex-shrink-0">
                        <div class="min-w-0">
                            <div class="text-xs font-bold">58mm (Kompak / Hemat Kertas)</div>
                            <div class="text-[11px] opacity-80 mt-0.5">Rekomendasi printer thermal Bluetooth / USB standar retail.</div>
                        </div>
                    </label>
                    <label class="flex items-start sm:items-center gap-3 p-3 sm:p-3.5 rounded-xl border cursor-pointer transition-all {{ $lebarKertas === '80mm' ? 'border-up-primary bg-up-primary/10 text-up-primary' : 'border-ink-200/60 dark:border-white/10 text-ink-700 dark:text-ink-300' }}">
                        <input type="radio" wire:model="lebarKertas" value="80mm" class="mt-0.5 sm:mt-0 text-up-primary focus:ring-up-primary flex-shrink-0">
                        <div class="min-w-0">
                            <div class="text-xs font-bold">80mm (Lebar / Standar Enterprise)</div>
                            <div class="text-[11px] opacity-80 mt-0.5">Struk lebar dengan detail servis, barcode claim garansi lebih leluasa.</div>
                        </div>
                    </label>
                </div>
            </div>

            <!-- QRIS Statis Toko -->
            <div class="p-3.5 sm:p-4 rounded-xl border border-ink-200/60 dark:border-white/10 bg-ink-100/40 dark:bg-white/[0.02] space-y-3">
                <div class="flex items-center justify-between gap-3">
                    <div class="min-w-0">
                        <div class="text-xs font-bold text-ink-900 dark:text-white">QRIS Statis Cabang Toko</div>
                        <div class="text-[11px] text-ink-500 mt-0.5">Tampilkan QRIS statis di layar kasir untuk mempermudah pembayaran non-tunai.</div>
                    </div>
                    <label class="relative inline-flex items-center cursor-pointer flex-shrink-0">
                        <input type="checkbox" wire:model.live="aktifkanQris" class="sr-only peer">
                        <div class="w-11 h-6 bg-ink-300 peer-focus:outline-none rounded-full peer dark:bg-white/20 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-up-primary"></div>
                    </label>
                </div>

                @if($aktifkanQris)
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-2.5 sm:gap-3 pt-2">
                        <div>
                            <label class="block text-xs font-medium text-ink-700 dark:text-ink-300 mb-1">Nama Merchant QRIS <span class="text-up-red">*</span></label>
                            <input type="text" wire:model="qrisMerchantName" class="w-full px-3 py-2 rounded-lg border border-ink-300 dark:border-white/10 bg-white dark:bg-[#0B1120] text-xs sm:text-sm text-ink-900 dark:text-white" placeholder="Contoh: UTE PARTS OFFICIAL">
                            @error('qrisMerchantName') <span class="text-xs text-up-red">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-ink-700 dark:text-ink-300 mb-1">NMID (Opsional)</label>
                            <input type="text" wire:model="qrisNmid" class="w-full px-3 py-2 rounded-lg border border-ink-300 dark:border-white/10 bg-white dark:bg-[#0B1120] text-xs sm:text-sm text-ink-900 dark:text-white" placeholder="ID National Merchant">
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-xs font-medium text-ink-700 dark:text-ink-300 mb-1">Payload Statis EMVCo QRIS <span class="text-up-red">*</span></label>
                            <textarea wire:model="qrisPayloadStatic" rows="2" class="w-full px-3 py-2 rounded-lg border border-ink-300 dark:border-white/10 bg-white dark:bg-[#0B1120] text-xs font-mono text-ink-900 dark:text-white" placeholder="00020101021126600014ID.LINKAJA.WWW..."></textarea>
                            @error('qrisPayloadStatic') <span class="text-xs text-up-red">{{ $message }}</span> @enderror
                        </div>
                    </div>
                @endif
            </div>

            <!-- Pengaturan Pajak PPN -->
            <div class="p-3.5 sm:p-4 rounded-xl border border-ink-200/60 dark:border-white/10 bg-ink-100/40 dark:bg-white/[0.02] flex items-center justify-between gap-3">
                <div class="min-w-0">
                    <div class="text-xs font-bold text-ink-900 dark:text-white">Perhitungan Pajak PPN ({{ $ppnPersen }}%)</div>
                    <div class="text-[11px] text-ink-500 mt-0.5">Aktifkan jika harga penjualan kasir dikenakan pemisahan PPN Keluaran.</div>
                </div>
                <label class="relative inline-flex items-center cursor-pointer flex-shrink-0">
                    <input type="checkbox" wire:model="aktifkanPpn" class="sr-only peer">
                    <div class="w-11 h-6 bg-ink-300 peer-focus:outline-none rounded-full peer dark:bg-white/20 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-up-primary"></div>
                </label>
            </div>
        </div>
    @endif

    <!-- STEP 6: Review & Aktivasi -->
    @if($currentStep === 6)
        <div class="glass-panel p-4 sm:p-6 rounded-2xl border border-ink-200/60 dark:border-white/10 space-y-4 sm:space-y-6">
            <div class="border-b border-ink-200/60 dark:border-white/10 pb-3 sm:pb-4">
                <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-xs font-semibold bg-up-mint/15 text-up-mint">
                    Langkah Terakhir &bull; Siap Go-Live
                </span>
                <h2 class="text-base sm:text-lg font-bold text-ink-900 dark:text-white mt-1.5 sm:mt-2">Ringkasan Konfigurasi Setup Awal</h2>
                <p class="text-xs text-ink-500 dark:text-ink-400 mt-0.5">Pastikan seluruh data inisialisasi di bawah sudah sesuai sebelum mengaktifkan sistem.</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-3 sm:gap-4">
                <!-- Ringkasan Toko -->
                <div class="p-3.5 sm:p-4 rounded-xl border border-ink-200/60 dark:border-white/10 bg-white/50 dark:bg-[#0B1120]/50 space-y-1 sm:space-y-1.5">
                    <div class="text-[10px] sm:text-xs font-bold text-ink-500 uppercase tracking-wider">Identitas Toko & Cabang</div>
                    <div class="text-xs sm:text-sm font-semibold text-ink-900 dark:text-white break-words">{{ $namaToko }} ({{ $namaCabang }})</div>
                    <div class="text-xs text-ink-600 dark:text-ink-400 break-words">{{ $alamatToko ?: 'Alamat belum diatur' }}</div>
                    <div class="text-xs text-ink-600 dark:text-ink-400">Telp: {{ $teleponToko ?: '-' }}</div>
                </div>

                <!-- Ringkasan Keuangan -->
                <div class="p-3.5 sm:p-4 rounded-xl border border-ink-200/60 dark:border-white/10 bg-white/50 dark:bg-[#0B1120]/50 space-y-1 sm:space-y-1.5">
                    <div class="text-[10px] sm:text-xs font-bold text-ink-500 uppercase tracking-wider">Modal Saldo Awal</div>
                    <div class="text-xs sm:text-sm font-semibold text-up-primary">Total: Rp {{ number_format($totalModalNominal, 0, ',', '.') }}</div>
                    <div class="text-xs text-ink-600 dark:text-ink-400">Kas Tunai: Rp {{ number_format($kasNominal, 0, ',', '.') }}</div>
                    <div class="text-xs text-ink-600 dark:text-ink-400">Bank: Rp {{ number_format($bankNominal, 0, ',', '.') }}</div>
                </div>

                <!-- Ringkasan Akun -->
                <div class="p-3.5 sm:p-4 rounded-xl border border-ink-200/60 dark:border-white/10 bg-white/50 dark:bg-[#0B1120]/50 space-y-1 sm:space-y-1.5">
                    <div class="text-[10px] sm:text-xs font-bold text-ink-500 uppercase tracking-wider">Akun Operasional</div>
                    <div class="text-xs text-ink-800 dark:text-ink-200 break-words">Admin: <strong>{{ $superAdminNama }}</strong> <span class="text-[11px] text-ink-500">({{ $superAdminEmail }})</span></div>
                    <div class="text-xs text-ink-800 dark:text-ink-200 break-words">Kasir: <strong>{{ $kasirNama }}</strong> <span class="text-[11px] text-ink-500">({{ $kasirEmail }})</span></div>
                    <div class="text-xs text-ink-800 dark:text-ink-200 break-words">Teknisi: <strong>{{ $teknisiNama }}</strong> <span class="text-[11px] text-ink-500">({{ $teknisiEmail }})</span></div>
                </div>

                <!-- Ringkasan Hardware & Produk -->
                <div class="p-3.5 sm:p-4 rounded-xl border border-ink-200/60 dark:border-white/10 bg-white/50 dark:bg-[#0B1120]/50 space-y-1 sm:space-y-1.5">
                    <div class="text-[10px] sm:text-xs font-bold text-ink-500 uppercase tracking-wider">Hardware & Migrasi</div>
                    <div class="text-xs text-ink-800 dark:text-ink-200">Printer Thermal: <strong>{{ $lebarKertas }}</strong></div>
                    <div class="text-xs text-ink-800 dark:text-ink-200 break-words">QRIS: <strong>{{ $aktifkanQris ? 'Aktif (' . $qrisMerchantName . ')' : 'Non-aktif' }}</strong></div>
                    <div class="text-xs text-ink-800 dark:text-ink-200 break-words">Katalog Produk: <strong>{{ $sudahPreview ? $previewSummary['total_sku'] . ' SKU siap import' : ($skipMigrasi ? 'Input manual via WMS' : 'Belum diunggah') }}</strong></div>
                </div>
            </div>

            <!-- Tombol Final -->
            <div class="pt-3 sm:pt-4 border-t border-ink-200/60 dark:border-white/10 flex flex-col-reverse sm:flex-row items-stretch sm:items-center justify-between gap-2.5 sm:gap-4">
                <button type="button" wire:click="previousStep" class="w-full sm:w-auto px-4 py-2.5 rounded-xl border border-ink-300 dark:border-white/10 text-xs font-semibold text-ink-700 dark:text-ink-200 hover:bg-ink-100 dark:hover:bg-white/5 cursor-pointer text-center">
                    &larr; Kembali Ubah Data
                </button>
                <button type="button"
                    wire:click="selesaikanSetup"
                    wire:loading.attr="disabled"
                    class="w-full sm:w-auto px-5 sm:px-6 py-2.5 sm:py-3 rounded-xl bg-up-primary hover:bg-up-primary-dark text-white text-xs sm:text-sm font-bold shadow-lg shadow-up-primary/25 flex items-center justify-center gap-2 cursor-pointer disabled:opacity-50">
                    <span wire:loading.remove wire:target="selesaikanSetup">Selesaikan Setup & Mulai Transaksi POS &rarr;</span>
                    <span wire:loading wire:target="selesaikanSetup" class="flex items-center gap-2">
                        <svg class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path></svg>
                        Menyimpan & Mengaktifkan Sistem...
                    </span>
                </button>
            </div>
        </div>
    @endif

    <!-- Navigation Buttons (Steps 1 to 5) -->
    @if($currentStep < 6)
        <div class="flex items-center justify-between pt-1 sm:pt-2 gap-2">
            @if($currentStep > 1)
                <button type="button" wire:click="previousStep" class="px-3.5 sm:px-4 py-2 rounded-xl border border-ink-300 dark:border-white/10 text-xs font-semibold text-ink-700 dark:text-ink-200 hover:bg-ink-100 dark:hover:bg-white/5 cursor-pointer">
                    &larr; Sebelumnya
                </button>
            @else
                <div></div>
            @endif

            <button type="button" wire:click="nextStep" class="px-4 sm:px-5 py-2 sm:py-2.5 rounded-xl bg-up-primary hover:bg-up-primary-dark text-white text-xs font-semibold shadow-md shadow-up-primary/20 cursor-pointer">
                Langkah {{ $currentStep + 1 }} &rarr;
            </button>
        </div>
    @endif
</div>
