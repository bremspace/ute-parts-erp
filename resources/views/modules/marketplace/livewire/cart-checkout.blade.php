<div class="max-w-4xl mx-auto px-4 sm:px-6 py-6 sm:py-10">
    @if($orderSuccess)
        <!-- ===== ORDER SUCCESS (Clean Editorial Receipt) ===== -->
        <div class="max-w-lg mx-auto text-center py-12 space-y-6">
            <div class="w-20 h-20 mx-auto rounded-3xl bg-up-mint/10 text-up-mint flex items-center justify-center shadow-lg shadow-up-mint/10">
                <svg class="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
                </svg>
            </div>

            <div class="space-y-2">
                <span class="text-xs font-bold uppercase tracking-wider text-up-mint">Pemesanan Selesai</span>
                <h1 class="text-2xl sm:text-3xl font-black text-ink-900 dark:text-white tracking-tight">Pesanan Telah Terdaftar</h1>
                <p class="text-xs sm:text-sm text-ink-500 dark:text-ink-400">
                    Nomor referensi pesanan: <strong class="text-ink-900 dark:text-white font-mono">{{ $orderNo }}</strong>
                </p>
            </div>

            <div class="rounded-3xl p-6 bg-white dark:bg-ink-900 border border-ink-100/80 dark:border-white/10 shadow-sm space-y-3">
                <div class="text-xs text-ink-400 font-semibold uppercase tracking-wider">Total Tagihan</div>
                <div class="text-3xl sm:text-4xl font-black text-up-primary tabular-nums">
                    Rp {{ number_format($orderTotal, 0, ',', '.') }}
                </div>
                <div class="p-3.5 rounded-2xl bg-amber-500/10 border border-amber-500/20 text-xs text-ink-700 dark:text-amber-200 text-left">
                    <p class="font-bold flex items-center gap-1.5 mb-1">
                        <span>⏳</span> Menunggu Konfirmasi & Pembayaran
                    </p>
                    <p class="text-[11px] leading-relaxed text-ink-500 dark:text-ink-400">
                        Sistem kasir & cabang telah menerima daftar suku cadang Anda. Selesaikan pembayaran saat ambil barang atau lewat instruksi resmi WhatsApp Ute Parts.
                    </p>
                </div>
            </div>

            <div class="flex flex-wrap gap-3 justify-center pt-2">
                <a href="{{ route('customer.account') }}"
                   class="px-6 py-3.5 rounded-2xl bg-ink-900 text-white dark:bg-white dark:text-ink-950 font-bold text-xs hover:opacity-90 transition-all min-h-[44px] flex items-center active:scale-[0.97]">
                    Lihat Status Pesanan
                </a>
                <a href="{{ route('shop') }}"
                   class="px-6 py-3.5 rounded-2xl bg-white dark:bg-ink-900 border border-ink-200/80 dark:border-white/10 text-ink-700 dark:text-ink-300 font-bold text-xs hover:bg-ink-50 transition-all min-h-[44px] flex items-center active:scale-[0.97]">
                    Lanjut Belanja
                </a>
            </div>
        </div>
    @elseif($mode === 'checkout')
        <!-- ===== CHECKOUT (Refined Clean 2-Column or Stacked Summary) ===== -->
        <div class="space-y-6">
            <div class="flex items-center justify-between pb-2">
                <div>
                    <h1 class="text-2xl sm:text-3xl font-black tracking-tight text-ink-900 dark:text-white">Penyelesaian Pesanan</h1>
                    <p class="text-xs text-ink-500 dark:text-ink-400">Pastikan detail penerima dan cabang penjemputan telah sesuai.</p>
                </div>
                <a href="{{ route('cart') }}" class="text-xs font-semibold text-up-primary hover:underline flex items-center gap-1">
                    ← Kembali ke Keranjang
                </a>
            </div>

            @auth('customer')
                <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
                    <!-- Form Column -->
                    <div class="lg:col-span-7 space-y-5">
                        <!-- Buyer Profile Card -->
                        <div class="bg-white dark:bg-ink-900 rounded-3xl border border-ink-100/80 dark:border-white/10 p-5 sm:p-6 shadow-[0_4px_20px_rgba(0,0,0,0.02)] space-y-2">
                            <div class="flex items-center justify-between">
                                <span class="text-xs font-bold uppercase tracking-wider text-ink-400 dark:text-ink-500">Profil Pelanggan</span>
                                <span class="text-[10px] font-bold bg-up-mint/10 text-up-mint px-2.5 py-0.5 rounded-full">TERVERIFIKASI</span>
                            </div>
                            <h3 class="text-base font-bold text-ink-900 dark:text-white">{{ $pelanggan->nama }}</h3>
                            <p class="text-xs text-ink-500 dark:text-ink-400 font-mono">{{ $pelanggan->telepon }}</p>
                        </div>

                        <!-- Delivery / Pickup Method Selection -->
                        <div class="bg-white dark:bg-ink-900 rounded-3xl border border-ink-100/80 dark:border-white/10 p-5 sm:p-6 shadow-[0_4px_20px_rgba(0,0,0,0.02)] space-y-4">
                            <span class="text-xs font-bold uppercase tracking-wider text-ink-400 dark:text-ink-500">Pilihan Pengambilan</span>
                            <div class="flex flex-col sm:flex-row gap-3">
                                <button
                                    type="button"
                                    wire:click="$set('metodeAmbil', 'ambil_ke_toko')"
                                    class="flex-1 p-3.5 sm:p-4 rounded-2xl border-2 text-left transition-all duration-200 cursor-pointer active:scale-[0.98] {{ $metodeAmbil === 'ambil_ke_toko' ? 'border-up-primary bg-up-primary/5 dark:bg-up-primary/10' : 'border-ink-200/80 dark:border-white/10 hover:border-ink-400' }}">
                                    <div class="text-lg">🏬</div>
                                    <div class="font-bold text-ink-900 dark:text-white text-xs sm:text-sm mt-1">Ambil di Toko</div>
                                    <div class="text-[11px] text-ink-400 mt-0.5">Bebas biaya ongkir</div>
                                </button>
                                <button
                                    type="button"
                                    wire:click="$set('metodeAmbil', 'pengiriman')"
                                    class="flex-1 p-3.5 sm:p-4 rounded-2xl border-2 text-left transition-all duration-200 cursor-pointer active:scale-[0.98] {{ $metodeAmbil === 'pengiriman' ? 'border-up-primary bg-up-primary/5 dark:bg-up-primary/10' : 'border-ink-200/80 dark:border-white/10 hover:border-ink-400' }}">
                                    <div class="text-lg">🚚</div>
                                    <div class="font-bold text-ink-900 dark:text-white text-xs sm:text-sm mt-1">Kirim Ekspedisi</div>
                                    <div class="text-[11px] text-ink-400 mt-0.5">Integrasi kurir Biteship</div>
                                </button>
                            </div>

                            <!-- Branch selector -->
                            <div class="space-y-1.5 pt-2">
                                <label class="block text-xs font-bold text-ink-700 dark:text-ink-300 uppercase tracking-wider">
                                    Pilih Lokasi Cabang
                                </label>
                                <div class="relative w-full min-w-0">
                                    <select wire:model.live="selectedCabangId"
                                            class="w-full min-w-0 max-w-full truncate appearance-none px-4 py-3 pr-8 rounded-2xl bg-ink-50 dark:bg-ink-950 border border-ink-200/80 dark:border-white/10 text-base sm:text-sm font-semibold text-ink-900 dark:text-white outline-none focus:border-up-primary transition-all min-h-[44px] cursor-pointer">
                                        @foreach($cabangs as $c)
                                            <option value="{{ $c->id }}">{{ $c->nama }} ({{ $c->kota ?? $c->alamat }})</option>
                                        @endforeach
                                    </select>
                                    <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none text-ink-400">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                        </svg>
                                    </div>
                                </div>
                            </div>

                            <!-- Note textarea -->
                            <div class="space-y-1.5 pt-2">
                                <label class="block text-xs font-bold text-ink-700 dark:text-ink-300 uppercase tracking-wider">
                                    Catatan Tambahan (Opsional)
                                </label>
                                <textarea wire:model="catatan" rows="2"
                                          placeholder="Contoh: Saya ambil siang ini sekitar jam 2..."
                                          class="w-full min-w-0 px-4 py-3 rounded-2xl bg-ink-50 dark:bg-ink-950 border border-ink-200/80 dark:border-white/10 text-base sm:text-sm text-ink-900 dark:text-white placeholder-ink-400 outline-none focus:border-up-primary transition-all"></textarea>
                            </div>
                        </div>
                    </div>

                    <!-- Order Summary Column -->
                    <div class="lg:col-span-5 space-y-5">
                        <div class="bg-white dark:bg-ink-900 rounded-3xl border border-ink-100/80 dark:border-white/10 p-5 sm:p-6 shadow-[0_4px_20px_rgba(0,0,0,0.02)] space-y-4">
                            <span class="text-xs font-bold uppercase tracking-wider text-ink-400 dark:text-ink-500">Rincian Belanja</span>
                            <div class="space-y-3 max-h-72 overflow-y-auto pr-1">
                                @foreach($cart as $item)
                                    <div class="flex items-center justify-between gap-3 py-2 border-b border-ink-100/60 dark:border-white/[0.06] last:border-0">
                                        <div class="min-w-0 space-y-0.5">
                                            <p class="text-xs font-bold text-ink-900 dark:text-white truncate">{{ $item['name'] }}</p>
                                            <p class="text-[11px] text-ink-400">
                                                {{ $item['qty'] }} × Rp {{ number_format($item['harga'], 0, ',', '.') }}
                                            </p>
                                        </div>
                                        <div class="text-xs font-bold text-ink-900 dark:text-white tabular-nums flex-shrink-0">
                                            Rp {{ number_format($item['harga'] * $item['qty'], 0, ',', '.') }}
                                        </div>
                                    </div>
                                @endforeach
                            </div>

                            <div class="pt-4 border-t border-ink-100/80 dark:border-white/10 space-y-2 text-xs">
                                <div class="flex justify-between items-center text-ink-600 dark:text-ink-400">
                                    <span>Subtotal</span>
                                    <span class="font-bold tabular-nums text-ink-900 dark:text-white">Rp {{ number_format($subtotal, 0, ',', '.') }}</span>
                                </div>
                                @if(!empty($pajakPreview['enabled']) && $pajakPreview['ppn_nominal'] > 0)
                                    <div class="flex justify-between items-center text-ink-500">
                                        <span>PPN ({{ $pajakPreview['ppn_percent'] }}%)</span>
                                        <span class="font-semibold tabular-nums">+ Rp {{ number_format($pajakPreview['ppn_nominal'], 0, ',', '.') }}</span>
                                    </div>
                                @endif
                                <div class="flex justify-between items-center pt-3 border-t border-ink-100/80 dark:border-white/10">
                                    <span class="font-bold text-ink-900 dark:text-white text-sm">Total Akhir</span>
                                    <span class="text-xl font-black text-up-primary tabular-nums">
                                        Rp {{ number_format($pajakPreview['total_akhir'] ?? $subtotal, 0, ',', '.') }}
                                    </span>
                                </div>
                            </div>

                            <button
                                type="button"
                                wire:click="placeOrder"
                                class="w-full py-4 rounded-2xl bg-up-primary hover:bg-up-primary-dark text-white font-bold text-sm shadow-[0_8px_24px_rgba(91,79,233,0.3)] transition-all duration-200 cursor-pointer active:scale-[0.98] min-h-[48px] flex items-center justify-center gap-2">
                                <span>Konfirmasi & Buat Pesanan</span>
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3" />
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>
            @else
                <!-- Guest View: Clean Gate -->
                <div class="text-center py-16 bg-white dark:bg-ink-900 rounded-3xl border border-ink-100/80 dark:border-white/10 p-8 space-y-4">
                    <div class="w-16 h-16 mx-auto rounded-3xl bg-up-primary/10 text-up-primary flex items-center justify-center">
                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                        </svg>
                    </div>
                    <div class="space-y-1">
                        <h2 class="text-lg font-bold text-ink-900 dark:text-white">Masuk untuk Menyelesaikan Pesanan</h2>
                        <p class="text-xs text-ink-500 dark:text-ink-400 max-w-sm mx-auto">
                            Dibutuhkan nomor telepon aktif untuk verifikasi ketersediaan suku cadang dan update status garansi.
                        </p>
                    </div>
                    <div class="flex flex-wrap gap-3 justify-center pt-2">
                        <a href="{{ route('customer.login') }}"
                           class="px-6 py-3.5 rounded-2xl bg-up-primary text-white font-bold text-xs hover:bg-up-primary-dark transition-all min-h-[44px] flex items-center active:scale-[0.97]">
                            Masuk Akun
                        </a>
                        <a href="{{ route('customer.register') }}"
                           class="px-6 py-3.5 rounded-2xl bg-ink-100 dark:bg-white/10 text-ink-800 dark:text-white font-bold text-xs hover:bg-ink-200 transition-all min-h-[44px] flex items-center active:scale-[0.97]">
                            Daftar Pelanggan Baru
                        </a>
                    </div>
                </div>
            @endauth
        </div>
    @else
        <!-- ===== CART (Clean Table & Order Review) ===== -->
        <div class="space-y-6">
            <div class="flex items-center justify-between pb-2">
                <div>
                    <h1 class="text-2xl sm:text-3xl font-black tracking-tight text-ink-900 dark:text-white">Keranjang Belanja</h1>
                    <p class="text-xs text-ink-500 dark:text-ink-400">Periksa suku cadang sebelum melanjutkan ke proses pengambilan.</p>
                </div>
                @if(count($cart) > 0)
                    <a href="{{ route('shop') }}" class="text-xs font-semibold text-up-primary hover:underline">
                        + Tambah Produk Lain
                    </a>
                @endif
            </div>

            @if(count($cart) === 0)
                <div class="text-center py-20 bg-white dark:bg-ink-900 rounded-3xl border border-ink-100/80 dark:border-white/10 p-8 space-y-4">
                    <div class="w-16 h-16 mx-auto rounded-3xl bg-ink-100 dark:bg-white/5 flex items-center justify-center text-ink-400">
                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
                        </svg>
                    </div>
                    <div class="space-y-1">
                        <h2 class="text-base font-bold text-ink-900 dark:text-white">Keranjang Anda Masih Kosong</h2>
                        <p class="text-xs text-ink-500 dark:text-ink-400">
                            Cari layar LCD, baterai pengganti, atau pesan jasa servis teknisi profesional.
                        </p>
                    </div>
                    <a href="{{ route('shop') }}"
                       class="inline-flex items-center gap-2 px-6 py-3.5 rounded-2xl bg-up-primary text-white font-bold text-xs hover:bg-up-primary-dark transition-all min-h-[44px] active:scale-[0.97]">
                        <span>Jelajahi Katalog Sparepart</span>
                    </a>
                </div>
            @else
                <div class="bg-white dark:bg-ink-900 rounded-3xl border border-ink-100/80 dark:border-white/10 p-5 sm:p-7 shadow-[0_4px_24px_rgba(0,0,0,0.02)] space-y-4">
                    <div class="space-y-4">
                        @foreach($cart as $itemKey => $item)
                            <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 sm:gap-4 py-3.5 border-b border-ink-100/70 dark:border-white/[0.06] last:border-0 min-w-0 w-full">
                                <div class="flex items-center gap-3 min-w-0 w-full sm:flex-1">
                                    <button
                                        type="button"
                                        wire:click.prevent="hapus('{{ $itemKey }}')"
                                        class="w-7 h-7 rounded-xl bg-ink-100/60 dark:bg-white/5 text-ink-400 hover:text-up-red hover:bg-up-red/10 flex items-center justify-center text-xs transition-colors cursor-pointer flex-shrink-0"
                                        title="Hapus dari keranjang">
                                        ✕
                                    </button>
                                    <div class="min-w-0 flex-1 space-y-0.5">
                                        <h4 class="text-xs sm:text-sm font-bold text-ink-900 dark:text-white truncate">{{ $item['name'] }}</h4>
                                        <p class="text-[11px] text-ink-500 dark:text-ink-400 truncate">
                                            {{ $item['variant_name'] ?? 'Standar' }}
                                            @if($item['alasan_harga'] !== 'Harga Retail Standar')
                                                <span class="text-up-mint font-medium">• {{ $item['alasan_harga'] }}</span>
                                            @endif
                                        </p>
                                    </div>
                                </div>

                                <div class="flex items-center justify-between sm:justify-end gap-3 sm:gap-5 w-full sm:w-auto min-w-0">
                                    <!-- Stepper button -->
                                    <div class="flex items-center bg-ink-50 dark:bg-ink-950 border border-ink-200/80 dark:border-white/10 rounded-2xl p-1 flex-shrink-0">
                                        <button
                                            type="button"
                                            wire:click="updateQty('{{ $itemKey }}', {{ $item['qty'] - 1 }})"
                                            class="w-7 h-7 rounded-xl bg-white dark:bg-ink-900 hover:bg-ink-100 dark:hover:bg-white/10 font-bold text-ink-700 dark:text-ink-300 transition-colors flex items-center justify-center cursor-pointer active:scale-[0.92] text-xs">
                                            −
                                        </button>
                                        <span class="w-8 text-center font-bold text-xs text-ink-900 dark:text-white tabular-nums">
                                            {{ $item['qty'] }}
                                        </span>
                                        <button
                                            type="button"
                                            wire:click="updateQty('{{ $itemKey }}', {{ $item['qty'] + 1 }})"
                                            class="w-7 h-7 rounded-xl bg-white dark:bg-ink-900 hover:bg-ink-100 dark:hover:bg-white/10 font-bold text-ink-700 dark:text-ink-300 transition-colors flex items-center justify-center cursor-pointer active:scale-[0.92] text-xs">
                                            +
                                        </button>
                                    </div>

                                    <div class="text-right sm:w-28 min-w-0">
                                        <span class="text-xs sm:text-sm font-black text-ink-900 dark:text-white tabular-nums block truncate">
                                            Rp {{ number_format($item['harga'] * $item['qty'], 0, ',', '.') }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <div class="pt-6 border-t border-ink-100/80 dark:border-white/10 flex flex-col sm:flex-row items-center justify-between gap-4">
                        <div class="text-xs text-ink-500 dark:text-ink-400">
                            Total pesanan terhitung untuk <strong class="text-ink-900 dark:text-white">{{ $jumlahItem }} suku cadang</strong>
                        </div>
                        <div class="flex items-baseline gap-2">
                            <span class="text-xs font-semibold text-ink-400 uppercase tracking-wider">Subtotal:</span>
                            <span class="text-2xl font-black text-up-primary tabular-nums">
                                Rp {{ number_format($subtotal, 0, ',', '.') }}
                            </span>
                        </div>
                    </div>
                </div>

                <div class="flex justify-end pt-2">
                    <button
                        type="button"
                        wire:click="lanjutCheckout"
                        class="w-full sm:w-auto px-10 py-4 rounded-2xl bg-up-primary hover:bg-up-primary-dark text-white font-bold text-sm shadow-[0_8px_24px_rgba(91,79,233,0.3)] transition-all duration-200 cursor-pointer active:scale-[0.98] min-h-[48px] flex items-center justify-center gap-2">
                        <span>Lanjutkan ke Pengambilan & Pembayaran</span>
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3" />
                        </svg>
                    </button>
                </div>
            @endif
        </div>
    @endif
</div>
