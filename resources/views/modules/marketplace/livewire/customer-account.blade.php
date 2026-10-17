<div class="max-w-4xl mx-auto px-4 sm:px-6 py-8">
    @if(!$customer)
        <div class="text-center py-16 bg-white dark:bg-ink-900 rounded-3xl border border-ink-100 dark:border-white/10 p-8 shadow-sm">
            <h2 class="font-black text-ink-900 dark:text-white text-lg">Silakan Masuk</h2>
            <p class="text-sm text-ink-500 dark:text-ink-400 mt-2">Login untuk melihat riwayat pesanan, servis, dan komisi Anda.</p>
            <a href="{{ route('customer.login') }}" class="inline-block mt-5 px-6 py-3 rounded-2xl bg-up-primary text-white font-bold text-sm hover:bg-up-primary-dark transition-colors shadow-md shadow-up-primary/20 min-h-[44px]">Masuk</a>
        </div>
    @else
        <!-- Profil header -->
        <div class="bg-white dark:bg-ink-900 rounded-3xl border border-ink-100 dark:border-white/10 p-6 mb-6 shadow-sm">
            <div class="flex items-center gap-4">
                <div class="w-14 h-14 rounded-2xl bg-gradient-to-tr from-up-primary to-up-accent flex items-center justify-center text-white font-black text-xl shadow-md">
                    {{ substr($customer->nama, 0, 1) }}
                </div>
                <div class="flex-1 min-w-0">
                    <h1 class="font-black text-ink-900 dark:text-white text-lg truncate">{{ $customer->nama }}</h1>
                    <p class="text-sm text-ink-500 dark:text-ink-400 font-mono">{{ $customer->telepon }}</p>
                </div>
                <div class="text-right flex-shrink-0">
                    <x-prism.tier-badge :tier="$customer->tierMembership?->nama ?? ($customer->is_reseller ? 'Reseller' : 'Retail')" />
                    <p class="text-xs text-ink-500 dark:text-ink-400 mt-2 tabular-nums">{{ $customer->poin_loyalty }} poin</p>
                </div>
            </div>

            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mt-5 text-center">
                <button wire:click="$set('activeTab', 'orders')" class="p-3 rounded-xl bg-ink-50 dark:bg-white/5 border border-ink-100 dark:border-white/10 hover:border-up-primary transition-[transform,border-color] active:scale-[0.98] cursor-pointer text-left min-h-[44px]">
                    <p class="font-black text-ink-900 dark:text-white tabular-nums text-lg">{{ $orders->count() }}</p>
                    <p class="text-[10px] text-ink-400 uppercase font-semibold">Pesanan</p>
                </button>
                <button wire:click="$set('activeTab', 'servis')" class="p-3 rounded-xl bg-ink-50 dark:bg-white/5 border border-ink-100 dark:border-white/10 hover:border-up-primary transition-[transform,border-color] active:scale-[0.98] cursor-pointer text-left min-h-[44px]">
                    <p class="font-black text-ink-900 dark:text-white tabular-nums text-lg">{{ $servis->count() }}</p>
                    <p class="text-[10px] text-ink-400 uppercase font-semibold">Servis</p>
                </button>
                <button wire:click="$set('activeTab', 'membership')" class="p-3 rounded-xl bg-ink-50 dark:bg-white/5 border border-ink-100 dark:border-white/10 hover:border-up-primary transition-[transform,border-color] active:scale-[0.98] cursor-pointer text-left min-h-[44px]">
                    <p class="font-black text-up-primary tabular-nums text-lg">{{ number_format($customer->poin_loyalty ?? 0, 0, ',', '.') }}</p>
                    <p class="text-[10px] text-ink-400 uppercase font-semibold">Poin Loyalty</p>
                </button>
                <button wire:click="$set('activeTab', '{{ $customer->is_reseller ? 'komisi' : 'membership' }}')" class="p-3 rounded-xl bg-ink-50 dark:bg-white/5 border border-ink-100 dark:border-white/10 hover:border-up-primary transition-[transform,border-color] active:scale-[0.98] cursor-pointer text-left min-h-[44px]">
                    <p class="font-black {{ $customer->is_reseller ? 'text-up-accent' : 'text-up-mint' }} tabular-nums text-lg truncate">
                        {{ $customer->is_reseller ? 'Rp ' . number_format($komisi->where('status', 'disetujui')->sum('nominal_komisi') + $komisi->where('status', 'pending')->sum('nominal_komisi'), 0, ',', '.') : 'Rp ' . number_format((float) ($customer->total_belanja_12bulan ?? 0), 0, ',', '.') }}
                    </p>
                    <p class="text-[10px] text-ink-400 uppercase font-semibold">{{ $customer->is_reseller ? 'Komisi' : 'Belanja 12 Bln' }}</p>
                </button>
            </div>
        </div>

        <!-- Tabs -->
        <div class="flex items-center gap-2 mb-5 overflow-x-auto scrollbar-none py-1 -my-1 flex-nowrap">
            @php
                $unreadNotifCount = $notifikasi->whereNull('read_at')->count();
                $tabList = [
                    'orders' => 'Pesanan',
                    'servis' => 'Tracking Servis',
                    'notifikasi' => 'Pemberitahuan',
                    'membership' => 'Status Membership & Poin',
                ];
                if ($customer->is_reseller) {
                    $tabList['komisi'] = 'Komisi Saya';
                }
            @endphp
            @foreach($tabList as $kode => $label)
                <button wire:click="$set('activeTab', '{{ $kode }}')" class="px-4 py-2.5 rounded-xl text-xs sm:text-sm font-bold transition-[transform,background-color] active:scale-[0.97] min-h-[44px] whitespace-nowrap cursor-pointer flex items-center gap-1.5 {{ $activeTab === $kode ? 'bg-up-primary text-white shadow-md shadow-up-primary/25' : 'bg-white dark:bg-ink-900 border border-ink-200 dark:border-white/10 text-ink-600 dark:text-ink-300 hover:bg-ink-50 dark:hover:bg-white/5' }}">
                    <span>{{ $label }}</span>
                    @if($kode === 'notifikasi' && $unreadNotifCount > 0)
                        <span class="px-1.5 py-0.2 rounded-full text-[10px] font-mono font-bold bg-up-red text-white">
                            {{ $unreadNotifCount }}
                        </span>
                    @endif
                </button>
            @endforeach
        </div>

        <!-- ORDERS -->
        @if($activeTab === 'orders')
            <div class="space-y-3">
                @forelse($orders as $o)
                    <div class="bg-white dark:bg-ink-900 rounded-3xl border border-ink-100 dark:border-white/10 p-5 shadow-sm space-y-3">
                        <div class="flex items-center justify-between flex-wrap gap-2">
                            <div>
                                <span class="font-mono text-up-primary font-bold text-sm">{{ $o->no_transaksi }}</span>
                                <span class="text-xs text-ink-400 ml-2">{{ $o->created_at->format('d/m/Y H:i') }}</span>
                            </div>
                            <x-prism.status-pill :status="$o->status" />
                        </div>

                        <!-- Status Details & Tracking Banner -->
                        @if($o->status === 'menunggu_pembayaran')
                            <div class="p-3.5 rounded-2xl bg-up-amber/10 border border-up-amber/20 text-xs text-ink-700 dark:text-amber-200 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3">
                                <div>
                                    <p class="font-bold text-up-amber flex items-center gap-1.5">
                                        <span class="w-2 h-2 rounded-full bg-up-amber animate-pulse"></span>
                                        <span>Menunggu Pembayaran</span>
                                    </p>
                                    <p class="text-[11px] text-ink-500 dark:text-ink-400 mt-0.5">Selesaikan pembayaran agar barang segera disiapkan oleh tim gudang.</p>
                                </div>
                                <div class="flex items-center gap-2">
                                    <button
                                        type="button"
                                        wire:click="bayarPesanan({{ $o->id }})"
                                        class="px-3.5 py-1.5 rounded-xl text-xs font-bold text-white bg-up-primary hover:bg-indigo-600 shadow-sm transition-colors cursor-pointer"
                                    >
                                        Bayar Sekarang (Simulasi)
                                    </button>
                                    <button
                                        type="button"
                                        wire:click="batalkanPesanan({{ $o->id }})"
                                        wire:confirm="Batalkan pesanan ini?"
                                        class="px-2.5 py-1.5 rounded-xl text-xs font-bold text-ink-500 hover:text-up-red transition-colors cursor-pointer"
                                    >
                                        Batal
                                    </button>
                                </div>
                            </div>
                        @elseif($o->status === 'lunas')
                            <div class="p-3 rounded-2xl bg-up-primary/10 border border-up-primary/20 text-xs text-ink-700 dark:text-indigo-200 flex items-center gap-2">
                                <span class="w-2 h-2 rounded-full bg-up-primary animate-pulse"></span>
                                <span>Pembayaran Terverifikasi — Menunggu giliran pengemasan di toko.</span>
                            </div>
                        @elseif($o->status === 'diproses')
                            <div class="p-3 rounded-2xl bg-indigo-500/10 border border-indigo-500/20 text-xs text-ink-700 dark:text-indigo-200 flex items-center gap-2">
                                <span class="w-2 h-2 rounded-full bg-indigo-400 animate-pulse"></span>
                                <span>Sedang Dikemas — Tim staf kami sedang menyiapkan suku cadang Anda.</span>
                            </div>
                        @elseif($o->status === 'dikirim')
                            <div class="p-3.5 rounded-2xl bg-sky-500/10 border border-sky-500/20 text-xs text-ink-700 dark:text-sky-200 space-y-1">
                                <div class="font-bold text-sky-400 flex items-center gap-1.5">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/></svg>
                                    <span>Paket Sedang Dikirim via {{ $o->pengiriman?->kurir ?? 'Ekspedisi' }}</span>
                                </div>
                                <p class="text-[11px] text-ink-600 dark:text-ink-300">
                                    Nomor Resi: <strong class="font-mono text-up-primary">{{ $o->pengiriman?->tracking_id ?: 'Dalam penjemputan kurir' }}</strong>
                                </p>
                            </div>
                        @elseif($o->status === 'siap_diambil')
                            <div class="p-3.5 rounded-2xl bg-up-mint/10 border border-up-mint/20 text-xs text-ink-700 dark:text-emerald-200 space-y-1">
                                <div class="font-bold text-up-mint flex items-center gap-1.5">
                                    <span>🏬</span>
                                    <span>Siap Diambil di Konter Toko</span>
                                </div>
                                <p class="text-[11px] text-ink-600 dark:text-ink-300">
                                    Suku cadang sudah siap diambil di cabang <strong>{{ $o->cabang?->nama }}</strong>. Tunjukkan nomor pesanan ini ke kasir.
                                </p>
                            </div>
                        @elseif($o->status === 'selesai')
                            <div class="p-3 rounded-2xl bg-up-mint/10 border border-up-mint/20 text-xs text-ink-700 dark:text-emerald-200 flex items-center gap-2">
                                <span class="text-up-mint font-bold">✓</span>
                                <span>Pesanan Selesai. Terima kasih telah berbelanja di Ute Parts!</span>
                            </div>
                        @elseif($o->status === 'batal')
                            <div class="p-3 rounded-2xl bg-up-red/10 border border-up-red/20 text-xs text-up-red flex items-center gap-2">
                                <span>✕</span>
                                <span>Pesanan Telah Dibatalkan.</span>
                            </div>
                        @endif

                        <div class="flex flex-wrap gap-1.5">
                            @foreach($o->items as $it)
                                <span class="text-[11px] px-2.5 py-1 rounded-xl bg-ink-50 dark:bg-white/5 border border-ink-100 dark:border-white/10 text-ink-600 dark:text-ink-300">{{ $it->produk?->nama }} ×{{ $it->jumlah }}</span>
                            @endforeach
                        </div>

                        <div class="flex justify-between items-center pt-3 border-t border-ink-100 dark:border-white/10 text-xs">
                            <span class="text-ink-500 dark:text-ink-400">{{ $o->cabang?->nama }}</span>
                            <span class="font-black text-up-primary tabular-nums text-sm">Rp {{ number_format($o->total_akhir, 0, ',', '.') }}</span>
                        </div>
                    </div>
                @empty
                    <div class="text-center py-14 bg-white dark:bg-ink-900 rounded-3xl border border-ink-100 dark:border-white/10 p-6 shadow-sm">
                        <p class="font-semibold text-ink-700 dark:text-ink-300">Belum ada pesanan</p>
                        <a href="{{ route('shop') }}" class="inline-block mt-4 px-5 py-2.5 rounded-2xl bg-up-primary text-white font-bold text-sm hover:bg-up-primary-dark transition-colors min-h-[44px]">Mulai Belanja</a>
                    </div>
                @endforelse
            </div>
        @endif

        <!-- NOTIFIKASI KONSUMEN -->
        @if($activeTab === 'notifikasi')
            <div class="space-y-3">
                @forelse($notifikasi as $notif)
                    <div
                        wire:click="markNotifikasiRead({{ $notif->id }})"
                        class="bg-white dark:bg-ink-900 rounded-3xl border transition-all duration-150 p-4 sm:p-5 shadow-sm cursor-pointer {{ is_null($notif->read_at) ? 'border-up-primary/40 bg-up-primary/[0.02]' : 'border-ink-100 dark:border-white/10 opacity-75' }}"
                    >
                        <div class="flex items-start gap-3">
                            <div class="p-2 rounded-xl flex-shrink-0 {{ is_null($notif->read_at) ? 'bg-up-primary/10 text-up-primary' : 'bg-black/5 dark:bg-white/5 text-ink-400' }}">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center justify-between gap-2 mb-1">
                                    <h4 class="text-xs font-bold text-ink-900 dark:text-white truncate">{{ $notif->judul }}</h4>
                                    <span class="text-[10px] text-ink-400 font-mono">{{ $notif->created_at?->diffForHumans() }}</span>
                                </div>
                                <p class="text-xs text-ink-600 dark:text-ink-300 leading-relaxed">{{ $notif->konten }}</p>
                                @if(is_null($notif->read_at))
                                    <div class="mt-2 flex items-center gap-1 text-[10px] text-up-primary font-bold">
                                        <span class="w-1.5 h-1.5 rounded-full bg-up-primary animate-pulse"></span>
                                        <span>Baru</span>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="text-center py-14 bg-white dark:bg-ink-900 rounded-3xl border border-ink-100 dark:border-white/10 p-6 shadow-sm">
                        <p class="font-semibold text-ink-700 dark:text-ink-300">Belum ada pemberitahuan</p>
                        <p class="text-xs text-ink-400 mt-1">Status pesanan dan servis Anda akan ditampilkan di sini.</p>
                    </div>
                @endforelse
            </div>
        @endif

        <!-- SERVIS TRACKING -->
        @if($activeTab === 'servis')
            <div class="space-y-3">
                @forelse($servis as $sv)
                    <div class="bg-white dark:bg-ink-900 rounded-3xl border border-ink-100 dark:border-white/10 p-5 shadow-sm">
                        <div class="flex items-center justify-between">
                            <div>
                                <span class="font-mono text-up-primary font-bold text-sm">{{ $sv->no_tiket }}</span>
                                <span class="font-bold text-ink-900 dark:text-white ml-2 text-sm">{{ $sv->jenis_hp }}</span>
                            </div>
                            <x-prism.status-pill :status="$sv->status" />
                        </div>
                        <p class="text-xs text-ink-500 dark:text-ink-400 mt-2">{{ $sv->keluhan }}</p>
                        @if($sv->estimasi_biaya !== null || $sv->status_pembayaran)
                            <div class="flex flex-wrap items-center gap-2 mt-2 pt-2 border-t border-ink-50 dark:border-white/5 text-xs">
                                @if($sv->estimasi_biaya !== null)
                                    <span class="text-ink-500 dark:text-ink-400">
                                        Estimasi Biaya: <strong class="text-ink-900 dark:text-white tabular-nums">Rp {{ number_format($sv->estimasi_biaya, 0, ',', '.') }}</strong>
                                    </span>
                                @endif
                                @if($sv->status_pembayaran)
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold {{ $sv->status_pembayaran === 'lunas' ? 'bg-up-mint/10 text-up-mint' : 'bg-up-amber/10 text-up-amber' }}">
                                        {{ $sv->status_pembayaran === 'lunas' ? 'Lunas' : 'Belum Bayar' }}
                                    </span>
                                @endif
                            </div>
                        @endif
                        <div class="flex items-center justify-between mt-3 pt-3 border-t border-ink-100 dark:border-white/10">
                            <span class="text-xs {{ $sv->garansi && $sv->garansi->active ? 'text-up-mint font-bold' : 'text-ink-400' }}">
                                {{ $sv->garansi && $sv->garansi->active ? '🛡️ Dalam Garansi s.d ' . $sv->garansi->tanggal_berakhir->format('d/m/Y') : 'Garansi ' . ($sv->garansi ? 'habis' : 'belum ada') }}
                            </span>
                            @if($sv->token_approval)
                                <a href="{{ url('/tracking/' . $sv->token_approval) }}" target="_blank" class="text-up-primary font-bold text-xs hover:underline">Lihat Tracking & Persetujuan →</a>
                            @endif
                        </div>
                    </div>
                @empty
                    <div class="text-center py-14 bg-white dark:bg-ink-900 rounded-3xl border border-ink-100 dark:border-white/10 p-6 shadow-sm">
                        <p class="font-semibold text-ink-700 dark:text-ink-300">Belum ada servis</p>
                    </div>
                @endforelse
            </div>
        @endif

        <!-- KOMISI -->
        @if($activeTab === 'komisi' && $customer->is_reseller)
            <div class="space-y-3">
                @forelse($komisi as $k)
                    <div class="bg-white dark:bg-ink-900 rounded-3xl border border-ink-100 dark:border-white/10 p-5 shadow-sm">
                        <div class="flex items-center justify-between">
                            <span class="font-mono text-up-accent font-bold text-sm">{{ $k->no_komisi }}</span>
                            <x-prism.status-pill :status="$k->status" />
                        </div>
                        <div class="flex justify-between items-center mt-2">
                            <span class="text-xs text-ink-500 dark:text-ink-400">{{ $k->keterangan }}</span>
                            <span class="font-black text-up-accent tabular-nums">Rp {{ number_format($k->nominal_komisi, 0, ',', '.') }}</span>
                        </div>
                    </div>
                @empty
                    <div class="text-center py-14 bg-white dark:bg-ink-900 rounded-3xl border border-ink-100 dark:border-white/10 p-6 shadow-sm">
                        <p class="font-semibold text-ink-700 dark:text-ink-300">Belum ada komisi</p>
                        <p class="text-xs text-ink-400 mt-1">Komisi otomatis terhitung dari transaksi atas nama Anda.</p>
                    </div>
                @endforelse
            </div>
        @endif
        <!-- MEMBERSHIP & POIN LOYALTY -->
        @if($activeTab === 'membership')
            <div class="space-y-6">
                <!-- Status Saat Ini & Keuntungan -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <!-- Kartu Tier -->
                    <div class="bg-white dark:bg-ink-900 rounded-2xl border border-ink-100 dark:border-white/10 p-6 relative overflow-hidden">
                        <div class="flex items-center justify-between mb-4">
                            <div>
                                <span class="text-xs text-ink-400 uppercase tracking-wider font-semibold">Tingkat Membership</span>
                                <h3 class="text-xl font-black text-ink-900 dark:text-white mt-0.5">{{ $membershipInfo['tierName'] }}</h3>
                            </div>
                            <x-prism.tier-badge :tier="$membershipInfo['tierName']" />
                        </div>

                        <div class="space-y-2.5 pt-2 border-t border-ink-100 dark:border-white/10 text-xs">
                            <div class="flex justify-between items-center">
                                <span class="text-ink-500 dark:text-ink-400">🏷️ Potongan Harga Katalog:</span>
                                <span class="font-bold text-ink-900 dark:text-white">{{ $membershipInfo['diskonPersen'] > 0 ? $membershipInfo['diskonPersen'] . '%' : 'Harga Normal' }}</span>
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="text-ink-500 dark:text-ink-400">⚡ Pengali Poin Loyalty:</span>
                                <span class="font-bold text-up-primary">{{ $membershipInfo['poinMultiplier'] }}x Poin</span>
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="text-ink-500 dark:text-ink-400">💰 Akumulasi Belanja 12 Bulan:</span>
                                <span class="font-bold text-ink-900 dark:text-white tabular-nums">Rp {{ number_format($membershipInfo['totalBelanja'], 0, ',', '.') }}</span>
                            </div>
                        </div>

                        <!-- Progress Bar ke Tier Berikutnya -->
                        <div class="mt-5 pt-4 border-t border-ink-100 dark:border-white/10">
                            @if($membershipInfo['nextTier'])
                                <div class="flex justify-between items-center text-xs mb-1.5">
                                    <span class="text-ink-500 dark:text-ink-400">Target ke <strong class="text-ink-900 dark:text-white">{{ $membershipInfo['nextTier']->nama }}</strong></span>
                                    <span class="font-bold text-up-primary tabular-nums">{{ $membershipInfo['progress'] }}%</span>
                                </div>
                                <div class="w-full h-2 rounded-full bg-ink-100 dark:bg-white/10 overflow-hidden">
                                    <div class="h-full bg-gradient-to-r from-up-primary to-up-mint rounded-full transition-[width] duration-500" style="width: {{ $membershipInfo['progress'] }}%"></div>
                                </div>
                                <p class="text-[11px] text-ink-400 mt-2">
                                    Belanja <strong class="text-ink-800 dark:text-ink-200 tabular-nums">Rp {{ number_format($membershipInfo['kekurangan'], 0, ',', '.') }}</strong> lagi untuk naik tingkat.
                                </p>
                            @else
                                <div class="p-3 rounded-xl bg-up-mint/10 border border-up-mint/20 text-center">
                                    <p class="text-xs font-bold text-up-mint">👑 Tingkat Membership Tertinggi</p>
                                    <p class="text-[11px] text-ink-500 dark:text-ink-400 mt-0.5">Anda menikmati diskon dan multiplier poin maksimal.</p>
                                </div>
                            @endif
                        </div>
                    </div>

                    <!-- Kartu Poin Loyalty -->
                    <div class="bg-white dark:bg-ink-900 rounded-2xl border border-ink-100 dark:border-white/10 p-6 flex flex-col justify-between">
                        <div>
                            <span class="text-xs text-ink-400 uppercase tracking-wider font-semibold">Saldo Poin Loyalty</span>
                            <div class="flex items-baseline gap-2 mt-1">
                                <span class="text-3xl font-black text-up-primary tabular-nums">{{ number_format($membershipInfo['poinLoyalty'], 0, ',', '.') }}</span>
                                <span class="text-sm font-bold text-ink-500">Poin</span>
                            </div>
                            <p class="text-xs text-ink-500 dark:text-ink-400 mt-3 leading-relaxed">
                                Poin diperoleh otomatis dari setiap transaksi belanja suku cadang atau servis yang telah lunas.
                            </p>
                        </div>

                        <div class="mt-4 p-3.5 rounded-xl bg-ink-50 dark:bg-white/5 border border-ink-100 dark:border-white/10 text-xs space-y-1.5">
                            <p class="font-bold text-ink-900 dark:text-white">💡 Cara Perolehan Poin:</p>
                            <p class="text-ink-500 dark:text-ink-400">
                                Setiap kelipatan <strong class="text-ink-800 dark:text-ink-200">Rp 1.000</strong> belanja = <strong>1 poin dasar</strong> × multiplier tier (<strong class="text-up-primary">{{ $membershipInfo['poinMultiplier'] }}x</strong>).
                            </p>
                            <p class="text-[11px] text-ink-400">
                                Contoh: Belanja Rp 100.000 memperoleh <span class="font-bold text-up-primary tabular-nums">{{ (int)(100 * $membershipInfo['poinMultiplier']) }} poin</span>.
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Perbandingan Seluruh Tingkatan Level -->
                <div class="bg-white dark:bg-ink-900 rounded-2xl border border-ink-100 dark:border-white/10 p-6">
                    <h3 class="text-base font-bold text-ink-900 dark:text-white mb-1">Tingkatan Level Membership</h3>
                    <p class="text-xs text-ink-500 dark:text-ink-400 mb-4">Level dievaluasi otomatis berdasarkan akumulasi total belanja Anda selama 12 bulan terakhir.</p>

                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
                        <!-- Level Retail / Dasar -->
                        <div class="p-4 rounded-xl border {{ !$membershipInfo['currentTier'] && !$customer->is_reseller ? 'border-up-primary bg-up-primary/5 ring-2 ring-up-primary/20' : 'border-ink-100 dark:border-white/10 bg-ink-50 dark:bg-white/5' }}">
                            <div class="flex items-center justify-between mb-2">
                                <span class="font-black text-ink-900 dark:text-white text-sm">Retail</span>
                                @if(!$membershipInfo['currentTier'] && !$customer->is_reseller)
                                    <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-up-primary text-white">Aktif</span>
                                @endif
                            </div>
                            <p class="text-[11px] text-ink-400">Syarat: <span class="font-semibold text-ink-700 dark:text-ink-300">Belanja awal</span></p>
                            <div class="mt-3 pt-2 border-t border-ink-100 dark:border-white/10 text-xs space-y-1">
                                <p class="text-ink-600 dark:text-ink-300">Diskon: <strong class="text-ink-900 dark:text-white">0%</strong></p>
                                <p class="text-ink-600 dark:text-ink-300">Multiplier: <strong class="text-ink-900 dark:text-white">1.0x</strong></p>
                            </div>
                        </div>

                        <!-- Tier dari Database -->
                        @foreach($membershipInfo['allTiers'] as $tier)
                            @php
                                $isCurrent = $membershipInfo['currentTier']?->id === $tier->id;
                            @endphp
                            <div class="p-4 rounded-xl border {{ $isCurrent ? 'border-up-primary bg-up-primary/5 ring-2 ring-up-primary/20' : 'border-ink-100 dark:border-white/10 bg-ink-50 dark:bg-white/5' }}">
                                <div class="flex items-center justify-between mb-2">
                                    <span class="font-black text-ink-900 dark:text-white text-sm">{{ $tier->nama }}</span>
                                    @if($isCurrent)
                                        <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-up-primary text-white">Aktif</span>
                                    @endif
                                </div>
                                <p class="text-[11px] text-ink-400">
                                    Min. Belanja: <strong class="text-ink-700 dark:text-ink-300 tabular-nums">Rp {{ number_format($tier->min_belanja_12bulan, 0, ',', '.') }}</strong>
                                </p>
                                <div class="mt-3 pt-2 border-t border-ink-100 dark:border-white/10 text-xs space-y-1">
                                    <p class="text-ink-600 dark:text-ink-300">Diskon: <strong class="text-ink-900 dark:text-white">{{ $tier->diskon_persen }}%</strong></p>
                                    <p class="text-ink-600 dark:text-ink-300">Multiplier: <strong class="text-up-primary">{{ $tier->poin_multiplier }}x</strong></p>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        @endif
    @endif
</div>