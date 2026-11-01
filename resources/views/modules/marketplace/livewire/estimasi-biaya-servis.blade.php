<div class="max-w-6xl mx-auto px-4 sm:px-6 py-6 sm:py-10 space-y-8 sm:space-y-12">
    <!-- Header Hero -->
    <div class="rounded-3xl bg-gradient-to-br from-[#0F172A] via-[#1E1B4B] to-[#0F172A] border border-white/10 p-6 sm:p-10 text-white shadow-2xl relative overflow-hidden">
        <div class="max-w-2xl space-y-3 relative z-10">
            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-[11px] font-bold uppercase tracking-wider bg-up-primary/80 backdrop-blur-md">
                <span class="w-1.5 h-1.5 rounded-full bg-up-mint animate-pulse"></span>
                <span>Kalkulator Transparan Teknisi</span>
            </span>
            <h1 class="text-2xl sm:text-4xl font-black tracking-tight leading-tight">
                Estimasi Biaya <span class="bg-gradient-to-r from-up-mint to-sky-400 bg-clip-text text-transparent">Servis &amp; Suku Cadang</span>
            </h1>
            <p class="text-xs sm:text-sm text-slate-300 leading-relaxed">
                Hitung simulasi total ongkos servis HP Anda sebelum datang ke toko. Jasa pengerjaan teknisi profesional + harga part transparan tanpa biaya tersembunyi.
            </p>
        </div>
        <div class="absolute -right-10 -bottom-10 w-72 h-72 bg-up-primary/20 rounded-full blur-3xl pointer-events-none"></div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">
        <!-- Left: Form Pemilihan Layanan & Gadget -->
        <div class="lg:col-span-7 space-y-6">
            <!-- Step 1: Pilih Jenis Kerusakan / Layanan -->
            <div class="p-5 sm:p-6 rounded-3xl bg-white dark:bg-ink-900 border border-ink-100 dark:border-white/10 shadow-sm space-y-4">
                <div class="flex items-center gap-2 text-xs font-bold uppercase tracking-wider text-up-primary">
                    <span class="w-6 h-6 rounded-full bg-up-primary/10 flex items-center justify-center">1</span>
                    <span>Pilih Kategori Kerusakan / Tindakan Servis</span>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                    @foreach($listJenis as $jenis)
                    <button type="button"
                            wire:click="selectJenis({{ $jenis->id }})"
                            class="p-3.5 rounded-2xl border text-left transition-all duration-140 active:scale-[0.98] cursor-pointer flex flex-col justify-between space-y-2 {{ $selectedJenisServisId === $jenis->id ? 'bg-up-primary/10 border-up-primary text-ink-900 dark:text-white shadow-sm' : 'bg-white dark:bg-ink-950/40 border-ink-100 dark:border-white/5 hover:border-ink-300 text-ink-700 dark:text-ink-300' }}">
                        <div class="font-bold text-xs sm:text-sm line-clamp-1">{{ $jenis->nama }}</div>
                        <div class="flex items-center justify-between text-[11px]">
                            <span class="text-ink-400">Jasa Teknisi:</span>
                            <span class="font-bold text-up-primary">Rp {{ number_format($jenis->biaya_jasa, 0, ',', '.') }}</span>
                        </div>
                    </button>
                    @endforeach
                </div>
            </div>

            <!-- Step 2: Input Model Gadget & Pilih Part (Opsional) -->
            <div class="p-5 sm:p-6 rounded-3xl bg-white dark:bg-ink-900 border border-ink-100 dark:border-white/10 shadow-sm space-y-4">
                <div class="flex items-center gap-2 text-xs font-bold uppercase tracking-wider text-up-primary">
                    <span class="w-6 h-6 rounded-full bg-up-primary/10 flex items-center justify-center">2</span>
                    <span>Tipe Smartphone &amp; Suku Cadang Pengganti</span>
                </div>

                <div class="space-y-2">
                    <label class="block text-xs font-semibold text-ink-600 dark:text-ink-300">Ketik Model HP Anda (Contoh: iPhone 11, Samsung A52, Redmi Note 10):</label>
                    <input type="text"
                           wire:model.live.debounce.300ms="gadgetModel"
                           placeholder="Ketik tipe atau seri gadget..."
                           class="w-full px-4 py-3 rounded-2xl bg-ink-50 dark:bg-black/30 border border-ink-200 dark:border-white/10 text-xs sm:text-sm text-ink-900 dark:text-white focus:outline-none focus:border-up-primary">
                </div>

                @if($partCandidates->isNotEmpty())
                <div class="space-y-2 pt-2">
                    <label class="block text-[11px] font-bold text-ink-400 uppercase tracking-wider">Pilih Suku Cadang yang Cocok:</label>
                    <div class="space-y-2 max-h-60 overflow-y-auto pr-1">
                        @foreach($partCandidates as $cand)
                        <div wire:click="$set('selectedPartId', {{ $cand->id }})"
                             class="p-3 rounded-xl border flex items-center justify-between gap-3 transition-colors cursor-pointer {{ $selectedPartId === $cand->id ? 'bg-up-mint/10 border-up-mint' : 'bg-white dark:bg-black/20 border-ink-100 dark:border-white/5 hover:border-ink-300' }}">
                            <div class="min-w-0 flex-1">
                                <div class="text-xs font-bold text-ink-900 dark:text-white truncate">{{ $cand->nama }}</div>
                                <div class="text-[10px] text-ink-400 font-mono">SKU: {{ $cand->kode_produk }}</div>
                            </div>
                            <div class="text-right shrink-0">
                                <div class="text-xs font-black text-up-primary">Rp {{ number_format($cand->harga_jual_retail, 0, ',', '.') }}</div>
                                <span class="text-[10px] font-semibold {{ $selectedPartId === $cand->id ? 'text-up-mint' : 'text-ink-400' }}">
                                    {{ $selectedPartId === $cand->id ? '✓ Terpilih' : '+ Pasang Part' }}
                                </span>
                            </div>
                        </div>
                        @endforeach
                    </div>
                </div>
                @endif
            </div>
        </div>

        <!-- Right: Rincian Nota Estimasi & Tombol Booking -->
        <div class="lg:col-span-5 space-y-6 lg:sticky lg:top-24">
            <div class="p-6 rounded-3xl bg-white dark:bg-ink-900 border border-ink-100 dark:border-white/10 shadow-xl space-y-5">
                <div class="border-b border-ink-100 dark:border-white/10 pb-4">
                    <span class="text-[10px] font-bold uppercase tracking-wider text-ink-400">Ringkasan Estimasi Biaya</span>
                    <h2 class="text-xl font-black text-ink-900 dark:text-white mt-1">{{ $webConfig['store_name'] ?? config('app.name', 'Store') }} Service Quote</h2>
                </div>

                <div class="space-y-3 text-xs">
                    <div class="flex items-center justify-between">
                        <span class="text-ink-500 dark:text-ink-400">Tindakan / Layanan:</span>
                        <span class="font-bold text-ink-900 dark:text-white text-right max-w-[200px] truncate">
                            {{ $activeJenis?->nama ?: '(Belum Memilih)' }}
                        </span>
                    </div>

                    <div class="flex items-center justify-between">
                        <span class="text-ink-500 dark:text-ink-400">Ongkos Jasa Teknisi:</span>
                        <span class="font-bold text-ink-900 dark:text-white">
                            Rp {{ number_format($biayaJasa, 0, ',', '.') }}
                        </span>
                    </div>

                    <div class="flex items-start justify-between gap-2">
                        <span class="text-ink-500 dark:text-ink-400">Komponen Part:</span>
                        <div class="text-right">
                            <span class="font-bold text-ink-900 dark:text-white block">
                                {{ $selectedPart ? 'Rp ' . number_format($biayaPart, 0, ',', '.') : 'Belum Memilih' }}
                            </span>
                            @if($selectedPart)
                                <span class="text-[10px] text-ink-400 truncate block max-w-[180px]">{{ $selectedPart->nama }}</span>
                            @endif
                        </div>
                    </div>

                    <div class="flex items-center justify-between text-up-mint">
                        <span>Garansi Toko Resmi:</span>
                        <span class="font-bold">{{ $activeJenis?->durasi_garansi_hari ?? 7 }} Hari Garansi</span>
                    </div>
                </div>

                <div class="pt-4 border-t border-ink-100 dark:border-white/10 flex items-center justify-between">
                    <div>
                        <span class="text-[11px] text-ink-400 block">Total Perkiraan Biaya:</span>
                        <div class="text-2xl font-black text-up-primary">
                            Rp {{ number_format($totalEstimasi, 0, ',', '.') }}
                        </div>
                    </div>
                </div>

                <div class="space-y-2 pt-2">
                    @php
                        $storeName = $webConfig['store_name'] ?? config('app.name', 'Toko');
                        $msgWa = "Halo {$storeName}, saya menghitung estimasi servis di website:\n- Layanan: " . ($activeJenis?->nama ?? '-') . "\n- Gadget: " . ($gadgetModel ?: '-') . "\n- Part: " . ($selectedPart?->nama ?? 'Tanpa Part') . "\n- Total Estimasi: Rp " . number_format($totalEstimasi, 0, ',', '.') . "\nMohon konfirmasi ketersediaan teknisi dan jadwal servis.";
                        $waPhone = preg_replace('/[^0-9]/', '', $webConfig['store_phone'] ?? '628123456789');
                    @endphp
                    <a href="https://wa.me/{{ $waPhone }}?text={{ urlencode($msgWa) }}"
                       target="_blank"
                       class="w-full py-3.5 px-4 rounded-2xl bg-up-primary hover:bg-up-primary/90 text-white font-bold text-xs sm:text-sm text-center shadow-lg shadow-up-primary/25 transition-[background-color,transform] duration-140 active:scale-95 flex items-center justify-center gap-2 cursor-pointer">
                        <span>Konsultasi &amp; Booking Jadwal</span>
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                    </a>

                    <a href="{{ route('servis.booking') }}"
                       class="w-full py-2.5 text-center text-xs text-ink-500 hover:text-up-primary font-semibold block transition-colors">
                        Isi Form Booking Servis Online &rarr;
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
