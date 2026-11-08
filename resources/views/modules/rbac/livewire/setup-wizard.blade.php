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
                <!-- Upload Logo Brand -->
                <div class="md:col-span-2">
                    <label class="block text-xs font-medium text-ink-700 dark:text-ink-300 mb-1">Logo Toko / Brand (Opsional)</label>
                    <div class="flex items-center gap-4 p-3.5 rounded-xl border border-dashed border-ink-300 dark:border-white/10 bg-black/[0.02] dark:bg-white/[0.02]">
                        <div class="w-16 h-16 rounded-xl border border-ink-200 dark:border-white/10 flex items-center justify-center overflow-hidden bg-white dark:bg-[#0B1120] flex-shrink-0 shadow-sm">
                            @if ($logoFile)
                                <img src="{{ $logoFile->temporaryUrl() }}" alt="Preview Logo" class="w-full h-full object-contain p-1">
                            @elseif ($existingLogoUrl)
                                <img src="{{ $existingLogoUrl }}" alt="Logo Toko" class="w-full h-full object-contain p-1">
                            @else
                                <x-prism.logo size="sm" :with-text="false" />
                            @endif
                        </div>
                        <div class="flex-1 min-w-0">
                            <input type="file" wire:model="logoFile" accept="image/png,image/jpeg,image/webp,image/svg+xml" class="text-xs text-ink-600 dark:text-ink-400 file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-up-primary/10 file:text-up-primary hover:file:bg-up-primary/20 cursor-pointer">
                            <p class="text-[11px] text-ink-500 mt-1">PNG, JPG, WebP, atau SVG (Maks. 2MB). Ditampilkan di header POS, struk kasir, dan toko online.</p>
                            @error('logoFile') <span class="text-xs text-up-red block mt-1">{{ $message }}</span> @enderror
                        </div>
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-medium text-ink-700 dark:text-ink-300 mb-1">Nama Toko Fisik <span class="text-up-red">*</span></label>
                    <input type="text" wire:model="namaToko" class="w-full px-3 sm:px-3.5 py-2 sm:py-2.5 rounded-xl border border-ink-300 dark:border-white/10 bg-white dark:bg-[#0B1120] text-xs sm:text-sm focus:border-up-primary focus:ring-1 focus:ring-up-primary text-ink-900 dark:text-white" placeholder="Contoh: Bremspace Sentral">
                    @error('namaToko') <span class="text-xs text-up-red">{{ $message }}</span> @enderror
                </div>

                <div class="space-y-2">
                    <label class="block text-xs font-medium text-ink-700 dark:text-ink-300">
                        Domain Toko / Bisnis <span class="text-up-red">*</span>
                    </label>
                    <input type="text"
                           wire:model.live.debounce.500ms="domainToko"
                           {{ $belumPunyaDomain ? 'readonly' : '' }}
                           class="w-full px-3 sm:px-3.5 py-2 sm:py-2.5 rounded-xl border {{ $belumPunyaDomain ? 'border-up-primary/40 bg-up-primary/[0.04]' : 'border-ink-300 dark:border-white/10 bg-white dark:bg-[#0B1120]' }} text-xs sm:text-sm font-mono focus:border-up-primary focus:ring-1 focus:ring-up-primary text-ink-900 dark:text-white transition"
                           placeholder="misal: bremspace.id atau pos.toko.com">
                    @error('domainToko') <span class="text-xs text-up-red block">{{ $message }}</span> @enderror

                    <!-- Checkbox Belum Punya Domain -->
                    <label class="flex items-start gap-2.5 p-2.5 rounded-xl border border-ink-200/80 dark:border-white/10 bg-black/[0.02] dark:bg-white/[0.02] hover:bg-black/[0.04] dark:hover:bg-white/[0.04] cursor-pointer transition select-none">
                        <input type="checkbox"
                               wire:model.live="belumPunyaDomain"
                               class="mt-0.5 rounded border-ink-300 text-up-primary focus:ring-up-primary/40 cursor-pointer">
                        <div class="text-xs space-y-0.5">
                            <span class="font-semibold text-ink-800 dark:text-ink-200">
                                Saya belum memiliki domain resmi (Gunakan IP server / domain lokal sementara)
                            </span>
                            <p class="text-[11px] text-ink-500">
                                Sistem otomatis menggunakan IP host aktif (<code class="font-mono text-up-primary">{{ $serverPublicIp ?: 'toko.local' }}</code>) agar setup dapat langsung dilanjutkan tanpa hambatan.
                            </p>
                        </div>
                    </label>

                    <p class="text-[11px] text-ink-500 dark:text-ink-400 flex items-start gap-1.5 leading-relaxed">
                        <span class="text-up-amber font-bold shrink-0">💡</span>
                        <span>
                            Email staf dan tautan sistem diseragamkan dengan domain ini. <strong>Domain dapat diubah kapan saja</strong> setelah setup selesai melalui menu <strong>Pengaturan &gt; Sistem &amp; Domain</strong>.
                        </span>
                    </p>
                </div>

                @php
                    $cleanDomainCheck = strtolower(trim($domainToko ?? ''));
                    $isLocalOrIp = filter_var($cleanDomainCheck, FILTER_VALIDATE_IP)
                        || in_array($cleanDomainCheck, ['localhost', '127.0.0.1', '::1'], true)
                        || str_ends_with($cleanDomainCheck, '.local')
                        || str_ends_with($cleanDomainCheck, '.lan')
                        || str_ends_with($cleanDomainCheck, '.internal')
                        || str_ends_with($cleanDomainCheck, '.test')
                        || $belumPunyaDomain;
                @endphp

                <!-- Bantuan Pointing DNS -->
                <div class="md:col-span-2 p-3.5 sm:p-4 rounded-xl border border-ink-200/80 dark:border-white/10 bg-black/[0.02] dark:bg-white/[0.02] space-y-3">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <svg class="w-4 h-4 text-up-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                            <span class="text-xs font-bold text-ink-900 dark:text-white">Panduan Pointing Domain {{ $isLocalOrIp ? '(Mode IP / Domain Lokal)' : ($isLocalhost ? '(Mode Lokal / Dev)' : '') }}</span>
                        </div>
                        <button type="button" wire:click="checkDns" wire:loading.attr="disabled" class="inline-flex items-center gap-1.5 px-2.5 py-1 text-xs font-medium rounded-lg bg-up-primary/10 hover:bg-up-primary/20 text-up-primary border border-up-primary/20 transition-colors">
                            <span wire:loading.remove wire:target="checkDns">Cek Status DNS</span>
                            <span wire:loading wire:target="checkDns" class="flex items-center gap-1">
                                <svg class="animate-spin w-3 h-3" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path></svg>
                                Memeriksa...
                            </span>
                        </button>
                    </div>

                    @if ($isLocalOrIp)
                        <div class="p-3 sm:p-3.5 rounded-xl bg-up-mint/10 border border-up-mint/30 text-xs text-ink-800 dark:text-ink-200 space-y-2">
                            <div class="font-bold text-up-mint flex items-center justify-between">
                                <span class="flex items-center gap-2">
                                    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                    <span>Mode Akses IP / Domain Lokal Aktif</span>
                                </span>
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-mono bg-up-mint/20 text-up-mint border border-up-mint/30 font-bold">Langsung Berfungsi</span>
                            </div>
                            <p class="text-[11px] text-ink-600 dark:text-ink-300 leading-relaxed">
                                Anda menggunakan alamat IP server atau domain internal (<code class="font-mono font-bold text-ink-900 dark:text-white">{{ $domainToko }}</code>). Tidak memerlukan konfigurasi DNS publik di awal dan aplikasi dapat langsung digunakan.
                            </p>
                            <div class="flex items-center gap-2 text-[11px] text-ink-500 pt-1 border-t border-up-mint/20">
                                <span class="font-mono text-up-mint font-bold">✓</span>
                                <span>Saat Anda sudah memiliki domain publik resmi nanti, cukup daftarkan A Record ke IP <code class="font-mono font-bold text-ink-900 dark:text-white">{{ $serverPublicIp }}</code> dan perbarui nama domain di menu <strong>Pengaturan &gt; Sistem &amp; Domain</strong>.</span>
                            </div>
                        </div>
                    @elseif ($isLocalhost)
                        <div class="p-2.5 rounded-lg bg-up-primary/10 border border-up-primary/20 text-xs text-ink-700 dark:text-ink-300 space-y-1.5">
                            <div class="font-semibold text-up-primary flex items-center gap-1.5">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                                Terdeteksi Lingkungan Komputer Lokal (Localhost / Dev)
                            </div>
                            <p class="text-[11px] text-ink-500 dark:text-ink-400">
                                Pada lingkungan lokal, domain tidak diarahkan ke IP publik VPS. Tambahkan baris berikut di file <code class="font-mono text-ink-900 dark:text-white">hosts</code> komputer Anda (<code class="font-mono text-[10px]">/etc/hosts</code> atau <code class="font-mono text-[10px]">C:\Windows\System32\drivers\etc\hosts</code>):
                            </p>
                            <div class="p-2 rounded bg-black/5 dark:bg-black/30 font-mono text-[11px] text-ink-900 dark:text-white select-all border border-ink-200 dark:border-white/5">
                                127.0.0.1 {{ $domainToko }}
                            </div>
                            <p class="text-[11px] text-ink-500 dark:text-ink-400">
                                Atau gunakan tunnel seperti Cloudflare Tunnel / Ngrok jika ingin domain publik mengarah ke localhost.
                            </p>
                        </div>
                    @else
                        <p class="text-xs text-ink-500 dark:text-ink-400">
                            Arahkan DNS domain Anda di penyedia domain (Niagahoster, Domainesia, Cloudflare, Namecheap, dsb) dengan data berikut:
                        </p>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 text-xs">
                            <div class="p-2.5 rounded-lg border border-ink-200 dark:border-white/10 bg-white dark:bg-[#0B1120]">
                                <span class="text-[10px] text-ink-400 uppercase tracking-wider block">Tipe Record</span>
                                <span class="font-bold font-mono text-ink-900 dark:text-white">A Record</span>
                                <span class="text-[11px] text-ink-500 block mt-0.5">Host: <code class="text-up-primary font-mono">@</code> atau domain utama</span>
                            </div>
                            <div class="p-2.5 rounded-lg border border-ink-200 dark:border-white/10 bg-white dark:bg-[#0B1120]">
                                <span class="text-[10px] text-ink-400 uppercase tracking-wider block">Target IP Publik Server</span>
                                <span class="font-bold font-mono text-ink-900 dark:text-white">{{ $serverPublicIp }}</span>
                                <span class="text-[11px] text-ink-500 block mt-0.5">Point A Record ke IP server ini</span>
                            </div>
                        </div>
                    @endif

                    @if (! $isLocalOrIp)
                        @if ($dnsStatus === 'matched')
                            <div class="p-2.5 rounded-lg bg-emerald-500/10 border border-emerald-500/20 text-emerald-600 dark:text-emerald-400 text-xs flex items-center gap-2">
                                <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                <span>DNS terhubung sempurna! Domain <strong>{{ $domainToko }}</strong> sudah mengarah ke server ini ({{ $dnsResolvedIp }}).</span>
                            </div>
                        @elseif ($dnsStatus === 'different_ip')
                            <div class="p-2.5 rounded-lg bg-amber-500/10 border border-amber-500/20 text-amber-600 dark:text-amber-400 text-xs flex items-center gap-2">
                                <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                                <span>Domain terdeteksi mengarah ke IP <strong>{{ $dnsResolvedIp }}</strong> (bukan {{ $serverPublicIp }}). Ubah A record di DNS registrar Anda.</span>
                            </div>
                        @elseif ($dnsStatus === 'unresolved')
                            <div class="p-2.5 rounded-lg bg-red-500/10 border border-red-500/20 text-red-600 dark:text-red-400 text-xs flex items-center gap-2">
                                <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                <span>DNS belum terdeteksi aktif untuk {{ $domainToko }}. Propagasi DNS biasanya memerlukan waktu 5–30 menit.</span>
                            </div>
                        @elseif ($dnsStatus === 'empty')
                            <div class="p-2.5 rounded-lg bg-red-500/10 border border-red-500/20 text-red-600 dark:text-red-400 text-xs">
                                Masukkan nama domain terlebih dahulu untuk memeriksa status pointing DNS.
                            </div>
                        @endif
                    @endif
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
                    <input type="text" wire:model="strukHeader" class="w-full px-3 sm:px-3.5 py-2 sm:py-2.5 rounded-xl border border-ink-300 dark:border-white/10 bg-white dark:bg-[#0B1120] text-xs sm:text-sm focus:border-up-primary focus:ring-1 focus:ring-up-primary text-ink-900 dark:text-white" placeholder="Contoh: BREMSPACE">
                    @error('strukHeader') <span class="text-xs text-up-red">{{ $message }}</span> @enderror
                </div>

                <div class="md:col-span-2">
                    <label class="block text-xs font-medium text-ink-700 dark:text-ink-300 mb-1">Alamat Lengkap Toko</label>
                    <textarea wire:model="alamatToko" rows="2" class="w-full px-3 sm:px-3.5 py-2 sm:py-2.5 rounded-xl border border-ink-300 dark:border-white/10 bg-white dark:bg-[#0B1120] text-xs sm:text-sm focus:border-up-primary focus:ring-1 focus:ring-up-primary text-ink-900 dark:text-white" placeholder="Jl. Raya Utama No. 123..."></textarea>
                    @error('alamatToko') <span class="text-xs text-up-red">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label class="block text-xs font-medium text-ink-700 dark:text-ink-300 mb-1">Slogan / Subtitle Struk</label>
                    <input type="text" wire:model="strukSlogan" class="w-full px-3 sm:px-3.5 py-2 sm:py-2.5 rounded-xl border border-ink-300 dark:border-white/10 bg-white dark:bg-[#0B1120] text-xs sm:text-sm focus:border-up-primary focus:ring-1 focus:ring-up-primary text-ink-900 dark:text-white" placeholder="Pusat Sparepart & Servis Gadget">
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
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-2.5 sm:gap-3">
                    <div>
                        <label class="block text-xs font-medium text-ink-700 dark:text-ink-300 mb-1">Nama Pemilik / Admin</label>
                        <input type="text" wire:model="superAdminNama" class="w-full px-3 py-2 rounded-lg border border-ink-300 dark:border-white/10 bg-white dark:bg-[#0B1120] text-xs sm:text-sm text-ink-900 dark:text-white">
                        @error('superAdminNama') <span class="text-xs text-up-red">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-ink-700 dark:text-ink-300 mb-1">Email Login Admin</label>
                        <input type="email" wire:model="superAdminEmail" class="w-full px-3 py-2 rounded-lg border border-ink-300 dark:border-white/10 bg-white dark:bg-[#0B1120] text-xs sm:text-sm text-ink-900 dark:text-white">
                        @error('superAdminEmail') <span class="text-xs text-up-red">{{ $message }}</span> @enderror
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
                <div class="grid grid-cols-1 md:grid-cols-3 gap-2.5 sm:gap-3">
                    <div>
                        <label class="block text-xs font-medium text-ink-700 dark:text-ink-300 mb-1">Nama Staf Kasir</label>
                        <input type="text" wire:model="kasirNama" class="w-full px-3 py-2 rounded-lg border border-ink-300 dark:border-white/10 bg-white dark:bg-[#0B1120] text-xs sm:text-sm text-ink-900 dark:text-white">
                        @error('kasirNama') <span class="text-xs text-up-red">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-ink-700 dark:text-ink-300 mb-1">Email Login Kasir</label>
                        <input type="email" wire:model="kasirEmail" class="w-full px-3 py-2 rounded-lg border border-ink-300 dark:border-white/10 bg-white dark:bg-[#0B1120] text-xs sm:text-sm text-ink-900 dark:text-white">
                        @error('kasirEmail') <span class="text-xs text-up-red">{{ $message }}</span> @enderror
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
                <div class="grid grid-cols-1 md:grid-cols-3 gap-2.5 sm:gap-3">
                    <div>
                        <label class="block text-xs font-medium text-ink-700 dark:text-ink-300 mb-1">Nama Teknisi</label>
                        <input type="text" wire:model="teknisiNama" class="w-full px-3 py-2 rounded-lg border border-ink-300 dark:border-white/10 bg-white dark:bg-[#0B1120] text-xs sm:text-sm text-ink-900 dark:text-white">
                        @error('teknisiNama') <span class="text-xs text-up-red">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-ink-700 dark:text-ink-300 mb-1">Email Login Teknisi</label>
                        <input type="email" wire:model="teknisiEmail" class="w-full px-3 py-2 rounded-lg border border-ink-300 dark:border-white/10 bg-white dark:bg-[#0B1120] text-xs sm:text-sm text-ink-900 dark:text-white">
                        @error('teknisiEmail') <span class="text-xs text-up-red">{{ $message }}</span> @enderror
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
                    <h2 class="text-base sm:text-lg font-bold text-ink-900 dark:text-white">Langkah 4: Migrasi Data Produk &amp; Stok Fisik (Opsional)</h2>
                    <p class="text-xs text-ink-500 dark:text-ink-400 mt-0.5">Pindahkan katalog SKU dan stok awal dari aplikasi lama atau file Excel kustom. Jika toko baru, Anda dapat melewati langkah ini.</p>
                </div>
                <button type="button" wire:click="downloadTemplate" class="self-start sm:self-auto inline-flex items-center gap-1.5 px-3 py-2 rounded-xl text-xs font-semibold bg-ink-200 dark:bg-white/10 hover:bg-ink-300 text-ink-800 dark:text-ink-100 transition-colors cursor-pointer flex-shrink-0">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                    Unduh Template Excel
                </button>
            </div>

            @if($errorMessage)
                <div class="p-3.5 sm:p-4 rounded-xl bg-up-red/10 border border-up-red/30 text-up-red text-xs sm:text-sm flex items-start gap-2.5 sm:gap-3">
                    <svg class="w-4 h-4 sm:w-5 sm:h-5 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <div class="break-words font-medium">{{ $errorMessage }}</div>
                </div>
            @endif

            @if(! $isCustomMapping && ! $sudahPreview)
                <!-- Upload Area Standar -->
                <div class="p-4 sm:p-6 border-2 border-dashed border-ink-300 dark:border-white/15 rounded-2xl text-center space-y-2.5 sm:space-y-3 bg-white/40 dark:bg-[#0B1120]/40">
                    <svg class="w-8 h-8 sm:w-10 sm:h-10 mx-auto text-ink-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/></svg>
                    <div class="text-xs sm:text-sm font-medium text-ink-800 dark:text-ink-200">
                        Pilih file migrasi produk (.xlsx, .xls, .csv)
                    </div>
                    <p class="text-[11px] sm:text-xs text-ink-500">Maksimal 25MB. Sistem otomatis mendeteksi format template standar, SidRetail, maupun format Excel kustom Anda.</p>
                    <div class="pt-1 sm:pt-2 overflow-hidden">
                        <input type="file" wire:model="fileMigrasi" accept=".xlsx,.xls,.csv" class="max-w-full text-xs text-ink-500 file:mr-2 sm:file:mr-4 file:py-1.5 sm:file:py-2 file:px-3 sm:file:px-4 file:rounded-xl file:border-0 file:text-[11px] sm:file:text-xs file:font-semibold file:bg-up-primary file:text-white hover:file:opacity-90 cursor-pointer">
                    </div>
                    <div wire:loading wire:target="fileMigrasi" class="text-xs text-up-primary font-semibold mt-2 animate-pulse">
                        Mengunggah dan menganalisis file...
                    </div>
                </div>
            @elseif($isCustomMapping && ! $sudahPreview)
                <!-- Custom Column Mapping Interface -->
                @php
                    $targetOptions = [
                        '⭐ Kolom Utama (Wajib)' => [
                            'nama' => 'Nama Produk / Sparepart [Wajib]',
                            'harga_jual' => 'Harga Jual (Retail / Eceran) [Wajib]',
                            'sku' => 'Kode SKU / Part Number [Wajib / Auto]',
                        ],
                        '💰 Harga & Nilai' => [
                            'harga_beli' => 'Harga Beli (Modal / HPP)',
                            'harga_reseller' => 'Harga Reseller',
                            'harga_agen' => 'Harga Agen / Grosir',
                        ],
                        '📦 Stok & Lokasi Penyimpanan' => [
                            'stok_awal' => 'Stok Awal (Saldo Awal)',
                            'gudang' => 'Gudang Penyimpanan',
                            'rak' => 'Lokasi Rak / Bin',
                            'min_stock' => 'Stok Minimum (Alert Restock)',
                            'reorder_point' => 'Reorder Point (ROP)',
                            'sn' => 'Lacak Serial Number (ya / tidak)',
                        ],
                        '🏷️ Kategori, Brand & Supplier' => [
                            'satuan' => 'Satuan Unit (pcs, box, set)',
                            'kategori' => 'Kategori Produk',
                            'sub_kategori' => 'Sub Kategori',
                            'brand' => 'Brand / Merk Produk',
                            'kualitas' => 'Kualitas / Grade (Original, OEM)',
                            'kondisi' => 'Kondisi (baru / bekas / oem)',
                            'supplier' => 'Supplier / Pemasok',
                        ],
                        '📱 Kompatibilitas Smartphone' => [
                            'merk_hp' => 'Merk HP / Ponsel',
                            'tipe_hp' => 'Tipe / Model HP Kompatibel',
                            'kompatibilitas_hp' => 'Kompatibilitas HP Bebas',
                            'produk_kompatibel_sku' => 'Produk Kompatibel (Cross-SKU)',
                        ],
                        '🔍 Barcode & Lainnya' => [
                            'barcode_1' => 'Barcode Utama',
                            'barcode_2' => 'Barcode 2 (Alternatif)',
                            'barcode_3' => 'Barcode 3',
                            'multi_barcode' => 'Multi Barcode (Pisahkan Koma)',
                            'konversi_satuan' => 'Konversi Multi Satuan',
                            'deskripsi' => 'Deskripsi / Keterangan',
                            'foto_url' => 'URL Foto Produk',
                        ],
                    ];

                    $mappedVals = array_filter(array_values($columnMapping ?? []), fn ($v) => $v !== '' && $v !== null && $v !== 'abaikan');
                    $hasNama = in_array('nama', $mappedVals, true);
                    $hasHarga = in_array('harga_jual', $mappedVals, true);
                    $hasSku = in_array('sku', $mappedVals, true);
                    $totalMapped = count($mappedVals);
                    $totalIgnored = count($customColumns) - $totalMapped;

                    $columnsJson = array_map(function ($c) {
                        return [
                            'key' => $c['key'],
                            'name' => strtolower($c['raw_name']),
                            'letter' => strtolower($c['col_letter']),
                            'index' => $c['index'],
                        ];
                    }, $customColumns);
                @endphp

                <div x-data="{
                    search: '',
                    filterTab: 'semua',
                    columns: {{ Js::from($columnsJson) }},
                    isMatch(name, key, letter, currentVal) {
                        const mapping = ($wire && $wire.columnMapping && $wire.columnMapping[key] !== undefined)
                            ? $wire.columnMapping[key]
                            : currentVal;
                        const isMapped = mapping !== '' && mapping !== null && mapping !== 'abaikan';
                        const isWajib = ['nama', 'harga_jual', 'sku'].includes(mapping);

                        if (this.filterTab === 'belum' && isMapped) return false;
                        if (this.filterTab === 'sudah' && !isMapped) return false;
                        if (this.filterTab === 'wajib' && !isWajib) return false;

                        if (this.search && this.search.trim() !== '') {
                            const q = this.search.toLowerCase().trim();
                            const text = (name + ' ' + letter + ' ' + key + ' ' + (mapping || '')).toLowerCase();
                            if (!text.includes(q)) return false;
                        }
                        return true;
                    },
                    get countSemua() {
                        return this.columns.length;
                    },
                    get countBelum() {
                        const mapping = ($wire && $wire.columnMapping) ? $wire.columnMapping : {{ Js::from($columnMapping) }};
                        return this.columns.filter(c => {
                            const val = mapping[c.key] || '';
                            return !val || val === 'abaikan';
                        }).length;
                    },
                    get countSudah() {
                        const mapping = ($wire && $wire.columnMapping) ? $wire.columnMapping : {{ Js::from($columnMapping) }};
                        return this.columns.filter(c => {
                            const val = mapping[c.key] || '';
                            return val && val !== 'abaikan';
                        }).length;
                    },
                    get countWajib() {
                        const mapping = ($wire && $wire.columnMapping) ? $wire.columnMapping : {{ Js::from($columnMapping) }};
                        return this.columns.filter(c => ['nama', 'harga_jual', 'sku'].includes(mapping[c.key] || '')).length;
                    },
                    get visibleCount() {
                        return this.columns.filter(c => {
                            const mapping = ($wire && $wire.columnMapping) ? $wire.columnMapping : {{ Js::from($columnMapping) }};
                            return this.isMatch(c.name, c.key, c.letter, mapping[c.key] || '');
                        }).length;
                    }
                }" class="space-y-4 sm:space-y-5">

                    <!-- Header Banner Pemetaan Kolom -->
                    <div class="p-4 sm:p-5 rounded-2xl bg-gradient-to-r from-up-primary/15 via-up-primary/5 to-transparent border border-up-primary/30 space-y-4">
                        <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
                            <div class="flex items-start gap-3">
                                <span class="w-10 h-10 rounded-xl bg-up-primary/20 text-up-primary flex items-center justify-center text-lg font-bold shadow-sm shrink-0 mt-0.5">⚡</span>
                                <div class="space-y-1">
                                    <div class="flex items-center gap-2 flex-wrap">
                                        <h3 class="text-sm sm:text-base font-bold text-ink-900 dark:text-white">Fitur Custom Import Aktif (Pemetaan Kolom)</h3>
                                        <span class="px-2.5 py-0.5 rounded-full text-[10px] font-mono font-bold bg-up-primary/20 text-up-primary border border-up-primary/30">
                                            {{ count($customColumns) }} Kolom Terbaca
                                        </span>
                                    </div>
                                    <p class="text-xs text-ink-500 dark:text-ink-400 leading-relaxed max-w-3xl">
                                        File Anda memiliki format kustom. Silakan cocokkan tiap kolom file dengan field master produk sistem. Kolom dengan pilihan <strong>Abaikan</strong> akan dilewati otomatis.
                                    </p>
                                </div>
                            </div>

                            @if(isset($gudangList) && count($gudangList) > 0)
                                <div class="flex items-center justify-between sm:justify-end gap-2.5 bg-black/5 dark:bg-black/40 border border-black/10 dark:border-white/10 px-3.5 py-2 rounded-xl shrink-0">
                                    <span class="text-xs text-ink-400 font-semibold whitespace-nowrap">Gudang Default:</span>
                                    <select wire:model="defaultGudangId" class="text-xs rounded-lg bg-white dark:bg-black/70 border border-black/15 dark:border-white/15 px-2.5 py-1.5 text-ink-900 dark:text-white font-medium focus:ring-1 focus:ring-up-primary cursor-pointer">
                                        @foreach($gudangList as $g)
                                            <option value="{{ $g->id }}">{{ $g->nama }} ({{ $g->kode }})</option>
                                        @endforeach
                                    </select>
                                </div>
                            @endif
                        </div>

                        <!-- Status Wajib Indicators & Quick Bulk Tools -->
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pt-3 border-t border-up-primary/15 text-xs">
                            <div class="flex items-center gap-2 flex-wrap">
                                <span class="text-[11px] text-ink-400 font-bold uppercase tracking-wider">Validasi Utama:</span>
                                <span class="px-2.5 py-1 rounded-lg text-xs font-bold flex items-center gap-1.5 {{ $hasNama ? 'bg-up-mint/20 text-up-mint border border-up-mint/30' : 'bg-up-red/20 text-up-red border border-up-red/30 animate-pulse' }}">
                                    <span>{{ $hasNama ? '✓' : '⚠️' }}</span>
                                    <span>Nama Produk (Wajib)</span>
                                </span>
                                <span class="px-2.5 py-1 rounded-lg text-xs font-bold flex items-center gap-1.5 {{ $hasHarga ? 'bg-up-mint/20 text-up-mint border border-up-mint/30' : 'bg-up-red/20 text-up-red border border-up-red/30 animate-pulse' }}">
                                    <span>{{ $hasHarga ? '✓' : '⚠️' }}</span>
                                    <span>Harga Jual (Wajib)</span>
                                </span>
                                <span class="px-2.5 py-1 rounded-lg text-xs font-bold flex items-center gap-1.5 {{ $hasSku ? 'bg-up-mint/20 text-up-mint border border-up-mint/30' : 'bg-up-amber/20 text-up-amber border border-up-amber/30' }}">
                                    <span>{{ $hasSku ? '✓' : 'ℹ️' }}</span>
                                    <span>{{ $hasSku ? 'SKU Terpetakan' : 'SKU (Auto-Generate)' }}</span>
                                </span>
                            </div>

                            <div class="flex items-center gap-2 flex-wrap">
                                <span class="text-[11px] text-ink-500 dark:text-ink-400 mr-1">
                                    <strong class="text-ink-900 dark:text-white">{{ $totalMapped }}</strong> dipetakan, <strong class="text-ink-900 dark:text-white">{{ $totalIgnored }}</strong> diabaikan
                                </span>
                                <button type="button"
                                        wire:click="resetCustomMapping"
                                        class="px-2.5 py-1.5 rounded-lg bg-black/5 dark:bg-white/5 hover:bg-black/10 dark:hover:bg-white/10 border border-black/10 dark:border-white/10 text-ink-700 dark:text-ink-300 text-xs font-bold cursor-pointer transition">
                                    ↺ Reset Rekomendasi
                                </button>
                                <button type="button"
                                        wire:click="abaikanSemuaKolom"
                                        class="px-2.5 py-1.5 rounded-lg bg-black/5 dark:bg-white/5 hover:bg-up-red/10 border border-black/10 dark:border-white/10 text-ink-500 hover:text-up-red text-xs font-bold cursor-pointer transition">
                                    ⛔ Abaikan Semua
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Quick Filter & Search Box Toolbar (Alpine Client-Side) -->
                    <div class="p-3.5 sm:p-4 rounded-2xl bg-black/5 dark:bg-white/[0.02] border border-black/10 dark:border-white/5 space-y-3">
                        <div class="flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-3">
                            <!-- Search Box -->
                            <div class="relative flex-1 max-w-md">
                                <span class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-ink-400 text-xs">
                                    🔍
                                </span>
                                <input type="text"
                                       x-model.debounce.100ms="search"
                                       placeholder="Cari kolom file atau target mapping..."
                                       class="w-full pl-8 pr-8 py-2 text-xs rounded-xl bg-white dark:bg-black/30 border border-black/10 dark:border-white/10 text-ink-900 dark:text-white placeholder-ink-400 focus:outline-none focus:ring-2 focus:ring-up-primary/40 transition">
                                <button x-cloak
                                        x-show="search.length > 0"
                                        @click="search = ''"
                                        type="button"
                                        class="absolute inset-y-0 right-0 pr-2.5 flex items-center text-ink-400 hover:text-ink-600 dark:hover:text-ink-200 text-xs cursor-pointer">
                                    ✕
                                </button>
                            </div>

                            <!-- Filter Tabs -->
                            <div class="flex items-center gap-1.5 overflow-x-auto pb-1 sm:pb-0 no-scrollbar">
                                <button type="button"
                                        @click="filterTab = 'semua'"
                                        :class="filterTab === 'semua' ? 'bg-up-primary text-white shadow-sm shadow-up-primary/30 font-black' : 'bg-black/5 dark:bg-white/5 text-ink-400 hover:text-ink-900 dark:hover:text-white font-semibold'"
                                        class="px-3 py-1.5 rounded-xl text-xs transition flex items-center gap-1.5 cursor-pointer whitespace-nowrap">
                                    <span>Semua</span>
                                    <span class="px-1.5 py-0.5 rounded-full text-[10px] font-mono"
                                          :class="filterTab === 'semua' ? 'bg-white/20 text-white' : 'bg-black/10 dark:bg-white/10 text-ink-400'"
                                          x-text="countSemua"></span>
                                </button>

                                <button type="button"
                                        @click="filterTab = 'belum'"
                                        :class="filterTab === 'belum' ? 'bg-up-amber text-black shadow-sm shadow-up-amber/30 font-black' : 'bg-black/5 dark:bg-white/5 text-ink-400 hover:text-ink-900 dark:hover:text-white font-semibold'"
                                        class="px-3 py-1.5 rounded-xl text-xs transition flex items-center gap-1.5 cursor-pointer whitespace-nowrap">
                                    <span>Belum Dipetakan</span>
                                    <span class="px-1.5 py-0.5 rounded-full text-[10px] font-mono"
                                          :class="filterTab === 'belum' ? 'bg-black/20 text-black font-bold' : 'bg-black/10 dark:bg-white/10 text-ink-400'"
                                          x-text="countBelum"></span>
                                </button>

                                <button type="button"
                                        @click="filterTab = 'sudah'"
                                        :class="filterTab === 'sudah' ? 'bg-up-mint text-black shadow-sm shadow-up-mint/30 font-black' : 'bg-black/5 dark:bg-white/5 text-ink-400 hover:text-ink-900 dark:hover:text-white font-semibold'"
                                        class="px-3 py-1.5 rounded-xl text-xs transition flex items-center gap-1.5 cursor-pointer whitespace-nowrap">
                                    <span>Sudah Dipetakan</span>
                                    <span class="px-1.5 py-0.5 rounded-full text-[10px] font-mono"
                                          :class="filterTab === 'sudah' ? 'bg-black/20 text-black font-bold' : 'bg-black/10 dark:bg-white/10 text-ink-400'"
                                          x-text="countSudah"></span>
                                </button>

                                <button type="button"
                                        @click="filterTab = 'wajib'"
                                        :class="filterTab === 'wajib' ? 'bg-up-primary/20 text-up-primary border border-up-primary/40 font-black' : 'bg-black/5 dark:bg-white/5 text-ink-400 hover:text-ink-900 dark:hover:text-white font-semibold'"
                                        class="px-3 py-1.5 rounded-xl text-xs transition flex items-center gap-1.5 cursor-pointer whitespace-nowrap">
                                    <span>⭐ Wajib</span>
                                    <span class="px-1.5 py-0.5 rounded-full text-[10px] font-mono"
                                          :class="filterTab === 'wajib' ? 'bg-up-primary text-white font-bold' : 'bg-black/10 dark:bg-white/10 text-ink-400'"
                                          x-text="countWajib"></span>
                                </button>
                            </div>
                        </div>

                        <!-- Toolbar Footer: Visible counter -->
                        <div class="flex items-center justify-between text-[11px] text-ink-400 pt-2 border-t border-black/5 dark:border-white/5">
                            <div>
                                Menampilkan <strong class="text-ink-900 dark:text-white" x-text="visibleCount"></strong> dari <strong class="text-ink-900 dark:text-white" x-text="countSemua"></strong> kolom
                            </div>
                            <div class="flex items-center gap-1 text-[11px] text-ink-400">
                                <span class="inline-block w-2 h-2 rounded-full bg-up-mint"></span>
                                <span>Hijau = Aktif</span>
                                <span class="inline-block w-2 h-2 rounded-full bg-up-primary ml-2"></span>
                                <span>Biru = Wajib</span>
                            </div>
                        </div>
                    </div>

                    <!-- Empty State (No matches found) -->
                    <div x-cloak
                         x-show="visibleCount === 0"
                         class="py-10 px-4 text-center rounded-2xl bg-black/5 dark:bg-white/[0.02] border border-dashed border-black/10 dark:border-white/10 space-y-3">
                        <div class="w-12 h-12 rounded-2xl bg-up-primary/10 text-up-primary flex items-center justify-center text-xl mx-auto">
                            🔍
                        </div>
                        <div class="space-y-1">
                            <h4 class="text-sm font-bold text-ink-900 dark:text-white">Tidak ada kolom yang cocok</h4>
                            <p class="text-xs text-ink-400 max-w-sm mx-auto">
                                Tidak ditemukan kolom yang cocok dengan kata kunci atau filter yang Anda pilih.
                            </p>
                        </div>
                        <button type="button"
                                @click="search = ''; filterTab = 'semua'"
                                class="px-4 py-2 rounded-xl bg-up-primary/10 hover:bg-up-primary/20 text-up-primary text-xs font-bold transition cursor-pointer">
                            Reset Pencarian &amp; Filter
                        </button>
                    </div>

                    <!-- MOBILE LAYOUT: Stacked Cards (md:hidden, touch targets >= 44px) -->
                    <div class="md:hidden space-y-3" x-show="visibleCount > 0">
                        @foreach($customColumns as $col)
                            @php
                                $selectedTarget = $columnMapping[$col['key']] ?? '';
                                $isTargetRequired = in_array($selectedTarget, ['sku', 'nama', 'harga_jual'], true);
                                $isTargetActive = ! empty($selectedTarget);
                            @endphp
                            <div x-show="isMatch('{{ strtolower(addslashes($col['raw_name'])) }}', '{{ $col['key'] }}', '{{ strtolower($col['col_letter']) }}', '{{ $selectedTarget }}')"
                                 wire:key="card-{{ $col['key'] }}"
                                 class="p-4 rounded-2xl transition border {{ $isTargetRequired ? 'bg-up-primary/[0.04] border-up-primary/40 shadow-sm' : ($isTargetActive ? 'bg-up-mint/[0.03] border-up-mint/30 shadow-sm' : 'bg-black/5 dark:bg-white/[0.02] border-black/10 dark:border-white/5 opacity-85 hover:opacity-100') }} space-y-3">

                                <div class="flex items-start justify-between gap-2">
                                    <div class="flex items-center gap-2.5 min-w-0">
                                        <span class="shrink-0 w-8 h-8 rounded-xl font-mono text-xs font-black flex items-center justify-center shadow-xs {{ $isTargetRequired ? 'bg-up-primary/20 text-up-primary border border-up-primary/30' : ($isTargetActive ? 'bg-up-mint/20 text-up-mint border border-up-mint/30' : 'bg-black/10 dark:bg-white/10 text-ink-400') }}">
                                            {{ $col['col_letter'] }}
                                        </span>
                                        <div class="min-w-0">
                                            <div class="font-bold text-sm text-ink-950 dark:text-white truncate" title="{{ $col['raw_name'] }}">
                                                {{ $col['raw_name'] }}
                                            </div>
                                            <div class="text-[10px] text-ink-400 font-mono">
                                                Index #{{ $col['index'] }}
                                            </div>
                                        </div>
                                    </div>

                                    <div class="flex items-center gap-1.5 shrink-0">
                                        @if($isTargetRequired)
                                            <span class="px-2 py-0.5 rounded-full bg-up-primary/20 text-up-primary text-[10px] font-black uppercase tracking-wider border border-up-primary/30">
                                                ⭐ Wajib
                                            </span>
                                        @elseif($isTargetActive)
                                            <span class="px-2 py-0.5 rounded-full bg-up-mint/20 text-up-mint text-[10px] font-bold uppercase tracking-wider border border-up-mint/30">
                                                ✓ Aktif
                                            </span>
                                        @else
                                            <span class="px-2 py-0.5 rounded-full bg-black/10 dark:bg-white/10 text-ink-400 text-[10px] font-medium">
                                                Abaikan
                                            </span>
                                        @endif

                                        @if($isTargetActive)
                                            <button type="button"
                                                    wire:click="clearColumnMapping('{{ $col['key'] }}')"
                                                    title="Abaikan kolom ini"
                                                    class="w-6 h-6 rounded-lg bg-black/5 dark:bg-white/5 hover:bg-up-red/20 text-ink-400 hover:text-up-red flex items-center justify-center transition cursor-pointer text-xs leading-none">
                                                ✕
                                            </button>
                                        @endif
                                    </div>
                                </div>

                                <!-- Sample Values -->
                                <div class="space-y-1">
                                    <div class="text-[10px] text-ink-400 font-semibold uppercase tracking-wider">Contoh Nilai Data:</div>
                                    @if(! empty($col['sample_values']))
                                        <div class="flex items-center gap-1.5 overflow-x-auto pb-1 -mx-1 px-1 no-scrollbar">
                                            @foreach($col['sample_values'] as $sample)
                                                <span class="shrink-0 px-2 py-0.5 rounded-lg bg-black/10 dark:bg-white/5 border border-black/5 dark:border-white/5 text-[11px] font-mono text-ink-700 dark:text-ink-300 max-w-[220px] truncate" title="{{ $sample }}">
                                                    {{ $sample }}
                                                </span>
                                            @endforeach
                                        </div>
                                    @else
                                        <div class="text-[11px] text-ink-500 italic">(sel kosong / tidak ada sampel)</div>
                                    @endif
                                </div>

                                <!-- Target Field Dropdown Select (Touch target >= 44px) -->
                                <div class="space-y-1">
                                    <label for="mobile-select-{{ $col['key'] }}" class="text-[10px] text-ink-400 font-semibold uppercase tracking-wider">
                                        Petakan ke Kolom Master:
                                    </label>
                                    <select id="mobile-select-{{ $col['key'] }}"
                                            wire:key="mobile-sel-{{ $col['key'] }}"
                                            wire:model.live="columnMapping.{{ $col['key'] }}"
                                            class="w-full min-h-[44px] text-xs sm:text-sm rounded-xl px-3 py-2.5 border transition cursor-pointer {{ $isTargetRequired ? 'bg-up-primary/10 border-up-primary text-up-primary font-bold focus:ring-2 focus:ring-up-primary/40' : ($isTargetActive ? 'bg-white dark:bg-black/40 border-up-mint/50 text-up-mint font-semibold' : 'bg-white dark:bg-black/30 border-black/10 dark:border-white/10 text-ink-400') }}">
                                        <option value="">-- ⛔ Abaikan Kolom Ini (Tidak Diimpor) --</option>
                                        @foreach($targetOptions as $groupLabel => $opts)
                                            <optgroup label="{{ $groupLabel }}">
                                                @foreach($opts as $val => $label)
                                                    <option value="{{ $val }}">{{ $label }}</option>
                                                @endforeach
                                            </optgroup>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <!-- DESKTOP LAYOUT: Polished Table (md:block) -->
                    <div class="hidden md:block p-5 rounded-2xl bg-black/5 dark:bg-white/[0.02] border border-black/10 dark:border-white/5 space-y-4" x-show="visibleCount > 0">
                        <div class="overflow-x-auto">
                            <table class="w-full text-left text-xs border-collapse">
                                <thead>
                                    <tr class="border-b border-black/10 dark:border-white/10 text-[11px] text-ink-400 uppercase tracking-wider">
                                        <th class="py-3 px-3 w-16 text-center">Kolom</th>
                                        <th class="py-3 px-4 w-1/4">Nama Kolom di File</th>
                                        <th class="py-3 px-4 w-1/3">Contoh Nilai Data (Sampel)</th>
                                        <th class="py-3 px-2 w-8 text-center">→</th>
                                        <th class="py-3 px-4">Dipetakan ke Kolom Master Produk</th>
                                        <th class="py-3 px-3 w-12 text-center">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-black/5 dark:divide-white/5">
                                    @foreach($customColumns as $col)
                                        @php
                                            $selectedTarget = $columnMapping[$col['key']] ?? '';
                                            $isTargetRequired = in_array($selectedTarget, ['sku', 'nama', 'harga_jual'], true);
                                            $isTargetActive = ! empty($selectedTarget);
                                        @endphp
                                        <tr x-show="isMatch('{{ strtolower(addslashes($col['raw_name'])) }}', '{{ $col['key'] }}', '{{ strtolower($col['col_letter']) }}', '{{ $selectedTarget }}')"
                                            wire:key="row-{{ $col['key'] }}"
                                            class="hover:bg-black/[0.02] dark:hover:bg-white/[0.02] transition {{ $isTargetRequired ? 'bg-up-primary/[0.03]' : ($isTargetActive ? 'bg-up-mint/[0.02]' : 'opacity-65 hover:opacity-100') }}">
                                            <!-- Column Letter / Index -->
                                            <td class="py-3 px-3 text-center">
                                                <span class="inline-flex items-center justify-center min-w-[28px] h-7 px-2 rounded-lg font-mono text-[11px] font-black {{ $isTargetRequired ? 'bg-up-primary/20 text-up-primary border border-up-primary/30' : ($isTargetActive ? 'bg-up-mint/20 text-up-mint border border-up-mint/30' : 'bg-black/5 dark:bg-white/5 text-ink-400') }}">
                                                    {{ $col['col_letter'] }}
                                                </span>
                                            </td>

                                            <!-- Header Name -->
                                            <td class="py-3 px-4">
                                                <div class="font-bold text-xs text-ink-900 dark:text-white">
                                                    {{ $col['raw_name'] }}
                                                </div>
                                                <div class="text-[10px] text-ink-400 font-mono mt-0.5">Index #{{ $col['index'] }}</div>
                                            </td>

                                            <!-- Sample Values -->
                                            <td class="py-3 px-4">
                                                @if(! empty($col['sample_values']))
                                                    <div class="flex flex-wrap gap-1.5 max-h-20 overflow-y-auto">
                                                        @foreach($col['sample_values'] as $sample)
                                                            <span class="px-2 py-0.5 rounded bg-black/10 dark:bg-white/5 text-[11px] font-mono text-ink-700 dark:text-ink-300 max-w-[200px] truncate" title="{{ $sample }}">
                                                                {{ $sample }}
                                                            </span>
                                                        @endforeach
                                                    </div>
                                                @else
                                                    <span class="text-[11px] text-ink-500 italic">(sel kosong / tidak ada sampel)</span>
                                                @endif
                                            </td>

                                            <!-- Arrow Icon -->
                                            <td class="py-3 px-2 text-center text-ink-400 text-sm font-bold">
                                                →
                                            </td>

                                            <!-- Target Field Dropdown -->
                                            <td class="py-3 px-4">
                                                <div class="flex items-center gap-2">
                                                    <select id="desktop-select-{{ $col['key'] }}"
                                                            wire:key="desktop-sel-{{ $col['key'] }}"
                                                            wire:model.live="columnMapping.{{ $col['key'] }}"
                                                            class="w-full text-xs rounded-xl px-3 py-2 border transition cursor-pointer {{ $isTargetRequired ? 'bg-up-primary/10 border-up-primary text-up-primary font-bold focus:ring-1 focus:ring-up-primary' : ($isTargetActive ? 'bg-white dark:bg-black/40 border-up-mint/50 text-up-mint' : 'bg-white dark:bg-black/30 border-black/10 dark:border-white/10 text-ink-400') }}">
                                                        <option value="">-- ⛔ Abaikan Kolom Ini (Tidak Diimpor) --</option>
                                                        @foreach($targetOptions as $groupLabel => $opts)
                                                            <optgroup label="{{ $groupLabel }}">
                                                                @foreach($opts as $val => $label)
                                                                    <option value="{{ $val }}">{{ $label }}</option>
                                                                @endforeach
                                                            </optgroup>
                                                        @endforeach
                                                    </select>

                                                    @if($isTargetRequired)
                                                        <span class="px-2 py-1 rounded-md bg-up-primary/20 text-up-primary text-[10px] font-black uppercase whitespace-nowrap border border-up-primary/30">
                                                            ⭐ Wajib
                                                        </span>
                                                    @elseif($isTargetActive)
                                                        <span class="px-2 py-1 rounded-md bg-up-mint/20 text-up-mint text-[10px] font-bold uppercase whitespace-nowrap border border-up-mint/30">
                                                            ✓ Aktif
                                                        </span>
                                                    @else
                                                        <span class="text-[10px] text-ink-500 whitespace-nowrap italic px-1">
                                                            Dilewati
                                                        </span>
                                                    @endif
                                                </div>
                                            </td>

                                            <!-- Quick Action -->
                                            <td class="py-3 px-3 text-center">
                                                @if($isTargetActive)
                                                    <button type="button"
                                                            wire:click="clearColumnMapping('{{ $col['key'] }}')"
                                                            title="Abaikan kolom ini"
                                                            class="w-7 h-7 rounded-lg bg-black/5 dark:bg-white/5 hover:bg-up-red/20 text-ink-400 hover:text-up-red flex items-center justify-center transition cursor-pointer text-xs mx-auto">
                                                        ✕
                                                    </button>
                                                @else
                                                    <span class="text-ink-500 text-xs">-</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Sticky / Floating Action Bar -->
                    <div class="sticky bottom-4 z-30 p-3.5 sm:p-4 rounded-2xl bg-white/95 dark:bg-neutral-900/95 backdrop-blur-xl border border-black/10 dark:border-white/10 shadow-2xl transition">
                        <div class="flex flex-col sm:flex-row items-center justify-between gap-3 max-w-7xl mx-auto">
                            <!-- Validation badges -->
                            <div class="flex items-center gap-1.5 sm:gap-2 flex-wrap justify-center sm:justify-start w-full sm:w-auto text-xs">
                                <span class="text-[11px] font-bold text-ink-400 uppercase tracking-wider hidden sm:inline">Validasi:</span>
                                <span class="px-2.5 py-1 rounded-lg text-xs font-bold flex items-center gap-1 {{ $hasNama ? 'bg-up-mint/20 text-up-mint border border-up-mint/30' : 'bg-up-red/20 text-up-red border border-up-red/30 animate-pulse' }}">
                                    <span>{{ $hasNama ? '✓' : '⚠️' }}</span>
                                    <span>Nama {{ $hasNama ? '' : '[Wajib]' }}</span>
                                </span>
                                <span class="px-2.5 py-1 rounded-lg text-xs font-bold flex items-center gap-1 {{ $hasHarga ? 'bg-up-mint/20 text-up-mint border border-up-mint/30' : 'bg-up-red/20 text-up-red border border-up-red/30 animate-pulse' }}">
                                    <span>{{ $hasHarga ? '✓' : '⚠️' }}</span>
                                    <span>Harga {{ $hasHarga ? '' : '[Wajib]' }}</span>
                                </span>
                                <span class="px-2.5 py-1 rounded-lg text-xs font-bold flex items-center gap-1 {{ $hasSku ? 'bg-up-mint/20 text-up-mint border border-up-mint/30' : 'bg-up-amber/20 text-up-amber border border-up-amber/30' }}">
                                    <span>{{ $hasSku ? '✓' : 'ℹ️' }}</span>
                                    <span>SKU {{ $hasSku ? '✓' : '(Auto)' }}</span>
                                </span>
                            </div>

                            <!-- Buttons -->
                            <div class="flex items-center gap-2.5 w-full sm:w-auto justify-end">
                                <button type="button"
                                        wire:click="resetImport"
                                        class="w-auto px-4 py-2.5 rounded-xl bg-black/5 dark:bg-white/5 border border-black/10 dark:border-white/10 hover:bg-black/10 dark:hover:bg-white/10 text-ink-700 dark:text-ink-300 font-bold text-xs cursor-pointer transition whitespace-nowrap">
                                    ← Ganti File
                                </button>

                                <button type="button"
                                        wire:click="applyCustomMappingAndPreview"
                                        wire:loading.attr="disabled"
                                        class="flex-1 sm:flex-initial px-6 sm:px-8 py-2.5 rounded-xl bg-up-primary hover:bg-up-primary/90 text-white font-black text-xs cursor-pointer shadow-lg shadow-up-primary/25 transition flex items-center justify-center gap-2 whitespace-nowrap">
                                    <span wire:loading.remove wire:target="applyCustomMappingAndPreview">Terapkan Pemetaan &amp; Preview Data →</span>
                                    <span wire:loading wire:target="applyCustomMappingAndPreview">Menganalisis Kolom...</span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            @elseif($sudahPreview)
                <!-- Preview & Analysis Result Summary -->
                <div class="p-4 sm:p-5 rounded-2xl bg-up-mint/10 border border-up-mint/30 space-y-4">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 border-b border-up-mint/20 pb-3">
                        <div class="flex items-center gap-2.5">
                            <span class="w-8 h-8 rounded-xl bg-up-mint/20 text-up-mint flex items-center justify-center text-sm font-bold shadow-xs">✓</span>
                            <div>
                                <h3 class="text-xs sm:text-sm font-bold text-ink-900 dark:text-white">
                                    File Berhasil Dianalisis &amp; Siap Diimpor
                                </h3>
                                <p class="text-[11px] text-ink-500">
                                    {{ $formatMigrasi === 'sid_retail' ? 'Format SidRetail terdeteksi' : ($isCustomMapping ? 'Pemetaan kolom kustom berhasil diterapkan' : 'Format Standar Excel terdeteksi') }}
                                </p>
                            </div>
                        </div>
                        <div class="text-left sm:text-right">
                            <span class="text-[11px] text-ink-500 block">Estimasi Nilai Persediaan Awal:</span>
                            <span class="text-xs sm:text-sm font-mono font-bold text-up-mint">Rp {{ number_format($previewSummary['estimasi_nilai'] ?? 0, 0, ',', '.') }}</span>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-2.5 text-center text-xs">
                        <div class="p-2.5 rounded-xl bg-white dark:bg-[#0B1120] border border-ink-200/50 dark:border-white/5">
                            <div class="text-[10px] text-ink-500 uppercase tracking-wider">Total Baris File</div>
                            <div class="font-bold text-base text-ink-900 dark:text-white tabular-nums mt-0.5">{{ $previewSummary['total_baris'] ?? 0 }}</div>
                        </div>
                        <div class="p-2.5 rounded-xl bg-white dark:bg-[#0B1120] border border-ink-200/50 dark:border-white/5">
                            <div class="text-[10px] text-up-mint uppercase tracking-wider font-semibold">Baris Valid</div>
                            <div class="font-bold text-base text-up-mint tabular-nums mt-0.5">{{ $previewSummary['total_valid'] ?? 0 }}</div>
                        </div>
                        <div class="p-2.5 rounded-xl bg-white dark:bg-[#0B1120] border border-ink-200/50 dark:border-white/5">
                            <div class="text-[10px] text-up-red uppercase tracking-wider font-semibold">Peringatan / Dilewati</div>
                            <div class="font-bold text-base text-up-red tabular-nums mt-0.5">{{ $previewSummary['total_invalid'] ?? 0 }}</div>
                        </div>
                        <div class="p-2.5 rounded-xl bg-white dark:bg-[#0B1120] border border-ink-200/50 dark:border-white/5">
                            <div class="text-[10px] text-ink-500 uppercase tracking-wider">Total SKU Masuk</div>
                            <div class="font-bold text-base text-ink-900 dark:text-white tabular-nums mt-0.5">{{ $previewSummary['total_sku'] ?? 0 }}</div>
                        </div>
                    </div>

                    <!-- Actions in Preview state -->
                    <div class="flex flex-col sm:flex-row items-center justify-between gap-2.5 pt-2 border-t border-up-mint/20">
                        <div class="flex items-center gap-2 w-full sm:w-auto flex-wrap">
                            <button type="button"
                                    wire:click="resetImport"
                                    class="w-full sm:w-auto px-3.5 py-2 rounded-xl bg-black/5 dark:bg-white/5 hover:bg-black/10 dark:hover:bg-white/10 text-ink-700 dark:text-ink-300 font-bold text-xs cursor-pointer transition flex items-center justify-center gap-1.5 border border-black/10 dark:border-white/10">
                                <span>←</span>
                                <span>Ganti / Upload Ulang File</span>
                            </button>

                            @if($isCustomMapping)
                                <button type="button"
                                        wire:click="backToMapping"
                                        class="w-full sm:w-auto px-4 py-2 rounded-xl bg-up-primary/10 border border-up-primary/30 hover:bg-up-primary/20 text-up-primary font-bold text-xs cursor-pointer transition flex items-center justify-center gap-1.5">
                                    <span>⚙️</span>
                                    <span>Ubah Pemetaan Kolom</span>
                                </button>
                            @endif
                        </div>

                        <div class="text-[11px] text-ink-500 dark:text-ink-400 text-center sm:text-right">
                            Data akan otomatis diproses saat Anda menyelesaikan Langkah 6 (Finalisasi).
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
                            <input type="text" wire:model="qrisMerchantName" class="w-full px-3 py-2 rounded-lg border border-ink-300 dark:border-white/10 bg-white dark:bg-[#0B1120] text-xs sm:text-sm text-ink-900 dark:text-white" placeholder="Contoh: BREMSPACE OFFICIAL">
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
